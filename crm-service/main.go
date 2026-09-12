// crm-service — سرویسِ CRM با Go.
//
// فاز ۰: فقط دو endpoint دارد:
//
//	GET /crm/api/health   → بدونِ احراز هویت، فقط سلامتِ سرویس و دیتابیس
//	GET /crm/api/me       → با توکنِ JWTِ همان اپِ PHP، اطلاعاتِ کاربرِ جاری
//
// این سرویس:
//   - همان دیتابیسِ MariaDBِ اپِ اصلی را می‌خواند (فعلاً فقط SELECT روی users).
//   - همان توکنِ JWT (HS256) را با همان jwt_secret اعتبارسنجی می‌کند — منطق
//     دقیقاً برابرِ includes/auth.php (چکِ امضا + exp + is_active + token_version).
//   - فقط روی 127.0.0.1 گوش می‌دهد؛ از بیرون فقط از طریقِ پروکسیِ /crm/ در Apache.
//
// اجرای محلی:  go run .   (بعد از go mod tidy و ساختِ config.json)
package main

import (
	"context"
	"database/sql"
	"encoding/json"
	"fmt"
	"log"
	"net/http"
	"os"
	"strconv"
	"strings"
	"time"

	_ "github.com/go-sql-driver/mysql"
	"github.com/golang-jwt/jwt/v5"
)

// ──────────────── پیکربندی ────────────────

type Config struct {
	Port      string `json:"port"`
	DBHost    string `json:"db_host"`
	DBPort    string `json:"db_port"`
	DBName    string `json:"db_name"`
	DBUser    string `json:"db_user"`
	DBPass    string `json:"db_pass"`
	JWTSecret string `json:"jwt_secret"` // باید دقیقاً برابرِ jwt_secret در config/config.php باشد
}

// loadConfig ابتدا config.json (یا مسیرِ CRM_CONFIG) را می‌خواند،
// سپس متغیرهای محیطی (CRM_*) هر مقدارِ موجود را بازنویسی می‌کنند —
// برای اجرا زیرِ systemd روی سرور مناسب است.
func loadConfig() Config {
	c := Config{Port: "8090", DBHost: "127.0.0.1", DBPort: "3306"}

	path := os.Getenv("CRM_CONFIG")
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
	set("CRM_PORT", &c.Port)
	set("CRM_DB_HOST", &c.DBHost)
	set("CRM_DB_PORT", &c.DBPort)
	set("CRM_DB_NAME", &c.DBName)
	set("CRM_DB_USER", &c.DBUser)
	set("CRM_DB_PASS", &c.DBPass)
	set("CRM_JWT_SECRET", &c.JWTSecret)

	if c.JWTSecret == "" || c.DBName == "" || c.DBUser == "" {
		log.Fatal("config: jwt_secret و db_name و db_user الزامی‌اند (در config.json یا متغیرهای محیطی)")
	}
	return c
}

// ──────────────── دیتابیس ────────────────

func openDB(c Config) *sql.DB {
	dsn := fmt.Sprintf(
		"%s:%s@tcp(%s:%s)/%s?parseTime=true&charset=utf8mb4&loc=Local",
		c.DBUser, c.DBPass, c.DBHost, c.DBPort, c.DBName,
	)
	db, err := sql.Open("mysql", dsn)
	if err != nil {
		log.Fatalf("db: %v", err)
	}
	db.SetMaxOpenConns(20)
	db.SetMaxIdleConns(5)
	db.SetConnMaxLifetime(time.Hour)

	if err := db.Ping(); err != nil {
		log.Fatalf("db: اتصال ناموفق — %v", err)
	}
	return db
}

// ──────────────── احراز هویت (همان JWTِ اپِ PHP) ────────────────

type authUser struct {
	ID    int64
	OrgID int64
}

type ctxKey string

const userKey ctxKey = "crmUser"

func withUser(ctx context.Context, u authUser) context.Context {
	return context.WithValue(ctx, userKey, u)
}

func userOf(ctx context.Context) authUser {
	u, _ := ctx.Value(userKey).(authUser)
	return u
}

// authMiddleware دقیقاً مثلِ includes/auth.php::validateToken عمل می‌کند:
// امضای HS256 با jwt_secret، انقضا، و بعد چکِ is_active و token_version در دیتابیس.
func (s *server) authMiddleware(next http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		raw := strings.TrimSpace(strings.TrimPrefix(r.Header.Get("Authorization"), "Bearer "))
		if raw == "" {
			writeErr(w, http.StatusUnauthorized, "توکن ارسال نشده است")
			return
		}

		tok, err := jwt.Parse(
			raw,
			func(t *jwt.Token) (any, error) { return []byte(s.cfg.JWTSecret), nil },
			jwt.WithValidMethods([]string{"HS256"}),
			jwt.WithExpirationRequired(),
		)
		if err != nil || !tok.Valid {
			writeErr(w, http.StatusUnauthorized, "توکن نامعتبر است")
			return
		}
		claims, ok := tok.Claims.(jwt.MapClaims)
		if !ok {
			writeErr(w, http.StatusUnauthorized, "توکن نامعتبر است")
			return
		}

		uid := toInt64(claims["user_id"])
		tv := toInt64(claims["tv"])
		if uid == 0 {
			writeErr(w, http.StatusUnauthorized, "توکن نامعتبر است")
			return
		}

		var isActive int
		var tokenVersion int64
		err = s.db.QueryRow(
			"SELECT is_active, token_version FROM users WHERE id = ?", uid,
		).Scan(&isActive, &tokenVersion)
		if err != nil || isActive != 1 || tokenVersion != tv {
			writeErr(w, http.StatusUnauthorized, "نشست منقضی شده — دوباره وارد شوید")
			return
		}

		u := authUser{ID: uid, OrgID: toInt64(claims["organization_id"])}
		next(w, r.WithContext(withUser(r.Context(), u)))
	}
}

// toInt64 چون کلایم‌های JSON در Go معمولاً float64 دیکود می‌شوند.
func toInt64(v any) int64 {
	switch n := v.(type) {
	case float64:
		return int64(n)
	case int64:
		return n
	case json.Number:
		i, _ := n.Int64()
		return i
	case string:
		i, _ := strconv.ParseInt(n, 10, 64)
		return i
	}
	return 0
}

// ──────────────── پاسخ‌های JSON ────────────────

func writeJSON(w http.ResponseWriter, status int, v any) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")
	w.WriteHeader(status)
	_ = json.NewEncoder(w).Encode(v)
}

func writeErr(w http.ResponseWriter, status int, msg string) {
	writeJSON(w, status, map[string]any{"success": false, "message": msg})
}

// writeDBErr: خطای واقعیِ SQL را لاگ می‌کند (journalctl -u crm-service) و اگر
// تشخیص داد جدول/ستونی روی این سرور ساخته نشده، به‌جایِ «خطای دیتابیس»یِ کور،
// پیامی می‌دهد که مستقیم می‌گوید مهاجرت اجرا نشده — برای دیباگِ سریع‌تر.
func writeDBErr(w http.ResponseWriter, op string, err error) {
	msg := err.Error()
	log.Printf("%s: %v", op, err)
	if strings.Contains(msg, "Error 1146") || strings.Contains(msg, "doesn't exist") {
		writeErr(w, http.StatusInternalServerError, "جدولِ این فیچر روی این سرور ساخته نشده — مهاجرتِ پایگاه‌داده اجرا نشده (migrate.php up)")
		return
	}
	if strings.Contains(msg, "Error 1054") || strings.Contains(msg, "Unknown column") {
		writeErr(w, http.StatusInternalServerError, "ستونی در دیتابیس کم است — مهاجرتِ پایگاه‌داده اجرا نشده (migrate.php up)")
		return
	}
	writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
}

// ──────────────── سرور و هندلرها ────────────────

type server struct {
	cfg                 Config
	db                  *sql.DB
	officialWarehouseID int64 // انبارِ «فاکتور رسمی» — کسر/افزایشِ موجودیِ فاکتور از این‌جا
}

func (s *server) handleHealth(w http.ResponseWriter, r *http.Request) {
	dbOK := s.db.Ping() == nil
	status := http.StatusOK
	if !dbOK {
		status = http.StatusServiceUnavailable
	}
	writeJSON(w, status, map[string]any{
		"ok":      dbOK,
		"service": "crm-service",
		"db":      dbOK,
		"time":    time.Now().Format(time.RFC3339),
	})
}

func (s *server) handleMe(w http.ResponseWriter, r *http.Request) {
	u := userOf(r.Context())

	var firstName, lastName, section sql.NullString
	err := s.db.QueryRow(
		"SELECT first_name, last_name, activity_section FROM users WHERE id = ?", u.ID,
	).Scan(&firstName, &lastName, &section)
	if err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		log.Printf("handleMe: %v", err)
		return
	}

	writeJSON(w, http.StatusOK, map[string]any{
		"user_id":          u.ID,
		"organization_id":  u.OrgID,
		"name":             strings.TrimSpace(firstName.String + " " + lastName.String),
		"activity_section": section.String,
	})
}

func logRequests(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		start := time.Now()
		next.ServeHTTP(w, r)
		log.Printf("%s %s (%s)", r.Method, r.URL.Path, time.Since(start).Round(time.Millisecond))
	})
}

func main() {
	cfg := loadConfig()
	db := openDB(cfg)
	defer db.Close()

	s := &server{cfg: cfg, db: db}

	// شناسه‌ی انبارِ «فاکتور رسمی» را یک‌بار می‌خوانیم (seedِ مهاجرت).
	if err := db.QueryRow("SELECT id FROM inv_warehouses WHERE kind = 'official' LIMIT 1").
		Scan(&s.officialWarehouseID); err != nil {
		log.Fatalf("انبارِ official یافت نشد — آیا مهاجرتِ inv_* اجرا شده؟ (%v)", err)
	}

	// نوشتن در ماژولِ فاکتور: هر کسی که «دیدنِ ماژول» را دارد (معادلِ
	// includes/crm_access.php) — یعنی دسترسیِ دیدن = دسترسیِ نوشتن.
	auth := s.authMiddleware
	write := func(h http.HandlerFunc) http.HandlerFunc { return auth(s.requireCRMAccess(h)) }

	mux := http.NewServeMux()
	mux.HandleFunc("GET /crm/api/health", s.handleHealth)
	mux.HandleFunc("GET /crm/api/me", auth(s.handleMe))

	// ── کالا ──
	mux.HandleFunc("GET /crm/api/inv/products", auth(s.listProducts))
	mux.HandleFunc("POST /crm/api/inv/products", write(s.createProduct))
	mux.HandleFunc("PUT /crm/api/inv/products/{id}", write(s.updateProduct))
	mux.HandleFunc("DELETE /crm/api/inv/products/{id}", write(s.deleteProduct))
	mux.HandleFunc("POST /crm/api/inv/products/import", write(s.importProducts))

	// ── مشتری (مشترک با CRM) ──
	mux.HandleFunc("GET /crm/api/customers", auth(s.listCustomers))
	mux.HandleFunc("POST /crm/api/customers", write(s.createCustomer))
	mux.HandleFunc("PUT /crm/api/customers/{id}", write(s.updateCustomer))
	mux.HandleFunc("DELETE /crm/api/customers/{id}", write(s.deleteCustomer))
	mux.HandleFunc("POST /crm/api/customers/import", write(s.importCustomers))

	// ── تنظیماتِ فاکتور + سربرگِ فروشنده ──
	mux.HandleFunc("GET /crm/api/inv/settings", auth(s.handleGetSettings))
	mux.HandleFunc("PUT /crm/api/inv/settings", write(s.handlePutSettings))

	// ── فاکتورِ رسمی ──
	mux.HandleFunc("GET /crm/api/inv/invoices", auth(s.listInvoices))
	mux.HandleFunc("GET /crm/api/inv/invoices/{id}", auth(s.getInvoice))
	mux.HandleFunc("POST /crm/api/inv/invoices", write(s.createInvoice))
	mux.HandleFunc("PUT /crm/api/inv/invoices/{id}", write(s.updateInvoice))
	mux.HandleFunc("POST /crm/api/inv/invoices/{id}/approve", write(s.approveInvoice))
	mux.HandleFunc("POST /crm/api/inv/invoices/{id}/cancel", write(s.cancelInvoice))
	mux.HandleFunc("POST /crm/api/inv/invoices/{id}/to-official", write(s.convertToOfficial))
	mux.HandleFunc("DELETE /crm/api/inv/invoices/{id}", write(s.deleteInvoice))

	// ── همکار + سهمِ سودِ ماهانه (شمسی) + گزارشِ همکاران ──
	// گزارش و ویرایشِ وضعیت‌ها: مدیران/سوپروایزر/حسابداری (requireReportAccess).
	report := func(h http.HandlerFunc) http.HandlerFunc { return auth(s.requireReportAccess(h)) }
	mux.HandleFunc("GET /crm/api/inv/partners", auth(s.listPartners))
	mux.HandleFunc("POST /crm/api/inv/partners", report(s.createPartner))
	mux.HandleFunc("PUT /crm/api/inv/partners/{id}", report(s.updatePartner))
	mux.HandleFunc("DELETE /crm/api/inv/partners/{id}", report(s.deletePartner))
	mux.HandleFunc("GET /crm/api/inv/partners/{id}/shares", report(s.listPartnerShares))
	mux.HandleFunc("PUT /crm/api/inv/partners/{id}/shares", report(s.setPartnerShare))
	mux.HandleFunc("GET /crm/api/inv/partner-share", write(s.getPartnerShare))
	mux.HandleFunc("GET /crm/api/inv/partner-report", report(s.partnerReport))
	mux.HandleFunc("PATCH /crm/api/inv/invoices/{id}/partner-status", report(s.setInvoicePartnerStatus))

	// ── تأمین‌کننده ──
	mux.HandleFunc("GET /crm/api/inv/suppliers", auth(s.listSuppliers))
	mux.HandleFunc("POST /crm/api/inv/suppliers", write(s.createSupplier))
	mux.HandleFunc("PUT /crm/api/inv/suppliers/{id}", write(s.updateSupplier))
	mux.HandleFunc("DELETE /crm/api/inv/suppliers/{id}", write(s.deleteSupplier))

	// ── فاکتورِ خرید ──
	mux.HandleFunc("GET /crm/api/inv/purchases", auth(s.listPurchases))
	mux.HandleFunc("GET /crm/api/inv/purchases/{id}", auth(s.getPurchase))
	mux.HandleFunc("POST /crm/api/inv/purchases", write(s.createPurchase))
	mux.HandleFunc("PUT /crm/api/inv/purchases/{id}", write(s.updatePurchase))
	mux.HandleFunc("POST /crm/api/inv/purchases/{id}/confirm", write(s.confirmPurchase))
	mux.HandleFunc("POST /crm/api/inv/purchases/{id}/cancel", write(s.cancelPurchase))
	mux.HandleFunc("DELETE /crm/api/inv/purchases/{id}", write(s.deletePurchase))

	addr := "127.0.0.1:" + cfg.Port
	srv := &http.Server{
		Addr:         addr,
		Handler:      logRequests(mux),
		ReadTimeout:  15 * time.Second,
		WriteTimeout: 30 * time.Second,
		IdleTimeout:  60 * time.Second,
	}

	log.Printf("crm-service روی http://%s — فقط از طریقِ پروکسیِ /crm/ در Apache", addr)
	log.Fatal(srv.ListenAndServe())
}
