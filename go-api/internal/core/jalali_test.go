package core

import "testing"

// همان لنگرهای tests/unit/JalaliTest.php — تطبیقِ Go با نسخهٔ مرجعِ PHP.

func TestGregorianToJalali(t *testing.T) {
	cases := []struct {
		gy, gm, gd int
		jy, jm, jd int
	}{
		{2021, 3, 21, 1400, 1, 1},   // نوروز ۱۴۰۰
		{2024, 3, 20, 1403, 1, 1},   // نوروز ۱۴۰۳
		{2024, 3, 19, 1402, 12, 29}, // آخرین روزِ ۱۴۰۲
		{2000, 1, 1, 1378, 10, 11},
		{2026, 9, 7, 1405, 6, 16},
	}
	for _, c := range cases {
		jy, jm, jd := GregorianToJalali(c.gy, c.gm, c.gd)
		if jy != c.jy || jm != c.jm || jd != c.jd {
			t.Errorf("GregorianToJalali(%d-%d-%d) = %d/%d/%d ; want %d/%d/%d",
				c.gy, c.gm, c.gd, jy, jm, jd, c.jy, c.jm, c.jd)
		}
	}
}

func TestJalaliToGregorian(t *testing.T) {
	cases := []struct {
		jy, jm, jd int
		gy, gm, gd int
	}{
		{1400, 1, 1, 2021, 3, 21},
		{1403, 1, 1, 2024, 3, 20},
		{1402, 12, 29, 2024, 3, 19},
		{1378, 10, 11, 2000, 1, 1},
		{1405, 6, 1, 2026, 8, 23},
		{1405, 7, 1, 2026, 9, 23},
	}
	for _, c := range cases {
		gy, gm, gd := JalaliToGregorian(c.jy, c.jm, c.jd)
		if gy != c.gy || gm != c.gm || gd != c.gd {
			t.Errorf("JalaliToGregorian(%d/%d/%d) = %d-%d-%d ; want %d-%d-%d",
				c.jy, c.jm, c.jd, gy, gm, gd, c.gy, c.gm, c.gd)
		}
	}
}

// رفت‌وبرگشت روی یک بازهٔ چندساله باید بی‌خطا باشد.
func TestJalaliRoundTrip(t *testing.T) {
	// از 1399/01/01 تا حدودِ 1410
	jy, jm := 1399, 1
	for step := 0; step < 12*12; step++ {
		gy, gm, gd := JalaliToGregorian(jy, jm, 1)
		bjy, bjm, bjd := GregorianToJalali(gy, gm, gd)
		if bjy != jy || bjm != jm || bjd != 1 {
			t.Fatalf("round-trip شکست: %d/%d/01 -> %d-%d-%d -> %d/%d/%d",
				jy, jm, gy, gm, gd, bjy, bjm, bjd)
		}
		if jm++; jm > 12 {
			jm = 1
			jy++
		}
	}
}
