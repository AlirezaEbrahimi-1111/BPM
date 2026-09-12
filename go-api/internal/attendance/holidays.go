// Package attendance — پورتِ بخشِ خواندنیِ api/attendance/* (فعلاً فقط
// today-status.php و absent-today.php؛ endpointِ نوشتنیِ ثبتِ ورود/خروج
// (register.php) و منطقِ محاسبه‌ی کسری/حقوق عمداً پورت نشده‌اند — طبقِ
// تصمیمِ صریح، چون ریسکِ مالی/عملیاتی‌شان بالاست.
package attendance

import (
	"database/sql"
	"time"
)

// holidaySet — پورتِ getHolidaySet() در includes/working-days-helper.php.
// برخلافِ نسخهٔ PHP (که با static cache در طولِ یک اجرای اسکریپت کش می‌شود)،
// اینجا کش نمی‌شود: go-api یک فرآیندِ درازمدت است، پس یک کشِ سطحِ پکیج
// می‌توانست بعد از تغییرِ جدولِ holidays بیات بماند.
func holidaySet(db *sql.DB, orgID int64) (map[string]bool, error) {
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

// recurringHolidayWeekdays — پورتِ getRecurringHolidayWeekdays().
// مقادیر بر اساسِ همان شماره‌گذاریِ PHP (date('w')): ۰=یکشنبه ... ۶=شنبه —
// که دقیقاً با time.Weekday() در Go یکی است (Sunday=0 ... Saturday=6)،
// پس هیچ تبدیلی لازم نیست.
func recurringHolidayWeekdays(db *sql.DB, orgID int64) (map[int]bool, error) {
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

// isWorkingDay — پورتِ دقیقِ isWorkingDay() در working-days-helper.php.
func isWorkingDay(t time.Time, holidays map[string]bool, recurring map[int]bool) bool {
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
