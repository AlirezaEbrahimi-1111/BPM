package main

import "time"

// تبدیل میلادی → شمسی. فقط سال شمسی برایمان مهم است (مبنای ریست شماره‌ی
// فاکتور)، ولی تابع کامل y/m/d برمی‌گرداند. الگوریتم استاندارد jdn.
func gregorianToJalali(gy, gm, gd int) (jy, jm, jd int) {
	gdm := []int{31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31}
	if gy > 1600 {
		jy = 979
		gy -= 1600
	} else {
		jy = 0
		gy -= 621
	}
	gy2 := gy
	if gm > 2 {
		gy2 = gy + 1
	}
	days := 365*gy + (gy2+3)/4 - (gy2+99)/100 + (gy2+399)/400 - 80 + gd
	for i := 0; i < gm-1; i++ {
		days += gdm[i]
	}
	// روز کبیسه‌ی میلادی همین بالا با gy2 (= gy+1 بعد از فوریه) حساب شده؛ یک `days++`
	// اضافه این‌جا بود که در سال‌های کبیسه از ۱ مارس تا آخر سال، تاریخ شمسی را یک
	// روز جلو می‌برد (۱۵۳۰ روز اشتباه در ۲۰۱۵–۲۰۳۵؛ با تقویم Intl کروم مقایسه شد).
	jy += 33 * (days / 12053)
	days %= 12053
	jy += 4 * (days / 1461)
	days %= 1461
	if days > 365 {
		jy += (days - 1) / 365
		days = (days - 1) % 365
	}
	if days < 186 {
		jm = 1 + days/31
		jd = 1 + days%31
	} else {
		jm = 7 + (days-186)/30
		jd = 1 + (days-186)%30
	}
	return
}

// jalaliToGregorian تبدیل شمسی → میلادی. عمدا الگوریتم مستقل دومی نیست: روزهای
// میلادی اطراف را می‌گردد تا همان روزی پیدا شود که gregorianToJalali به این تاریخ
// شمسی می‌برد؛ پس دو جهت هرگز با هم ناسازگار نمی‌شوند. ok=false یعنی تاریخ نامعتبر
// (مثلا ۱۴۰۵/۰۷/۳۱ یا ۱۴۰۵/۱۲/۳۰ در سال غیرکبیسه).
func jalaliToGregorian(jy, jm, jd int) (t time.Time, ok bool) {
	if jm < 1 || jm > 12 || jd < 1 || jd > 31 || (jm > 6 && jd > 30) {
		return time.Time{}, false
	}
	// ۱ فروردین همیشه بین ۱۹ تا ۲۲ مارس است؛ از ۱۵ مارس شروع و حداکثر ۳۸۰ روز جلو برو
	d := time.Date(jy+621, 3, 15, 12, 0, 0, 0, time.UTC)
	for i := 0; i < 380; i++ {
		y, m, dd := gregorianToJalali(d.Year(), int(d.Month()), d.Day())
		if y == jy && m == jm && dd == jd {
			return time.Date(d.Year(), d.Month(), d.Day(), 0, 0, 0, 0, time.UTC), true
		}
		if y > jy {
			break
		}
		d = d.AddDate(0, 0, 1)
	}
	return time.Time{}, false
}

// jalaliYearOf سال شمسی یک تاریخ میلادی را می‌دهد؛ اگر t صفر باشد، «حالا».
func jalaliYearOf(t time.Time) int {
	if t.IsZero() {
		t = time.Now()
	}
	jy, _, _ := gregorianToJalali(t.Year(), int(t.Month()), t.Day())
	return jy
}

// jalaliYMOf سال و ماه شمسی یک تاریخ میلادی را می‌دهد؛ اگر t صفر باشد، «حالا».
func jalaliYMOf(t time.Time) (jy, jm int) {
	if t.IsZero() {
		t = time.Now()
	}
	jy, jm, _ = gregorianToJalali(t.Year(), int(t.Month()), t.Day())
	return
}
