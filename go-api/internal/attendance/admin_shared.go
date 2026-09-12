package attendance

import (
	"database/sql"
	"net/http"

	"bmp/go-api/internal/core"
)

// canManageAttendanceConfig — همون قاعده‌ی تکرارشده در سه فایلِ
// allowed-ips.php/devices.php/denied-log.php:
//
//	in_array($user_id, getSuperAdminIds()) || in_array($role, ['supervisor','management'])
//
// عمداً از core.IsSuperAdmin/HasPermission استفاده نمی‌کند چون خودِ PHP هم
// اینجا مستقیماً از ماتریسِ اجازه‌ها استفاده نمی‌کرده، همین چکِ ad-hoc را
// تکرار کرده — برایِ پاریتی، همان‌جا عیناً نگه داشته شده.
func canManageAttendanceConfig(userID int64, role string) bool {
	for _, id := range core.SuperAdminIDs {
		if id == userID {
			return true
		}
	}
	return role == "supervisor" || role == "management"
}

// loadOrgRole — سازمان و نقشِ کاربرِ جاری (معادلِ getUserInfo() برایِ همین سه فایل).
func loadOrgRole(db *sql.DB, userID int64) (orgID int64, role string, err error) {
	var orgN sql.NullInt64
	var roleN sql.NullString
	err = db.QueryRow("SELECT organization_id, role FROM users WHERE id = ?", userID).Scan(&orgN, &roleN)
	if err != nil {
		return 0, "", err
	}
	role = roleN.String
	if role == "" {
		role = "employee"
	}
	return orgN.Int64, role, nil
}

// requireAttendanceManager — گاردِ مشترکِ هر سه endpoint. اگر اجازه نبود،
// خودش ۴۰۳ می‌نویسد و false برمی‌گرداند.
func requireAttendanceManager(w http.ResponseWriter, db *sql.DB, userID int64) (orgID int64, ok bool) {
	orgID, role, err := loadOrgRole(db, userID)
	if err != nil {
		core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
		return 0, false
	}
	if !canManageAttendanceConfig(userID, role) {
		core.WriteErr(w, http.StatusForbidden, "دسترسی غیرمجاز")
		return 0, false
	}
	return orgID, true
}
