package main

// ═══════════════════════════════════════════════════════════════════
//  CRM جدید (حسابداری غیررسمی) — مرحلهٔ ۱: مشتریان.
//  ⚠️ کاملا جدا از ماژول «فاکتور رسمی» (crm_customers / inv_*): فقط جدول‌های ucrm_*.
//
//  مسیر تأیید:  کارشناس ثبت → pending_manager → (مدیر تأیید، شاید کارشناس را عوض کند)
//               → pending_accounting → (حسابداری تأیید) → active
//  هر رد (دلیل اجباری) → rejected → کارشناس اصلاح و دوباره ارسال می‌کند (یا انصراف/حذف).
//  مشتری‌ای که خودِ مدیر دستی ثبت کند، مرحلهٔ مدیر را لازم ندارد → مستقیم pending_accounting.
// ═══════════════════════════════════════════════════════════════════

import (
	"database/sql"
	"net/http"
	"sort"
	"strconv"
	"strings"
	"time"
)

const (
	ucStPendingManager    = "pending_manager"
	ucStPendingAccounting = "pending_accounting"
	ucStRejected          = "rejected"
	ucStActive            = "active"
)

// ──────────────── نقش‌ها ────────────────

type ucrmRoles struct {
	Manager    bool `json:"manager"`
	Accounting bool `json:"accounting"`
	Sales      bool `json:"sales"`
}

func (r ucrmRoles) any() bool { return r.Manager || r.Accounting || r.Sales }

// seesAll: مدیر و حسابداری همهٔ مشتری‌ها را می‌بینند؛ کارشناس فقط مال خودش.
func (r ucrmRoles) seesAll() bool { return r.Manager || r.Accounting }

// ucrmRolesOf — ⚠️ هم‌راستا با ucrmRoles در includes/ucrm_access.php. فقط سازمان ۱.
func (s *server) ucrmRolesOf(uid int64) ucrmRoles {
	if uid == 1 || uid == 19 {
		return ucrmRoles{Manager: true}
	}
	var mgr, acc, sales int
	err := s.db.QueryRow(`
		SELECT
		  (u.role IN ('supervisor','admin','manager','management') OR COALESCE(u.is_supervisor, 0) = 1),
		  (u.activity_section = 'accounting' OR EXISTS (SELECT 1 FROM user_activity_sections s
		        WHERE s.user_id = u.id AND s.section_key = 'accounting')),
		  (u.activity_section = 'sales' OR EXISTS (SELECT 1 FROM user_activity_sections s
		        WHERE s.user_id = u.id AND s.section_key = 'sales'))
		FROM users u
		WHERE u.id = ? AND u.is_active = 1 AND u.organization_id = 1`, uid).Scan(&mgr, &acc, &sales)
	if err != nil {
		return ucrmRoles{}
	}
	return ucrmRoles{Manager: mgr == 1, Accounting: acc == 1, Sales: sales == 1}
}

// requireUCRM: فقط کسی که یکی از سه نقش را دارد.
func (s *server) requireUCRM(next http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		if s.ucrmRolesOf(userOf(r.Context()).ID).any() {
			next(w, r)
			return
		}
		writeErr(w, http.StatusForbidden, "به CRM دسترسی ندارید")
	}
}

// ucrmOrg: CRM فعلا فقط مال سازمان ۱ است (مدیر کل هم با هر توکنی روی سازمان ۱ کار می‌کند).
const ucrmOrg = 1

// ──────────────── مدل‌ها ────────────────

type ucrmCustomerOut struct {
	ID             int64    `json:"id"`
	Type           string   `json:"type"`
	Name           string   `json:"name"`
	NationalID     string   `json:"national_id"`
	Province       string   `json:"province"`
	City           string   `json:"city"`
	Address        string   `json:"address"`
	Note           string   `json:"note"`
	RepID          int64    `json:"rep_id"`
	RepName        string   `json:"rep_name"`
	Status         string   `json:"status"`
	RejectedStage  string   `json:"rejected_stage"`
	RejectReason   string   `json:"reject_reason"`
	Source         string   `json:"source"`
	Phones         []string `json:"phones"`
	LastFollowupAt string   `json:"last_followup_at"`
	CreatedBy      int64    `json:"created_by"`
	CreatedAt      string   `json:"created_at"`
	// تارگت (تومان): پیشنهاد کارشناس، و تارگت ماهانهٔ تأییدشدهٔ مدیر (target_amount).
	// فروش واقعی ماه از فاکتورهای CRM پر می‌شود (فعلا null).
	ProposedTarget *int64   `json:"proposed_target"`
	TargetAmount   *int64   `json:"target_amount"`    // تارگت مؤثر دورهٔ جاری (ucrm_targets.go)
	TargetPeriod   string   `json:"target_period"`    // «مهر ۱۴۰۵» / «پاییز ۱۴۰۵» / «سال ۱۴۰۵»
	TargetSuspend  bool     `json:"target_suspended"` // «معلق» → در رنگ وضعیت حساب نمی‌شود
	SalesAmount    *float64 `json:"sales_amount"`
	// اجازه‌های کاربر جاری روی همین ردیف (تا دکمه‌ها در صفحه درست نشان داده شوند)
	CanEdit     bool `json:"can_edit"`
	CanDelete   bool `json:"can_delete"`
	CanApprove  bool `json:"can_approve"`
	CanResubmit bool `json:"can_resubmit"`
}

type ucrmCustomerIn struct {
	Type       string   `json:"type"`
	Name       string   `json:"name"`
	NationalID string   `json:"national_id"`
	Province   string   `json:"province"`
	City       string   `json:"city"`
	Address    string   `json:"address"`
	Note       string   `json:"note"`
	RepID      int64    `json:"rep_id"`
	RepName    string   `json:"rep_name"` // فقط اکسل: نام کارشناس
	Phones     []string `json:"phones"`
	Resubmit   bool     `json:"resubmit"` // ویرایش مشتری ردشده + ارسال دوباره برای تأیید
	// تارگت ماهانه (تومان، ۰ = بدون تارگت). کارشناس → پیشنهاد؛ مدیر روی مشتری تأییدشده → تارگت ماهانه
	Target int64 `json:"target"`
}

func (c *ucrmCustomerIn) normalize() {
	c.Type = customerType(c.Type)
	c.Name = faLetters(strings.Join(strings.Fields(c.Name), " "))
	c.NationalID = digitsOnly(c.NationalID)
	c.Province = strings.TrimSpace(c.Province)
	c.City = strings.TrimSpace(c.City)
	c.Address = faLetters(strings.TrimSpace(c.Address))
	c.Note = strings.TrimSpace(c.Note)
	c.RepName = faLetters(strings.TrimSpace(c.RepName))
	seen := map[string]bool{}
	out := []string{}
	for _, p := range c.Phones {
		p = digitsOnly(p)
		if p != "" && !seen[p] {
			seen[p] = true
			out = append(out, p)
		}
	}
	c.Phones = out
}

// digitsOnly: رقم فارسی/عربی → لاتین و حذف هر چیز غیررقمی (فاصله، خط‌تیره، پرانتز…)
func digitsOnly(v string) string {
	var b strings.Builder
	for _, r := range v {
		switch {
		case r >= '0' && r <= '9':
			b.WriteRune(r)
		case r >= '۰' && r <= '۹':
			b.WriteRune('0' + (r - '۰'))
		case r >= '٠' && r <= '٩':
			b.WriteRune('0' + (r - '٠'))
		}
	}
	return b.String()
}

// ──────────────── اعتبارسنجی (هم‌ارز assets/js/iran-id-validate.js) ────────────────

// کد ملی (۱۰ رقم) / شناسهٔ ملی (۱۱ رقم): فقط تعداد رقم — تصمیم ۱۴۰۵/۰۸/۰۱، هم‌ارز
// assets/js/iran-id-validate.js (رقم کنترل دیگر بررسی نمی‌شود). ورودی قبلا digitsOnly شده است.
func validNationalCode(s string) bool { return len(s) == 10 }

func validLegalID(s string) bool { return len(s) == 11 }

// validateCustomer: خطای فارسی یا "" — همان قواعد فرم.
func validateUcrmCustomer(in ucrmCustomerIn) string {
	if in.Name == "" {
		return "نام مشتری الزامی است."
	}
	if len(in.Phones) == 0 {
		return "دست‌کم یک شماره تماس لازم است."
	}
	for _, p := range in.Phones {
		if len(p) < 4 || len(p) > 15 {
			return "شماره تماس «" + p + "» معتبر نیست."
		}
	}
	if in.Target < 0 || in.Target > 1_000_000_000_000_000 {
		return "تارگت ماهانه معتبر نیست."
	}
	if in.NationalID != "" {
		if in.Type == "individual" && !validNationalCode(in.NationalID) {
			return "کد ملی باید ۱۰ رقم باشد."
		}
		if in.Type == "legal" && !validLegalID(in.NationalID) {
			return "شناسهٔ ملی شرکت باید ۱۱ رقم باشد."
		}
	}
	return ""
}

// ──────────────── کمکی‌ها ────────────────

func (s *server) userName(id int64) string {
	if id <= 0 {
		return ""
	}
	var fn, ln sql.NullString
	if err := s.db.QueryRow("SELECT first_name, last_name FROM users WHERE id = ?", id).Scan(&fn, &ln); err != nil {
		return ""
	}
	return strings.TrimSpace(fn.String + " " + ln.String)
}

// isUcrmRep: کارشناس فروش قابل انتخاب = عضو فعال واحد فروش یا سرپرست سازمان ۱ (نه حساب پشتیبان id=1).
func (s *server) isUcrmRep(id int64) bool {
	if id <= 0 || id == 1 {
		return false
	}
	var ok int
	err := s.db.QueryRow(`
		SELECT 1 FROM users u
		WHERE u.id = ? AND u.is_active = 1 AND u.organization_id = 1
		  AND (u.activity_section = 'sales'
		    OR EXISTS (SELECT 1 FROM user_activity_sections s WHERE s.user_id = u.id AND s.section_key = 'sales')
		    OR u.role IN ('supervisor','admin','manager','management') OR COALESCE(u.is_supervisor, 0) = 1)
		LIMIT 1`, id).Scan(&ok)
	return err == nil && ok == 1
}

// ucrmEvent یک ردیف تاریخچه می‌نویسد و شناسه‌اش را برمی‌گرداند (برای ارسال اعلان بعد از commit).
func (s *server) ucrmEvent(ex interface {
	Exec(string, ...any) (sql.Result, error)
}, customerID, actorID int64, action, note string) int64 {
	res, err := ex.Exec(`INSERT INTO ucrm_customer_events (customer_id, action, actor_id, note) VALUES (?, ?, ?, ?)`,
		customerID, action, nullableID(actorID), note)
	if err != nil {
		return 0
	}
	id, _ := res.LastInsertId()
	return id
}

// nullIfZeroIf: فقط وقتی cond درست است مقدار را می‌نویسد، وگرنه NULL (nullIfZero در stock.go)
func nullIfZeroIf(v int64, cond bool) any {
	if !cond {
		return nil
	}
	return nullIfZero(v)
}

// faMoney: ۵۰۰۰۰۰۰۰ → «۵۰,۰۰۰,۰۰۰» (رقم فارسی + جداکنندهٔ هزارگان)، برای متن تاریخچه
func faMoney(n int64) string {
	d := strconv.FormatInt(n, 10)
	var b strings.Builder
	for i, r := range d {
		if i > 0 && (len(d)-i)%3 == 0 {
			b.WriteRune(',')
		}
		b.WriteRune('۰' + (r - '0'))
	}
	return b.String()
}

func savePhones(tx *sql.Tx, customerID int64, phones []string) error {
	if _, err := tx.Exec("DELETE FROM ucrm_customer_phones WHERE customer_id = ?", customerID); err != nil {
		return err
	}
	for i, p := range phones {
		if _, err := tx.Exec("INSERT INTO ucrm_customer_phones (customer_id, phone, sort) VALUES (?, ?, ?)", customerID, p, i); err != nil {
			return err
		}
	}
	return nil
}

func fmtTime(t sql.NullTime) string {
	if !t.Valid {
		return ""
	}
	return t.Time.Format("2006-01-02 15:04:05")
}

// permissions: اجازه‌های کاربر روی یک مشتری.
func ucrmPerms(c *ucrmCustomerOut, uid int64, ro ucrmRoles) {
	// «مال من» = کارشناس فعلی مشتری (اگر مدیر کارشناس را عوض کند، به کارشناس جدید می‌رسد)
	own := c.RepID == uid
	switch {
	case ro.Manager:
		c.CanEdit, c.CanDelete = true, true
	case own && (c.Status == ucStPendingManager || c.Status == ucStRejected):
		// کارشناس: تا تأیید نشده می‌تواند اصلاح کند یا درخواست را پس بگیرد (حذف)
		c.CanEdit, c.CanDelete = true, true
	}
	c.CanApprove = (c.Status == ucStPendingManager && ro.Manager) ||
		(c.Status == ucStPendingAccounting && (ro.Accounting || uid == 1 || uid == 19))
	c.CanResubmit = c.Status == ucStRejected && (own || ro.Manager)
}

// ──────────────── GET /ucrm/me ────────────────

func (s *server) ucrmMe(w http.ResponseWriter, r *http.Request) {
	uid := userOf(r.Context()).ID
	set := s.ucrmLoadSettings()
	writeJSON(w, http.StatusOK, map[string]any{
		"user_id":  uid,
		"name":     s.userName(uid),
		"roles":    s.ucrmRolesOf(uid),
		"settings": set,
	})
}

// ──────────────── GET /ucrm/reps ────────────────

func (s *server) ucrmReps(w http.ResponseWriter, r *http.Request) {
	rows, err := s.db.Query(`
		SELECT u.id, TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')))
		FROM users u
		WHERE u.is_active = 1 AND u.organization_id = 1 AND u.id <> 1
		  AND (u.activity_section = 'sales'
		    OR EXISTS (SELECT 1 FROM user_activity_sections s WHERE s.user_id = u.id AND s.section_key = 'sales')
		    OR u.role IN ('supervisor','admin','manager','management') OR COALESCE(u.is_supervisor, 0) = 1)
		ORDER BY u.first_name, u.last_name`)
	if err != nil {
		writeDBErr(w, "ucrmReps", err)
		return
	}
	defer rows.Close()
	items := []map[string]any{}
	for rows.Next() {
		var id int64
		var name string
		if rows.Scan(&id, &name) == nil {
			items = append(items, map[string]any{"id": id, "name": name})
		}
	}
	writeJSON(w, http.StatusOK, map[string]any{"items": items})
}

// ──────────────── GET /ucrm/customers ────────────────

const ucrmSelect = `
	SELECT c.id, c.type, c.name, COALESCE(c.national_id,''), COALESCE(c.province,''), COALESCE(c.city,''),
	       COALESCE(c.address,''), COALESCE(c.note,''), c.proposed_target, c.monthly_target, COALESCE(c.rep_id,0),
	       TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))),
	       c.status, COALESCE(c.rejected_stage,''), COALESCE(c.reject_reason,''), c.source,
	       c.last_followup_at, COALESCE(c.created_by,0), c.created_at
	FROM ucrm_customers c
	LEFT JOIN users u ON u.id = c.rep_id`

func scanUcrm(sc interface{ Scan(...any) error }) (ucrmCustomerOut, error) {
	var c ucrmCustomerOut
	var lf sql.NullTime
	var created time.Time
	var pt, mt sql.NullInt64
	err := sc.Scan(&c.ID, &c.Type, &c.Name, &c.NationalID, &c.Province, &c.City, &c.Address, &c.Note, &pt, &mt,
		&c.RepID, &c.RepName, &c.Status, &c.RejectedStage, &c.RejectReason, &c.Source, &lf, &c.CreatedBy, &created)
	if pt.Valid {
		c.ProposedTarget = &pt.Int64
	}
	_ = mt // ستون monthly_target فقط سابقه است؛ تارگت مؤثر از ucrm_targets می‌آید
	c.LastFollowupAt = fmtTime(lf)
	c.CreatedAt = created.Format("2006-01-02 15:04:05")
	c.Phones = []string{}
	return c, err
}

func (s *server) ucrmListCustomers(w http.ResponseWriter, r *http.Request) {
	uid := userOf(r.Context()).ID
	ro := s.ucrmRolesOf(uid)

	where := " WHERE c.organization_id = ? AND c.is_deleted = 0"
	args := []any{ucrmOrg}
	if !ro.seesAll() {
		where += " AND c.rep_id = ?"
		args = append(args, uid)
	}
	rows, err := s.db.Query(ucrmSelect+where+" ORDER BY c.id DESC", args...)
	if err != nil {
		writeDBErr(w, "ucrmListCustomers", err)
		return
	}
	defer rows.Close()

	items := []ucrmCustomerOut{}
	idx := map[int64]int{}
	for rows.Next() {
		c, err := scanUcrm(rows)
		if err != nil {
			writeDBErr(w, "ucrmListCustomers scan", err)
			return
		}
		ucrmPerms(&c, uid, ro)
		idx[c.ID] = len(items)
		items = append(items, c)
	}
	rows.Close()

	// شماره‌ها — یک کوئری برای همه
	if len(items) > 0 {
		prow, err := s.db.Query(`SELECT p.customer_id, p.phone FROM ucrm_customer_phones p
			JOIN ucrm_customers c ON c.id = p.customer_id
			WHERE c.organization_id = ? AND c.is_deleted = 0 ORDER BY p.customer_id, p.sort, p.id`, ucrmOrg)
		if err != nil {
			writeDBErr(w, "ucrmListCustomers phones", err)
			return
		}
		defer prow.Close()
		for prow.Next() {
			var cid int64
			var ph string
			if prow.Scan(&cid, &ph) == nil {
				if i, ok := idx[cid]; ok {
					items[i].Phones = append(items[i].Phones, ph)
				}
			}
		}
	}
	s.ucrmApplyCurrentTargets(items)
	writeJSON(w, http.StatusOK, map[string]any{"items": items, "roles": ro, "settings": s.ucrmLoadSettings()})
}

// loadVisible: یک مشتری که کاربر اجازهٔ دیدنش را دارد (یا nil).
func (s *server) ucrmLoad(id, uid int64, ro ucrmRoles) (*ucrmCustomerOut, error) {
	c, err := scanUcrm(s.db.QueryRow(ucrmSelect+" WHERE c.id = ? AND c.organization_id = ? AND c.is_deleted = 0", id, ucrmOrg))
	if err == sql.ErrNoRows {
		return nil, nil
	}
	if err != nil {
		return nil, err
	}
	if !ro.seesAll() && c.RepID != uid {
		return nil, nil
	}
	ucrmPerms(&c, uid, ro)
	rows, err := s.db.Query("SELECT phone FROM ucrm_customer_phones WHERE customer_id = ? ORDER BY sort, id", id)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	for rows.Next() {
		var p string
		if rows.Scan(&p) == nil {
			c.Phones = append(c.Phones, p)
		}
	}
	one := []ucrmCustomerOut{c}
	s.ucrmApplyCurrentTargets(one)
	c = one[0]
	return &c, nil
}

// ──────────────── GET /ucrm/customers/{id} (صفحهٔ جزئیات) ────────────────

func (s *server) ucrmGetCustomer(w http.ResponseWriter, r *http.Request) {
	uid := userOf(r.Context()).ID
	ro := s.ucrmRolesOf(uid)
	c, err := s.ucrmLoad(idParam(r), uid, ro)
	if err != nil {
		writeDBErr(w, "ucrmGetCustomer", err)
		return
	}
	if c == nil {
		writeErr(w, http.StatusNotFound, "مشتری یافت نشد")
		return
	}
	rows, err := s.db.Query(`
		SELECT e.action, COALESCE(e.note,''), e.created_at,
		       TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')))
		FROM ucrm_customer_events e LEFT JOIN users u ON u.id = e.actor_id
		WHERE e.customer_id = ? ORDER BY e.id DESC`, c.ID)
	if err != nil {
		writeDBErr(w, "ucrmGetCustomer events", err)
		return
	}
	defer rows.Close()
	events := []map[string]any{}
	for rows.Next() {
		var act, note, actor string
		var at time.Time
		if rows.Scan(&act, &note, &at, &actor) == nil {
			events = append(events, map[string]any{
				"action": act, "note": note, "actor": actor, "created_at": at.Format("2006-01-02 15:04:05"),
			})
		}
	}
	writeJSON(w, http.StatusOK, map[string]any{"customer": c, "events": events, "roles": ro, "settings": s.ucrmLoadSettings()})
}

// ──────────────── POST /ucrm/customers ────────────────

func (s *server) ucrmCreateCustomer(w http.ResponseWriter, r *http.Request) {
	uid := userOf(r.Context()).ID
	ro := s.ucrmRolesOf(uid)
	var in ucrmCustomerIn
	if !decodeJSON(w, r, &in, 1<<20) {
		return
	}
	if !ro.Manager && !ro.Sales {
		writeErr(w, http.StatusForbidden, "ثبت مشتری فقط برای تیم فروش و مدیران است.")
		return
	}
	in.normalize()
	if msg := validateUcrmCustomer(in); msg != "" {
		writeErr(w, http.StatusBadRequest, msg)
		return
	}
	// کارشناس: غیرمدیر همیشه خودش (مدیر موقع تأیید می‌تواند عوض کند)
	if !ro.Manager {
		in.RepID = uid
	}
	if in.RepID <= 0 {
		writeErr(w, http.StatusBadRequest, "کارشناس فروش را انتخاب کنید.")
		return
	}
	if ro.Manager && !s.isUcrmRep(in.RepID) {
		writeErr(w, http.StatusBadRequest, "کارشناس انتخاب‌شده عضو تیم فروش نیست.")
		return
	}
	if s.checkCustomerDup(w, dupUnofficialCustomers, "مشتری", ucrmOrg, 0, in.Name, in.NationalID, "", "", false) {
		return
	}

	status := ucStPendingManager
	if ro.Manager {
		status = ucStPendingAccounting
	}
	tx, err := s.db.Begin()
	if err != nil {
		writeDBErr(w, "ucrmCreate begin", err)
		return
	}
	defer tx.Rollback()
	var evs []int64 // رویدادهای این درخواست → اعلان بعد از commit
	res, err := tx.Exec(`
		INSERT INTO ucrm_customers
		  (organization_id, type, name, national_id, province, city, address, note, proposed_target, monthly_target,
		   rep_id, status, source, created_by)
		VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'manual', ?)`,
		ucrmOrg, in.Type, in.Name, nullIfEmpty(in.NationalID), nullIfEmpty(in.Province), nullIfEmpty(in.City),
		nullIfEmpty(in.Address), nullIfEmpty(in.Note), nullIfZero(in.Target),
		// مدیر خودش ثبت کرده → مرحلهٔ مدیر ندارد، پس پیشنهادش همان تارگت ماهانه است
		nullIfZeroIf(in.Target, ro.Manager), in.RepID, status, uid)
	if err != nil {
		writeDBErr(w, "ucrmCreate insert", err)
		return
	}
	id, _ := res.LastInsertId()
	if err := savePhones(tx, id, in.Phones); err != nil {
		writeDBErr(w, "ucrmCreate phones", err)
		return
	}
	note := s.userName(uid) + " مشتری «" + in.Name + "» را ثبت کرد و برای تأیید مدیر فرستاد."
	if status == ucStPendingAccounting {
		note = s.userName(uid) + " (مدیر) مشتری «" + in.Name + "» را با کارشناس فروش «" + s.userName(in.RepID) +
			"» ثبت کرد و برای تأیید حسابداری فرستاد."
	}
	if ro.Manager && in.Target > 0 {
		t := in.Target
		if err := ucrmUpsertTarget(tx, id, "monthly", s.ucrmCurrentPeriods()["monthly"], &t, nil, nil, uid); err != nil {
			writeDBErr(w, "ucrmCreate target", err)
			return
		}
	}
	evs = append(evs, s.ucrmEvent(tx, id, uid, "created", note))
	if err := tx.Commit(); err != nil {
		writeDBErr(w, "ucrmCreate commit", err)
		return
	}
	s.ucrmNotifyAsync(r, evs)
	msg := "مشتری ثبت شد و درخواست برای مدیر ارسال شد."
	if status == ucStPendingAccounting {
		msg = "مشتری ثبت شد و برای تأیید حسابداری ارسال شد."
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "id": id, "status": status, "message": msg})
}

// ──────────────── PUT /ucrm/customers/{id} ────────────────

func (s *server) ucrmUpdateCustomer(w http.ResponseWriter, r *http.Request) {
	uid := userOf(r.Context()).ID
	ro := s.ucrmRolesOf(uid)
	cur, err := s.ucrmLoad(idParam(r), uid, ro)
	if err != nil {
		writeDBErr(w, "ucrmUpdate load", err)
		return
	}
	if cur == nil {
		writeErr(w, http.StatusNotFound, "مشتری یافت نشد")
		return
	}
	if !cur.CanEdit {
		writeErr(w, http.StatusForbidden, "اجازهٔ ویرایش این مشتری را ندارید.")
		return
	}
	var in ucrmCustomerIn
	if !decodeJSON(w, r, &in, 1<<20) {
		return
	}
	in.normalize()
	// کد ملی قدیمیِ دست‌نخورده دوباره سنجیده نشود (داده‌های قبلی ویرایش را قفل نکنند)
	chk := in
	if in.NationalID == cur.NationalID && in.Type == cur.Type {
		chk.NationalID = ""
	}
	if msg := validateUcrmCustomer(chk); msg != "" {
		writeErr(w, http.StatusBadRequest, msg)
		return
	}
	if !ro.Manager {
		in.RepID = cur.RepID
	} else if in.RepID != cur.RepID && !s.isUcrmRep(in.RepID) {
		writeErr(w, http.StatusBadRequest, "کارشناس انتخاب‌شده عضو تیم فروش نیست.")
		return
	}
	if s.checkCustomerDup(w, dupUnofficialCustomers, "مشتری", ucrmOrg, cur.ID, in.Name, in.NationalID, cur.Name, cur.NationalID, true) {
		return
	}

	status := cur.Status
	resubmit := in.Resubmit && cur.Status == ucStRejected
	if resubmit {
		status = ucStPendingManager
		if ro.Manager {
			status = ucStPendingAccounting
		}
	}

	tx, err := s.db.Begin()
	if err != nil {
		writeDBErr(w, "ucrmUpdate begin", err)
		return
	}
	defer tx.Rollback()
	var evs []int64 // رویدادهای این درخواست → اعلان بعد از commit
	// تارگت پیشنهادی فقط تا قبل از تأیید مدیر عوض می‌شود؛ بعد از آن تارگت هر دوره از صفحهٔ
	// «تارگت‌ها» (ucrm_targets.go) تعیین می‌شود → این‌جا دست نمی‌خورد.
	proposed := any(cur.ProposedTarget)
	if cur.ProposedTarget == nil {
		proposed = nil
	}
	if cur.Status == ucStPendingManager || (cur.Status == ucStRejected && cur.RejectedStage != "accounting") {
		proposed = nullIfZero(in.Target)
	}
	if _, err := tx.Exec(`
		UPDATE ucrm_customers
		SET type = ?, name = ?, national_id = ?, province = ?, city = ?, address = ?, note = ?, rep_id = ?, status = ?,
		    proposed_target = ?
		WHERE id = ?`,
		in.Type, in.Name, nullIfEmpty(in.NationalID), nullIfEmpty(in.Province), nullIfEmpty(in.City),
		nullIfEmpty(in.Address), nullIfEmpty(in.Note), in.RepID, status, proposed, cur.ID); err != nil {
		writeDBErr(w, "ucrmUpdate", err)
		return
	}
	if err := savePhones(tx, cur.ID, in.Phones); err != nil {
		writeDBErr(w, "ucrmUpdate phones", err)
		return
	}
	actor := s.userName(uid)
	if resubmit {
		to := "مدیر"
		if status == ucStPendingAccounting {
			to = "حسابداری"
		}
		evs = append(evs, s.ucrmEvent(tx, cur.ID, uid, "resubmitted", actor+" مشتری «"+in.Name+"» را اصلاح کرد و دوباره برای تأیید "+to+" فرستاد."))
	} else {
		evs = append(evs, s.ucrmEvent(tx, cur.ID, uid, "edited", actor+" اطلاعات مشتری «"+in.Name+"» را ویرایش کرد."))
	}
	if in.RepID != cur.RepID {
		evs = append(evs, s.ucrmEvent(tx, cur.ID, uid, "rep_changed", actor+" کارشناس فروش را از «"+cur.RepName+"» به «"+s.userName(in.RepID)+"» تغییر داد."))
	}
	if err := tx.Commit(); err != nil {
		writeDBErr(w, "ucrmUpdate commit", err)
		return
	}
	s.ucrmNotifyAsync(r, evs)
	msg := "تغییرات ذخیره شد."
	if resubmit {
		msg = "اصلاحات ذخیره شد و درخواست دوباره برای تأیید ارسال شد."
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "status": status, "message": msg})
}

// ──────────────── POST /ucrm/customers/{id}/approve  body: {rep_id?} ────────────────

func (s *server) ucrmApprove(w http.ResponseWriter, r *http.Request) {
	uid := userOf(r.Context()).ID
	ro := s.ucrmRolesOf(uid)
	var body struct {
		RepID  int64  `json:"rep_id"`
		Target *int64 `json:"target"` // فقط مرحلهٔ مدیر: تارگت ماهانه (تومان)؛ خالی = همان پیشنهاد کارشناس
	}
	if !decodeJSON(w, r, &body, 1<<16) {
		return
	}
	if body.Target != nil && (*body.Target < 0 || *body.Target > 1_000_000_000_000_000) {
		writeErr(w, http.StatusBadRequest, "تارگت ماهانه معتبر نیست.")
		return
	}
	cur, err := s.ucrmLoad(idParam(r), uid, ro)
	if err != nil {
		writeDBErr(w, "ucrmApprove load", err)
		return
	}
	if cur == nil {
		writeErr(w, http.StatusNotFound, "مشتری یافت نشد")
		return
	}
	if !cur.CanApprove {
		writeErr(w, http.StatusForbidden, "تأیید این مشتری در این مرحله با شما نیست.")
		return
	}
	actor := s.userName(uid)
	tx, err := s.db.Begin()
	if err != nil {
		writeDBErr(w, "ucrmApprove begin", err)
		return
	}
	defer tx.Rollback()
	var evs []int64 // رویدادهای این درخواست → اعلان بعد از commit

	var next, msg string
	if cur.Status == ucStPendingManager {
		next = ucStPendingAccounting
		repID := cur.RepID
		if body.RepID > 0 && body.RepID != cur.RepID {
			if !s.isUcrmRep(body.RepID) {
				writeErr(w, http.StatusBadRequest, "کارشناس انتخاب‌شده عضو تیم فروش نیست.")
				return
			}
			repID = body.RepID
			evs = append(evs, s.ucrmEvent(tx, cur.ID, uid, "rep_changed", actor+" کارشناس فروش را از «"+cur.RepName+"» به «"+s.userName(repID)+"» تغییر داد."))
		}
		var target int64
		if body.Target != nil {
			target = *body.Target
		} else if cur.ProposedTarget != nil {
			target = *cur.ProposedTarget
		}
		if _, err := tx.Exec("UPDATE ucrm_customers SET status = ?, rep_id = ?, monthly_target = ?, rejected_stage = NULL, reject_reason = NULL WHERE id = ?",
			next, repID, nullIfZero(target), cur.ID); err != nil {
			writeDBErr(w, "ucrmApprove", err)
			return
		}
		if target > 0 {
			if err := ucrmUpsertTarget(tx, cur.ID, "monthly", s.ucrmCurrentPeriods()["monthly"], &target, nil, nil, uid); err != nil {
				writeDBErr(w, "ucrmApprove target", err)
				return
			}
		}
		tgt := " و بدون تارگت ماهانه"
		if target > 0 {
			tgt = " و تارگت ماهانهٔ " + faMoney(target) + " تومان"
		}
		evs = append(evs, s.ucrmEvent(tx, cur.ID, uid, "manager_approved", actor+" (مدیر) مشتری «"+cur.Name+"» را با کارشناس فروش «"+s.userName(repID)+"»"+tgt+" تأیید کرد و برای حسابداری فرستاد."))
		msg = "تأیید شد و برای حسابداری ارسال شد."
	} else {
		next = ucStActive
		if _, err := tx.Exec("UPDATE ucrm_customers SET status = ?, rejected_stage = NULL, reject_reason = NULL WHERE id = ?",
			next, cur.ID); err != nil {
			writeDBErr(w, "ucrmApprove", err)
			return
		}
		evs = append(evs, s.ucrmEvent(tx, cur.ID, uid, "accounting_approved", actor+" (حسابداری) مشتری «"+cur.Name+"» را تأیید کرد؛ مشتری فعال شد."))
		msg = "تأیید شد؛ مشتری فعال شد."
	}
	if err := tx.Commit(); err != nil {
		writeDBErr(w, "ucrmApprove commit", err)
		return
	}
	s.ucrmNotifyAsync(r, evs)
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "status": next, "message": msg})
}

// ──────────────── POST /ucrm/customers/{id}/reject  body: {reason} ────────────────

func (s *server) ucrmReject(w http.ResponseWriter, r *http.Request) {
	uid := userOf(r.Context()).ID
	ro := s.ucrmRolesOf(uid)
	var body struct {
		Reason string `json:"reason"`
	}
	if !decodeJSON(w, r, &body, 1<<16) {
		return
	}
	body.Reason = strings.TrimSpace(body.Reason)
	if len([]rune(body.Reason)) < 3 {
		writeErr(w, http.StatusBadRequest, "دلیل رد را بنویسید.")
		return
	}
	cur, err := s.ucrmLoad(idParam(r), uid, ro)
	if err != nil {
		writeDBErr(w, "ucrmReject load", err)
		return
	}
	if cur == nil {
		writeErr(w, http.StatusNotFound, "مشتری یافت نشد")
		return
	}
	if !cur.CanApprove {
		writeErr(w, http.StatusForbidden, "رد این مشتری در این مرحله با شما نیست.")
		return
	}
	stage, who := "manager", "مدیر"
	if cur.Status == ucStPendingAccounting {
		stage, who = "accounting", "حسابداری"
	}
	tx, err := s.db.Begin()
	if err != nil {
		writeDBErr(w, "ucrmReject begin", err)
		return
	}
	defer tx.Rollback()
	var evs []int64 // رویدادهای این درخواست → اعلان بعد از commit
	if _, err := tx.Exec("UPDATE ucrm_customers SET status = ?, rejected_stage = ?, reject_reason = ? WHERE id = ?",
		ucStRejected, stage, body.Reason, cur.ID); err != nil {
		writeDBErr(w, "ucrmReject", err)
		return
	}
	evs = append(evs, s.ucrmEvent(tx, cur.ID, uid, stage+"_rejected", s.userName(uid)+" ("+who+") مشتری «"+cur.Name+"» را رد کرد و برای اصلاح به کارشناس فروش برگرداند. دلیل: «"+body.Reason+"»"))
	if err := tx.Commit(); err != nil {
		writeDBErr(w, "ucrmReject commit", err)
		return
	}
	s.ucrmNotifyAsync(r, evs)
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "message": "رد شد و برای اصلاح به کارشناس فروش برگشت."})
}

// ──────────────── DELETE /ucrm/customers/{id} — حذف نرم ────────────────

func (s *server) ucrmDeleteCustomer(w http.ResponseWriter, r *http.Request) {
	uid := userOf(r.Context()).ID
	ro := s.ucrmRolesOf(uid)
	cur, err := s.ucrmLoad(idParam(r), uid, ro)
	if err != nil {
		writeDBErr(w, "ucrmDelete load", err)
		return
	}
	if cur == nil {
		writeErr(w, http.StatusNotFound, "مشتری یافت نشد")
		return
	}
	if !cur.CanDelete {
		writeErr(w, http.StatusForbidden, "اجازهٔ حذف این مشتری را ندارید.")
		return
	}
	tx, err := s.db.Begin()
	if err != nil {
		writeDBErr(w, "ucrmDelete begin", err)
		return
	}
	defer tx.Rollback()
	var evs []int64 // رویدادهای این درخواست → اعلان بعد از commit
	if _, err := tx.Exec("UPDATE ucrm_customers SET is_deleted = 1, deleted_by = ?, deleted_at = NOW() WHERE id = ?", uid, cur.ID); err != nil {
		writeDBErr(w, "ucrmDelete", err)
		return
	}
	evs = append(evs, s.ucrmEvent(tx, cur.ID, uid, "deleted", s.userName(uid)+" مشتری «"+cur.Name+"» را حذف کرد."))
	if err := tx.Commit(); err != nil {
		writeDBErr(w, "ucrmDelete commit", err)
		return
	}
	s.ucrmNotifyAsync(r, evs)
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "message": "مشتری حذف شد."})
}

// ──────────────── GET /ucrm/customers/phone-check?phones=a,b&exclude=id ────────────────
// شمارهٔ تکراری فقط هشدار است (جلوی ثبت را نمی‌گیرد). بین همهٔ مشتری‌ها می‌گردد،
// حتی آن‌هایی که کارشناس جاری نمی‌بیند — تا مشتری تکراری دوبار ثبت نشود.

func (s *server) ucrmPhoneCheck(w http.ResponseWriter, r *http.Request) {
	exclude, _ := strconv.ParseInt(r.URL.Query().Get("exclude"), 10, 64)
	out := []map[string]any{}
	seen := map[string]bool{}
	for _, p := range strings.Split(r.URL.Query().Get("phones"), ",") {
		p = digitsOnly(p)
		if p == "" || seen[p] || len(seen) >= 20 {
			continue
		}
		seen[p] = true
		rows, err := s.db.Query(`
			SELECT c.name, TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')))
			FROM ucrm_customer_phones ph
			JOIN ucrm_customers c ON c.id = ph.customer_id AND c.is_deleted = 0 AND c.organization_id = ?
			LEFT JOIN users u ON u.id = c.rep_id
			WHERE ph.phone = ? AND c.id <> ? LIMIT 3`, ucrmOrg, p, exclude)
		if err != nil {
			writeDBErr(w, "ucrmPhoneCheck", err)
			return
		}
		for rows.Next() {
			var name, rep string
			if rows.Scan(&name, &rep) == nil {
				out = append(out, map[string]any{"phone": p, "customer": name, "rep": rep})
			}
		}
		rows.Close()
	}
	writeJSON(w, http.StatusOK, map[string]any{"items": out})
}

// ──────────────── POST /ucrm/customers/import  body: {rows:[...]} ────────────────
// همه «در انتظار مدیر» ثبت می‌شوند (حتی اگر مدیر بارگذاری کند). ردیف نامعتبر رد
// می‌شود و بقیه ثبت می‌شوند؛ خطاها با شمارهٔ ردیف برمی‌گردند.

func (s *server) ucrmImport(w http.ResponseWriter, r *http.Request) {
	uid := userOf(r.Context()).ID
	ro := s.ucrmRolesOf(uid)
	var body struct {
		Rows []ucrmCustomerIn `json:"rows"`
	}
	if !decodeJSON(w, r, &body, 8<<20) {
		return
	}
	if !ro.Manager && !ro.Sales {
		writeErr(w, http.StatusForbidden, "ثبت مشتری فقط برای تیم فروش و مدیران است.")
		return
	}
	if len(body.Rows) > 2000 {
		writeErr(w, http.StatusBadRequest, "حداکثر ۲۰۰۰ ردیف در هر بار بارگذاری.")
		return
	}

	// نام کارشناس → شناسه (فقط برای مدیر؛ کارشناس همیشه خودش)
	repByName := map[string]int64{}
	if ro.Manager {
		rows, err := s.db.Query(`
			SELECT u.id, TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')))
			FROM users u WHERE u.is_active = 1 AND u.organization_id = 1 AND u.id <> 1`)
		if err == nil {
			for rows.Next() {
				var id int64
				var n string
				if rows.Scan(&id, &n) == nil {
					repByName[strings.Join(strings.Fields(n), " ")] = id
				}
			}
			rows.Close()
		}
	}

	tx, err := s.db.Begin()
	if err != nil {
		writeDBErr(w, "ucrmImport begin", err)
		return
	}
	defer tx.Rollback()
	var evs []int64 // رویدادهای این درخواست → اعلان بعد از commit

	actor := s.userName(uid)
	inserted := 0
	errs := []map[string]any{}
	ix := s.loadDupIndex(dupUnofficialCustomers, ucrmOrg)
	for i, row := range body.Rows {
		n := i + 2 // ردیف ۱ اکسل = عنوان ستون‌ها
		row.normalize()
		if msg := validateUcrmCustomer(row); msg != "" {
			errs = append(errs, map[string]any{"row": n, "message": msg})
			continue
		}
		rep := uid
		if ro.Manager {
			if row.RepName == "" {
				errs = append(errs, map[string]any{"row": n, "message": "کارشناس فروش خالی است."})
				continue
			}
			id, ok := repByName[strings.Join(strings.Fields(row.RepName), " ")]
			if !ok || !s.isUcrmRep(id) {
				errs = append(errs, map[string]any{"row": n, "message": "کارشناس «" + row.RepName + "» در تیم فروش پیدا نشد."})
				continue
			}
			rep = id
		}
		// نام یا کد/شناسهٔ ملی تکراری (با مشتری‌های موجود یا ردیف‌های قبلی همین فایل)
		if msg := ix.check("مشتری", row.Name, row.NationalID); msg != "" {
			errs = append(errs, map[string]any{"row": n, "message": msg})
			continue
		}
		res, err := tx.Exec(`
			INSERT INTO ucrm_customers
			  (organization_id, type, name, national_id, province, city, address, note, proposed_target, rep_id, status, source, created_by)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'excel', ?)`,
			ucrmOrg, row.Type, row.Name, nullIfEmpty(row.NationalID), nullIfEmpty(row.Province), nullIfEmpty(row.City),
			nullIfEmpty(row.Address), nullIfEmpty(row.Note), nullIfZero(row.Target), rep, ucStPendingManager, uid)
		if err != nil {
			writeDBErr(w, "ucrmImport insert", err)
			return
		}
		id, _ := res.LastInsertId()
		if err := savePhones(tx, id, row.Phones); err != nil {
			writeDBErr(w, "ucrmImport phones", err)
			return
		}
		evs = append(evs, s.ucrmEvent(tx, id, uid, "created", actor+" مشتری «"+row.Name+"» را از فایل اکسل بارگذاری کرد و برای تأیید مدیر فرستاد."))
		inserted++
	}
	if err := tx.Commit(); err != nil {
		writeDBErr(w, "ucrmImport commit", err)
		return
	}
	s.ucrmNotifyAsync(r, evs)
	sort.Slice(errs, func(a, b int) bool { return errs[a]["row"].(int) < errs[b]["row"].(int) })
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "inserted": inserted, "errors": errs})
}

// ──────────────── تنظیمات (بازه‌های رنگ تارگت) ────────────────

type ucrmSettings struct {
	TargetRedBelow  int `json:"target_red_below"`
	TargetGreenFrom int `json:"target_green_from"`
}

func (s *server) ucrmLoadSettings() ucrmSettings {
	st := ucrmSettings{TargetRedBelow: 80, TargetGreenFrom: 100}
	_ = s.db.QueryRow("SELECT target_red_below, target_green_from FROM ucrm_settings WHERE organization_id = ?", ucrmOrg).
		Scan(&st.TargetRedBelow, &st.TargetGreenFrom)
	return st
}

// PUT /ucrm/settings — فقط مدیر
func (s *server) ucrmPutSettings(w http.ResponseWriter, r *http.Request) {
	uid := userOf(r.Context()).ID
	if !s.ucrmRolesOf(uid).Manager {
		writeErr(w, http.StatusForbidden, "فقط مدیر می‌تواند تنظیمات CRM را تغییر دهد.")
		return
	}
	var in ucrmSettings
	if !decodeJSON(w, r, &in, 1<<12) {
		return
	}
	if in.TargetRedBelow < 1 || in.TargetGreenFrom > 1000 || in.TargetRedBelow >= in.TargetGreenFrom {
		writeErr(w, http.StatusBadRequest, "مرز قرمز باید از مرز سبز کمتر باشد (هر دو درصد مثبت).")
		return
	}
	if _, err := s.db.Exec(`
		INSERT INTO ucrm_settings (organization_id, target_red_below, target_green_from, updated_by)
		VALUES (?, ?, ?, ?)
		ON DUPLICATE KEY UPDATE target_red_below = VALUES(target_red_below),
		                        target_green_from = VALUES(target_green_from), updated_by = VALUES(updated_by)`,
		ucrmOrg, in.TargetRedBelow, in.TargetGreenFrom, uid); err != nil {
		writeDBErr(w, "ucrmPutSettings", err)
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "settings": in})
}
