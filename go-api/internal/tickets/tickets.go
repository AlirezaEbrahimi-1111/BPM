// Package tickets — پورتِ api/tickets/list.php + api/tickets/mark-all-read.php
// (فقط این دو — بقیه‌ی api/tickets/* عملیاتِ روی یک تیکتِ تکی‌اند، نه چیزی
// که هدر روی هر بارگذاریِ صفحه صدا بزند).
package tickets

import (
	"database/sql"
	"math"
	"net/http"
	"strconv"
	"strings"

	"bmp/go-api/internal/core"
)

// ticketObserverIds — پورتِ ticketObserverIds() در includes/ticket_notify.php.
var ticketObserverIds = map[int64]bool{19: true}

func isTicketObserver(userID int64) bool {
	return ticketObserverIds[userID]
}

func hasReadsTable(db *sql.DB) bool {
	var name string
	err := db.QueryRow("SHOW TABLES LIKE 'ticket_message_reads'").Scan(&name)
	return err == nil
}

func qInt(r *http.Request, key string, def int) int {
	if v := r.URL.Query().Get(key); v != "" {
		if n, err := strconv.Atoi(v); err == nil {
			return n
		}
	}
	return def
}

// List — پورتِ دقیقِ api/tickets/list.php
//
//	GET /go/api/tickets/list?page=&limit=&status=&priority=&category=&search=&mine=1
func List(db *sql.DB) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		u := core.UserOf(r.Context())

		me, err := core.LoadUser(db, u.ID)
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}
		if me == nil {
			core.WriteErr(w, http.StatusUnauthorized, "کاربر یافت نشد")
			return
		}

		page := qInt(r, "page", 1)
		if page < 1 {
			page = 1
		}
		limit := qInt(r, "limit", 20)
		if limit < 1 {
			limit = 1
		}
		if limit > 200 {
			limit = 200
		}
		status := strings.TrimSpace(r.URL.Query().Get("status"))
		priority := strings.TrimSpace(r.URL.Query().Get("priority"))
		category := strings.TrimSpace(r.URL.Query().Get("category"))
		search := strings.TrimSpace(r.URL.Query().Get("search"))
		mineScope := r.URL.Query().Get("mine") == "1"
		offset := (page - 1) * limit

		baseWhere := []string{"t.deleted_at IS NULL"}
		var baseParams []any

		if !core.IsSuperAdmin(me) {
			role := me.Role
			if role == "" {
				role = "employee"
			}
			switch role {
			case "supervisor", "admin":
				baseWhere = append(baseWhere, "t.organization_id = ?")
				baseParams = append(baseParams, me.OrganizationID)
			case "manager":
				subIDs, err := core.GetSubordinateIds(db, u.ID)
				if err != nil {
					core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
					return
				}
				subIDs = append(subIDs, u.ID)
				placeholders := strings.TrimSuffix(strings.Repeat("?,", len(subIDs)), ",")
				baseWhere = append(baseWhere, "t.created_by IN ("+placeholders+")")
				for _, id := range subIDs {
					baseParams = append(baseParams, id)
				}
			default:
				baseWhere = append(baseWhere, "t.created_by = ?")
				baseParams = append(baseParams, u.ID)
			}
		}
		baseWhereSQL := "WHERE " + strings.Join(baseWhere, " AND ")

		where := append([]string{}, baseWhere...)
		params := append([]any{}, baseParams...)

		if status != "" {
			where = append(where, "ts.name = ?")
			params = append(params, status)
		}
		if priority != "" {
			where = append(where, "tp.name = ?")
			params = append(params, priority)
		}
		if category != "" {
			catInt, _ := strconv.Atoi(category)
			where = append(where, "t.category_id = ?")
			params = append(params, catInt)
		}
		if search != "" {
			where = append(where, "(t.subject LIKE ? OR t.ticket_number LIKE ?)")
			like := "%" + search + "%"
			params = append(params, like, like)
		}

		supportIDs := core.SuperAdminIDs // [1, 19]
		supportStrs := make([]string, len(supportIDs))
		for i, id := range supportIDs {
			supportStrs[i] = strconv.FormatInt(id, 10)
		}
		supportList := strings.Join(supportStrs, ",")
		iAmSupport := core.IsSuperAdmin(me)

		lastAuthorSub := `(SELECT tm2.user_id FROM ticket_messages tm2
			WHERE tm2.ticket_id = t.id AND tm2.deleted_at IS NULL
			ORDER BY tm2.created_at DESC, tm2.id DESC LIMIT 1)`

		hasReads := hasReadsTable(db)

		if mineScope && !iAmSupport {
			where = append(where, "(t.created_by = ? OR t.assigned_to = ?)")
			params = append(params, u.ID, u.ID)
		}
		whereSQL := "WHERE " + strings.Join(where, " AND ")

		ballExpr := "(" + lastAuthorSub + " IN (" + supportList + "))"
		if iAmSupport {
			ballExpr = "(" + lastAuthorSub + " IS NOT NULL AND " + lastAuthorSub + " NOT IN (" + supportList + "))"
		}
		unseenExpr := "1"
		if hasReads {
			unseenExpr = `((SELECT MAX(tm4.created_at) FROM ticket_messages tm4
				WHERE tm4.ticket_id = t.id AND tm4.deleted_at IS NULL)
			  > COALESCE((SELECT tmr2.last_read_at FROM ticket_message_reads tmr2
			                WHERE tmr2.ticket_id = t.id AND tmr2.user_id = ?),
			             '1000-01-01 00:00:00'))`
		}
		awaitingExpr := "(CASE WHEN " + ballExpr + " AND " + unseenExpr + " THEN 1 ELSE 0 END) as awaiting_you"

		// ── آمار (بر اساسِ فیلترِ پایه) ──
		statsSQL := `
			SELECT
				COUNT(*) as total,
				SUM(CASE WHEN ts.name = 'open' THEN 1 ELSE 0 END) as open_count,
				SUM(CASE WHEN ts.name = 'in_progress' THEN 1 ELSE 0 END) as in_progress_count,
				SUM(CASE WHEN ts.name = 'waiting_reply' THEN 1 ELSE 0 END) as waiting_reply_count,
				SUM(CASE WHEN ts.name = 'resolved' THEN 1 ELSE 0 END) as resolved_count,
				SUM(CASE WHEN ts.name = 'closed' THEN 1 ELSE 0 END) as closed_count
			FROM tickets t
			LEFT JOIN ticket_statuses ts ON t.status_id = ts.id
			LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
			` + baseWhereSQL

		statsRows, err := db.Query(statsSQL, baseParams...)
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطا در پردازش درخواست")
			return
		}
		statsMaps, err := core.ScanRowsToMaps(statsRows)
		statsRows.Close()
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطا در پردازش درخواست")
			return
		}
		var stats map[string]any
		if len(statsMaps) > 0 {
			stats = statsMaps[0]
		} else {
			stats = map[string]any{}
		}
		total := toInt64(stats["total"])

		unseenSelect := "0 as unseen_count"
		if hasReads {
			unseenSelect = `(SELECT COUNT(*) FROM ticket_messages tmu
				WHERE tmu.ticket_id = t.id
				  AND tmu.deleted_at IS NULL
				  AND tmu.user_id <> ?
				  AND tmu.created_at > COALESCE(
					  (SELECT tmr.last_read_at FROM ticket_message_reads tmr
					     WHERE tmr.ticket_id = t.id AND tmr.user_id = ?),
					  '1000-01-01 00:00:00')
			   ) as unseen_count`
		}

		listSQL := `
			SELECT
				t.id,
				t.ticket_number,
				t.subject,
				t.status_id,
				t.priority_id,
				t.category_id,
				t.created_by,
				t.assigned_to,
				t.created_at,
				t.updated_at,
				ts.label   as status_label,
				ts.name    as status,
				ts.color   as status_color,
				tp.label   as priority_label,
				tp.name    as priority,
				tp.color   as priority_color,
				tc.name    as category_name,
				CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')) as creator_name,
				(SELECT COUNT(*) FROM ticket_messages tm WHERE tm.ticket_id = t.id) as message_count,
				(SELECT COUNT(*) FROM ticket_attachments ta WHERE ta.ticket_id = t.id) as attachment_count,
				` + unseenSelect + `,
				` + awaitingExpr + `,
				` + lastAuthorSub + ` as last_msg_user_id
			FROM tickets t
			LEFT JOIN ticket_statuses ts ON t.status_id = ts.id
			LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
			LEFT JOIN ticket_categories tc ON t.category_id = tc.id
			LEFT JOIN users u ON t.created_by = u.id
			` + whereSQL + `
			ORDER BY t.created_at DESC
			LIMIT ? OFFSET ?`

		var listParams []any
		if hasReads {
			listParams = append(listParams, u.ID, u.ID, u.ID)
		}
		listParams = append(listParams, params...)
		listParams = append(listParams, limit, offset)

		listRows, err := db.Query(listSQL, listParams...)
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطا در پردازش درخواست")
			return
		}
		ticketsOut, err := core.ScanRowsToMaps(listRows)
		listRows.Close()
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطا در پردازش درخواست")
			return
		}

		viewerIsObserver := isTicketObserver(u.ID)
		for _, tk := range ticketsOut {
			awaiting := toInt64(tk["awaiting_you"])
			tk["awaiting_you"] = awaiting
			if viewerIsObserver &&
				toInt64(tk["created_by"]) != u.ID &&
				toInt64(tk["assigned_to"]) != u.ID {
				tk["awaiting_you"] = int64(0)
			}
		}

		totalPages := 1.0
		if total > 0 {
			totalPages = math.Ceil(float64(total) / float64(limit))
		}

		core.WriteJSON(w, http.StatusOK, map[string]any{
			"success": true,
			"tickets": ticketsOut,
			"stats":   stats,
			"pagination": map[string]any{
				"page":        page,
				"limit":       limit,
				"total":       total,
				"total_pages": totalPages,
			},
		})
	}
}

// MarkAllRead — پورتِ دقیقِ api/tickets/mark-all-read.php
//
//	POST /go/api/tickets/mark-all-read
func MarkAllRead(db *sql.DB) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		u := core.UserOf(r.Context())

		if !hasReadsTable(db) {
			core.WriteJSON(w, http.StatusOK, map[string]any{
				"success": true, "updated": 0, "message": "جدول ردیابی موجود نیست",
			})
			return
		}

		me, err := core.LoadUser(db, u.ID)
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}
		iAmSupport := core.IsSuperAdmin(me)

		scopeSQL := ""
		params := []any{u.ID}
		if !iAmSupport {
			scopeSQL = " AND (t.created_by = ? OR t.assigned_to = ?)"
			params = append(params, u.ID, u.ID)
		}

		sqlStr := `
			INSERT INTO ticket_message_reads (ticket_id, user_id, last_read_at)
			SELECT t.id, ?, NOW()
			FROM tickets t
			WHERE t.deleted_at IS NULL` + scopeSQL + `
			ON DUPLICATE KEY UPDATE last_read_at = NOW()
		`
		res, err := db.Exec(sqlStr, params...)
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}
		affected, _ := res.RowsAffected()
		core.WriteJSON(w, http.StatusOK, map[string]any{"success": true, "updated": affected})
	}
}

func toInt64(v any) int64 {
	switch n := v.(type) {
	case int64:
		return n
	case float64:
		return int64(n)
	}
	return 0
}
