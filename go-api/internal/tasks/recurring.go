package tasks

import (
	"database/sql"

	"bmp/go-api/internal/core"
)

// MaybeStartNextPeriod — پورتِ دقیقِ maybeStartNextPeriod() در
// includes/recurring-helper.php: اگر کارِ تکرارشونده‌ای در period_done
// باشد و دوره‌ی بعدی‌اش شروع شده باشد، وضعیت را به not_started برمی‌گرداند
// (بدونِ ثبتِ رکوردِ تاریخچه — عمداً، چون این عملِ سیستمی نباید به کاربر
// نشان داده شود). این عملیات UPDATE می‌نویسد — چون خودِ منطق «همین که
// تسک دیده شد، دوره را چک کن» طراحی شده (مثلِ overview.php)، نه چون این
// endpoint قرار است نوشتنی باشد؛ رفعِ ناهماهنگیِ صفحاتِ مختلف (که هرکدوم
// این چک را انجام می‌دادند یا نمی‌دادند) با تصمیمِ صریح انجام شد.
func MaybeStartNextPeriod(db *sql.DB, task map[string]any, today string, holidays map[string]bool, preloadedCompletionMap map[int64][]string) bool {
	if strOf(task, "task_type") != "continuous" {
		return false
	}
	if strOf(task, "status") != "period_done" {
		return false
	}
	if strOf(task, "start_date") == "" {
		return false
	}

	st := core.PeStateTask{
		ID:                    int64Of(task, "id"),
		TaskType:              strOf(task, "task_type"),
		StartDate:             strOf(task, "start_date"),
		EndDate:               strOf(task, "end_date"),
		PeriodType:            strOf(task, "period_type"),
		OverdueForgivenCredit: int(int64Of(task, "overdue_forgiven_credit")),
	}
	state := core.PeStateCalc(db, st, holidays, today, preloadedCompletionMap)

	if !state.IsTodayDone && !state.Finished && state.Started {
		_, err := db.Exec("UPDATE tasks SET status = 'not_started', updated_at = NOW() WHERE id = ?", st.ID)
		if err != nil {
			return false
		}
		task["status"] = "not_started"
		return true
	}
	return false
}
