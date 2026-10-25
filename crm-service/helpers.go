package main

import (
	"encoding/json"
	"net/http"
	"strconv"
	"strings"
)

// ──────────────── ورودی JSON ────────────────

// decodeJSON بدنهٔ درخواست را در dst می‌ریزد؛ حجم را هم محدود می‌کند.
func decodeJSON(w http.ResponseWriter, r *http.Request, dst any, maxBytes int64) bool {
	r.Body = http.MaxBytesReader(w, r.Body, maxBytes)
	dec := json.NewDecoder(r.Body)
	dec.DisallowUnknownFields()
	if err := dec.Decode(dst); err != nil {
		writeErr(w, http.StatusBadRequest, "بدنهٔ درخواست نامعتبر است: "+err.Error())
		return false
	}
	return true
}

// ──────────────── صفحه‌بندی ────────────────

type pageParams struct {
	Q      string
	Limit  int
	Offset int
	Page   int
	Per    int
}

func parsePage(r *http.Request) pageParams {
	q := faLetters(strings.TrimSpace(r.URL.Query().Get("q"))) // «ي/ك» عربیِ چسبانده‌شده هم پیدا کند
	page, _ := strconv.Atoi(r.URL.Query().Get("page"))
	per, _ := strconv.Atoi(r.URL.Query().Get("per"))
	if page < 1 {
		page = 1
	}
	if per < 1 || per > 200 {
		per = 50
	}
	return pageParams{Q: q, Limit: per, Offset: (page - 1) * per, Page: page, Per: per}
}

// ──────────────── دسترسی ────────────────

// requireFlags یک هندلر را فقط برای کاربری اجازه می‌دهد که «حداقل یکی» از
// ستون‌های بولی داده‌شده روی users برایش ۱ باشد. برای مسیرهای نوشتنی.
func (s *server) requireFlags(cols []string, next http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		u := userOf(r.Context())
		// نام ستون‌ها از کد خودمان می‌آید، نه از ورودی کاربر — امن برای درج در کوئری.
		sel := make([]string, len(cols))
		for i, c := range cols {
			sel[i] = "`" + c + "`"
		}
		row := s.db.QueryRow("SELECT "+strings.Join(sel, ",")+" FROM users WHERE id = ?", u.ID)
		vals := make([]any, len(cols))
		ptrs := make([]any, len(cols))
		for i := range vals {
			ptrs[i] = &vals[i]
		}
		if err := row.Scan(ptrs...); err != nil {
			writeErr(w, http.StatusForbidden, "دسترسی غیرمجاز")
			return
		}
		for _, v := range vals {
			if toInt64(v) == 1 {
				next(w, r)
				return
			}
		}
		writeErr(w, http.StatusForbidden, "برای این کار مجوز ندارید")
	}
}

// requireCRMAccess: نوشتن در ماژول فاکتور برای هر کسی که «دیدن ماژول» را دارد —
// دقیقا همان مجموعه‌ی crmModuleAllowed در includes/crm_access.php (سوپرادمین
// id 1/19، شماره‌موبایل‌های EXTRA_PHONES، یا عضو فعال تیم حسابداری سازمان ۱).
// طبق تصمیم صریح: «دیدن منو = دسترسی کامل CRUD»، بدون محدودیت ریزتر —
// فلگ‌های is_create_official_invoice/is_sales_manager دیگر شرط لازم نیستند
// (قبلا کسانی که فقط از راه EXTRA_PHONES می‌دیدند، منو را داشتند ولی هر
// نوشتنی ۴۰۳ می‌گرفت؛ همین ناهماهنگی گزارش شد).
func (s *server) requireCRMAccess(next http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		if s.hasCRMAccess(userOf(r.Context()).ID) {
			next(w, r)
			return
		}
		writeErr(w, http.StatusForbidden, "برای این کار مجوز ندارید")
	}
}

// crmSections: واحدهایی (سازمان ۱) که دسترسی کامل ماژول فاکتور رسمی دارند —
// 'l_accounting' = «حسابداری رسمی». ثابتِ کامپایل‌شده (نه ورودی کاربر) پس درج
// مستقیم در SQL امن است. ⚠️ هم‌راستا با crmModuleSections در includes/crm_access.php.
const crmSections = `'accounting','l_accounting'`

// hasCRMAccess: شرط requireCRMAccess به‌صورت بله/خیر (تا قابل ترکیب باشد).
func (s *server) hasCRMAccess(uid int64) bool {
	if superAdminIDs[uid] {
		return true
	}
	var ok int
	err := s.db.QueryRow(`
		SELECT 1 FROM users u
		WHERE u.id = ? AND u.is_active = 1
		  AND (
		        u.phone IN ('09927949376')
		     OR (u.organization_id = 1 AND (
		            u.activity_section IN (`+crmSections+`)
		         OR u.activity_unit = 'AC'
		         OR EXISTS (SELECT 1 FROM user_activity_sections uas
		                    WHERE uas.user_id = u.id AND uas.section_key IN (`+crmSections+`))
		         OR EXISTS (SELECT 1 FROM user_activity_units uau
		                    WHERE uau.user_id = u.id AND uau.activity_unit = 'AC')
		        ))
		  )
		LIMIT 1`, uid).Scan(&ok)
	return err == nil && ok == 1
}

// requireReportAccess: دیدن «گزارش همکاران» برای مدیران، سوپروایزرها و تیم
// حسابداری. یعنی هر کسی که requireCRMAccess را دارد، به‌علاوهٔ نقش‌های
// سازمانی (supervisor/admin/manager/management) و is_supervisor = 1.
func (s *server) requireReportAccess(next http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		if s.hasReportAccess(userOf(r.Context()).ID) {
			next(w, r)
			return
		}
		writeErr(w, http.StatusForbidden, "دسترسی به این گزارش ندارید")
	}
}

// hasReportAccess: شرط requireReportAccess به‌صورت بله/خیر.
func (s *server) hasReportAccess(uid int64) bool {
	if superAdminIDs[uid] {
		return true
	}
	// 🔒 فقط سازمان ۱ (شرکت اصلی). گزارش همکاران برای هیچ سازمان دیگری
	// نمایش داده نمی‌شود. هم‌راستا با crmReportAllowed در includes/crm_access.php.
	var ok int
	err := s.db.QueryRow(`
		SELECT 1 FROM users u
		WHERE u.id = ? AND u.is_active = 1
		  AND u.organization_id = 1
		  AND (
		        u.role IN ('supervisor','admin','manager','management')
		     OR COALESCE(u.is_supervisor, 0) = 1
		     OR u.is_create_official_invoice = 1
		     OR u.is_sales_manager = 1
		     OR u.activity_section IN (`+crmSections+`)
		     OR u.activity_unit = 'AC'
		     OR EXISTS (SELECT 1 FROM user_activity_sections uas
		                WHERE uas.user_id = u.id AND uas.section_key IN (`+crmSections+`))
		     OR EXISTS (SELECT 1 FROM user_activity_units uau
		                WHERE uau.user_id = u.id AND uau.activity_unit = 'AC')
		  )
		LIMIT 1`, uid).Scan(&ok)
	return err == nil && ok == 1
}

// legacyImportUserIDs: کاربرانی جز مدیر کل که به «ورود فاکتورهای قدیمی» دسترسی دارند
// (درخواست ۱۴۰۵/۰۸/۰۲: ۱۵۴ = رضا بادپر، سازمان ۱). هم‌قدم با pages/inv-import-legacy.php.
var legacyImportUserIDs = map[int64]bool{154: true}

// superAdminIDs — هم‌راستا با getSuperAdminIds() در includes/permissions.php:
// ۱ مدیر کل، ۱۹ رضا فضایلی، ۱۲۹ زهرا بادپر (۱۹ و ۱۲۹ عمدا یکسان).
var superAdminIDs = map[int64]bool{1: true, 19: true, 129: true}

// requireLegacyImport: مدیر کل + legacyImportUserIDs
func (s *server) requireLegacyImport(next http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		if uid := userOf(r.Context()).ID; superAdminIDs[uid] || legacyImportUserIDs[uid] {
			next(w, r)
			return
		}
		writeErr(w, http.StatusForbidden, "به بخش «ورود فاکتورهای قدیمی» دسترسی ندارید")
	}
}

// requireCRMOrReportAccess: هر کسی که یکی از دو دسترسی بالا را دارد — برای
// کارهایی که هم از «فهرست فاکتورها» (تیم حسابداری) و هم از «گزارش همکاران»
// (مدیران/سوپروایزرها) انجام می‌شوند، مثل درصد اختصاصی سهم همکار.
// (requireReportAccess به‌تنهایی کافی نیست: شماره‌های EXTRA_PHONES را ندارد.)
func (s *server) requireCRMOrReportAccess(next http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		uid := userOf(r.Context()).ID
		if s.hasCRMAccess(uid) || s.hasReportAccess(uid) {
			next(w, r)
			return
		}
		writeErr(w, http.StatusForbidden, "برای این کار مجوز ندارید")
	}
}

// ──────────────── ابزار کوچک ────────────────

// nullableID: شناسه‌ی مثبت را همان‌طور برمی‌گرداند، ۰ و منفی را NULL می‌کند.
func nullableID(v int64) any {
	if v > 0 {
		return v
	}
	return nil
}

func idParam(r *http.Request) int64 {
	i, _ := strconv.ParseInt(r.PathValue("id"), 10, 64)
	return i
}

func trimStr(v any) string { return strings.TrimSpace(toStr(v)) }

func toStr(v any) string {
	switch x := v.(type) {
	case string:
		return x
	case nil:
		return ""
	case float64:
		return strconv.FormatFloat(x, 'f', -1, 64)
	case bool:
		if x {
			return "1"
		}
		return "0"
	default:
		return ""
	}
}

// truthy: "بله"/"بلی"/"yes"/"true"/"1" → true
func truthy(v any) bool {
	s := strings.ToLower(strings.TrimSpace(toStr(v)))
	switch s {
	case "1", "بله", "بلی", "yes", "y", "true", "on", "معاف":
		return true
	}
	return false
}

// customerType: «حقیقی»→individual، «حقوقی»→legal، مقادیر انگلیسی هم پذیرفته می‌شوند.
func customerType(v any) string {
	s := strings.ToLower(strings.TrimSpace(toStr(v)))
	switch s {
	case "individual", "حقیقی", "شخص", "person":
		return "individual"
	default:
		return "legal"
	}
}

// faLetters: «ي/ى» و «ك» عربی → «ی» و «ک» فارسی (درخواست ۱۴۰۵/۰۸/۰۲). متن کپی‌شده از
// شایگان این حروف عربی را دارد؛ ظاهرشان یکی است ولی جستجوی «کیبورد» نام «كيبورد» را
// پیدا نمی‌کرد. روی نام‌ها و شرح ردیف‌ها هنگام ذخیره زده می‌شود. (یکسان‌سازی کامل‌تر
// برای مقایسهٔ تکراری‌ها: normName در dedup.go)
var faLetterReplacer = strings.NewReplacer("ي", "ی", "ى", "ی", "ك", "ک")

func faLetters(s string) string { return faLetterReplacer.Replace(s) }
