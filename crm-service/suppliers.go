package main

import (
	"net/http"
	"strings"
)

// تأمین‌کننده‌ها — جدولِ inv_suppliers. ساختار مثلِ مشتری ولی بدونِ نوع، با یادداشت.

type supplierOut struct {
	ID           int64  `json:"id"`
	Name         string `json:"name"`
	Phone        string `json:"phone"`
	Mobile       string `json:"mobile"`
	NationalID   string `json:"national_id"`
	EconomicCode string `json:"economic_code"`
	Address      string `json:"address"`
	Note         string `json:"note"`
}

type supplierIn struct {
	Name         string `json:"name"`
	Phone        string `json:"phone"`
	Mobile       string `json:"mobile"`
	NationalID   string `json:"national_id"`
	EconomicCode string `json:"economic_code"`
	Address      string `json:"address"`
	Note         string `json:"note"`
}

func (c *supplierIn) normalize() {
	c.Name = strings.TrimSpace(c.Name)
	c.Phone = strings.TrimSpace(c.Phone)
	c.Mobile = strings.TrimSpace(c.Mobile)
	c.NationalID = strings.TrimSpace(c.NationalID)
	c.EconomicCode = strings.TrimSpace(c.EconomicCode)
	c.Address = strings.TrimSpace(c.Address)
	c.Note = strings.TrimSpace(c.Note)
}

// GET /crm/api/inv/suppliers?q=&page=&per=
func (s *server) listSuppliers(w http.ResponseWriter, r *http.Request) {
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
	if err := s.db.QueryRow("SELECT COUNT(*) FROM inv_suppliers WHERE "+where, args...).Scan(&total); err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}

	rows, err := s.db.Query(`
		SELECT id, name,
		       COALESCE(phone,''), COALESCE(mobile,''), COALESCE(national_id,''),
		       COALESCE(economic_code,''), COALESCE(address,''), COALESCE(note,'')
		FROM inv_suppliers
		WHERE `+where+`
		ORDER BY name
		LIMIT ? OFFSET ?`, append(args, pg.Limit, pg.Offset)...)
	if err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	defer rows.Close()

	items := []supplierOut{}
	for rows.Next() {
		var it supplierOut
		if err := rows.Scan(&it.ID, &it.Name, &it.Phone, &it.Mobile, &it.NationalID,
			&it.EconomicCode, &it.Address, &it.Note); err != nil {
			writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
			return
		}
		items = append(items, it)
	}
	writeJSON(w, http.StatusOK, map[string]any{"items": items, "total": total, "page": pg.Page, "per": pg.Per})
}

// POST /crm/api/inv/suppliers
func (s *server) createSupplier(w http.ResponseWriter, r *http.Request) {
	var in supplierIn
	if !decodeJSON(w, r, &in, 1<<20) {
		return
	}
	in.normalize()
	if in.Name == "" {
		writeErr(w, http.StatusBadRequest, "نام تأمین‌کننده الزامی است")
		return
	}
	u := userOf(r.Context())

	res, err := s.db.Exec(`
		INSERT INTO inv_suppliers
		  (organization_id, name, phone, mobile, national_id, economic_code, address, note, created_by)
		VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)`,
		u.OrgID, in.Name, nullIfEmpty(in.Phone), nullIfEmpty(in.Mobile), nullIfEmpty(in.NationalID),
		nullIfEmpty(in.EconomicCode), nullIfEmpty(in.Address), nullIfEmpty(in.Note), u.ID)
	if err != nil {
		writeErr(w, http.StatusInternalServerError, "درجِ تأمین‌کننده ناموفق بود")
		return
	}
	id, _ := res.LastInsertId()
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "id": id})
}

// PUT /crm/api/inv/suppliers/{id}
func (s *server) updateSupplier(w http.ResponseWriter, r *http.Request) {
	id := idParam(r)
	var in supplierIn
	if !decodeJSON(w, r, &in, 1<<20) {
		return
	}
	in.normalize()
	if in.Name == "" {
		writeErr(w, http.StatusBadRequest, "نام تأمین‌کننده الزامی است")
		return
	}
	u := userOf(r.Context())

	res, err := s.db.Exec(`
		UPDATE inv_suppliers
		SET name = ?, phone = ?, mobile = ?, national_id = ?, economic_code = ?, address = ?, note = ?
		WHERE id = ? AND organization_id = ? AND is_deleted = 0`,
		in.Name, nullIfEmpty(in.Phone), nullIfEmpty(in.Mobile), nullIfEmpty(in.NationalID),
		nullIfEmpty(in.EconomicCode), nullIfEmpty(in.Address), nullIfEmpty(in.Note), id, u.OrgID)
	if err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	if n, _ := res.RowsAffected(); n == 0 {
		writeErr(w, http.StatusNotFound, "تأمین‌کننده یافت نشد")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true})
}

// DELETE /crm/api/inv/suppliers/{id}  — حذفِ نرم.
func (s *server) deleteSupplier(w http.ResponseWriter, r *http.Request) {
	id := idParam(r)
	u := userOf(r.Context())
	res, err := s.db.Exec(
		"UPDATE inv_suppliers SET is_deleted = 1 WHERE id = ? AND organization_id = ? AND is_deleted = 0", id, u.OrgID)
	if err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	if n, _ := res.RowsAffected(); n == 0 {
		writeErr(w, http.StatusNotFound, "تأمین‌کننده یافت نشد")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true})
}
