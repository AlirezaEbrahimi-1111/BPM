package core

import "testing"

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
