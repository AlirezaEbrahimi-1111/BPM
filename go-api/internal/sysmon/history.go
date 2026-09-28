// نمونه‌گیریِ دوره‌ای (برایِ نمودارهایِ ۱ساعت/۲۴ساعت/۷روزِ صفحه‌ی
// server-monitor.php) + endpoint خواندنِ همون تاریخچه.
package sysmon

import (
	"database/sql"
	"log"
	"net/http"
	"time"

	"bmp/go-api/internal/core"
)

// StartSampler — هر ۲ دقیقه یک نمونه‌ی خلاصه می‌گیره و توی
// system_metrics_history ذخیره می‌کنه؛ همون‌جا هم داده‌ی قدیمی‌تر از ۸ روز
// رو پاک می‌کنه (نیازی به cron جدا نیست، چون go-api خودش یک سرویسِ دائمیه).
// از main() به‌صورتِ go sysmon.StartSampler(db) صدا زده می‌شه — این تابع
// خودش بلاک نمی‌کنه، گوروتینِ داخلیش تا آخرِ عمرِ سرویس زنده می‌مونه.
func StartSampler(db *sql.DB) {
	go func() {
		sampleOnce(db)
		ticker := time.NewTicker(2 * time.Minute)
		defer ticker.Stop()
		for range ticker.C {
			sampleOnce(db)
		}
	}()
}

func sampleOnce(db *sql.DB) {
	s, err := collectSnapshot()
	if err != nil {
		log.Printf("sysmon sampler: collectSnapshot: %v", err)
		return
	}

	_, err = db.Exec(`
		INSERT INTO system_metrics_history
			(recorded_at, cpu_percent, ram_percent, disk_percent, net_rx_kbps, net_tx_kbps, load1)
		VALUES (NOW(), ?, ?, ?, ?, ?, ?)
	`, round1(s.cpuPercent), round1(s.memPercent), round1(s.diskPercent),
		round1(s.netRxBps/1024), round1(s.netTxBps/1024), s.load1)
	if err != nil {
		log.Printf("sysmon sampler: insert: %v", err)
	}

	if _, err := db.Exec("DELETE FROM system_metrics_history WHERE recorded_at < NOW() - INTERVAL 8 DAY"); err != nil {
		log.Printf("sysmon sampler: cleanup: %v", err)
	}
}

// rangeConfig — یک نگاشتِ ثابت و شناخته‌شده از رشته‌ی range به بازه/سایزِ سطل؛
// bucketSeconds مستقیم (نه از ورودیِ کاربر) توی کوئری میره، پس امنه
var rangeConfig = map[string]struct {
	since         time.Duration
	bucketSeconds int
}{
	"1h": {time.Hour, 120},
	"7d": {7 * 24 * time.Hour, 3600},
	// پیش‌فرض (از جمله "24h" و هرچیزِ ناشناخته‌ی دیگه) پایین‌تر است
}

// History — GET /go/api/system/monitor/history?range=1h|24h|7d
// فقط id=1، مثلِ Monitor. برایِ نرم‌بودنِ نمودار، ردیف‌های خام رو توی
// سطل‌هایِ زمانی (۲دقیقه/۱۰دقیقه/۱ساعت، بسته به بازه) میانگین می‌گیره —
// نه هزاران نقطه‌ی خام برایِ ۷ روز.
func History(db *sql.DB) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		u := core.UserOf(r.Context())
		if u.ID != 1 {
			core.WriteErr(w, http.StatusForbidden, "دسترسی غیرمجاز")
			return
		}

		rangeParam := r.URL.Query().Get("range")
		cfg, ok := rangeConfig[rangeParam]
		if !ok {
			rangeParam = "24h"
			cfg = struct {
				since         time.Duration
				bucketSeconds int
			}{24 * time.Hour, 600}
		}

		cutoff := time.Now().Add(-cfg.since).Format("2006-01-02 15:04:05")

		rows, err := db.Query(`
			SELECT
				FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(recorded_at) / ?) * ?) AS bucket,
				AVG(cpu_percent), AVG(ram_percent), AVG(disk_percent),
				AVG(net_rx_kbps), AVG(net_tx_kbps), AVG(load1)
			FROM system_metrics_history
			WHERE recorded_at >= ?
			GROUP BY bucket
			ORDER BY bucket
		`, cfg.bucketSeconds, cfg.bucketSeconds, cutoff)
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطا در خواندن تاریخچه")
			log.Printf("sysmon History query: %v", err)
			return
		}
		defer rows.Close()

		labels := []string{}
		cpuArr := []float64{}
		ramArr := []float64{}
		diskArr := []float64{}
		rxArr := []float64{}
		txArr := []float64{}
		loadArr := []float64{}

		for rows.Next() {
			// 🔒 اتصالِ دیتابیس عمدا parseTime نداره (core/db.go) — ستونِ
			// DATETIME باید رشته اسکن بشه، نه time.Time، وگرنه خطایِ
			// «unsupported Scan» می‌ده (دقیقا همینو با تستِ زنده گرفتم)
			var bucketStr string
			var cpu, ram, disk, rx, tx, load float64
			if err := rows.Scan(&bucketStr, &cpu, &ram, &disk, &rx, &tx, &load); err != nil {
				core.WriteErr(w, http.StatusInternalServerError, "خطا در خواندن تاریخچه")
				log.Printf("sysmon History scan: %v", err)
				return
			}
			// 🔒 عمدا فرمتِ ظاهریِ فارسی/شمسی این‌جا ساخته نمی‌شه — همون رشته‌ی
			// خامِ MySQL برمی‌گرده و فرانت‌اند با همون TimeSync ای که همه‌جایِ
			// پروژه استفاده می‌شه (assets/js/time-sync.js) فرمتش می‌کنه؛ یک
			// منطقِ تبدیلِ شمسی، نه یکی این‌جا هم به Go دوباره‌نویسی بشه
			labels = append(labels, bucketStr)
			cpuArr = append(cpuArr, round1(cpu))
			ramArr = append(ramArr, round1(ram))
			diskArr = append(diskArr, round1(disk))
			rxArr = append(rxArr, round1(rx))
			txArr = append(txArr, round1(tx))
			loadArr = append(loadArr, round1(load))
		}

		core.WriteJSON(w, http.StatusOK, map[string]any{
			"success":     true,
			"range":       rangeParam,
			"labels":      labels,
			"cpu":         cpuArr,
			"ram":         ramArr,
			"disk":        diskArr,
			"net_rx_kbps": rxArr,
			"net_tx_kbps": txArr,
			"load1":       loadArr,
		})
	}
}
