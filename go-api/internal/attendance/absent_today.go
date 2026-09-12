package attendance

import (
	"database/sql"
	"net/http"
	"strings"

	"bmp/go-api/internal/core"
)

type absentEntry struct {
	UserID int64  `json:"user_id"`
	Name   string `json:"name"`
	Type   string `json:"type"`
}

// AbsentToday — پورتِ دقیقِ api/attendance/absent-today.php
//
//	GET /go/api/attendance/absent-today
//	→ {"success":true,"holiday":bool,"today":"YYYY-MM-DD","absent":[...],"count":N}
func AbsentToday(db *sql.DB) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		u := core.UserOf(r.Context())
		now := core.TehranNow()
		today := now.Format("2006-01-02")

		var orgID sql.NullInt64
		err := db.QueryRow("SELECT organization_id FROM users WHERE id = ?", u.ID).Scan(&orgID)
		if err != nil || !orgID.Valid {
			core.WriteJSON(w, http.StatusOK, map[string]any{
				"success": true, "holiday": false, "today": today, "absent": []absentEntry{}, "count": 0,
			})
			return
		}

		holidays, err := holidaySet(db, orgID.Int64)
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}
		recurring, err := recurringHolidayWeekdays(db, orgID.Int64)
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}
		if !isWorkingDay(now, holidays, recurring) {
			core.WriteJSON(w, http.StatusOK, map[string]any{
				"success": true, "holiday": true, "today": today, "absent": []absentEntry{}, "count": 0,
			})
			return
		}

		rows, err := db.Query(`
			SELECT id, first_name, last_name
			FROM users
			WHERE organization_id = ?
			  AND is_active = 1
			  AND COALESCE(is_deleted, 0) = 0
			  AND id <> ?
			  AND COALESCE(role, '') <> 'supervisor'
			  AND COALESCE(is_supervisor, 0) = 0
			  AND COALESCE(shift_count, 0) >= 1
			ORDER BY first_name, last_name
		`, orgID.Int64, u.ID)
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}
		type userRow struct {
			id                  int64
			firstName, lastName sql.NullString
		}
		var users []userRow
		for rows.Next() {
			var ur userRow
			if err := rows.Scan(&ur.id, &ur.firstName, &ur.lastName); err != nil {
				rows.Close()
				core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
				return
			}
			users = append(users, ur)
		}
		rows.Close()

		if len(users) == 0 {
			core.WriteJSON(w, http.StatusOK, map[string]any{
				"success": true, "holiday": false, "today": today, "absent": []absentEntry{}, "count": 0,
			})
			return
		}

		ids := make([]int64, len(users))
		args := make([]any, len(users))
		for i, ur := range users {
			ids[i] = ur.id
			args[i] = ur.id
		}
		ph := strings.TrimSuffix(strings.Repeat("?,", len(ids)), ",")

		present := map[int64]bool{}
		presentArgs := append(append([]any{}, args...), today)
		presentRows, err := db.Query("SELECT DISTINCT user_id FROM attendance_records WHERE user_id IN ("+ph+") AND date = ? AND check_in IS NOT NULL", presentArgs...)
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}
		for presentRows.Next() {
			var uid int64
			if err := presentRows.Scan(&uid); err != nil {
				presentRows.Close()
				core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
				return
			}
			present[uid] = true
		}
		presentRows.Close()

		onLeave := map[int64]bool{}
		leaveArgs := append(append([]any{}, args...), today, today)
		leaveRows, err := db.Query("SELECT DISTINCT user_id FROM leave_requests WHERE user_id IN ("+ph+") AND status = 'approved' AND start_date <= ? AND end_date >= ?", leaveArgs...)
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}
		for leaveRows.Next() {
			var uid int64
			if err := leaveRows.Scan(&uid); err != nil {
				leaveRows.Close()
				core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
				return
			}
			onLeave[uid] = true
		}
		leaveRows.Close()

		type passWindow struct{ start, end string }
		passByUser := map[int64][]passWindow{}
		passArgs := append(append([]any{}, args...), today)
		passRows, err := db.Query("SELECT user_id, start_time, end_time FROM pass_requests WHERE user_id IN ("+ph+") AND pass_date = ? AND (status IS NULL OR status <> 'cancelled')", passArgs...)
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}
		for passRows.Next() {
			var uid int64
			var start, end sql.NullString
			if err := passRows.Scan(&uid, &start, &end); err != nil {
				passRows.Close()
				core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
				return
			}
			passByUser[uid] = append(passByUser[uid], passWindow{start.String, end.String})
		}
		passRows.Close()

		nowT := now.Format("15:04:05")
		absent := []absentEntry{}
		for _, ur := range users {
			if present[ur.id] {
				continue
			}
			name := strings.TrimSpace(ur.firstName.String + " " + ur.lastName.String)

			if onLeave[ur.id] {
				absent = append(absent, absentEntry{ur.id, name, "leave"})
				continue
			}

			inPassNow := false
			for _, pw := range passByUser[ur.id] {
				if pw.start != "" && pw.end != "" && nowT >= pw.start && nowT <= pw.end {
					inPassNow = true
					break
				}
			}
			if inPassNow {
				continue
			}

			absent = append(absent, absentEntry{ur.id, name, "absent"})
		}

		core.WriteJSON(w, http.StatusOK, map[string]any{
			"success": true,
			"holiday": false,
			"today":   today,
			"absent":  absent,
			"count":   len(absent),
		})
	}
}
