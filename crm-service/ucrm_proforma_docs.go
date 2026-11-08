package main

// ═══════════════════════════════════════════════════════════════════
//  صدورِ پیش‌فاکتور فروش در CRM (مرحلهٔ ۱، ۱۴۰۵/۰۸/۱۷) —
//  pages/ucrm-proforma-edit.php + فهرستِ مشترک در ucrm_proformas.go.
//
//  جدول‌ها: ucrm_proformas + ucrm_proforma_items (مهاجرت 20261108_100000).
//  کالا فقط از کالاهای شایگان (کشِ پیش‌بارگذاری‌شده)؛ قیمت را کارشناس وارد
//  می‌کند (تومان). شماره = PFC-سال-ترتیب. تا وقتی به شایگان نرفته (send_status
//  ≠ sent) قابل ویرایش/حذف است. ارسال به شایگان (کارشناس درخواست، حسابداری
//  تأیید) = مرحلهٔ ۲.
//
//  دسترسی: مثل مشتری — مدیر/حسابداری همه؛ کارشناس فقط مشتری‌هایی که
//  کارشناسشان خودش است (ucrmLoad). مشتری باید «فعال» باشد.
//  در فهرست/عملیات، شناسهٔ این‌ها «crm-<id>» است (کنار GuId شایگان).
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
	CustomerID   int64       `json:"customer_id"`
	IssueDate    string      `json:"issue_date"`
	NextFollowup string      `json:"next_followup"`
	Note         string      `json:"note"`
	Items        []pfDocItem `json:"items"`
}

func pfDocNumber(jy, seq int) string { return fmt.Sprintf("PFC-%d-%04d", jy, seq) }

// clean ورودی را مرتب و اعتبارسنجی می‌کند؛ پیام خطا یا "" برمی‌گرداند.
func (in *pfDocIn) clean() string {
	in.IssueDate = strings.TrimSpace(in.IssueDate)
	in.NextFollowup = strings.TrimSpace(in.NextFollowup)
	in.Note = strings.TrimSpace(in.Note)
	if in.CustomerID <= 0 {
		return "مشتری را انتخاب کنید"
	}
	if _, err := time.Parse("2006-01-02", in.IssueDate); err != nil {
		return "تاریخ صدور نامعتبر است"
	}
	if in.NextFollowup != "" {
		if _, err := time.Parse("2006-01-02", in.NextFollowup); err != nil {
			return "تاریخ پیگیری نامعتبر است"
		}
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
		writeErr(w, http.StatusBadRequest, "برای این مشتری هنوز نمی‌شود پیش‌فاکتور صادر کرد؛ مشتری باید تأیید و فعال شده باشد")
		return nil
	}
	return c
}

type pfDocHead struct {
	ID          int64
	CustomerID  int64
	RepID       int64
	SendStatus  string
	IsDeleted   bool
	CustomerRep int64
}

// pfDocLoad: سرِ سند + اجازهٔ دیدن (مدیر/حسابداری همه؛ کارشناس اگر کارشناسِ فعلیِ مشتری باشد).
func (s *server) pfDocLoad(w http.ResponseWriter, id, uid int64, ro ucrmRoles) *pfDocHead {
	var h pfDocHead
	var del int
	err := s.db.QueryRow(`
		SELECT p.id, p.customer_id, COALESCE(p.rep_id, 0), p.send_status, p.is_deleted, COALESCE(c.rep_id, 0)
		FROM ucrm_proformas p JOIN ucrm_customers c ON c.id = p.customer_id
		WHERE p.id = ? AND p.organization_id = ?`, id, ucrmOrg).
		Scan(&h.ID, &h.CustomerID, &h.RepID, &h.SendStatus, &del, &h.CustomerRep)
	if err == sql.ErrNoRows || (err == nil && del == 1) {
		writeErr(w, http.StatusNotFound, "پیش‌فاکتور یافت نشد")
		return nil
	}
	if err != nil {
		writeDBErr(w, "pfDocLoad", err)
		return nil
	}
	if !ro.seesAll() && h.CustomerRep != uid {
		writeErr(w, http.StatusForbidden, "این پیش‌فاکتور مربوط به مشتری‌های شما نیست")
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
	if msg := pfCheckItems(in.Items); msg != "" {
		writeErr(w, http.StatusBadRequest, msg)
		return
	}
	c := s.pfCustomerFor(w, in.CustomerID, uid, ro)
	if c == nil {
		return
	}
	t, _ := time.Parse("2006-01-02", in.IssueDate)
	jy, _, _ := gregorianToJalali(t.Year(), int(t.Month()), t.Day())
	sub, disc, total := pfTotals(in.Items)

	tx, err := s.db.Begin()
	if err != nil {
		writeDBErr(w, "ucrmCreateProformaDoc", err)
		return
	}
	defer tx.Rollback()
	var seq int
	if err := tx.QueryRow(`SELECT COALESCE(MAX(seq), 0) + 1 FROM ucrm_proformas
		WHERE organization_id = ? AND jy = ? FOR UPDATE`, ucrmOrg, jy).Scan(&seq); err != nil {
		writeDBErr(w, "ucrmCreateProformaDoc seq", err)
		return
	}
	res, err := tx.Exec(`INSERT INTO ucrm_proformas
		(organization_id, jy, seq, customer_id, rep_id, issue_date, next_followup, note, subtotal, discount, total, created_by, updated_by)
		VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
		ucrmOrg, jy, seq, c.ID, nullIfZeroIf(c.RepID, true), in.IssueDate, nullIfEmpty(in.NextFollowup),
		nullIfEmpty(in.Note), sub, disc, total, uid, uid)
	if err != nil {
		writeDBErr(w, "ucrmCreateProformaDoc insert", err)
		return
	}
	pid, _ := res.LastInsertId()
	if err := pfInsertItems(tx, pid, in.Items); err != nil {
		writeDBErr(w, "ucrmCreateProformaDoc items", err)
		return
	}
	if err := tx.Commit(); err != nil {
		writeDBErr(w, "ucrmCreateProformaDoc commit", err)
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{
		"success": true, "id": pid, "number": pfDocNumber(jy, seq),
		"message": "پیش‌فاکتور " + pfDocNumber(jy, seq) + " ثبت شد.",
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
		writeErr(w, http.StatusConflict, "این پیش‌فاکتور برای حسابداری ارسال شده و دیگر قابل ویرایش نیست")
		return
	}
	var in pfDocIn
	if !decodeJSON(w, r, &in, 1<<20) {
		return
	}
	if msg := in.clean(); msg != "" {
		writeErr(w, http.StatusBadRequest, msg)
		return
	}
	if msg := pfCheckItems(in.Items); msg != "" {
		writeErr(w, http.StatusBadRequest, msg)
		return
	}
	c := s.pfCustomerFor(w, in.CustomerID, uid, ro)
	if c == nil {
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
	if _, err := tx.Exec(`UPDATE ucrm_proformas SET customer_id = ?, rep_id = ?, issue_date = ?, next_followup = ?, note = ?,
		subtotal = ?, discount = ?, total = ?, updated_by = ? WHERE id = ?`,
		c.ID, nullIfZeroIf(c.RepID, true), in.IssueDate, nullIfEmpty(in.NextFollowup), nullIfEmpty(in.Note),
		sub, disc, total, uid, id); err != nil {
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
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "id": id, "message": "پیش‌فاکتور ذخیره شد."})
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
	var issue, follow, note, status, custName, repName, createdBy string
	var sub, disc, total int64
	err := s.db.QueryRow(`
		SELECT p.jy, p.seq, DATE_FORMAT(p.issue_date, '%Y-%m-%d'), COALESCE(DATE_FORMAT(p.next_followup, '%Y-%m-%d'), ''),
		       COALESCE(p.note, ''), p.status, c.name, COALESCE(CONCAT(ru.first_name, ' ', ru.last_name), ''),
		       COALESCE(CONCAT(cu.first_name, ' ', cu.last_name), ''), p.subtotal, p.discount, p.total
		FROM ucrm_proformas p
		JOIN ucrm_customers c ON c.id = p.customer_id
		LEFT JOIN users ru ON ru.id = p.rep_id
		LEFT JOIN users cu ON cu.id = p.created_by
		WHERE p.id = ?`, id).
		Scan(&jy, &seq, &issue, &follow, &note, &status, &custName, &repName, &createdBy, &sub, &disc, &total)
	if err != nil {
		writeDBErr(w, "ucrmGetProformaDoc", err)
		return
	}
	rows, err := s.db.Query(`SELECT item_code, COALESCE(item_guid, ''), item_name, COALESCE(unit, ''), qty, unit_price, discount, line_total
		FROM ucrm_proforma_items WHERE proforma_id = ? ORDER BY sort, id`, id)
	if err != nil {
		writeDBErr(w, "ucrmGetProformaDoc items", err)
		return
	}
	defer rows.Close()
	items := []pfDocItem{}
	for rows.Next() {
		var it pfDocItem
		if err := rows.Scan(&it.ItemCode, &it.ItemGUID, &it.ItemName, &it.Unit, &it.Qty, &it.UnitPrice, &it.Discount, &it.LineTotal); err != nil {
			writeDBErr(w, "ucrmGetProformaDoc scan", err)
			return
		}
		items = append(items, it)
	}
	writeJSON(w, http.StatusOK, map[string]any{
		"id": id, "number": pfDocNumber(jy, seq), "customer_id": h.CustomerID, "customer_name": custName,
		"rep_name": strings.TrimSpace(repName), "created_by_name": strings.TrimSpace(createdBy),
		"issue_date": issue, "next_followup": follow, "note": note, "status": status, "send_status": h.SendStatus,
		"subtotal": sub, "discount": disc, "total": total, "items": items, "can_edit": h.editable(),
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
		writeErr(w, http.StatusConflict, "این پیش‌فاکتور برای حسابداری ارسال شده و قابل حذف نیست")
		return
	}
	if _, err := s.db.Exec(`UPDATE ucrm_proformas SET is_deleted = 1, deleted_by = ?, deleted_at = NOW() WHERE id = ?`, uid, id); err != nil {
		writeDBErr(w, "ucrmDeleteProformaDoc", err)
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "message": "پیش‌فاکتور حذف شد."})
}

// ──────────────── فهرستِ مشترک: پیش‌فاکتورهای CRM (هنوز به شایگان نرفته) ────────────────

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
	Status       string
	Late         bool
	LateDays     int
}

func (s *server) pfCrmRows() ([]pfCrmRow, error) {
	rows, err := s.db.Query(`
		SELECT p.id, p.jy, p.seq, c.id, c.name, COALESCE(c.rep_id, 0), COALESCE(CONCAT(u.first_name, ' ', u.last_name), ''),
		       p.total, DATE_FORMAT(p.issue_date, '%Y-%m-%d'), COALESCE(DATE_FORMAT(p.next_followup, '%Y-%m-%d'), ''), p.status,
		       (p.next_followup IS NOT NULL AND p.next_followup < CURDATE()), COALESCE(DATEDIFF(CURDATE(), p.next_followup), 0)
		FROM ucrm_proformas p
		JOIN ucrm_customers c ON c.id = p.customer_id
		LEFT JOIN users u ON u.id = c.rep_id
		WHERE p.organization_id = ? AND p.is_deleted = 0 AND p.shygun_guid IS NULL`, ucrmOrg)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	out := []pfCrmRow{}
	for rows.Next() {
		var x pfCrmRow
		var jy, seq, late int
		if err := rows.Scan(&x.ID, &jy, &seq, &x.CustomerID, &x.CustomerName, &x.RepID, &x.RepName, &x.Amount,
			&x.IssueDate, &x.NextFollowup, &x.Status, &late, &x.LateDays); err != nil {
			return nil, err
		}
		x.Number, x.Late, x.RepName = pfDocNumber(jy, seq), late == 1, strings.TrimSpace(x.RepName)
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
	return id, true
}
