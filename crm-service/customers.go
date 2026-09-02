package main

import (
	"net/http"
	"strings"
)

// مشتری در جدولِ crm_customers است و بینِ CRM و فاکتور مشترک است.

type customerOut struct {
	ID           int64  `json:"id"`
	Type         string `json:"type"` // individual | legal
	Name         string `json:"name"`
	Phone        string `json:"phone"`
	Mobile       string `json:"mobile"`
	NationalID   string `json:"national_id"`
	EconomicCode string `json:"economic_code"`
	Province     string `json:"province"`
	City         string `json:"city"`
	PostalCode   string `json:"postal_code"`
	Address      string `json:"address"`
}

type customerIn struct {
	Type         string `json:"type"`
	Name         string `json:"name"`
	Phone        string `json:"phone"`
	Mobile       string `json:"mobile"`
	NationalID   string `json:"national_id"`
	EconomicCode string `json:"economic_code"`
	Province     string `json:"province"`
	City         string `json:"city"`
	PostalCode   string `json:"postal_code"`
	Address      string `json:"address"`
}

func (c *customerIn) normalize() {
	c.Name = strings.TrimSpace(c.Name)
	c.Type = customerType(c.Type)
	c.Phone = strings.TrimSpace(c.Phone)
	c.Mobile = strings.TrimSpace(c.Mobile)
	c.NationalID = strings.TrimSpace(c.NationalID)
	c.EconomicCode = strings.TrimSpace(c.EconomicCode)
	c.Province = strings.TrimSpace(c.Province)
	c.City = strings.TrimSpace(c.City)
	c.PostalCode = strings.TrimSpace(c.PostalCode)
	c.Address = strings.TrimSpace(c.Address)
}

// GET /crm/api/customers?q=&page=&per=
func (s *server) listCustomers(w http.ResponseWriter, r *http.Request) {
	pg := parsePage(r)
	u := userOf(r.Context())

	where := "is_deleted = 0 AND organization_id = ?"
	args := []any{u.OrgID}
	if pg.Q != "" {
		where += " AND (name LIKE ? OR phone LIKE ? OR mobile LIKE ? OR national_id LIKE ?)"
		like := "%" + pg.Q + "%"
		args = append(args, like, like, like, like)
	}

	var total int
	if err := s.db.QueryRow("SELECT COUNT(*) FROM crm_customers WHERE "+where, args...).Scan(&total); err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}

	rows, err := s.db.Query(`
		SELECT id, type, name,
		       COALESCE(phone,''), COALESCE(mobile,''), COALESCE(national_id,''),
		       COALESCE(economic_code,''), COALESCE(province,''), COALESCE(city,''),
		       COALESCE(postal_code,''), COALESCE(address,'')
		FROM crm_customers
		WHERE `+where+`
		ORDER BY name
		LIMIT ? OFFSET ?`, append(args, pg.Limit, pg.Offset)...)
	if err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	defer rows.Close()

	items := []customerOut{}
	for rows.Next() {
		var it customerOut
		if err := rows.Scan(&it.ID, &it.Type, &it.Name, &it.Phone, &it.Mobile, &it.NationalID,
			&it.EconomicCode, &it.Province, &it.City, &it.PostalCode, &it.Address); err != nil {
			writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
			return
		}
		items = append(items, it)
	}

	writeJSON(w, http.StatusOK, map[string]any{
		"items": items, "total": total, "page": pg.Page, "per": pg.Per,
	})
}

// POST /crm/api/customers
func (s *server) createCustomer(w http.ResponseWriter, r *http.Request) {
	var in customerIn
	if !decodeJSON(w, r, &in, 1<<20) {
		return
	}
	in.normalize()
	if in.Name == "" {
		writeErr(w, http.StatusBadRequest, "نام مشتری الزامی است")
		return
	}
	u := userOf(r.Context())

	res, err := s.db.Exec(`
		INSERT INTO crm_customers
		  (organization_id, type, name, phone, mobile, national_id, economic_code,
		   province, city, postal_code, address, created_by)
		VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
		u.OrgID, in.Type, in.Name, nullIfEmpty(in.Phone), nullIfEmpty(in.Mobile),
		nullIfEmpty(in.NationalID), nullIfEmpty(in.EconomicCode),
		nullIfEmpty(in.Province), nullIfEmpty(in.City), nullIfEmpty(in.PostalCode),
		nullIfEmpty(in.Address), u.ID)
	if err != nil {
		writeErr(w, http.StatusInternalServerError, "درجِ مشتری ناموفق بود")
		return
	}
	id, _ := res.LastInsertId()
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "id": id})
}

// PUT /crm/api/customers/{id}
func (s *server) updateCustomer(w http.ResponseWriter, r *http.Request) {
	id := idParam(r)
	var in customerIn
	if !decodeJSON(w, r, &in, 1<<20) {
		return
	}
	in.normalize()
	if in.Name == "" {
		writeErr(w, http.StatusBadRequest, "نام مشتری الزامی است")
		return
	}
	u := userOf(r.Context())

	res, err := s.db.Exec(`
		UPDATE crm_customers
		SET type = ?, name = ?, phone = ?, mobile = ?, national_id = ?, economic_code = ?,
		    province = ?, city = ?, postal_code = ?, address = ?
		WHERE id = ? AND organization_id = ? AND is_deleted = 0`,
		in.Type, in.Name, nullIfEmpty(in.Phone), nullIfEmpty(in.Mobile),
		nullIfEmpty(in.NationalID), nullIfEmpty(in.EconomicCode),
		nullIfEmpty(in.Province), nullIfEmpty(in.City), nullIfEmpty(in.PostalCode),
		nullIfEmpty(in.Address), id, u.OrgID)
	if err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	if n, _ := res.RowsAffected(); n == 0 {
		writeErr(w, http.StatusNotFound, "مشتری یافت نشد")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true})
}

// DELETE /crm/api/customers/{id}  — حذفِ نرم.
func (s *server) deleteCustomer(w http.ResponseWriter, r *http.Request) {
	id := idParam(r)
	u := userOf(r.Context())
	res, err := s.db.Exec(
		"UPDATE crm_customers SET is_deleted = 1 WHERE id = ? AND organization_id = ? AND is_deleted = 0", id, u.OrgID)
	if err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	if n, _ := res.RowsAffected(); n == 0 {
		writeErr(w, http.StatusNotFound, "مشتری یافت نشد")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true})
}

// POST /crm/api/customers/import   body: {"rows":[{type,name,phone,mobile,national_id,economic_code,address}, ...]}
func (s *server) importCustomers(w http.ResponseWriter, r *http.Request) {
	var body struct {
		Rows []customerIn `json:"rows"`
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
		INSERT INTO crm_customers
		  (organization_id, type, name, phone, mobile, national_id, economic_code,
		   province, city, postal_code, address, created_by)
		VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`)
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
			errs = append(errs, map[string]any{"row": i + 1, "message": "نام مشتری خالی است"})
			continue
		}
		if _, e := ins.Exec(u.OrgID, row.Type, row.Name, nullIfEmpty(row.Phone), nullIfEmpty(row.Mobile),
			nullIfEmpty(row.NationalID), nullIfEmpty(row.EconomicCode),
			nullIfEmpty(row.Province), nullIfEmpty(row.City), nullIfEmpty(row.PostalCode),
			nullIfEmpty(row.Address), u.ID); e != nil {
			errs = append(errs, map[string]any{"row": i + 1, "message": "درج ناموفق"})
			continue
		}
		inserted++
	}

	if err := tx.Commit(); err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "inserted": inserted, "errors": errs})
}
