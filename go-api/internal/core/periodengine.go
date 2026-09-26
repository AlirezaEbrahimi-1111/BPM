package core

import (
	"database/sql"
	"strings"
	"time"
)

// پورت دقیق includes/period-engine.php — تنها مرجع محاسبه‌ی دوره‌ی
// کارهای تکرارشونده. هر تغییری اینجا باید هم‌زمان در نسخه‌ی PHP هم باشد.

const peMaxPeriods = 3000

// peAddMonthsClamped — پورت pe_addMonthsClamped(): افزودن N ماه به یک
// تاریخ لنگر با روز ثابت، بدون سرریز تقویمی (اگر روز لنگر در ماه
// مقصد نبود، به آخرین روز همان ماه محدود می‌شود).
func peAddMonthsClamped(anchor time.Time, months int) time.Time {
	day := anchor.Day()
	y, m := anchor.Year(), int(anchor.Month())+months

	// m همیشه >= 1 است (months فقط از pe_nextPeriodAfter با +1 صدا زده
	// می‌شود)، پس نیازی به هندل صریح ماه منفی نیست.
	y += (m - 1) / 12
	m = ((m-1)%12+12)%12 + 1

	lastDay := time.Date(y, time.Month(m)+1, 0, 0, 0, 0, 0, time.UTC).Day()
	if day > lastDay {
		day = lastDay
	}
	return time.Date(y, time.Month(m), day, 0, 0, 0, 0, time.UTC)
}

// peMonthsBetween — پورت pe_monthsBetween().
func peMonthsBetween(anchor, date time.Time) int {
	y := date.Year() - anchor.Year()
	m := int(date.Month()) - int(anchor.Month())
	return y*12 + m
}

func dateOnly(t time.Time) time.Time {
	return time.Date(t.Year(), t.Month(), t.Day(), 0, 0, 0, 0, time.UTC)
}

func parseYMD(s string) (time.Time, error) {
	if len(s) > 10 {
		s = s[:10]
	}
	return time.Parse("2006-01-02", s)
}

// PePeriodDates — پورت دقیق pe_periodDates(): فهرست تاریخ سررسید
// همه‌ی دوره‌ها از start تا upto (شامل خودش)، صعودی.
func PePeriodDates(periodType string, start, upto time.Time, holidays map[string]bool, endDate *string) []string {
	var dates []string
	cursor := dateOnly(start)
	limit := dateOnly(upto)
	noRecurring := map[int]bool{}

	switch periodType {
	case "daily":
		for !IsWorkingDay(cursor, holidays, noRecurring) {
			cursor = cursor.AddDate(0, 0, 1)
			if cursor.After(limit) {
				return dates
			}
		}
		for guard := 0; !cursor.After(limit) && guard < peMaxPeriods; guard++ {
			d := cursor.Format("2006-01-02")
			if endDate != nil && d > *endDate {
				break
			}
			dates = append(dates, d)
			for {
				cursor = cursor.AddDate(0, 0, 1)
				if IsWorkingDay(cursor, holidays, noRecurring) {
					break
				}
			}
		}
		return dates

	case "weekly":
		for guard := 0; !cursor.After(limit) && guard < peMaxPeriods; guard++ {
			d := cursor.Format("2006-01-02")
			if endDate != nil && d > *endDate {
				break
			}
			dates = append(dates, d)
			cursor = cursor.AddDate(0, 0, 7)
		}
		return dates

	case "monthly":
		i := 0
		for guard := 0; guard < peMaxPeriods; guard++ {
			periodDate := peAddMonthsClamped(dateOnly(start), i)
			if periodDate.After(limit) {
				break
			}
			d := periodDate.Format("2006-01-02")
			if endDate != nil && d > *endDate {
				break
			}
			dates = append(dates, d)
			i++
		}
		return dates
	}

	return dates
}

// PePeriodOf — پورت pe_periodOf(): تاریخ سررسید دوره‌ای که یک تاریخ
// مشخص به آن تعلق دارد (آخرین سررسیدی که <= date است).
func PePeriodOf(periodDates []string, date string) string {
	found := ""
	for _, d := range periodDates {
		if d <= date {
			found = d
		} else {
			break
		}
	}
	return found
}

// PeCompletionDates — پورت pe_completionDates(): تاریخ‌های یکتایی که
// کاربر روی این کار «تکمیل» زده (از preloadedMap اگر داده شده باشد).
func PeCompletionDates(db *sql.DB, taskID int64, preloadedMap map[int64][]string) ([]string, error) {
	if preloadedMap != nil {
		return preloadedMap[taskID], nil
	}
	rows, err := db.Query(`
		SELECT DISTINCT DATE(created_at) FROM task_history
		WHERE task_id = ? AND action = 'completed' ORDER BY 1 ASC
	`, taskID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []string
	for rows.Next() {
		var d string
		if err := rows.Scan(&d); err != nil {
			return nil, err
		}
		out = append(out, d)
	}
	return out, rows.Err()
}

// PePreloadCompletionDates — پورت pe_preloadCompletionDates(): نسخه‌ی
// دسته‌ای برای همه‌ی تسک‌های داده‌شده، با یک کوئری.
func PePreloadCompletionDates(db *sql.DB, taskIDs []int64) (map[int64][]string, error) {
	uniq := map[int64]bool{}
	var ids []int64
	for _, id := range taskIDs {
		if id != 0 && !uniq[id] {
			uniq[id] = true
			ids = append(ids, id)
		}
	}
	if len(ids) == 0 {
		return map[int64][]string{}, nil
	}
	placeholders := strings.TrimSuffix(strings.Repeat("?,", len(ids)), ",")
	args := make([]any, len(ids))
	for i, id := range ids {
		args[i] = id
	}
	rows, err := db.Query(`
		SELECT DISTINCT task_id, DATE(created_at) AS d FROM task_history
		WHERE task_id IN (`+placeholders+`) AND action = 'completed'
		ORDER BY task_id ASC, d ASC
	`, args...)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	m := map[int64][]string{}
	for rows.Next() {
		var taskID int64
		var d string
		if err := rows.Scan(&taskID, &d); err != nil {
			return nil, err
		}
		m[taskID] = append(m[taskID], d)
	}
	return m, rows.Err()
}

// PeNextPeriodAfter — پورت pe_nextPeriodAfter().
func PeNextPeriodAfter(periodType, afterDate string, holidays map[string]bool, anchor *time.Time) string {
	d, err := parseYMD(afterDate)
	if err != nil {
		return afterDate
	}
	d = dateOnly(d)
	noRecurring := map[int]bool{}

	switch periodType {
	case "daily":
		for {
			d = d.AddDate(0, 0, 1)
			if IsWorkingDay(d, holidays, noRecurring) {
				break
			}
		}
	case "weekly":
		d = d.AddDate(0, 0, 7)
	case "monthly":
		anchorDate := d
		if anchor != nil {
			anchorDate = dateOnly(*anchor)
		}
		monthsSoFar := peMonthsBetween(anchorDate, d)
		d = peAddMonthsClamped(anchorDate, monthsSoFar+1)
	}
	return d.Format("2006-01-02")
}

// PeState — خروجی pe_state(): وضعیت کامل دوره‌های یک کار تکرارشونده.
type PeState struct {
	Started            bool
	Finished           bool
	CurrentPeriodDate  *string
	IsTodayDone        bool
	NextDueDate        *string
	DaysRemaining      *int
	OverduePeriods     int
	OverdueRaw         int
	CompletedPeriods   int
	Forgiven           int
	WorkingDaysDelayed int
	CanComplete        bool
}

// PeStateTask — فقط ستون‌هایی از تسک که pe_state لازم دارد.
type PeStateTask struct {
	ID                    int64
	TaskType              string
	StartDate             string // ممکن است خالی باشد
	EndDate               string // ممکن است خالی باشد
	PeriodType            string
	OverdueForgivenCredit int
}

// PeStateCalc — پورت دقیق pe_state().
func PeStateCalc(db *sql.DB, task PeStateTask, holidays map[string]bool, today string, preloadedCompletionMap map[int64][]string) PeState {
	out := PeState{Forgiven: task.OverdueForgivenCredit}

	if task.TaskType != "continuous" || task.StartDate == "" || task.PeriodType == "" {
		return out
	}

	start, err := parseYMD(task.StartDate)
	if err != nil {
		return out
	}
	start = dateOnly(start)
	now, err := parseYMD(today)
	if err != nil {
		return out
	}
	now = dateOnly(now)

	var endDate *string
	if task.EndDate != "" {
		e := task.EndDate[:10]
		endDate = &e
	}

	if now.Before(start) {
		nd := start.Format("2006-01-02")
		out.NextDueDate = &nd
		days := int(start.Sub(now).Hours() / 24)
		out.DaysRemaining = &days
		return out
	}

	out.Started = true

	if endDate != nil && today > *endDate {
		out.Finished = true
		return out
	}

	periods := PePeriodDates(task.PeriodType, start, now, holidays, endDate)
	if len(periods) == 0 {
		return out
	}

	currentPeriod := PePeriodOf(periods, today)
	if currentPeriod != "" {
		cp := currentPeriod
		out.CurrentPeriodDate = &cp
	}

	completionDates, err := PeCompletionDates(db, task.ID, preloadedCompletionMap)
	if err != nil {
		completionDates = nil
	}
	completedPeriods := map[string]bool{}
	for _, cd := range completionDates {
		p := PePeriodOf(periods, cd)
		if p != "" {
			completedPeriods[p] = true
		}
	}
	out.CompletedPeriods = len(completedPeriods)
	out.IsTodayDone = completedPeriods[currentPeriod]

	overdueRaw := 0
	for _, p := range periods {
		if p >= currentPeriod {
			break
		}
		if !completedPeriods[p] {
			overdueRaw++
		}
	}
	out.OverdueRaw = overdueRaw
	out.OverduePeriods = overdueRaw - task.OverdueForgivenCredit
	if out.OverduePeriods < 0 {
		out.OverduePeriods = 0
	}

	if out.IsTodayDone {
		var anchorPtr *time.Time
		anchorPtr = &start
		next := PeNextPeriodAfter(task.PeriodType, currentPeriod, holidays, anchorPtr)
		if endDate != nil && next > *endDate {
			out.NextDueDate = nil
		} else {
			out.NextDueDate = &next
		}
	} else {
		cp := currentPeriod
		out.NextDueDate = &cp
	}

	if out.NextDueDate != nil {
		nd, err := parseYMD(*out.NextDueDate)
		if err == nil {
			nd = dateOnly(nd)
			days := int(nd.Sub(now).Hours() / 24)
			out.DaysRemaining = &days
			if nd.Before(now) {
				out.WorkingDaysDelayed = CalcPeriodicDelayWorkingDays(*out.NextDueDate, today, holidays)
			}
		}
	}

	out.CanComplete = !out.IsTodayDone && !out.Finished
	return out
}
