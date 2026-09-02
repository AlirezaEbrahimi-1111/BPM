package main

import (
	"database/sql"
	"fmt"
	"math"
	"net/http"
	"strings"
	"time"
)

// ───────────────────────── ورودی ─────────────────────────

type invoiceItemIn struct {
	ProductID   *int64  `json:"product_id"`
	Title       string  `json:"title"`
	Qty         float64 `json:"qty"`
	UnitPrice   int64   `json:"unit_price"`
	Discount    int64   `json:"discount"`
	IsTaxExempt bool    `json:"is_tax_exempt"`
}

type invoiceIn struct {
	DocType    string          `json:"doc_type"`
	CustomerID int64           `json:"customer_id"`
	IssueDate  string          `json:"issue_date"` // "YYYY-MM-DD" یا ""
	Note       string          `json:"note"`
	Items      []invoiceItemIn `json:"items"`
}

// ───────────────────────── خروجی ─────────────────────────

type invoiceItemOut struct {
	ID          int64   `json:"id"`
	ProductID   *int64  `json:"product_id"`
	Title       string  `json:"title"`
	Qty         float64 `json:"qty"`
	UnitPrice   int64   `json:"unit_price"`
	Discount    int64   `json:"discount"`
	IsTaxExempt bool    `json:"is_tax_exempt"`
	TaxRate     float64 `json:"tax_rate"`
	TaxAmount   int64   `json:"tax_amount"`
	LineTotal   int64   `json:"line_total"`
}

type invoiceOut struct {
	ID             int64            `json:"id"`
	DocType        string           `json:"doc_type"`
	Number         string           `json:"number"`
	SeqYear        *int             `json:"seq_year"`
	SeqNo          *int             `json:"seq_no"`
	CustomerID     int64            `json:"customer_id"`
	CustomerName   string           `json:"customer_name"`
	IssueDate      string           `json:"issue_date"`
	Status         string           `json:"status"`
	Source         string           `json:"source"`
	Subtotal       int64            `json:"subtotal_amount"`
	DiscountAmount int64            `json:"discount_amount"`
	TaxAmount      int64            `json:"tax_amount"`
	TotalAmount    int64            `json:"total_amount"`
	Note           string           `json:"note"`
	CreatedAt      string           `json:"created_at"`
	ApprovedAt     string           `json:"approved_at"`
	Items          []invoiceItemOut `json:"items,omitempty"`
}

// ───────────────────────── محاسبه ─────────────────────────

type computedLine struct {
	in        invoiceItemIn
	taxRate   float64
	taxAmount int64
	lineTotal int64
}

// محاسبه‌ی ردیف‌ها و جمع‌ها. مالیات فقط روی ردیف‌های غیرمعاف، با نرخِ اسنپ‌شات.
func computeInvoice(items []invoiceItemIn, vatRate float64) (lines []computedLine, subtotal, discount, tax, total int64) {
	for _, it := range items {
		gross := int64(math.Round(it.Qty * float64(it.UnitPrice)))
		afterDisc := gross - it.Discount
		if afterDisc < 0 {
			afterDisc = 0
		}
		rate := vatRate
		var t int64
		if it.IsTaxExempt {
			rate = 0
		} else {
			t = int64(math.Round(float64(afterDisc) * rate / 100))
		}
		lt := afterDisc + t
		lines = append(lines, computedLine{in: it, taxRate: rate, taxAmount: t, lineTotal: lt})
		subtotal += gross
		discount += it.Discount
		tax += t
		total += lt
	}
	return
}

func parseIssueDate(s string) (sql.NullString, time.Time) {
	s = strings.TrimSpace(s)
	if s == "" {
		return sql.NullString{}, time.Time{}
	}
	t, err := time.Parse("2006-01-02", s)
	if err != nil {
		return sql.NullString{}, time.Time{}
	}
	return sql.NullString{String: s, Valid: true}, t
}

// ───────────────────────── هندلرها ─────────────────────────

// GET /crm/api/inv/invoices?q=&status=&page=&per=
func (s *server) listInvoices(w http.ResponseWriter, r *http.Request) {
	pg := parsePage(r)
	u := userOf(r.Context())
	status := strings.TrimSpace(r.URL.Query().Get("status"))

	where := "i.organization_id = ?"
	args := []any{u.OrgID}
	if status != "" {
		where += " AND i.status = ?"
		args = append(args, status)
	}
	if pg.Q != "" {
		where += " AND (i.number LIKE ? OR c.name LIKE ?)"
		like := "%" + pg.Q + "%"
		args = append(args, like, like)
	}

	var total int
	if err := s.db.QueryRow(`
		SELECT COUNT(*) FROM inv_invoices i
		LEFT JOIN crm_customers c ON c.id = i.customer_id
		WHERE `+where, args...).Scan(&total); err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}

	rows, err := s.db.Query(`
		SELECT i.id, i.doc_type, COALESCE(i.number,''), i.seq_year, i.seq_no,
		       i.customer_id, COALESCE(c.name,'—'), COALESCE(i.issue_date,''),
		       i.status, i.source, i.subtotal_amount, i.discount_amount,
		       i.tax_amount, i.total_amount, COALESCE(i.note,''),
		       DATE_FORMAT(i.created_at,'%Y-%m-%d %H:%i'),
		       COALESCE(DATE_FORMAT(i.approved_at,'%Y-%m-%d %H:%i'),'')
		FROM inv_invoices i
		LEFT JOIN crm_customers c ON c.id = i.customer_id
		WHERE `+where+`
		ORDER BY i.id DESC
		LIMIT ? OFFSET ?`, append(args, pg.Limit, pg.Offset)...)
	if err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	defer rows.Close()

	items := []invoiceOut{}
	for rows.Next() {
		var o invoiceOut
		var sy, sn sql.NullInt64
		if err := rows.Scan(&o.ID, &o.DocType, &o.Number, &sy, &sn, &o.CustomerID, &o.CustomerName,
			&o.IssueDate, &o.Status, &o.Source, &o.Subtotal, &o.DiscountAmount, &o.TaxAmount,
			&o.TotalAmount, &o.Note, &o.CreatedAt, &o.ApprovedAt); err != nil {
			writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
			return
		}
		if sy.Valid {
			v := int(sy.Int64)
			o.SeqYear = &v
		}
		if sn.Valid {
			v := int(sn.Int64)
			o.SeqNo = &v
		}
		items = append(items, o)
	}
	writeJSON(w, http.StatusOK, map[string]any{"items": items, "total": total, "page": pg.Page, "per": pg.Per})
}

// GET /crm/api/inv/invoices/{id}
func (s *server) getInvoice(w http.ResponseWriter, r *http.Request) {
	id := idParam(r)
	u := userOf(r.Context())

	var o invoiceOut
	var sy, sn sql.NullInt64
	err := s.db.QueryRow(`
		SELECT i.id, i.doc_type, COALESCE(i.number,''), i.seq_year, i.seq_no,
		       i.customer_id, COALESCE(c.name,'—'), COALESCE(i.issue_date,''),
		       i.status, i.source, i.subtotal_amount, i.discount_amount,
		       i.tax_amount, i.total_amount, COALESCE(i.note,''),
		       DATE_FORMAT(i.created_at,'%Y-%m-%d %H:%i'),
		       COALESCE(DATE_FORMAT(i.approved_at,'%Y-%m-%d %H:%i'),'')
		FROM inv_invoices i
		LEFT JOIN crm_customers c ON c.id = i.customer_id
		WHERE i.id = ? AND i.organization_id = ?`, id, u.OrgID).
		Scan(&o.ID, &o.DocType, &o.Number, &sy, &sn, &o.CustomerID, &o.CustomerName,
			&o.IssueDate, &o.Status, &o.Source, &o.Subtotal, &o.DiscountAmount, &o.TaxAmount,
			&o.TotalAmount, &o.Note, &o.CreatedAt, &o.ApprovedAt)
	if err == sql.ErrNoRows {
		writeErr(w, http.StatusNotFound, "فاکتور یافت نشد")
		return
	}
	if err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	if sy.Valid {
		v := int(sy.Int64)
		o.SeqYear = &v
	}
	if sn.Valid {
		v := int(sn.Int64)
		o.SeqNo = &v
	}

	rows, err := s.db.Query(`
		SELECT id, product_id, title, qty, unit_price, discount, is_tax_exempt, tax_rate, tax_amount, line_total
		FROM inv_invoice_items WHERE invoice_id = ? ORDER BY sort_order, id`, id)
	if err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	defer rows.Close()
	o.Items = []invoiceItemOut{}
	for rows.Next() {
		var it invoiceItemOut
		var pid sql.NullInt64
		if err := rows.Scan(&it.ID, &pid, &it.Title, &it.Qty, &it.UnitPrice, &it.Discount,
			&it.IsTaxExempt, &it.TaxRate, &it.TaxAmount, &it.LineTotal); err != nil {
			writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
			return
		}
		if pid.Valid {
			v := pid.Int64
			it.ProductID = &v
		}
		o.Items = append(o.Items, it)
	}

	st, _ := s.getSettings()
	writeJSON(w, http.StatusOK, map[string]any{"invoice": o, "seller": st.Seller, "footer_note": st.FooterNote})
}

func normalizeDocType(t string) string {
	if strings.TrimSpace(t) == "proforma" {
		return "proforma"
	}
	return "official"
}

func (in *invoiceIn) validate() string {
	if in.CustomerID == 0 {
		return "انتخابِ مشتری الزامی است"
	}
	valid := 0
	for _, it := range in.Items {
		if strings.TrimSpace(it.Title) == "" {
			continue
		}
		if it.Qty <= 0 {
			return "تعدادِ هر ردیف باید بزرگ‌تر از صفر باشد"
		}
		valid++
	}
	if valid == 0 {
		return "حداقل یک ردیفِ کالا لازم است"
	}
	return ""
}

// POST /crm/api/inv/invoices  — همیشه پیش‌نویس
func (s *server) createInvoice(w http.ResponseWriter, r *http.Request) {
	var in invoiceIn
	if !decodeJSON(w, r, &in, 4<<20) {
		return
	}
	if msg := in.validate(); msg != "" {
		writeErr(w, http.StatusBadRequest, msg)
		return
	}
	u := userOf(r.Context())
	st, err := s.getSettings()
	if err != nil {
		writeErr(w, http.StatusInternalServerError, "خواندنِ تنظیمات ناموفق بود")
		return
	}

	issueNS, _ := parseIssueDate(in.IssueDate)
	docType := normalizeDocType(in.DocType)
	items := keepFilledItems(in.Items)
	lines, sub, disc, tax, total := computeInvoice(items, st.VatRate)

	tx, err := s.db.Begin()
	if err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	defer tx.Rollback()

	res, err := tx.Exec(`
		INSERT INTO inv_invoices
		  (organization_id, doc_type, customer_id, warehouse_id, issue_date, status, source,
		   subtotal_amount, discount_amount, tax_amount, total_amount, note, created_by)
		VALUES (?, ?, ?, ?, ?, 'draft', 'staff', ?, ?, ?, ?, ?, ?)`,
		u.OrgID, docType, in.CustomerID, s.officialWarehouseID, issueNS,
		sub, disc, tax, total, nullIfEmpty(in.Note), u.ID)
	if err != nil {
		writeErr(w, http.StatusInternalServerError, "درجِ فاکتور ناموفق بود")
		return
	}
	invID, _ := res.LastInsertId()

	if err := insertItems(tx, invID, lines); err != nil {
		writeErr(w, http.StatusInternalServerError, "درجِ ردیف‌ها ناموفق بود")
		return
	}
	if err := tx.Commit(); err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "id": invID})
}

// PUT /crm/api/inv/invoices/{id}  — فقط پیش‌نویس
func (s *server) updateInvoice(w http.ResponseWriter, r *http.Request) {
	id := idParam(r)
	var in invoiceIn
	if !decodeJSON(w, r, &in, 4<<20) {
		return
	}
	if msg := in.validate(); msg != "" {
		writeErr(w, http.StatusBadRequest, msg)
		return
	}
	u := userOf(r.Context())

	var status string
	if err := s.db.QueryRow(
		"SELECT status FROM inv_invoices WHERE id = ? AND organization_id = ?", id, u.OrgID).
		Scan(&status); err == sql.ErrNoRows {
		writeErr(w, http.StatusNotFound, "فاکتور یافت نشد")
		return
	} else if err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	if status != "draft" {
		writeErr(w, http.StatusConflict, "فقط فاکتورِ پیش‌نویس قابلِ ویرایش است")
		return
	}

	st, _ := s.getSettings()
	issueNS, _ := parseIssueDate(in.IssueDate)
	docType := normalizeDocType(in.DocType)
	items := keepFilledItems(in.Items)
	lines, sub, disc, tax, total := computeInvoice(items, st.VatRate)

	tx, err := s.db.Begin()
	if err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	defer tx.Rollback()

	if _, err := tx.Exec(`
		UPDATE inv_invoices
		SET doc_type = ?, customer_id = ?, issue_date = ?, note = ?,
		    subtotal_amount = ?, discount_amount = ?, tax_amount = ?, total_amount = ?
		WHERE id = ?`,
		docType, in.CustomerID, issueNS, nullIfEmpty(in.Note),
		sub, disc, tax, total, id); err != nil {
		writeErr(w, http.StatusInternalServerError, "به‌روزرسانی ناموفق بود")
		return
	}
	if _, err := tx.Exec("DELETE FROM inv_invoice_items WHERE invoice_id = ?", id); err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	if err := insertItems(tx, id, lines); err != nil {
		writeErr(w, http.StatusInternalServerError, "درجِ ردیف‌ها ناموفق بود")
		return
	}
	if err := tx.Commit(); err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true})
}

// POST /crm/api/inv/invoices/{id}/approve
func (s *server) approveInvoice(w http.ResponseWriter, r *http.Request) {
	id := idParam(r)
	u := userOf(r.Context())

	tx, err := s.db.Begin()
	if err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	defer tx.Rollback()

	var status, issueDate, docType string
	if err := tx.QueryRow(
		"SELECT status, COALESCE(issue_date,''), doc_type FROM inv_invoices WHERE id = ? AND organization_id = ? FOR UPDATE",
		id, u.OrgID).Scan(&status, &issueDate, &docType); err == sql.ErrNoRows {
		writeErr(w, http.StatusNotFound, "فاکتور یافت نشد")
		return
	} else if err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	if status != "draft" {
		writeErr(w, http.StatusConflict, "این فاکتور قبلاً از حالتِ پیش‌نویس خارج شده")
		return
	}

	// سالِ شمسی بر مبنایِ تاریخِ صدور (یا امروز اگر خالی)
	var jy int
	if _, t := parseIssueDate(issueDate); !t.IsZero() {
		jy = jalaliYearOf(t)
	} else {
		jy = jalaliYearOf(time.Time{})
	}

	var nextNo int
	if err := tx.QueryRow(
		"SELECT COALESCE(MAX(seq_no),0)+1 FROM inv_invoices WHERE seq_year = ? FOR UPDATE", jy).
		Scan(&nextNo); err != nil {
		writeErr(w, http.StatusInternalServerError, "تعیینِ شماره‌ی فاکتور ناموفق بود")
		return
	}

	var prefix string
	_ = tx.QueryRow("SELECT number_prefix FROM inv_settings WHERE id = 1").Scan(&prefix)
	number := fmt.Sprintf("%s%d/%d", prefix, jy, nextNo)

	if _, err := tx.Exec(`
		UPDATE inv_invoices
		SET status = 'approved', seq_year = ?, seq_no = ?, number = ?, approved_by = ?, approved_at = NOW()
		WHERE id = ?`, jy, nextNo, number, u.ID, id); err != nil {
		if strings.Contains(err.Error(), "1062") {
			writeErr(w, http.StatusConflict, "تداخلِ شماره‌ی فاکتور — دوباره تلاش کنید")
			return
		}
		writeErr(w, http.StatusInternalServerError, "تأیید ناموفق بود")
		return
	}

	// کسرِ موجودی فقط برای «فاکتورِ رسمی». پیش‌فاکتور فقط رزرو می‌کند (که در
	// listProducts محاسبه می‌شود) و با تأیید هم موجودیِ فیزیکی را کم نمی‌کند.
	// کمبودِ موجودی هرگز بلاک نمی‌کند.
	if docType == "official" {
		rows, err := tx.Query(
			"SELECT product_id, qty FROM inv_invoice_items WHERE invoice_id = ? AND product_id IS NOT NULL", id)
		if err != nil {
			writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
			return
		}
		type mv struct {
			pid int64
			qty float64
		}
		var moves []mv
		for rows.Next() {
			var m mv
			if err := rows.Scan(&m.pid, &m.qty); err != nil {
				rows.Close()
				writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
				return
			}
			moves = append(moves, m)
		}
		rows.Close()
		for _, m := range moves {
			if err := addStock(tx, m.pid, s.officialWarehouseID, -m.qty, "invoice", "invoice", id, u.ID); err != nil {
				writeErr(w, http.StatusInternalServerError, "ثبتِ حرکتِ انبار ناموفق بود")
				return
			}
		}
	}

	if err := tx.Commit(); err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "number": number})
}

// POST /crm/api/inv/invoices/{id}/cancel
func (s *server) cancelInvoice(w http.ResponseWriter, r *http.Request) {
	id := idParam(r)
	u := userOf(r.Context())

	tx, err := s.db.Begin()
	if err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	defer tx.Rollback()

	var status string
	if err := tx.QueryRow(
		"SELECT status FROM inv_invoices WHERE id = ? AND organization_id = ? FOR UPDATE", id, u.OrgID).
		Scan(&status); err == sql.ErrNoRows {
		writeErr(w, http.StatusNotFound, "فاکتور یافت نشد")
		return
	} else if err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	if status == "cancelled" {
		writeErr(w, http.StatusConflict, "این فاکتور قبلاً باطل شده")
		return
	}

	if status == "approved" {
		// برگرداندنِ موجودی: عکسِ همه‌ی حرکت‌هایِ این فاکتور.
		rows, err := tx.Query(
			"SELECT product_id, warehouse_id, qty FROM inv_stock_moves WHERE ref_type = 'invoice' AND ref_id = ?", id)
		if err != nil {
			writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
			return
		}
		type rmv struct {
			pid, wh int64
			qty     float64
		}
		var rev []rmv
		for rows.Next() {
			var m rmv
			if err := rows.Scan(&m.pid, &m.wh, &m.qty); err != nil {
				rows.Close()
				writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
				return
			}
			rev = append(rev, m)
		}
		rows.Close()
		for _, m := range rev {
			if err := addStock(tx, m.pid, m.wh, -m.qty, "return", "invoice", id, u.ID); err != nil {
				writeErr(w, http.StatusInternalServerError, "برگرداندنِ موجودی ناموفق بود")
				return
			}
		}
	}

	if _, err := tx.Exec("UPDATE inv_invoices SET status = 'cancelled' WHERE id = ?", id); err != nil {
		writeErr(w, http.StatusInternalServerError, "ابطال ناموفق بود")
		return
	}
	if err := tx.Commit(); err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true})
}

// DELETE /crm/api/inv/invoices/{id}  — فقط پیش‌نویس
func (s *server) deleteInvoice(w http.ResponseWriter, r *http.Request) {
	id := idParam(r)
	u := userOf(r.Context())

	tx, err := s.db.Begin()
	if err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	defer tx.Rollback()

	var status string
	if err := tx.QueryRow(
		"SELECT status FROM inv_invoices WHERE id = ? AND organization_id = ?", id, u.OrgID).
		Scan(&status); err == sql.ErrNoRows {
		writeErr(w, http.StatusNotFound, "فاکتور یافت نشد")
		return
	} else if err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	if status != "draft" {
		writeErr(w, http.StatusConflict, "فقط فاکتورِ پیش‌نویس حذف می‌شود؛ فاکتورِ تأییدشده را «باطل» کنید")
		return
	}
	if _, err := tx.Exec("DELETE FROM inv_invoice_items WHERE invoice_id = ?", id); err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	if _, err := tx.Exec("DELETE FROM inv_invoices WHERE id = ?", id); err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	if err := tx.Commit(); err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true})
}

// ───────────────────────── کمکی ─────────────────────────

func keepFilledItems(items []invoiceItemIn) []invoiceItemIn {
	out := make([]invoiceItemIn, 0, len(items))
	for _, it := range items {
		if strings.TrimSpace(it.Title) == "" {
			continue
		}
		if it.UnitPrice < 0 {
			it.UnitPrice = 0
		}
		if it.Discount < 0 {
			it.Discount = 0
		}
		out = append(out, it)
	}
	return out
}

func insertItems(tx *sql.Tx, invID int64, lines []computedLine) error {
	stmt, err := tx.Prepare(`
		INSERT INTO inv_invoice_items
		  (invoice_id, product_id, title, qty, unit_price, discount, is_tax_exempt, tax_rate, tax_amount, line_total, sort_order)
		VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`)
	if err != nil {
		return err
	}
	defer stmt.Close()
	for i, ln := range lines {
		var pid any
		if ln.in.ProductID != nil && *ln.in.ProductID != 0 {
			pid = *ln.in.ProductID
		}
		if _, err := stmt.Exec(invID, pid, strings.TrimSpace(ln.in.Title), ln.in.Qty, ln.in.UnitPrice,
			ln.in.Discount, ln.in.IsTaxExempt, ln.taxRate, ln.taxAmount, ln.lineTotal, i); err != nil {
			return err
		}
	}
	return nil
}
