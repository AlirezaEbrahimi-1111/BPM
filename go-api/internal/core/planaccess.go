package core

// پورتِ دقیقِ includes/plan-access.php — هر تغییری باید هم‌زمان در هر دو باشد.
// قواعد: پلن واقعی با انقضا (امروز < plan_expires_at، NULL = دائمی)؛ وب با
// web_access (تستِ وب یا طلایی) و سطح طلایی؛ هدر X-Client: web|app (نبودن = app).

import (
	"database/sql"
	"net/http"
	"strings"
)

type PlanState struct {
	Plan      string // free | silver | gold (سطح مؤثر، با انقضا)
	PlanUsers int64
	WebAccess bool
}

var planRank = map[string]int{"free": 0, "silver": 1, "gold": 2}

var planFeatureMin = map[string]string{
	"create_for_others":  "silver",
	"delegated_tasks":    "silver",
	"daily_tasks":        "silver",
	"user_management":    "silver",
	"delegate":           "gold",
	"routine":            "gold",
	"history_share":      "gold",
	"viewers":            "gold",
	"chat":               "gold",
	"notifications":      "gold",
	"attendance_checkin": "gold",
	"task_overview":      "gold",
	"monitoring":         "gold",
	"admin_other":        "gold",
}

const planRestrictedMsg = "این امکان در پلن سازمان شما فعال نیست"

// PlanClient — 'web' اگر هدر X-Client=web باشد، وگرنه 'app'.
func PlanClient(r *http.Request) string {
	if strings.EqualFold(strings.TrimSpace(r.Header.Get("X-Client")), "web") {
		return "web"
	}
	return "app"
}

// PlanAllowsState — تصمیمِ خالص (بدون DB)؛ همتایِ planAllows() در PHP.
func PlanAllowsState(st PlanState, client, feature string) bool {
	min, ok := planFeatureMin[feature]
	if !ok {
		return false
	}
	level := st.Plan
	if client == "web" {
		if !st.WebAccess {
			return false
		}
		level = "gold"
	}
	return planRank[level] >= planRank[min]
}

// LoadPlanState — همتایِ planState() در PHP. todayYMD مثل "2026-10-03" (به وقت تهران).
func LoadPlanState(db *sql.DB, orgID int64, todayYMD string) (PlanState, error) {
	var plan sql.NullString
	var users sql.NullInt64
	var expires, trialEnds sql.NullString
	err := db.QueryRow(`
		SELECT plan, plan_users,
		       DATE_FORMAT(plan_expires_at, '%Y-%m-%d'),
		       DATE_FORMAT(web_trial_ends_at, '%Y-%m-%d')
		FROM organizations WHERE id = ?`, orgID).Scan(&plan, &users, &expires, &trialEnds)
	if err == sql.ErrNoRows {
		return PlanState{Plan: "free", PlanUsers: 1}, nil
	}
	if err != nil {
		return PlanState{}, err
	}

	paidActive := (plan.String == "silver" || plan.String == "gold") &&
		(!expires.Valid || todayYMD < expires.String)
	effective := "free"
	if paidActive {
		effective = plan.String
	}
	trialActive := trialEnds.Valid && todayYMD < trialEnds.String

	return PlanState{
		Plan:      effective,
		PlanUsers: users.Int64,
		WebAccess: trialActive || effective == "gold",
	}, nil
}

// RequirePlan — میان‌افزارِ امکان؛ باید داخل s.auth قرار گیرد (کاربر در context است).
func RequirePlan(db *sql.DB, feature string, next http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		u := UserOf(r.Context())
		var orgID sql.NullInt64
		if err := db.QueryRow("SELECT organization_id FROM users WHERE id = ?", u.ID).Scan(&orgID); err != nil {
			WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}
		st, err := LoadPlanState(db, orgID.Int64, TehranNow().Format("2006-01-02"))
		if err != nil {
			WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}
		if !PlanAllowsState(st, PlanClient(r), feature) {
			WriteJSON(w, http.StatusForbidden, map[string]any{
				"success": false,
				"code":    "PLAN_RESTRICTED",
				"message": planRestrictedMsg,
			})
			return
		}
		next(w, r)
	}
}
