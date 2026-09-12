package reports

import (
	"database/sql"
	"net/http"

	"bmp/go-api/internal/core"
)

// Search — پورتِ دقیقِ api/reports/search.php
//
//	GET /go/api/reports/search?q=&unit=
//	→ {"success":true,"reports":[{id,unique_code,activity_unit,report_date,
//	   content_preview,created_at,first_name,last_name}],"query":q}
//
// 🔒 خط قرمز (عیناً از نسخهٔ PHP): مجوزِ دیدنِ گزارشِ دیگران فقط برای
// نویسنده‌هایی با activity_section='management' و «در همان سازمانِ کاربرِ
// جستجوکننده» اعمال می‌شود — وگرنه گزارش‌هایِ واحدِ مدیریتِ سازمانِ دیگر هم
// دیده می‌شد.
//
// این کوئری به یک ایندکسِ FULLTEXT روی reports.content نیاز دارد
// (MATCH...AGAINST)؛ اگر آن ایندکس روی جدول نباشد، همین‌جا هم مثلِ نسخهٔ
// PHP با خطای MySQL 1191 شکست می‌خورد — تعمدی، برای پاریتیِ کامل با رفتارِ
// فعلی، نه یک رفتارِ جدید.
func Search(db *sql.DB) http.HandlerFunc {
	const orgQ = "SELECT organization_id FROM users WHERE id = ?"
	const baseQ = `SELECT r.id, r.unique_code, r.activity_unit, r.report_date,
	                      SUBSTRING(r.content, 1, 200) AS content_preview,
	                      r.created_at, u.first_name, u.last_name
	               FROM reports r
	               JOIN users u ON r.user_id = u.id
	               WHERE (r.user_id = ? OR (u.activity_section = 'management' AND u.organization_id = ?))
	                 AND MATCH(r.content) AGAINST(? IN NATURAL LANGUAGE MODE)`
	const unitFilter = " AND r.activity_unit = ?"
	const tail = " ORDER BY r.report_date DESC LIMIT 50"

	return func(w http.ResponseWriter, r *http.Request) {
		u := core.UserOf(r.Context())
		q := r.URL.Query().Get("q")
		unit := r.URL.Query().Get("unit")
		if q == "" {
			core.WriteErr(w, http.StatusBadRequest, "کلمه کلیدی الزامی است")
			return
		}

		var myOrgID sql.NullInt64
		if err := db.QueryRow(orgQ, u.ID).Scan(&myOrgID); err != nil && err != sql.ErrNoRows {
			core.WriteErr(w, http.StatusInternalServerError, "خطای داخلی سرور")
			return
		}

		query := baseQ
		args := []any{u.ID, myOrgID, q}
		if unit != "" {
			query += unitFilter
			args = append(args, unit)
		}
		query += tail

		rows, err := db.Query(query, args...)
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای داخلی سرور")
			return
		}
		defer rows.Close()

		out := []map[string]any{}
		for rows.Next() {
			var id int64
			var code, unitVal, date, created string
			var preview, firstName, lastName sql.NullString
			if err := rows.Scan(&id, &code, &unitVal, &date, &preview, &created, &firstName, &lastName); err != nil {
				core.WriteErr(w, http.StatusInternalServerError, "خطای داخلی سرور")
				return
			}
			out = append(out, map[string]any{
				"id": id, "unique_code": code, "activity_unit": unitVal,
				"report_date": date, "content_preview": nsToAny(preview), "created_at": created,
				"first_name": nsToAny(firstName), "last_name": nsToAny(lastName),
			})
		}

		core.WriteJSON(w, http.StatusOK, map[string]any{
			"success": true, "reports": out, "query": q,
		})
	}
}
