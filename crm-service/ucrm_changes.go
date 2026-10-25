package main

// ═══════════════════════════════════════════════════════════════════
//  CRM جدید — «درخواست تغییر اطلاعات مشتری» (طرح ۱۴۰۵/۰۸/۰۲).
//
//  ویرایش اطلاعات مشتریِ فعال از کادر «اطلاعات پایه» صفحهٔ جزئیات مشتری فوری اعمال
//  نمی‌شود: کارشناس همان مشتری یا مدیر یک درخواست می‌فرستد → حسابداری (بدون مرحلهٔ
//  مدیر) تأیید یا رد می‌کند (رد = دلیل اجباری) → با تأیید، تغییرها اعمال می‌شوند.
//  برای هر مشتری حداکثر یک درخواست در انتظار. کار حسابداری در «کارهای من» از
//  api/ucrm/notify-events.php ساخته می‌شود (مرحلهٔ change).
//
//  مشتریِ هنوز تأییدنشده (در انتظار / ردشده) همان مسیر قبلی را دارد (فرم کامل + ارسال دوباره).
// ═══════════════════════════════════════════════════════════════════

import (
	"database/sql"
	"encoding/json"
	"net/http"
	"strconv"
	"strings"
	"time"
)

const (
	chgPending   = "pending"
	chgApproved  = "approved"
	chgRejected  = "rejected"
	chgCancelled = "cancelled"
)

// ucrmChangeIn: فیلدهای قابل ویرایش در کادر «اطلاعات پایه»
type ucrmChangeIn struct {
	Name           string   `json:"name"`
	AccountingName string   `json:"accounting_name"`
	Phones         []string `json:"phones"`
	Province       string   `json:"province"`
	City           string   `json:"city"`
	Address        string   `json:"address"`
	EconomicCode   string   `json:"economic_code"`
	NationalID     string   `json:"national_id"`
}

// ucrmChange: یک فیلد عوض‌شده (old/new متن یا فهرست شماره‌ها)
type ucrmChange struct {
	Field string `json:"field"`
	Label string `json:"label"`
	Old   any    `json:"old"`
	New   any    `json:"new"`
}

type ucrmChangeReq struct {
	ID            int64        `json:"id"`
	CustomerID    int64        `json:"customer_id"`
	RequestedBy   int64        `json:"requested_by"`
	RequesterName string       `json:"requester_name"`
	Changes       []ucrmChange `json:"changes"`
	Status        string       `json:"status"`
	RejectReason  string       `json:"reject_reason"`
	DecidedByName string       `json:"decided_by_name"`
	DecidedAt     string       `json:"decided_at"`
	CreatedAt     string       `json:"created_at"`
	CanDecide     bool         `json:"can_decide"`
	CanCancel     bool         `json:"can_cancel"`
}

func sameStrings(a, b []string) bool {
	if len(a) != len(b) {
		return false
	}
	for i := range a {
		if a[i] != b[i] {
			return false
		}
	}
	return true
}

// changeLabels: برچسب فارسی هر فیلد (برای صفحهٔ تأیید و متن کار حسابداری)
func changeLabel(field, ctype string) string {
	switch field {
	case "name":
		if ctype == "individual" {
			return "نام"
		}
		return "نام شرکت"
	case "accounting_name":
		return "نام در نرم‌افزار حسابداری"
	case "phones":
		return "شماره تماس"
	case "province":
		return "استان"
	case "city":
		return "شهر"
	case "address":
		return "آدرس"
	case "economic_code":
		return "کد اقتصادی"
	case "national_id":
		if ctype == "individual" {
			return "کد ملی"
		}
		return "شناسهٔ ملی"
	}
	return field
}

func (s *server) loadChangeReq(sc interface{ Scan(...any) error }) (*ucrmChangeReq, error) {
	var q ucrmChangeReq
	var raw string
	var created time.Time
	var decided sql.NullTime
	if err := sc.Scan(&q.ID, &q.CustomerID, &q.RequestedBy, &q.RequesterName, &raw, &q.Status, &q.RejectReason,
		&q.DecidedByName, &decided, &created); err != nil {
		return nil, err
	}
	_ = json.Unmarshal([]byte(raw), &q.Changes)
	q.CreatedAt = created.Format("2006-01-02 15:04:05")
	q.DecidedAt = fmtTime(decided)
	return &q, nil
}

const changeReqSelect = `
	SELECT q.id, q.customer_id, q.requested_by,
	       TRIM(CONCAT(COALESCE(ru.first_name,''), ' ', COALESCE(ru.last_name,''))),
	       q.changes, q.status, COALESCE(q.reject_reason,''),
	       TRIM(CONCAT(COALESCE(du.first_name,''), ' ', COALESCE(du.last_name,''))),
	       q.decided_at, q.created_at
	FROM ucrm_change_requests q
	LEFT JOIN users ru ON ru.id = q.requested_by
	LEFT JOIN users du ON du.id = q.decided_by`

// latestChangeReq: آخرین درخواست تغییر مشتری (هر وضعیتی) — صفحهٔ جزئیات «در انتظار» یا «ردشده» نشان می‌دهد
func (s *server) latestChangeReq(customerID, uid int64, ro ucrmRoles) *ucrmChangeReq {
	q, err := s.loadChangeReq(s.db.QueryRow(changeReqSelect+" WHERE q.customer_id = ? ORDER BY q.id DESC LIMIT 1", customerID))
	if err != nil {
		return nil
	}
	q.CanDecide = q.Status == chgPending && (ro.Accounting || superAdminIDs[uid])
	q.CanCancel = q.Status == chgPending && (q.RequestedBy == uid || ro.Manager)
	return q
}

// POST /ucrm/customers/{id}/change-request
func (s *server) ucrmRequestChange(w http.ResponseWriter, r *http.Request) {
	uid := userOf(r.Context()).ID
	ro := s.ucrmRolesOf(uid)
	c, err := s.ucrmLoad(idParam(r), uid, ro)
	if err != nil {
		writeDBErr(w, "ucrmRequestChange", err)
		return
	}
	if c == nil {
		writeErr(w, http.StatusNotFound, "مشتری یافت نشد")
		return
	}
	if c.Status != ucStActive {
		writeErr(w, http.StatusConflict, "این مشتری هنوز تأیید نشده؛ از فرم «ویرایش مشتری» اصلاح کنید.")
		return
	}
	if !(c.RepID == uid || ro.Manager) {
		writeErr(w, http.StatusForbidden, "فقط کارشناس همین مشتری یا مدیر می‌تواند درخواست تغییر بفرستد.")
		return
	}
	var in ucrmChangeIn
	if !decodeJSON(w, r, &in, 1<<16) {
		return
	}
	// همان یکسان‌سازی و قواعد فرم مشتری (ucrm_customers.go)
	norm := ucrmCustomerIn{Type: c.Type, Name: in.Name, NationalID: in.NationalID, EconomicCode: in.EconomicCode,
		Province: in.Province, City: in.City, Address: in.Address, Phones: in.Phones}
	norm.normalize()
	accName := faLetters(strings.Join(strings.Fields(in.AccountingName), " "))
	if len([]rune(accName)) > 255 {
		writeErr(w, http.StatusBadRequest, "نام حسابداری حداکثر ۲۵۵ نویسه است.")
		return
	}
	chk := norm
	if chk.NationalID == c.NationalID {
		chk.NationalID = ""
	}
	if chk.EconomicCode == c.EconomicCode {
		chk.EconomicCode = ""
	}
	if msg := validateUcrmCustomer(chk); msg != "" {
		writeErr(w, http.StatusBadRequest, msg)
		return
	}
	if s.checkCustomerDup(w, dupUnofficialCustomers, "مشتری", ucrmOrg, c.ID, norm.Name, norm.NationalID, c.Name, c.NationalID, true) {
		return
	}

	changes := []ucrmChange{}
	add := func(field string, old, new any) {
		changes = append(changes, ucrmChange{Field: field, Label: changeLabel(field, c.Type), Old: old, New: new})
	}
	if norm.Name != c.Name {
		add("name", c.Name, norm.Name)
	}
	if accName != c.AccountingName {
		add("accounting_name", c.AccountingName, accName)
	}
	if !sameStrings(norm.Phones, c.Phones) {
		add("phones", c.Phones, norm.Phones)
	}
	if norm.Province != c.Province {
		add("province", c.Province, norm.Province)
	}
	if norm.City != c.City {
		add("city", c.City, norm.City)
	}
	if norm.Address != c.Address {
		add("address", c.Address, norm.Address)
	}
	if norm.EconomicCode != c.EconomicCode {
		add("economic_code", c.EconomicCode, norm.EconomicCode)
	}
	if norm.NationalID != c.NationalID {
		add("national_id", c.NationalID, norm.NationalID)
	}
	if len(changes) == 0 {
		writeErr(w, http.StatusBadRequest, "تغییری ثبت نشده است.")
		return
	}

	tx, err := s.db.Begin()
	if err != nil {
		writeDBErr(w, "ucrmRequestChange begin", err)
		return
	}
	defer tx.Rollback()
	var pend int
	if err := tx.QueryRow("SELECT COUNT(*) FROM ucrm_change_requests WHERE customer_id = ? AND status = ? FOR UPDATE",
		c.ID, chgPending).Scan(&pend); err != nil {
		writeDBErr(w, "ucrmRequestChange pending", err)
		return
	}
	if pend > 0 {
		writeErr(w, http.StatusConflict, "یک درخواست تغییر برای این مشتری در انتظار تأیید حسابداری است؛ تا تصمیم حسابداری درخواست دیگری ثبت نمی‌شود.")
		return
	}
	raw, _ := json.Marshal(changes)
	if _, err := tx.Exec("INSERT INTO ucrm_change_requests (customer_id, requested_by, changes) VALUES (?, ?, ?)",
		c.ID, uid, string(raw)); err != nil {
		writeDBErr(w, "ucrmRequestChange insert", err)
		return
	}
	labels := make([]string, len(changes))
	for i, ch := range changes {
		labels[i] = ch.Label
	}
	ev := s.ucrmEvent(tx, c.ID, uid, "change_requested",
		s.userName(uid)+" درخواست تغییر اطلاعات مشتری «"+c.Name+"» ("+strings.Join(labels, "، ")+") را برای تأیید حسابداری فرستاد.")
	if err := tx.Commit(); err != nil {
		writeDBErr(w, "ucrmRequestChange commit", err)
		return
	}
	s.ucrmNotifyAsync(r, []int64{ev})
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "message": "درخواست تغییر برای تأیید حسابداری فرستاده شد."})
}

// تصمیم روی درخواست: approve | reject | cancel — POST /ucrm/change-requests/{id}/{action}
func (s *server) ucrmDecideChange(action string) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		uid := userOf(r.Context()).ID
		ro := s.ucrmRolesOf(uid)
		rid, _ := strconv.ParseInt(r.PathValue("id"), 10, 64)
		var in struct {
			Reason string `json:"reason"`
		}
		if !decodeJSON(w, r, &in, 1<<14) {
			return
		}
		in.Reason = strings.TrimSpace(in.Reason)
		if action == "reject" && in.Reason == "" {
			writeErr(w, http.StatusBadRequest, "دلیل رد را بنویسید.")
			return
		}

		tx, err := s.db.Begin()
		if err != nil {
			writeDBErr(w, "ucrmDecideChange begin", err)
			return
		}
		defer tx.Rollback()
		q, err := s.loadChangeReq(tx.QueryRow(changeReqSelect+" WHERE q.id = ? FOR UPDATE", rid))
		if err == sql.ErrNoRows {
			writeErr(w, http.StatusNotFound, "درخواست یافت نشد")
			return
		} else if err != nil {
			writeDBErr(w, "ucrmDecideChange load", err)
			return
		}
		c, err := s.ucrmLoad(q.CustomerID, uid, ro)
		if err != nil {
			writeDBErr(w, "ucrmDecideChange customer", err)
			return
		}
		if c == nil {
			writeErr(w, http.StatusNotFound, "مشتری یافت نشد")
			return
		}
		if q.Status != chgPending {
			writeErr(w, http.StatusConflict, "این درخواست قبلا بررسی شده است.")
			return
		}
		canDecide := ro.Accounting || superAdminIDs[uid]
		switch action {
		case "approve", "reject":
			if !canDecide {
				writeErr(w, http.StatusForbidden, "تأیید یا رد تغییرات مشتری با حسابداری است.")
				return
			}
		case "cancel":
			if !(q.RequestedBy == uid || ro.Manager) {
				writeErr(w, http.StatusForbidden, "فقط درخواست‌دهنده یا مدیر می‌تواند درخواست را لغو کند.")
				return
			}
		}

		actor := s.userName(uid)
		var evAction, note, status, msg string
		switch action {
		case "approve":
			if err := s.applyCustomerChanges(w, tx, c, q.Changes); err != nil {
				return // پیام خطا را applyCustomerChanges نوشته
			}
			status, evAction, msg = chgApproved, "change_approved", "تغییرات تأیید و روی مشتری اعمال شد."
			note = actor + " (حسابداری) تغییر اطلاعات مشتری «" + c.Name + "» را تأیید کرد؛ تغییرات اعمال شد."
		case "reject":
			status, evAction, msg = chgRejected, "change_rejected", "درخواست تغییر رد شد."
			note = actor + " (حسابداری) درخواست تغییر اطلاعات مشتری «" + c.Name + "» را رد کرد. دلیل: «" + in.Reason + "»"
		default:
			status, evAction, msg = chgCancelled, "change_cancelled", "درخواست تغییر لغو شد."
			note = actor + " درخواست تغییر اطلاعات مشتری «" + c.Name + "» را لغو کرد."
		}
		if _, err := tx.Exec(`UPDATE ucrm_change_requests SET status = ?, reject_reason = ?, decided_by = ?, decided_at = NOW() WHERE id = ?`,
			status, nullIfEmpty(in.Reason), uid, q.ID); err != nil {
			writeDBErr(w, "ucrmDecideChange update", err)
			return
		}
		ev := s.ucrmEvent(tx, c.ID, uid, evAction, note)
		if err := tx.Commit(); err != nil {
			writeDBErr(w, "ucrmDecideChange commit", err)
			return
		}
		s.ucrmNotifyAsync(r, []int64{ev})
		writeJSON(w, http.StatusOK, map[string]any{"success": true, "message": msg})
	}
}

// applyCustomerChanges: تغییرهای تأییدشده را روی مشتری می‌نویسد (داخل همان تراکنش).
// تکراری بودن نام/شناسه دوباره سنجیده می‌شود (ممکن است بین درخواست و تأیید مشتری دیگری ثبت شده باشد).
func (s *server) applyCustomerChanges(w http.ResponseWriter, tx *sql.Tx, c *ucrmCustomerOut, changes []ucrmChange) error {
	next := *c
	sets, args := []string{}, []any{}
	var phones []string
	str := func(v any) string { s, _ := v.(string); return s }
	for _, ch := range changes {
		switch ch.Field {
		case "name":
			next.Name = str(ch.New)
			sets, args = append(sets, "name = ?"), append(args, next.Name)
		case "accounting_name":
			sets, args = append(sets, "accounting_name = ?"), append(args, nullIfEmpty(str(ch.New)))
		case "province":
			sets, args = append(sets, "province = ?"), append(args, nullIfEmpty(str(ch.New)))
		case "city":
			sets, args = append(sets, "city = ?"), append(args, nullIfEmpty(str(ch.New)))
		case "address":
			sets, args = append(sets, "address = ?"), append(args, nullIfEmpty(str(ch.New)))
		case "economic_code":
			sets, args = append(sets, "economic_code = ?"), append(args, nullIfEmpty(str(ch.New)))
		case "national_id":
			next.NationalID = str(ch.New)
			sets, args = append(sets, "national_id = ?"), append(args, nullIfEmpty(next.NationalID))
		case "phones":
			if list, ok := ch.New.([]any); ok {
				for _, p := range list {
					if ps, ok := p.(string); ok && ps != "" {
						phones = append(phones, ps)
					}
				}
			}
			if len(phones) == 0 {
				writeErr(w, http.StatusBadRequest, "دست‌کم یک شماره تماس لازم است.")
				return sql.ErrNoRows
			}
		}
	}
	if s.checkCustomerDup(w, dupUnofficialCustomers, "مشتری", ucrmOrg, c.ID, next.Name, next.NationalID, c.Name, c.NationalID, true) {
		return sql.ErrNoRows
	}
	if len(sets) > 0 {
		if _, err := tx.Exec("UPDATE ucrm_customers SET "+strings.Join(sets, ", ")+" WHERE id = ?", append(args, c.ID)...); err != nil {
			writeDBErr(w, "applyCustomerChanges", err)
			return err
		}
	}
	if phones != nil {
		if err := savePhones(tx, c.ID, phones); err != nil {
			writeDBErr(w, "applyCustomerChanges phones", err)
			return err
		}
	}
	return nil
}
