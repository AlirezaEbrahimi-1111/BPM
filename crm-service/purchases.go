package main

import (
	"database/sql"
	"math"
	"net/http"
	"strings"
)

// فاکتور خرید — با «تأیید» موجودی انبار رسمی زیاد می‌شود.
// جدول‌ها: inv_purchase_invoices + inv_purchase_items (هر ردیف حتما کالا دارد).

type purchaseItemIn struct {
	ProductID *int64  `json:"product_id"`
	Title     string  `json:"title"` // نام تایپ‌شده وقتی با هیچ کالای کاتالوگ مطابقت نداشت
	Qty       float64 `json:"qty"`
	UnitPrice int64   `json:"unit_price"`
	Discount  int64   `json:"discount"`
}

type purchaseIn struct {
	SupplierID        int64            `json:"supplier_id"`
	SupplierRefNumber string           `json:"supplier_ref_number"`
	IssueDate         string           `json:"issue_date"`
	Note              string           `json:"note"`
	Items             []purchaseItemIn `json:"items"`
}

type purchaseItemOut struct {
	ID        int64   `json:"id"`
	ProductID int64   `json:"product_id"`
	Name      string  `json:"name"`
	Qty       float64 `json:"qty"`
	UnitPrice int64   `json:"unit_price"`
	Discount  int64   `json:"discount"`
	TaxAmount int64   `json:"tax_amount"`
	LineTotal int64   `json:"line_total"`
}

type purchaseOut struct {
	ID                int64             `json:"id"`
	SupplierID        int64             `json:"supplier_id"`
	SupplierName      string            `json:"supplier_name"`
	SupplierRefNumber string            `json:"supplier_ref_number"`
	IssueDate         string            `json:"issue_date"`
	Status            string            `json:"status"`
	Subtotal          int64             `json:"subtotal_amount"`
	DiscountAmount    int64             `json:"discount_amount"`
	TaxAmount         int64             `json:"tax_amount"`
	TotalAmount       int64             `json:"total_amount"`
	Note              string            `json:"note"`
	CreatedAt         string            `json:"created_at"`
	ConfirmedAt       string            `json:"confirmed_at"`
	Items             []purchaseItemOut `json:"items,omitempty"`
}

// dbQuerier: هم *sql.DB هم *sql.Tx این را دارند — برای این‌که یک تابع بتواند
// چه داخل تراکنش، چه بیرونش کوئری بزند (لازم برای دیدن کالاهایی که همین حالا،
// در همین تراکنش، برای یک ردیف متنی آزاد تازه ساخته شده‌اند).
type dbQuerier interface {
	Query(query string, args ...any) (*sql.Rows, error)
}

// نقشه‌ی «معاف از مالیات» برای کالاهای داده‌شده.
func (s *server) productExemptMap(q dbQuerier, ids []int64) (map[int64]bool, error) {
	out := map[int64]bool{}
	if len(ids) == 0 {
		return out, nil
	}
	ph := strings.TrimRight(strings.Repeat("?,", len(ids)), ",")
	args := make([]any, len(ids))
	for i, v := range ids {
		args[i] = v
	}
	rows, err := q.Query("SELECT id, is_tax_exempt FROM inv_products WHERE id IN ("+ph+")", args...)
	if err != nil {
		return out, err
	}
	defer rows.Close()
	for rows.Next() {
		var id int64
		var ex bool
		if err := rows.Scan(&id, &ex); err != nil {
			return out, err
		}
		out[id] = ex
	}
	return out, nil
}

type computedPLine struct {
	in        purchaseItemIn
	taxAmount int64
	lineTotal int64
}

func computePurchase(items []purchaseItemIn, exempt map[int64]bool, vatRate float64) (lines []computedPLine, subtotal, discount, tax, total int64) {
	for _, it := range items {
		gross := int64(math.Round(it.Qty * float64(it.UnitPrice)))
		after := gross - it.Discount
		if after < 0 {
			after = 0
		}
		var pid int64
		if it.ProductID != nil {
			pid = *it.ProductID
		}
		var t int64
		if !exempt[pid] {
			t = int64(math.Round(float64(after) * vatRate / 100))
		}
		lt := after + t
		lines = append(lines, computedPLine{in: it, taxAmount: t, lineTotal: lt})
		subtotal += gross
		discount += it.Discount
		tax += t
		total += lt
	}
	return
}

// cleanPurchaseItems: ردیفی نگه داشته می‌شود که یا شناسهٔ کالای معتبر دارد،
// یا نام تایپ‌شده (که هنگام درج، کالای تازه‌ای برایش ساخته می‌شود) — مثل
// قلم متنی آزاد فاکتور فروش.
func cleanPurchaseItems(items []purchaseItemIn) []purchaseItemIn {
	out := make([]purchaseItemIn, 0, len(items))
	for _, it := range items {
		hasProduct := it.ProductID != nil && *it.ProductID > 0
		if !hasProduct && strings.TrimSpace(it.Title) == "" {
			continue
		}
		if it.Qty <= 0 {
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

// resolvePurchaseItems: برای هر ردیف بدون شناسهٔ کالا (یعنی متن تایپ‌شده با
// هیچ کالای کاتالوگی — سمت کلاینت — یکی نشد)، یک کالای تازه در کاتالوگ
// می‌سازد و ردیف را به آن گره می‌زند — دقیقا مثل قلم متنی آزاد فاکتور
// فروش (invoices.go: insertItems)، عمدا بدون حذف تکراری سمت سرور؛ تطبیق
// نام با کاتالوگ کار کلاینت است (rowTemplate → syncProdName).
// باید با همان tx-یی صدا زده شود که درج خود فاکتور هم در آن انجام می‌شود،
// چون کالاهای تازه‌ساز تا commit برای کوئری‌های بیرون تراکنش دیده نمی‌شوند.
func resolvePurchaseItems(tx *sql.Tx, orgID, userID int64, items []purchaseItemIn) ([]purchaseItemIn, error) {
	out := make([]purchaseItemIn, len(items))
	copy(out, items)
	for i, it := range out {
		if it.ProductID != nil && *it.ProductID > 0 {
			continue
		}
		title := strings.TrimSpace(it.Title)
		if title == "" {
			continue
		}
		res, err := tx.Exec(
			"INSERT INTO inv_products (organization_id, name, unit_price, created_by) VALUES (?, ?, ?, ?)",
			orgID, title, it.UnitPrice, userID)
		if err != nil {
			return nil, err
		}
		newID, _ := res.LastInsertId()
		out[i].ProductID = &newID
	}
	return out, nil
}

func (in *purchaseIn) validate() string {
	if in.SupplierID == 0 {
		return "انتخاب تأمین‌کننده الزامی است"
	}
	if len(cleanPurchaseItems(in.Items)) == 0 {
		return "حداقل یک ردیف کالا با تعداد معتبر لازم است"
	}
	return ""
}

// GET /crm/api/inv/purchases?q=&status=&page=&per=
func (s *server) listPurchases(w http.ResponseWriter, r *http.Request) {
	pg := parsePage(r)
	u := userOf(r.Context())
	status := strings.TrimSpace(r.URL.Query().Get("status"))

	where := "p.organization_id = ?"
	args := []any{u.OrgID}
	if status != "" {
		where += " AND p.status = ?"
		args = append(args, status)
	}
	if pg.Q != "" {
		where += " AND (COALESCE(p.supplier_ref_number,'') LIKE ? OR sp.name LIKE ?)"
		like := "%" + pg.Q + "%"
		args = append(args, like, like)
	}

	var total int
	if err := s.db.QueryRow(`
		SELECT COUNT(*) FROM inv_purchase_invoices p
		LEFT JOIN inv_suppliers sp ON sp.id = p.supplier_id
		WHERE `+where, args...).Scan(&total); err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}

	rows, err := s.db.Query(`
		SELECT p.id, p.supplier_id, COALESCE(sp.name,'—'), COALESCE(p.supplier_ref_number,''),
		       COALESCE(p.issue_date,''), p.status, p.subtotal_amount, p.discount_amount,
		       p.tax_amount, p.total_amount, COALESCE(p.note,''),
		       DATE_FORMAT(p.created_at,'%Y-%m-%d %H:%i'),
		       COALESCE(DATE_FORMAT(p.confirmed_at,'%Y-%m-%d %H:%i'),'')
		FROM inv_purchase_invoices p
		LEFT JOIN inv_suppliers sp ON sp.id = p.supplier_id
		WHERE `+where+`
		ORDER BY p.id DESC
		LIMIT ? OFFSET ?`, append(args, pg.Limit, pg.Offset)...)
	if err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	defer rows.Close()

	items := []purchaseOut{}
	for rows.Next() {
		var o purchaseOut
		if err := rows.Scan(&o.ID, &o.SupplierID, &o.SupplierName, &o.SupplierRefNumber, &o.IssueDate,
			&o.Status, &o.Subtotal, &o.DiscountAmount, &o.TaxAmount, &o.TotalAmount, &o.Note,
			&o.CreatedAt, &o.ConfirmedAt); err != nil {
			writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
			return
		}
		items = append(items, o)
	}
	writeJSON(w, http.StatusOK, map[string]any{"items": items, "total": total, "page": pg.Page, "per": pg.Per})
}

// GET /crm/api/inv/purchases/{id}
func (s *server) getPurchase(w http.ResponseWriter, r *http.Request) {
	id := idParam(r)
	u := userOf(r.Context())

	var o purchaseOut
	err := s.db.QueryRow(`
		SELECT p.id, p.supplier_id, COALESCE(sp.name,'—'), COALESCE(p.supplier_ref_number,''),
		       COALESCE(p.issue_date,''), p.status, p.subtotal_amount, p.discount_amount,
		       p.tax_amount, p.total_amount, COALESCE(p.note,''),
		       DATE_FORMAT(p.created_at,'%Y-%m-%d %H:%i'),
		       COALESCE(DATE_FORMAT(p.confirmed_at,'%Y-%m-%d %H:%i'),'')
		FROM inv_purchase_invoices p
		LEFT JOIN inv_suppliers sp ON sp.id = p.supplier_id
		WHERE p.id = ? AND p.organization_id = ?`, id, u.OrgID).
		Scan(&o.ID, &o.SupplierID, &o.SupplierName, &o.SupplierRefNumber, &o.IssueDate, &o.Status,
			&o.Subtotal, &o.DiscountAmount, &o.TaxAmount, &o.TotalAmount, &o.Note, &o.CreatedAt, &o.ConfirmedAt)
	if err == sql.ErrNoRows {
		writeErr(w, http.StatusNotFound, "فاکتور خرید یافت نشد")
		return
	}
	if err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}

	rows, err := s.db.Query(`
		SELECT it.id, it.product_id, COALESCE(pr.name,'—'), it.qty, it.unit_price, it.discount, it.tax_amount, it.line_total
		FROM inv_purchase_items it
		LEFT JOIN inv_products pr ON pr.id = it.product_id
		WHERE it.purchase_invoice_id = ? ORDER BY it.sort_order, it.id`, id)
	if err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	defer rows.Close()
	o.Items = []purchaseItemOut{}
	for rows.Next() {
		var it purchaseItemOut
		if err := rows.Scan(&it.ID, &it.ProductID, &it.Name, &it.Qty, &it.UnitPrice, &it.Discount, &it.TaxAmount, &it.LineTotal); err != nil {
			writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
			return
		}
		o.Items = append(o.Items, it)
	}
	writeJSON(w, http.StatusOK, map[string]any{"purchase": o})
}

// buildPurchaseLines: items باید از قبل با resolvePurchaseItems حل شده باشند
// (یعنی همه‌شان product_id دارند). حتما با همان tx صدا زده شود.
func (s *server) buildPurchaseLines(tx *sql.Tx, items []purchaseItemIn, vatRate float64) ([]computedPLine, int64, int64, int64, int64, error) {
	ids := make([]int64, 0, len(items))
	for _, it := range items {
		if it.ProductID != nil {
			ids = append(ids, *it.ProductID)
		}
	}
	exempt, err := s.productExemptMap(tx, ids)
	if err != nil {
		return nil, 0, 0, 0, 0, err
	}
	lines, sub, disc, tax, total := computePurchase(items, exempt, vatRate)
	return lines, sub, disc, tax, total, nil
}

func insertPurchaseItems(tx *sql.Tx, pid int64, lines []computedPLine) error {
	stmt, err := tx.Prepare(`
		INSERT INTO inv_purchase_items
		  (purchase_invoice_id, product_id, qty, unit_price, discount, tax_amount, line_total, sort_order)
		VALUES (?, ?, ?, ?, ?, ?, ?, ?)`)
	if err != nil {
		return err
	}
	defer stmt.Close()
	for i, ln := range lines {
		var pidVal any
		if ln.in.ProductID != nil {
			pidVal = *ln.in.ProductID
		}
		if _, err := stmt.Exec(pid, pidVal, ln.in.Qty, ln.in.UnitPrice, ln.in.Discount,
			ln.taxAmount, ln.lineTotal, i); err != nil {
			return err
		}
	}
	return nil
}

// POST /crm/api/inv/purchases  — همیشه پیش‌نویس
func (s *server) createPurchase(w http.ResponseWriter, r *http.Request) {
	var in purchaseIn
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
		writeErr(w, http.StatusInternalServerError, "خواندن تنظیمات ناموفق بود")
		return
	}
	issueNS, _ := parseIssueDate(in.IssueDate)

	tx, err := s.db.Begin()
	if err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	defer tx.Rollback()

	resolved, err := resolvePurchaseItems(tx, u.OrgID, u.ID, cleanPurchaseItems(in.Items))
	if err != nil {
		writeErr(w, http.StatusInternalServerError, "ثبت کالا ناموفق بود")
		return
	}
	lines, sub, disc, tax, total, err := s.buildPurchaseLines(tx, resolved, st.VatRate)
	if err != nil {
		writeErr(w, http.StatusInternalServerError, "محاسبه‌ی ردیف‌ها ناموفق بود")
		return
	}

	res, err := tx.Exec(`
		INSERT INTO inv_purchase_invoices
		  (organization_id, supplier_id, supplier_ref_number, warehouse_id, issue_date, status,
		   subtotal_amount, discount_amount, tax_amount, total_amount, note, created_by)
		VALUES (?, ?, ?, ?, ?, 'draft', ?, ?, ?, ?, ?, ?)`,
		u.OrgID, in.SupplierID, nullIfEmpty(strings.TrimSpace(in.SupplierRefNumber)), s.officialWarehouseID,
		issueNS, sub, disc, tax, total, nullIfEmpty(strings.TrimSpace(in.Note)), u.ID)
	if err != nil {
		writeErr(w, http.StatusInternalServerError, "درج فاکتور خرید ناموفق بود")
		return
	}
	pid, _ := res.LastInsertId()
	if err := insertPurchaseItems(tx, pid, lines); err != nil {
		writeErr(w, http.StatusInternalServerError, "درج ردیف‌ها ناموفق بود")
		return
	}
	if err := tx.Commit(); err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "id": pid})
}

// PUT /crm/api/inv/purchases/{id}  — فقط پیش‌نویس
func (s *server) updatePurchase(w http.ResponseWriter, r *http.Request) {
	id := idParam(r)
	var in purchaseIn
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
		"SELECT status FROM inv_purchase_invoices WHERE id = ? AND organization_id = ?", id, u.OrgID).
		Scan(&status); err == sql.ErrNoRows {
		writeErr(w, http.StatusNotFound, "فاکتور خرید یافت نشد")
		return
	} else if err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	if status != "draft" {
		writeErr(w, http.StatusConflict, "فقط فاکتور خرید پیش‌نویس قابل ویرایش است")
		return
	}

	st, err := s.getSettings()
	if err != nil {
		writeErr(w, http.StatusInternalServerError, "خواندن تنظیمات ناموفق بود")
		return
	}
	issueNS, _ := parseIssueDate(in.IssueDate)

	tx, err := s.db.Begin()
	if err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	defer tx.Rollback()

	resolved, err := resolvePurchaseItems(tx, u.OrgID, u.ID, cleanPurchaseItems(in.Items))
	if err != nil {
		writeErr(w, http.StatusInternalServerError, "ثبت کالا ناموفق بود")
		return
	}
	lines, sub, disc, tax, total, err := s.buildPurchaseLines(tx, resolved, st.VatRate)
	if err != nil {
		writeErr(w, http.StatusInternalServerError, "محاسبه‌ی ردیف‌ها ناموفق بود")
		return
	}

	if _, err := tx.Exec(`
		UPDATE inv_purchase_invoices
		SET supplier_id = ?, supplier_ref_number = ?, issue_date = ?, note = ?,
		    subtotal_amount = ?, discount_amount = ?, tax_amount = ?, total_amount = ?
		WHERE id = ?`,
		in.SupplierID, nullIfEmpty(strings.TrimSpace(in.SupplierRefNumber)), issueNS,
		nullIfEmpty(strings.TrimSpace(in.Note)), sub, disc, tax, total, id); err != nil {
		writeErr(w, http.StatusInternalServerError, "به‌روزرسانی ناموفق بود")
		return
	}
	if _, err := tx.Exec("DELETE FROM inv_purchase_items WHERE purchase_invoice_id = ?", id); err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	if err := insertPurchaseItems(tx, id, lines); err != nil {
		writeErr(w, http.StatusInternalServerError, "درج ردیف‌ها ناموفق بود")
		return
	}
	if err := tx.Commit(); err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true})
}

// POST /crm/api/inv/purchases/{id}/confirm  — موجودی را زیاد می‌کند
func (s *server) confirmPurchase(w http.ResponseWriter, r *http.Request) {
	id := idParam(r)
	u := userOf(r.Context())

	tx, err := s.db.Begin()
	if err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	defer tx.Rollback()

	var status string
	var whID int64
	if err := tx.QueryRow(
		"SELECT status, warehouse_id FROM inv_purchase_invoices WHERE id = ? AND organization_id = ? FOR UPDATE",
		id, u.OrgID).Scan(&status, &whID); err == sql.ErrNoRows {
		writeErr(w, http.StatusNotFound, "فاکتور خرید یافت نشد")
		return
	} else if err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	if status != "draft" {
		writeErr(w, http.StatusConflict, "این فاکتور خرید قبلا از حالت پیش‌نویس خارج شده")
		return
	}

	rows, err := tx.Query("SELECT product_id, qty FROM inv_purchase_items WHERE purchase_invoice_id = ?", id)
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
		if err := addStock(tx, m.pid, whID, m.qty, "purchase", "purchase", id, u.ID); err != nil {
			writeErr(w, http.StatusInternalServerError, "افزودن موجودی ناموفق بود")
			return
		}
	}
	if _, err := tx.Exec(
		"UPDATE inv_purchase_invoices SET status = 'confirmed', confirmed_by = ?, confirmed_at = NOW() WHERE id = ?",
		u.ID, id); err != nil {
		writeErr(w, http.StatusInternalServerError, "تأیید ناموفق بود")
		return
	}
	if err := tx.Commit(); err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true})
}

// POST /crm/api/inv/purchases/{id}/cancel
func (s *server) cancelPurchase(w http.ResponseWriter, r *http.Request) {
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
		"SELECT status FROM inv_purchase_invoices WHERE id = ? AND organization_id = ? FOR UPDATE", id, u.OrgID).
		Scan(&status); err == sql.ErrNoRows {
		writeErr(w, http.StatusNotFound, "فاکتور خرید یافت نشد")
		return
	} else if err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	if status == "cancelled" {
		writeErr(w, http.StatusConflict, "این فاکتور خرید قبلا باطل شده")
		return
	}

	if status == "confirmed" {
		rows, err := tx.Query(
			"SELECT product_id, warehouse_id, qty FROM inv_stock_moves WHERE ref_type = 'purchase' AND ref_id = ?", id)
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
			if err := addStock(tx, m.pid, m.wh, -m.qty, "return", "purchase", id, u.ID); err != nil {
				writeErr(w, http.StatusInternalServerError, "برگرداندن موجودی ناموفق بود")
				return
			}
		}
	}

	if _, err := tx.Exec("UPDATE inv_purchase_invoices SET status = 'cancelled' WHERE id = ?", id); err != nil {
		writeErr(w, http.StatusInternalServerError, "ابطال ناموفق بود")
		return
	}
	if err := tx.Commit(); err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true})
}

// DELETE /crm/api/inv/purchases/{id}  — فقط پیش‌نویس
func (s *server) deletePurchase(w http.ResponseWriter, r *http.Request) {
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
		"SELECT status FROM inv_purchase_invoices WHERE id = ? AND organization_id = ?", id, u.OrgID).
		Scan(&status); err == sql.ErrNoRows {
		writeErr(w, http.StatusNotFound, "فاکتور خرید یافت نشد")
		return
	} else if err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	if status != "draft" {
		writeErr(w, http.StatusConflict, "فقط فاکتور خرید پیش‌نویس حذف می‌شود؛ فاکتور تأییدشده را «باطل» کنید")
		return
	}
	if _, err := tx.Exec("DELETE FROM inv_purchase_items WHERE purchase_invoice_id = ?", id); err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	if _, err := tx.Exec("DELETE FROM inv_purchase_invoices WHERE id = ?", id); err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	if err := tx.Commit(); err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true})
}
