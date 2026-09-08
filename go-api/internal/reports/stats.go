// Package reports — پورتِ endpointهای api/reports/* به Go.
package reports

import (
	"database/sql"
	"net/http"

	"bmp/go-api/internal/core"
)

// Stats — پورتِ دقیقِ api/reports/stats.php
//
//	GET /go/api/reports/stats
//	→ {"success":true,"stats":{"total":N,"today":N,"week":N,"month":N}}
//
// همان چهار COUNT، همان شرط‌های تاریخِ MySQL (CURDATE / YEARWEEK / MONTH+YEAR)،
// همان دامنه (فقط reportهای خودِ کاربرِ احرازشده).
func Stats(db *sql.DB) http.HandlerFunc {
	const (
		qTotal = "SELECT COUNT(*) FROM reports WHERE user_id = ?"
		qToday = "SELECT COUNT(*) FROM reports WHERE user_id = ? AND DATE(report_date) = CURDATE()"
		qWeek  = "SELECT COUNT(*) FROM reports WHERE user_id = ? AND YEARWEEK(report_date) = YEARWEEK(NOW())"
		qMonth = "SELECT COUNT(*) FROM reports WHERE user_id = ? AND MONTH(report_date) = MONTH(NOW()) AND YEAR(report_date) = YEAR(NOW())"
	)

	return func(w http.ResponseWriter, r *http.Request) {
		u := core.UserOf(r.Context())

		count := func(q string) (int64, error) {
			var n int64
			err := db.QueryRow(q, u.ID).Scan(&n)
			return n, err
		}

		total, e1 := count(qTotal)
		today, e2 := count(qToday)
		week, e3 := count(qWeek)
		month, e4 := count(qMonth)
		if e1 != nil || e2 != nil || e3 != nil || e4 != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای داخلی سرور")
			return
		}

		core.WriteJSON(w, http.StatusOK, map[string]any{
			"success": true,
			"stats": map[string]int64{
				"total": total,
				"today": today,
				"week":  week,
				"month": month,
			},
		})
	}
}
