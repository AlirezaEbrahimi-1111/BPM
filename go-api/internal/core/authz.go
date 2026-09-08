package core

import "database/sql"

// ─── پورتِ دقیقِ includes/permissions.php ───────────────────────────
//
// هر تغییری اینجا باید هم‌زمان در includes/permissions.php باشد (و برعکس).
// تستِ authz_test.go موارد را قفل می‌کند.

// SuperAdminIDs — getSuperAdminIds(). تنها جایی که شناسهٔ عددیِ سخت مجاز است.
var SuperAdminIDs = []int64{1, 19}

// permissionMatrix — getPermissionMatrix(): نقش → اجازه‌ها.
var permissionMatrix = map[string][]string{
	"supervisor": supervisorPerms,
	"admin":      supervisorPerms, // مقدارِ قدیمی — همیشه معادلِ supervisor
	"manager": {
		"manage_users",
		"view_section_tasks",
		"view_org_dashboard_reports",
		"create_task",
		"create_recurring_task",
		"create_workflow",
		"approve_deadline_request",
		"approve_overdue_clear",
		"view_reports",
		"send_section_announcement",
	},
	"employee": {
		"create_task",
		"create_recurring_task",
	},
}

var supervisorPerms = []string{
	"manage_users",
	"manage_activity_sections",
	"view_org_settings",
	"manage_task_groups",
	"view_all_org_tasks",
	"view_section_tasks",
	"create_task",
	"create_recurring_task",
	"create_workflow",
	"create_routine_template",
	"monitor_all_workflows",
	"approve_deadline_request",
	"approve_overdue_clear",
	"view_reports",
	"view_payroll",
	"send_org_announcement",
	"send_section_announcement",
	"grant_user_permissions",
}

// individualGrantMap — getIndividualGrantMap(): ستونِ users → اجازه‌ای که می‌دهد.
var individualGrantMap = map[string]string{
	"can_create_routine":  "create_routine_template",
	"can_create_workflow": "create_workflow",
}

// PermUser — همان ستون‌هایی که loadUserForPermissions() می‌خواند.
type PermUser struct {
	ID                int64
	Role              string
	OrganizationID    int64
	ActivitySection   string
	CanCreateRoutine  bool
	CanCreateWorkflow bool
}

// LoadUser — پورتِ loadUserForPermissions(): nil اگر کاربر نبود، یا غیرفعال، یا حذف‌شده.
func LoadUser(db *sql.DB, userID int64) (*PermUser, error) {
	var (
		u                   PermUser
		role, section       sql.NullString
		canRoutine, canWf   sql.NullInt64
		isActive, isDeleted sql.NullInt64
		orgID               sql.NullInt64
	)
	err := db.QueryRow(`
		SELECT id, role, organization_id, activity_section,
		       can_create_routine, can_create_workflow, is_active, is_deleted
		FROM users WHERE id = ?`, userID).
		Scan(&u.ID, &role, &orgID, &section, &canRoutine, &canWf, &isActive, &isDeleted)
	if err == sql.ErrNoRows {
		return nil, nil
	}
	if err != nil {
		return nil, err
	}
	if isActive.Int64 != 1 || isDeleted.Int64 == 1 {
		return nil, nil
	}
	u.Role = role.String
	u.OrganizationID = orgID.Int64
	u.ActivitySection = section.String
	u.CanCreateRoutine = canRoutine.Int64 == 1
	u.CanCreateWorkflow = canWf.Int64 == 1
	return &u, nil
}

// IsSuperAdmin — isSuperAdmin().
func IsSuperAdmin(u *PermUser) bool {
	if u == nil {
		return false
	}
	for _, id := range SuperAdminIDs {
		if u.ID == id {
			return true
		}
	}
	return false
}

// HasPermission — پورتِ hasPermission(): سوپرادمین → اختیارِ فردی → جدولِ نقش.
func HasPermission(u *PermUser, permission string) bool {
	if u == nil {
		return false
	}
	if IsSuperAdmin(u) {
		return true
	}

	// لایهٔ ۲: اختیارِ فردیِ صریح، بر نقش اولویت دارد.
	for col, granted := range individualGrantMap {
		if granted != permission {
			continue
		}
		if (col == "can_create_routine" && u.CanCreateRoutine) ||
			(col == "can_create_workflow" && u.CanCreateWorkflow) {
			return true
		}
	}

	// لایهٔ ۳: نقش.
	role := u.Role
	if role == "" {
		role = "employee"
	}
	perms, ok := permissionMatrix[role]
	if !ok {
		return false // نقشِ ناشناخته → هیچ اجازه‌ای (اصلِ احتیاط)
	}
	for _, p := range perms {
		if p == permission {
			return true
		}
	}
	return false
}

// IsSameOrg — isSameOrganization(): سوپرادمین مستثناست.
func IsSameOrg(u *PermUser, resourceOrgID int64) bool {
	if u == nil {
		return false
	}
	if IsSuperAdmin(u) {
		return true
	}
	return u.OrganizationID == resourceOrgID
}
