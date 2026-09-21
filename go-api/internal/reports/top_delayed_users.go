package reports

import (
	"database/sql"
	"net/http"
	"sort"
	"strings"

	"bmp/go-api/internal/core"
)

// delayBucket — یک سطرِ انباشتگرِ $acc در PHP.
// RefID عمداً `any` است: برایِ کاربر عددِ شناسه و برایِ واحد نامِ رشته‌ایِ
// واحد است — دقیقاً مثلِ PHP.
type delayBucket struct {
	Kind       string `json:"kind"`
	RefID      any    `json:"ref_id"`
	Name       string `json:"name"`
	Periodic   int    `json:"periodic"`
	Continuous int    `json:"continuous"`
	Workflow   int    `json:"workflow"`
	DelayDays  int    `json:"delay_days"`
	DelayHours int    `json:"delay_hours"`
	Total      int    `json:"total"`
}

// TopDelayedUsers — پورتِ دقیقِ api/reports/top-delayed-users.php
//
//	GET /go/api/reports/top-delayed-users
//	→ {"success":true,"users":[...]}
//
// هر سه نوعِ کارِ تأخیردار (مقطعی / دوره‌ای / روتین) شمرده می‌شود و تأخیر
// یا پایِ کاربرِ مسئول یا — اگر مسئولی نباشد — پایِ واحدِ سازمانی نوشته
// می‌شود. کاربرِ غیرفعال (نه حذف‌شده) با برچسبِ «(غیرفعال)» می‌آید.
func TopDelayedUsers(db *sql.DB) http.HandlerFunc {
	const qPeriodic = `
        SELECT id, assignee_id, activity_section,
            GREATEST(
                COALESCE(CAST(due_date AS DATE), CAST('1000-01-01' AS DATE)),
                COALESCE(CAST(deadline AS DATE), CAST('1000-01-01' AS DATE)),
                COALESCE(CAST(original_deadline AS DATE), CAST('1000-01-01' AS DATE))
            ) AS effective_due
        FROM tasks
        WHERE organization_id = ?
          AND is_deleted = 0
          AND task_type = 'periodic'
          AND status NOT IN ('completed', 'approved', 'stopped', 'rejected')
        HAVING effective_due > '1000-01-01' AND effective_due < ?
    `

	const qContinuous = `
        SELECT * FROM tasks
        WHERE organization_id = ?
          AND is_deleted = 0
          AND task_type = 'continuous'
          AND status NOT IN ('completed', 'approved', 'stopped', 'rejected')
    `

	const qWorkflow = `
        SELECT t.id, t.assignee_id, t.activity_section, t.deadline
        FROM tasks t
        JOIN workflow_instance_steps wis ON wis.task_id = t.id
        WHERE t.organization_id = ?
          AND t.is_deleted = 0
          AND t.is_workflow_task = 1
          AND wis.status IN ('active', 'pending', 'delayed')
          AND t.deadline IS NOT NULL
          AND t.deadline < NOW()
          AND t.status NOT IN ('completed', 'approved', 'stopped', 'rejected')
    `

	return func(w http.ResponseWriter, r *http.Request) {
		u := core.UserOf(r.Context())

		me, err := core.LoadUser(db, u.ID)
		if err != nil || me == nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}
		if !core.HasPermission(me, "view_all_org_tasks") && !core.HasPermission(me, "view_org_dashboard_reports") {
			core.WriteErr(w, http.StatusForbidden, "دسترسی غیرمجاز")
			return
		}

		orgID := me.OrganizationID
		if orgID <= 0 {
			// PHP این شاخه را با HTTP 200 و success=false برمی‌گرداند.
			core.WriteJSON(w, http.StatusOK, map[string]any{
				"success": false,
				"message": "سازمان نامعتبر",
			})
			return
		}

		now := core.TehranNow()
		today := now.Format("2006-01-02")
		nowStr := now.Format("2006-01-02 15:04:05")

		holidays, err := core.HolidaySet(db, nil)
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}

		// ── نگاشتِ شناسه‌ی کاربر → نام و وضعیتِ فعال‌بودن ──
		userNames := map[int64]string{}
		userActive := map[int64]bool{}
		{
			rows, err := db.Query(`
				SELECT id, first_name, last_name, is_active
				FROM users
				WHERE organization_id = ? AND is_deleted = 0
			`, orgID)
			if err != nil {
				core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
				return
			}
			list, err := core.ScanRowsToMaps(rows)
			rows.Close()
			if err != nil {
				core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
				return
			}
			for _, ur := range list {
				uid := mInt(ur, "id")
				userNames[uid] = strings.TrimSpace(mStr(ur, "first_name") + " " + mStr(ur, "last_name"))
				userActive[uid] = mInt(ur, "is_active") != 0
			}
		}

		// انباشتگر — ترتیبِ درج باید حفظ شود (PHP آرایه‌ی انجمنی را به
		// ترتیبِ درج نگه می‌دارد و مرتب‌سازیِ نهایی در تساوی همین را
		// حفظ می‌کند).
		order := []string{}
		acc := map[string]*delayBucket{}

		// bucket — معادلِ کلوژرِ $bucket در PHP.
		bucket := func(assigneeID any, section any) *delayBucket {
			// PHP: `!empty($assignee_id) && isset($userNames[(int)$assignee_id])`
			// — empty() هم NULL و هم 0/"0" را رد می‌کند.
			if aid := core.ToInt64(assigneeID); aid != 0 {
				if name, ok := userNames[aid]; ok {
					key := "user:" + fmtAny(aid)
					if b, exists := acc[key]; exists {
						return b
					}
					label := name
					if !userActive[aid] {
						label += " (غیرفعال)"
					}
					b := &delayBucket{Kind: "user", RefID: aid, Name: label}
					acc[key] = b
					order = append(order, key)
					return b
				}
			}
			// واحد — PHP: `$section ?: 'نامشخص'` (هم NULL و هم رشته‌ی خالی)
			sec := fmtAny(section)
			if sec == "" {
				sec = "نامشخص"
			}
			key := "section:" + sec
			if b, exists := acc[key]; exists {
				return b
			}
			b := &delayBucket{Kind: "section", RefID: sec, Name: sec}
			acc[key] = b
			order = append(order, key)
			return b
		}

		// ══ ۱) کارهای مقطعیِ تأخیردار ══
		{
			rows, err := db.Query(qPeriodic, orgID, today)
			if err != nil {
				core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
				return
			}
			list, err := core.ScanRowsToMaps(rows)
			rows.Close()
			if err != nil {
				core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
				return
			}
			for _, t := range list {
				b := bucket(t["assignee_id"], t["activity_section"])
				b.Periodic++
				due := mStr(t, "effective_due")
				if len(due) > 10 {
					due = due[:10]
				}
				b.DelayDays += core.CalcPeriodicDelayWorkingDays(due, today, holidays)
			}
		}

		// ══ ۲) کارهای دوره‌ایِ تأخیردار (از موتورِ مشترک) ══
		{
			rows, err := db.Query(qContinuous, orgID)
			if err != nil {
				core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
				return
			}
			list, err := core.ScanRowsToMaps(rows)
			rows.Close()
			if err != nil {
				core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
				return
			}
			for _, t := range list {
				state := core.PeStateCalc(db, core.PeStateTask{
					ID:                    mInt(t, "id"),
					TaskType:              mStr(t, "task_type"),
					StartDate:             mStr(t, "start_date"),
					EndDate:               mStr(t, "end_date"),
					PeriodType:            mStr(t, "period_type"),
					OverdueForgivenCredit: int(mInt(t, "overdue_forgiven_credit")),
				}, holidays, today, nil)

				if state.OverduePeriods > 0 {
					b := bucket(t["assignee_id"], t["activity_section"])
					b.Continuous++
					b.DelayDays += state.WorkingDaysDelayed
				}
			}
		}

		// ══ ۳) کارهای روتینِ تأخیردار — ساعتی، نه روزِ کاری ══
		{
			rows, err := db.Query(qWorkflow, orgID)
			if err != nil {
				core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
				return
			}
			list, err := core.ScanRowsToMaps(rows)
			rows.Close()
			if err != nil {
				core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
				return
			}
			for _, t := range list {
				b := bucket(t["assignee_id"], t["activity_section"])
				b.Workflow++
				b.DelayHours += core.CalcHourDelay(mStr(t, "deadline"), nowStr)
			}
		}

		// ── خروجی ──
		result := []*delayBucket{}
		for _, key := range order {
			b := acc[key]
			if b.DelayDays <= 0 && b.DelayHours <= 0 {
				continue
			}
			b.Total = b.DelayDays // سازگاریِ عقب‌رو با مصرف‌کننده‌هایِ قدیمی
			result = append(result, b)
		}

		sort.SliceStable(result, func(i, j int) bool {
			if result[i].DelayDays != result[j].DelayDays {
				return result[i].DelayDays > result[j].DelayDays
			}
			return result[i].DelayHours > result[j].DelayHours
		})

		core.WriteJSON(w, http.StatusOK, map[string]any{
			"success": true,
			"users":   result,
		})
	}
}
