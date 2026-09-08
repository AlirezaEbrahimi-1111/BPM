// Package core — هستهٔ مشترکِ مهاجرتِ PHP → Go.
//
// این پکیج «کِرنِل» است: پیکربندی، اتصالِ دیتابیس، احراز هویتِ JWT (دقیقاً
// برابرِ includes/auth.php)، منطقِ دسترسی (برابرِ includes/permissions.php) و
// تقویمِ شمسی. هر ماژولِ بعدیِ Go (گزارش‌ها، اعلان‌ها، …) این را import می‌کند.
//
// اصلِ عدم‌شکنندگی: نسخهٔ PHP تا پایانِ مهاجرت «مرجع» است؛ این پکیج فقط باید
// «عیناً» همان رفتار را بدهد و با تستِ تطبیقی قفل می‌شود.
package core

import (
	"encoding/json"
	"log"
	"os"
)

// Config — همان مقادیرِ config/config.php که این سرویس نیاز دارد.
type Config struct {
	Port      string `json:"port"`
	DBHost    string `json:"db_host"`
	DBPort    string `json:"db_port"`
	DBName    string `json:"db_name"`
	DBUser    string `json:"db_user"`
	DBPass    string `json:"db_pass"`
	JWTSecret string `json:"jwt_secret"` // باید دقیقاً برابرِ jwt_secret در config/config.php باشد
}

// LoadConfig ابتدا فایلِ JSON (پیش‌فرض config.json، یا مسیرِ GOAPI_CONFIG) را
// می‌خواند، سپس متغیرهای محیطیِ GOAPI_* هر مقدارِ موجود را بازنویسی می‌کنند
// (مناسب برای اجرا زیرِ systemd روی سرور، بدونِ فایلِ رمز روی دیسک).
func LoadConfig() Config {
	c := Config{Port: "8091", DBHost: "127.0.0.1", DBPort: "3306"}

	path := os.Getenv("GOAPI_CONFIG")
	if path == "" {
		path = "config.json"
	}
	if b, err := os.ReadFile(path); err == nil {
		if err := json.Unmarshal(b, &c); err != nil {
			log.Fatalf("config: خواندنِ %s ناموفق بود: %v", path, err)
		}
	}

	set := func(key string, dst *string) {
		if v := os.Getenv(key); v != "" {
			*dst = v
		}
	}
	set("GOAPI_PORT", &c.Port)
	set("GOAPI_DB_HOST", &c.DBHost)
	set("GOAPI_DB_PORT", &c.DBPort)
	set("GOAPI_DB_NAME", &c.DBName)
	set("GOAPI_DB_USER", &c.DBUser)
	set("GOAPI_DB_PASS", &c.DBPass)
	set("GOAPI_JWT_SECRET", &c.JWTSecret)

	if c.JWTSecret == "" || c.DBName == "" || c.DBUser == "" {
		log.Fatal("config: jwt_secret و db_name و db_user الزامی‌اند (در config.json یا متغیرهای محیطی GOAPI_*)")
	}
	return c
}
