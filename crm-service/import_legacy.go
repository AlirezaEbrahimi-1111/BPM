package main

import (
	"database/sql"
	"fmt"
	"math"
	"net/http"
	"regexp"
	"strings"
	"time"
)

// ═══════════════════════════════════════════════════════════════════
//  ورود فاکتورهای قدیمی (پیش از BPM) از اکسل — فقط مدیر کل
// ───────────────────────────────────────────────────────────────────
//  فایل اکسل در مرورگر خوانده و دسته‌بندی می‌شود (pages/inv-import-legacy.php)؛
//  این‌جا فقط داده‌ی تمیزشده می‌رسد و روی دیتابیس واقعی بررسی/ثبت می‌شود.
//
//  POST /crm/api/inv/import-legacy           {dry_run, batch, seq_year, invoices:[…]}
//      dry_run=true  → فقط گزارش (هیچ نوشتنی)
//      dry_run=false → همان بررسی؛ اگر بی‌خطا بود همه در یک تراکنش ثبت می‌شوند
//  POST /crm/api/inv/import-legacy/rollback  {}  → حذف همه‌ی فاکتورهای source='import'
//
//  تصمیم‌های کاربر (۱۴۰۵/۰۷/۲۵):
//   • اقلام «شرح آزاد»اند (product_id = NULL): هیچ حرکت انبار و هیچ کالای تازه‌ای
//     در کاتالوگ ساخته نمی‌شود (insertItems عادی برای شرح آزاد کالا می‌سازد؛ این‌جا نه).
//   • مبلغ و مالیات عینا از اکسل؛ دوباره حساب نمی‌شوند (فقط سازگاری‌شان چک می‌شود).
//   • kind: official/proforma = تأییدشده؛ cancelled = باطل با اقلام؛
//     reserve = باطلِ بدون اقلام (شماره‌ای که در اکسل خالی بود — فقط رزرو).
//   • همکار ثبت نمی‌شود؛ کد مؤدیان اگر بود «ثبت‌شده».
// ═══════════════════════════════════════════════════════════════════

const legacyExternalSource = "excel_import"

type legacyCustomer struct {
	Name         string `json:"name"`
	NationalID   string `json:"national_id"`
	EconomicCode string `json:"economic_code"`
	PostalCode   string `json:"postal_code"`
	Province     string `json:"province"`
	City         string `json:"city"`
	Address      string `json:"address"`
	Phone        string `json:"phone"`
}

type legacyItem struct {
	Title     string  `json:"title"`
	Qty       float64 `json:"qty"`
	UnitPrice int64   `json:"unit_price"`
	Discount  int64   `json:"discount"`
	TaxAmount int64   `json:"tax_amount"`
	LineTotal int64   `json:"line_total"`
}

type legacyInvoice struct {
	No          int            `json:"no"`
	Kind        string         `json:"kind"`       // official | proforma | cancelled | reserve
	IssueDate   string         `json:"issue_date"` // شمسی YYYY/MM/DD (برای reserve می‌تواند خالی باشد)
	Customer    legacyCustomer `json:"customer"`
	Note        string         `json:"note"`
	Settled     bool           `json:"settled"`
	MoadianCode string         `json:"moadian_code"`
	Items       []legacyItem   `json:"items"`
	Total       int64          `json:"total"` // جمع کل برگه‌ی فاکتور (برای چک سازگاری)
}

type legacyImportIn struct {
	DryRun   bool            `json:"dry_run"`
	Batch    string          `json:"batch"`
	SeqYear  int             `json:"seq_year"`
	Invoices []legacyInvoice `json:"invoices"`
}

type legacyIssue struct {
	No  int    `json:"no"`
	Msg string `json:"message"`
}

type legacyNewCustomer struct {
	Name         string `json:"name"`
	NationalID   string `json:"national_id"`
	EconomicCode string `json:"economic_code"`
	Invoices     []int  `json:"invoices"`
}

// ── نرمال‌سازی برای تطبیق مشتری ──
var nonDigit = regexp.MustCompile(`\D`)
var moadianRe = regexp.MustCompile(`^[A-Z0-9]{10,60}$`)

func faToEnDigits(s string) string {
	return strings.Map(func(r rune) rune {
		switch {
		case r >= '۰' && r <= '۹':
			return '0' + (r - '۰')
		case r >= '٠' && r <= '٩':
			return '0' + (r - '٠')
		}
		return r
	}, s)
}

func onlyDigits(s string) string { return nonDigit.ReplaceAllString(faToEnDigits(s), "") }

// نام برای مقایسه: ی/ک عربی → فارسی، بدون نیم‌فاصله/فاصله/نقطه‌گذاری، حروف کوچک
func nameKey(s string) string {
	s = strings.NewReplacer("ي", "ی", "ك", "ک", "‌", "", "ـ", "").Replace(strings.ToLower(s))
	var b strings.Builder
	for _, r := range s {
		if r == ' ' || r == '.' || r == '-' || r == '_' || r == '(' || r == ')' || r == '،' || r == ',' {
			continue
		}
		b.WriteRune(r)
	}
	return b.String()
}

func parseJalaliSlash(s string) (time.Time, int, bool) {
	p := strings.Split(onlyDigitsKeepSlash(s), "/")
	if len(p) != 3 {
		return time.Time{}, 0, false
	}
	var y, m, d int
	if _, err := fmt.Sscanf(p[0]+" "+p[1]+" "+p[2], "%d %d %d", &y, &m, &d); err != nil {
		return time.Time{}, 0, false
	}
	t, ok := jalaliToGregorian(y, m, d)
	return t, y, ok
}

func onlyDigitsKeepSlash(s string) string {
	s = faToEnDigits(strings.TrimSpace(s))
	var b strings.Builder
	for _, r := range s {
		if (r >= '0' && r <= '9') || r == '/' {
			b.WriteRune(r)
		}
	}
	return b.String()
}

// POST /crm/api/inv/import-legacy
func (s *server) importLegacy(w http.ResponseWriter, r *http.Request) {
	u := userOf(r.Context())
	var in legacyImportIn
	if !decodeJSON(w, r, &in, 16<<20) {
		return
	}
	in.Batch = strings.TrimSpace(in.Batch)
	if len([]rune(in.Batch)) > 80 {
		in.Batch = string([]rune(in.Batch)[:80])
	}
	if in.SeqYear < 1390 || in.SeqYear > 1500 {
		writeErr(w, http.StatusBadRequest, "سال شماره‌گذاری نامعتبر است")
		return
	}
	if len(in.Invoices) == 0 || len(in.Invoices) > 3000 {
		writeErr(w, http.StatusBadRequest, "فهرست فاکتورها خالی یا بیش از حد بزرگ است")
		return
	}

	var errs []legacyIssue
	addErr := func(no int, f string, a ...any) { errs = append(errs, legacyIssue{no, fmt.Sprintf(f, a...)}) }

	// ── ۱) بررسی‌های بدون دیتابیس ──
	seen := map[int]bool{}
	dates := map[int]sql.NullString{}
	type tot struct{ sub, disc, tax, total int64 }
	totals := map[int]tot{}
	counts := map[string]int{}
	var itemCount int
	var grand int64
	for i := range in.Invoices {
		inv := &in.Invoices[i]
		inv.Customer.Name = strings.TrimSpace(inv.Customer.Name)
		inv.MoadianCode = strings.ToUpper(strings.TrimSpace(inv.MoadianCode))
		if inv.No < 1 {
			addErr(inv.No, "شماره‌ی فاکتور نامعتبر است")
			continue
		}
		if seen[inv.No] {
			addErr(inv.No, "این شماره در فایل تکراری است")
		}
		seen[inv.No] = true
		switch inv.Kind {
		case "official", "proforma", "cancelled", "reserve":
		default:
			addErr(inv.No, "نوع نامعتبر: %s", inv.Kind)
			continue
		}
		counts[inv.Kind]++

		// تاریخ: باید شمسی معتبر و در همان سال شماره‌گذاری باشد (رزرو می‌تواند بی‌تاریخ باشد)
		if strings.TrimSpace(inv.IssueDate) == "" {
			if inv.Kind != "reserve" {
				addErr(inv.No, "تاریخ فاکتور خالی است")
			}
		} else if t, jy, ok := parseJalaliSlash(inv.IssueDate); !ok {
			addErr(inv.No, "تاریخ نامعتبر است: %s", inv.IssueDate)
		} else if jy != in.SeqYear {
			addErr(inv.No, "تاریخ %s در سال %d نیست", inv.IssueDate, in.SeqYear)
		} else {
			dates[inv.No] = sql.NullString{String: t.Format("2006-01-02"), Valid: true}
		}

		if inv.Kind != "reserve" && inv.Customer.Name == "" {
			addErr(inv.No, "نام مشتری خالی است")
		}
		if inv.MoadianCode != "" && !moadianRe.MatchString(inv.MoadianCode) {
			addErr(inv.No, "کد مؤدیان نامعتبر است: %s", inv.MoadianCode)
		}
		if inv.Kind == "reserve" {
			if len(inv.Items) > 0 {
				addErr(inv.No, "شماره‌ی رزرو نباید قلم داشته باشد")
			}
			continue
		}
		if len(inv.Items) == 0 {
			addErr(inv.No, "فاکتور هیچ قلمی ندارد")
			continue
		}

		// اقلام: مبلغ‌ها عینا از اکسل، فقط سازگاری‌شان چک می‌شود (±۱ ریال گرد کردن)
		var t tot
		for k, it := range inv.Items {
			if strings.TrimSpace(it.Title) == "" {
				addErr(inv.No, "قلم %d شرح ندارد", k+1)
			}
			if it.Qty < 0 || it.UnitPrice < 0 || it.Discount < 0 || it.TaxAmount < 0 {
				addErr(inv.No, "قلم %d مقدار منفی دارد", k+1)
			}
			gross := int64(math.Round(it.Qty * float64(it.UnitPrice)))
			if d := gross - it.Discount + it.TaxAmount - it.LineTotal; d > 1 || d < -1 {
				addErr(inv.No, "قلم %d: تعداد×قیمت−تخفیف+مالیات با جمع ردیف (%d) نمی‌خواند", k+1, it.LineTotal)
			}
			t.sub += gross
			t.disc += it.Discount
			t.tax += it.TaxAmount
			t.total += it.LineTotal
		}
		if d := t.total - inv.Total; d > 2 || d < -2 {
			addErr(inv.No, "جمع اقلام (%d) با جمع کل فاکتور (%d) نمی‌خواند", t.total, inv.Total)
		}
		totals[inv.No] = t
		itemCount += len(inv.Items)
		if inv.Kind == "official" {
			grand += t.total
		}
	}

	// ── ۲) شماره‌های گرفته‌شده در دیتابیس (یکتایی سال+شماره، سراسری) ──
	var conflicts []int
	if rows, err := s.db.Query("SELECT seq_no FROM inv_invoices WHERE seq_year = ?", in.SeqYear); err != nil {
		writeDBErr(w, "importLegacy", err)
		return
	} else {
		taken := map[int]bool{}
		for rows.Next() {
			var n sql.NullInt64
			if rows.Scan(&n) == nil && n.Valid {
				taken[int(n.Int64)] = true
			}
		}
		rows.Close()
		for _, inv := range in.Invoices {
			if taken[inv.No] {
				conflicts = append(conflicts, inv.No)
			}
		}
	}

	// ── ۳) تطبیق مشتری: شناسه‌ی ملی → شماره‌ی اقتصادی → نام (فقط اگر یکتا) ──
	byNID, byEco, byName := map[string]int64{}, map[string]int64{}, map[string]int64{}
	nameDup := map[string]bool{}
	if rows, err := s.db.Query(`SELECT id, name, COALESCE(national_id,''), COALESCE(economic_code,'')
		FROM crm_customers WHERE organization_id = ? AND is_deleted = 0`, u.OrgID); err != nil {
		writeDBErr(w, "importLegacy", err)
		return
	} else {
		for rows.Next() {
			var id int64
			var name, nid, eco string
			if rows.Scan(&id, &name, &nid, &eco) != nil {
				continue
			}
			if d := onlyDigits(nid); d != "" {
				if _, ok := byNID[d]; !ok {
					byNID[d] = id
				}
			}
			if d := onlyDigits(eco); d != "" {
				if _, ok := byEco[d]; !ok {
					byEco[d] = id
				}
			}
			if k := nameKey(name); k != "" {
				if _, ok := byName[k]; ok {
					nameDup[k] = true
				}
				byName[k] = id
			}
		}
		rows.Close()
	}
	custOf := map[int]int64{}    // شماره‌ی فاکتور → مشتری موجود
	newKeyOf := map[int]string{} // شماره‌ی فاکتور → کلید مشتری تازه
	newCust := map[string]*legacyNewCustomer{}
	newOrder := []string{}
	newData := map[string]legacyCustomer{}
	matched := 0
	for _, inv := range in.Invoices {
		c := inv.Customer
		if inv.Kind == "reserve" && c.Name == "" {
			continue
		}
		nid, eco, nk := onlyDigits(c.NationalID), onlyDigits(c.EconomicCode), nameKey(c.Name)
		if id, ok := byNID[nid]; ok && nid != "" {
			custOf[inv.No] = id
		} else if id, ok := byEco[eco]; ok && eco != "" {
			custOf[inv.No] = id
		} else if id, ok := byName[nk]; ok && nk != "" && !nameDup[nk] {
			custOf[inv.No] = id
		}
		if _, ok := custOf[inv.No]; ok {
			matched++
			continue
		}
		key := "n:" + nk
		if nid != "" {
			key = "i:" + nid
		} else if eco != "" {
			key = "e:" + eco
		}
		if _, ok := newCust[key]; !ok {
			newCust[key] = &legacyNewCustomer{Name: c.Name, NationalID: nid, EconomicCode: eco}
			newData[key] = c
			newOrder = append(newOrder, key)
		}
		newCust[key].Invoices = append(newCust[key].Invoices, inv.No)
		newKeyOf[inv.No] = key
	}
	newList := make([]legacyNewCustomer, 0, len(newOrder))
	for _, k := range newOrder {
		newList = append(newList, *newCust[k])
	}

	report := map[string]any{
		"dry_run":           in.DryRun,
		"seq_year":          in.SeqYear,
		"counts":            counts,
		"invoice_count":     len(in.Invoices),
		"item_count":        itemCount,
		"official_total":    grand,
		"errors":            errs,
		"conflicts":         conflicts,
		"matched_customers": matched,
		"new_customers":     newList,
	}
	if in.DryRun {
		report["success"] = true
		writeJSON(w, http.StatusOK, report)
		return
	}
	if len(errs) > 0 || len(conflicts) > 0 {
		report["success"] = false
		report["message"] = "به‌خاطر خطا یا شماره‌ی تکراری هیچ فاکتوری ثبت نشد؛ اول بررسی را بی‌خطا کنید."
		writeJSON(w, http.StatusConflict, report)
		return
	}

	// ── ۴) ثبت — همه یا هیچ ──
	tx, err := s.db.Begin()
	if err != nil {
		writeDBErr(w, "importLegacy", err)
		return
	}
	defer tx.Rollback()

	ref := nullIfEmpty(in.Batch)
	insCust, err := tx.Prepare(`INSERT INTO crm_customers
		  (organization_id, type, name, phone, national_id, economic_code, province, city, postal_code, address,
		   external_source, external_ref, created_by)
		VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`)
	if err != nil {
		writeDBErr(w, "importLegacy", err)
		return
	}
	defer insCust.Close()
	newID := map[string]int64{}
	for _, k := range newOrder {
		c := newData[k]
		ctype := "legal"
		if len(onlyDigits(c.NationalID)) == 10 { // کد ملی ۱۰ رقمی = شخص حقیقی؛ شناسه‌ی ملی شرکت ۱۱ رقمی است
			ctype = "individual"
		}
		res, err := insCust.Exec(u.OrgID, ctype, c.Name, nullIfEmpty(c.Phone), nullIfEmpty(onlyDigits(c.NationalID)),
			nullIfEmpty(onlyDigits(c.EconomicCode)), nullIfEmpty(c.Province), nullIfEmpty(c.City),
			nullIfEmpty(onlyDigits(c.PostalCode)), nullIfEmpty(c.Address), legacyExternalSource, ref, u.ID)
		if err != nil {
			writeDBErr(w, "importLegacy customer", err)
			return
		}
		newID[k], _ = res.LastInsertId()
	}

	// مشتری جای‌نگهدار برای شماره‌های رزروِ بی‌نام (customer_id اجباری است)
	var placeholderID int64
	for _, inv := range in.Invoices {
		if inv.Kind == "reserve" && inv.Customer.Name == "" {
			res, err := insCust.Exec(u.OrgID, "legal", "شماره‌ی رزرو (در اکسل خالی بود)", nil, nil, nil, nil, nil, nil, nil,
				legacyExternalSource, ref, u.ID)
			if err != nil {
				writeDBErr(w, "importLegacy placeholder", err)
				return
			}
			placeholderID, _ = res.LastInsertId()
			break
		}
	}

	insInv, err := tx.Prepare(`INSERT INTO inv_invoices
		  (organization_id, doc_type, customer_id, warehouse_id, issue_date, status, source, payment_type,
		   subtotal_amount, discount_amount, tax_amount, total_amount, note, created_by, approved_by, approved_at,
		   seq_year, seq_no, number, settlement_status, moadian_status, moadian_code)
		VALUES (?, ?, ?, ?, ?, ?, 'import', NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`)
	if err != nil {
		writeDBErr(w, "importLegacy", err)
		return
	}
	defer insInv.Close()
	insItem, err := tx.Prepare(`INSERT INTO inv_invoice_items
		  (invoice_id, product_id, title, qty, unit_price, discount, is_tax_exempt, tax_rate, tax_amount, line_total, sort_order)
		VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?)`)
	if err != nil {
		writeDBErr(w, "importLegacy", err)
		return
	}
	defer insItem.Close()

	created := 0
	for _, inv := range in.Invoices {
		cid, ok := custOf[inv.No]
		if !ok {
			if k, has := newKeyOf[inv.No]; has {
				cid = newID[k]
			} else {
				cid = placeholderID
			}
		}
		docType := "official"
		if inv.Kind == "proforma" {
			docType = "proforma"
		}
		status := "approved"
		if inv.Kind == "cancelled" || inv.Kind == "reserve" {
			status = "cancelled"
		}
		date := dates[inv.No]
		var approvedBy, approvedAt any
		if status == "approved" && date.Valid {
			approvedBy, approvedAt = u.ID, date.String+" 12:00:00"
		}
		note := strings.TrimSpace(inv.Note)
		if inv.Kind == "reserve" {
			note = strings.TrimSpace("شماره‌ی رزرو: این شماره در اکسلِ فاکتورهای قدیمی خالی بود. " + note)
		}
		settle := "unsettled"
		if inv.Settled {
			settle = "settled"
		}
		moadian := "unregistered"
		if inv.MoadianCode != "" {
			moadian = "registered"
		}
		t := totals[inv.No]
		res, err := insInv.Exec(u.OrgID, docType, cid, s.officialWarehouseID, date, status,
			t.sub, t.disc, t.tax, t.total, nullIfEmpty(note), u.ID, approvedBy, approvedAt,
			in.SeqYear, inv.No, formatInvoiceNumber(tx, inv.No), settle, moadian, nullIfEmpty(inv.MoadianCode))
		if isDuplicateKey(err) {
			writeErr(w, http.StatusConflict, fmt.Sprintf("شماره‌ی %d همین الان در سال %d ثبت شد؛ هیچ فاکتوری وارد نشد", inv.No, in.SeqYear))
			return
		}
		if err != nil {
			writeDBErr(w, "importLegacy invoice", err)
			return
		}
		invID, _ := res.LastInsertId()
		for k, it := range inv.Items {
			gross := int64(math.Round(it.Qty * float64(it.UnitPrice)))
			after := gross - it.Discount
			rate := 0.0
			if after > 0 {
				rate = math.Round(float64(it.TaxAmount)*10000/float64(after)) / 100 // دو رقم اعشار، مثلا ۱۰٫۰۰
			}
			exempt := it.TaxAmount == 0 && after > 0
			title := strings.TrimSpace(it.Title)
			if len([]rune(title)) > 255 {
				title = string([]rune(title)[:255])
			}
			if _, err := insItem.Exec(invID, title, it.Qty, it.UnitPrice, it.Discount, exempt, rate,
				it.TaxAmount, it.LineTotal, k); err != nil {
				writeDBErr(w, "importLegacy item", err)
				return
			}
		}
		created++
	}
	if err := tx.Commit(); err != nil {
		writeDBErr(w, "importLegacy commit", err)
		return
	}
	report["success"] = true
	report["created"] = created
	report["message"] = fmt.Sprintf("%d فاکتور و %d مشتری تازه ثبت شد.", created, len(newOrder))
	writeJSON(w, http.StatusOK, report)
}

// POST /crm/api/inv/import-legacy/rollback — حذف همه‌ی فاکتورهای واردشده از اکسل،
// و (حذف نرمِ) مشتری‌هایی که خود ابزار ساخته و حالا هیچ فاکتوری ندارند. به بقیه دست نمی‌زند.
func (s *server) rollbackLegacyImport(w http.ResponseWriter, r *http.Request) {
	u := userOf(r.Context())
	var in struct{}
	if !decodeJSON(w, r, &in, 1<<10) {
		return
	}
	tx, err := s.db.Begin()
	if err != nil {
		writeDBErr(w, "rollbackLegacyImport", err)
		return
	}
	defer tx.Rollback()

	res, err := tx.Exec(`DELETE it FROM inv_invoice_items it
		JOIN inv_invoices i ON i.id = it.invoice_id
		WHERE i.organization_id = ? AND i.source = 'import'`, u.OrgID)
	if err != nil {
		writeDBErr(w, "rollbackLegacyImport items", err)
		return
	}
	items, _ := res.RowsAffected()
	res, err = tx.Exec("DELETE FROM inv_invoices WHERE organization_id = ? AND source = 'import'", u.OrgID)
	if err != nil {
		writeDBErr(w, "rollbackLegacyImport invoices", err)
		return
	}
	invs, _ := res.RowsAffected()
	// حذف نرم (مثل خود سیستم): UPDATE روی crm_customers در عمل امتحان‌شده است؛
	// DELETE فقط در فایل GRANT نوشته شده و هیچ مسیری تا حالا اجرایش نکرده.
	res, err = tx.Exec(`UPDATE crm_customers c
		SET c.is_deleted = 1
		WHERE c.organization_id = ? AND c.external_source = ? AND c.is_deleted = 0
		  AND NOT EXISTS (SELECT 1 FROM inv_invoices i WHERE i.customer_id = c.id)`, u.OrgID, legacyExternalSource)
	if err != nil {
		writeDBErr(w, "rollbackLegacyImport customers", err)
		return
	}
	custs, _ := res.RowsAffected()
	if err := tx.Commit(); err != nil {
		writeDBErr(w, "rollbackLegacyImport commit", err)
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{
		"success": true,
		"message": fmt.Sprintf("%d فاکتور واردشده (با %d قلم) و %d مشتریِ ساخته‌شده حذف شد.", invs, items, custs),
	})
}
