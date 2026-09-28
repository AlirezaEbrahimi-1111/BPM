// Package sysmon — مانیتورینگ سبکِ خودِ سرور (نه Netdata)، فقط برای
// api/system/monitor یک صفحه‌ی خلاصه‌ی داخلِ خودِ پروژه.
//
// 🔒 چرا مستقیم از /proc لینوکس می‌خونه، نه از Netdata: Netdata برای
// دیدنِ دستیِ کاملِ سرور نصب شده (با تونلِ SSH)، نه برای اینکه این
// endpoint هر بار روش پروکسی بزنه. اسم فایل دیسک/شبکه روی هر سرور فرق
// می‌کنه (مثلا eth0 در برابر ens3) و اعتماد به chart id هایِ Netdata
// شکننده‌ست؛ چون این سرویس خودش روی همون سرور لینوکسی اجرا می‌شه، خوندنِ
// مستقیمِ /proc دقیقا همون اعدادیه که هر ابزارِ دیگه (top، Netdata، …)
// هم می‌بینه، بدون هیچ وابستگیِ اضافه‌ای.
package sysmon

import (
	"bufio"
	"database/sql"
	"fmt"
	"net/http"
	"os"
	"strconv"
	"strings"
	"sync"
	"time"

	"bmp/go-api/internal/core"
)

/* ─────────────────────── نمونه‌گیریِ CPU (نرخی، نیازمندِ دو خواندن) ─────────────────────── */

type cpuSample struct {
	user, nice, system, idle, iowait, irq, softirq, steal uint64
}

func (c cpuSample) total() uint64 {
	return c.user + c.nice + c.system + c.idle + c.iowait + c.irq + c.softirq + c.steal
}
func (c cpuSample) idleAll() uint64 { return c.idle + c.iowait }

// parseCPUSample — منطقِ خالص، جدا از خواندنِ فایل، تا با متنِ فرضی هم
// (تستِ واحد، بدونِ نیاز به لینوکسِ واقعی) قابل‌بررسی باشه
func parseCPUSample(content string) (cpuSample, error) {
	sc := bufio.NewScanner(strings.NewReader(content))
	if !sc.Scan() {
		return cpuSample{}, fmt.Errorf("empty /proc/stat")
	}
	// خط اول: "cpu  user nice system idle iowait irq softirq steal guest guest_nice"
	fields := strings.Fields(sc.Text())
	if len(fields) < 8 || fields[0] != "cpu" {
		return cpuSample{}, fmt.Errorf("unexpected /proc/stat format")
	}
	vals := make([]uint64, 8)
	for i := 0; i < 8; i++ {
		vals[i], _ = strconv.ParseUint(fields[i+1], 10, 64)
	}
	return cpuSample{vals[0], vals[1], vals[2], vals[3], vals[4], vals[5], vals[6], vals[7]}, nil
}

func readCPUSample() (cpuSample, error) {
	data, err := os.ReadFile("/proc/stat")
	if err != nil {
		return cpuSample{}, err
	}
	return parseCPUSample(string(data))
}

func cpuPercent(prev, cur cpuSample) float64 {
	totalDelta := float64(cur.total() - prev.total())
	if totalDelta <= 0 {
		return 0
	}
	idleDelta := float64(cur.idleAll() - prev.idleAll())
	pct := 100 * (totalDelta - idleDelta) / totalDelta
	if pct < 0 {
		pct = 0
	}
	if pct > 100 {
		pct = 100
	}
	return pct
}

/* ─────────────────────── نمونه‌گیریِ شبکه (نرخی) ─────────────────────── */

type netSample struct{ rxBytes, txBytes uint64 }

// جمعِ همه‌ی رابط‌ها به‌جز loopback — چون اسمِ رابطِ اصلی (eth0، ens3، …)
// بین سرورها فرق می‌کنه، جمع‌کردنِ همه‌ی رابط‌های واقعی ساده‌تر و پایدارتره
func parseNetSample(content string) netSample {
	var out netSample
	sc := bufio.NewScanner(strings.NewReader(content))
	lineNo := 0
	for sc.Scan() {
		lineNo++
		if lineNo <= 2 {
			continue // دو خط هدر
		}
		line := sc.Text()
		parts := strings.SplitN(line, ":", 2)
		if len(parts) != 2 {
			continue
		}
		iface := strings.TrimSpace(parts[0])
		if iface == "lo" {
			continue
		}
		fields := strings.Fields(parts[1])
		if len(fields) < 16 {
			continue
		}
		rx, _ := strconv.ParseUint(fields[0], 10, 64)
		tx, _ := strconv.ParseUint(fields[8], 10, 64)
		out.rxBytes += rx
		out.txBytes += tx
	}
	return out
}

func readNetSample() (netSample, error) {
	data, err := os.ReadFile("/proc/net/dev")
	if err != nil {
		return netSample{}, err
	}
	return parseNetSample(string(data)), nil
}

/* ─────────────────────── کشِ بینِ درخواست‌ها، برایِ محاسبه‌ی نرخ ─────────────────────── */

var (
	rateMu       sync.Mutex
	lastCPU      cpuSample
	lastNet      netSample
	lastSampleAt time.Time
	haveSample   bool
)

// rateSample — یک بار /proc رو می‌خونه و با آخرین نمونه‌ی ذخیره‌شده مقایسه
// می‌کنه تا CPU% و نرخِ شبکه رو بده. دفعه‌ی اولِ مطلق (سرویس تازه بالا اومده)،
// چون نمونه‌ی قبلی نداریم، یک بار با ۳۰۰ میلی‌ثانیه فاصله دوبار می‌خونه —
// فقط همون یک درخواستِ اول کمی کندتره، بقیه‌ی درخواست‌ها فوری‌ان.
func rateSample() (cpuPct float64, rxBps, txBps float64, err error) {
	rateMu.Lock()
	defer rateMu.Unlock()

	curCPU, err := readCPUSample()
	if err != nil {
		return 0, 0, 0, err
	}
	curNet, err := readNetSample()
	if err != nil {
		return 0, 0, 0, err
	}
	now := time.Now()

	if !haveSample {
		time.Sleep(300 * time.Millisecond)
		curCPU2, err2 := readCPUSample()
		curNet2, err3 := readNetSample()
		now2 := time.Now()
		if err2 == nil && err3 == nil {
			cpuPct = cpuPercent(curCPU, curCPU2)
			elapsed := now2.Sub(now).Seconds()
			if elapsed > 0 {
				rxBps = float64(curNet2.rxBytes-curNet.rxBytes) / elapsed
				txBps = float64(curNet2.txBytes-curNet.txBytes) / elapsed
			}
			curCPU, curNet, now = curCPU2, curNet2, now2
		}
		lastCPU, lastNet, lastSampleAt, haveSample = curCPU, curNet, now, true
		return cpuPct, rxBps, txBps, nil
	}

	elapsed := now.Sub(lastSampleAt).Seconds()
	cpuPct = cpuPercent(lastCPU, curCPU)
	if elapsed > 0 {
		rxBps = float64(curNet.rxBytes-lastNet.rxBytes) / elapsed
		txBps = float64(curNet.txBytes-lastNet.txBytes) / elapsed
	}
	lastCPU, lastNet, lastSampleAt = curCPU, curNet, now
	return cpuPct, rxBps, txBps, nil
}

/* ─────────────────────── RAM/Swap از /proc/meminfo ─────────────────────── */

func parseMemInfo(content string) map[string]uint64 {
	out := map[string]uint64{}
	sc := bufio.NewScanner(strings.NewReader(content))
	for sc.Scan() {
		parts := strings.SplitN(sc.Text(), ":", 2)
		if len(parts) != 2 {
			continue
		}
		key := strings.TrimSpace(parts[0])
		valFields := strings.Fields(parts[1]) // "  12345 kB" → ["12345","kB"]
		if len(valFields) == 0 {
			continue
		}
		v, _ := strconv.ParseUint(valFields[0], 10, 64)
		out[key] = v // همیشه کیلوبایت (واحدِ استانداردِ این فایل)
	}
	return out
}

func readMemInfo() (map[string]uint64, error) {
	data, err := os.ReadFile("/proc/meminfo")
	if err != nil {
		return nil, err
	}
	return parseMemInfo(string(data)), nil
}

/* ─────────────────────── لود و آپ‌تایم ─────────────────────── */

func parseLoadAvg(content string) (l1, l5, l15 float64, err error) {
	f := strings.Fields(content)
	if len(f) < 3 {
		err = fmt.Errorf("unexpected /proc/loadavg")
		return
	}
	l1, _ = strconv.ParseFloat(f[0], 64)
	l5, _ = strconv.ParseFloat(f[1], 64)
	l15, _ = strconv.ParseFloat(f[2], 64)
	return
}

func readLoadAvg() (l1, l5, l15 float64, err error) {
	data, err := os.ReadFile("/proc/loadavg")
	if err != nil {
		return
	}
	return parseLoadAvg(string(data))
}

func parseUptimeSeconds(content string) (float64, error) {
	f := strings.Fields(content)
	if len(f) < 1 {
		return 0, fmt.Errorf("unexpected /proc/uptime")
	}
	return strconv.ParseFloat(f[0], 64)
}

func readUptimeSeconds() (float64, error) {
	data, err := os.ReadFile("/proc/uptime")
	if err != nil {
		return 0, err
	}
	return parseUptimeSeconds(string(data))
}

func formatUptime(seconds float64) string {
	total := int64(seconds)
	days := total / 86400
	hours := (total % 86400) / 3600
	mins := (total % 3600) / 60
	if days > 0 {
		return fmt.Sprintf("%dر %dس %dد", days, hours, mins)
	}
	if hours > 0 {
		return fmt.Sprintf("%dس %dد", hours, mins)
	}
	return fmt.Sprintf("%dد", mins)
}

/* ─────────────────────── هندلر HTTP ─────────────────────── */

// Monitor — GET /go/api/system/monitor
// پورت جدید (معادلِ PHP نداره) — پشتِ auth عادی، ولی فقط id=1 اجازه‌ی
// دیدنِ اطلاعاتِ داخلیِ سرور رو داره (طبقِ همون قاعده‌ای که برایِ
// رصدِ امنیتی/لاگِ خطا در header.php هم استفاده شده: Number(user.id)===1).
func Monitor(db *sql.DB) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		u := core.UserOf(r.Context())
		if u.ID != 1 {
			core.WriteErr(w, http.StatusForbidden, "دسترسی غیرمجاز")
			return
		}

		cpuPct, rxBps, txBps, err := rateSample()
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطا در خواندن اطلاعات CPU/شبکه")
			return
		}

		mem, err := readMemInfo()
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطا در خواندن اطلاعات حافظه")
			return
		}
		memTotalKB := mem["MemTotal"]
		memAvailKB := mem["MemAvailable"]
		memUsedKB := uint64(0)
		if memTotalKB > memAvailKB {
			memUsedKB = memTotalKB - memAvailKB
		}
		memPct := 0.0
		if memTotalKB > 0 {
			memPct = 100 * float64(memUsedKB) / float64(memTotalKB)
		}
		swapTotalKB := mem["SwapTotal"]
		swapFreeKB := mem["SwapFree"]
		swapUsedKB := uint64(0)
		if swapTotalKB > swapFreeKB {
			swapUsedKB = swapTotalKB - swapFreeKB
		}
		swapPct := 0.0
		if swapTotalKB > 0 {
			swapPct = 100 * float64(swapUsedKB) / float64(swapTotalKB)
		}

		diskTotal, diskUsed, err := readDisk("/")
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطا در خواندن اطلاعات دیسک")
			return
		}
		diskPct := 0.0
		if diskTotal > 0 {
			diskPct = 100 * float64(diskUsed) / float64(diskTotal)
		}

		l1, l5, l15, err := readLoadAvg()
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطا در خواندن میانگین بار سیستم")
			return
		}

		uptimeSec, err := readUptimeSeconds()
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطا در خواندن آپ‌تایم")
			return
		}

		dbOK := db.Ping() == nil

		core.WriteJSON(w, http.StatusOK, map[string]any{
			"success": true,
			"cpu": map[string]any{
				"percent": round1(cpuPct),
			},
			"ram": map[string]any{
				"used_mb":  memUsedKB / 1024,
				"total_mb": memTotalKB / 1024,
				"percent":  round1(memPct),
			},
			"swap": map[string]any{
				"used_mb":  swapUsedKB / 1024,
				"total_mb": swapTotalKB / 1024,
				"percent":  round1(swapPct),
			},
			"disk": map[string]any{
				"used_gb":  round1(float64(diskUsed) / 1024 / 1024 / 1024),
				"total_gb": round1(float64(diskTotal) / 1024 / 1024 / 1024),
				"percent":  round1(diskPct),
			},
			"network": map[string]any{
				"rx_kbps": round1(rxBps / 1024),
				"tx_kbps": round1(txBps / 1024),
			},
			"load": map[string]any{
				"l1":  l1,
				"l5":  l5,
				"l15": l15,
			},
			"uptime_label": formatUptime(uptimeSec),
			"db_ok":        dbOK,
		})
	}
}

func round1(f float64) float64 {
	return float64(int64(f*10+0.5)) / 10
}
