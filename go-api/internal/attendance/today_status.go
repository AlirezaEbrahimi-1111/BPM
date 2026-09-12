package attendance

import (
	"database/sql"
	"net/http"
	"time"

	"bmp/go-api/internal/core"
)

// parseClock — یک رشتهٔ ساعتِ MySQL ("HH:MM:SS" یا "HH:MM") را روی یک
// تاریخِ ثابتِ دلخواه (برایِ مقایسه/جمع‌وتفریق) پارس می‌کند. معادلِ
// `new DateTime($timeString)` در PHP وقتی فقط رشتهٔ ساعت داده می‌شود
// (PHP خودش تاریخِ امروز را فرض می‌کند؛ اینجا یک تاریخِ ثابتِ دلخواه کافی
// است چون فقط برایِ مقایسهٔ نسبی استفاده می‌شود).
func parseClock(s string) (time.Time, bool) {
	if s == "" {
		return time.Time{}, false
	}
	for _, layout := range []string{"15:04:05", "15:04"} {
		if t, err := time.Parse(layout, s); err == nil {
			return t, true
		}
	}
	return time.Time{}, false
}

type statusButton struct {
	Type    string `json:"type"`
	Shift   int    `json:"shift"`
	Label   string `json:"label"`
	Enabled bool   `json:"enabled"`
}

// TodayStatus — پورتِ دقیقِ api/attendance/today-status.php
//
//	GET /go/api/attendance/today-status
//	→ {"success":true,"shift_count":N,"buttons":[...],"window_message":str|null,
//	   "status_message":str,"current_time":"HH:MM","shift1":{...},"shift2":{...}}
func TodayStatus(db *sql.DB) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		u := core.UserOf(r.Context())
		now := core.TehranNow()
		today := now.Format("2006-01-02")

		var shiftCount sql.NullInt64
		var shift1Start, shift1End, shift2Start, shift2End sql.NullString
		err := db.QueryRow(`
			SELECT shift_count, shift_1_start, shift_1_end, shift_2_start, shift_2_end
			FROM users WHERE id = ?
		`, u.ID).Scan(&shiftCount, &shift1Start, &shift1End, &shift2Start, &shift2End)
		if err == sql.ErrNoRows {
			core.WriteErr(w, http.StatusInternalServerError, "خطا در پردازش درخواست")
			return
		}
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطا در پردازش درخواست")
			return
		}

		rows, err := db.Query(`
			SELECT shift_number, check_in, check_out
			FROM attendance_records
			WHERE user_id = ? AND date = ?
			ORDER BY shift_number ASC
		`, u.ID, today)
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطا در پردازش درخواست")
			return
		}
		defer rows.Close()

		var shift1In, shift1Out, shift2In, shift2Out sql.NullString
		for rows.Next() {
			var shiftNum int
			var checkIn, checkOut sql.NullString
			if err := rows.Scan(&shiftNum, &checkIn, &checkOut); err != nil {
				core.WriteErr(w, http.StatusInternalServerError, "خطا در پردازش درخواست")
				return
			}
			if shiftNum == 1 {
				shift1In, shift1Out = checkIn, checkOut
			} else if shiftNum == 2 {
				shift2In, shift2Out = checkIn, checkOut
			}
		}

		buttons := []statusButton{}
		var windowMessage any = nil

		if shiftCount.Int64 == 1 {
			if !shift1In.Valid {
				buttons = append(buttons, statusButton{"check_in", 1, "ورود", true})
			} else if !shift1Out.Valid {
				buttons = append(buttons, statusButton{"check_out", 1, "خروج", true})
			}
		} else {
			nowClock, _ := parseClock(now.Format("15:04:05"))

			shift1InOpen := true
			if shift1End.Valid && shift1End.String != "" {
				if end, ok := parseClock(shift1End.String); ok {
					shift1InOpen = nowClock.Before(end)
				}
			}

			shift2InOpen := true
			if shift2Start.Valid && shift2Start.String != "" {
				if start, ok := parseClock(shift2Start.String); ok {
					allowed := start.Add(-30 * time.Minute)
					shift2InOpen = !nowClock.Before(allowed)
				}
			}

			switch {
			case shift2In.Valid && !shift2Out.Valid:
				buttons = append(buttons, statusButton{"check_out", 2, "خروج شیفت 2", true})
			case shift2In.Valid && shift2Out.Valid:
				// هر دو شیفت کامل شده — بدونِ دکمه
			case !shift1In.Valid:
				if shift1InOpen {
					buttons = append(buttons, statusButton{"check_in", 1, "ورود شیفت 1", true})
				} else if shift2InOpen {
					buttons = append(buttons, statusButton{"check_in", 2, "ورود شیفت 2", true})
				} else {
					windowMessage = "الان زمان ثبت ورود نیست"
				}
			case !shift1Out.Valid:
				buttons = append(buttons, statusButton{"check_out", 1, "خروج شیفت 1", true})
			default:
				if shift2InOpen {
					buttons = append(buttons, statusButton{"check_in", 2, "ورود شیفت 2", true})
				} else {
					windowMessage = "الان زمان ثبت ورود نیست"
				}
			}
		}

		statusMessage := ""
		if shift1In.Valid {
			statusMessage = "ورود ثبت شد: " + hhmm(shift1In.String)
			if shift1Out.Valid {
				statusMessage += " | خروج: " + hhmm(shift1Out.String)
			}
		}
		if shift2In.Valid {
			statusMessage += " | ورود ش۲: " + hhmm(shift2In.String)
			if shift2Out.Valid {
				statusMessage += " | خروج ش۲: " + hhmm(shift2Out.String)
			}
		}

		core.WriteJSON(w, http.StatusOK, map[string]any{
			"success":        true,
			"shift_count":    int(shiftCount.Int64),
			"buttons":        buttons,
			"window_message": windowMessage,
			"status_message": statusMessage,
			"current_time":   now.Format("15:04"),
			"shift1": map[string]any{
				"check_in":  nsHHMM(shift1In),
				"check_out": nsHHMM(shift1Out),
			},
			"shift2": map[string]any{
				"check_in":  nsHHMM(shift2In),
				"check_out": nsHHMM(shift2Out),
			},
		})
	}
}

// hhmm — معادلِ substr($datetime, 11, 5) در PHP: از یک رشتهٔ
// "YYYY-MM-DD HH:MM:SS" فقط بخشِ "HH:MM" را برمی‌دارد.
func hhmm(s string) string {
	if len(s) >= 16 {
		return s[11:16]
	}
	return s
}

func nsHHMM(n sql.NullString) any {
	if !n.Valid {
		return nil
	}
	return hhmm(n.String)
}
