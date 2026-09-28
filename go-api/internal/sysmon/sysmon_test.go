package sysmon

import "testing"

// نمونه‌ی واقعیِ /proc/stat (یک سرور لینوکسِ معمولی) — خط اول همون چیزیه
// که parseCPUSample می‌خونه، بقیه‌ی خط‌ها (cpu0، intr، …) نادیده گرفته می‌شن
const sampleStat1 = `cpu  1000 10 200 8000 50 0 5 0 0 0
cpu0 500 5 100 4000 25 0 2 0 0 0
intr 12345
ctxt 67890
btime 1700000000
`

const sampleStat2 = `cpu  1050 10 220 8100 60 0 6 0 0 0
cpu0 525 5 110 4050 30 0 3 0 0 0
intr 12400
ctxt 68000
btime 1700000000
`

func TestParseCPUSample(t *testing.T) {
	c1, err := parseCPUSample(sampleStat1)
	if err != nil {
		t.Fatalf("parseCPUSample(sample1): %v", err)
	}
	if c1.user != 1000 || c1.idle != 8000 || c1.iowait != 50 {
		t.Fatalf("parseCPUSample(sample1) fields wrong: %+v", c1)
	}

	c2, err := parseCPUSample(sampleStat2)
	if err != nil {
		t.Fatalf("parseCPUSample(sample2): %v", err)
	}

	// دلتا: total از (1000+10+200+8000+50+0+5+0)=9265 به (1050+10+220+8100+60+0+6+0)=9446 → +181
	// idle+iowait از 8050 به 8160 → +110 → مشغول=71 → درصد = 71/181*100 ≈ 39.2%
	pct := cpuPercent(c1, c2)
	if pct < 39.0 || pct > 39.5 {
		t.Fatalf("cpuPercent = %.2f, expected ≈39.2", pct)
	}
}

func TestParseCPUSample_Invalid(t *testing.T) {
	if _, err := parseCPUSample("garbage\n"); err == nil {
		t.Fatal("expected error for malformed /proc/stat")
	}
	if _, err := parseCPUSample(""); err == nil {
		t.Fatal("expected error for empty /proc/stat")
	}
}

func TestCPUPercent_NoTimeElapsed(t *testing.T) {
	c, _ := parseCPUSample(sampleStat1)
	if pct := cpuPercent(c, c); pct != 0 {
		t.Fatalf("cpuPercent با دو نمونه‌ی یکسان باید ۰ باشه، شد %.2f", pct)
	}
}

// نمونه‌ی واقعیِ /proc/net/dev — با هدر ۲خطی، یک لوپ‌بک (باید نادیده گرفته بشه)
// و دو رابط واقعی (باید جمع بشن)
const sampleNetDev = `Inter-|   Receive                                                |  Transmit
 face |bytes    packets errs drop fifo frame compressed multicast|bytes    packets errs drop fifo colls carrier compressed
    lo: 999999999    1000    0    0    0     0          0         0   999999999    1000    0    0    0     0       0          0
  eth0: 1000000     500    0    0    0     0          0         0   2000000     300    0    0    0     0       0          0
docker0:  50000      10    0    0    0     0          0         0    60000      12    0    0    0     0       0          0
`

func TestParseNetSample(t *testing.T) {
	n := parseNetSample(sampleNetDev)
	wantRx := uint64(1000000 + 50000)
	wantTx := uint64(2000000 + 60000)
	if n.rxBytes != wantRx || n.txBytes != wantTx {
		t.Fatalf("parseNetSample = %+v, want rx=%d tx=%d (lo باید حذف بشه)", n, wantRx, wantTx)
	}
}

func TestParseNetSample_OnlyLoopback(t *testing.T) {
	content := "Inter-|...\n face |...\n    lo: 100 1 0 0 0 0 0 0 200 1 0 0 0 0 0 0\n"
	n := parseNetSample(content)
	if n.rxBytes != 0 || n.txBytes != 0 {
		t.Fatalf("فقط loopback باید همه چیز رو صفر کنه، شد %+v", n)
	}
}

// نمونه‌ی واقعیِ /proc/meminfo
const sampleMemInfo = `MemTotal:        8000000 kB
MemFree:          500000 kB
MemAvailable:    3000000 kB
Buffers:          100000 kB
Cached:          2000000 kB
SwapTotal:       2000000 kB
SwapFree:        1500000 kB
`

func TestParseMemInfo(t *testing.T) {
	m := parseMemInfo(sampleMemInfo)
	cases := map[string]uint64{
		"MemTotal": 8000000, "MemAvailable": 3000000,
		"SwapTotal": 2000000, "SwapFree": 1500000,
	}
	for k, want := range cases {
		if m[k] != want {
			t.Errorf("meminfo[%s] = %d, want %d", k, m[k], want)
		}
	}
	// محاسبه‌ی همون منطقی که هندلر انجام می‌ده
	used := m["MemTotal"] - m["MemAvailable"]
	if used != 5000000 {
		t.Errorf("used RAM = %d kB, want 5000000", used)
	}
	pct := 100 * float64(used) / float64(m["MemTotal"])
	if pct < 62.4 || pct > 62.6 {
		t.Errorf("RAM percent = %.2f, want ≈62.5", pct)
	}
}

func TestParseMemInfo_NoSwap(t *testing.T) {
	m := parseMemInfo("MemTotal: 1000 kB\nMemAvailable: 500 kB\n")
	if m["SwapTotal"] != 0 {
		t.Errorf("SwapTotal غایب باید ۰ حساب بشه، شد %d", m["SwapTotal"])
	}
}

func TestParseLoadAvg(t *testing.T) {
	l1, l5, l15, err := parseLoadAvg("0.52 0.58 0.59 1/234 5678\n")
	if err != nil {
		t.Fatalf("parseLoadAvg: %v", err)
	}
	if l1 != 0.52 || l5 != 0.58 || l15 != 0.59 {
		t.Fatalf("parseLoadAvg = %.2f %.2f %.2f", l1, l5, l15)
	}
}

func TestParseLoadAvg_Invalid(t *testing.T) {
	if _, _, _, err := parseLoadAvg("0.5 0.6"); err == nil {
		t.Fatal("expected error, only 2 fields given")
	}
}

func TestParseUptimeSeconds(t *testing.T) {
	sec, err := parseUptimeSeconds("12345.67 98765.43\n")
	if err != nil {
		t.Fatalf("parseUptimeSeconds: %v", err)
	}
	if sec < 12345.6 || sec > 12345.7 {
		t.Fatalf("parseUptimeSeconds = %v", sec)
	}
}

func TestFormatUptime(t *testing.T) {
	cases := []struct {
		seconds float64
		want    string
	}{
		{90, "1د"},                     // فقط دقیقه
		{3661, "1س 1د"},                // ساعت+دقیقه
		{90000, "1ر 1س 0د"},            // روز+ساعت+دقیقه (90000s = 1روز+1ساعت)
		{2*86400 + 3*3600, "2ر 3س 0د"}, // چندروزه
	}
	for _, c := range cases {
		if got := formatUptime(c.seconds); got != c.want {
			t.Errorf("formatUptime(%v) = %q, want %q", c.seconds, got, c.want)
		}
	}
}

func TestRound1(t *testing.T) {
	cases := map[float64]float64{
		39.24: 39.2, 39.25: 39.3, 0: 0, 100: 100, 62.55: 62.6,
	}
	for in, want := range cases {
		if got := round1(in); got != want {
			t.Errorf("round1(%v) = %v, want %v", in, got, want)
		}
	}
}
