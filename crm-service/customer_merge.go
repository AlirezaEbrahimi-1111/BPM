package main

import (
	"database/sql"
	"net/http"
)

// ───────────────────────── ادغام مشتری تکراری ─────────────────────────
//
// درخواست ۱۴۰۵/۰۷/۲۹: در فهرست مشتریان، مشتری‌های تکراری هست که برای هر کدام فاکتور
// ثبت شده. حذفِ ساده (is_deleted=1) فاکتورها را به مشتریِ حذف‌شده وصل نگه می‌داشت.
// حالا قبل از حذف، فاکتورها (و بقیهٔ سوابق CRM) به مشتریِ انتخاب‌شده منتقل می‌شوند.
//
//   GET  /crm/api/customers/{id}/usage   → فاکتورهای این مشتری + شمار سوابق دیگر
//   POST /crm/api/customers/{id}/merge   {"target_id": n}  → انتقال همه + حذف (نرم)
//
// جدول‌هایی که customer_id دارند. دو جدول قید یکتا دارند (crm_followups: یک پیگیری برای
// هر مشتری؛ crm_targets: یک هدف برای هر مشتری در هر ماه) — اگر مشتری مقصد ردیف مشابه
// دارد، ردیف مبدأ منتقل نمی‌شود (UPDATE IGNORE) و روی مشتری حذف‌شده می‌ماند؛ چیزی پاک
// نمی‌شود و تعدادش در پاسخ گزارش می‌شود. دسترسی crm_service به همهٔ این جدول‌ها هست
// (crm-service/deploy/crm_service_db_user.sql).

type customerRefTable struct {
	Table  string
	Label  string // نام فارسی برای نمایش به کاربر
	Unique bool   // قید یکتا روی customer_id دارد → UPDATE IGNORE
}

var customerRefTables = []customerRefTable{
	{"inv_invoices", "فاکتور", false},
	{"inv_invoice_requests", "درخواست فاکتور", false},
	{"crm_proformas", "پیش‌فاکتور CRM", false},
	{"crm_actual_sales", "فروش ثبت‌شده", false},
	{"crm_activities", "فعالیت", false},
	{"crm_assignment_requests", "درخواست ارجاع", false},
	{"crm_sms_log", "پیامک", false},
	{"crm_followups", "پیگیری", true},
	{"crm_targets", "هدف فروش", true},
}

type customerInvoiceRef struct {
	ID          int64   `json:"id"`
	DocType     string  `json:"doc_type"`
	Number      string  `json:"number"`
	Status      string  `json:"status"`
	IssueDate   string  `json:"issue_date"`
	TotalAmount float64 `json:"total_amount"`
}

type customerRefCount struct {
	Table string `json:"table"`
	Label string `json:"label"`
	Count int    `json:"count"`
}

// مشتری فعالِ همین سازمان؟
func (s *server) customerInOrg(id, orgID int64) (string, bool, error) {
	var name string
	err := s.db.QueryRow(
		"SELECT name FROM crm_customers WHERE id = ? AND organization_id = ? AND is_deleted = 0", id, orgID).Scan(&name)
	if err == sql.ErrNoRows {
		return "", false, nil
	}
	return name, err == nil, err
}

// GET /crm/api/customers/{id}/usage
func (s *server) customerUsage(w http.ResponseWriter, r *http.Request) {
	id := idParam(r)
	u := userOf(r.Context())
	name, ok, err := s.customerInOrg(id, u.OrgID)
	if err != nil {
		writeDBErr(w, "customerUsage", err)
		return
	}
	if !ok {
		writeErr(w, http.StatusNotFound, "مشتری یافت نشد")
		return
	}

	rows, err := s.db.Query(`
		SELECT id, doc_type, COALESCE(number,''), status, COALESCE(issue_date,''), total_amount
		FROM inv_invoices WHERE customer_id = ? AND is_deleted = 0
		ORDER BY COALESCE(issue_date, DATE(created_at)) DESC, id DESC`, id)
	if err != nil {
		writeDBErr(w, "customerUsage invoices", err)
		return
	}
	invoices := []customerInvoiceRef{}
	for rows.Next() {
		var x customerInvoiceRef
		if err := rows.Scan(&x.ID, &x.DocType, &x.Number, &x.Status, &x.IssueDate, &x.TotalAmount); err != nil {
			rows.Close()
			writeDBErr(w, "customerUsage scan", err)
			return
		}
		invoices = append(invoices, x)
	}
	rows.Close()

	others := []customerRefCount{}
	total := len(invoices)
	for _, t := range customerRefTables {
		if t.Table == "inv_invoices" {
			continue
		}
		var n int
		if err := s.db.QueryRow("SELECT COUNT(*) FROM "+t.Table+" WHERE customer_id = ?", id).Scan(&n); err != nil {
			continue // جدولی که روی این سرور نیست/دسترسی ندارد نباید کل پاسخ را خراب کند
		}
		if n > 0 {
			others = append(others, customerRefCount{t.Table, t.Label, n})
			total += n
		}
	}
	writeJSON(w, http.StatusOK, map[string]any{
		"success": true, "id": id, "name": name,
		"invoices": invoices, "others": others, "total_refs": total,
	})
}

// POST /crm/api/customers/{id}/merge   {"target_id": n}
func (s *server) mergeCustomer(w http.ResponseWriter, r *http.Request) {
	id := idParam(r)
	u := userOf(r.Context())
	var in struct {
		TargetID int64 `json:"target_id"`
	}
	if !decodeJSON(w, r, &in, 1<<16) {
		return
	}
	if in.TargetID <= 0 || in.TargetID == id {
		writeErr(w, http.StatusBadRequest, "مشتری مقصد را انتخاب کنید (غیر از همین مشتری).")
		return
	}
	srcName, ok, err := s.customerInOrg(id, u.OrgID)
	if err != nil {
		writeDBErr(w, "mergeCustomer src", err)
		return
	}
	if !ok {
		writeErr(w, http.StatusNotFound, "مشتری یافت نشد")
		return
	}
	dstName, ok, err := s.customerInOrg(in.TargetID, u.OrgID)
	if err != nil {
		writeDBErr(w, "mergeCustomer dst", err)
		return
	}
	if !ok {
		writeErr(w, http.StatusBadRequest, "مشتری مقصد یافت نشد یا حذف شده است.")
		return
	}

	tx, err := s.db.Begin()
	if err != nil {
		writeDBErr(w, "mergeCustomer begin", err)
		return
	}
	defer tx.Rollback()

	moved := []customerRefCount{}
	left := []customerRefCount{}
	for _, t := range customerRefTables {
		verb := "UPDATE "
		if t.Unique {
			verb = "UPDATE IGNORE "
		}
		res, err := tx.Exec(verb+t.Table+" SET customer_id = ? WHERE customer_id = ?", in.TargetID, id)
		if err != nil {
			writeDBErr(w, "mergeCustomer "+t.Table, err)
			return
		}
		if n, _ := res.RowsAffected(); n > 0 {
			moved = append(moved, customerRefCount{t.Table, t.Label, int(n)})
		}
		if t.Unique {
			var rest int
			if err := tx.QueryRow("SELECT COUNT(*) FROM "+t.Table+" WHERE customer_id = ?", id).Scan(&rest); err == nil && rest > 0 {
				left = append(left, customerRefCount{t.Table, t.Label, rest})
			}
		}
	}
	if _, err := tx.Exec(
		"UPDATE crm_customers SET is_deleted = 1 WHERE id = ? AND organization_id = ?", id, u.OrgID); err != nil {
		writeDBErr(w, "mergeCustomer delete", err)
		return
	}
	if err := tx.Commit(); err != nil {
		writeDBErr(w, "mergeCustomer commit", err)
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{
		"success": true, "source_name": srcName, "target_id": in.TargetID, "target_name": dstName,
		"moved": moved, "left_on_deleted": left,
	})
}
