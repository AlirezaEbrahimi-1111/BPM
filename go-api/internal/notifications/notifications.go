// Package notifications — پورتِ api/notifications/*.php + بخشِ خواندنیِ
// includes/Notification.php (فیلترها، لیست، شمارشِ خوانده‌نشده، خواندن/حذف).
// ساختنِ نوتیفیکیشن (Notification::create، شاملِ ارسالِ پیامک) عمداً پورت
// نشده — آن منطق فقط از داخلِ خودِ PHP و از دهها جایِ دیگر صدا زده می‌شود،
// نه یک endpointِ سرراستِ قابلِ‌مصرف برایِ کلاینت.
package notifications

import (
	"database/sql"
	"net/http"
	"regexp"
	"strconv"
	"strings"

	"bmp/go-api/internal/core"
)

var namePattern = regexp.MustCompile(`توسط\s*"([^"]+)"`)

// toInt64 — کلایم‌های JSON در Go به‌صورتِ float64 دیکود می‌شوند؛ رشته‌ها هم
// (اگر کلاینت به‌جایِ عدد رشته بفرستد) پشتیبانی می‌شوند.
func toInt64(v any) int64 {
	switch n := v.(type) {
	case float64:
		return int64(n)
	case int64:
		return n
	case int:
		return int64(n)
	case string:
		i, _ := strconv.ParseInt(n, 10, 64)
		return i
	}
	return 0
}

func parseIntQuery(s string) int64 {
	i, _ := strconv.ParseInt(s, 10, 64)
	return i
}

func truthy(v any) bool {
	switch n := v.(type) {
	case int64:
		return n != 0
	case float64:
		return n != 0
	case bool:
		return n
	}
	return false
}

// passesTaskSelfFilter — پورتِ Notification::shouldShowNotification():
// نوتیفیکیشنِ مربوط به تسکی که هم creator هم assignee‌اش خودِ کاربر است
// مخفی می‌شود، مگر bypass_self_filter فعال باشد.
func passesTaskSelfFilter(db *sql.DB, n map[string]any, userID int64) bool {
	if truthy(n["bypass_self_filter"]) {
		return true
	}
	relatedType, _ := n["related_type"].(string)
	relatedID := toInt64(n["related_id"])
	if relatedID == 0 || relatedType != "task" {
		return true
	}
	var creatorID, assigneeID sql.NullInt64
	err := db.QueryRow("SELECT creator_id, assignee_id FROM tasks WHERE id = ?", relatedID).
		Scan(&creatorID, &assigneeID)
	if err != nil {
		return true // مثلِ نسخهٔ PHP: خطا در چک → نمایش داده شود
	}
	if creatorID.Valid && assigneeID.Valid && creatorID.Int64 == userID && assigneeID.Int64 == userID {
		return false
	}
	return true
}

// passesMessageNameFilter — پورتِ چکِ اضافیِ خودِ list.php/new.php (نه
// چیزی از داخلِ کلاسِ Notification): اگر متنِ پیام «توسط "نامِ خودِ من"»
// باشد، یعنی خودم انجامش داده‌ام، نشان داده نشود.
func passesMessageNameFilter(db *sql.DB, message string, userID int64) bool {
	if !strings.Contains(message, "توسط") {
		return true
	}
	m := namePattern.FindStringSubmatch(message)
	if len(m) < 2 {
		return true
	}
	performerName := strings.TrimSpace(m[1])

	var fullName sql.NullString
	err := db.QueryRow("SELECT CONCAT(first_name, ' ', last_name) FROM users WHERE id = ?", userID).Scan(&fullName)
	if err != nil {
		return true
	}
	return performerName != fullName.String
}

// filterList — ترکیبِ هر دو فیلتر، برایِ list.php/new.php.
func filterList(db *sql.DB, rows []map[string]any, userID int64) []map[string]any {
	out := make([]map[string]any, 0, len(rows))
	for _, n := range rows {
		if !passesTaskSelfFilter(db, n, userID) {
			continue
		}
		msg, _ := n["message"].(string)
		if !passesMessageNameFilter(db, msg, userID) {
			continue
		}
		out = append(out, n)
	}
	return out
}

// List — پورتِ دقیقِ api/notifications/list.php
//
//	GET /go/api/notifications/list?limit=&unread_only=1
//	→ {"success":true,"notifications":[...همه‌ی ستون‌هایِ جدول...],"unread_count":N}
func List(db *sql.DB) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		u := core.UserOf(r.Context())

		limit := 20
		if v := r.URL.Query().Get("limit"); v != "" {
			if n := parseIntQuery(v); n > 0 {
				limit = int(n)
			}
		}
		if limit > 50 {
			limit = 50
		}
		unreadOnly := r.URL.Query().Get("unread_only") == "1"

		// همون الگویِ getUserNotifications(): سه‌برابرِ limit می‌گیریم چون
		// بعداً فیلتر می‌شه، سپس به limit اصلی برش می‌خوریم.
		where := "user_id = ?"
		if unreadOnly {
			where += " AND is_read = 0"
		}
		rows, err := db.Query("SELECT * FROM notifications WHERE "+where+" ORDER BY created_at DESC LIMIT ?", u.ID, limit*3)
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}
		maps, err := core.ScanRowsToMaps(rows)
		rows.Close()
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}

		filtered := filterList(db, maps, u.ID)
		if len(filtered) > limit {
			filtered = filtered[:limit]
		}

		count, err := unreadCount(db, u.ID)
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}

		core.WriteJSON(w, http.StatusOK, map[string]any{
			"success":       true,
			"notifications": filtered,
			"unread_count":  count,
		})
	}
}

// unreadCount — پورتِ Notification::getUnreadCount(): همه‌ی is_read=0 را
// می‌خواند، فقط با فیلترِ تسکِ خودی (بدونِ فیلترِ نامِ داخلِ متن).
func unreadCount(db *sql.DB, userID int64) (int, error) {
	rows, err := db.Query("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0", userID)
	if err != nil {
		return 0, err
	}
	maps, err := core.ScanRowsToMaps(rows)
	rows.Close()
	if err != nil {
		return 0, err
	}
	count := 0
	for _, n := range maps {
		if passesTaskSelfFilter(db, n, userID) {
			count++
		}
	}
	return count, nil
}

// New — پورتِ دقیقِ api/notifications/new.php (پولینگِ لحظه‌ای)
//
//	GET /go/api/notifications/new?since=ID
//	→ {"success":true,"new_count":N,"notifications":[...],"latest_id":?,"latest_notification":?}
func New(db *sql.DB) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		u := core.UserOf(r.Context())
		sinceID := parseIntQuery(r.URL.Query().Get("since"))

		rows, err := db.Query("SELECT * FROM notifications WHERE user_id = ? AND id > ? ORDER BY created_at DESC", u.ID, sinceID)
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}
		maps, err := core.ScanRowsToMaps(rows)
		rows.Close()
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}

		filtered := filterList(db, maps, u.ID)

		resp := map[string]any{
			"success":       true,
			"new_count":     len(filtered),
			"notifications": filtered,
		}
		if len(filtered) > 0 {
			resp["latest_id"] = filtered[0]["id"]
			resp["latest_notification"] = filtered[0]
		}
		core.WriteJSON(w, http.StatusOK, resp)
	}
}

// MarkRead — پورتِ دقیقِ api/notifications/mark-read.php (هر ۵ حالت)
//
//	POST /go/api/notifications/mark-read   body: {id} یا {task_id} یا
//	     {ticket_id} یا {related_type,related_id} یا {related_types:[...]}
func MarkRead(db *sql.DB) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		u := core.UserOf(r.Context())

		var body struct {
			ID           any      `json:"id"`
			TaskID       any      `json:"task_id"`
			TicketID     any      `json:"ticket_id"`
			RelatedType  string   `json:"related_type"`
			RelatedID    any      `json:"related_id"`
			RelatedTypes []string `json:"related_types"`
		}
		_ = core.ReadJSON(r, &body)

		var (
			matched bool
			ok      bool
		)

		switch {
		case toInt64(body.ID) != 0:
			matched = true
			_, err := db.Exec("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?", toInt64(body.ID), u.ID)
			ok = err == nil
		case toInt64(body.TaskID) != 0:
			matched = true
			_, err := db.Exec("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND related_id = ? AND related_type = 'task' AND is_read = 0", u.ID, toInt64(body.TaskID))
			ok = err == nil
		case toInt64(body.TicketID) != 0:
			matched = true
			_, err := db.Exec("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND related_id = ? AND related_type = 'ticket' AND is_read = 0", u.ID, toInt64(body.TicketID))
			ok = err == nil
		case body.RelatedType != "" && toInt64(body.RelatedID) != 0:
			matched = true
			_, err := db.Exec("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND related_id = ? AND related_type = ? AND is_read = 0", u.ID, toInt64(body.RelatedID), body.RelatedType)
			ok = err == nil
		case len(body.RelatedTypes) > 0:
			matched = true
			types := make([]string, 0, len(body.RelatedTypes))
			for _, t := range body.RelatedTypes {
				if t != "" {
					types = append(types, t)
				}
			}
			if len(types) == 0 {
				ok = false // مثلِ نسخهٔ PHP: بعدِ فیلتر چیزی نموند → $result = false
			} else {
				placeholders := strings.TrimSuffix(strings.Repeat("?,", len(types)), ",")
				args := make([]any, 0, len(types)+1)
				args = append(args, u.ID)
				for _, t := range types {
					args = append(args, t)
				}
				_, err := db.Exec("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND related_type IN ("+placeholders+") AND is_read = 0", args...)
				ok = err == nil
			}
		}

		if !matched {
			core.WriteErr(w, http.StatusBadRequest, "شناسه اعلان یا تسک الزامی است")
			return
		}

		msg := "خطا در علامت‌گذاری"
		if ok {
			msg = "اعلان خوانده شد"
		}
		core.WriteJSON(w, http.StatusOK, map[string]any{"success": ok, "message": msg})
	}
}

// MarkAllRead — پورتِ دقیقِ api/notifications/mark-all-read.php
//
//	POST /go/api/notifications/mark-all-read
func MarkAllRead(db *sql.DB) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		u := core.UserOf(r.Context())
		_, err := db.Exec("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0", u.ID)
		ok := err == nil
		msg := "خطا در علامت‌گذاری"
		if ok {
			msg = "همه اعلان‌ها خوانده شدند"
		}
		core.WriteJSON(w, http.StatusOK, map[string]any{"success": ok, "message": msg})
	}
}

// Delete — پورتِ دقیقِ api/notifications/delete.php
//
//	POST /go/api/notifications/delete   body: {id}
//
// نکته‌ی هم‌ارزیِ عمدی با نسخه‌ی PHP: وقتی id خالی باشد، نسخه‌ی PHP یک
// Exception می‌اندازد که در catch عمومی به HTTP 500 + پیامِ ژنریک تبدیل
// می‌شود (نه 400 با پیامِ واقعی) — همین رفتار این‌جا هم عیناً حفظ شده.
func Delete(db *sql.DB) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		u := core.UserOf(r.Context())
		var body struct {
			ID any `json:"id"`
		}
		_ = core.ReadJSON(r, &body)
		id := toInt64(body.ID)
		if id == 0 {
			core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}
		_, err := db.Exec("DELETE FROM notifications WHERE id = ? AND user_id = ?", id, u.ID)
		ok := err == nil
		msg := "خطا در حذف"
		if ok {
			msg = "اعلان حذف شد"
		}
		core.WriteJSON(w, http.StatusOK, map[string]any{"success": ok, "message": msg})
	}
}
