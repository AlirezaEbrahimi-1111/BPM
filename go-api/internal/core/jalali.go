package core

// تقویمِ شمسی — پورتِ الگوریتمِ استانداردِ jdf که در PHP مرجع است:
//   - GregorianToJalali  ≡ includes/JalaliHelper.php + attendance_system گِرِگوریِن‌توجلالیِ‌کَلک
//   - JalaliToGregorian  ≡ نسخهٔ اصلاح‌شدهٔ includes/date_helper.php::jalaliToGregorian
//     (این جلسه تعمیر شد؛ نسخهٔ قبلی ترمِ «سال» را جا انداخته بود و همیشه ~۱۶۰۰ می‌داد)
//
// قفلِ تطبیق: jalali_test.go با همان لنگرهای tests/unit/JalaliTest.php.

// GregorianToJalali میلادی (y, m=1..12, d) → شمسی (jy, jm=1..12, jd).
func GregorianToJalali(gy, gm, gd int) (jy, jm, jd int) {
	gDM := [12]int{0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334}
	gy2 := gy
	if gm > 2 {
		gy2 = gy + 1
	}
	days := 355666 + (365 * gy) + (gy2+3)/4 - (gy2+99)/100 + (gy2+399)/400 + gd + gDM[gm-1]
	jy = -1595 + 33*(days/12053)
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

// JalaliToGregorian شمسی (jy, jm=1..12, jd) → میلادی (gy, gm=1..12, gd).
func JalaliToGregorian(jy, jm, jd int) (gy, gm, gd int) {
	if jy < 979 {
		gy = 621
	} else {
		gy = 1600
		jy -= 979
	}
	days := (365 * jy) + (jy/33)*8 + ((jy%33)+3)/4 + 78 + jd
	if jm < 7 {
		days += (jm - 1) * 31
	} else {
		days += (jm-7)*30 + 186
	}
	gy += 400 * (days / 146097)
	days %= 146097
	if days > 36524 {
		days--
		gy += 100 * (days / 36524)
		days %= 36524
		if days >= 365 {
			days++
		}
	}
	gy += 4 * (days / 1461)
	days %= 1461
	if days > 365 {
		gy += (days - 1) / 365
		days = (days - 1) % 365
	}
	gd = days + 1

	leap := 28
	if (gy%4 == 0 && gy%100 != 0) || gy%400 == 0 {
		leap = 29
	}
	salA := [13]int{0, 31, leap, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31}
	for gm = 0; gm < 13; gm++ {
		if gd <= salA[gm] {
			break
		}
		gd -= salA[gm]
	}
	return
}
