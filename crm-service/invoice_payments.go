package main

// واریزهای فاکتور — تسویهٔ یک‌جا یا چندمرحله‌ای (درخواست ۱۴۰۵/۰۸/۰۲).
//
// هر واریز یک ردیف inv_invoice_payments است (مهاجرت 20261024_110000). وضعیت تسویهٔ
// فاکتور (inv_invoices.settlement_status) دیگر دستی انتخاب نمی‌شود و بعد از هر
// ثبت/حذف واریز (و ویرایش مبلغ فاکتور) از جمع واریزها حساب می‌شود:
//   جمع = ۰        → unsettled
//   ۰ < جمع < مبلغ → partial
//   جمع ≥ مبلغ     → settled
//
// استثنا: فاکتورهای قدیمی که پیش از این قابلیت دستی «تسویه شده» یا «بخشی» خورده‌اند
// و هیچ واریزی ندارند، وضعیتشان دست نمی‌خورد (مبلغشان معلوم نیست)؛ با اولین واریز
// وارد همین محاسبه می‌شوند.
//
// حذف واریز نرم است تا سابقهٔ «چه کسی ثبت/حذف کرد» بماند.

import (
	"database/sql"
	"net/http"
	"strconv"
	"strings"
)

// جمع و تعداد واریزهای فعال هر فاکتور — برای LEFT JOIN در فهرست فاکتورها و گزارش همکاران
const paymentSumsSQL = `SELECT invoice_id, SUM(amount) AS paid, COUNT(*) AS cnt
	FROM inv_invoice_payments WHERE is_deleted = 0 GROUP BY invoice_id`

var paymentMethods = map[string]bool{"cash": true, "card": true, "cheque": true, "transfer": true}

type invoicePaymentOut struct {
	ID            int64  `json:"id"`
	Amount        int64  `json:"amount"`
	PayDate       string `json:"pay_date"`
	Method        string `json:"method"`
	Note          string `json:"note"`
	CreatedAt     string `json:"created_at"`
	CreatedByName string `json:"created_by_name"`
}

// sqlQueryer: *sql.DB یا *sql.Tx
type sqlQueryer interface {
	QueryRow(query string, args ...any) *sql.Row
	Exec(query string, args ...any) (sql.Result, error)
}

// جمع و تعداد واریزهای فعال یک فاکتور
func invoicePaidSum(q sqlQueryer, invoiceID int64) (paid int64, count int, err error) {
	err = q.QueryRow(`SELECT COALESCE(SUM(amount),0), COUNT(*) FROM inv_invoice_payments
		WHERE invoice_id = ? AND is_deleted = 0`, invoiceID).Scan(&paid, &count)
	return
}

// settlementFor: وضعیت تسویه از روی جمع واریزها و مبلغ فاکتور
func settlementFor(paid, total int64) string {
	switch {
	case paid <= 0:
		return "unsettled"
	case paid < total:
		return "partial"
	default:
		return "settled"
	}
}

// recomputeSettlement وضعیت تسویهٔ فاکتور را از واریزها به‌روز می‌کند. فاکتور بدون
// واریز فقط وقتی دست می‌خورد که force باشد (ثبت/حذف واریز)، تا وضعیت دستی قدیمی
// فاکتورهای بی‌واریز با ویرایش فاکتور پاک نشود.
func recomputeSettlement(q sqlQueryer, invoiceID int64, force bool) (string, error) {
	paid, count, err := invoicePaidSum(q, invoiceID)
	if err != nil {
		return "", err
	}
	var total int64
	var cur string
	if err := q.QueryRow("SELECT total_amount, settlement_status FROM inv_invoices WHERE id = ?", invoiceID).
		Scan(&total, &cur); err != nil {
		return "", err
	}
	if count == 0 && !force {
		return cur, nil
	}
	st := settlementFor(paid, total)
	if st != cur {
		if _, err := q.Exec("UPDATE inv_invoices SET settlement_status = ? WHERE id = ?", st, invoiceID); err != nil {
			return "", err
		}
	}
	return st, nil
}

// paymentsSummary: پاسخ مشترک فهرست/ثبت/حذف — صفحه ردیف جدولش را با همین به‌روز می‌کند
func (s *server) paymentsSummary(invoiceID, orgID int64) (map[string]any, error) {
	var total int64
	var settlement, status string
	if err := s.db.QueryRow(`SELECT total_amount, settlement_status, status FROM inv_invoices
		WHERE id = ? AND organization_id = ? AND is_deleted = 0`, invoiceID, orgID).
		Scan(&total, &settlement, &status); err != nil {
		return nil, err
	}
	rows, err := s.db.Query(`
		SELECT p.id, p.amount, DATE_FORMAT(p.pay_date,'%Y-%m-%d'), COALESCE(p.method,''), COALESCE(p.note,''),
		       DATE_FORMAT(p.created_at,'%Y-%m-%d %H:%i'),
		       TRIM(CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')))
		FROM inv_invoice_payments p
		LEFT JOIN users u ON u.id = p.created_by
		WHERE p.invoice_id = ? AND p.is_deleted = 0
		ORDER BY p.pay_date, p.id`, invoiceID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	items := []invoicePaymentOut{}
	var paid int64
	for rows.Next() {
		var p invoicePaymentOut
		if err := rows.Scan(&p.ID, &p.Amount, &p.PayDate, &p.Method, &p.Note, &p.CreatedAt, &p.CreatedByName); err != nil {
			return nil, err
		}
		paid += p.Amount
		items = append(items, p)
	}
	if err := rows.Err(); err != nil {
		return nil, err
	}
	remaining := total - paid
	if len(items) == 0 && settlement == "settled" {
		remaining = 0 // تسویهٔ قدیمی بدون مبلغ
	}
	if remaining < 0 {
		remaining = 0
	}
	var today string
	_ = s.db.QueryRow("SELECT DATE_FORMAT(CURDATE(),'%Y-%m-%d')").Scan(&today)
	return map[string]any{
		"success":           true,
		"items":             items,
		"total_amount":      total,
		"paid_amount":       paid,
		"remaining_amount":  remaining,
		"payment_count":     len(items),
		"settlement_status": settlement,
		"invoice_status":    status,
		"today":             today, // پیش‌فرض تاریخ واریز = امروزِ سرور، نه ساعت دستگاه
	}, nil
}

// GET /crm/api/inv/invoices/{id}/payments
func (s *server) listInvoicePayments(w http.ResponseWriter, r *http.Request) {
	id := idParam(r)
	u := userOf(r.Context())
	out, err := s.paymentsSummary(id, u.OrgID)
	if err == sql.ErrNoRows {
		writeErr(w, http.StatusNotFound, "فاکتور یافت نشد")
		return
	} else if err != nil {
		writeDBErr(w, "listInvoicePayments", err)
		return
	}
	writeJSON(w, http.StatusOK, out)
}

// POST /crm/api/inv/invoices/{id}/payments
// body: {amount, pay_date (YYYY-MM-DD میلادی), method, note}
func (s *server) addInvoicePayment(w http.ResponseWriter, r *http.Request) {
	id := idParam(r)
	u := userOf(r.Context())
	var in struct {
		Amount  int64  `json:"amount"`
		PayDate string `json:"pay_date"`
		Method  string `json:"method"`
		Note    string `json:"note"`
	}
	if !decodeJSON(w, r, &in, 1<<20) {
		return
	}
	in.Method = strings.TrimSpace(in.Method)
	in.Note = strings.TrimSpace(in.Note)
	if in.Amount <= 0 {
		writeErr(w, http.StatusBadRequest, "مبلغ واریز باید بیشتر از صفر باشد")
		return
	}
	if in.Method != "" && !paymentMethods[in.Method] {
		writeErr(w, http.StatusBadRequest, "روش پرداخت نامعتبر است")
		return
	}
	if len([]rune(in.Note)) > 500 {
		writeErr(w, http.StatusBadRequest, "توضیح واریز حداکثر ۵۰۰ نویسه است")
		return
	}
	payDate, _ := parseIssueDate(in.PayDate)
	if !payDate.Valid {
		writeErr(w, http.StatusBadRequest, "تاریخ واریز نامعتبر است")
		return
	}

	tx, err := s.db.Begin()
	if err != nil {
		writeDBErr(w, "addInvoicePayment", err)
		return
	}
	defer tx.Rollback()

	// قفل ردیف فاکتور تا دو ثبت هم‌زمان با هم از مانده رد نشوند
	var total int64
	var status string
	if err := tx.QueryRow(`SELECT total_amount, status FROM inv_invoices
		WHERE id = ? AND organization_id = ? AND is_deleted = 0 FOR UPDATE`, id, u.OrgID).
		Scan(&total, &status); err == sql.ErrNoRows {
		writeErr(w, http.StatusNotFound, "فاکتور یافت نشد")
		return
	} else if err != nil {
		writeDBErr(w, "addInvoicePayment", err)
		return
	}
	if status == "cancelled" {
		writeErr(w, http.StatusConflict, "برای فاکتور باطل‌شده نمی‌توان واریز ثبت کرد")
		return
	}
	paid, _, err := invoicePaidSum(tx, id)
	if err != nil {
		writeDBErr(w, "addInvoicePayment", err)
		return
	}
	if remaining := total - paid; in.Amount > remaining {
		writeErr(w, http.StatusUnprocessableEntity,
			"مبلغ واریز از ماندهٔ فاکتور ("+faMoney(remaining)+" ریال) بیشتر است")
		return
	}
	if _, err := tx.Exec(`INSERT INTO inv_invoice_payments
		(organization_id, invoice_id, amount, pay_date, method, note, created_by)
		VALUES (?, ?, ?, ?, ?, ?, ?)`,
		u.OrgID, id, in.Amount, payDate, nullIfEmpty(in.Method), nullIfEmpty(in.Note), u.ID); err != nil {
		writeDBErr(w, "addInvoicePayment", err)
		return
	}
	if _, err := recomputeSettlement(tx, id, true); err != nil {
		writeDBErr(w, "addInvoicePayment", err)
		return
	}
	if err := tx.Commit(); err != nil {
		writeDBErr(w, "addInvoicePayment", err)
		return
	}
	s.writePaymentsSummary(w, id, u.OrgID, "واریز ثبت شد")
}

// DELETE /crm/api/inv/invoices/{id}/payments/{pid} — حذف نرم
func (s *server) deleteInvoicePayment(w http.ResponseWriter, r *http.Request) {
	id := idParam(r)
	pid, _ := strconv.ParseInt(r.PathValue("pid"), 10, 64)
	u := userOf(r.Context())

	tx, err := s.db.Begin()
	if err != nil {
		writeDBErr(w, "deleteInvoicePayment", err)
		return
	}
	defer tx.Rollback()

	var one int
	if err := tx.QueryRow(`SELECT 1 FROM inv_invoices WHERE id = ? AND organization_id = ? AND is_deleted = 0 FOR UPDATE`,
		id, u.OrgID).Scan(&one); err == sql.ErrNoRows {
		writeErr(w, http.StatusNotFound, "فاکتور یافت نشد")
		return
	} else if err != nil {
		writeDBErr(w, "deleteInvoicePayment", err)
		return
	}
	res, err := tx.Exec(`UPDATE inv_invoice_payments SET is_deleted = 1, deleted_by = ?, deleted_at = NOW()
		WHERE id = ? AND invoice_id = ? AND organization_id = ? AND is_deleted = 0`, u.ID, pid, id, u.OrgID)
	if err != nil {
		writeDBErr(w, "deleteInvoicePayment", err)
		return
	}
	if n, _ := res.RowsAffected(); n == 0 {
		writeErr(w, http.StatusNotFound, "واریز یافت نشد")
		return
	}
	if _, err := recomputeSettlement(tx, id, true); err != nil {
		writeDBErr(w, "deleteInvoicePayment", err)
		return
	}
	if err := tx.Commit(); err != nil {
		writeDBErr(w, "deleteInvoicePayment", err)
		return
	}
	s.writePaymentsSummary(w, id, u.OrgID, "واریز حذف شد")
}

func (s *server) writePaymentsSummary(w http.ResponseWriter, invoiceID, orgID int64, msg string) {
	out, err := s.paymentsSummary(invoiceID, orgID)
	if err != nil {
		// خود ثبت/حذف انجام شده؛ فقط خلاصه خوانده نشد
		writeJSON(w, http.StatusOK, map[string]any{"success": true, "message": msg})
		return
	}
	out["message"] = msg
	writeJSON(w, http.StatusOK, out)
}
