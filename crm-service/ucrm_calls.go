package main

// ═══════════════════════════════════════════════════════════════════
//  گزارش تماس‌های مشتری — تب «تاریخچهٔ پیگیری ← تماس‌ها» در
//  pages/ucrm-customer.php (۱۴۰۵/۰۸/۱۹)، نمایش مثل تاریخچهٔ تسک.
//
//  هر گزارش: نتیجهٔ تماس + متن + پیوست (اختیاری) + پیگیریِ بعدی (اختیاری).
//  از دو راه ثبت می‌شود: دکمهٔ «ثبت گزارش تماس»، و «انجام شد»ِ پیگیریِ
//  نوعِ تماس (followup_id ← همان پیگیری «انجام شد» می‌شود). پیگیریِ بعدی یک
//  ردیفِ تازه در ucrm_followups است.
//
//  دسترسی مثل پیگیری‌ها: دیدن = هر کس مشتری را می‌بیند؛ ثبت = کارشناسِ همان
//  مشتری یا مدیر. فایل‌ها در cfg.UploadDir (/opt/bmp-crm/uploads/ucrm-calls، بیرون از وب) با نامِ تصادفی (دسترسیِ مستقیمِ وب
//  بسته — .htaccess) و فقط از /ucrm/call-files/{id} با همین بررسی دانلود می‌شوند.
// ═══════════════════════════════════════════════════════════════════

import (
	"crypto/rand"
	"database/sql"
	"encoding/hex"
	"fmt"
	"io"
	"mime/multipart"
	"net/http"
	"os"
	"path/filepath"
	"strconv"
	"strings"
	"time"
)

var ucrmCallResults = map[string]string{
	"answered": "پاسخ داد", "no_answer": "پاسخ نداد", "busy": "مشغول بود", "wrong_number": "شماره اشتباه",
}

const (
	callMaxFiles    = 5
	callMaxFileSize = 10 << 20 // ۱۰ مگابایت
)

var callAllowedExt = map[string]bool{
	".jpg": true, ".jpeg": true, ".png": true, ".webp": true, ".pdf": true, ".doc": true, ".docx": true,
	".xls": true, ".xlsx": true, ".txt": true, ".mp3": true, ".m4a": true, ".ogg": true, ".wav": true, ".amr": true,
}

type ucrmCallFile struct {
	ID   int64  `json:"id"`
	Name string `json:"name"`
	Size int64  `json:"size"`
}

type ucrmCall struct {
	ID            int64          `json:"id"`
	Result        string         `json:"result"`
	ResultLabel   string         `json:"result_label"`
	Note          string         `json:"note"`
	FollowupTitle string         `json:"followup_title"` // اگر از «انجام شد»ِ یک پیگیری ثبت شده
	NextDueAt     string         `json:"next_due_at"`    // پیگیریِ بعدی
	NextTitle     string         `json:"next_title"`
	CreatedBy     string         `json:"created_by_name"`
	CreatedAt     string         `json:"created_at"`
	Files         []ucrmCallFile `json:"files"`
}

// callUploadDir: پوشهٔ پیوست‌ها؛ یک‌بار .htaccess «دسترسی بسته» در آن می‌نشیند.
func (s *server) callUploadDir() (string, error) {
	dir := s.cfg.UploadDir
	if err := os.MkdirAll(dir, 0o750); err != nil {
		return "", err
	}
	ht := filepath.Join(dir, ".htaccess")
	if _, err := os.Stat(ht); os.IsNotExist(err) {
		_ = os.WriteFile(ht, []byte("Require all denied\n"), 0o640)
	}
	return dir, nil
}

func randomName(ext string) string {
	b := make([]byte, 16)
	_, _ = rand.Read(b)
	return hex.EncodeToString(b) + ext
}

// ──────────────── POST /ucrm/customers/{id}/calls (multipart) ────────────────
// فیلدها: result, note, followup_id?, next_due_at? («YYYY-MM-DD HH:MM»)، next_title?, files[]?

func (s *server) ucrmCreateCall(w http.ResponseWriter, r *http.Request) {
	uid := userOf(r.Context()).ID
	ro := s.ucrmRolesOf(uid)
	c, _ := s.ucrmFollowupCustomer(w, idParam(r), uid, ro, true)
	if c == nil {
		return
	}
	r.Body = http.MaxBytesReader(w, r.Body, callMaxFiles*callMaxFileSize+(1<<20))
	if err := r.ParseMultipartForm(8 << 20); err != nil {
		writeErr(w, http.StatusBadRequest, "حجم فایل‌ها بیش از حد مجاز است (هر فایل حداکثر ۱۰ مگابایت).")
		return
	}
	result := strings.TrimSpace(r.FormValue("result"))
	if ucrmCallResults[result] == "" {
		writeErr(w, http.StatusBadRequest, "نتیجهٔ تماس را انتخاب کنید.")
		return
	}
	note := strings.TrimSpace(r.FormValue("note"))
	if result == "answered" && note == "" {
		writeErr(w, http.StatusBadRequest, "برای تماسی که پاسخ داده شده، متن گزارش را بنویسید.")
		return
	}
	followupID, _ := strconv.ParseInt(r.FormValue("followup_id"), 10, 64)

	var next *ucrmFollowupIn
	var nextDue time.Time
	if v := strings.TrimSpace(r.FormValue("next_due_at")); v != "" {
		title := strings.TrimSpace(r.FormValue("next_title"))
		if title == "" {
			title = "پیگیری بعدی تماس"
		}
		next = &ucrmFollowupIn{Title: title, Type: "call", DueAt: v}
		var msg string
		if msg, nextDue = next.clean(); msg != "" {
			writeErr(w, http.StatusBadRequest, "پیگیری بعدی: "+msg)
			return
		}
	}

	var files []*multipart.FileHeader
	if r.MultipartForm != nil {
		files = r.MultipartForm.File["files"]
	}
	if len(files) > callMaxFiles {
		writeErr(w, http.StatusBadRequest, fmt.Sprintf("حداکثر %d فایل می‌شود پیوست کرد.", callMaxFiles))
		return
	}
	for _, fh := range files {
		if fh.Size > callMaxFileSize {
			writeErr(w, http.StatusBadRequest, "فایل «"+fh.Filename+"» بیش از ۱۰ مگابایت است.")
			return
		}
		if !callAllowedExt[strings.ToLower(filepath.Ext(fh.Filename))] {
			writeErr(w, http.StatusBadRequest, "نوع فایل «"+fh.Filename+"» مجاز نیست (عکس، PDF، Word، Excel، متن یا صوت).")
			return
		}
	}

	tx, err := s.db.Begin()
	if err != nil {
		writeDBErr(w, "ucrmCreateCall", err)
		return
	}
	defer tx.Rollback()

	if followupID > 0 {
		var cid int64
		var status string
		err := tx.QueryRow(`SELECT customer_id, status FROM ucrm_followups WHERE id = ? AND is_deleted = 0 FOR UPDATE`, followupID).
			Scan(&cid, &status)
		if err == sql.ErrNoRows || (err == nil && cid != c.ID) {
			writeErr(w, http.StatusBadRequest, "پیگیری یافت نشد.")
			return
		} else if err != nil {
			writeDBErr(w, "ucrmCreateCall followup", err)
			return
		}
		if _, err := tx.Exec(`UPDATE ucrm_followups SET status = 'done', done_at = NOW(), done_by = ?, updated_by = ? WHERE id = ?`,
			uid, uid, followupID); err != nil {
			writeDBErr(w, "ucrmCreateCall followup done", err)
			return
		}
	}

	var nextID any
	if next != nil {
		res, err := tx.Exec(`INSERT INTO ucrm_followups (customer_id, title, type, due_at, note, created_by, updated_by)
			VALUES (?, ?, 'call', ?, NULL, ?, ?)`, c.ID, next.Title, nextDue.Format("2006-01-02 15:04:05"), uid, uid)
		if err != nil {
			writeDBErr(w, "ucrmCreateCall next", err)
			return
		}
		id, _ := res.LastInsertId()
		nextID = id
	}

	res, err := tx.Exec(`INSERT INTO ucrm_call_logs (customer_id, followup_id, result, note, next_followup_id, created_by)
		VALUES (?, ?, ?, ?, ?, ?)`, c.ID, nullIfZeroIf(followupID, true), result, nullIfEmpty(note), nextID, uid)
	if err != nil {
		writeDBErr(w, "ucrmCreateCall insert", err)
		return
	}
	callID, _ := res.LastInsertId()
	// تماس = تماسِ واقعی با مشتری → «آخرین پیگیری» در فهرست مشتریان
	if _, err := tx.Exec("UPDATE ucrm_customers SET last_followup_at = NOW() WHERE id = ?", c.ID); err != nil {
		writeDBErr(w, "ucrmCreateCall last", err)
		return
	}

	// فایل‌ها: اول روی دیسک، بعد ردیف؛ اگر چیزی شکست، فایل‌های نوشته‌شده پاک می‌شوند
	var written []string
	cleanup := func() {
		for _, p := range written {
			_ = os.Remove(p)
		}
	}
	if len(files) > 0 {
		dir, err := s.callUploadDir()
		if err != nil {
			writeErr(w, http.StatusInternalServerError, "پوشهٔ پیوست‌ها در دسترس نیست.")
			return
		}
		for _, fh := range files {
			stored := randomName(strings.ToLower(filepath.Ext(fh.Filename)))
			dst := filepath.Join(dir, stored)
			if err := saveUpload(fh, dst); err != nil {
				cleanup()
				writeErr(w, http.StatusInternalServerError, "ذخیرهٔ فایل «"+fh.Filename+"» ناموفق بود.")
				return
			}
			written = append(written, dst)
			if _, err := tx.Exec(`INSERT INTO ucrm_call_files (call_id, stored_name, original_name, mime, size, uploaded_by)
				VALUES (?, ?, ?, ?, ?, ?)`, callID, stored, filepath.Base(fh.Filename), nullIfEmpty(fh.Header.Get("Content-Type")), fh.Size, uid); err != nil {
				cleanup()
				writeDBErr(w, "ucrmCreateCall file", err)
				return
			}
		}
	}
	if err := tx.Commit(); err != nil {
		cleanup()
		writeDBErr(w, "ucrmCreateCall commit", err)
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "id": callID, "message": "گزارش تماس ثبت شد."})
}

func saveUpload(fh *multipart.FileHeader, dst string) error {
	src, err := fh.Open()
	if err != nil {
		return err
	}
	defer src.Close()
	out, err := os.OpenFile(dst, os.O_WRONLY|os.O_CREATE|os.O_EXCL, 0o640)
	if err != nil {
		return err
	}
	if _, err := io.Copy(out, src); err != nil {
		out.Close()
		_ = os.Remove(dst)
		return err
	}
	return out.Close()
}

// ──────────────── GET /ucrm/customers/{id}/calls ────────────────

func (s *server) ucrmListCalls(w http.ResponseWriter, r *http.Request) {
	uid := userOf(r.Context()).ID
	ro := s.ucrmRolesOf(uid)
	c, manage := s.ucrmFollowupCustomer(w, idParam(r), uid, ro, false)
	if c == nil {
		return
	}
	rows, err := s.db.Query(`
		SELECT l.id, l.result, COALESCE(l.note, ''), COALESCE(f.title, ''),
		       COALESCE(DATE_FORMAT(n.due_at, '%Y-%m-%d %H:%i:%s'), ''), COALESCE(n.title, ''),
		       TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))),
		       DATE_FORMAT(l.created_at, '%Y-%m-%d %H:%i:%s')
		FROM ucrm_call_logs l
		LEFT JOIN ucrm_followups f ON f.id = l.followup_id
		LEFT JOIN ucrm_followups n ON n.id = l.next_followup_id AND n.is_deleted = 0
		LEFT JOIN users u ON u.id = l.created_by
		WHERE l.customer_id = ? AND l.is_deleted = 0
		ORDER BY l.created_at DESC, l.id DESC`, c.ID)
	if err != nil {
		writeDBErr(w, "ucrmListCalls", err)
		return
	}
	defer rows.Close()
	items := []ucrmCall{}
	byID := map[int64]int{}
	for rows.Next() {
		var x ucrmCall
		if err := rows.Scan(&x.ID, &x.Result, &x.Note, &x.FollowupTitle, &x.NextDueAt, &x.NextTitle, &x.CreatedBy, &x.CreatedAt); err != nil {
			writeDBErr(w, "ucrmListCalls scan", err)
			return
		}
		x.ResultLabel = ucrmCallResults[x.Result]
		x.Files = []ucrmCallFile{}
		byID[x.ID] = len(items)
		items = append(items, x)
	}
	if len(items) > 0 {
		frows, err := s.db.Query(`SELECT cf.id, cf.call_id, cf.original_name, cf.size FROM ucrm_call_files cf
			JOIN ucrm_call_logs l ON l.id = cf.call_id WHERE l.customer_id = ? AND l.is_deleted = 0 ORDER BY cf.id`, c.ID)
		if err != nil {
			writeDBErr(w, "ucrmListCalls files", err)
			return
		}
		defer frows.Close()
		for frows.Next() {
			var f ucrmCallFile
			var callID int64
			if frows.Scan(&f.ID, &callID, &f.Name, &f.Size) == nil {
				if i, ok := byID[callID]; ok {
					items[i].Files = append(items[i].Files, f)
				}
			}
		}
	}
	writeJSON(w, http.StatusOK, map[string]any{"items": items, "can_manage": manage, "results": ucrmCallResults})
}

// ──────────────── GET /ucrm/call-files/{id} — دانلود با بررسیِ دسترسی ────────────────

func (s *server) ucrmCallFile(w http.ResponseWriter, r *http.Request) {
	uid := userOf(r.Context()).ID
	ro := s.ucrmRolesOf(uid)
	id, _ := strconv.ParseInt(r.PathValue("id"), 10, 64)
	var stored, name, mime string
	var customerID int64
	err := s.db.QueryRow(`SELECT cf.stored_name, cf.original_name, COALESCE(cf.mime, ''), l.customer_id
		FROM ucrm_call_files cf JOIN ucrm_call_logs l ON l.id = cf.call_id
		WHERE cf.id = ? AND l.is_deleted = 0`, id).Scan(&stored, &name, &mime, &customerID)
	if err == sql.ErrNoRows {
		writeErr(w, http.StatusNotFound, "فایل یافت نشد")
		return
	} else if err != nil {
		writeDBErr(w, "ucrmCallFile", err)
		return
	}
	if c, _ := s.ucrmFollowupCustomer(w, customerID, uid, ro, false); c == nil {
		return
	}
	path := filepath.Join(s.cfg.UploadDir, filepath.Base(stored))
	f, err := os.Open(path)
	if err != nil {
		writeErr(w, http.StatusNotFound, "فایل روی سرور پیدا نشد")
		return
	}
	defer f.Close()
	if mime == "" {
		mime = "application/octet-stream"
	}
	w.Header().Set("Content-Type", mime)
	w.Header().Set("X-Content-Type-Options", "nosniff")
	w.Header().Set("Content-Disposition", "attachment; filename*=UTF-8''"+urlPathEscape(name))
	http.ServeContent(w, r, "", time.Time{}, f)
}

func urlPathEscape(s string) string {
	var b strings.Builder
	for _, c := range []byte(s) {
		if (c >= 'a' && c <= 'z') || (c >= 'A' && c <= 'Z') || (c >= '0' && c <= '9') || strings.IndexByte("-_.~", c) >= 0 {
			b.WriteByte(c)
		} else {
			fmt.Fprintf(&b, "%%%02X", c)
		}
	}
	return b.String()
}
