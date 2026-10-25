package core

import (
	"reflect"
	"testing"
	"time"
)

// قفل تطبیق با tests/unit/PeriodEngineTest.php — همان تاریخ‌ها و انتظارها.

func mustDate(s string) time.Time {
	t, err := time.Parse("2006-01-02", s)
	if err != nil {
		panic(err)
	}
	return t
}

func TestPeAddMonthsClamped(t *testing.T) {
	cases := []struct {
		name   string
		anchor string
		months int
		want   string
	}{
		{"normal", "2026-01-15", 1, "2026-02-15"},
		{"clamp to shorter month", "2026-01-31", 1, "2026-02-28"},
		{"anchor re-expands after clamp", "2026-01-31", 2, "2026-03-31"},
		{"year boundary", "2026-11-30", 3, "2027-02-28"},
		{"leap year Feb 29", "2024-01-31", 1, "2024-02-29"},
		{"zero months", "2026-06-07", 0, "2026-06-07"},
	}
	for _, c := range cases {
		got := peAddMonthsClamped(mustDate(c.anchor), c.months).Format("2006-01-02")
		if got != c.want {
			t.Errorf("%s: peAddMonthsClamped(%s,%d) = %s; want %s", c.name, c.anchor, c.months, got, c.want)
		}
	}
}

func TestPeMonthsBetween(t *testing.T) {
	cases := []struct {
		name         string
		anchor, date string
		want         int
	}{
		{"same month", "2026-03-01", "2026-03-28", 0},
		{"day ignored", "2026-01-15", "2026-04-01", 3},
		{"year crossing", "2025-11-01", "2026-02-01", 3},
	}
	for _, c := range cases {
		got := peMonthsBetween(mustDate(c.anchor), mustDate(c.date))
		if got != c.want {
			t.Errorf("%s: peMonthsBetween(%s,%s) = %d; want %d", c.name, c.anchor, c.date, got, c.want)
		}
	}
}

func TestPePeriodDatesDaily(t *testing.T) {
	cases := []struct {
		name     string
		start    string
		upto     string
		holidays map[string]bool
		endDate  *string
		want     []string
	}{
		{"friday excluded", "2026-07-11", "2026-07-18", map[string]bool{}, nil,
			[]string{"2026-07-11", "2026-07-12", "2026-07-13", "2026-07-14", "2026-07-15", "2026-07-16", "2026-07-18"}},
		{"start on friday skips to next working day", "2026-07-10", "2026-07-13", map[string]bool{}, nil,
			[]string{"2026-07-11", "2026-07-12", "2026-07-13"}},
		{"holiday mid-range excluded", "2026-07-11", "2026-07-14", map[string]bool{"2026-07-13": true}, nil,
			[]string{"2026-07-11", "2026-07-12", "2026-07-14"}},
	}
	for _, c := range cases {
		got := PePeriodDates("daily", mustDate(c.start), mustDate(c.upto), c.holidays, c.endDate)
		if !reflect.DeepEqual(got, c.want) {
			t.Errorf("%s: got %v; want %v", c.name, got, c.want)
		}
	}

	end := "2026-07-14"
	got := PePeriodDates("daily", mustDate("2026-07-11"), mustDate("2026-07-31"), map[string]bool{}, &end)
	want := []string{"2026-07-11", "2026-07-12", "2026-07-13", "2026-07-14"}
	if !reflect.DeepEqual(got, want) {
		t.Errorf("endDate closes range early: got %v; want %v", got, want)
	}
}

func TestPePeriodDatesWeekly(t *testing.T) {
	got := PePeriodDates("weekly", mustDate("2026-07-11"), mustDate("2026-08-01"), map[string]bool{}, nil)
	want := []string{"2026-07-11", "2026-07-18", "2026-07-25", "2026-08-01"}
	if !reflect.DeepEqual(got, want) {
		t.Errorf("weekly: got %v; want %v", got, want)
	}

	end := "2026-07-25"
	got = PePeriodDates("weekly", mustDate("2026-07-11"), mustDate("2026-09-01"), map[string]bool{}, &end)
	want = []string{"2026-07-11", "2026-07-18", "2026-07-25"}
	if !reflect.DeepEqual(got, want) {
		t.Errorf("weekly endDate: got %v; want %v", got, want)
	}
}

func TestPePeriodDatesMonthly(t *testing.T) {
	got := PePeriodDates("monthly", mustDate("2026-01-15"), mustDate("2026-04-30"), map[string]bool{}, nil)
	want := []string{"2026-01-15", "2026-02-15", "2026-03-15", "2026-04-15"}
	if !reflect.DeepEqual(got, want) {
		t.Errorf("monthly anchor: got %v; want %v", got, want)
	}

	got = PePeriodDates("monthly", mustDate("2026-01-31"), mustDate("2026-04-30"), map[string]bool{}, nil)
	want = []string{"2026-01-31", "2026-02-28", "2026-03-31", "2026-04-30"}
	if !reflect.DeepEqual(got, want) {
		t.Errorf("monthly no-drift: got %v; want %v (March must be 31, proving no anchor drift)", got, want)
	}

	got = PePeriodDates("monthly", mustDate("2026-01-15"), mustDate("2026-03-14"), map[string]bool{}, nil)
	want = []string{"2026-01-15", "2026-02-15"}
	if !reflect.DeepEqual(got, want) {
		t.Errorf("monthly upto cutoff: got %v; want %v", got, want)
	}
}

func TestPePeriodOf(t *testing.T) {
	periods := []string{"2026-07-01", "2026-07-02", "2026-07-05", "2026-07-06"}
	cases := []struct {
		name string
		date string
		want string
	}{
		{"last due <= date", "2026-07-04", "2026-07-02"},
		{"exact match", "2026-07-05", "2026-07-05"},
		{"before first period", "2026-06-30", ""},
		{"after all periods", "2026-07-20", "2026-07-06"},
	}
	for _, c := range cases {
		got := PePeriodOf(periods, c.date)
		if got != c.want {
			t.Errorf("%s: PePeriodOf(...,%s) = %q; want %q", c.name, c.date, got, c.want)
		}
	}
}

// ── weekdays / interval — قفل تطبیق با PeriodEngineTest.php ──

func TestPePeriodDatesWeekdays(t *testing.T) {
	o := PeOpts{Weekdays: []int{0, 1, 4}}
	got := PePeriodDates("weekdays", mustDate("2026-07-15"), mustDate("2026-07-27"), map[string]bool{}, nil, o)
	want := []string{"2026-07-16", "2026-07-19", "2026-07-20", "2026-07-23", "2026-07-26", "2026-07-27"}
	if !reflect.DeepEqual(got, want) {
		t.Errorf("weekdays: got %v; want %v", got, want)
	}
	got = PePeriodDates("weekdays", mustDate("2026-07-15"), mustDate("2026-07-23"), map[string]bool{"2026-07-19": true}, nil, o)
	want = []string{"2026-07-16", "2026-07-20", "2026-07-23"}
	if !reflect.DeepEqual(got, want) {
		t.Errorf("weekdays holiday skip: got %v; want %v", got, want)
	}
	if n := PeNextPeriodAfter("weekdays", "2026-07-16", map[string]bool{"2026-07-19": true}, nil, o); n != "2026-07-20" {
		t.Errorf("weekdays next after holiday: got %s", n)
	}
	if !reflect.DeepEqual(PeParseWeekdays("4,0,5,1,1,9,x"), []int{0, 1, 4}) {
		t.Errorf("PeParseWeekdays: got %v", PeParseWeekdays("4,0,5,1,1,9,x"))
	}
}

func TestPePeriodDatesInterval(t *testing.T) {
	o := PeOpts{IntervalDays: 9}
	got := PePeriodDates("interval", mustDate("2026-07-11"), mustDate("2026-08-10"), map[string]bool{}, nil, o)
	want := []string{"2026-07-11", "2026-07-20", "2026-07-29", "2026-08-07"}
	if !reflect.DeepEqual(got, want) {
		t.Errorf("interval: got %v; want %v", got, want)
	}
	if got := PePeriodDates("interval", mustDate("2026-07-11"), mustDate("2026-08-10"), map[string]bool{}, nil, PeOpts{IntervalDays: 1}); len(got) != 0 {
		t.Errorf("interval 1 must yield nothing: %v", got)
	}
	if n := PeNextPeriodAfter("interval", "2026-07-20", map[string]bool{}, nil, o); n != "2026-07-29" {
		t.Errorf("interval next: got %s", n)
	}
}

func TestPeStateWeekdays(t *testing.T) {
	task := PeStateTask{ID: 1, TaskType: "continuous", StartDate: "2026-07-15", PeriodType: "weekdays", PeriodWeekdays: "0,1,4"}
	st := PeStateCalc(nil, task, map[string]bool{}, "2026-07-15", map[int64][]string{1: {}})
	if st.NextDueDate == nil || *st.NextDueDate != "2026-07-16" || st.CanComplete {
		t.Errorf("before first weekday: %+v", st)
	}
	st = PeStateCalc(nil, task, map[string]bool{}, "2026-07-21", map[int64][]string{1: {"2026-07-16", "2026-07-20"}})
	if st.OverduePeriods != 1 || !st.IsTodayDone || st.NextDueDate == nil || *st.NextDueDate != "2026-07-23" || st.CanComplete {
		t.Errorf("weekdays state: %+v next=%v", st, st.NextDueDate)
	}
}
