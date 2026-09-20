package core

import (
	"encoding/json"
	"log"
	"net"
	"net/http"
	"strings"
	"time"
)

// ReadJSON بدنه‌ی JSONِ درخواست را در v می‌خواند — معادلِ
// json_decode(file_get_contents('php://input'), true) در PHP.
func ReadJSON(r *http.Request, v any) error {
	defer r.Body.Close()
	return json.NewDecoder(r.Body).Decode(v)
}

// WriteJSON پاسخِ JSON با هدر و status می‌نویسد.
func WriteJSON(w http.ResponseWriter, status int, v any) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")
	w.WriteHeader(status)
	_ = json.NewEncoder(w).Encode(v)
}

// WriteErr قالبِ خطای یکسانِ اپ: {"success":false,"message":...}
func WriteErr(w http.ResponseWriter, status int, msg string) {
	WriteJSON(w, status, map[string]any{"success": false, "message": msg})
}

// ClientIP — IPِ واقعیِ کلاینت، معادلِ $_SERVER['REMOTE_ADDR'] در PHP.
// چونِ /go/api/ پشتِ ProxyPass اپاچی می‌آد (نگاه کن به
// go-api/deploy/apache-go-api-proxy.conf)، r.RemoteAddr همیشه 127.0.0.1
// (خودِ اپاچی) رو نشون می‌ده؛ IPِ واقعی رو mod_proxy_http به‌صورتِ
// پیش‌فرض توی X-Forwarded-For می‌ذاره. چون Go فقط رویِ 127.0.0.1 گوش
// می‌ده (بندِ main.go)، این هدر همیشه از همون اپاچیِ محلی و قابلِ‌اعتماده،
// نه از یک کلاینتِ بیرونیِ جعل‌کننده.
func ClientIP(r *http.Request) string {
	if xff := r.Header.Get("X-Forwarded-For"); xff != "" {
		if i := strings.IndexByte(xff, ','); i != -1 {
			return strings.TrimSpace(xff[:i])
		}
		return strings.TrimSpace(xff)
	}
	if host, _, err := net.SplitHostPort(r.RemoteAddr); err == nil {
		return host
	}
	return r.RemoteAddr
}

// LogRequests یک میدل‌ورِ لاگِ ساده (متد، مسیر، زمان).
func LogRequests(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		start := time.Now()
		next.ServeHTTP(w, r)
		log.Printf("%s %s (%s)", r.Method, r.URL.Path, time.Since(start).Round(time.Millisecond))
	})
}
