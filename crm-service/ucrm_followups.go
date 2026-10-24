package main

// ═══════════════════════════════════════════════════════════════════
//  CRM جدید — پیگیری‌های مشتری (پنجرهٔ «ثبت پیگیری جدید» و تب «برنامهٔ پیگیری»، ۱۴۰۵/۰۸/۰۲).
//
//  هر پیگیری: عنوان، نوع، تاریخ و زمان (وقت تهران)، یادداشت، وضعیت pending|done|cancelled.
//  «عقب‌افتاده» = pending با زمان گذشته (با NOW() دیتابیس، نه ساعت پردازه).
//  کارشناس همان مشتری یا مدیر ثبت/ویرایش/حذف/تغییر وضعیت می‌کند؛ بقیه (حسابداری) فقط می‌بینند.
//  «انجام شد» → ucrm_customers.last_followup_at (ستون «آخرین پیگیری» فهرست مشتریان).
// ═══════════════════════════════════════════════════════════════════

import (
	"database/sql"
	"net/http"
	"strconv"
	"strings"
	"time"
)

var ucrmFollowupTypes = map[string]bool{"call": true, "sms": true, "meeting": true, "email": true, "proforma": true, "other": true}

type ucrmFollowup struct {
	ID        int64  `json:"id"`
	Title     string `json:"title"`
	Type      string `json:"type"`
	DueAt     string `json:"due_at"` // YYYY-MM-DD HH:MM:SS (وقت تهران)
	Note      string `json:"note"`
	Status    string `json:"status"`
	Overdue   bool   `json:"overdue"`
	DoneAt    string `json:"done_at"`
	DoneBy    string `json:"done_by_name"`
	CreatedBy string `json:"created_by_name"`
	CreatedAt string `json:"created_at"`
}

type ucrmFollowupIn struct {
	Title string `json:"title"`
	Type  string `json:"type"`
	DueAt string `json:"due_at"` // «YYYY-MM-DD HH:MM» میلادی
	Note  string `json:"note"`
}

func (in *ucrmFollowupIn) clean() (string, time.Time) {
	in.Title = faLetters(strings.Join(strings.Fields(in.Title), " "))
	in.Note = strings.TrimSpace(in.Note)
	in.Type = strings.TrimSpace(in.Type)
	if in.Title == "" {
		return "عنوان پیگیری را وارد کنید.", time.Time{}
	}
	if len([]rune(in.Title)) > 255 {
		return "عنوان پیگیری حداکثر ۲۵۵ نویسه است.", time.Time{}
	}
	if !ucrmFollowupTypes[in.Type] {
		return "نوع پیگیری را انتخاب کنید.", time.Time{}
	}
	t, err := time.Parse("2006-01-02 15:04", strings.TrimSpace(in.DueAt))
	if err != nil {
		return "تاریخ و زمان پیگیری معتبر نیست.", time.Time{}
	}
	return "", t
}

// ucrmFollowupCustomer: مشتری قابل‌مشاهده + آیا کاربر می‌تواند پیگیری‌هایش را مدیریت کند
func (s *server) ucrmFollowupCustomer(w http.ResponseWriter, customerID, uid int64, ro ucrmRoles, needManage bool) (*ucrmCustomerOut, bool) {
	c, err := s.ucrmLoad(customerID, uid, ro)
	if err != nil {
		writeDBErr(w, "ucrmFollowupCustomer", err)
		return nil, false
	}
	if c == nil {
		writeErr(w, http.StatusNotFound, "مشتری یافت نشد")
		return nil, false
	}
	manage := c.RepID == uid || ro.Manager
	if needManage && !manage {
		writeErr(w, http.StatusForbidden, "فقط کارشناس همین مشتری یا مدیر می‌تواند پیگیری‌ها را تغییر دهد.")
		return nil, false
	}
	return c, manage
}

// GET /ucrm/customers/{id}/followups
func (s *server) ucrmListFollowups(w http.ResponseWriter, r *http.Request) {
	uid := userOf(r.Context()).ID
	ro := s.ucrmRolesOf(uid)
	c, manage := s.ucrmFollowupCustomer(w, idParam(r), uid, ro, false)
	if c == nil {
		return
	}
	rows, err := s.db.Query(`
		SELECT f.id, f.title, f.type, DATE_FORMAT(f.due_at,'%Y-%m-%d %H:%i:%s'), COALESCE(f.note,''), f.status,
		       (f.status = 'pending' AND f.due_at < NOW()),
		       COALESCE(DATE_FORMAT(f.done_at,'%Y-%m-%d %H:%i:%s'),''),
		       TRIM(CONCAT(COALESCE(du.first_name,''), ' ', COALESCE(du.last_name,''))),
		       TRIM(CONCAT(COALESCE(cu.first_name,''), ' ', COALESCE(cu.last_name,''))),
		       DATE_FORMAT(f.created_at,'%Y-%m-%d %H:%i:%s')
		FROM ucrm_followups f
		LEFT JOIN users du ON du.id = f.done_by
		LEFT JOIN users cu ON cu.id = f.created_by
		WHERE f.customer_id = ? AND f.is_deleted = 0
		ORDER BY f.due_at DESC, f.id DESC`, c.ID)
	if err != nil {
		writeDBErr(w, "ucrmListFollowups", err)
		return
	}
	defer rows.Close()
	items := []ucrmFollowup{}
	for rows.Next() {
		var f ucrmFollowup
		var od int
		if err := rows.Scan(&f.ID, &f.Title, &f.Type, &f.DueAt, &f.Note, &f.Status, &od, &f.DoneAt, &f.DoneBy, &f.CreatedBy, &f.CreatedAt); err != nil {
			writeDBErr(w, "ucrmListFollowups scan", err)
			return
		}
		f.Overdue = od == 1
		items = append(items, f)
	}
	writeJSON(w, http.StatusOK, map[string]any{"items": items, "can_manage": manage})
}

// POST /ucrm/customers/{id}/followups
func (s *server) ucrmCreateFollowup(w http.ResponseWriter, r *http.Request) {
	uid := userOf(r.Context()).ID
	ro := s.ucrmRolesOf(uid)
	c, _ := s.ucrmFollowupCustomer(w, idParam(r), uid, ro, true)
	if c == nil {
		return
	}
	var in ucrmFollowupIn
	if !decodeJSON(w, r, &in, 1<<16) {
		return
	}
	msg, due := in.clean()
	if msg != "" {
		writeErr(w, http.StatusBadRequest, msg)
		return
	}
	res, err := s.db.Exec(`INSERT INTO ucrm_followups (customer_id, title, type, due_at, note, created_by, updated_by)
		VALUES (?, ?, ?, ?, ?, ?, ?)`, c.ID, in.Title, in.Type, due.Format("2006-01-02 15:04:05"), nullIfEmpty(in.Note), uid, uid)
	if err != nil {
		writeDBErr(w, "ucrmCreateFollowup", err)
		return
	}
	id, _ := res.LastInsertId()
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "id": id, "message": "پیگیری ثبت شد."})
}

// پیگیری + مشتری‌اش (برای ویرایش/حذف/وضعیت) — اجازهٔ مدیریت را هم می‌سنجد
func (s *server) ucrmFollowupForEdit(w http.ResponseWriter, r *http.Request) (int64, *ucrmCustomerOut) {
	uid := userOf(r.Context()).ID
	ro := s.ucrmRolesOf(uid)
	fid, _ := strconv.ParseInt(r.PathValue("id"), 10, 64)
	var cid int64
	err := s.db.QueryRow("SELECT customer_id FROM ucrm_followups WHERE id = ? AND is_deleted = 0", fid).Scan(&cid)
	if err == sql.ErrNoRows {
		writeErr(w, http.StatusNotFound, "پیگیری یافت نشد")
		return 0, nil
	} else if err != nil {
		writeDBErr(w, "ucrmFollowupForEdit", err)
		return 0, nil
	}
	c, _ := s.ucrmFollowupCustomer(w, cid, uid, ro, true)
	if c == nil {
		return 0, nil
	}
	return fid, c
}

// PUT /ucrm/followups/{id}
func (s *server) ucrmUpdateFollowup(w http.ResponseWriter, r *http.Request) {
	fid, c := s.ucrmFollowupForEdit(w, r)
	if c == nil {
		return
	}
	var in ucrmFollowupIn
	if !decodeJSON(w, r, &in, 1<<16) {
		return
	}
	msg, due := in.clean()
	if msg != "" {
		writeErr(w, http.StatusBadRequest, msg)
		return
	}
	if _, err := s.db.Exec(`UPDATE ucrm_followups SET title = ?, type = ?, due_at = ?, note = ?, updated_by = ? WHERE id = ?`,
		in.Title, in.Type, due.Format("2006-01-02 15:04:05"), nullIfEmpty(in.Note), userOf(r.Context()).ID, fid); err != nil {
		writeDBErr(w, "ucrmUpdateFollowup", err)
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "message": "پیگیری ویرایش شد."})
}

// DELETE /ucrm/followups/{id} — حذف نرم
func (s *server) ucrmDeleteFollowup(w http.ResponseWriter, r *http.Request) {
	fid, c := s.ucrmFollowupForEdit(w, r)
	if c == nil {
		return
	}
	if _, err := s.db.Exec(`UPDATE ucrm_followups SET is_deleted = 1, deleted_by = ?, deleted_at = NOW() WHERE id = ?`,
		userOf(r.Context()).ID, fid); err != nil {
		writeDBErr(w, "ucrmDeleteFollowup", err)
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "message": "پیگیری حذف شد."})
}

// POST /ucrm/followups/{id}/status   body: {"status":"done"|"cancelled"|"pending"}
func (s *server) ucrmFollowupStatus(w http.ResponseWriter, r *http.Request) {
	fid, c := s.ucrmFollowupForEdit(w, r)
	if c == nil {
		return
	}
	var in struct {
		Status string `json:"status"`
	}
	if !decodeJSON(w, r, &in, 1<<10) {
		return
	}
	uid := userOf(r.Context()).ID
	var err error
	msg := ""
	switch in.Status {
	case "done":
		_, err = s.db.Exec(`UPDATE ucrm_followups SET status = 'done', done_at = NOW(), done_by = ?, updated_by = ? WHERE id = ?`, uid, uid, fid)
		if err == nil {
			// «آخرین پیگیری» مشتری (ستون فهرست مشتریان)
			_, err = s.db.Exec("UPDATE ucrm_customers SET last_followup_at = NOW() WHERE id = ?", c.ID)
		}
		msg = "پیگیری انجام شد."
	case "cancelled":
		_, err = s.db.Exec(`UPDATE ucrm_followups SET status = 'cancelled', done_at = NULL, done_by = NULL, updated_by = ? WHERE id = ?`, uid, fid)
		msg = "پیگیری لغو شد."
	case "pending":
		_, err = s.db.Exec(`UPDATE ucrm_followups SET status = 'pending', done_at = NULL, done_by = NULL, updated_by = ? WHERE id = ?`, uid, fid)
		msg = "پیگیری به «در انتظار» برگشت."
	default:
		writeErr(w, http.StatusBadRequest, "وضعیت نامعتبر است.")
		return
	}
	if err != nil {
		writeDBErr(w, "ucrmFollowupStatus", err)
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "message": msg})
}
