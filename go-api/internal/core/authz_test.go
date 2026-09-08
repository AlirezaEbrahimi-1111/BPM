package core

import "testing"

// قفلِ تطبیق با includes/permissions.php::hasPermission (بدونِ دیتابیس).

func TestHasPermission(t *testing.T) {
	sup := &PermUser{ID: 100, Role: "supervisor"}
	mgr := &PermUser{ID: 101, Role: "manager"}
	emp := &PermUser{ID: 102, Role: "employee"}
	empWf := &PermUser{ID: 103, Role: "employee", CanCreateWorkflow: true}
	sadmin := &PermUser{ID: 1, Role: "employee"} // سوپرادمین با شناسه
	unknown := &PermUser{ID: 104, Role: "ghost"}

	cases := []struct {
		name string
		u    *PermUser
		perm string
		want bool
	}{
		{"nil user", nil, "create_task", false},
		{"supervisor manage_users", sup, "manage_users", true},
		{"supervisor view_payroll", sup, "view_payroll", true},
		{"manager manage_users (coarse)", mgr, "manage_users", true},
		{"manager NOT view_payroll", mgr, "view_payroll", false},
		{"manager NOT view_all_org_tasks", mgr, "view_all_org_tasks", false},
		{"employee create_task", emp, "create_task", true},
		{"employee NOT create_workflow", emp, "create_workflow", false},
		{"employee w/ grant -> create_workflow", empWf, "create_workflow", true},
		{"superadmin gets anything", sadmin, "view_payroll", true},
		{"superadmin unknown perm still true", sadmin, "totally_made_up", true},
		{"unknown role -> nothing", unknown, "create_task", false},
	}
	for _, c := range cases {
		if got := HasPermission(c.u, c.perm); got != c.want {
			t.Errorf("%s: HasPermission(%q) = %v ; want %v", c.name, c.perm, got, c.want)
		}
	}
}

func TestIsSameOrg(t *testing.T) {
	u := &PermUser{ID: 200, Role: "employee", OrganizationID: 5}
	sa := &PermUser{ID: 1, OrganizationID: 5}
	if !IsSameOrg(u, 5) {
		t.Error("same org should be true")
	}
	if IsSameOrg(u, 6) {
		t.Error("different org should be false")
	}
	if !IsSameOrg(sa, 999) {
		t.Error("superadmin crosses org boundary")
	}
	if IsSameOrg(nil, 5) {
		t.Error("nil user false")
	}
}
