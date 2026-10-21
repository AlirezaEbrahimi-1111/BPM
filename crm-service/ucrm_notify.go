package main

// اعلان‌های CRM جدید (ثبت، تأیید، رد، تغییر کارشناس).
//
// چرا از PHP؟ اعلانِ پروژه (اعلان داخل برنامه + پیامک + Push متریکس) همه در
// includes/Notification.php::create است و عمدا به Go پورت نمی‌شود — همان مرزی که
// go-api برای «دستگاه جدید» دارد (api/internal/notify-new-device.php).
// این‌جا Go بعد از commit فقط شناسهٔ رویدادها را به api/ucrm/notify-events.php
// می‌فرستد، با «توکن خود همان کاربر» (نه کلید جدا) — PHP خودش رویدادها را از
// دیتابیس می‌خواند، چک می‌کند مال همین کاربر و هنوز اعلان‌نشده باشند، و گیرنده‌ها
// را تعیین می‌کند. پس با این تماس نمی‌شود اعلان دلخواه ساخت.
//
// در goroutine جدا (fire-and-forget): اگر شکست بخورد فقط لاگ می‌شود و خود کار
// (ثبت/تأیید/رد) خراب نمی‌شود.

import (
	"bytes"
	"encoding/json"
	"log"
	"net/http"
	"strings"
	"time"
)

func (s *server) ucrmNotifyAsync(r *http.Request, eventIDs []int64) {
	ids := []int64{}
	for _, id := range eventIDs {
		if id > 0 {
			ids = append(ids, id)
		}
	}
	if len(ids) == 0 {
		return
	}
	authz := r.Header.Get("Authorization")
	base := strings.TrimRight(s.cfg.BaseURL, "/")
	go func() {
		body, _ := json.Marshal(map[string]any{"event_ids": ids})
		req, err := http.NewRequest("POST", base+"/api/ucrm/notify-events.php", bytes.NewReader(body))
		if err != nil {
			log.Printf("ucrmNotify: ساخت درخواست ناموفق: %v", err)
			return
		}
		req.Header.Set("Content-Type", "application/json")
		req.Header.Set("Authorization", authz)
		client := &http.Client{Timeout: 20 * time.Second}
		resp, err := client.Do(req)
		if err != nil {
			log.Printf("ucrmNotify: درخواست ناموفق (%s): %v", base, err)
			return
		}
		defer resp.Body.Close()
		if resp.StatusCode != http.StatusOK {
			log.Printf("ucrmNotify: پاسخ غیرمنتظره %d از %s", resp.StatusCode, base)
		}
	}()
}
