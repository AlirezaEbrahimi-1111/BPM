package main

import (
	"database/sql"
	"net/http"
	"strings"
)

// یک ردیفِ کالا در پاسخِ لیست/ساخت.
type productOut struct {
	ID          int64   `json:"id"`
	Code        string  `json:"code"`
	Name        string  `json:"name"`
	Unit        string  `json:"unit"`
	UnitPrice   int64   `json:"unit_price"`
	IsService   bool    `json:"is_service"`
	IsTaxExempt bool    `json:"is_tax_exempt"`
	Stock       float64 `json:"stock"` // موجودیِ انبارِ «فاکتور رسمی»
}

// ورودیِ ساخت/ویرایشِ کالا.
type productIn struct {
	Code         string   `json:"code"`
	Name         string   `json:"name"`
	Unit         string   `json:"unit"`
	UnitPrice    int64    `json:"unit_price"`
	IsService    bool     `json:"is_service"`
	IsTaxExempt  bool     `json:"is_tax_exempt"`
	OpeningStock *float64 `json:"opening_stock"` // فقط هنگامِ ساخت — nil = صفر
}

func (p *productIn) normalize() {
	p.Name = strings.TrimSpace(p.Name)
	p.Code = strings.TrimSpace(p.Code)
	p.Unit = strings.TrimSpace(p.Unit)
	if p.Unit == "" {
		p.Unit = "عدد"
	}
	if p.UnitPrice < 0 {
		p.UnitPrice = 0
	}
}

// GET /crm/api/inv/products?q=&page=&per=
func (s *server) listProducts(w http.ResponseWriter, r *http.Request) {
	pg := parsePage(r)
	u := userOf(r.Context())

	where := "p.is_deleted = 0 AND p.organization_id = ?"
	args := []any{u.OrgID}
	if pg.Q != "" {
		where += " AND (p.name LIKE ? OR p.code LIKE ?)"
		like := "%" + pg.Q + "%"
		args = append(args, like, like)
	}

	var total int
	if err := s.db.QueryRow("SELECT COUNT(*) FROM inv_products p WHERE "+where, args...).Scan(&total); err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}

	rows, err := s.db.Query(`
		SELECT p.id, p.code, p.name, p.unit, p.unit_price, p.is_service, p.is_tax_exempt,
		       COALESCE(st.qty, 0)
		FROM inv_products p
		LEFT JOIN inv_stock st ON st.product_id = p.id AND st.warehouse_id = ?
		WHERE `+where+`
		ORDER BY p.name
		LIMIT ? OFFSET ?`,
		append([]any{s.officialWarehouseID}, append(args, pg.Limit, pg.Offset)...)...)
	if err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	defer rows.Close()

	items := []productOut{}
	for rows.Next() {
		var it productOut
		var code sql.NullString
		if err := rows.Scan(&it.ID, &code, &it.Name, &it.Unit, &it.UnitPrice, &it.IsService, &it.IsTaxExempt, &it.Stock); err != nil {
			writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
			return
		}
		it.Code = code.String
		items = append(items, it)
	}

	writeJSON(w, http.StatusOK, map[string]any{
		"items": items, "total": total, "page": pg.Page, "per": pg.Per,
	})
}

// POST /crm/api/inv/products
func (s *server) createProduct(w http.ResponseWriter, r *http.Request) {
	var in productIn
	if !decodeJSON(w, r, &in, 1<<20) {
		return
	}
	in.normalize()
	if in.Name == "" {
		writeErr(w, http.StatusBadRequest, "نام کالا الزامی است")
		return
	}
	u := userOf(r.Context())

	tx, err := s.db.Begin()
	if err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	defer tx.Rollback()

	res, err := tx.Exec(`
		INSERT INTO inv_products (organization_id, code, name, unit, unit_price, is_service, is_tax_exempt, created_by)
		VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
		u.OrgID, nullIfEmpty(in.Code), in.Name, in.Unit, in.UnitPrice, in.IsService, in.IsTaxExempt, u.ID)
	if err != nil {
		writeErr(w, http.StatusInternalServerError, "درجِ کالا ناموفق بود")
		return
	}
	pid, _ := res.LastInsertId()

	if in.OpeningStock != nil && *in.OpeningStock != 0 {
		if err := addStock(tx, pid, s.officialWarehouseID, *in.OpeningStock, "opening", "", 0, u.ID); err != nil {
			writeErr(w, http.StatusInternalServerError, "ثبتِ موجودیِ اولیه ناموفق بود")
			return
		}
	}

	if err := tx.Commit(); err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "id": pid})
}

// PUT /crm/api/inv/products/{id}  — موجودی از این مسیر تغییر نمی‌کند.
func (s *server) updateProduct(w http.ResponseWriter, r *http.Request) {
	id := idParam(r)
	var in productIn
	if !decodeJSON(w, r, &in, 1<<20) {
		return
	}
	in.normalize()
	if in.Name == "" {
		writeErr(w, http.StatusBadRequest, "نام کالا الزامی است")
		return
	}
	u := userOf(r.Context())

	res, err := s.db.Exec(`
		UPDATE inv_products
		SET code = ?, name = ?, unit = ?, unit_price = ?, is_service = ?, is_tax_exempt = ?
		WHERE id = ? AND organization_id = ? AND is_deleted = 0`,
		nullIfEmpty(in.Code), in.Name, in.Unit, in.UnitPrice, in.IsService, in.IsTaxExempt, id, u.OrgID)
	if err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	if n, _ := res.RowsAffected(); n == 0 {
		writeErr(w, http.StatusNotFound, "کالا یافت نشد")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true})
}

// DELETE /crm/api/inv/products/{id}  — حذفِ نرم.
func (s *server) deleteProduct(w http.ResponseWriter, r *http.Request) {
	id := idParam(r)
	u := userOf(r.Context())
	res, err := s.db.Exec(
		"UPDATE inv_products SET is_deleted = 1 WHERE id = ? AND organization_id = ? AND is_deleted = 0", id, u.OrgID)
	if err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	if n, _ := res.RowsAffected(); n == 0 {
		writeErr(w, http.StatusNotFound, "کالا یافت نشد")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true})
}

// POST /crm/api/inv/products/import   body: {"rows":[{code,name,unit,unit_price,is_tax_exempt,is_service,opening_stock}, ...]}
func (s *server) importProducts(w http.ResponseWriter, r *http.Request) {
	var body struct {
		Rows []productIn `json:"rows"`
	}
	if !decodeJSON(w, r, &body, 8<<20) {
		return
	}
	u := userOf(r.Context())

	tx, err := s.db.Begin()
	if err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	defer tx.Rollback()

	ins, err := tx.Prepare(`
		INSERT INTO inv_products (organization_id, code, name, unit, unit_price, is_service, is_tax_exempt, created_by)
		VALUES (?, ?, ?, ?, ?, ?, ?, ?)`)
	if err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	defer ins.Close()

	inserted := 0
	errs := []map[string]any{}
	for i, row := range body.Rows {
		row.normalize()
		if row.Name == "" {
			errs = append(errs, map[string]any{"row": i + 1, "message": "نام کالا خالی است"})
			continue
		}
		res, e := ins.Exec(u.OrgID, nullIfEmpty(row.Code), row.Name, row.Unit, row.UnitPrice, row.IsService, row.IsTaxExempt, u.ID)
		if e != nil {
			errs = append(errs, map[string]any{"row": i + 1, "message": "درج ناموفق"})
			continue
		}
		pid, _ := res.LastInsertId()
		if row.OpeningStock != nil && *row.OpeningStock != 0 {
			if e := addStock(tx, pid, s.officialWarehouseID, *row.OpeningStock, "opening", "", 0, u.ID); e != nil {
				errs = append(errs, map[string]any{"row": i + 1, "message": "موجودیِ اولیه ثبت نشد"})
			}
		}
		inserted++
	}

	if err := tx.Commit(); err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "inserted": inserted, "errors": errs})
}
