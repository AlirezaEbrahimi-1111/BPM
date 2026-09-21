package reports

import (
	"database/sql"
	"fmt"
	"math/rand"
	"net/http"
	"strings"

	"bmp/go-api/internal/core"
)

// ─── Submit — پورتِ دقیقِ api/reports/submit.php ──────────────────────
//
//	POST /go/api/reports/submit   body: {activity_unit, content, report_date?}
//	→ 201 {"success":true,"message":"...","report_code":"...","report_id":N}
func Submit(db *sql.DB) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		u := core.UserOf(r.Context())

		var body struct {
			ActivityUnit string `json:"activity_unit"`
			Content      string `json:"content"`
			ReportDate   string `json:"report_date"`
		}
		_ = core.ReadJSON(r, &body)

		if body.ActivityUnit == "" {
			core.WriteErr(w, http.StatusBadRequest, "واحد فعالیت الزامی است")
			return
		}
		content := strings.TrimSpace(body.Content)
		// len() روی رشتهٔ Go طولِ بایت است — دقیقاً معادلِ strlen() در PHP
		// (نه طولِ کاراکتر/رون)، پس آستانهٔ ۵۰ برای متنِ فارسی هم یکسان می‌ماند.
		if content == "" || len(content) < 50 {
			core.WriteErr(w, http.StatusBadRequest, "محتوای گزارش باید حداقل 50 کاراکتر باشد")
			return
		}
		reportDate := body.ReportDate
		if reportDate == "" {
			reportDate = core.TehranNow().Format("2006-01-02")
		}

		var unitMatch int
		err := db.QueryRow(`
			SELECT 1 FROM user_activity_units WHERE user_id = ? AND activity_unit = ?
			UNION
			SELECT 1 FROM users WHERE id = ? AND activity_unit = ?
			LIMIT 1
		`, u.ID, body.ActivityUnit, u.ID, body.ActivityUnit).Scan(&unitMatch)
		if err == sql.ErrNoRows {
			core.WriteErr(w, http.StatusForbidden, "شما در این واحد فعالیت ندارید")
			return
		}
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای داخلی سرور")
			return
		}

		var existingID int64
		var existingCode string
		err = db.QueryRow(
			"SELECT id, unique_code FROM reports WHERE user_id = ? AND activity_unit = ? AND report_date = ?",
			u.ID, body.ActivityUnit, reportDate,
		).Scan(&existingID, &existingCode)
		if err == nil {
			core.WriteJSON(w, http.StatusConflict, map[string]any{
				"success": false, "message": "شما قبلا برای امروز و این واحد گزارش ارسال کرده‌اید",
				"existing_code": existingCode,
			})
			return
		}
		if err != sql.ErrNoRows {
			core.WriteErr(w, http.StatusInternalServerError, "خطای داخلی سرور")
			return
		}

		code, err := generateUniqueReportCode(db)
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای داخلی سرور")
			return
		}

		res, err := db.Exec(
			"INSERT INTO reports (unique_code, user_id, activity_unit, report_date, content, created_at) VALUES (?, ?, ?, ?, ?, NOW())",
			code, u.ID, body.ActivityUnit, reportDate, content,
		)
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطا در ذخیره گزارش")
			return
		}
		reportID, _ := res.LastInsertId()

		core.WriteJSON(w, http.StatusCreated, map[string]any{
			"success": true, "message": "گزارش با موفقیت ارسال شد",
			"report_code": code, "report_id": reportID,
		})
	}
}

// generateUniqueReportCode — پورتِ generateUniqueReportCode() در submit.php
// (فرمتِ RPT + سالِ دو رقمی + ماه + روز (به‌وقتِ تهران) + ۴ رقمِ تصادفی،
// حداکثر ۱۰ تلاش، سپس افتادن به کدِ مبتنی‌بر timestamp).
func generateUniqueReportCode(db *sql.DB) (string, error) {
	now := core.TehranNow()
	for attempt := 0; attempt < 10; attempt++ {
		code := fmt.Sprintf("RPT%s%04d", now.Format("060102"), rand.Intn(9999)+1)
		var id int64
		err := db.QueryRow("SELECT id FROM reports WHERE unique_code = ?", code).Scan(&id)
		if err == sql.ErrNoRows {
			return code, nil
		}
		if err != nil {
			return "", err
		}
	}
	return "RPT" + now.Format("060102150405"), nil
}
