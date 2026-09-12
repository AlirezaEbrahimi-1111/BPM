package attendance

import (
	"database/sql"
	"net"
	"net/http"
	"strings"

	"bmp/go-api/internal/core"
)

// AllowedIPs — پورتِ دقیقِ api/attendance/allowed-ips.php (GET + POST action=add/toggle/delete)
//
//	GET  /go/api/attendance/allowed-ips
//	POST /go/api/attendance/allowed-ips   body: {action, ...}
func AllowedIPs(db *sql.DB) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		u := core.UserOf(r.Context())
		org, ok := requireAttendanceManager(w, db, u.ID)
		if !ok {
			return
		}

		if r.Method == http.MethodGet {
			rows, err := db.Query("SELECT * FROM attendance_allowed_ips WHERE organization_id = ? ORDER BY created_at DESC", org)
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
			core.WriteJSON(w, http.StatusOK, map[string]any{"success": true, "ips": list})
			return
		}

		var body struct {
			Action    string `json:"action"`
			IPAddress string `json:"ip_address"`
			Label     string `json:"label"`
			ID        any    `json:"id"`
		}
		_ = core.ReadJSON(r, &body)

		if body.Action == "add" {
			ip := strings.TrimSpace(body.IPAddress)
			label := strings.TrimSpace(body.Label)
			if net.ParseIP(ip) == nil {
				core.WriteErr(w, http.StatusBadRequest, "آدرس IP نامعتبر است")
				return
			}
			_, err := db.Exec(`INSERT INTO attendance_allowed_ips (organization_id, ip_address, label, is_active, created_by, created_at)
				VALUES (?, ?, ?, 1, ?, NOW())
				ON DUPLICATE KEY UPDATE label = VALUES(label), is_active = 1`, org, ip, label, u.ID)
			if err != nil {
				core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
				return
			}
			core.WriteJSON(w, http.StatusOK, map[string]any{"success": true, "message": "IP ثبت شد"})
			return
		}

		id := toInt64Body(body.ID)
		if id == 0 {
			core.WriteErr(w, http.StatusBadRequest, "شناسه الزامی است")
			return
		}
		var isActive int
		err := db.QueryRow("SELECT is_active FROM attendance_allowed_ips WHERE id = ? AND organization_id = ?", id, org).Scan(&isActive)
		if err == sql.ErrNoRows {
			core.WriteErr(w, http.StatusNotFound, "IP یافت نشد")
			return
		}
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}

		switch body.Action {
		case "toggle":
			newVal := 1
			if isActive != 0 {
				newVal = 0
			}
			if _, err := db.Exec("UPDATE attendance_allowed_ips SET is_active = ? WHERE id = ?", newVal, id); err != nil {
				core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
				return
			}
			msg := "غیرفعال شد"
			if newVal != 0 {
				msg = "فعال شد"
			}
			core.WriteJSON(w, http.StatusOK, map[string]any{"success": true, "message": msg, "is_active": newVal})
		case "delete":
			if _, err := db.Exec("DELETE FROM attendance_allowed_ips WHERE id = ?", id); err != nil {
				core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
				return
			}
			core.WriteJSON(w, http.StatusOK, map[string]any{"success": true, "message": "حذف شد"})
		default:
			core.WriteErr(w, http.StatusBadRequest, "عملیات نامعتبر")
		}
	}
}

func toInt64Body(v any) int64 {
	switch n := v.(type) {
	case float64:
		return int64(n)
	case int64:
		return n
	}
	return 0
}
