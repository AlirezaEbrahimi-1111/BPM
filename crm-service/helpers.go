package main

import (
	"encoding/json"
	"net/http"
	"strconv"
	"strings"
)

// ──────────────── ورودیِ JSON ────────────────

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
	q := strings.TrimSpace(r.URL.Query().Get("q"))
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
// ستون‌های بولیِ داده‌شده روی users برایش ۱ باشد. برای مسیرهای نوشتنی.
func (s *server) requireFlags(cols []string, next http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		u := userOf(r.Context())
		// نام ستون‌ها از کدِ خودمان می‌آید، نه از ورودیِ کاربر — امن برای درج در کوئری.
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

// requireCRMAccess: نوشتن در ماژولِ فاکتور برای هر کسی که «دیدنِ ماژول» را دارد —
// دقیقاً همان مجموعه‌ی crmModuleAllowed در includes/crm_access.php (سوپرادمین
// id 1/19، شماره‌موبایل‌هایِ EXTRA_PHONES، یا عضوِ فعالِ تیمِ حسابداریِ سازمانِ ۱).
// طبقِ تصمیمِ صریح: «دیدنِ منو = دسترسیِ کاملِ CRUD»، بدونِ محدودیتِ ریزتر —
// فلگ‌هایِ is_create_official_invoice/is_sales_manager دیگر شرطِ لازم نیستند
// (قبلاً کسانی که فقط از راهِ EXTRA_PHONES می‌دیدند، منو را داشتند ولی هر
// نوشتنی ۴۰۳ می‌گرفت؛ همین ناهماهنگی گزارش شد).
func (s *server) requireCRMAccess(next http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		u := userOf(r.Context())
		if u.ID == 1 || u.ID == 19 {
			next(w, r)
			return
		}
		var ok int
		err := s.db.QueryRow(`
			SELECT 1 FROM users u
			WHERE u.id = ? AND u.is_active = 1
			  AND (
			        u.phone IN ('09927949376')
			     OR (u.organization_id = 1 AND (
			            u.activity_section = 'accounting'
			         OR u.activity_unit = 'AC'
			         OR EXISTS (SELECT 1 FROM user_activity_sections uas
			                    WHERE uas.user_id = u.id AND uas.section_key = 'accounting')
			         OR EXISTS (SELECT 1 FROM user_activity_units uau
			                    WHERE uau.user_id = u.id AND uau.activity_unit = 'AC')
			        ))
			  )
			LIMIT 1`, u.ID).Scan(&ok)
		if err == nil && ok == 1 {
			next(w, r)
			return
		}
		writeErr(w, http.StatusForbidden, "برای این کار مجوز ندارید")
	}
}

// requireReportAccess: دیدنِ «گزارشِ همکاران» برای مدیران، سوپروایزرها و تیمِ
// حسابداری. یعنی هر کسی که requireCRMAccess را دارد، به‌علاوهٔ نقش‌های
// سازمانی (supervisor/admin/manager/management) و is_supervisor = 1.
func (s *server) requireReportAccess(next http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		u := userOf(r.Context())
		if u.ID == 1 || u.ID == 19 {
			next(w, r)
			return
		}
		var ok int
		err := s.db.QueryRow(`
			SELECT 1 FROM users u
			WHERE u.id = ? AND u.is_active = 1
			  AND (
			        u.role IN ('supervisor','admin','manager','management')
			     OR COALESCE(u.is_supervisor, 0) = 1
			     OR u.is_create_official_invoice = 1
			     OR u.is_sales_manager = 1
			     OR (u.organization_id = 1 AND (
			            u.activity_section = 'accounting'
			         OR u.activity_unit = 'AC'
			         OR EXISTS (SELECT 1 FROM user_activity_sections uas
			                    WHERE uas.user_id = u.id AND uas.section_key = 'accounting')
			         OR EXISTS (SELECT 1 FROM user_activity_units uau
			                    WHERE uau.user_id = u.id AND uau.activity_unit = 'AC')
			        ))
			  )
			LIMIT 1`, u.ID).Scan(&ok)
		if err == nil && ok == 1 {
			next(w, r)
			return
		}
		writeErr(w, http.StatusForbidden, "دسترسی به این گزارش ندارید")
	}
}

// ──────────────── ابزارِ کوچک ────────────────

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

// customerType: «حقیقی»→individual، «حقوقی»→legal، مقادیرِ انگلیسی هم پذیرفته می‌شوند.
func customerType(v any) string {
	s := strings.ToLower(strings.TrimSpace(toStr(v)))
	switch s {
	case "individual", "حقیقی", "شخص", "person":
		return "individual"
	default:
		return "legal"
	}
}
