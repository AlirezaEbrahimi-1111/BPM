package reports

import (
	"database/sql"
	"net/http"

	"bmp/go-api/internal/core"
)

// Detail — پورتِ دقیقِ api/reports/detail.php
//
//	GET /go/api/reports/detail?id=  یا  ?code=&lt;unique_code&gt;
//	→ {"success":true,"report":{id,unique_code,user_id,activity_unit,report_date,content,created_at,unit_name}}
//
// فقط گزارشِ خودِ کاربرِ احرازشده (WHERE ... AND user_id = ?) — بدونِ استثنا.
func Detail(db *sql.DB) http.HandlerFunc {
	const byID = `SELECT id, unique_code, user_id, activity_unit, report_date, content, created_at
	              FROM reports WHERE id = ? AND user_id = ?`
	const byCode = `SELECT id, unique_code, user_id, activity_unit, report_date, content, created_at
	                FROM reports WHERE unique_code = ? AND user_id = ?`

	return func(w http.ResponseWriter, r *http.Request) {
		u := core.UserOf(r.Context())
		id := r.URL.Query().Get("id")
		code := r.URL.Query().Get("code")
		if id == "" && code == "" {
			core.WriteErr(w, http.StatusBadRequest, "شناسه یا کد گزارش الزامی است")
			return
		}

		var row *sql.Row
		if id != "" {
			row = db.QueryRow(byID, id, u.ID)
		} else {
			row = db.QueryRow(byCode, code, u.ID)
		}

		var (
			rid, uid                        int64
			uniqueCode, unit, date, created string
			content                         string
		)
		err := row.Scan(&rid, &uniqueCode, &uid, &unit, &date, &content, &created)
		if err == sql.ErrNoRows {
			core.WriteErr(w, http.StatusNotFound, "گزارش یافت نشد")
			return
		}
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای داخلی سرور")
			return
		}

		name := unitNames[unit]
		if name == "" {
			name = unit
		}

		core.WriteJSON(w, http.StatusOK, map[string]any{
			"success": true,
			"report": map[string]any{
				"id":            rid,
				"unique_code":   uniqueCode,
				"user_id":       uid,
				"activity_unit": unit,
				"report_date":   date,
				"content":       content,
				"created_at":    created,
				"unit_name":     name,
			},
		})
	}
}
