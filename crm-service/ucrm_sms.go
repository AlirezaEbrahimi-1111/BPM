package main

// ═══════════════════════════════════════════════════════════════════
//  پیامک به مشتری (بخش ۹ طرح CRM، ۱۴۰۵/۰۸/۱۹).
//
//  قالب = الگوی تأییدشدهٔ ملی‌پیامک (خط خدماتی): body_id + متنِ پیش‌نمایش با
//  {0} {1}… + برچسبِ هر متغیر. مدیریتِ قالب فقط مدیر؛ ارسال = کارشناسِ همان
//  مشتری یا مدیر، فقط به شماره‌های خودِ مشتری.
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
	ID        int64    `json:"id"`
	Title     string   `json:"title"`
	BodyID    int64    `json:"body_id"`
	Preview   string   `json:"preview"`
	VarLabels []string `json:"var_labels"`
	IsActive  bool     `json:"is_active"`
}

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
	q := `SELECT id, title, body_id, preview, COALESCE(var_labels, '[]'), is_active FROM ucrm_sms_templates
		WHERE organization_id = ? AND is_deleted = 0`
	if onlyActive {
		q += " AND is_active = 1"
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
		if err := rows.Scan(&t.ID, &t.Title, &t.BodyID, &t.Preview, &labels, &active); err != nil {
			return nil, err
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
	if in.BodyID <= 0 {
		return "کد الگوی ملی‌پیامک را وارد کنید."
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

// POST /ucrm/sms-templates  و  PUT /ucrm/sms-templates/{id}
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
		res, err := s.db.Exec(`UPDATE ucrm_sms_templates SET title = ?, body_id = ?, preview = ?, var_labels = ?, is_active = ?, updated_by = ?
			WHERE id = ? AND organization_id = ? AND is_deleted = 0`, in.Title, in.BodyID, in.Preview, string(labels), in.IsActive, uid, id, ucrmOrg)
		if err != nil {
			writeDBErr(w, "ucrmSaveSmsTemplate", err)
			return
		}
		if n, _ := res.RowsAffected(); n == 0 {
			var exists int
			_ = s.db.QueryRow("SELECT COUNT(*) FROM ucrm_sms_templates WHERE id = ? AND is_deleted = 0", id).Scan(&exists)
			if exists == 0 {
				writeErr(w, http.StatusNotFound, "قالب یافت نشد")
				return
			}
		}
		writeJSON(w, http.StatusOK, map[string]any{"success": true, "id": id, "message": "قالب ذخیره شد."})
		return
	}
	res, err := s.db.Exec(`INSERT INTO ucrm_sms_templates (organization_id, title, body_id, preview, var_labels, is_active, created_by, updated_by)
		VALUES (?, ?, ?, ?, ?, ?, ?, ?)`, ucrmOrg, in.Title, in.BodyID, in.Preview, string(labels), in.IsActive, uid, uid)
	if err != nil {
		writeDBErr(w, "ucrmSaveSmsTemplate insert", err)
		return
	}
	nid, _ := res.LastInsertId()
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "id": nid, "message": "قالب اضافه شد."})
}

// DELETE /ucrm/sms-templates/{id} — حذف نرم (تاریخچه دست نمی‌خورد)
func (s *server) ucrmDeleteSmsTemplate(w http.ResponseWriter, r *http.Request) {
	uid := userOf(r.Context()).ID
	if !s.ucrmRolesOf(uid).Manager {
		writeErr(w, http.StatusForbidden, "مدیریت قالب‌های پیامک فقط با مدیر است")
		return
	}
	id, _ := strconv.ParseInt(r.PathValue("id"), 10, 64)
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
