package reports

import (
	"database/sql"
	"net/http"
	"regexp"
	"strings"

	"bmp/go-api/internal/core"
)

// groupedActions — همان ۹ کلیدی که PHP از قبل در $grouped می‌سازد. هر کلید
// حتی وقتی خالی است باید در خروجی باشد (آرایه‌ی تهی)، چون daily-report.php
// روی وجودشان حساب می‌کند.
var groupedActions = []string{
	"created", "assigned", "completed",
	"approved", "rejected", "delegated",
	"updated", "pending_approval", "stopped",
}

var dateRe = regexp.MustCompile(`^\d{4}-\d{2}-\d{2}$`)

// TodayActivities — پورت دقیق api/reports/get-today-activities.php
//
//	GET /go/api/reports/get-today-activities[?date=YYYY-MM-DD]
//	→ {"success":true,"data":{date,user,activities,grouped_activities,summary,overdue_tasks}}
//
// ✅ getUserInfo() در includes/middleware.php قبلا ستون‌های
// manager_code/manager_name/manager_lastname/report_prefix/report_suffix/
// official_code/manager_id/activity_unit را SELECT نمی‌کرد (این فیلدها در
// خروجی userInfo همیشه تهی بودند). این باگ جداگانه و آگاهانه در PHP رفع
// شد؛ این‌جا هم همزمان با هم رفع شده تا با خروجی جدید PHP برابر بماند.
func TodayActivities(db *sql.DB) http.HandlerFunc {
	const qActivities = `
        SELECT
            th.id, th.task_id, th.from_user_id, th.to_user_id,
            th.action, th.notes, th.created_at,
            t.title as task_title, t.description as task_description,
            t.status as task_status, t.priority as task_priority,
            t.due_date as task_due_date, t.task_type, t.activity_section,
            CONCAT(COALESCE(fu.first_name,''),' ',COALESCE(fu.last_name,'')) as from_user_name,
            CONCAT(COALESCE(tu.first_name,''),' ',COALESCE(tu.last_name,'')) as to_user_name
        FROM task_history th
        LEFT JOIN tasks t ON th.task_id = t.id
        LEFT JOIN users fu ON th.from_user_id = fu.id AND fu.is_active = 1 AND fu.is_deleted = 0
        LEFT JOIN users tu ON th.to_user_id = tu.id AND tu.is_active = 1 AND tu.is_deleted = 0
        WHERE DATE(th.created_at) = ? AND th.from_user_id = ?
          AND (t.is_deleted = 0 OR t.is_deleted IS NULL)
        ORDER BY th.created_at DESC
    `

	// 🔒 effective_deadline (با ساعت) — قبلا شاخه‌ی ساعتی فقط با
	// «is_workflow_task && deadline!=""» انتخاب می‌شد، با این فرض که
	// deadline برای کار روتین همیشه پره. این فرض همیشه درست نبود: چندتا
	// کار روتین واقعی پیدا شدن که deadline‌شون خالی بود ولی due_date پر
	// بود (و ماه‌ها گذشته) — parity این فیکس با api/reports/
	// get-today-activities.php و top-delayed-users.php لازمه.
	const qOverdue = `
        SELECT
            t.id, t.title, t.priority, t.task_type, t.is_workflow_task,
            t.due_date, t.deadline, t.original_deadline,
            GREATEST(
                COALESCE(CAST(t.due_date AS DATE), CAST('1000-01-01' AS DATE)),
                COALESCE(CAST(t.deadline AS DATE), CAST('1000-01-01' AS DATE)),
                COALESCE(CAST(t.original_deadline AS DATE), CAST('1000-01-01' AS DATE))
            ) AS effective_due,
            GREATEST(
                COALESCE(CAST(CONCAT(t.due_date, ' 23:59:59') AS DATETIME), CAST('1000-01-01 00:00:00' AS DATETIME)),
                COALESCE(CAST(t.deadline AS DATETIME), CAST('1000-01-01 00:00:00' AS DATETIME)),
                COALESCE(CAST(t.original_deadline AS DATETIME), CAST('1000-01-01 00:00:00' AS DATETIME))
            ) AS effective_deadline,
            CONCAT(COALESCE(c.first_name,''),' ',COALESCE(c.last_name,'')) as creator_name
        FROM tasks t
        LEFT JOIN users c ON t.creator_id = c.id AND c.is_active = 1 AND c.is_deleted = 0
        WHERE t.assignee_id = ? AND t.is_deleted = 0
          AND t.status NOT IN ('completed','approved','stopped','rejected')
          AND (t.due_date IS NOT NULL OR t.deadline IS NOT NULL OR t.original_deadline IS NOT NULL)
        HAVING effective_due > '1000-01-01' AND effective_due < CURDATE()
        ORDER BY effective_due ASC
        LIMIT 15
    `

	return func(w http.ResponseWriter, r *http.Request) {
		u := core.UserOf(r.Context())

		// معادل getUserInfo() — با همان قید is_active = 1، پس کاربر
		// غیرفعال ۴۰۱ می‌گیرد.
		var (
			uid             int64
			orgID           sql.NullInt64
			firstName       sql.NullString
			lastName        sql.NullString
			activityUnit    sql.NullString
			officialCode    sql.NullString
			managerID       sql.NullInt64
			managerCode     sql.NullString
			managerName     sql.NullString
			managerLastname sql.NullString
			reportPrefix    sql.NullString
			reportSuffix    sql.NullString
		)
		err := db.QueryRow(`
			SELECT id, organization_id, first_name, last_name,
			       activity_unit, official_code,
			       manager_id, manager_code, manager_name, manager_lastname,
			       report_prefix, report_suffix
			FROM users WHERE id = ? AND is_active = 1
		`, u.ID).Scan(
			&uid, &orgID, &firstName, &lastName,
			&activityUnit, &officialCode,
			&managerID, &managerCode, &managerName, &managerLastname,
			&reportPrefix, &reportSuffix,
		)
		if err == sql.ErrNoRows {
			core.WriteErr(w, http.StatusUnauthorized, "کاربر یافت نشد")
			return
		}
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}

		now := core.TehranNow()
		date := r.URL.Query().Get("date")
		if date == "" {
			date = now.Format("2006-01-02")
		}
		if !dateRe.MatchString(date) {
			core.WriteErr(w, http.StatusBadRequest, "فرمت تاریخ نامعتبر")
			return
		}

		// ══ ۱) فعالیت‌های آن روز ══
		rows, err := db.Query(qActivities, date, u.ID)
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}
		activities, err := core.ScanRowsToMaps(rows)
		rows.Close()
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}

		grouped := make(map[string][]map[string]any, len(groupedActions))
		for _, k := range groupedActions {
			grouped[k] = []map[string]any{}
		}
		for _, a := range activities {
			action := mStr(a, "action")
			if _, ok := grouped[action]; ok {
				grouped[action] = append(grouped[action], a)
			}
		}

		// ══ ۲) کارهای معوقه ══
		rows, err = db.Query(qOverdue, u.ID)
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}
		overdue, err := core.ScanRowsToMaps(rows)
		rows.Close()
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}

		// getHolidaySet($db, $user['organization_id'] ?? null) — برخلاف
		// top-delayed-users این‌جا شناسه‌ی سازمان پاس داده می‌شود.
		var orgPtr *int64
		if orgID.Valid {
			v := orgID.Int64
			orgPtr = &v
		}
		holidays, err := core.HolidaySet(db, orgPtr)
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}

		todayStr := now.Format("2006-01-02")
		nowStr := now.Format("2006-01-02 15:04:05")

		for _, ot := range overdue {
			// PHP (فیکس‌شده): !empty($ot['is_workflow_task']) — دیگه شرط
			// deadline!="" نداره؛ effective_deadline (GREATEST) جایگزین
			// خود deadline در calcHourDelay شده.
			if mInt(ot, "is_workflow_task") != 0 {
				effDeadline := mStr(ot, "effective_deadline")
				ot["unit"] = "hours"
				ot["hours_overdue"] = core.CalcHourDelay(effDeadline, nowStr)
				ot["days_overdue"] = nil
			} else {
				due := mStr(ot, "effective_due")
				if len(due) > 10 {
					due = due[:10]
				}
				ot["unit"] = "days"
				ot["days_overdue"] = core.CalcPeriodicDelayWorkingDays(due, todayStr, holidays)
				ot["hours_overdue"] = nil
			}
		}

		// ══ ۳) آمار ══
		summary := map[string]any{
			"total_activities":  len(activities),
			"tasks_created":     len(grouped["created"]),
			"tasks_completed":   len(grouped["completed"]),
			"tasks_approved":    len(grouped["approved"]),
			"tasks_rejected":    len(grouped["rejected"]),
			"tasks_delegated":   len(grouped["delegated"]),
			"tasks_assigned":    len(grouped["assigned"]),
			"notes_added":       len(grouped["updated"]),
			"sent_for_approval": len(grouped["pending_approval"]),
			"tasks_stopped":     len(grouped["stopped"]),
			"overdue_count":     len(overdue),
		}

		// ══ ۴) اطلاعات کاربر ══
		// PHP: `$user['x'] ?? ''` برای فیلدهای رشته‌ای (NULL دیتابیس →
		// رشته‌ی خالی، نه JSON null)، ولی `$user['manager_id'] ?? null`
		// برای manager_id (NULL دیتابیس → JSON null، نه ۰).
		var managerIDOut any
		if managerID.Valid {
			managerIDOut = managerID.Int64
		}
		userInfo := map[string]any{
			"id":               uid,
			"first_name":       firstName.String,
			"last_name":        lastName.String,
			"full_name":        strings.TrimSpace(firstName.String + " " + lastName.String),
			"activity_unit":    activityUnit.String,
			"official_code":    officialCode.String,
			"manager_id":       managerIDOut,
			"manager_code":     managerCode.String,
			"manager_name":     managerName.String,
			"manager_lastname": managerLastname.String,
			"report_prefix":    reportPrefix.String,
			"report_suffix":    reportSuffix.String,
		}

		core.WriteJSON(w, http.StatusOK, map[string]any{
			"success": true,
			"data": map[string]any{
				"date":               date,
				"user":               userInfo,
				"activities":         activities,
				"grouped_activities": grouped,
				"summary":            summary,
				"overdue_tasks":      overdue,
			},
		})
	}
}
