package reports

import (
	"database/sql"
	"fmt"
	"net/http"
	"strconv"

	"bmp/go-api/internal/core"
)

// نامِ واحدها — عیناً از api/reports/list.php و detail.php.
var unitNames = map[string]string{
	"RS":  "کامپیوتر",
	"ATM": "فضای مجازی + رسانه",
	"AM":  "نوجوانان",
	"AC":  "حسابداری",
	"PR":  "روابط عمومی",
	"HE":  "تربیتی",
}

func qint(r *http.Request, key string, def int) int {
	if v := r.URL.Query().Get(key); v != "" {
		if n, err := strconv.Atoi(v); err == nil {
			return n
		}
	}
	return def
}

// Today — پورتِ api/reports/today.php
//
//	GET /go/api/reports/today
//	→ {"success":true,"reports":[{id,unique_code,activity_unit,report_date,created_at,content}],"count":N}
func Today(db *sql.DB) http.HandlerFunc {
	const q = `SELECT id, unique_code, activity_unit, report_date, created_at, content
	           FROM reports
	           WHERE user_id = ? AND report_date = CURDATE()
	           ORDER BY created_at DESC`
	return func(w http.ResponseWriter, r *http.Request) {
		u := core.UserOf(r.Context())
		rows, err := db.Query(q, u.ID)
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}
		defer rows.Close()

		out := []map[string]any{}
		for rows.Next() {
			var id int64
			var code, unit, date, created, content string
			if err := rows.Scan(&id, &code, &unit, &date, &created, &content); err != nil {
				core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
				return
			}
			out = append(out, map[string]any{
				"id": id, "unique_code": code, "activity_unit": unit,
				"report_date": date, "created_at": created, "content": content,
			})
		}
		core.WriteJSON(w, http.StatusOK, map[string]any{
			"success": true, "reports": out, "count": len(out),
		})
	}
}

// History — پورتِ api/reports/history.php
//
//	GET /go/api/reports/history?limit=N   (limit: 1..100, پیش‌فرض 20)
//	→ {"success":true,"reports":[{id,unique_code,activity_unit,report_date,content_preview,created_at}]}
func History(db *sql.DB) http.HandlerFunc {
	const q = `SELECT id, unique_code, activity_unit, report_date,
	                  SUBSTRING(content, 1, 100) AS content_preview, created_at
	           FROM reports
	           WHERE user_id = ?
	           ORDER BY report_date DESC, created_at DESC
	           LIMIT ?`
	return func(w http.ResponseWriter, r *http.Request) {
		u := core.UserOf(r.Context())
		limit := qint(r, "limit", 20)
		if limit < 1 {
			limit = 1
		}
		if limit > 100 {
			limit = 100
		}
		rows, err := db.Query(q, u.ID, limit)
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای داخلی سرور")
			return
		}
		defer rows.Close()

		out := []map[string]any{}
		for rows.Next() {
			var id int64
			var code, unit, date, created string
			var preview sql.NullString
			if err := rows.Scan(&id, &code, &unit, &date, &preview, &created); err != nil {
				core.WriteErr(w, http.StatusInternalServerError, "خطای داخلی سرور")
				return
			}
			out = append(out, map[string]any{
				"id": id, "unique_code": code, "activity_unit": unit,
				"report_date": date, "content_preview": nsToAny(preview), "created_at": created,
			})
		}
		core.WriteJSON(w, http.StatusOK, map[string]any{"success": true, "reports": out})
	}
}

// List — پورتِ api/reports/list.php
//
//	GET /go/api/reports/list?limit=&offset=&unit=&search=
//	→ {"success":true,"reports":[{id,unique_code,activity_unit,report_date,created_at,content_preview,unit_name}],"total":N}
func List(db *sql.DB) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		u := core.UserOf(r.Context())
		limit := qint(r, "limit", 50)
		offset := qint(r, "offset", 0)
		unit := r.URL.Query().Get("unit")
		search := r.URL.Query().Get("search")

		where := "r.user_id = ?"
		args := []any{u.ID}
		if unit != "" {
			where += " AND r.activity_unit = ?"
			args = append(args, unit)
		}
		if search != "" {
			where += " AND (r.content LIKE ? OR r.unique_code LIKE ?)"
			like := "%" + search + "%"
			args = append(args, like, like)
		}

		// LIMIT/OFFSET مثلِ نسخهٔ PHP مستقیم درج می‌شوند (هر دو int، بدونِ تزریق).
		q := fmt.Sprintf(`SELECT r.id, r.unique_code, r.activity_unit, r.report_date, r.created_at,
		                         SUBSTRING(r.content, 1, 4096) AS content_preview
		                  FROM reports r
		                  WHERE %s
		                  ORDER BY r.report_date DESC, r.created_at DESC
		                  LIMIT %d OFFSET %d`, where, limit, offset)

		rows, err := db.Query(q, args...)
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}
		defer rows.Close()

		out := []map[string]any{}
		for rows.Next() {
			var id int64
			var code, unitVal, date, created string
			var preview sql.NullString
			if err := rows.Scan(&id, &code, &unitVal, &date, &created, &preview); err != nil {
				core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
				return
			}
			name := unitNames[unitVal]
			if name == "" {
				name = unitVal
			}
			// نسخهٔ PHP: اگر پیش‌نمایش ناخالی بود، '...' می‌چسباند.
			var previewOut any
			if preview.Valid {
				p := preview.String
				if p != "" {
					p += "..."
				}
				previewOut = p
			} else {
				previewOut = nil
			}
			out = append(out, map[string]any{
				"id": id, "unique_code": code, "activity_unit": unitVal,
				"report_date": date, "created_at": created,
				"content_preview": previewOut, "unit_name": name,
			})
		}
		core.WriteJSON(w, http.StatusOK, map[string]any{
			"success": true, "reports": out, "total": len(out),
		})
	}
}

func nsToAny(n sql.NullString) any {
	if n.Valid {
		return n.String
	}
	return nil
}
