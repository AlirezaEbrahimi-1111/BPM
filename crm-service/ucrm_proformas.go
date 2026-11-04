package main

// ═══════════════════════════════════════════════════════════════════
//  پیش‌فاکتورهای فروشِ شایگان در CRM — صفحهٔ «پیش‌فاکتورهای باز و پیگیری‌ها»
//  (pages/ucrm-proformas.php، طرح ۱۴۰۵/۰۸/۱۳).
//
//  خودِ پیش‌فاکتور مالِ شایگان است و فقط خوانده می‌شود:
//    InvoiceAbsList/GetInvoiceAbsList با Domain.InvType = 13 (پیش‌فاکتور فروش)
//    → شماره، تاریخ، JobCostingGuid (مشتری)، مبلغ‌های خلاصه. ردیف‌های کالا لازم نیست.
//  (Invoice/Get فیلترِ From/To روی نوع را نادیده می‌گیرد و ردیف‌ها را هم می‌آورد — سنگین.)
//  مشتری با JobCostingGuid = ucrm_customers.shygun_guid به CRM وصل می‌شود.
//  مبلغ شایگان ریال است؛ CRM تومان نشان می‌دهد (÷۱۰).
//
//  مالِ CRM (جدول ucrm_proforma_states): وضعیت + تاریخ پیگیری بعدی.
//    not_started (پیش‌فرض، بدون ردیف) | approved («تکمیل») | stopped («لغو»)
//    «عقب افتاده» = not_started با تاریخ پیگیریِ گذشته (CURDATE دیتابیس).
//
//  پیش‌بارگذاری: فهرست پیش‌فاکتورها (هر ۱۰ دقیقه) و کالاهای شایگان (هر ۳۰ دقیقه)
//  در پس‌زمینه خوانده و در حافظه نگه داشته می‌شوند تا صفحه منتظر شایگان نماند.
// ═══════════════════════════════════════════════════════════════════

import (
	"bytes"
	"database/sql"
	"fmt"
	"log"
	"math"
	"net/http"
	"sort"
	"strconv"
	"strings"
	"sync"
	"time"
)

const (
	shygunProformaType   = 13
	shygunProformaEvery  = 10 * time.Minute
	shygunItemsEvery     = 30 * time.Minute
	pfLateDays           = 7 // کارت «معوق (بیش از ۷ روز)»
	pfStNotStarted       = "not_started"
	pfStApproved         = "approved"
	pfStStopped          = "stopped"
	pfStOverdue          = "overdue" // محاسبه‌ای، ذخیره نمی‌شود
	shygunMaxPageLoops   = 1000      // سقف امنیتی صفحه‌خوانی
	shygunPageSize       = 100
	shygunPageReqTimeout = 30 * time.Second
)

// ──────────────── ردیف‌های شایگان ────────────────

type shygunAbsInvoice struct {
	InvoiceNumber               int64   `json:"InvoiceNumber"`
	InvoiceType                 int     `json:"InvoiceType"`
	InvoiceDate                 string  `json:"InvoiceDate"` // «2026/11/03 00:00:00»
	JobCostingName              string  `json:"JobCostingName"`
	JobCostingGuid              string  `json:"JobCostingGuid"`
	InvoiceGuid                 string  `json:"InvoiceGuid"`
	BodiesSummaryMasterCurrency float64 `json:"BodiesSummaryMasterCurrency"`
	DiscountMasterCurrency      float64 `json:"DiscountMasterCurrency"`
	ExpSummaryMasterCurrency    float64 `json:"ExpSummaryMasterCurrency"`
}

type shygunItemRow struct {
	ItemCode      string `json:"ItemCode"`
	ItemDesc      string `json:"ItemDesc"`
	CInactive     bool   `json:"CInactive"`
	Unit1Name     string `json:"Unit1Name"`
	ItemGroupName string `json:"ItemGroupName"`
	GuId          string `json:"GuId"`
}

// پیش‌فاکتورِ ساده‌شده (کش)
type shygunProforma struct {
	GUID       string
	Number     int64
	Date       string // Y-m-d میلادی
	JobGUID    string
	JobName    string
	AmountRial float64
}

type shygunItem struct {
	Code  string `json:"code"`
	Name  string `json:"name"`
	Unit  string `json:"unit"`
	Group string `json:"group"`
	GUID  string `json:"guid"`
}

// ──────────────── کش‌ها (پس‌زمینه پر می‌کند، هندلرها فقط می‌خوانند) ────────────────

var shygunPfCache struct {
	mu        sync.RWMutex
	rows      []shygunProforma
	fetchedAt time.Time
	lastErr   string
}

var shygunItemCache struct {
	mu        sync.RWMutex
	rows      []shygunItem
	fetchedAt time.Time
	lastErr   string
}

// shygunPaged همهٔ صفحه‌های یک متدِ فهرستیِ شایگان را می‌خواند. چون سقفِ
// اندازهٔ صفحه در متدهای مختلف فرق دارد (۲۰ تا ۱۰۰)، RowStart به اندازهٔ
// ردیف‌های واقعاً برگشته جلو می‌رود و تا صفحهٔ خالی ادامه می‌دهد.
func shygunPaged[T any](s *server, path string, domain map[string]any) ([]T, error) {
	out := []T{}
	start := 0
	var prev []byte
	for i := 0; i < shygunMaxPageLoops; i++ {
		req := shygunGetReq{Config: shygunConnConfig{ConnectionName: s.cfg.ShygunConn}, Domain: domain}
		data, err := s.shygunPost(fmt.Sprintf("%s?RowStart=%d&RowCount=%d", path, start, shygunPageSize), req, shygunPageReqTimeout)
		if err != nil {
			return nil, err
		}
		if prev != nil && bytes.Equal(data, prev) {
			break // RowStart نادیده گرفته شد — همان صفحه دوباره آمد
		}
		prev = data
		rows, err := shygunResult[T](data)
		if err != nil {
			return nil, err
		}
		if len(rows) == 0 {
			break
		}
		out = append(out, rows...)
		start += len(rows)
	}
	return out, nil
}

func (s *server) shygunFetchProformas() ([]shygunProforma, error) {
	rows, err := shygunPaged[shygunAbsInvoice](s, "/api/InvoiceAbsList/GetInvoiceAbsList",
		map[string]any{"InvType": shygunProformaType})
	if err != nil {
		return nil, err
	}
	out := make([]shygunProforma, 0, len(rows))
	seen := map[string]bool{}
	for _, r := range rows {
		g := strings.ToLower(strings.TrimSpace(r.InvoiceGuid))
		if r.InvoiceType != shygunProformaType || g == "" || seen[g] {
			continue
		}
		seen[g] = true
		d := strings.ReplaceAll(strings.TrimSpace(r.InvoiceDate), "/", "-")
		if len(d) >= 10 {
			d = d[:10]
		}
		out = append(out, shygunProforma{
			GUID:       g,
			Number:     r.InvoiceNumber,
			Date:       d,
			JobGUID:    strings.ToLower(strings.TrimSpace(r.JobCostingGuid)),
			JobName:    faLetters(strings.TrimSpace(r.JobCostingName)),
			AmountRial: r.BodiesSummaryMasterCurrency - r.DiscountMasterCurrency + r.ExpSummaryMasterCurrency,
		})
	}
	return out, nil
}

func (s *server) shygunFetchItems() ([]shygunItem, error) {
	rows, err := shygunPaged[shygunItemRow](s, "/api/Item/Get", map[string]any{})
	if err != nil {
		return nil, err
	}
	out := make([]shygunItem, 0, len(rows))
	seen := map[string]bool{}
	for _, r := range rows {
		code := strings.TrimSpace(r.ItemCode)
		if r.CInactive || code == "" || seen[code] {
			continue
		}
		seen[code] = true
		out = append(out, shygunItem{
			Code: code, Name: strings.TrimSpace(r.ItemDesc), Unit: strings.TrimSpace(r.Unit1Name),
			Group: strings.TrimSpace(r.ItemGroupName), GUID: strings.ToLower(strings.TrimSpace(r.GuId)),
		})
	}
	return out, nil
}

func (s *server) refreshShygunProformas() {
	rows, err := s.shygunFetchProformas()
	shygunPfCache.mu.Lock()
	defer shygunPfCache.mu.Unlock()
	if err != nil {
		shygunPfCache.lastErr = err.Error()
		log.Printf("shygun proformas refresh: %v", err)
		return // کشِ قبلی می‌ماند
	}
	shygunPfCache.rows, shygunPfCache.fetchedAt, shygunPfCache.lastErr = rows, time.Now(), ""
}

func (s *server) refreshShygunItems() {
	rows, err := s.shygunFetchItems()
	shygunItemCache.mu.Lock()
	defer shygunItemCache.mu.Unlock()
	if err != nil {
		shygunItemCache.lastErr = err.Error()
		log.Printf("shygun items refresh: %v", err)
		return
	}
	shygunItemCache.rows, shygunItemCache.fetchedAt, shygunItemCache.lastErr = rows, time.Now(), ""
}

// startShygunWarmers: پیش‌بارگذاریِ پس‌زمینه از لحظهٔ بالا آمدنِ سرویس.
func (s *server) startShygunWarmers() {
	loop := func(every time.Duration, fn func()) {
		fn()
		t := time.NewTicker(every)
		for range t.C {
			fn()
		}
	}
	go loop(shygunProformaEvery, s.refreshShygunProformas)
	go loop(shygunItemsEvery, s.refreshShygunItems)
}

// ──────────────── GET /ucrm/shygun/items[?refresh=1] ────────────────
// کلِ فهرستِ کالاها یک‌جا (برای جستجوی سمتِ مرورگر بدون انتظار).

func (s *server) ucrmShygunItems(w http.ResponseWriter, r *http.Request) {
	if r.URL.Query().Get("refresh") == "1" {
		s.refreshShygunItems()
	}
	shygunItemCache.mu.RLock()
	defer shygunItemCache.mu.RUnlock()
	if shygunItemCache.rows == nil && shygunItemCache.lastErr != "" {
		writeErr(w, http.StatusBadGateway, "اتصال به نرم‌افزار حسابداری برقرار نشد: "+shygunItemCache.lastErr)
		return
	}
	items := shygunItemCache.rows
	if items == nil {
		items = []shygunItem{}
	}
	writeJSON(w, http.StatusOK, map[string]any{
		"items": items, "loading": shygunItemCache.rows == nil, "fetched_at": jalaliDateTime(shygunItemCache.fetchedAt),
	})
}

// ──────────────── پیوند مشتری و وضعیت‌ها ────────────────

type pfCustomer struct {
	ID      int64
	Name    string
	RepID   int64
	RepName string
}

// مشتری‌های CRM که به تفصیلیِ شایگان وصل‌اند: shygun_guid → مشتری
func (s *server) pfCustomersByGuid() (map[string]pfCustomer, error) {
	rows, err := s.db.Query(`
		SELECT c.id, c.name, COALESCE(c.rep_id, 0), COALESCE(CONCAT(u.first_name, ' ', u.last_name), ''), LOWER(c.shygun_guid)
		FROM ucrm_customers c
		LEFT JOIN users u ON u.id = c.rep_id
		WHERE c.organization_id = ? AND c.is_deleted = 0 AND c.shygun_guid IS NOT NULL AND c.shygun_guid <> ''`, ucrmOrg)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	m := map[string]pfCustomer{}
	for rows.Next() {
		var c pfCustomer
		var g string
		if err := rows.Scan(&c.ID, &c.Name, &c.RepID, &c.RepName, &g); err != nil {
			return nil, err
		}
		m[strings.TrimSpace(g)] = c
	}
	return m, rows.Err()
}

type pfState struct {
	Status   string
	Followup string // Y-m-d یا خالی
	Late     bool   // پیگیری گذشته
	LateDays int
}

func (s *server) pfStates() (map[string]pfState, error) {
	rows, err := s.db.Query(`
		SELECT invoice_guid, status, COALESCE(DATE_FORMAT(next_followup, '%Y-%m-%d'), ''),
		       (next_followup IS NOT NULL AND next_followup < CURDATE()),
		       COALESCE(DATEDIFF(CURDATE(), next_followup), 0)
		FROM ucrm_proforma_states WHERE organization_id = ?`, ucrmOrg)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	m := map[string]pfState{}
	for rows.Next() {
		var g string
		var st pfState
		var late int
		if err := rows.Scan(&g, &st.Status, &st.Followup, &late, &st.LateDays); err != nil {
			return nil, err
		}
		st.Late = late == 1
		m[strings.ToLower(g)] = st
	}
	return m, rows.Err()
}

func pfNumber(p shygunProforma) string {
	jy := 0
	if t, err := time.Parse("2006-01-02", p.Date); err == nil {
		jy, _, _ = gregorianToJalali(t.Year(), int(t.Month()), t.Day())
	}
	return fmt.Sprintf("PF-%d-%04d", jy, p.Number)
}

// ──────────────── GET /ucrm/proformas?from=&to=&rep=&customer= ────────────────
// customer داده شود → همهٔ پیش‌فاکتورهای همان مشتری (بازهٔ تاریخ نادیده).

func (s *server) ucrmListProformas(w http.ResponseWriter, r *http.Request) {
	uid := userOf(r.Context()).ID
	ro := s.ucrmRolesOf(uid)
	q := r.URL.Query()
	from, to := strings.TrimSpace(q.Get("from")), strings.TrimSpace(q.Get("to"))
	repF, _ := strconv.ParseInt(q.Get("rep"), 10, 64)
	custF, _ := strconv.ParseInt(q.Get("customer"), 10, 64)
	if custF > 0 {
		from, to = "", ""
	}

	shygunPfCache.mu.RLock()
	all := shygunPfCache.rows
	fetchedAt := shygunPfCache.fetchedAt
	lastErr := shygunPfCache.lastErr
	shygunPfCache.mu.RUnlock()
	if all == nil && lastErr != "" {
		writeErr(w, http.StatusBadGateway, "اتصال به نرم‌افزار حسابداری برقرار نشد: "+lastErr)
		return
	}

	custs, err := s.pfCustomersByGuid()
	if err != nil {
		writeDBErr(w, "ucrmListProformas customers", err)
		return
	}
	states, err := s.pfStates()
	if err != nil {
		writeDBErr(w, "ucrmListProformas states", err)
		return
	}

	type rowOut struct {
		GUID         string `json:"guid"`
		Number       string `json:"number"`
		CustomerID   int64  `json:"customer_id"`
		CustomerName string `json:"customer_name"`
		RepName      string `json:"rep_name"`
		Amount       int64  `json:"amount"` // تومان
		IssueDate    string `json:"issue_date"`
		NextFollowup string `json:"next_followup"`
		Status       string `json:"status"`
		CanAct       bool   `json:"can_act"`
	}
	items := []rowOut{}
	var cOpen, cLate, cApproved int
	var cSum int64
	for _, p := range all {
		if from != "" && p.Date < from {
			continue
		}
		if to != "" && p.Date > to {
			continue
		}
		c, linked := custs[p.JobGUID]
		if custF > 0 && (!linked || c.ID != custF) {
			continue
		}
		if !ro.seesAll() && (!linked || c.RepID != uid) {
			continue // کارشناس: فقط مشتری‌های خودش
		}
		if repF > 0 && (!linked || c.RepID != repF) {
			continue
		}
		st, ok := states[p.GUID]
		if !ok {
			st = pfState{Status: pfStNotStarted}
		}
		status := st.Status
		if status == pfStNotStarted && st.Late {
			status = pfStOverdue
		}
		name := p.JobName
		var cid int64
		rep := ""
		if linked {
			name, cid, rep = c.Name, c.ID, c.RepName
		}
		amount := int64(math.Round(p.AmountRial / 10))
		items = append(items, rowOut{
			GUID: p.GUID, Number: pfNumber(p), CustomerID: cid, CustomerName: name, RepName: strings.TrimSpace(rep),
			Amount: amount, IssueDate: p.Date, NextFollowup: st.Followup, Status: status,
			CanAct: ro.seesAll() || (linked && c.RepID == uid),
		})
		switch status {
		case pfStNotStarted, pfStOverdue:
			cOpen++
			cSum += amount
			if status == pfStOverdue && st.LateDays > pfLateDays {
				cLate++
			}
		case pfStApproved:
			cApproved++
		}
	}
	sort.SliceStable(items, func(i, j int) bool {
		if items[i].IssueDate != items[j].IssueDate {
			return items[i].IssueDate > items[j].IssueDate
		}
		return items[i].Number > items[j].Number
	})
	rate := 0
	if len(items) > 0 {
		rate = int(math.Round(float64(cApproved) * 100 / float64(len(items))))
	}
	writeJSON(w, http.StatusOK, map[string]any{
		"items":      items,
		"cards":      map[string]any{"open": cOpen, "sum": cSum, "late": cLate, "rate": rate},
		"loading":    all == nil,
		"fetched_at": jalaliDateTime(fetchedAt),
	})
}

// ──────────────── عملیات: تکمیل / لغو / تغییر تاریخ پیگیری ────────────────

// pfAuthorize: پیش‌فاکتور باید در کش باشد؛ مدیر/حسابداری همه، کارشناس فقط مشتری خودش.
func (s *server) pfAuthorize(w http.ResponseWriter, r *http.Request) (string, bool) {
	uid := userOf(r.Context()).ID
	ro := s.ucrmRolesOf(uid)
	g := strings.ToLower(strings.TrimSpace(r.PathValue("guid")))
	var p *shygunProforma
	shygunPfCache.mu.RLock()
	for i := range shygunPfCache.rows {
		if shygunPfCache.rows[i].GUID == g {
			cp := shygunPfCache.rows[i]
			p = &cp
			break
		}
	}
	shygunPfCache.mu.RUnlock()
	if p == nil {
		writeErr(w, http.StatusNotFound, "پیش‌فاکتور یافت نشد")
		return "", false
	}
	if ro.seesAll() {
		return g, true
	}
	var repID int64
	err := s.db.QueryRow(`SELECT COALESCE(rep_id, 0) FROM ucrm_customers
		WHERE organization_id = ? AND is_deleted = 0 AND LOWER(shygun_guid) = ? LIMIT 1`, ucrmOrg, p.JobGUID).Scan(&repID)
	if err != nil && err != sql.ErrNoRows {
		writeDBErr(w, "pfAuthorize", err)
		return "", false
	}
	if repID != uid {
		writeErr(w, http.StatusForbidden, "این پیش‌فاکتور مربوط به مشتری‌های شما نیست")
		return "", false
	}
	return g, true
}

// POST /ucrm/proformas/{guid}/status  {"status":"approved"|"stopped"|"not_started"}
func (s *server) ucrmProformaStatus(w http.ResponseWriter, r *http.Request) {
	g, ok := s.pfAuthorize(w, r)
	if !ok {
		return
	}
	var in struct {
		Status string `json:"status"`
	}
	if !decodeJSON(w, r, &in, 1<<12) {
		return
	}
	msgs := map[string]string{
		pfStApproved:   "پیش‌فاکتور «تأیید شده» شد.",
		pfStStopped:    "پیش‌فاکتور «متوقف شده» شد.",
		pfStNotStarted: "پیش‌فاکتور دوباره باز شد.",
	}
	msg, valid := msgs[in.Status]
	if !valid {
		writeErr(w, http.StatusBadRequest, "وضعیت نامعتبر است")
		return
	}
	uid := userOf(r.Context()).ID
	if _, err := s.db.Exec(`
		INSERT INTO ucrm_proforma_states (invoice_guid, organization_id, status, status_by, status_at, updated_by)
		VALUES (?, ?, ?, ?, NOW(), ?)
		ON DUPLICATE KEY UPDATE status = VALUES(status), status_by = VALUES(status_by), status_at = NOW(), updated_by = VALUES(updated_by)`,
		g, ucrmOrg, in.Status, uid, uid); err != nil {
		writeDBErr(w, "ucrmProformaStatus", err)
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "message": msg})
}

// PUT /ucrm/proformas/{guid}/followup  {"date":"2026-11-10"} (خالی = حذف تاریخ)
func (s *server) ucrmProformaFollowup(w http.ResponseWriter, r *http.Request) {
	g, ok := s.pfAuthorize(w, r)
	if !ok {
		return
	}
	var in struct {
		Date string `json:"date"`
	}
	if !decodeJSON(w, r, &in, 1<<12) {
		return
	}
	in.Date = strings.TrimSpace(in.Date)
	var date any
	if in.Date != "" {
		if _, err := time.Parse("2006-01-02", in.Date); err != nil {
			writeErr(w, http.StatusBadRequest, "تاریخ پیگیری نامعتبر است")
			return
		}
		date = in.Date
	}
	uid := userOf(r.Context()).ID
	if _, err := s.db.Exec(`
		INSERT INTO ucrm_proforma_states (invoice_guid, organization_id, next_followup, updated_by)
		VALUES (?, ?, ?, ?)
		ON DUPLICATE KEY UPDATE next_followup = VALUES(next_followup), updated_by = VALUES(updated_by)`,
		g, ucrmOrg, date, uid); err != nil {
		writeDBErr(w, "ucrmProformaFollowup", err)
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "message": "تاریخ پیگیری ثبت شد."})
}
