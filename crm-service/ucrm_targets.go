package main

// ═══════════════════════════════════════════════════════════════════
//  CRM جدید — تارگت‌ها و قواعد هدف‌گذاری (pages/ucrm-targets.php).
//
//  دوره‌ها (شمسی): monthly '1405-07' | quarterly '1405-Q3' | yearly '1405'
//  مقدار مؤثر هر مشتری برای یک دوره (به ترتیب اولویت):
//    ۱) عدد اختصاصی همان دوره (ucrm_targets)
//    ۲) آخرین عدد دوره‌های قبلیِ همان نوع («ادامه از دورهٔ قبل»)
//    ۳) قاعدهٔ پیش‌فرض فعال (ucrm_target_rules): اول قاعدهٔ همان کارشناس، بعد «همهٔ کارشناسان»
//  «معلق» (is_active=0) از آخرین رکورد همان نوع ارث می‌رسد و در رنگ وضعیت حساب نمی‌شود.
//  ویرایش: فقط مدیر. دیدن: مدیر و حسابداری همه، کارشناس فقط مشتری‌های خودش.
// ═══════════════════════════════════════════════════════════════════

import (
	"database/sql"
	"fmt"
	"net/http"
	"regexp"
	"strconv"
	"strings"
	"time"
)

var (
	reMonthKey   = regexp.MustCompile(`^\d{4}-(0[1-9]|1[0-2])$`)
	reQuarterKey = regexp.MustCompile(`^\d{4}-Q[1-4]$`)
	reYearKey    = regexp.MustCompile(`^\d{4}$`)
	jMonthNames  = []string{"فروردین", "اردیبهشت", "خرداد", "تیر", "مرداد", "شهریور", "مهر", "آبان", "آذر", "دی", "بهمن", "اسفند"}
	quarterNames = []string{"بهار", "تابستان", "پاییز", "زمستان"}
)

func validPeriod(ptype, key string) bool {
	switch ptype {
	case "monthly":
		return reMonthKey.MatchString(key)
	case "quarterly":
		return reQuarterKey.MatchString(key)
	case "yearly":
		return reYearKey.MatchString(key)
	}
	return false
}

func faDigits(s string) string {
	var b strings.Builder
	for _, r := range s {
		if r >= '0' && r <= '9' {
			b.WriteRune('۰' + (r - '0'))
		} else {
			b.WriteRune(r)
		}
	}
	return b.String()
}

// periodLabel: «مهر ۱۴۰۵» / «پاییز ۱۴۰۵» / «سال ۱۴۰۵»
func periodLabel(ptype, key string) string {
	switch ptype {
	case "monthly":
		m, _ := strconv.Atoi(key[5:])
		if m >= 1 && m <= 12 {
			return jMonthNames[m-1] + " " + faDigits(key[:4])
		}
	case "quarterly":
		q, _ := strconv.Atoi(key[6:])
		if q >= 1 && q <= 4 {
			return quarterNames[q-1] + " " + faDigits(key[:4])
		}
	case "yearly":
		return "سال " + faDigits(key)
	}
	return faDigits(key)
}

// ucrmCurrentPeriods: دوره‌های جاری بر اساس «امروزِ دیتابیس» (MySQL سرور = تهران؛ ساعت پروسه ممکن است نباشد)
func (s *server) ucrmCurrentPeriods() map[string]string {
	var d time.Time
	if err := s.db.QueryRow("SELECT CURDATE()").Scan(&d); err != nil {
		d = time.Now()
	}
	jy, jm, _ := gregorianToJalali(d.Year(), int(d.Month()), d.Day())
	return map[string]string{
		"monthly":   fmt.Sprintf("%04d-%02d", jy, jm),
		"quarterly": fmt.Sprintf("%04d-Q%d", jy, (jm-1)/3+1),
		"yearly":    fmt.Sprintf("%04d", jy),
	}
}

type effTarget struct {
	Target    *int64 `json:"target"`
	TargetSrc string `json:"target_src"` // own | carry | rule | ""
	Floor     *int64 `json:"floor"`
	FloorSrc  string `json:"floor_src"`
	Count     *int64 `json:"target_count"`     // تارگت تعداد فروش (مورد) — بدون قاعدهٔ پیش‌فرض
	CountSrc  string `json:"target_count_src"` // own | carry | ""
	Active    bool   `json:"is_active"`
}

// ucrmEffective: مقدار مؤثر تارگت/کف برای همهٔ مشتری‌های سازمان در یک دوره. reps: customer_id → rep_id
func (s *server) ucrmEffective(reps map[int64]int64, ptype, key string) (map[int64]*effTarget, error) {
	out := map[int64]*effTarget{}
	for id := range reps {
		out[id] = &effTarget{Active: true}
	}
	rows, err := s.db.Query(`
		SELECT t.customer_id, t.period_key, t.target_amount, t.floor_amount, t.target_count, t.is_active
		FROM ucrm_targets t JOIN ucrm_customers c ON c.id = t.customer_id
		WHERE c.organization_id = ? AND c.is_deleted = 0 AND t.period_type = ? AND t.period_key <= ?
		ORDER BY t.customer_id, t.period_key DESC`, ucrmOrg, ptype, key)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	seen := map[int64]bool{}
	for rows.Next() {
		var cid int64
		var pk string
		var tg, fl, cnt sql.NullInt64
		var act int
		if err := rows.Scan(&cid, &pk, &tg, &fl, &cnt, &act); err != nil {
			return nil, err
		}
		e, ok := out[cid]
		if !ok {
			continue
		}
		src := "carry"
		if pk == key {
			src = "own"
		}
		if !seen[cid] { // آخرین رکورد → وضعیت فعال/معلق
			seen[cid] = true
			e.Active = act == 1
		}
		if e.Target == nil && tg.Valid {
			v := tg.Int64
			e.Target, e.TargetSrc = &v, src
		}
		if e.Floor == nil && fl.Valid {
			v := fl.Int64
			e.Floor, e.FloorSrc = &v, src
		}
		if e.Count == nil && cnt.Valid {
			v := cnt.Int64
			e.Count, e.CountSrc = &v, src
		}
	}
	rows.Close()

	// قواعد پیش‌فرض
	type ruleKey struct {
		basis string
		rep   int64
	}
	rules := map[ruleKey]int64{}
	rr, err := s.db.Query(`SELECT basis, amount, COALESCE(rep_id,0) FROM ucrm_target_rules
		WHERE organization_id = ? AND period_type = ? AND is_active = 1 ORDER BY id`, ucrmOrg, ptype)
	if err != nil {
		return nil, err
	}
	defer rr.Close()
	for rr.Next() {
		var b string
		var a, rep int64
		if rr.Scan(&b, &a, &rep) == nil {
			if _, dup := rules[ruleKey{b, rep}]; !dup {
				rules[ruleKey{b, rep}] = a
			}
		}
	}
	pick := func(basis string, rep int64) (int64, bool) {
		if v, ok := rules[ruleKey{basis, rep}]; ok && rep > 0 {
			return v, true
		}
		v, ok := rules[ruleKey{basis, 0}]
		return v, ok
	}
	for cid, e := range out {
		if e.Target == nil {
			if v, ok := pick("target", reps[cid]); ok {
				e.Target, e.TargetSrc = &v, "rule"
			}
		}
		if e.Floor == nil {
			if v, ok := pick("floor", reps[cid]); ok {
				e.Floor, e.FloorSrc = &v, "rule"
			}
		}
	}
	return out, nil
}

// ucrmApplyCurrentTargets: برای badge فهرست مشتریان — تارگت ماهانهٔ جاری؛ اگر نبود فصلی، بعد سالانه.
func (s *server) ucrmApplyCurrentTargets(items []ucrmCustomerOut) {
	if len(items) == 0 {
		return
	}
	reps := map[int64]int64{}
	for _, c := range items {
		reps[c.ID] = c.RepID
	}
	cur := s.ucrmCurrentPeriods()
	done := map[int64]bool{}
	for _, pt := range []string{"monthly", "quarterly", "yearly"} {
		eff, err := s.ucrmEffective(reps, pt, cur[pt])
		if err != nil {
			return
		}
		for i := range items {
			c := &items[i]
			if done[c.ID] {
				continue
			}
			if e := eff[c.ID]; e != nil && e.Target != nil && *e.Target > 0 {
				v := *e.Target
				c.TargetAmount = &v
				c.TargetPeriod = periodLabel(pt, cur[pt])
				c.TargetSuspend = !e.Active
				done[c.ID] = true
			}
		}
	}
}

// ucrmUpsertTarget: یک رکورد دوره را می‌نویسد؛ nil = دست نزن، ۰ = خالی کن (برگشت به ادامه/قاعده)
func ucrmUpsertTarget(ex interface {
	Exec(string, ...any) (sql.Result, error)
}, cid int64, ptype, key string, target, floor, count *int64, active *bool, uid int64) error {
	if _, err := ex.Exec(`INSERT IGNORE INTO ucrm_targets (customer_id, period_type, period_key, updated_by) VALUES (?, ?, ?, ?)`,
		cid, ptype, key, uid); err != nil {
		return err
	}
	set := []string{"updated_by = ?"}
	args := []any{uid}
	if target != nil {
		set = append(set, "target_amount = ?")
		args = append(args, nullIfZero(*target))
	}
	if floor != nil {
		set = append(set, "floor_amount = ?")
		args = append(args, nullIfZero(*floor))
	}
	if count != nil {
		set = append(set, "target_count = ?")
		args = append(args, nullIfZero(*count))
	}
	if active != nil {
		set = append(set, "is_active = ?")
		a := 0
		if *active {
			a = 1
		}
		args = append(args, a)
	}
	args = append(args, cid, ptype, key)
	_, err := ex.Exec("UPDATE ucrm_targets SET "+strings.Join(set, ", ")+
		" WHERE customer_id = ? AND period_type = ? AND period_key = ?", args...)
	return err
}

// ──────────────── GET /ucrm/targets?period_type=&period=&rep_id= ────────────────

func (s *server) ucrmListTargets(w http.ResponseWriter, r *http.Request) {
	uid := userOf(r.Context()).ID
	ro := s.ucrmRolesOf(uid)
	cur := s.ucrmCurrentPeriods()
	ptype := r.URL.Query().Get("period_type")
	if ptype == "" {
		ptype = "monthly"
	}
	key := strings.TrimSpace(r.URL.Query().Get("period"))
	if key == "" {
		key = cur[ptype]
	}
	if !validPeriod(ptype, key) {
		writeErr(w, http.StatusBadRequest, "دورهٔ انتخاب‌شده معتبر نیست.")
		return
	}
	repFilter, _ := strconv.ParseInt(r.URL.Query().Get("rep_id"), 10, 64)

	// مشتری‌هایی که تارگت دارند: تأییدشده یا در انتظار حسابداری (از تأیید مدیر گذشته‌اند)
	where := " WHERE c.organization_id = ? AND c.is_deleted = 0 AND c.status IN ('active','pending_accounting')"
	args := []any{ucrmOrg}
	if !ro.seesAll() {
		where += " AND c.rep_id = ?"
		args = append(args, uid)
	} else if repFilter > 0 {
		where += " AND c.rep_id = ?"
		args = append(args, repFilter)
	}
	rows, err := s.db.Query(`
		SELECT c.id, c.name, c.type, c.status, COALESCE(c.rep_id,0),
		       TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')))
		FROM ucrm_customers c LEFT JOIN users u ON u.id = c.rep_id`+where+` ORDER BY c.name`, args...)
	if err != nil {
		writeDBErr(w, "ucrmListTargets", err)
		return
	}
	defer rows.Close()
	type item struct {
		CustomerID int64    `json:"customer_id"`
		Name       string   `json:"name"`
		Type       string   `json:"type"`
		Status     string   `json:"status"`
		RepID      int64    `json:"rep_id"`
		RepName    string   `json:"rep_name"`
		Sales      *float64 `json:"sales"` // فروش واقعی دوره — از فاکتورهای CRM (فعلا ندارد)
		effTarget
	}
	items := []item{}
	reps := map[int64]int64{}
	for rows.Next() {
		var it item
		if err := rows.Scan(&it.CustomerID, &it.Name, &it.Type, &it.Status, &it.RepID, &it.RepName); err != nil {
			writeDBErr(w, "ucrmListTargets scan", err)
			return
		}
		reps[it.CustomerID] = it.RepID
		items = append(items, it)
	}
	rows.Close()
	eff, err := s.ucrmEffective(reps, ptype, key)
	if err != nil {
		writeDBErr(w, "ucrmListTargets eff", err)
		return
	}
	for i := range items {
		if e := eff[items[i].CustomerID]; e != nil {
			items[i].effTarget = *e
		}
	}
	ps, pe := periodRangeJalali(ptype, key)
	writeJSON(w, http.StatusOK, map[string]any{
		"items": items, "period_type": ptype, "period": key, "period_label": periodLabel(ptype, key),
		"current": cur, "roles": ro, "settings": s.ucrmLoadSettings(),
		"period_start": ps, "period_end": pe,
	})
}

// ──────────────── PUT /ucrm/targets — فقط مدیر (تکی، گروهی، افزودن) ────────────────
// body: {customer_ids:[..], period_type, period, target?, floor?, is_active?}
// target/floor: عدد = تعیین، ۰ = پاک کردن عدد اختصاصی (برگشت به دورهٔ قبل/قاعده)، نبودن = دست نزن

func (s *server) ucrmPutTargets(w http.ResponseWriter, r *http.Request) {
	uid := userOf(r.Context()).ID
	if !s.ucrmRolesOf(uid).Manager {
		writeErr(w, http.StatusForbidden, "فقط مدیر می‌تواند تارگت تعیین کند.")
		return
	}
	var in struct {
		CustomerIDs []int64 `json:"customer_ids"`
		PeriodType  string  `json:"period_type"`
		Period      string  `json:"period"`
		Target      *int64  `json:"target"`
		Floor       *int64  `json:"floor"`
		TargetCount *int64  `json:"target_count"` // تارگت تعداد فروش (مورد)
		IsActive    *bool   `json:"is_active"`
	}
	if !decodeJSON(w, r, &in, 1<<20) {
		return
	}
	if !validPeriod(in.PeriodType, in.Period) {
		writeErr(w, http.StatusBadRequest, "دورهٔ انتخاب‌شده معتبر نیست.")
		return
	}
	if len(in.CustomerIDs) == 0 || len(in.CustomerIDs) > 2000 {
		writeErr(w, http.StatusBadRequest, "دست‌کم یک مشتری را انتخاب کنید.")
		return
	}
	if in.Target == nil && in.Floor == nil && in.TargetCount == nil && in.IsActive == nil {
		writeErr(w, http.StatusBadRequest, "چیزی برای ذخیره نیست.")
		return
	}
	for _, v := range []*int64{in.Target, in.Floor} {
		if v != nil && (*v < 0 || *v > 1_000_000_000_000_000) {
			writeErr(w, http.StatusBadRequest, "مبلغ معتبر نیست.")
			return
		}
	}
	if in.TargetCount != nil && (*in.TargetCount < 0 || *in.TargetCount > 10_000_000) {
		writeErr(w, http.StatusBadRequest, "تارگت تعداد فروش معتبر نیست.")
		return
	}
	if in.Target != nil && in.Floor != nil && *in.Target > 0 && *in.Floor > *in.Target {
		writeErr(w, http.StatusBadRequest, "کف فروش نمی‌تواند از تارگت بیشتر باشد.")
		return
	}

	tx, err := s.db.Begin()
	if err != nil {
		writeDBErr(w, "ucrmPutTargets begin", err)
		return
	}
	defer tx.Rollback()
	actor := s.userName(uid)
	label := periodLabel(in.PeriodType, in.Period)
	chk, err := tx.Prepare(`SELECT name FROM ucrm_customers WHERE id = ? AND organization_id = ? AND is_deleted = 0
		AND status IN ('active','pending_accounting')`)
	if err != nil {
		writeDBErr(w, "ucrmPutTargets prep", err)
		return
	}
	defer chk.Close()
	done := 0
	seen := map[int64]bool{}
	for _, cid := range in.CustomerIDs {
		if cid <= 0 || seen[cid] {
			continue
		}
		seen[cid] = true
		var name string
		if chk.QueryRow(cid, ucrmOrg).Scan(&name) != nil {
			continue
		}
		if err := ucrmUpsertTarget(tx, cid, in.PeriodType, in.Period, in.Target, in.Floor, in.TargetCount, in.IsActive, uid); err != nil {
			writeDBErr(w, "ucrmPutTargets upsert", err)
			return
		}
		parts := []string{}
		if in.Target != nil {
			if *in.Target > 0 {
				parts = append(parts, "تارگت را "+faMoney(*in.Target)+" تومان")
			} else {
				parts = append(parts, "تارگت اختصاصی را خالی")
			}
		}
		if in.Floor != nil {
			if *in.Floor > 0 {
				parts = append(parts, "کف فروش را "+faMoney(*in.Floor)+" تومان")
			} else {
				parts = append(parts, "کف فروش اختصاصی را خالی")
			}
		}
		if in.TargetCount != nil {
			if *in.TargetCount > 0 {
				parts = append(parts, "تارگت تعداد فروش را "+faDigits(strconv.FormatInt(*in.TargetCount, 10))+" مورد")
			} else {
				parts = append(parts, "تارگت تعداد فروش اختصاصی را خالی")
			}
		}
		if in.IsActive != nil {
			if *in.IsActive {
				parts = append(parts, "وضعیت را «فعال»")
			} else {
				parts = append(parts, "وضعیت را «معلق»")
			}
		}
		note := actor + " برای مشتری «" + name + "» در " + label + " " + strings.Join(parts, "، ") + " کرد."
		s.ucrmEvent(tx, cid, uid, "target_set", note)
		done++
	}
	if err := tx.Commit(); err != nil {
		writeDBErr(w, "ucrmPutTargets commit", err)
		return
	}
	if done == 0 {
		writeErr(w, http.StatusBadRequest, "هیچ مشتری معتبری انتخاب نشده است.")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "updated": done,
		"message": faDigits(strconv.Itoa(done)) + " مشتری برای " + label + " به‌روز شد."})
}

// ──────────────── قواعد پیش‌فرض («تنظیمات هدف‌گذاری») ────────────────

type ucrmRule struct {
	ID         int64  `json:"id"`
	Basis      string `json:"basis"`
	PeriodType string `json:"period_type"`
	Amount     int64  `json:"amount"`
	RepID      int64  `json:"rep_id"`
	RepName    string `json:"rep_name"`
	IsActive   bool   `json:"is_active"`
}

func (s *server) ucrmListRules(w http.ResponseWriter, r *http.Request) {
	rows, err := s.db.Query(`
		SELECT t.id, t.basis, t.period_type, t.amount, COALESCE(t.rep_id,0),
		       TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))), t.is_active
		FROM ucrm_target_rules t LEFT JOIN users u ON u.id = t.rep_id
		WHERE t.organization_id = ? ORDER BY t.is_active DESC, t.period_type, t.basis, t.id`, ucrmOrg)
	if err != nil {
		writeDBErr(w, "ucrmListRules", err)
		return
	}
	defer rows.Close()
	items := []ucrmRule{}
	for rows.Next() {
		var it ucrmRule
		var act int
		if rows.Scan(&it.ID, &it.Basis, &it.PeriodType, &it.Amount, &it.RepID, &it.RepName, &act) == nil {
			it.IsActive = act == 1
			items = append(items, it)
		}
	}
	writeJSON(w, http.StatusOK, map[string]any{"items": items})
}

func (s *server) ucrmSaveRule(w http.ResponseWriter, r *http.Request) {
	uid := userOf(r.Context()).ID
	if !s.ucrmRolesOf(uid).Manager {
		writeErr(w, http.StatusForbidden, "فقط مدیر می‌تواند تنظیمات هدف‌گذاری را تغییر دهد.")
		return
	}
	id := idParam(r) // صفر = قاعدهٔ جدید (POST)
	var in struct {
		Basis      string `json:"basis"`
		PeriodType string `json:"period_type"`
		Amount     int64  `json:"amount"`
		RepID      int64  `json:"rep_id"`
		IsActive   bool   `json:"is_active"`
	}
	if !decodeJSON(w, r, &in, 1<<14) {
		return
	}
	if in.Basis != "target" && in.Basis != "floor" {
		writeErr(w, http.StatusBadRequest, "مبنای هدف‌گذاری را انتخاب کنید.")
		return
	}
	if in.PeriodType != "monthly" && in.PeriodType != "quarterly" && in.PeriodType != "yearly" {
		writeErr(w, http.StatusBadRequest, "دورهٔ زمانی را انتخاب کنید.")
		return
	}
	if in.Amount <= 0 || in.Amount > 1_000_000_000_000_000 {
		writeErr(w, http.StatusBadRequest, "مقدار هدف را وارد کنید.")
		return
	}
	if in.RepID > 0 && !s.isUcrmRep(in.RepID) {
		writeErr(w, http.StatusBadRequest, "کارشناس انتخاب‌شده عضو تیم فروش نیست.")
		return
	}
	// یک قاعدهٔ فعال برای هر (مبنا، دوره، کارشناس)
	if in.IsActive {
		var other int64
		_ = s.db.QueryRow(`SELECT id FROM ucrm_target_rules WHERE organization_id = ? AND basis = ? AND period_type = ?
			AND COALESCE(rep_id,0) = ? AND is_active = 1 AND id <> ? LIMIT 1`,
			ucrmOrg, in.Basis, in.PeriodType, in.RepID, id).Scan(&other)
		if other > 0 {
			writeErr(w, http.StatusConflict, "برای همین مبنا، دوره و کارشناس یک قاعدهٔ فعال دیگر هست؛ همان را ویرایش کنید یا اول غیرفعالش کنید.")
			return
		}
	}
	act := 0
	if in.IsActive {
		act = 1
	}
	if id > 0 {
		res, err := s.db.Exec(`UPDATE ucrm_target_rules SET basis = ?, period_type = ?, amount = ?, rep_id = ?, is_active = ?
			WHERE id = ? AND organization_id = ?`, in.Basis, in.PeriodType, in.Amount, nullableID(in.RepID), act, id, ucrmOrg)
		if err != nil {
			writeDBErr(w, "ucrmSaveRule", err)
			return
		}
		if n, _ := res.RowsAffected(); n == 0 {
			var ok int
			if s.db.QueryRow("SELECT 1 FROM ucrm_target_rules WHERE id = ? AND organization_id = ?", id, ucrmOrg).Scan(&ok) != nil {
				writeErr(w, http.StatusNotFound, "قاعده یافت نشد")
				return
			}
		}
	} else {
		res, err := s.db.Exec(`INSERT INTO ucrm_target_rules (organization_id, basis, period_type, amount, rep_id, is_active, created_by)
			VALUES (?, ?, ?, ?, ?, ?, ?)`, ucrmOrg, in.Basis, in.PeriodType, in.Amount, nullableID(in.RepID), act, uid)
		if err != nil {
			writeDBErr(w, "ucrmSaveRule", err)
			return
		}
		id, _ = res.LastInsertId()
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "id": id, "message": "تنظیمات هدف‌گذاری ذخیره شد."})
}

func (s *server) ucrmDeleteRule(w http.ResponseWriter, r *http.Request) {
	uid := userOf(r.Context()).ID
	if !s.ucrmRolesOf(uid).Manager {
		writeErr(w, http.StatusForbidden, "فقط مدیر می‌تواند تنظیمات هدف‌گذاری را تغییر دهد.")
		return
	}
	res, err := s.db.Exec("DELETE FROM ucrm_target_rules WHERE id = ? AND organization_id = ?", idParam(r), ucrmOrg)
	if err != nil {
		writeDBErr(w, "ucrmDeleteRule", err)
		return
	}
	if n, _ := res.RowsAffected(); n == 0 {
		writeErr(w, http.StatusNotFound, "قاعده یافت نشد")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "message": "قاعده حذف شد."})
}

// jalaliMonthDays: تعداد روزهای یک ماه شمسی (اسفند کبیسه = ۳۰)
func jalaliMonthDays(jy, jm int) int {
	switch {
	case jm <= 6:
		return 31
	case jm <= 11:
		return 30
	}
	if _, ok := jalaliToGregorian(jy, 12, 30); ok {
		return 30
	}
	return 29
}

// periodRangeJalali: روز اول و آخر یک دوره به شمسی «۱۴۰۵/۰۸/۰۱» — برای نمایش در پنجرهٔ تارگت
func periodRangeJalali(ptype, key string) (string, string) {
	var jy, m1, m2 int
	switch ptype {
	case "monthly":
		fmt.Sscanf(key, "%d-%d", &jy, &m1)
		m2 = m1
	case "quarterly":
		var q int
		fmt.Sscanf(key, "%d-Q%d", &jy, &q)
		m1, m2 = q*3-2, q*3
	case "yearly":
		fmt.Sscanf(key, "%d", &jy)
		m1, m2 = 1, 12
	}
	if jy < 1300 || m1 < 1 || m2 > 12 {
		return "", ""
	}
	return fmt.Sprintf("%04d/%02d/%02d", jy, m1, 1), fmt.Sprintf("%04d/%02d/%02d", jy, m2, jalaliMonthDays(jy, m2))
}
