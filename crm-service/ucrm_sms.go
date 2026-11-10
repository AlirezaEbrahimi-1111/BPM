package main

// ═══════════════════════════════════════════════════════════════════
//  پیامک به مشتری (بخش ۹ طرح CRM، ۱۴۰۵/۰۸/۱۹).
//
//  قالب = الگوی تأییدشدهٔ ملی‌پیامک (خط خدماتی): body_id + متنِ پیش‌نمایش با
//  {0} {1}… + برچسبِ هر متغیر. مدیریتِ قالب فقط مدیر؛ ارسال = کارشناسِ همان
//  مشتری یا مدیر، فقط به شماره‌های خودِ مشتری.
//  قالبِ تازه بدونِ کدِ الگو → مستقیم در ملی‌پیامک ساخته و برای تأیید فرستاده
//  می‌شود (ShareService/AddBody)؛ وضعیت/توضیحِ کارشناس با GetBodies خوانده می‌شود.
//  فقط قالبِ «تأییدشده» (approval_status = 1) در ارسال به مشتری دیده می‌شود.
//  تماس با ملی‌پیامک در PHP است (api/ucrm/sms-pattern.php) — اطلاعات حساب یک جا.
//  ارسالِ واقعی: این سرویس ردیفِ ucrm_sms_log (queued) را می‌سازد و همان لحظه
//  api/ucrm/sms-deliver.php را با توکنِ همین کاربر صدا می‌زند (اطلاعات حسابِ
//  پیامک فقط در includes/sms.php است)؛ نتیجه (sent/failed) را PHP در همان ردیف
//  می‌نویسد. تاریخچه = تب «تاریخچهٔ پیگیری ← پیامک‌ها» در pages/ucrm-customer.php.
// ═══════════════════════════════════════════════════════════════════

import (
	"bytes"
	"database/sql"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"regexp"
	"strconv"
	"strings"
	"time"
)

type ucrmSmsTemplate struct {
	ID             int64    `json:"id"`
	Title          string   `json:"title"`
	BodyID         int64    `json:"body_id"`
	Preview        string   `json:"preview"`
	VarLabels      []string `json:"var_labels"`
	IsActive       bool     `json:"is_active"`
	ApprovalStatus *int     `json:"approval_status"` // nil = هنوز خوانده نشده؛ BodyStatus ملی‌پیامک
	ReviewNote     string   `json:"review_note"`
}

// وضعیت‌های BodyStatus ملی‌پیامک
const (
	smsStWait      = 0
	smsStApproved  = 1
	smsStNeedsEdit = 4
	smsStDeleted   = 5
)

var smsPlaceholderRe = regexp.MustCompile(`\{(\d+)\}`)

// smsRender: {0} {1}… در متنِ پیش‌نمایش با مقدارها
func smsRender(preview string, args []string) string {
	return smsPlaceholderRe.ReplaceAllStringFunc(preview, func(m string) string {
		i, _ := strconv.Atoi(m[1 : len(m)-1])
		if i < len(args) {
			return args[i]
		}
		return m
	})
}

func (s *server) smsTemplates(onlyActive bool) ([]ucrmSmsTemplate, error) {
	q := `SELECT id, title, body_id, preview, COALESCE(var_labels, '[]'), is_active, approval_status, COALESCE(review_note, '')
		FROM ucrm_sms_templates WHERE organization_id = ? AND is_deleted = 0`
	if onlyActive {
		q += " AND is_active = 1 AND approval_status = 1" // فقط تأییدشده در ملی‌پیامک
	}
	rows, err := s.db.Query(q+" ORDER BY title", ucrmOrg)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	out := []ucrmSmsTemplate{}
	for rows.Next() {
		var t ucrmSmsTemplate
		var labels string
		var active int
		var st sql.NullInt64
		if err := rows.Scan(&t.ID, &t.Title, &t.BodyID, &t.Preview, &labels, &active, &st, &t.ReviewNote); err != nil {
			return nil, err
		}
		if st.Valid {
			v := int(st.Int64)
			t.ApprovalStatus = &v
		}
		_ = json.Unmarshal([]byte(labels), &t.VarLabels)
		if t.VarLabels == nil {
			t.VarLabels = []string{}
		}
		t.IsActive = active == 1
		out = append(out, t)
	}
	return out, rows.Err()
}

// ──────────────── GET /ucrm/sms-templates[?all=1] ────────────────

func (s *server) ucrmListSmsTemplates(w http.ResponseWriter, r *http.Request) {
	ro := s.ucrmRolesOf(userOf(r.Context()).ID)
	items, err := s.smsTemplates(!(ro.Manager && r.URL.Query().Get("all") == "1"))
	if err != nil {
		writeDBErr(w, "ucrmListSmsTemplates", err)
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"items": items, "can_manage": ro.Manager})
}

type smsTemplateIn struct {
	Title     string   `json:"title"`
	BodyID    int64    `json:"body_id"`
	Preview   string   `json:"preview"`
	VarLabels []string `json:"var_labels"`
	IsActive  bool     `json:"is_active"`
}

func (in *smsTemplateIn) clean() string {
	in.Title = strings.TrimSpace(in.Title)
	in.Preview = strings.TrimSpace(in.Preview)
	labels := []string{}
	for _, l := range in.VarLabels {
		if l = strings.TrimSpace(l); l != "" {
			labels = append(labels, l)
		}
	}
	in.VarLabels = labels
	if in.Title == "" || len([]rune(in.Title)) > 150 {
		return "عنوان قالب را وارد کنید (حداکثر ۱۵۰ نویسه)."
	}
	if in.BodyID < 0 {
		return "کد الگو نامعتبر است."
	}
	if in.Preview == "" {
		return "متن الگو را برای پیش‌نمایش وارد کنید."
	}
	// تعداد متغیرها = بزرگ‌ترین {n} + ۱
	maxIdx := -1
	for _, m := range smsPlaceholderRe.FindAllStringSubmatch(in.Preview, -1) {
		if i, _ := strconv.Atoi(m[1]); i > maxIdx {
			maxIdx = i
		}
	}
	if len(in.VarLabels) != maxIdx+1 {
		return fmt.Sprintf("متن الگو %d متغیر ({0} تا {%d}) دارد؛ برای هر کدام یک برچسب وارد کنید.", maxIdx+1, maxIdx)
	}
	return ""
}

// smsPatternCall: api/ucrm/sms-pattern.php با توکنِ همین مدیر (اطلاعات حساب پیامک فقط در PHP)
func (s *server) smsPatternCall(r *http.Request, payload map[string]any) (map[string]any, string) {
	body, _ := json.Marshal(payload)
	req, err := http.NewRequest("POST", strings.TrimRight(s.cfg.BaseURL, "/")+"/api/ucrm/sms-pattern.php", bytes.NewReader(body))
	if err != nil {
		return nil, "خطای داخلی"
	}
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Authorization", r.Header.Get("Authorization"))
	resp, err := (&http.Client{Timeout: 30 * time.Second}).Do(req)
	if err != nil {
		return nil, "ارتباط با ملی‌پیامک برقرار نشد"
	}
	defer resp.Body.Close()
	raw, _ := io.ReadAll(io.LimitReader(resp.Body, 1<<20))
	var out map[string]any
	if json.Unmarshal(raw, &out) != nil {
		return nil, fmt.Sprintf("پاسخ نامعتبر (%d)", resp.StatusCode)
	}
	if ok, _ := out["ok"].(bool); !ok {
		msg, _ := out["error"].(string)
		if msg == "" {
			msg = "خطای ملی‌پیامک"
		}
		return nil, msg
	}
	return out, ""
}

// POST /ucrm/sms-templates  و  PUT /ucrm/sms-templates/{id}
// تازه + بدونِ کدِ الگو → AddBody در ملی‌پیامک (در انتظار تأیید). با کد → قالبِ ساخته‌شده در پنل.
// ویرایشِ متن/عنوان فقط وقتی ملی‌پیامک «نیاز به اصلاح» گفته (EditBody → دوباره در انتظار تأیید).
func (s *server) ucrmSaveSmsTemplate(w http.ResponseWriter, r *http.Request) {
	uid := userOf(r.Context()).ID
	if !s.ucrmRolesOf(uid).Manager {
		writeErr(w, http.StatusForbidden, "مدیریت قالب‌های پیامک فقط با مدیر است")
		return
	}
	var in smsTemplateIn
	if !decodeJSON(w, r, &in, 1<<16) {
		return
	}
	if msg := in.clean(); msg != "" {
		writeErr(w, http.StatusBadRequest, msg)
		return
	}
	labels, _ := json.Marshal(in.VarLabels)
	id, _ := strconv.ParseInt(r.PathValue("id"), 10, 64)

	if id > 0 {
		var curTitle, curPreview string
		var curBody int64
		var curSt sql.NullInt64
		err := s.db.QueryRow(`SELECT title, preview, body_id, approval_status FROM ucrm_sms_templates
			WHERE id = ? AND organization_id = ? AND is_deleted = 0`, id, ucrmOrg).Scan(&curTitle, &curPreview, &curBody, &curSt)
		if err == sql.ErrNoRows {
			writeErr(w, http.StatusNotFound, "قالب یافت نشد")
			return
		} else if err != nil {
			writeDBErr(w, "ucrmSaveSmsTemplate load", err)
			return
		}
		status := curSt
		msg := "قالب ذخیره شد."
		if in.Preview != curPreview {
			if !curSt.Valid || curSt.Int64 != smsStNeedsEdit {
				writeErr(w, http.StatusBadRequest, "متن الگو فقط وقتی قابل تغییر است که ملی‌پیامک «نیاز به اصلاح» گفته باشد؛ برای متن تازه، قالب جدید بسازید.")
				return
			}
			if len([]rune(in.Title)) > 50 {
				writeErr(w, http.StatusBadRequest, "برای ارسال به ملی‌پیامک، عنوان حداکثر ۵۰ نویسه است.")
				return
			}
			if _, e := s.smsPatternCall(r, map[string]any{"action": "edit", "body_id": curBody, "title": in.Title, "body": in.Preview}); e != "" {
				writeErr(w, http.StatusBadGateway, e)
				return
			}
			status = sql.NullInt64{Int64: smsStWait, Valid: true}
			msg = "اصلاحیه برای ملی‌پیامک فرستاده شد و منتظر تأیید است."
		}
		if _, err := s.db.Exec(`UPDATE ucrm_sms_templates SET title = ?, preview = ?, var_labels = ?, is_active = ?, approval_status = ?,
			review_note = IF(?, NULL, review_note), updated_by = ? WHERE id = ?`,
			in.Title, in.Preview, string(labels), in.IsActive, status, in.Preview != curPreview, uid, id); err != nil {
			writeDBErr(w, "ucrmSaveSmsTemplate", err)
			return
		}
		writeJSON(w, http.StatusOK, map[string]any{"success": true, "id": id, "message": msg})
		return
	}

	var status any // قالبِ ساخته‌شده در پنل: وضعیت با «به‌روزرسانی وضعیت» خوانده می‌شود
	msg := "قالب اضافه شد. برای دیدن وضعیت تأییدش «به‌روزرسانی وضعیت» را بزنید."
	if in.BodyID == 0 {
		if len([]rune(in.Title)) > 50 {
			writeErr(w, http.StatusBadRequest, "برای ارسال به ملی‌پیامک، عنوان حداکثر ۵۰ نویسه است.")
			return
		}
		if len(in.VarLabels) == 0 {
			writeErr(w, http.StatusBadRequest, "ملی‌پیامک الگوی بدون متغیر را نمی‌پذیرد؛ دست‌کم یک {0} در متن بگذارید.")
			return
		}
		out, e := s.smsPatternCall(r, map[string]any{"action": "add", "title": in.Title, "body": in.Preview})
		if e != "" {
			writeErr(w, http.StatusBadGateway, e)
			return
		}
		bid, _ := out["body_id"].(float64)
		in.BodyID = int64(bid)
		status = smsStWait
		msg = fmt.Sprintf("قالب برای تأیید به ملی‌پیامک فرستاده شد (کد الگو %d). پس از تأییدِ ملی‌پیامک قابل ارسال است.", in.BodyID)
	}
	crmMade := status != nil // در ملی‌پیامک با همین CRM ساخته شد
	res, err := s.db.Exec(`INSERT INTO ucrm_sms_templates (organization_id, title, body_id, preview, var_labels, is_active, approval_status, crm_created, created_by, updated_by)
		VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`, ucrmOrg, in.Title, in.BodyID, in.Preview, string(labels), in.IsActive, status, crmMade, uid, uid)
	if err != nil {
		writeDBErr(w, "ucrmSaveSmsTemplate insert", err)
		return
	}
	nid, _ := res.LastInsertId()
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "id": nid, "message": msg})
}

// POST /ucrm/sms-templates/refresh — وضعیت و توضیحِ کارشناس از ملی‌پیامک (GetBodies)
func (s *server) ucrmRefreshSmsTemplates(w http.ResponseWriter, r *http.Request) {
	uid := userOf(r.Context()).ID
	if !s.ucrmRolesOf(uid).Manager {
		writeErr(w, http.StatusForbidden, "مدیریت قالب‌های پیامک فقط با مدیر است")
		return
	}
	out, e := s.smsPatternCall(r, map[string]any{"action": "list"})
	if e != "" {
		writeErr(w, http.StatusBadGateway, e)
		return
	}
	found := map[int64][2]any{}
	if items, ok := out["items"].([]any); ok {
		for _, it := range items {
			m, _ := it.(map[string]any)
			bid, _ := m["body_id"].(float64)
			st, _ := m["status"].(float64)
			note, _ := m["note"].(string)
			found[int64(bid)] = [2]any{int(st), note}
		}
	}
	tpls, err := s.smsTemplates(false)
	if err != nil {
		writeDBErr(w, "ucrmRefreshSmsTemplates", err)
		return
	}
	for _, t := range tpls {
		st, note := any(smsStDeleted), any(nil) // در فهرستِ ملی‌پیامک نیست = حذف‌شده
		if f, ok := found[t.BodyID]; ok {
			st, note = f[0], nullIfEmpty(strings.TrimSpace(f[1].(string)))
		}
		if _, err := s.db.Exec(`UPDATE ucrm_sms_templates SET approval_status = ?, review_note = ?, status_checked_at = NOW() WHERE id = ?`,
			st, note, t.ID); err != nil {
			writeDBErr(w, "ucrmRefreshSmsTemplates update", err)
			return
		}
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "message": "وضعیت قالب‌ها از ملی‌پیامک به‌روز شد."})
}

// DELETE /ucrm/sms-templates/{id} — حذف نرم (تاریخچه دست نمی‌خورد)
func (s *server) ucrmDeleteSmsTemplate(w http.ResponseWriter, r *http.Request) {
	uid := userOf(r.Context()).ID
	if !s.ucrmRolesOf(uid).Manager {
		writeErr(w, http.StatusForbidden, "مدیریت قالب‌های پیامک فقط با مدیر است")
		return
	}
	id, _ := strconv.ParseInt(r.PathValue("id"), 10, 64)
	var body int64
	var st sql.NullInt64
	if err := s.db.QueryRow(`SELECT body_id, approval_status FROM ucrm_sms_templates WHERE id = ? AND organization_id = ? AND is_deleted = 0`,
		id, ucrmOrg).Scan(&body, &st); err == sql.ErrNoRows {
		writeErr(w, http.StatusNotFound, "قالب یافت نشد")
		return
	}
	// فقط قالبی که خودِ CRM در ملی‌پیامک ساخته (created_by دارد و وضعیت دارد) از پنل هم حذف می‌شود؛
	// قالب‌هایی که دستی در پنل ساخته شده‌اند (مثل الگوهای ورود سیستم) در پنل دست نمی‌خورند.
	var crmMade int
	_ = s.db.QueryRow(`SELECT COUNT(*) FROM ucrm_sms_templates WHERE id = ? AND crm_created = 1`, id).Scan(&crmMade)
	if crmMade == 1 && st.Valid && st.Int64 != smsStDeleted {
		if _, e := s.smsPatternCall(r, map[string]any{"action": "delete", "body_id": body}); e != "" {
			writeErr(w, http.StatusBadGateway, "حذف از ملی‌پیامک ناموفق بود: "+e)
			return
		}
	}
	if _, err := s.db.Exec(`UPDATE ucrm_sms_templates SET is_deleted = 1, updated_by = ? WHERE id = ? AND organization_id = ?`,
		uid, id, ucrmOrg); err != nil {
		writeDBErr(w, "ucrmDeleteSmsTemplate", err)
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "message": "قالب حذف شد."})
}

// ──────────────── POST /ucrm/customers/{id}/sms {template_id, phone, args[]} ────────────────

func (s *server) ucrmSendSms(w http.ResponseWriter, r *http.Request) {
	uid := userOf(r.Context()).ID
	ro := s.ucrmRolesOf(uid)
	c, _ := s.ucrmFollowupCustomer(w, idParam(r), uid, ro, true)
	if c == nil {
		return
	}
	var in struct {
		TemplateID int64    `json:"template_id"`
		Phone      string   `json:"phone"`
		Args       []string `json:"args"`
	}
	if !decodeJSON(w, r, &in, 1<<14) {
		return
	}
	phone := digitsOnly(in.Phone)
	ok := false
	for _, p := range c.Phones {
		if digitsOnly(p) == phone && phone != "" {
			ok = true
			break
		}
	}
	if !ok {
		writeErr(w, http.StatusBadRequest, "شماره را از شماره‌های همین مشتری انتخاب کنید.")
		return
	}
	var t ucrmSmsTemplate
	var labels string
	err := s.db.QueryRow(`SELECT id, title, body_id, preview, COALESCE(var_labels, '[]') FROM ucrm_sms_templates
		WHERE id = ? AND organization_id = ? AND is_deleted = 0 AND is_active = 1`, in.TemplateID, ucrmOrg).
		Scan(&t.ID, &t.Title, &t.BodyID, &t.Preview, &labels)
	if err == sql.ErrNoRows {
		writeErr(w, http.StatusBadRequest, "قالب پیامک را انتخاب کنید.")
		return
	} else if err != nil {
		writeDBErr(w, "ucrmSendSms template", err)
		return
	}
	_ = json.Unmarshal([]byte(labels), &t.VarLabels)
	args := make([]string, len(t.VarLabels))
	for i := range args {
		if i < len(in.Args) {
			args[i] = strings.TrimSpace(strings.ReplaceAll(in.Args[i], ";", "،")) // «;» جداکنندهٔ ملی‌پیامک است
		}
		if args[i] == "" {
			writeErr(w, http.StatusBadRequest, "مقدار «"+t.VarLabels[i]+"» را وارد کنید.")
			return
		}
	}
	argsJSON, _ := json.Marshal(args)
	res, err := s.db.Exec(`INSERT INTO ucrm_sms_log (customer_id, template_id, phone, args, text, status, sent_by)
		VALUES (?, ?, ?, ?, ?, 'queued', ?)`, c.ID, t.ID, phone, string(argsJSON), smsRender(t.Preview, args), uid)
	if err != nil {
		writeDBErr(w, "ucrmSendSms insert", err)
		return
	}
	logID, _ := res.LastInsertId()

	// ارسالِ واقعی در PHP (همان لحظه، تا کاربر نتیجه را ببیند)
	status, msg := s.smsDeliver(r, logID)
	if status != "sent" {
		writeJSON(w, http.StatusBadGateway, map[string]any{"success": false, "id": logID,
			"message": "پیامک فرستاده نشد: " + msg})
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "id": logID, "message": "پیامک فرستاده شد."})
}

func (s *server) smsDeliver(r *http.Request, logID int64) (string, string) {
	body, _ := json.Marshal(map[string]any{"log_id": logID})
	req, err := http.NewRequest("POST", strings.TrimRight(s.cfg.BaseURL, "/")+"/api/ucrm/sms-deliver.php", bytes.NewReader(body))
	if err != nil {
		return "failed", "خطای داخلی"
	}
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Authorization", r.Header.Get("Authorization"))
	resp, err := (&http.Client{Timeout: 25 * time.Second}).Do(req)
	if err != nil {
		s.smsMarkFailed(logID, "سرویس ارسال در دسترس نیست")
		return "failed", "سرویس ارسال در دسترس نیست"
	}
	defer resp.Body.Close()
	raw, _ := io.ReadAll(io.LimitReader(resp.Body, 1<<16))
	var out struct {
		Status  string `json:"status"`
		Message string `json:"message"`
	}
	_ = json.Unmarshal(raw, &out)
	if out.Status == "" {
		s.smsMarkFailed(logID, fmt.Sprintf("پاسخ نامعتبر از سرویس ارسال (%d)", resp.StatusCode))
		return "failed", "پاسخ نامعتبر از سرویس ارسال"
	}
	return out.Status, out.Message
}

func (s *server) smsMarkFailed(logID int64, msg string) {
	_, _ = s.db.Exec(`UPDATE ucrm_sms_log SET status = 'failed', error = ? WHERE id = ? AND status = 'queued'`, msg, logID)
}

// ──────────────── GET /ucrm/customers/{id}/sms — تاریخچه ────────────────

func (s *server) ucrmListSms(w http.ResponseWriter, r *http.Request) {
	uid := userOf(r.Context()).ID
	ro := s.ucrmRolesOf(uid)
	c, manage := s.ucrmFollowupCustomer(w, idParam(r), uid, ro, false)
	if c == nil {
		return
	}
	rows, err := s.db.Query(`
		SELECT l.id, COALESCE(t.title, ''), l.phone, COALESCE(l.text, ''), l.status, COALESCE(l.error, ''),
		       TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))),
		       DATE_FORMAT(l.created_at, '%Y-%m-%d %H:%i:%s')
		FROM ucrm_sms_log l
		LEFT JOIN ucrm_sms_templates t ON t.id = l.template_id
		LEFT JOIN users u ON u.id = l.sent_by
		WHERE l.customer_id = ?
		ORDER BY l.created_at DESC, l.id DESC`, c.ID)
	if err != nil {
		writeDBErr(w, "ucrmListSms", err)
		return
	}
	defer rows.Close()
	type smsRow struct {
		ID        int64  `json:"id"`
		Template  string `json:"template"`
		Phone     string `json:"phone"`
		Text      string `json:"text"`
		Status    string `json:"status"`
		Error     string `json:"error"`
		SentBy    string `json:"sent_by_name"`
		CreatedAt string `json:"created_at"`
	}
	items := []smsRow{}
	for rows.Next() {
		var x smsRow
		if err := rows.Scan(&x.ID, &x.Template, &x.Phone, &x.Text, &x.Status, &x.Error, &x.SentBy, &x.CreatedAt); err != nil {
			writeDBErr(w, "ucrmListSms scan", err)
			return
		}
		items = append(items, x)
	}
	writeJSON(w, http.StatusOK, map[string]any{"items": items, "can_send": manage})
}
