package main

// ═══════════════════════════════════════════════════════════════════
//  سفارش‌های فروش (شایگان ۱۱) و خرید (۱۲) در CRM — pages/ucrm-orders.php
//  (۱۴۰۵/۰۸/۱۷).
//
//  فهرست = سفارش‌های ثبت‌شده در خودِ شایگان (Invoice/Get با فیلتر
//  InvoiceType.In — فرمت From/To نادیده گرفته می‌شود؛ پیش‌بارگذاری هر ۱۰ دقیقه)
//  + سفارش‌های صادرشده در CRM که هنوز به شایگان نرفته‌اند (ucrm_proforma_docs.go).
//
//  وضعیت در شایگان فیلدِ جدا ندارد؛ از سند حساب می‌شود (طبق داده‌ی واقعی):
//    ControlCheck = false                  → unchecked  «کنترل نشده»
//    باقی‌ماندهٔ همهٔ ردیف‌ها (OrderRemain1) = 0 → delivered  «تحویل کامل»
//    بخشی تحویل شده                         → partial    «تحویل ناقص»
//    هیچ تحویلی                              → open       «باز»
//  سندهای CRM: crm_draft «ثبت در CRM» | pending_accounting | rejected.
//  مبلغ شایگان ریال است؛ تومان = ÷۱۰.
//
//  دسترسی: سفارش خرید فعلا فقط مدیر/سوپروایزر. سفارش فروش مثل مشتری‌ها —
//  مدیر/حسابداری همه؛ کارشناس فقط مشتری‌های خودش (پیوند با shygun_job_no).
// ═══════════════════════════════════════════════════════════════════

import (
	"log"
	"math"
	"net/http"
	"sort"
	"strconv"
	"strings"
	"sync"
	"time"
)

type shygunOrderLine struct {
	Quan           float64 `json:"Quan"`
	Amount         float64 `json:"Amount"`
	LineDiscAmount float64 `json:"LineDiscAmount"`
	OrderRemain1   float64 `json:"OrderRemain1"`
	FinalOrderQuan float64 `json:"FinalOrderQuan"`
}

type shygunOrderInv struct {
	Body             []shygunOrderLine `json:"Body"`
	InvTyp           int               `json:"InvTyp"`
	InvNo            int64             `json:"InvNo"`
	InvDate          string            `json:"InvDate"`
	JobCostingNumber string            `json:"JobCostingNumber"`
	JobCostingName   string            `json:"JobCostingName"`
	DiscAmount       float64           `json:"DiscAmount"`
	ControlCheck     bool              `json:"ControlCheck"`
	GuId             string            `json:"GuId"`
}

type shygunOrder struct {
	GUID       string
	Type       int
	Number     int64
	Date       string
	JobNo      string
	JobName    string
	AmountRial float64
	Status     string
}

const (
	ordStUnchecked = "unchecked"
	ordStOpen      = "open"
	ordStPartial   = "partial"
	ordStDelivered = "delivered"
)

var shygunOrdCache struct {
	mu        sync.RWMutex
	rows      map[int][]shygunOrder // 11 | 12
	fetchedAt time.Time
	lastErr   string
}

func shygunOrderStatus(o shygunOrderInv) string {
	if !o.ControlCheck {
		return ordStUnchecked
	}
	var ordered, remain float64
	for _, l := range o.Body {
		q := l.FinalOrderQuan
		if q <= 0 {
			q = l.Quan
		}
		ordered += q
		remain += l.OrderRemain1
	}
	switch {
	case remain <= 0:
		return ordStDelivered
	case remain < ordered:
		return ordStPartial
	default:
		return ordStOpen
	}
}

func (s *server) shygunFetchOrders(t int) ([]shygunOrder, error) {
	rows, err := shygunPaged[shygunOrderInv](s, "/api/Invoice/Get",
		map[string]any{"InvoiceType": map[string]any{"In": []string{strconv.Itoa(t)}}})
	if err != nil {
		return nil, err
	}
	out := make([]shygunOrder, 0, len(rows))
	seen := map[string]bool{}
	for _, r := range rows {
		g := strings.ToLower(strings.TrimSpace(r.GuId))
		if r.InvTyp != t || g == "" || seen[g] {
			continue
		}
		seen[g] = true
		amt := -r.DiscAmount
		for _, l := range r.Body {
			amt += l.Amount - l.LineDiscAmount
		}
		d := strings.ReplaceAll(strings.TrimSpace(r.InvDate), "/", "-")
		if len(d) >= 10 {
			d = d[:10]
		}
		out = append(out, shygunOrder{
			GUID: g, Type: t, Number: r.InvNo, Date: d, JobNo: strings.TrimSpace(r.JobCostingNumber),
			JobName: faLetters(strings.TrimSpace(r.JobCostingName)), AmountRial: amt, Status: shygunOrderStatus(r),
		})
	}
	return out, nil
}

func (s *server) refreshShygunOrders() {
	res := map[int][]shygunOrder{}
	for _, t := range []int{11, 12} {
		rows, err := s.shygunFetchOrders(t)
		if err != nil {
			shygunOrdCache.mu.Lock()
			shygunOrdCache.lastErr = err.Error()
			shygunOrdCache.mu.Unlock()
			log.Printf("shygun orders %d refresh: %v", t, err)
			return // کشِ قبلی می‌ماند
		}
		res[t] = rows
	}
	shygunOrdCache.mu.Lock()
	shygunOrdCache.rows, shygunOrdCache.fetchedAt, shygunOrdCache.lastErr = res, time.Now(), ""
	shygunOrdCache.mu.Unlock()
}

// مشتری‌های CRM که به تفصیلیِ شایگان وصل‌اند: shygun_job_no → مشتری
func (s *server) pfCustomersByJobNo() (map[string]pfCustomer, error) {
	rows, err := s.db.Query(`
		SELECT c.id, c.name, COALESCE(c.rep_id, 0), COALESCE(CONCAT(u.first_name, ' ', u.last_name), ''), c.shygun_job_no
		FROM ucrm_customers c
		LEFT JOIN users u ON u.id = c.rep_id
		WHERE c.organization_id = ? AND c.is_deleted = 0 AND c.shygun_job_no IS NOT NULL AND c.shygun_job_no <> ''`, ucrmOrg)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	m := map[string]pfCustomer{}
	for rows.Next() {
		var c pfCustomer
		var jn string
		if err := rows.Scan(&c.ID, &c.Name, &c.RepID, &c.RepName, &jn); err != nil {
			return nil, err
		}
		c.RepName = strings.TrimSpace(c.RepName)
		m[strings.TrimSpace(jn)] = c
	}
	return m, rows.Err()
}

// ──────────────── GET /ucrm/orders?type=sales|purchase&from=&to=&rep=&customer= ────────────────

func (s *server) ucrmListOrders(w http.ResponseWriter, r *http.Request) {
	uid := userOf(r.Context()).ID
	ro := s.ucrmRolesOf(uid)
	q := r.URL.Query()
	purchase := q.Get("type") == "purchase"
	docType, shType := docSalesOrder, 11
	if purchase {
		docType, shType = docPurchaseOrder, 12
	}
	if !canDoc(ro, docType) {
		writeErr(w, http.StatusForbidden, "سفارش‌های خرید فعلا فقط برای مدیران است")
		return
	}
	from, to := strings.TrimSpace(q.Get("from")), strings.TrimSpace(q.Get("to"))
	repF, _ := strconv.ParseInt(q.Get("rep"), 10, 64)
	custF, _ := strconv.ParseInt(q.Get("customer"), 10, 64)
	if custF > 0 {
		from, to = "", ""
	}

	shygunOrdCache.mu.RLock()
	all := shygunOrdCache.rows[shType]
	loaded := shygunOrdCache.rows != nil
	fetchedAt, lastErr := shygunOrdCache.fetchedAt, shygunOrdCache.lastErr
	shygunOrdCache.mu.RUnlock()
	if !loaded && lastErr != "" {
		writeErr(w, http.StatusBadGateway, "اتصال به نرم‌افزار حسابداری برقرار نشد: "+lastErr)
		return
	}
	custs, err := s.pfCustomersByJobNo()
	if err != nil {
		writeDBErr(w, "ucrmListOrders customers", err)
		return
	}

	type rowOut struct {
		GUID         string `json:"guid"`
		Number       string `json:"number"`
		CustomerID   int64  `json:"customer_id"`
		PartyName    string `json:"party_name"`
		RepName      string `json:"rep_name"`
		Amount       int64  `json:"amount"` // تومان
		IssueDate    string `json:"issue_date"`
		ExpectedDate string `json:"expected_date"`
		Status       string `json:"status"`
		Source       string `json:"source"`           // shygun | crm
		DocID        int64  `json:"doc_id,omitempty"` // سندِ CRM
	}
	items := []rowOut{}
	var cOpen, cDelivered int
	var cOpenSum int64
	count := func(status string, amount int64) {
		switch status {
		case ordStDelivered:
			cDelivered++
		case ordStOpen, ordStPartial, ordStUnchecked:
			cOpen++
			cOpenSum += amount
		}
	}
	for _, o := range all {
		if (from != "" && o.Date < from) || (to != "" && o.Date > to) {
			continue
		}
		c, linked := custs[o.JobNo]
		if !purchase {
			if (custF > 0 && (!linked || c.ID != custF)) || (!ro.seesAll() && (!linked || c.RepID != uid)) ||
				(repF > 0 && (!linked || c.RepID != repF)) {
				continue
			}
		}
		name, cid, rep := o.JobName, int64(0), ""
		if linked && !purchase {
			name, cid, rep = c.Name, c.ID, c.RepName
		}
		amount := int64(math.Round(o.AmountRial / 10))
		jy := 0
		if t, err := time.Parse("2006-01-02", o.Date); err == nil {
			jy, _, _ = gregorianToJalali(t.Year(), int(t.Month()), t.Day())
		}
		prefix := "SO"
		if purchase {
			prefix = "PO"
		}
		items = append(items, rowOut{
			GUID: o.GUID, Number: prefix + "-" + strconv.Itoa(jy) + "-" + leftPad4(o.Number), CustomerID: cid,
			PartyName: name, RepName: rep, Amount: amount, IssueDate: o.Date, Status: o.Status, Source: "shygun",
		})
		count(o.Status, amount)
	}

	crmRows, err := s.pfCrmRows(docType)
	if err != nil {
		writeDBErr(w, "ucrmListOrders crm", err)
		return
	}
	for _, x := range crmRows {
		if (from != "" && x.IssueDate < from) || (to != "" && x.IssueDate > to) {
			continue
		}
		if !purchase && ((custF > 0 && x.CustomerID != custF) || (!ro.seesAll() && x.RepID != uid) || (repF > 0 && x.RepID != repF)) {
			continue
		}
		status := "crm_draft"
		if x.SendStatus == "pending_accounting" || x.SendStatus == "rejected" {
			status = x.SendStatus
		}
		items = append(items, rowOut{
			GUID: pfCrmPrefix + strconv.FormatInt(x.ID, 10), Number: x.Number, CustomerID: x.CustomerID,
			PartyName: x.CustomerName, RepName: x.RepName, Amount: x.Amount, IssueDate: x.IssueDate,
			ExpectedDate: x.ExpectedDate, Status: status, Source: "crm", DocID: x.ID,
		})
		cOpen++
		cOpenSum += x.Amount
	}

	sort.SliceStable(items, func(i, j int) bool {
		if items[i].IssueDate != items[j].IssueDate {
			return items[i].IssueDate > items[j].IssueDate
		}
		return items[i].Number > items[j].Number
	})
	writeJSON(w, http.StatusOK, map[string]any{
		"items":      items,
		"cards":      map[string]any{"total": len(items), "open": cOpen, "open_sum": cOpenSum, "delivered": cDelivered},
		"loading":    !loaded,
		"fetched_at": jalaliDateTime(fetchedAt),
	})
}

func leftPad4(n int64) string {
	s := strconv.FormatInt(n, 10)
	for len(s) < 4 {
		s = "0" + s
	}
	return s
}
