// Package tasks — پورتِ api/tasks/my-tasks.php (فقط این یکی از ۴
// endpointِ لیستِ کارها؛ all-tasks.php/overview.php/delegated-tasks.php
// عمداً پورت نشدن، طبقِ تصمیمِ صریح، چون هرکدوم قاعده‌ی دسترسیِ مستقل و
// ناهماهنگِ خودشون رو دارن).
package tasks

import (
	"database/sql"
	"time"

	"bmp/go-api/internal/core"
)

func parseDate(s string) (time.Time, error) {
	if len(s) > 10 {
		s = s[:10]
	}
	return time.Parse("2006-01-02", s)
}

func strOf(m map[string]any, key string) string {
	if v, ok := m[key]; ok && v != nil {
		if s, ok := v.(string); ok {
			return s
		}
	}
	return ""
}

func int64Of(m map[string]any, key string) int64 {
	v, ok := m[key]
	if !ok || v == nil {
		return 0
	}
	switch n := v.(type) {
	case int64:
		return n
	case float64:
		return int64(n)
	}
	return 0
}

func boolOf(m map[string]any, key string) bool {
	return int64Of(m, key) != 0
}

// EnrichTaskDates — پورتِ دقیقِ enrichTaskDates() در
// includes/task-dates-helper.php. تسک را (به‌عنوانِ map، هم‌ارزِ آرایه‌ی
// انجمنیِ PHP) با فیلدهایِ محاسبه‌شده پر می‌کند.
func EnrichTaskDates(task map[string]any, db *sql.DB, holidays map[string]bool, today string, preloadedCompletionMap map[int64][]string) {
	task["overdue_periods"] = int64(0)
	task["next_due_date"] = nil
	task["days_remaining"] = nil
	task["working_days_delayed"] = int64(0)
	task["hours_delayed"] = int64(0)
	task["hours_remaining"] = nil

	taskType := strOf(task, "task_type")

	if taskType == "continuous" {
		st := core.PeStateTask{
			ID:                    int64Of(task, "id"),
			TaskType:              taskType,
			StartDate:             strOf(task, "start_date"),
			EndDate:               strOf(task, "end_date"),
			PeriodType:            strOf(task, "period_type"),
			OverdueForgivenCredit: int(int64Of(task, "overdue_forgiven_credit")),
		}
		s := core.PeStateCalc(db, st, holidays, today, preloadedCompletionMap)

		task["overdue_periods"] = int64(s.OverduePeriods)
		if s.NextDueDate != nil {
			task["next_due_date"] = *s.NextDueDate
		}
		if s.DaysRemaining != nil {
			task["days_remaining"] = int64(*s.DaysRemaining)
		}
		task["working_days_delayed"] = int64(s.WorkingDaysDelayed)
		task["is_today_done"] = s.IsTodayDone
		task["can_complete"] = s.CanComplete
		if s.CurrentPeriodDate != nil {
			task["current_period_date"] = *s.CurrentPeriodDate
		} else {
			task["current_period_date"] = nil
		}

		endDate := strOf(task, "end_date")
		status := strOf(task, "status")
		task["needs_renewal_decision"] = endDate != "" &&
			endDate[:min(10, len(endDate))] <= today &&
			int64Of(task, "is_pending_approval") != 1 &&
			int64Of(task, "has_pending_renewal_request") != 1 &&
			status != "completed" && status != "approved" && status != "rejected"

		return
	}

	if taskType == "periodic" {
		var maxDate string
		for _, f := range []string{"due_date", "deadline", "original_deadline"} {
			v := strOf(task, f)
			if v == "" {
				continue
			}
			d := v
			if len(d) > 10 {
				d = d[:10]
			}
			if d > maxDate {
				maxDate = d
			}
		}

		if maxDate != "" {
			task["next_due_date"] = maxDate
			days := daysBetween(today, maxDate)
			task["days_remaining"] = int64(days)

			status := strOf(task, "status")
			done := status == "completed" || status == "approved"
			if today > maxDate && !done {
				task["working_days_delayed"] = int64(core.CalcPeriodicDelayWorkingDays(maxDate, today, holidays))
			}
		}

		if boolOf(task, "is_workflow_task") {
			deadline := strOf(task, "deadline")
			status := strOf(task, "status")
			done := status == "completed" || status == "approved"
			if deadline != "" && !done {
				nowDateTime := core.TehranNow().Format("2006-01-02 15:04:05")
				task["hours_delayed"] = int64(core.CalcHourDelay(deadline, nowDateTime))
				task["hours_remaining"] = int64(core.CalcHourRemaining(deadline, nowDateTime))
			}
		}
	}
}

func min(a, b int) int {
	if a < b {
		return a
	}
	return b
}

// daysBetween — معادلِ (int) $current->diff($max)->format('%r%a') برایِ دو
// تاریخِ 'Y-m-d': مثبت اگر maxDate جلوتر باشد، منفی اگر گذشته باشد.
func daysBetween(today, maxDate string) int {
	t, err1 := parseDate(today)
	m, err2 := parseDate(maxDate)
	if err1 != nil || err2 != nil {
		return 0
	}
	return int(m.Sub(t).Hours() / 24)
}
