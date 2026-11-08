package main

// ═══════════════════════════════════════════════════════════════════
//  اسنادِ صادرشده در CRM — پیش‌فاکتور فروش، سفارش فروش، سفارش خرید
//  (۱۴۰۵/۰۸/۱۷) — pages/ucrm-proforma-edit.php (?type=...).
//
//  جدول‌ها: ucrm_proformas (+ doc_type) + ucrm_proforma_items
//  (مهاجرت‌های 20261108_100000 و 20261108_110000).
//    proforma        → پیش‌فاکتور فروش (شایگان ۱۳)، طرف = مشتری CRM، شماره PFC-سال-ترتیب
//    sales_order     → سفارش فروش (۱۱)، طرف = مشتری CRM، شماره SOC-…
//    purchase_order  → سفارش خرید (۱۲)، طرف = تفصیلیِ شایگان (تأمین‌کننده)، شماره POC-…
//                      فعلا فقط مدیر/سوپروایزر (بعدا واحد خرید).
//  کالا فقط از کالاهای شایگان (کشِ پیش‌بارگذاری‌شده)؛ قیمت تومان. تا وقتی به
//  شایگان نرفته (send_status ≠ sent) قابل ویرایش/حذف است. ارسال (کارشناس درخواست،
//  حسابداری تأیید) = مرحلهٔ بعد. پیش‌فاکتورِ CRM → «تبدیل به سفارش فروش».
//
//  دسترسی (فروش): مدیر/حسابداری همه؛ کارشناس فقط مشتری‌هایی که کارشناسشان خودش است.
//  در فهرست‌ها شناسهٔ این‌ها «crm-<id>» است (کنار GuId شایگان).
// ═══════════════════════════════════════════════════════════════════

import (
	"database/sql"
	"fmt"
	"math"
	"net/http"
	"strconv"
	"strings"
	"time"
)

const pfCrmPrefix = "crm-"

const (
	docProforma      = "proforma"
	docSalesOrder    = "sales_order"
	docPurchaseOrder = "purchase_order"
)

var docPrefix = map[string]string{docProforma: "PFC", docSalesOrder: "SOC", docPurchaseOrder: "POC"}
var docLabel = map[string]string{docProforma: "پیش‌فاکتور", docSalesOrder: "سفارش فروش", docPurchaseOrder: "سفارش خرید"}

type pfDocItem struct {
	ItemCode  string  `json:"item_code"`
	ItemGUID  string  `json:"item_guid"`
	ItemName  string  `json:"item_name"`
	Unit      string  `json:"unit"`
	Qty       float64 `json:"qty"`
	UnitPrice int64   `json:"unit_price"`
	Discount  int64   `json:"discount"`
	LineTotal int64   `json:"line_total"`
}

type pfDocIn struct {
	DocType      string      `json:"doc_type"`
	CustomerID   int64       `json:"customer_id"`
	PartyJobNo   string      `json:"party_job_no"` // سفارش خرید: تأمین‌کننده
	IssueDate    string      `json:"issue_date"`
	NextFollowup string      `json:"next_followup"`
	ExpectedDate string      `json:"expected_date"`
	Note         string      `json:"note"`
	Items        []pfDocItem `json:"items"`
}

func pfDocNumber(docType string, jy, seq int) string {
	p := docPrefix[docType]
	if p == "" {
		p = "PFC"
	}
	return fmt.Sprintf("%s-%d-%04d", p, jy, seq)
}

func validYMD(v string) bool {
	_, err := time.Parse("2006-01-02", v)
	return err == nil
}

// clean ورودی را مرتب و اعتبارسنجی می‌کند؛ پیام خطا یا "" برمی‌گرداند.
func (in *pfDocIn) clean() string {
	in.DocType = strings.TrimSpace(in.DocType)
	if in.DocType == "" {
		in.DocType = docProforma
	}
	if docPrefix[in.DocType] == "" {
		return "نوع سند نامعتبر است"
	}
	in.IssueDate = strings.TrimSpace(in.IssueDate)
	in.NextFollowup = strings.TrimSpace(in.NextFollowup)
	in.ExpectedDate = strings.TrimSpace(in.ExpectedDate)
	in.PartyJobNo = digitsOnly(in.PartyJobNo)
	in.Note = strings.TrimSpace(in.Note)
	if in.DocType == docPurchaseOrder {
		if in.PartyJobNo == "" {
			return "تأمین‌کننده را انتخاب کنید"
		}
		in.CustomerID = 0
	} else if in.CustomerID <= 0 {
		return "مشتری را انتخاب کنید"
	}
	if !validYMD(in.IssueDate) {
		return "تاریخ صدور نامعتبر است"
	}
	if in.NextFollowup != "" && !validYMD(in.NextFollowup) {
		return "تاریخ پیگیری نامعتبر است"
	}
	if in.ExpectedDate != "" && !validYMD(in.ExpectedDate) {
		return "تاریخ تحویل نامعتبر است"
	}
	if len(in.Items) == 0 {
		return "حداقل یک کالا وارد کنید"
	}
	if len(in.Items) > 300 {
		return "تعداد ردیف‌ها بیش از حد است"
	}
	for i := range in.Items {
		it := &in.Items[i]
		it.ItemCode = strings.TrimSpace(it.ItemCode)
		it.ItemName = strings.TrimSpace(it.ItemName)
		it.ItemGUID = strings.ToLower(strings.TrimSpace(it.ItemGUID))
		row := fmt.Sprintf("ردیف %d: ", i+1)
		if it.ItemCode == "" || it.ItemName == "" {
			return row + "کالا را از فهرست کالاهای حسابداری انتخاب کنید"
		}
		if it.Qty <= 0 || it.Qty > 1e9 {
			return row + "تعداد باید بیشتر از صفر باشد"
		}
		if it.UnitPrice < 0 || it.Discount < 0 {
			return row + "مبلغ منفی مجاز نیست"
		}
		gross := int64(math.Round(it.Qty * float64(it.UnitPrice)))
		if it.Discount > gross {
			return row + "تخفیف از مبلغ ردیف بیشتر است"
		}
		it.LineTotal = gross - it.Discount
	}
	return ""
}

// pfCheckItems: کدِ کالا باید در کشِ کالاهای شایگان باشد (اگر کش هنوز پر نشده، رد نمی‌شود).
func pfCheckItems(items []pfDocItem) string {
	shygunItemCache.mu.RLock()
	defer shygunItemCache.mu.RUnlock()
	if shygunItemCache.rows == nil {
		return ""
	}
	byCode := make(map[string]shygunItem, len(shygunItemCache.rows))
	for _, it := range shygunItemCache.rows {
		byCode[it.Code] = it
	}
	for i := range items {
		k, ok := byCode[items[i].ItemCode]
		if !ok {
			return fmt.Sprintf("ردیف %d: کالای «%s» در حسابداری پیدا نشد", i+1, items[i].ItemName)
		}
		items[i].ItemGUID, items[i].ItemName, items[i].Unit = k.GUID, k.Name, k.Unit
	}
	return ""
}

// pfCustomerFor: مشتریِ قابل‌دیدن و فعال برای صدور.
func (s *server) pfCustomerFor(w http.ResponseWriter, cid, uid int64, ro ucrmRoles) *ucrmCustomerOut {
	c, err := s.ucrmLoad(cid, uid, ro)
	if err != nil {
		writeDBErr(w, "pfCustomerFor", err)
		return nil
	}
	if c == nil {
		writeErr(w, http.StatusNotFound, "مشتری یافت نشد")
		return nil
	}
	if c.Status != ucStActive {
		writeErr(w, http.StatusBadRequest, "برای این مشتری هنوز نمی‌شود سند صادر کرد؛ مشتری باید تأیید و فعال شده باشد")
		return nil
	}
	return c
}

// pfPartyFor: تأمین‌کنندهٔ سفارش خرید از کشِ «همهٔ تفصیلی‌های شایگان».
func (s *server) pfPartyFor(w http.ResponseWriter, jobNo string) *shygunCustomer {
	rows, _, err := s.shygunAllJobsAny()
	if err != nil {
		writeErr(w, http.StatusBadGateway, "اتصال به نرم‌افزار حسابداری برقرار نشد: "+err.Error())
		return nil
	}
	for i := range rows {
		if rows[i].JobNo == jobNo {
			p := rows[i]
			return &p
		}
	}
	writeErr(w, http.StatusBadRequest, "تأمین‌کننده در حسابداری پیدا نشد")
	return nil
}

// canDoc: اجازهٔ کار با این نوع سند. سفارش خرید فعلا فقط مدیر/سوپروایزر.
func canDoc(ro ucrmRoles, docType string) bool {
	if docType == docPurchaseOrder {
		return ro.Manager
	}
	return true
}

type pfDocHead struct {
	ID          int64
	DocType     string
	CustomerID  int64
	SendStatus  string
	CustomerRep int64
}

// pfDocLoad: سرِ سند + اجازهٔ دیدن.
func (s *server) pfDocLoad(w http.ResponseWriter, id, uid int64, ro ucrmRoles) *pfDocHead {
	var h pfDocHead
	var del int
	err := s.db.QueryRow(`
		SELECT p.id, p.doc_type, COALESCE(p.customer_id, 0), p.send_status, p.is_deleted, COALESCE(c.rep_id, 0)
		FROM ucrm_proformas p LEFT JOIN ucrm_customers c ON c.id = p.customer_id
		WHERE p.id = ? AND p.organization_id = ?`, id, ucrmOrg).
		Scan(&h.ID, &h.DocType, &h.CustomerID, &h.SendStatus, &del, &h.CustomerRep)
	if err == sql.ErrNoRows || (err == nil && del == 1) {
		writeErr(w, http.StatusNotFound, "سند یافت نشد")
		return nil
	}
	if err != nil {
		writeDBErr(w, "pfDocLoad", err)
		return nil
	}
	if !canDoc(ro, h.DocType) || (h.DocType != docPurchaseOrder && !ro.seesAll() && h.CustomerRep != uid) {
		writeErr(w, http.StatusForbidden, "به این سند دسترسی ندارید")
		return nil
	}
	return &h
}

func (h *pfDocHead) editable() bool { return h.SendStatus == "draft" || h.SendStatus == "rejected" }

func pfTotals(items []pfDocItem) (sub, disc, total int64) {
	for _, it := range items {
		sub += it.LineTotal + it.Discount
		disc += it.Discount
		total += it.LineTotal
	}
	return
}

func pfInsertItems(tx *sql.Tx, pid int64, items []pfDocItem) error {
	st, err := tx.Prepare(`INSERT INTO ucrm_proforma_items
		(proforma_id, sort, item_code, item_guid, item_name, unit, qty, unit_price, discount, line_total)
		VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`)
	if err != nil {
		return err
	}
	defer st.Close()
	for i, it := range items {
		if _, err := st.Exec(pid, i, it.ItemCode, nullIfEmpty(it.ItemGUID), it.ItemName, nullIfEmpty(it.Unit),
			it.Qty, it.UnitPrice, it.Discount, it.LineTotal); err != nil {
			return err
		}
	}
	return nil
}

// pfParty: طرفِ سند (مشتری CRM یا تأمین‌کنندهٔ شایگان) — فیلدهای ذخیره.
type pfParty struct {
	CustomerID any
	RepID      any
	JobNo      any
	GUID       any
	Name       any
}

func (s *server) pfResolveParty(w http.ResponseWriter, in *pfDocIn, uid int64, ro ucrmRoles) (*pfParty, bool) {
	if in.DocType == docPurchaseOrder {
		p := s.pfPartyFor(w, in.PartyJobNo)
		if p == nil {
			return nil, false
		}
		return &pfParty{nil, nil, p.JobNo, nullIfEmpty(p.GUID), p.Name}, true
	}
	c := s.pfCustomerFor(w, in.CustomerID, uid, ro)
	if c == nil {
		return nil, false
	}
	return &pfParty{c.ID, nullIfZeroIf(c.RepID, true), nil, nil, nil}, true
}

// pfInsertDoc: سندِ تازه در تراکنشِ داده‌شده (شماره = ترتیبِ بعدی برای همان نوع و سال).
func pfInsertDoc(tx *sql.Tx, in *pfDocIn, pt *pfParty, uid int64, sourceID any) (int64, string, error) {
	t, _ := time.Parse("2006-01-02", in.IssueDate)
	jy, _, _ := gregorianToJalali(t.Year(), int(t.Month()), t.Day())
	sub, disc, total := pfTotals(in.Items)
	var seq int
	if err := tx.QueryRow(`SELECT COALESCE(MAX(seq), 0) + 1 FROM ucrm_proformas
		WHERE organization_id = ? AND doc_type = ? AND jy = ? FOR UPDATE`, ucrmOrg, in.DocType, jy).Scan(&seq); err != nil {
		return 0, "", err
	}
	res, err := tx.Exec(`INSERT INTO ucrm_proformas
		(organization_id, doc_type, jy, seq, customer_id, party_job_no, party_guid, party_name, rep_id, issue_date,
		 next_followup, expected_date, source_doc_id, note, subtotal, discount, total, created_by, updated_by)
		VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
		ucrmOrg, in.DocType, jy, seq, pt.CustomerID, pt.JobNo, pt.GUID, pt.Name, pt.RepID, in.IssueDate,
		nullIfEmpty(in.NextFollowup), nullIfEmpty(in.ExpectedDate), sourceID, nullIfEmpty(in.Note), sub, disc, total, uid, uid)
	if err != nil {
		return 0, "", err
	}
	pid, _ := res.LastInsertId()
	if err := pfInsertItems(tx, pid, in.Items); err != nil {
		return 0, "", err
	}
	return pid, pfDocNumber(in.DocType, jy, seq), nil
}

// ──────────────── POST /ucrm/proforma-docs ────────────────

func (s *server) ucrmCreateProformaDoc(w http.ResponseWriter, r *http.Request) {
	uid := userOf(r.Context()).ID
	ro := s.ucrmRolesOf(uid)
	var in pfDocIn
	if !decodeJSON(w, r, &in, 1<<20) {
		return
	}
	if msg := in.clean(); msg != "" {
		writeErr(w, http.StatusBadRequest, msg)
		return
	}
	if !canDoc(ro, in.DocType) {
		writeErr(w, http.StatusForbidden, "ثبت "+docLabel[in.DocType]+" فعلا فقط برای مدیران است")
		return
	}
	if msg := pfCheckItems(in.Items); msg != "" {
		writeErr(w, http.StatusBadRequest, msg)
		return
	}
	pt, ok := s.pfResolveParty(w, &in, uid, ro)
	if !ok {
		return
	}
	tx, err := s.db.Begin()
	if err != nil {
		writeDBErr(w, "ucrmCreateProformaDoc", err)
		return
	}
	defer tx.Rollback()
	pid, number, err := pfInsertDoc(tx, &in, pt, uid, nil)
	if err != nil {
		writeDBErr(w, "ucrmCreateProformaDoc insert", err)
		return
	}
	if err := tx.Commit(); err != nil {
		writeDBErr(w, "ucrmCreateProformaDoc commit", err)
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{
		"success": true, "id": pid, "number": number,
		"message": docLabel[in.DocType] + " " + number + " ثبت شد.",
	})
}

// ──────────────── PUT /ucrm/proforma-docs/{id} ────────────────

func (s *server) ucrmUpdateProformaDoc(w http.ResponseWriter, r *http.Request) {
	uid := userOf(r.Context()).ID
	ro := s.ucrmRolesOf(uid)
	id, _ := strconv.ParseInt(r.PathValue("id"), 10, 64)
	h := s.pfDocLoad(w, id, uid, ro)
	if h == nil {
		return
	}
	if !h.editable() {
		writeErr(w, http.StatusConflict, "این سند برای حسابداری ارسال شده و دیگر قابل ویرایش نیست")
		return
	}
	var in pfDocIn
	if !decodeJSON(w, r, &in, 1<<20) {
		return
	}
	in.DocType = h.DocType // نوع سند عوض نمی‌شود
	if msg := in.clean(); msg != "" {
		writeErr(w, http.StatusBadRequest, msg)
		return
	}
	if msg := pfCheckItems(in.Items); msg != "" {
		writeErr(w, http.StatusBadRequest, msg)
		return
	}
	pt, ok := s.pfResolveParty(w, &in, uid, ro)
	if !ok {
		return
	}
	sub, disc, total := pfTotals(in.Items)
	tx, err := s.db.Begin()
	if err != nil {
		writeDBErr(w, "ucrmUpdateProformaDoc", err)
		return
	}
	defer tx.Rollback()
	// سال/ترتیبِ شماره ثابت می‌ماند، حتی اگر تاریخ صدور عوض شود
	if _, err := tx.Exec(`UPDATE ucrm_proformas SET customer_id = ?, party_job_no = ?, party_guid = ?, party_name = ?, rep_id = ?,
		issue_date = ?, next_followup = ?, expected_date = ?, note = ?, subtotal = ?, discount = ?, total = ?, updated_by = ?
		WHERE id = ?`,
		pt.CustomerID, pt.JobNo, pt.GUID, pt.Name, pt.RepID, in.IssueDate, nullIfEmpty(in.NextFollowup),
		nullIfEmpty(in.ExpectedDate), nullIfEmpty(in.Note), sub, disc, total, uid, id); err != nil {
		writeDBErr(w, "ucrmUpdateProformaDoc update", err)
		return
	}
	if _, err := tx.Exec("DELETE FROM ucrm_proforma_items WHERE proforma_id = ?", id); err != nil {
		writeDBErr(w, "ucrmUpdateProformaDoc items", err)
		return
	}
	if err := pfInsertItems(tx, id, in.Items); err != nil {
		writeDBErr(w, "ucrmUpdateProformaDoc items", err)
		return
	}
	if err := tx.Commit(); err != nil {
		writeDBErr(w, "ucrmUpdateProformaDoc commit", err)
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "id": id, "message": docLabel[h.DocType] + " ذخیره شد."})
}

// pfLoadItems ردیف‌های یک سند.
func (s *server) pfLoadItems(id int64) ([]pfDocItem, error) {
	rows, err := s.db.Query(`SELECT item_code, COALESCE(item_guid, ''), item_name, COALESCE(unit, ''), qty, unit_price, discount, line_total
		FROM ucrm_proforma_items WHERE proforma_id = ? ORDER BY sort, id`, id)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	items := []pfDocItem{}
	for rows.Next() {
		var it pfDocItem
		if err := rows.Scan(&it.ItemCode, &it.ItemGUID, &it.ItemName, &it.Unit, &it.Qty, &it.UnitPrice, &it.Discount, &it.LineTotal); err != nil {
			return nil, err
		}
		items = append(items, it)
	}
	return items, rows.Err()
}

// ──────────────── GET /ucrm/proforma-docs/{id} ────────────────

func (s *server) ucrmGetProformaDoc(w http.ResponseWriter, r *http.Request) {
	uid := userOf(r.Context()).ID
	ro := s.ucrmRolesOf(uid)
	id, _ := strconv.ParseInt(r.PathValue("id"), 10, 64)
	h := s.pfDocLoad(w, id, uid, ro)
	if h == nil {
		return
	}
	var jy, seq int
	var issue, follow, expected, note, status, custName, repName, createdBy, partyNo, partyName string
	var sub, disc, total, sourceID int64
	err := s.db.QueryRow(`
		SELECT p.jy, p.seq, DATE_FORMAT(p.issue_date, '%Y-%m-%d'), COALESCE(DATE_FORMAT(p.next_followup, '%Y-%m-%d'), ''),
		       COALESCE(DATE_FORMAT(p.expected_date, '%Y-%m-%d'), ''), COALESCE(p.note, ''), p.status,
		       COALESCE(c.name, ''), COALESCE(CONCAT(ru.first_name, ' ', ru.last_name), ''),
		       COALESCE(CONCAT(cu.first_name, ' ', cu.last_name), ''), COALESCE(p.party_job_no, ''), COALESCE(p.party_name, ''),
		       p.subtotal, p.discount, p.total, COALESCE(p.source_doc_id, 0)
		FROM ucrm_proformas p
		LEFT JOIN ucrm_customers c ON c.id = p.customer_id
		LEFT JOIN users ru ON ru.id = p.rep_id
		LEFT JOIN users cu ON cu.id = p.created_by
		WHERE p.id = ?`, id).
		Scan(&jy, &seq, &issue, &follow, &expected, &note, &status, &custName, &repName, &createdBy, &partyNo, &partyName,
			&sub, &disc, &total, &sourceID)
	if err != nil {
		writeDBErr(w, "ucrmGetProformaDoc", err)
		return
	}
	items, err := s.pfLoadItems(id)
	if err != nil {
		writeDBErr(w, "ucrmGetProformaDoc items", err)
		return
	}
	// سفارش فروشی که از این پیش‌فاکتور ساخته شده (برای دکمهٔ «تبدیل»)
	var orderID int64
	if h.DocType == docProforma {
		_ = s.db.QueryRow(`SELECT id FROM ucrm_proformas WHERE source_doc_id = ? AND doc_type = ? AND is_deleted = 0 LIMIT 1`,
			id, docSalesOrder).Scan(&orderID)
	}
	writeJSON(w, http.StatusOK, map[string]any{
		"id": id, "doc_type": h.DocType, "number": pfDocNumber(h.DocType, jy, seq),
		"customer_id": h.CustomerID, "customer_name": custName, "party_job_no": partyNo, "party_name": partyName,
		"rep_name": strings.TrimSpace(repName), "created_by_name": strings.TrimSpace(createdBy),
		"issue_date": issue, "next_followup": follow, "expected_date": expected, "note": note, "status": status,
		"send_status": h.SendStatus, "subtotal": sub, "discount": disc, "total": total, "items": items,
		"can_edit": h.editable(), "source_doc_id": sourceID, "sales_order_id": orderID,
	})
}

// ──────────────── DELETE /ucrm/proforma-docs/{id} — حذف نرم ────────────────

func (s *server) ucrmDeleteProformaDoc(w http.ResponseWriter, r *http.Request) {
	uid := userOf(r.Context()).ID
	ro := s.ucrmRolesOf(uid)
	id, _ := strconv.ParseInt(r.PathValue("id"), 10, 64)
	h := s.pfDocLoad(w, id, uid, ro)
	if h == nil {
		return
	}
	if !h.editable() {
		writeErr(w, http.StatusConflict, "این سند برای حسابداری ارسال شده و قابل حذف نیست")
		return
	}
	if _, err := s.db.Exec(`UPDATE ucrm_proformas SET is_deleted = 1, deleted_by = ?, deleted_at = NOW() WHERE id = ?`, uid, id); err != nil {
		writeDBErr(w, "ucrmDeleteProformaDoc", err)
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "message": docLabel[h.DocType] + " حذف شد."})
}

// ──────────────── POST /ucrm/proforma-docs/{id}/to-sales-order ────────────────
// پیش‌فاکتورِ CRM → سفارش فروشِ تازه با همان مشتری و کالاها؛ پیش‌فاکتور «تأیید شده» می‌شود.

func (s *server) ucrmProformaToSalesOrder(w http.ResponseWriter, r *http.Request) {
	uid := userOf(r.Context()).ID
	ro := s.ucrmRolesOf(uid)
	id, _ := strconv.ParseInt(r.PathValue("id"), 10, 64)
	h := s.pfDocLoad(w, id, uid, ro)
	if h == nil {
		return
	}
	if h.DocType != docProforma {
		writeErr(w, http.StatusBadRequest, "فقط پیش‌فاکتور به سفارش فروش تبدیل می‌شود")
		return
	}
	var existing int64
	_ = s.db.QueryRow(`SELECT id FROM ucrm_proformas WHERE source_doc_id = ? AND doc_type = ? AND is_deleted = 0 LIMIT 1`,
		id, docSalesOrder).Scan(&existing)
	if existing > 0 {
		writeJSON(w, http.StatusConflict, map[string]any{"success": false, "id": existing,
			"message": "از این پیش‌فاکتور قبلا سفارش فروش ساخته شده است."})
		return
	}
	items, err := s.pfLoadItems(id)
	if err != nil {
		writeDBErr(w, "ucrmProformaToSalesOrder items", err)
		return
	}
	var note string
	_ = s.db.QueryRow("SELECT COALESCE(note, '') FROM ucrm_proformas WHERE id = ?", id).Scan(&note)
	var today string
	_ = s.db.QueryRow("SELECT DATE_FORMAT(CURDATE(), '%Y-%m-%d')").Scan(&today)
	in := pfDocIn{DocType: docSalesOrder, CustomerID: h.CustomerID, IssueDate: today, Note: note, Items: items}
	if msg := in.clean(); msg != "" {
		writeErr(w, http.StatusBadRequest, msg)
		return
	}
	pt, ok := s.pfResolveParty(w, &in, uid, ro)
	if !ok {
		return
	}
	tx, err := s.db.Begin()
	if err != nil {
		writeDBErr(w, "ucrmProformaToSalesOrder", err)
		return
	}
	defer tx.Rollback()
	oid, number, err := pfInsertDoc(tx, &in, pt, uid, id)
	if err != nil {
		writeDBErr(w, "ucrmProformaToSalesOrder insert", err)
		return
	}
	if _, err := tx.Exec(`UPDATE ucrm_proformas SET status = ?, status_by = ?, status_at = NOW(), updated_by = ? WHERE id = ?`,
		pfStApproved, uid, uid, id); err != nil {
		writeDBErr(w, "ucrmProformaToSalesOrder status", err)
		return
	}
	if err := tx.Commit(); err != nil {
		writeDBErr(w, "ucrmProformaToSalesOrder commit", err)
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "id": oid, "number": number,
		"message": "سفارش فروش " + number + " از این پیش‌فاکتور ساخته شد."})
}

// ──────────────── فهرستِ مشترک: اسنادِ CRM که هنوز به شایگان نرفته‌اند ────────────────

type pfCrmRow struct {
	ID           int64
	Number       string
	CustomerID   int64
	CustomerName string
	RepID        int64
	RepName      string
	Amount       int64
	IssueDate    string
	NextFollowup string
	ExpectedDate string
	Status       string
	SendStatus   string
	Late         bool
	LateDays     int
}

func (s *server) pfCrmRows(docType string) ([]pfCrmRow, error) {
	rows, err := s.db.Query(`
		SELECT p.id, p.jy, p.seq, COALESCE(c.id, 0), COALESCE(c.name, p.party_name, ''), COALESCE(c.rep_id, 0),
		       COALESCE(CONCAT(u.first_name, ' ', u.last_name), ''),
		       p.total, DATE_FORMAT(p.issue_date, '%Y-%m-%d'), COALESCE(DATE_FORMAT(p.next_followup, '%Y-%m-%d'), ''),
		       COALESCE(DATE_FORMAT(p.expected_date, '%Y-%m-%d'), ''), p.status, p.send_status,
		       (p.next_followup IS NOT NULL AND p.next_followup < CURDATE()), COALESCE(DATEDIFF(CURDATE(), p.next_followup), 0)
		FROM ucrm_proformas p
		LEFT JOIN ucrm_customers c ON c.id = p.customer_id
		LEFT JOIN users u ON u.id = c.rep_id
		WHERE p.organization_id = ? AND p.doc_type = ? AND p.is_deleted = 0 AND p.shygun_guid IS NULL`, ucrmOrg, docType)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	out := []pfCrmRow{}
	for rows.Next() {
		var x pfCrmRow
		var jy, seq, late int
		if err := rows.Scan(&x.ID, &jy, &seq, &x.CustomerID, &x.CustomerName, &x.RepID, &x.RepName, &x.Amount,
			&x.IssueDate, &x.NextFollowup, &x.ExpectedDate, &x.Status, &x.SendStatus, &late, &x.LateDays); err != nil {
			return nil, err
		}
		x.Number, x.Late, x.RepName = pfDocNumber(docType, jy, seq), late == 1, strings.TrimSpace(x.RepName)
		out = append(out, x)
	}
	return out, rows.Err()
}

// pfCrmAuthorize: عملیاتِ وضعیت/پیگیری روی پیش‌فاکتورِ CRM (guid = crm-<id>).
func (s *server) pfCrmAuthorize(w http.ResponseWriter, r *http.Request) (int64, bool) {
	uid := userOf(r.Context()).ID
	id, _ := strconv.ParseInt(strings.TrimPrefix(r.PathValue("guid"), pfCrmPrefix), 10, 64)
	h := s.pfDocLoad(w, id, uid, s.ucrmRolesOf(uid))
	if h == nil {
		return 0, false
	}
	if h.DocType != docProforma {
		writeErr(w, http.StatusBadRequest, "این عملیات فقط برای پیش‌فاکتور است")
		return 0, false
	}
	return id, true
}

// ──────────────── GET /ucrm/shygun/parties — همهٔ تفصیلی‌ها (انتخاب تأمین‌کننده) ────────────────

func (s *server) ucrmShygunParties(w http.ResponseWriter, r *http.Request) {
	if !s.ucrmRolesOf(userOf(r.Context()).ID).Manager {
		writeErr(w, http.StatusForbidden, "دسترسی ندارید")
		return
	}
	var rows []shygunCustomer
	var fetchedAt time.Time
	var err error
	if r.URL.Query().Get("refresh") == "1" {
		rows, fetchedAt, err = s.shygunAllJobs(true)
	} else {
		rows, fetchedAt, err = s.shygunAllJobsAny()
	}
	if err != nil {
		writeErr(w, http.StatusBadGateway, "اتصال به نرم‌افزار حسابداری برقرار نشد: "+err.Error())
		return
	}
	items := make([]map[string]any, 0, len(rows))
	for _, p := range rows {
		items = append(items, map[string]any{"job_no": p.JobNo, "name": p.Name})
	}
	writeJSON(w, http.StatusOK, map[string]any{"items": items, "fetched_at": jalaliDateTime(fetchedAt)})
}
