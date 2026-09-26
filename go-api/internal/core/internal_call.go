package core

import (
	"bytes"
	"encoding/json"
	"log"
	"net/http"
	"time"
)

// NotifyNewDeviceAsync — تماس داخلی (fire-and-forget) به
// api/internal/notify-new-device.php، برای حالت نادر «دستگاه کاملا
// جدید + کاربر تأییدنشده» در ثبت ورود/خروج. چرا اینجا و نه پورت کامل
// Notification::create() در Go؟ نگاه کن به internal/attendance/
// admin_devices.go — همون مرز ازقبل‌تعیین‌شده: ارسال پیامک/نوتیف عمدا
// به Go پورت نمی‌شه، پیچیده و وابسته به سرویس پیامکه.
//
// در یک goroutine جدا اجرا می‌شه تا پاسخ اصلی ثبت ورود/خروج معطل این
// تماس جانبی نمونه؛ اگه شکست بخوره فقط لاگ می‌شه (managers یک اطلاع
// دیرتر از پنل خودشون می‌بینن، نه این‌که خود ثبت ورود/خروج خراب بشه).
func NotifyNewDeviceAsync(cfg Config, orgID, userID int64, ip string) {
	if cfg.BaseURL == "" || cfg.GoInternalSecret == "" {
		log.Printf("NotifyNewDeviceAsync: base_url/go_internal_secret تنظیم نشده — این نوتیف نادیده گرفته شد")
		return
	}
	go func() {
		body, _ := json.Marshal(map[string]any{
			"organization_id": orgID,
			"user_id":         userID,
			"ip":              ip,
		})
		req, err := http.NewRequest("POST", cfg.BaseURL+"/api/internal/notify-new-device.php", bytes.NewReader(body))
		if err != nil {
			log.Printf("NotifyNewDeviceAsync: ساخت درخواست ناموفق: %v", err)
			return
		}
		req.Header.Set("Content-Type", "application/json")
		req.Header.Set("Authorization", "Bearer "+cfg.GoInternalSecret)

		client := &http.Client{Timeout: 8 * time.Second}
		resp, err := client.Do(req)
		if err != nil {
			log.Printf("NotifyNewDeviceAsync: درخواست ناموفق: %v", err)
			return
		}
		defer resp.Body.Close()
		if resp.StatusCode != http.StatusOK {
			log.Printf("NotifyNewDeviceAsync: پاسخ غیرمنتظره: %d", resp.StatusCode)
		}
	}()
}
