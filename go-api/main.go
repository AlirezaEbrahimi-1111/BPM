// go-api — بازنویسیِ تدریجیِ لایهٔ api/ اپِ PHP به Go (الگوی Strangler Fig).
//
// endpointها تا این‌جا:
//
//	GET    /go/api/health           → بدونِ احراز هویت؛ سلامتِ سرویس و دیتابیس
//	GET    /go/api/me               → با همان JWTِ اپِ PHP؛ کاربر + اجازه‌هایش
//	GET    /go/api/reports/stats    → پورتِ api/reports/stats.php
//	GET    /go/api/reports/today    → پورتِ api/reports/today.php
//	GET    /go/api/reports/history  → پورتِ api/reports/history.php
//	GET    /go/api/reports/list     → پورتِ api/reports/list.php
//	GET    /go/api/reports/detail   → پورتِ api/reports/detail.php
//	GET    /go/api/reports/search   → پورتِ api/reports/search.php
//	POST   /go/api/reports/save     → پورتِ api/reports/save.php
//	POST   /go/api/reports/submit   → پورتِ api/reports/submit.php
//	DELETE|POST /go/api/reports/delete   → پورتِ api/reports/delete.php
//	POST   /go/api/reports/generate → پورتِ api/reports/generate.php
//	GET    /go/api/attendance/today-status → پورتِ api/attendance/today-status.php
//	GET    /go/api/attendance/absent-today → پورتِ api/attendance/absent-today.php
//	GET    /go/api/notifications/list      → پورتِ api/notifications/list.php
//	GET    /go/api/notifications/new       → پورتِ api/notifications/new.php
//	POST   /go/api/notifications/mark-read → پورتِ api/notifications/mark-read.php
//	POST   /go/api/notifications/mark-all-read → پورتِ api/notifications/mark-all-read.php
//	POST   /go/api/notifications/delete    → پورتِ api/notifications/delete.php
//	GET    /go/api/announcements/list      → پورتِ api/announcements/list.php
//	GET    /go/api/tickets/list            → پورتِ api/tickets/list.php
//	POST   /go/api/tickets/mark-all-read   → پورتِ api/tickets/mark-all-read.php
//	GET|POST /go/api/attendance/allowed-ips → پورتِ api/attendance/allowed-ips.php
//	GET|POST /go/api/attendance/devices     → پورتِ جزئیِ api/attendance/devices.php (بدونِ approve/reject)
//	GET|POST /go/api/attendance/denied-log  → پورتِ api/attendance/denied-log.php
//	GET    /go/api/tickets/detail          → پورتِ api/tickets/detail.php
//
// عمداً پورت نشده: api/attendance/register.php (ثبتِ ورود/خروج) و هر منطقِ
// محاسبه‌ی کسری/حقوق — ریسکِ مالی/عملیاتی‌شان بالاست؛ نیازمندِ تصمیمِ
// جداگانه پیش از هر پورتی.
//
// روالِ افزودنِ endpoint: پورت در internal/<module>/، ثبت در main.go، سپس
// «تستِ سایه‌ای» (SHADOW-TEST.md) — خروجیِ Go و PHP روی یک دیتابیس مقایسه شود —
// و تنها بعد از تأیید، یک خطِ ProxyPass در Apache اضافه می‌شود.
//
// این سرویس:
//   - همان MariaDBِ اپِ اصلی را می‌خواند (فعلاً فقط SELECT روی users).
//   - همان JWT (HS256, همان jwt_secret) را دقیقاً مثلِ includes/auth.php می‌سنجد.
//   - فقط روی 127.0.0.1 گوش می‌دهد؛ از بیرون فقط از طریقِ ProxyPass /go/api/ در Apache.
//   - کاملاً مستقل از PHP و از crm-service است؛ اگر بمیرد فقط /go/api/* می‌افتد.
//
// اجرای محلی:  go run .   (پس از `go mod tidy` و ساختِ config.json از نمونه)
package main

import (
	"database/sql"
	"log"
	"net/http"
	"strings"
	"time"

	"bmp/go-api/internal/announcements"
	"bmp/go-api/internal/attendance"
	"bmp/go-api/internal/core"
	"bmp/go-api/internal/notifications"
	"bmp/go-api/internal/reports"
	"bmp/go-api/internal/tickets"
)

type server struct {
	cfg  core.Config
	db   *sql.DB
	auth func(http.HandlerFunc) http.HandlerFunc
}

func (s *server) handleHealth(w http.ResponseWriter, r *http.Request) {
	dbOK := s.db.Ping() == nil
	status := http.StatusOK
	if !dbOK {
		status = http.StatusServiceUnavailable
	}
	core.WriteJSON(w, status, map[string]any{
		"ok":      dbOK,
		"service": "go-api",
		"db":      dbOK,
		"time":    time.Now().Format(time.RFC3339),
	})
}

// GET /go/api/me — کاربرِ جاری + فهرستِ اجازه‌هایش (برای اثباتِ کِرنِل).
func (s *server) handleMe(w http.ResponseWriter, r *http.Request) {
	u := core.UserOf(r.Context())

	pu, err := core.LoadUser(s.db, u.ID)
	if err != nil {
		core.WriteErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		log.Printf("handleMe LoadUser: %v", err)
		return
	}
	if pu == nil {
		core.WriteErr(w, http.StatusUnauthorized, "کاربر یافت نشد یا غیرفعال است")
		return
	}

	var firstName, lastName sql.NullString
	_ = s.db.QueryRow("SELECT first_name, last_name FROM users WHERE id = ?", u.ID).
		Scan(&firstName, &lastName)

	// چند اجازهٔ نمونه — فقط برای تأییدِ این‌که پورتِ permissions.php کار می‌کند.
	sample := []string{"create_task", "view_reports", "manage_users", "view_payroll", "create_workflow"}
	perms := map[string]bool{}
	for _, p := range sample {
		perms[p] = core.HasPermission(pu, p)
	}

	core.WriteJSON(w, http.StatusOK, map[string]any{
		"success":            true,
		"user_id":            pu.ID,
		"organization_id":    pu.OrganizationID,
		"role":               pu.Role,
		"activity_section":   pu.ActivitySection,
		"is_super_admin":     core.IsSuperAdmin(pu),
		"name":               strings.TrimSpace(firstName.String + " " + lastName.String),
		"permissions_sample": perms,
	})
}

func main() {
	cfg := core.LoadConfig()
	db := core.OpenDB(cfg)
	defer db.Close()

	s := &server{
		cfg:  cfg,
		db:   db,
		auth: core.AuthMiddleware(db, cfg.JWTSecret),
	}

	mux := http.NewServeMux()
	mux.HandleFunc("GET /go/api/health", s.handleHealth)
	mux.HandleFunc("GET /go/api/me", s.auth(s.handleMe))

	// ── ماژولِ گزارش‌ها (پورتِ api/reports/*) ──
	mux.HandleFunc("GET /go/api/reports/stats", s.auth(reports.Stats(s.db)))
	mux.HandleFunc("GET /go/api/reports/today", s.auth(reports.Today(s.db)))
	mux.HandleFunc("GET /go/api/reports/history", s.auth(reports.History(s.db)))
	mux.HandleFunc("GET /go/api/reports/list", s.auth(reports.List(s.db)))
	mux.HandleFunc("GET /go/api/reports/detail", s.auth(reports.Detail(s.db)))
	mux.HandleFunc("GET /go/api/reports/search", s.auth(reports.Search(s.db)))
	mux.HandleFunc("POST /go/api/reports/save", s.auth(reports.Save(s.db)))
	mux.HandleFunc("POST /go/api/reports/submit", s.auth(reports.Submit(s.db)))
	mux.HandleFunc("DELETE /go/api/reports/delete", s.auth(reports.Delete(s.db)))
	mux.HandleFunc("POST /go/api/reports/delete", s.auth(reports.Delete(s.db)))
	mux.HandleFunc("POST /go/api/reports/generate", s.auth(reports.Generate(s.db)))

	// ── ماژولِ حضور و غیاب — فقط بخشِ خواندنی (پورتِ api/attendance/*) ──
	mux.HandleFunc("GET /go/api/attendance/today-status", s.auth(attendance.TodayStatus(s.db)))
	mux.HandleFunc("GET /go/api/attendance/absent-today", s.auth(attendance.AbsentToday(s.db)))
	// این سه‌تا در PHP هم روی متد شاخه نمی‌زنن (فقط GET را جدا می‌کنن، بقیه
	// را به‌عنوانِ نوشتنِ JSON با یک action می‌خونن)، پس بدونِ پیشوندِ متد
	// ثبت می‌شن تا همون انعطاف حفظ بشه.
	mux.HandleFunc("/go/api/attendance/allowed-ips", s.auth(attendance.AllowedIPs(s.db)))
	mux.HandleFunc("/go/api/attendance/devices", s.auth(attendance.Devices(s.db)))
	mux.HandleFunc("/go/api/attendance/denied-log", s.auth(attendance.DeniedLog(s.db)))

	// ── ماژولِ اعلان‌ها (پورتِ api/notifications/*) ──
	mux.HandleFunc("GET /go/api/notifications/list", s.auth(notifications.List(s.db)))
	mux.HandleFunc("GET /go/api/notifications/new", s.auth(notifications.New(s.db)))
	mux.HandleFunc("POST /go/api/notifications/mark-read", s.auth(notifications.MarkRead(s.db)))
	mux.HandleFunc("POST /go/api/notifications/mark-all-read", s.auth(notifications.MarkAllRead(s.db)))
	mux.HandleFunc("POST /go/api/notifications/delete", s.auth(notifications.Delete(s.db)))

	// ── ماژولِ اطلاعیه‌ها — فقط بخشِ خواندنی (پورتِ api/announcements/list.php) ──
	mux.HandleFunc("GET /go/api/announcements/list", s.auth(announcements.List(s.db)))

	// ── ماژولِ تیکت‌ها — فقط لیست + علامت‌گذاریِ همه‌خوانده‌شده ──
	mux.HandleFunc("GET /go/api/tickets/list", s.auth(tickets.List(s.db)))
	mux.HandleFunc("POST /go/api/tickets/mark-all-read", s.auth(tickets.MarkAllRead(s.db)))
	mux.HandleFunc("GET /go/api/tickets/detail", s.auth(tickets.Detail(s.db)))

	addr := "127.0.0.1:" + cfg.Port
	srv := &http.Server{
		Addr:         addr,
		Handler:      core.LogRequests(mux),
		ReadTimeout:  15 * time.Second,
		WriteTimeout: 30 * time.Second,
		IdleTimeout:  60 * time.Second,
	}

	log.Printf("go-api روی http://%s — فقط از طریقِ پروکسیِ /go/api/ در Apache", addr)
	log.Fatal(srv.ListenAndServe())
}
