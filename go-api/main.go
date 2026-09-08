// go-api — بازنویسیِ تدریجیِ لایهٔ api/ اپِ PHP به Go (الگوی Strangler Fig).
//
// فاز ۱ (همین): فقط کِرنِل + دو endpointِ اثباتِ کار:
//
//	GET /go/api/health   → بدونِ احراز هویت؛ سلامتِ سرویس و دیتابیس
//	GET /go/api/me        → با همان JWTِ اپِ PHP؛ کاربر + اجازه‌هایش
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

	"bmp/go-api/internal/core"
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
