package tickets

import (
	"database/sql"
	"net/http"

	"bmp/go-api/internal/core"
)

// Detail — پورت دقیق api/tickets/detail.php
//
//	GET /go/api/tickets/detail?id=
//	→ {"success":true,"ticket":{...},"messages":[...],"attachments":[...],
//	   "history":[...],"statuses":[...],"current_user_id":N,"current_user_role":"..."}
func Detail(db *sql.DB) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		u := core.UserOf(r.Context())

		ticketID := parseIntQuery2(r.URL.Query().Get("id"))
		if ticketID == 0 {
			core.WriteJSON(w, http.StatusOK, map[string]any{"success": false, "message": "شناسه تیکت الزامی است"})
			return
		}

		var role sql.NullString
		var orgID sql.NullInt64
		err := db.QueryRow("SELECT role, organization_id FROM users WHERE id = ?", u.ID).Scan(&role, &orgID)
		if err == sql.ErrNoRows {
			core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}

		ticketRows, err := db.Query(`
			SELECT t.*,
			       ts.name   AS status_name,
			       ts.label  AS status_label,
			       ts.color  AS status_color,
			       tp.name   AS priority_name,
			       tp.label  AS priority_label,
			       tp.color  AS priority_color,
			       tc.name   AS category_name,
			       CONCAT(uc.first_name, ' ', uc.last_name) AS creator_name,
			       CONCAT(ua.first_name, ' ', ua.last_name) AS assigned_name,
			       lt.title AS linked_task_title,
			       lt.status AS linked_task_status
			FROM tickets t
			JOIN ticket_statuses ts ON t.status_id = ts.id
			JOIN ticket_priorities tp ON t.priority_id = tp.id
			LEFT JOIN ticket_categories tc ON t.category_id = tc.id
			LEFT JOIN users uc ON t.created_by = uc.id
			LEFT JOIN users ua ON t.assigned_to = ua.id
			LEFT JOIN tasks lt ON (t.source_type = 'task' AND t.source_id = lt.id)
			WHERE t.id = ? AND t.deleted_at IS NULL
			  AND (t.organization_id = ? OR ? = 1)
		`, ticketID, orgID.Int64, u.ID)
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}
		ticketMaps, err := core.ScanRowsToMaps(ticketRows)
		ticketRows.Close()
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}
		if len(ticketMaps) == 0 {
			core.WriteErr(w, http.StatusNotFound, "تیکت یافت نشد")
			return
		}
		ticket := ticketMaps[0]

		me := &core.PermUser{ID: u.ID, Role: role.String, OrganizationID: orgID.Int64}
		can, err := core.CanManageTargetUser(db, me, toInt64(ticket["created_by"]))
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}
		if !can {
			core.WriteErr(w, http.StatusForbidden, "دسترسی غیرمجاز")
			return
		}

		// ثبت «این کاربر تیکت را الان دید» — مثل نسخهٔ PHP، خطا در این
		// بخش کشنده نیست (مثلا اگر جدول هنوز مایگریت نشده باشد).
		_, _ = db.Exec(`
			INSERT INTO ticket_message_reads (ticket_id, user_id, last_read_at)
			VALUES (?, ?, NOW())
			ON DUPLICATE KEY UPDATE last_read_at = NOW()
		`, ticketID, u.ID)

		msgRows, err := db.Query(`
			SELECT tm.id, tm.message, tm.created_at, tm.user_id,
			       CONCAT(u.first_name, ' ', u.last_name) AS user_name,
			       u.role AS user_role
			FROM ticket_messages tm
			LEFT JOIN users u ON tm.user_id = u.id
			WHERE tm.ticket_id = ? AND tm.deleted_at IS NULL
			ORDER BY tm.created_at ASC
		`, ticketID)
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}
		messages, err := core.ScanRowsToMaps(msgRows)
		msgRows.Close()
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}

		attRows, err := db.Query(`
			SELECT ta.id, ta.message_id, ta.user_id, ta.original_name, ta.stored_name,
			       ta.mime_type, ta.file_size, ta.created_at,
			       CONCAT(u.first_name, ' ', u.last_name) AS uploader_name
			FROM ticket_attachments ta
			LEFT JOIN users u ON ta.user_id = u.id
			WHERE ta.ticket_id = ?
			ORDER BY ta.created_at ASC
		`, ticketID)
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}
		attachments, err := core.ScanRowsToMaps(attRows)
		attRows.Close()
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}

		histRows, err := db.Query(`
			SELECT th.action, th.old_value, th.new_value, th.created_at,
			       CONCAT(u.first_name, ' ', u.last_name) AS user_name
			FROM ticket_history th
			LEFT JOIN users u ON th.user_id = u.id
			WHERE th.ticket_id = ?
			ORDER BY th.created_at ASC
		`, ticketID)
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}
		history, err := core.ScanRowsToMaps(histRows)
		histRows.Close()
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}

		statusRows, err := db.Query("SELECT id, name, label, color FROM ticket_statuses ORDER BY sort_order")
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}
		statuses, err := core.ScanRowsToMaps(statusRows)
		statusRows.Close()
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}

		core.WriteJSON(w, http.StatusOK, map[string]any{
			"success":           true,
			"ticket":            ticket,
			"messages":          messages,
			"attachments":       attachments,
			"history":           history,
			"statuses":          statuses,
			"current_user_id":   u.ID,
			"current_user_role": role.String,
		})
	}
}

func parseIntQuery2(s string) int64 {
	var n int64
	for _, c := range s {
		if c < '0' || c > '9' {
			return 0
		}
		n = n*10 + int64(c-'0')
	}
	return n
}
