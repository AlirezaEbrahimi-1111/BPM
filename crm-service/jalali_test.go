package main

import (
	"testing"
	"time"
)

// هر روز میلادی ۲۰۱۵ تا ۲۰۳۵: میلادی → شمسی → میلادی باید همان روز شود.
func TestJalaliRoundTrip(t *testing.T) {
	for d := time.Date(2015, 1, 1, 0, 0, 0, 0, time.UTC); d.Year() < 2036; d = d.AddDate(0, 0, 1) {
		jy, jm, jd := gregorianToJalali(d.Year(), int(d.Month()), d.Day())
		g, ok := jalaliToGregorian(jy, jm, jd)
		if !ok || !g.Equal(d) {
			t.Fatalf("%s → %d/%d/%d → %s (ok=%v)", d.Format("2006-01-02"), jy, jm, jd, g.Format("2006-01-02"), ok)
		}
	}
}

func TestJalaliKnownDates(t *testing.T) {
	cases := []struct {
		jy, jm, jd int
		want       string
	}{
		{1405, 1, 1, "2026-03-21"},  // نوروز ۱۴۰۵
		{1405, 1, 8, "2026-03-28"},  // فاکتور ۱ فایل اکسل
		{1405, 7, 25, "2026-10-17"}, // همان روزی که این کد نوشته شد
		{1404, 12, 29, "2026-03-20"},
	}
	for _, c := range cases {
		g, ok := jalaliToGregorian(c.jy, c.jm, c.jd)
		if !ok || g.Format("2006-01-02") != c.want {
			t.Errorf("%d/%d/%d → %s ok=%v, want %s", c.jy, c.jm, c.jd, g.Format("2006-01-02"), ok, c.want)
		}
	}
	for _, bad := range [][3]int{{1405, 7, 31}, {1405, 13, 1}, {1405, 0, 5}, {1404, 12, 31}} {
		if _, ok := jalaliToGregorian(bad[0], bad[1], bad[2]); ok {
			t.Errorf("%v should be invalid", bad)
		}
	}
}
