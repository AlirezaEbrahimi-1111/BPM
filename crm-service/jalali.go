package main

import "time"

// تبدیلِ میلادی → شمسی. فقط سالِ شمسی برایمان مهم است (مبنایِ ریستِ شماره‌ی
// فاکتور)، ولی تابع کاملِ y/m/d برمی‌گرداند. الگوریتمِ استانداردِ jdn.
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
	if gm > 2 && ((gy%4 == 0 && gy%100 != 0) || gy%400 == 0) {
		days++
	}
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

// jalaliYearOf سالِ شمسیِ یک تاریخِ میلادی را می‌دهد؛ اگر t صفر باشد، «حالا».
func jalaliYearOf(t time.Time) int {
	if t.IsZero() {
		t = time.Now()
	}
	jy, _, _ := gregorianToJalali(t.Year(), int(t.Month()), t.Day())
	return jy
}

// jalaliYMOf سال و ماهِ شمسیِ یک تاریخِ میلادی را می‌دهد؛ اگر t صفر باشد، «حالا».
func jalaliYMOf(t time.Time) (jy, jm int) {
	if t.IsZero() {
		t = time.Now()
	}
	jy, jm, _ = gregorianToJalali(t.Year(), int(t.Month()), t.Day())
	return
}
