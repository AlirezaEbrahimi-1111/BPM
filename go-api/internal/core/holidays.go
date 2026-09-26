package core

import (
	"database/sql"
	"time"
)

// HolidaySet — پورت getHolidaySet() در includes/working-days-helper.php.
// orgID == nil یعنی همون‌چیزی که فراخوانی PHP بدون آرگومان می‌داد: چون
// PHP وقتی $organizationId=null باشه، `organization_id = :org_id` با
// NULL هرگز true نمی‌شه، در عمل فقط ردیف‌های global `organization_id
// IS NULL` برمی‌گردن. اینجا هم با orgID=nil همون رفتار عینا تکرار می‌شه.
//
// برخلاف نسخهٔ PHP (که با static cache در طول یک اجرای اسکریپت کش
// می‌شود)، اینجا کش نمی‌شود: go-api یک فرآیند درازمدت است، پس یک کش
// سطح پکیج می‌توانست بعد از تغییر جدول holidays بیات بماند.
func HolidaySet(db *sql.DB, orgID *int64) (map[string]bool, error) {
	rows, err := db.Query(`
		SELECT holiday_date FROM holidays
		WHERE type = 'date' AND holiday_date IS NOT NULL
		  AND (organization_id IS NULL OR organization_id = ?)
	`, orgID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	set := map[string]bool{}
	for rows.Next() {
		var d string
		if err := rows.Scan(&d); err != nil {
			return nil, err
		}
		set[d] = true
	}
	return set, rows.Err()
}

// RecurringHolidayWeekdays — پورت getRecurringHolidayWeekdays(). مقادیر بر
// اساس همان شماره‌گذاری PHP (date('w')): ۰=یکشنبه ... ۶=شنبه — که دقیقا
// با time.Weekday() در Go یکی است (Sunday=0 ... Saturday=6)، پس هیچ
// تبدیلی لازم نیست.
func RecurringHolidayWeekdays(db *sql.DB, orgID *int64) (map[int]bool, error) {
	rows, err := db.Query(`
		SELECT DISTINCT day_of_week FROM holidays
		WHERE type = 'weekly' AND day_of_week IS NOT NULL
		  AND (organization_id IS NULL OR organization_id = ?)
	`, orgID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	days := map[int]bool{}
	for rows.Next() {
		var d int
		if err := rows.Scan(&d); err != nil {
			return nil, err
		}
		days[d] = true
	}
	return days, rows.Err()
}

// IsWorkingDay — پورت دقیق isWorkingDay() در working-days-helper.php.
func IsWorkingDay(t time.Time, holidays map[string]bool, recurring map[int]bool) bool {
	dow := int(t.Weekday())
	if dow == 5 { // جمعه
		return false
	}
	if recurring[dow] {
		return false
	}
	if holidays[t.Format("2006-01-02")] {
		return false
	}
	return true
}

// CalcPeriodicDelayWorkingDays — پورت دقیق calcPeriodicDelayWorkingDays()
// در working-days-helper.php: تعداد روز کاری تأخیر، از due+1 تا today.
//
// عمدا recurring را نمی‌گیرد: نسخهٔ PHP خودش isWorkingDay() را با
// آرگومان سوم را خالی صدا می‌زند (پیش‌فرض تابع)، یعنی این محاسبه فقط
// جمعه + تعطیلات یک‌روزه را کم می‌کند، نه تعطیلات هفتگی تکرارشونده —
// حتی اگر این ناهماهنگی به‌نظر برسد، پاریتی با PHP اولویت دارد.
func CalcPeriodicDelayWorkingDays(dueDateStr, todayStr string, holidays map[string]bool) int {
	due, err1 := time.Parse("2006-01-02", dueDateStr[:10])
	today, err2 := time.Parse("2006-01-02", todayStr[:10])
	if err1 != nil || err2 != nil {
		return 0
	}
	if !today.After(due) {
		return 0
	}
	cursor := due.AddDate(0, 0, 1)
	delay := 0
	noRecurring := map[int]bool{}
	for !cursor.After(today) {
		if IsWorkingDay(cursor, holidays, noRecurring) {
			delay++
		}
		cursor = cursor.AddDate(0, 0, 1)
	}
	return delay
}

// CalcHourDelay — پورت دقیق calcHourDelay() در working-days-helper.php.
func CalcHourDelay(deadline, now string) int {
	d, err1 := time.Parse("2006-01-02 15:04:05", deadline)
	n, err2 := time.Parse("2006-01-02 15:04:05", now)
	if err1 != nil || err2 != nil || !n.After(d) {
		return 0
	}
	return int(n.Sub(d).Hours())
}

// CalcHourRemaining — پورت دقیق calcHourRemaining().
func CalcHourRemaining(deadline, now string) int {
	d, err1 := time.Parse("2006-01-02 15:04:05", deadline)
	n, err2 := time.Parse("2006-01-02 15:04:05", now)
	if err1 != nil || err2 != nil || !d.After(n) {
		return 0
	}
	diff := d.Sub(n)
	hours := int(diff.Hours())
	if diff-time.Duration(hours)*time.Hour > 0 {
		hours++ // ceil
	}
	return hours
}
