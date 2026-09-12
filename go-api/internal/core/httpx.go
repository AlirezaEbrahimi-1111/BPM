package core

import (
	"encoding/json"
	"log"
	"net/http"
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

// LogRequests یک میدل‌ورِ لاگِ ساده (متد، مسیر، زمان).
func LogRequests(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		start := time.Now()
		next.ServeHTTP(w, r)
		log.Printf("%s %s (%s)", r.Method, r.URL.Path, time.Since(start).Round(time.Millisecond))
	})
}
