package attendance

import (
	"database/sql"
	"net/http"

	"bmp/go-api/internal/core"
)

// dlRangeCond — پورتِ دقیقِ dl_range_cond() در denied-log.php.
func dlRangeCond(rng string) string {
	switch rng {
	case "today":
		return "AND DATE(l.created_at) = CURDATE()"
	case "week":
		return "AND l.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
	default:
		return ""
	}
}

// DeniedLog — پورتِ دقیقِ api/attendance/denied-log.php
//
//	GET  /go/api/attendance/denied-log?range=today|week|all
//	POST /go/api/attendance/denied-log   body: {action: delete|delete_filtered|clear, ...}
func DeniedLog(db *sql.DB) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		u := core.UserOf(r.Context())
		org, ok := requireAttendanceManager(w, db, u.ID)
		if !ok {
			return
		}

		if r.Method == http.MethodGet {
			rng := r.URL.Query().Get("range")
			if rng == "" {
				rng = "today"
			}
			cond := dlRangeCond(rng)
			rows, err := db.Query(`
				SELECT l.id, l.action, l.reason, l.ip_address, l.user_agent, l.created_at,
				       CONCAT(COALESCE(uu.first_name,''),' ',COALESCE(uu.last_name,'')) AS user_name
				FROM attendance_denied_log l
				LEFT JOIN users uu ON l.user_id = uu.id
				WHERE l.organization_id = ? AND l.is_deleted = 0 `+cond+`
				ORDER BY l.created_at DESC
				LIMIT 500
			`, org)
			if err != nil {
				core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
				return
			}
			list, err := core.ScanRowsToMaps(rows)
			rows.Close()
			if err != nil {
				core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
				return
			}
			core.WriteJSON(w, http.StatusOK, map[string]any{"success": true, "logs": list})
			return
		}

		var body struct {
			Action string `json:"action"`
			ID     any    `json:"id"`
			Range  string `json:"range"`
		}
		_ = core.ReadJSON(r, &body)

		switch body.Action {
		case "delete":
			id := toInt64Body(body.ID)
			if id == 0 {
				core.WriteErr(w, http.StatusBadRequest, "شناسه الزامی است")
				return
			}
			_, err := db.Exec(`UPDATE attendance_denied_log SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?
				WHERE id = ? AND organization_id = ?`, u.ID, id, org)
			if err != nil {
				core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
				return
			}
			core.WriteJSON(w, http.StatusOK, map[string]any{"success": true, "message": "حذف شد"})
		case "delete_filtered", "clear":
			rng := body.Range
			if rng == "" {
				rng = "today"
			}
			cond := dlRangeCond(rng)
			_, err := db.Exec(`UPDATE attendance_denied_log l
				SET l.is_deleted = 1, l.deleted_at = NOW(), l.deleted_by = ?
				WHERE l.organization_id = ? AND l.is_deleted = 0 `+cond, u.ID, org)
			if err != nil {
				core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
				return
			}
			core.WriteJSON(w, http.StatusOK, map[string]any{"success": true, "message": "موارد فیلترشده حذف شدند"})
		default:
			core.WriteErr(w, http.StatusBadRequest, "عملیات نامعتبر")
		}
	}
}
