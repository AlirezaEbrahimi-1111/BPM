package main

// ═══════════════════════════════════════════════════════════════════
//  جلوگیری از مشتری / تأمین‌کنندهٔ تکراری (درخواست ۱۴۰۵/۰۸/۰۱)
//
//  هر بخش جدا: حسابداری رسمی (crm_customers، inv_suppliers) و حسابداری غیررسمی
//  (ucrm_customers) — یک شرکت می‌تواند در هر دو باشد، ولی در هر کدام فقط یک بار.
//
//  «تکراری» = نام یکسان بعد از یکسان‌سازی (normName) یا کد/شناسهٔ ملی یکسان.
//  یکسان‌سازی: «ي/ی»، «ك/ک»، «ة/ۀ→ه»، نیم‌فاصله و فاصلهٔ اضافه، کشیده (ـ)، رقم فارسی/عربی/لاتین،
//  خط تیره/نقطه/ویرگول/پرانتز. «شرکت X» و «X» عمدا یکی حساب نمی‌شوند.
//
//  ویرایش رکورد قدیمی فقط وقتی رد می‌شود که نام/شناسه «عوض شده» و به یک رکورد دیگر
//  برخورد کند — پس تکراری‌های قدیمی همچنان قابل ویرایش‌اند تا با «ادغام» یکی شوند.
//  پاسخ تکراری: 409 + {message, duplicate:{id, name}} — صفحه می‌تواند همان را انتخاب کند.
// ═══════════════════════════════════════════════════════════════════

import (
	"net/http"
	"strconv"
	"strings"
	"unicode"
)

var nameFold = strings.NewReplacer(
	"ي", "ی", "ى", "ی", "ئ", "ی", "ك", "ک", "ة", "ه", "ۀ", "ه", "أ", "ا", "إ", "ا", "ٱ", "ا",
	"‌", " ", "‍", "", "‏", "", "‎", "", "ـ", "",
	"۰", "0", "۱", "1", "۲", "2", "۳", "3", "۴", "4", "۵", "5", "۶", "6", "۷", "7", "۸", "8", "۹", "9",
	"٠", "0", "١", "1", "٢", "2", "٣", "3", "٤", "4", "٥", "5", "٦", "6", "٧", "7", "٨", "8", "٩", "9",
)

// normName: کلید مقایسهٔ نام (نه برای ذخیره — نام همان‌طور که کاربر نوشته ذخیره می‌شود).
func normName(s string) string {
	s = nameFold.Replace(s)
	var b strings.Builder
	space := false
	for _, r := range s {
		// اعراب/حرکات (ً ٌ ٍ َ ُ ِ ّ ْ …) حذف
		if unicode.Is(unicode.Mn, r) {
			continue
		}
		if unicode.IsSpace(r) || strings.ContainsRune("-_.،,()[]/\\«»\"'٫", r) {
			space = true
			continue
		}
		if space && b.Len() > 0 {
			b.WriteByte(' ')
		}
		space = false
		b.WriteRune(unicode.ToLower(r))
	}
	return b.String()
}

// جدول‌هایی که این بررسی را دارند — نام جدول از کد خودمان است، نه ورودی کاربر.
const (
	dupOfficialCustomers   = "crm_customers"
	dupOfficialSuppliers   = "inv_suppliers"
	dupUnofficialCustomers = "ucrm_customers"
)

type dupHit struct {
	ID   int64  `json:"id"`
	Name string `json:"name"`
}

// findDupName: رکورد دیگری (حذف‌نشده، همان سازمان) با همان نام یکسان‌سازی‌شده، یا nil.
func (s *server) findDupName(table string, orgID, exclude int64, name string) *dupHit {
	key := normName(name)
	if key == "" {
		return nil
	}
	rows, err := s.db.Query("SELECT id, name FROM "+table+" WHERE organization_id = ? AND is_deleted = 0 AND id <> ?", orgID, exclude)
	if err != nil {
		return nil
	}
	defer rows.Close()
	for rows.Next() {
		var h dupHit
		if rows.Scan(&h.ID, &h.Name) == nil && normName(h.Name) == key {
			return &h
		}
	}
	return nil
}

// findDupNationalID: رکورد دیگری با همان کد/شناسهٔ ملی (فقط رقم)، یا nil.
func (s *server) findDupNationalID(table string, orgID, exclude int64, nid string) *dupHit {
	nid = digitsOnly(nid)
	if nid == "" {
		return nil
	}
	var h dupHit
	err := s.db.QueryRow("SELECT id, name FROM "+table+" WHERE organization_id = ? AND is_deleted = 0 AND id <> ? AND national_id = ? LIMIT 1",
		orgID, exclude, nid).Scan(&h.ID, &h.Name)
	if err != nil {
		return nil
	}
	return &h
}

// writeDup: پاسخ استاندارد تکراری
func writeDup(w http.ResponseWriter, msg string, h *dupHit) {
	writeJSON(w, http.StatusConflict, map[string]any{"success": false, "message": msg, "duplicate": h})
}

// checkCustomerDup: بررسی کامل برای ثبت/ویرایش. kind = «مشتری» یا «تأمین‌کننده».
// oldName/oldNID = مقدار فعلی رکورد در ویرایش (تا رکورد قدیمیِ تکراری قفل نشود)؛ در ثبت خالی.
// true = تکراری بود و پاسخ داده شد.
func (s *server) checkCustomerDup(w http.ResponseWriter, table, kind string, orgID, exclude int64,
	name, nid, oldName, oldNID string, editing bool) bool {
	if !editing || normName(name) != normName(oldName) {
		if h := s.findDupName(table, orgID, exclude, name); h != nil {
			writeDup(w, kind+" «"+h.Name+"» قبلاً ثبت شده است.", h)
			return true
		}
	}
	if !editing || digitsOnly(nid) != digitsOnly(oldNID) {
		if h := s.findDupNationalID(table, orgID, exclude, nid); h != nil {
			writeDup(w, "این کد/شناسهٔ ملی قبلاً برای "+kind+" «"+h.Name+"» ثبت شده است.", h)
			return true
		}
	}
	return false
}

// dupIndex: برای بارگذاری اکسل — همهٔ نام‌ها/شناسه‌های موجود یک‌جا (به‌جای یک کوئری برای هر ردیف).
type dupIndex struct {
	names map[string]string // کلید نام → نام اصلی
	nids  map[string]string // شناسه → نام
}

func (s *server) loadDupIndex(table string, orgID int64) dupIndex {
	ix := dupIndex{names: map[string]string{}, nids: map[string]string{}}
	rows, err := s.db.Query("SELECT name, COALESCE(national_id,'') FROM "+table+" WHERE organization_id = ? AND is_deleted = 0", orgID)
	if err != nil {
		return ix
	}
	defer rows.Close()
	for rows.Next() {
		var n, nid string
		if rows.Scan(&n, &nid) == nil {
			ix.names[normName(n)] = n
			if d := digitsOnly(nid); d != "" {
				ix.nids[d] = n
			}
		}
	}
	return ix
}

// check: پیام خطای فارسی اگر ردیف تکراری است (با موجودی‌ها یا ردیف‌های قبلی همین فایل)، وگرنه "" و ثبت در فهرست.
func (ix dupIndex) check(kind, name, nid string) string {
	if k := normName(name); k != "" {
		if old, ok := ix.names[k]; ok {
			return kind + " «" + old + "» قبلاً ثبت شده (یا در همین فایل تکرار شده) است."
		}
	}
	if d := digitsOnly(nid); d != "" {
		if old, ok := ix.nids[d]; ok {
			return "کد/شناسهٔ ملی تکراری — قبلاً برای «" + old + "» ثبت شده (یا در همین فایل تکرار شده) است."
		}
	}
	if k := normName(name); k != "" {
		ix.names[k] = name
	}
	if d := digitsOnly(nid); d != "" {
		ix.nids[d] = name
	}
	return ""
}

// GET /crm/api/customers/duplicates — گروه‌های تکراری موجود مشتریان حسابداری رسمی
// (برای یکی کردن با «حذف و انتقال فاکتورها»). نام یکسان‌سازی‌شده یا شناسهٔ یکسان.
func (s *server) customerDuplicates(w http.ResponseWriter, r *http.Request) {
	u := userOf(r.Context())
	rows, err := s.db.Query(`SELECT id, name, COALESCE(national_id,'') FROM crm_customers
		WHERE organization_id = ? AND is_deleted = 0 ORDER BY id`, u.OrgID)
	if err != nil {
		writeDBErr(w, "customerDuplicates", err)
		return
	}
	defer rows.Close()
	type item struct {
		ID         int64  `json:"id"`
		Name       string `json:"name"`
		NationalID string `json:"national_id"`
	}
	byName := map[string][]item{}
	byNID := map[string][]item{}
	order := []string{}
	for rows.Next() {
		var it item
		if rows.Scan(&it.ID, &it.Name, &it.NationalID) != nil {
			continue
		}
		k := normName(it.Name)
		if _, ok := byName[k]; !ok {
			order = append(order, "n:"+k)
		}
		byName[k] = append(byName[k], it)
		if d := digitsOnly(it.NationalID); d != "" {
			if _, ok := byNID[d]; !ok {
				order = append(order, "i:"+d)
			}
			byNID[d] = append(byNID[d], it)
		}
	}
	groups := []map[string]any{}
	seen := map[string]bool{} // یک گروه دوبار (هم با نام هم با شناسه) نیاید
	for _, o := range order {
		var list []item
		reason := "نام یکسان"
		if strings.HasPrefix(o, "n:") {
			list = byName[o[2:]]
		} else {
			list = byNID[o[2:]]
			reason = "کد/شناسهٔ ملی یکسان"
		}
		if len(list) < 2 {
			continue
		}
		ids := ""
		for _, it := range list {
			ids += "," + itoa64(it.ID)
		}
		if seen[ids] {
			continue
		}
		seen[ids] = true
		groups = append(groups, map[string]any{"reason": reason, "items": list})
	}
	writeJSON(w, http.StatusOK, map[string]any{"groups": groups})
}

func itoa64(v int64) string { return strconv.FormatInt(v, 10) }
