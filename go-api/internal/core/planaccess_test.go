package core

import (
	"database/sql"
	"testing"
)

func TestPlanAllowsState(t *testing.T) {
	free := PlanState{Plan: "free"}
	silver := PlanState{Plan: "silver"}
	gold := PlanState{Plan: "gold", WebAccess: true}
	trialOnly := PlanState{Plan: "free", WebAccess: true}

	cases := []struct {
		name    string
		st      PlanState
		client  string
		feature string
		want    bool
	}{
		{"free app create_for_others", free, "app", "create_for_others", false},
		{"silver app create_for_others", silver, "app", "create_for_others", true},
		{"silver app delegate", silver, "app", "delegate", false},
		{"gold app delegate", gold, "app", "delegate", true},
		{"silver app task_overview", silver, "app", "task_overview", false},
		{"gold app task_overview", gold, "app", "task_overview", true},
		{"free web no trial", free, "web", "delegate", false},
		{"trial web delegate", trialOnly, "web", "delegate", true},
		{"trial app delegate (plan stays free)", trialOnly, "app", "delegate", false},
		{"silver web (no web access)", silver, "web", "delegated_tasks", false},
		{"gold web chat", gold, "web", "chat", true},
		{"unknown feature denied", gold, "app", "nope", false},
	}
	for _, c := range cases {
		if got := PlanAllowsState(c.st, c.client, c.feature); got != c.want {
			t.Errorf("%s: got %v want %v", c.name, got, c.want)
		}
	}
}

func TestComputePlanStateLocked(t *testing.T) {
	ns := func(v string) sql.NullString { return sql.NullString{String: v, Valid: v != ""} }
	today := "2026-11-04"
	cases := []struct {
		name                string
		plan, exp, trial    string
		wantLocked, wantWeb bool
	}{
		{"free, trial running", "free", "", "2026-11-10", false, true},
		{"free, trial ends today (expiry day = no access)", "free", "", "2026-11-04", true, false},
		{"free, no trial date", "free", "", "", true, false},
		{"gold active", "gold", "2026-12-01", "", false, true},
		{"gold expired, no trial", "gold", "2026-11-01", "", true, false},
		{"silver active", "silver", "2026-12-01", "", false, false},
		{"gold expired but trial extended", "gold", "2026-11-01", "2026-11-20", false, true},
	}
	for _, c := range cases {
		st := computePlanState(c.plan, 1, ns(c.exp), ns(c.trial), today)
		if st.Locked != c.wantLocked || st.WebAccess != c.wantWeb {
			t.Errorf("%s: locked=%v web=%v, want locked=%v web=%v", c.name, st.Locked, st.WebAccess, c.wantLocked, c.wantWeb)
		}
	}
}
