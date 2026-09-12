package reports

import (
	"database/sql"
	"fmt"
	"net/http"
	"strings"
	"time"

	"bmp/go-api/internal/core"
)

// taskStatusLabels — پورتِ عیناً‌ همان TASK_STATUS_LABELS در
// includes/task-status-helper.php. هر تغییری آن‌جا باید این‌جا هم اعمال شود.
var taskStatusLabels = map[string]string{
	"not_started":           "شروع نشده",
	"in_progress":           "در حال انجام",
	"pending_approval":      "در انتظار تأیید",
	"completed":             "تکمیل شده",
	"approved":              "تأیید شده",
	"delegated":             "ارجاع شده",
	"rejected":              "متوقف شده(کارهای عادی)",
	"stopped":               "متوقف شده(فرآیندها)",
	"period_done":           "دوره انجام شد",
	"termination_requested": "در انتظار اتمام",
}

// Generate — پورتِ دقیقِ api/reports/generate.php
//
//	POST /go/api/reports/generate   body: {unit, date}
//	→ {"success":true,"content":"...","task_count":N}
//
// هیچ نوشتنی در دیتابیس ندارد — فقط متنِ گزارش را از رویِ کارهای همان روز
// می‌سازد (پیشوند/پسوندِ کاربر + فهرستِ کارها با برچسبِ فارسیِ وضعیت).
func Generate(db *sql.DB) http.HandlerFunc {
	const taskQ = `SELECT title, status, description
	               FROM tasks
	               WHERE assignee_id = ?
	                 AND (activity_section = ? OR activity_section IS NULL)
	                 AND (
	                     (task_type = 'periodic' AND due_date = ?)
	                     OR (task_type = 'continuous' AND start_date <= ?)
	                 )
	               ORDER BY priority DESC, created_at ASC`

	return func(w http.ResponseWriter, r *http.Request) {
		u := core.UserOf(r.Context())

		var body struct {
			Unit string `json:"unit"`
			Date string `json:"date"`
		}
		_ = core.ReadJSON(r, &body)
		if body.Unit == "" || body.Date == "" {
			core.WriteErr(w, http.StatusBadRequest, "واحد و تاریخ الزامی است")
			return
		}

		var prefix, suffix sql.NullString
		_ = db.QueryRow("SELECT report_prefix, report_suffix FROM users WHERE id = ?", u.ID).
			Scan(&prefix, &suffix)

		rows, err := db.Query(taskQ, u.ID, body.Unit, body.Date, body.Date)
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای داخلی سرور")
			return
		}
		defer rows.Close()

		type taskRow struct{ title, status, description string }
		var tasks []taskRow
		for rows.Next() {
			var t taskRow
			var desc sql.NullString
			if err := rows.Scan(&t.title, &t.status, &desc); err != nil {
				core.WriteErr(w, http.StatusInternalServerError, "خطای داخلی سرور")
				return
			}
			t.description = desc.String
			tasks = append(tasks, t)
		}

		var b strings.Builder
		if prefix.Valid && prefix.String != "" {
			b.WriteString(prefix.String)
			b.WriteString("\n\n")
		}
		// معادلِ date('Y/m/d', strtotime($date)) در PHP.
		b.WriteString("گزارش کاری - تاریخ: ")
		b.WriteString(formatSlashDate(body.Date))
		b.WriteString("\n")
		b.WriteString("واحد: ")
		b.WriteString(body.Unit)
		b.WriteString("\n\n")

		if len(tasks) > 0 {
			b.WriteString("فعالیت‌های انجام شده:\n\n")
			for i, t := range tasks {
				label, ok := taskStatusLabels[t.status]
				if !ok {
					label = t.status
				}
				b.WriteString(fmt.Sprintf("%d. %s - وضعیت: %s\n", i+1, t.title, label))
				if t.description != "" {
					b.WriteString("   توضیحات: ")
					b.WriteString(t.description)
					b.WriteString("\n")
				}
			}
		} else {
			b.WriteString("هیچ کاری برای این تاریخ ثبت نشده است.\n")
		}

		b.WriteString("\n")
		if suffix.Valid {
			b.WriteString(suffix.String)
		}

		core.WriteJSON(w, http.StatusOK, map[string]any{
			"success": true, "content": b.String(), "task_count": len(tasks),
		})
	}
}

// formatSlashDate — معادلِ date('Y/m/d', strtotime($date)). اگر ورودی به
// شکلِ YYYY-MM-DD نبود (strtotime شکست می‌خورد)، همان رشتهٔ خام برگردانده
// می‌شود؛ نه ۱۹۷۰/۰۱/۰۱ی که strtotime(false) در PHP تولید می‌کرد.
func formatSlashDate(d string) string {
	t, err := time.Parse("2006-01-02", d)
	if err != nil {
		return d
	}
	return t.Format("2006/01/02")
}
