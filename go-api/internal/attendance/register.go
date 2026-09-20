package attendance

import (
	"crypto/sha256"
	"database/sql"
	"encoding/hex"
	"net/http"
	"strconv"
	"strings"
	"time"

	"bmp/go-api/internal/core"
)

// registerBody — بدنه‌ی درخواستِ ثبتِ ورود/خروج.
type registerBody struct {
	Action      string `json:"action"`
	Shift       int    `json:"shift"`
	Fingerprint string `json:"fingerprint"`
}

func sha256Hex(s string) string {
	h := sha256.Sum256([]byte(s))
	return hex.EncodeToString(h[:])
}

// Register — پورتِ دقیقِ api/attendance/register.php (ثبتِ ورود/خروج).
//
//	POST /go/api/attendance/register   body: {action, shift, fingerprint}
//
// 🔒 طبقِ یادداشتِ بالایِ main.go، این فایل به‌خاطرِ ریسکِ مالی/عملیاتیِ
// داده‌هایِ حضور (این جدول مستقیم توسطِ monthly-report.php/monthly-deficit.php/
// leave-balance*.php خونده می‌شه) با دقتِ کامل و پیام‌به‌پیام برابرِ نسخه‌ی
// PHP پورت شده — از جمله کدهایِ HTTPِ به‌ظاهر عجیبِ PHP (مثلاً «قبلاً ثبت
// شده» با کدِ ۲۰۰ به‌جایِ ۴۰۰، چون خودِ PHP هم اونجا http_response_code
// صدا نمی‌زنه).
//
// 🔒 تنها استثنا: حالتِ نادرِ «دستگاهِ کاملاً جدید + کاربرِ تأییدنشده» —
// اون یک شاخه به‌جایِ بازنویسیِ Notification::create() اینجا، یک تماسِ
// داخلیِ HTTP به api/internal/notify-new-device.php می‌زنه (نگاه کن به
// core.NotifyNewDeviceAsync و internal/attendance/admin_devices.go برایِ
// همون مرزِ ازقبل‌پذیرفته‌شده).
func Register(db *sql.DB, cfg core.Config) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		u := core.UserOf(r.Context())
		org := u.OrgID
		if org == 0 {
			core.WriteJSON(w, http.StatusForbidden, map[string]any{"success": false, "message": "سازمان نامعتبر"})
			return
		}

		var body registerBody
		_ = core.ReadJSON(r, &body)
		shift := body.Shift
		if shift == 0 {
			shift = 1
		}

		clientIP := core.ClientIP(r)
		fp := strings.TrimSpace(body.Fingerprint)

		var fpHashForLog any
		if fp != "" {
			fpHashForLog = sha256Hex(fp)
		}
		var actionForLog any
		if body.Action != "" {
			actionForLog = body.Action
		}
		ua := r.UserAgent()
		if len(ua) > 255 {
			ua = ua[:255]
		}

		logDenied := func(reason string) {
			_, _ = db.Exec(`
				INSERT INTO attendance_denied_log
					(organization_id, user_id, action, reason, ip_address, fingerprint_hash, user_agent, created_at)
				VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
			`, org, u.ID, actionForLog, reason, clientIP, fpHashForLog, ua)
		}

		// ── لایهٔ ۱: IP داخلیِ مجاز ──
		var ipCount int
		if err := db.QueryRow(`
			SELECT COUNT(*) FROM attendance_allowed_ips
			WHERE organization_id = ? AND ip_address = ? AND is_active = 1
		`, org, clientIP).Scan(&ipCount); err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطا در پردازش درخواست")
			return
		}
		if ipCount == 0 {
			logDenied("IP_NOT_ALLOWED")
			core.WriteJSON(w, http.StatusForbidden, map[string]any{
				"success": false, "message": "ثبت ورود/خروج فقط از شبکهٔ مجاز سازمان امکان‌پذیر است", "error_code": "IP_NOT_ALLOWED",
			})
			return
		}

		// ── لایهٔ ۲: دستگاهِ تأییدشده ──
		if len(fp) < 16 {
			logDenied("NO_FINGERPRINT")
			core.WriteJSON(w, http.StatusBadRequest, map[string]any{
				"success": false, "message": "شناسهٔ دستگاه ارسال نشد. لطفا صفحه را تازه کنید.", "error_code": "NO_FINGERPRINT",
			})
			return
		}
		fpHash := sha256Hex(fp)

		var deviceStatus sql.NullString
		err := db.QueryRow(`
			SELECT status FROM attendance_devices
			WHERE organization_id = ? AND fingerprint_hash = ?
			LIMIT 1
		`, org, fpHash).Scan(&deviceStatus)

		if err == sql.ErrNoRows {
			// دستگاه اصلاً دیده نشده — یا خودکار تأیید می‌شه (کاربرِ ازقبل‌تأییدشده)
			// یا در انتظار می‌مونه و مدیران مطلع می‌شن
			var approvedCount int
			_ = db.QueryRow("SELECT COUNT(*) FROM attendance_approved_users WHERE organization_id = ? AND user_id = ? LIMIT 1", org, u.ID).Scan(&approvedCount)
			autoApprove := approvedCount > 0
			if !autoApprove {
				var prevCount int
				_ = db.QueryRow("SELECT COUNT(*) FROM attendance_records WHERE user_id = ? AND organization_id = ?", u.ID, org).Scan(&prevCount)
				autoApprove = prevCount > 0
			}

			status := "pending"
			if autoApprove {
				status = "approved"
			}
			res, insErr := db.Exec(`
				INSERT IGNORE INTO attendance_devices
					(organization_id, fingerprint_hash, status, first_seen_ip, first_seen_user_id, created_at)
				VALUES (?, ?, ?, ?, ?, NOW())
			`, org, fpHash, status, clientIP, u.ID)
			if insErr != nil {
				core.WriteErr(w, http.StatusInternalServerError, "خطا در پردازش درخواست")
				return
			}

			if autoApprove {
				_, _ = db.Exec("UPDATE attendance_devices SET last_used_at = NOW() WHERE organization_id = ? AND fingerprint_hash = ?", org, fpHash)
				_, _ = db.Exec("INSERT IGNORE INTO attendance_approved_users (organization_id, user_id, approved_at) VALUES (?, ?, NOW())", org, u.ID)
				// اجازهٔ ثبتِ ورود/خروج — ادامه به پایینِ تابع
			} else {
				if rows, _ := res.RowsAffected(); rows > 0 {
					core.NotifyNewDeviceAsync(cfg, org, u.ID, clientIP)
				}
				logDenied("DEVICE_PENDING")
				core.WriteJSON(w, http.StatusForbidden, map[string]any{
					"success": false, "message": "این دستگاه هنوز تأیید نشده است. از سرپرست بخواهید آن را در پنل دستگاه‌ها تأیید کند.", "error_code": "DEVICE_PENDING",
				})
				return
			}
		} else if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطا در پردازش درخواست")
			return
		} else if deviceStatus.String != "approved" {
			reason := "DEVICE_NOT_APPROVED"
			msg := "این دستگاه هنوز در انتظار تأیید سرپرست است."
			if deviceStatus.String == "rejected" {
				reason = "DEVICE_REJECTED"
				msg = "این دستگاه توسط سرپرست رد شده است."
			}
			logDenied(reason)
			core.WriteJSON(w, http.StatusForbidden, map[string]any{
				"success": false, "message": msg, "error_code": "DEVICE_NOT_APPROVED",
			})
			return
		} else {
			_, _ = db.Exec("UPDATE attendance_devices SET last_used_at = NOW() WHERE organization_id = ? AND fingerprint_hash = ?", org, fpHash)
		}

		// ============================================
		// پایانِ گاردِ امنیتی — شروعِ ثبتِ ورود/خروج
		// ============================================
		if body.Action != "check_in" && body.Action != "check_out" {
			core.WriteJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "Invalid action"})
			return
		}

		now := core.TehranNow()
		today := now.Format("2006-01-02")
		nowStr := now.Format("2006-01-02 15:04:05")
		currentTime := now.Format("15:04:05")

		var shiftCount int
		var shift1Start, shift1End, shift2Start, shift2End sql.NullString
		err = db.QueryRow(`
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
		_ = shift1Start // فقط برایِ parity با SELECTِ PHP نگه داشته شده؛ منطق ازش استفاده نمی‌کنه (خودِ PHP هم همینطوره)

		// ⭐ محدودیتِ شیفتِ دوم: فقط از ۳۰ دقیقه قبلِ شروعِ شیفت ۲
		if body.Action == "check_in" && shift == 2 {
			if shiftCount < 2 {
				core.WriteJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "شما کاربر دو شیفته نیستید"})
				return
			}
			if !shift2Start.Valid || shift2Start.String == "" {
				core.WriteJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "ساعت شروع شیفت دوم تعریف نشده است"})
				return
			}
			shiftStart, ok1 := parseClock(shift2Start.String)
			curr, ok2 := parseClock(currentTime)
			if ok1 && ok2 {
				allowed := shiftStart.Add(-30 * time.Minute)
				if curr.Before(allowed) {
					core.WriteJSON(w, http.StatusBadRequest, map[string]any{
						"success":       false,
						"message":       "الان زمان ثبت ورود نیست (ورود شیفت ۲ از ساعت " + allowed.Format("15:04") + " امکان‌پذیر است)",
						"allowed_time":  allowed.Format("15:04:05"),
						"shift_2_start": shift2Start.String,
						"current_time":  currentTime,
						"error_code":    "TOO_EARLY_FOR_SHIFT_2",
					})
					return
				}
			}
		}

		// ⭐ محدودیتِ ورودِ شیفتِ ۱: فقط تا پایانِ شیفتِ ۱ (برایِ کاربرانِ دوشیفته)
		if body.Action == "check_in" && shift == 1 && shiftCount >= 2 && shift1End.Valid && shift1End.String != "" {
			end, ok1 := parseClock(shift1End.String)
			curr, ok2 := parseClock(currentTime)
			if ok1 && ok2 && !curr.Before(end) {
				core.WriteJSON(w, http.StatusBadRequest, map[string]any{
					"success": false, "message": "زمان ثبت ورود شیفت ۱ به پایان رسیده است", "error_code": "SHIFT_1_ENDED",
				})
				return
			}
		}

		if shift > shiftCount {
			core.WriteJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "شماره شیفت نامعتبر است"})
			return
		}

		var recordID int64
		var checkIn, checkOut sql.NullString
		err = db.QueryRow(`
			SELECT id, check_in, check_out FROM attendance_records
			WHERE user_id = ? AND date = ? AND shift_number = ?
		`, u.ID, today, shift).Scan(&recordID, &checkIn, &checkOut)
		hasRecord := true
		if err == sql.ErrNoRows {
			hasRecord = false
		} else if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطا در پردازش درخواست")
			return
		}

		shiftStr := strconv.Itoa(shift)

		if body.Action == "check_in" {
			if hasRecord && checkIn.Valid && !checkOut.Valid {
				// 🔒 عیناً مثلِ PHP: بدونِ http_response_code صریح → پیش‌فرضِ ۲۰۰
				core.WriteJSON(w, http.StatusOK, map[string]any{
					"success": false, "message": "شما قبلا ورود شیفت " + shiftStr + " را ثبت کرده‌اید",
				})
				return
			}

			var execErr error
			if hasRecord {
				_, execErr = db.Exec("UPDATE attendance_records SET check_in = ?, check_out = NULL, updated_at = NOW() WHERE id = ?", nowStr, recordID)
			} else {
				_, execErr = db.Exec(`
					INSERT INTO attendance_records (user_id, organization_id, date, shift_number, check_in, created_at)
					VALUES (?, ?, ?, ?, ?, NOW())
				`, u.ID, org, today, shift, nowStr)
			}
			if execErr != nil {
				core.WriteErr(w, http.StatusInternalServerError, "خطا در پردازش درخواست")
				return
			}

			msg := "ورود شیفت " + shiftStr + " ثبت شد"
			if hasRecord {
				msg = "ورود شیفت " + shiftStr + " مجددا ثبت شد"
			}
			core.WriteJSON(w, http.StatusOK, map[string]any{
				"success": true, "message": msg, "shift_number": shift, "check_in": nowStr,
			})
			return
		}

		// action === check_out
		if !hasRecord || !checkIn.Valid {
			core.WriteJSON(w, http.StatusOK, map[string]any{
				"success": false, "message": "ابتدا باید ورود شیفت " + shiftStr + " را ثبت کنید",
			})
			return
		}
		if checkOut.Valid {
			core.WriteJSON(w, http.StatusOK, map[string]any{
				"success": false, "message": "شما قبلا خروج شیفت " + shiftStr + " را ثبت کرده‌اید",
			})
			return
		}
		if _, err := db.Exec("UPDATE attendance_records SET check_out = ?, updated_at = NOW() WHERE id = ?", nowStr, recordID); err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطا در پردازش درخواست")
			return
		}
		core.WriteJSON(w, http.StatusOK, map[string]any{
			"success": true, "message": "خروج شیفت " + shiftStr + " ثبت شد", "shift_number": shift, "check_out": nowStr,
		})
	}
}
