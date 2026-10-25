package core

import (
	"database/sql"
	"strconv"
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

// PeOpts — تنظیمات الگوی تکرار برای weekdays/interval (پورت pe_taskOpts در PHP).
type PeOpts struct {
	Weekdays     []int // شمارهٔ روز هفته (۰=یکشنبه … ۶=شنبه، مثل PHP date('w'))؛ جمعه=۵ مجاز نیست
	IntervalDays int   // ۲ تا ۳۶۵
}

const (
	peIntervalMin = 2
	peIntervalMax = 365
)

// PeParseWeekdays — پورت pe_parseWeekdays(): '0,1,4' → [0 1 4] یکتا، مرتب، بدون جمعه.
func PeParseWeekdays(csv string) []int {
	seen := map[int]bool{}
	for _, part := range strings.Split(csv, ",") {
		n, err := strconv.Atoi(strings.TrimSpace(part))
		if err != nil || n < 0 || n > 6 || n == 5 {
			continue
		}
		seen[n] = true
	}
	out := []int{}
	for i := 0; i <= 6; i++ {
		if seen[i] {
			out = append(out, i)
		}
	}
	return out
}

func peHasWeekday(days []int, wd int) bool {
	for _, d := range days {
		if d == wd {
			return true
		}
	}
	return false
}

// PeFirstPeriodOnOrAfter — پورت pe_firstPeriodOnOrAfter(): برای weekdays اولین روز
// انتخاب‌شدهٔ کاری (نه خود start)؛ بقیه همان start.
func PeFirstPeriodOnOrAfter(periodType string, start time.Time, holidays map[string]bool, opts PeOpts) *string {
	if periodType != "weekdays" {
		d := dateOnly(start).Format("2006-01-02")
		return &d
	}
	dates := PePeriodDates(periodType, start, dateOnly(start).AddDate(0, 0, 60), holidays, nil, opts)
	if len(dates) == 0 {
		return nil
	}
	return &dates[0]
}

// PePeriodDates — پورت دقیق pe_periodDates(): فهرست تاریخ سررسید
// همه‌ی دوره‌ها از start تا upto (شامل خودش)، صعودی.
func PePeriodDates(periodType string, start, upto time.Time, holidays map[string]bool, endDate *string, optsArg ...PeOpts) []string {
	var dates []string
	cursor := dateOnly(start)
	limit := dateOnly(upto)
	noRecurring := map[int]bool{}
	var opts PeOpts
	if len(optsArg) > 0 {
		opts = optsArg[0]
	}

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

	case "weekdays":
		// روز انتخابی که تعطیل رسمی است دوره ندارد (مثل daily)
		if len(opts.Weekdays) == 0 {
			return dates
		}
		for guard := 0; !cursor.After(limit) && len(dates) < peMaxPeriods && guard < peMaxPeriods*7; guard++ {
			d := cursor.Format("2006-01-02")
			if endDate != nil && d > *endDate {
				break
			}
			if peHasWeekday(opts.Weekdays, int(cursor.Weekday())) && IsWorkingDay(cursor, holidays, noRecurring) {
				dates = append(dates, d)
			}
			cursor = cursor.AddDate(0, 0, 1)
		}
		return dates

	case "interval":
		n := opts.IntervalDays
		if n < peIntervalMin || n > peIntervalMax {
			return dates
		}
		for guard := 0; !cursor.After(limit) && guard < peMaxPeriods; guard++ {
			d := cursor.Format("2006-01-02")
			if endDate != nil && d > *endDate {
				break
			}
			dates = append(dates, d)
			cursor = cursor.AddDate(0, 0, n)
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
func PeNextPeriodAfter(periodType, afterDate string, holidays map[string]bool, anchor *time.Time, optsArg ...PeOpts) string {
	var opts PeOpts
	if len(optsArg) > 0 {
		opts = optsArg[0]
	}
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
	case "weekdays":
		for i := 0; i < 60 && len(opts.Weekdays) > 0; i++ {
			d = d.AddDate(0, 0, 1)
			if peHasWeekday(opts.Weekdays, int(d.Weekday())) && IsWorkingDay(d, holidays, noRecurring) {
				break
			}
		}
	case "interval":
		n := opts.IntervalDays
		if n < peIntervalMin {
			n = peIntervalMin
		}
		if n > peIntervalMax {
			n = peIntervalMax
		}
		d = d.AddDate(0, 0, n)
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
	// OldestOverdueDate / OverdueWorkingDays — تأخیر واقعی کار دوره‌ای: از قدیمی‌ترین
	// دورهٔ معوقهٔ بخشیده‌نشده تا امروز (WorkingDaysDelayed نسبت به موعد دورهٔ جاری است).
	OldestOverdueDate  *string
	OverdueWorkingDays int
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
	// weekdays / interval — ⚠️ هر کوئری که PeStateTask می‌سازد باید این دو ستون را هم بخواند
	PeriodWeekdays     string // tasks.period_weekdays، مثلا '0,1,4'
	PeriodIntervalDays int    // tasks.period_interval_days
}

func (t PeStateTask) opts() PeOpts {
	return PeOpts{Weekdays: PeParseWeekdays(t.PeriodWeekdays), IntervalDays: t.PeriodIntervalDays}
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

	opts := task.opts()

	if now.Before(start) {
		first := PeFirstPeriodOnOrAfter(task.PeriodType, start, holidays, opts)
		out.NextDueDate = first
		if first != nil {
			if fd, err := parseYMD(*first); err == nil {
				days := int(dateOnly(fd).Sub(now).Hours() / 24)
				out.DaysRemaining = &days
			}
		}
		return out
	}

	out.Started = true

	if endDate != nil && today > *endDate {
		out.Finished = true
		return out
	}

	periods := PePeriodDates(task.PeriodType, start, now, holidays, endDate, opts)
	if len(periods) == 0 {
		// weekdays: اولین روز انتخاب‌شده هنوز نرسیده → موعد = اولین دوره (هم‌خوان با PHP)
		if task.PeriodType == "weekdays" {
			if first := PeFirstPeriodOnOrAfter("weekdays", start, holidays, opts); first != nil && (endDate == nil || *first <= *endDate) {
				out.NextDueDate = first
				if fd, err := parseYMD(*first); err == nil {
					days := int(dateOnly(fd).Sub(now).Hours() / 24)
					out.DaysRemaining = &days
				}
			}
		}
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
	var overdueDates []string
	for _, p := range periods {
		if p >= currentPeriod {
			break
		}
		if !completedPeriods[p] {
			overdueRaw++
			overdueDates = append(overdueDates, p)
		}
	}
	out.OverdueRaw = overdueRaw
	out.OverduePeriods = overdueRaw - task.OverdueForgivenCredit
	if out.OverduePeriods < 0 {
		out.OverduePeriods = 0
	}
	// بخشش (رفع دورهٔ معوقه) قدیمی‌ترین دوره‌ها را کنار می‌گذارد
	if f := max(0, task.OverdueForgivenCredit); out.OverduePeriods > 0 && f < len(overdueDates) {
		oldest := overdueDates[f]
		out.OldestOverdueDate = &oldest
		out.OverdueWorkingDays = CalcPeriodicDelayWorkingDays(oldest, today, holidays)
	}

	if out.IsTodayDone {
		var anchorPtr *time.Time
		anchorPtr = &start
		next := PeNextPeriodAfter(task.PeriodType, currentPeriod, holidays, anchorPtr, opts)
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
