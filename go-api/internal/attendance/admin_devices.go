package attendance

import (
	"database/sql"
	"net/http"
	"strings"

	"bmp/go-api/internal/core"
)

// Devices — پورت جزئی api/attendance/devices.php.
//
//	GET  /go/api/attendance/devices
//	POST /go/api/attendance/devices   body: {action: delete|relabel, ...}
//
// 🔒 عمدا پورت نشده: action=approve/reject. هر دو Notification::create()
// (شامل ارسال پیامک async) را صدا می‌زنند — همان مرزی که در ماژول
// notifications گذاشته شد: منطق ساختن نوتیف/پیامک اینجا پورت نمی‌شود.
// اگر این دو action با این endpoint صدا زده شوند، پاسخ «عملیات نامعتبر»
// می‌گیرند؛ فرانت‌اند فعلا به هیچ‌کدام از این Go endpointها وصل نیست، پس
// این محدودیت هیچ اثری روی رفتار زنده ندارد.
func Devices(db *sql.DB) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		u := core.UserOf(r.Context())
		org, ok := requireAttendanceManager(w, db, u.ID)
		if !ok {
			return
		}

		if r.Method == http.MethodGet {
			rows, err := db.Query(`
				SELECT d.*,
				       CONCAT(COALESCE(fu.first_name,''),' ',COALESCE(fu.last_name,'')) AS first_seen_user_name,
				       CONCAT(COALESCE(au.first_name,''),' ',COALESCE(au.last_name,'')) AS approved_by_name
				FROM attendance_devices d
				LEFT JOIN users fu ON d.first_seen_user_id = fu.id
				LEFT JOIN users au ON d.approved_by = au.id
				WHERE d.organization_id = ?
				ORDER BY (d.status = 'pending') DESC, d.created_at DESC
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
			core.WriteJSON(w, http.StatusOK, map[string]any{"success": true, "devices": list})
			return
		}

		var body struct {
			Action string `json:"action"`
			ID     any    `json:"id"`
			Label  string `json:"label"`
		}
		_ = core.ReadJSON(r, &body)

		id := toInt64Body(body.ID)
		if id == 0 {
			core.WriteErr(w, http.StatusBadRequest, "شناسه الزامی است")
			return
		}

		var exists int64
		err := db.QueryRow("SELECT id FROM attendance_devices WHERE id = ? AND organization_id = ?", id, org).Scan(&exists)
		if err == sql.ErrNoRows {
			core.WriteErr(w, http.StatusNotFound, "دستگاه یافت نشد")
			return
		}
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}

		switch body.Action {
		case "delete":
			if _, err := db.Exec("DELETE FROM attendance_devices WHERE id=?", id); err != nil {
				core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
				return
			}
			core.WriteJSON(w, http.StatusOK, map[string]any{"success": true, "message": "دستگاه حذف شد"})
		case "relabel":
			label := strings.TrimSpace(body.Label)
			if _, err := db.Exec("UPDATE attendance_devices SET label=? WHERE id=?", label, id); err != nil {
				core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
				return
			}
			core.WriteJSON(w, http.StatusOK, map[string]any{"success": true, "message": "برچسب ذخیره شد"})
		default:
			core.WriteErr(w, http.StatusBadRequest, "عملیات نامعتبر")
		}
	}
}
