package main

// ═══════════════════════════════════════════════════════════════════
//  CRM جدید — داشبورد فروش (تب «فروش» داشبورد مدیر/حسابداری، ۱۴۰۵/۰۸/۰۳) و
//  کارت «کارهای کارشناس فروش» (داشبورد کارشناس فروش).
//
//  GET  /ucrm/dashboard?from=YYYY-MM-DD&to=YYYY-MM-DD&rep_id=
//       needs_followup: مشتری فعالی که بیش از N روز (ucrm_settings.followup_alert_days) پیگیری
//                       «انجام‌شده» نداشته (یا هیچ‌وقت — آن‌وقت از روز ثبت مشتری شمرده می‌شود).
//                       بازهٔ تاریخ روی این بخش اثر ندارد.
//       matrix:         برای هر کارشناس، جمع تارگت ماهانهٔ مؤثر مشتری‌های فعالش در بازه
//                       (ماهِ نیمه‌کاره به نسبت روزها) + فروش. فروش فعلا صفر است تا فاکتورهای CRM ساخته شوند.
//       مدیر و حسابداری همه را می‌بینند (با فیلتر کارشناس)؛ بقیه فقط مشتری‌های خودشان.
//  POST /ucrm/customers/{id}/nudge — زنگ «اطلاع‌رسانی»: اعلان + پیامک به کارشناس همان مشتری
//       (از راه رویداد followup_nudge → api/ucrm/notify-events.php). فقط مدیر و حسابداری.
//  GET  /ucrm/followups/open — همهٔ پیگیری‌های انجام‌نشدهٔ مشتری‌های خود کاربر (اول عقب‌افتاده‌ها).
// ═══════════════════════════════════════════════════════════════════

import (
	"fmt"
	"net/http"
	"sort"
	"strconv"
	"time"
)

type ucrmNeedFollowup struct {
	ID           int64  `json:"id"`
	Name         string `json:"name"`
	RepID        int64  `json:"rep_id"`
	RepName      string `json:"rep_name"`
	LastFollowup string `json:"last_followup_at"` // خالی = هیچ‌وقت
	Days         int    `json:"days"`
}

type ucrmMatrixRow struct {
	RepID   int64  `json:"rep_id"`
	RepName string `json:"rep_name"`
	Target  int64  `json:"target"`
	Sales   int64  `json:"sales"`
}

// monthsInRange: ماه‌های شمسی که بازهٔ میلادی [from, to] را می‌پوشانند + تعداد روزهای مشترک هر ماه با بازه
func monthsInRange(from, to time.Time) (keys []string, overlap map[string]int, days map[string]int) {
	overlap, days = map[string]int{}, map[string]int{}
	for d := from; !d.After(to); d = d.AddDate(0, 0, 1) {
		jy, jm, _ := gregorianToJalali(d.Year(), int(d.Month()), d.Day())
		k := fmt.Sprintf("%04d-%02d", jy, jm)
		if _, ok := overlap[k]; !ok {
			keys = append(keys, k)
			days[k] = jalaliMonthDays(jy, jm)
		}
		overlap[k]++
	}
	return
}

// GET /ucrm/dashboard
func (s *server) ucrmDashboard(w http.ResponseWriter, r *http.Request) {
	uid := userOf(r.Context()).ID
	ro := s.ucrmRolesOf(uid)
	seesAll := ro.Manager || ro.Accounting
	q := r.URL.Query()
	repID := uid
	if seesAll {
		repID, _ = strconv.ParseInt(q.Get("rep_id"), 10, 64)
	}
	from, err1 := time.Parse("2006-01-02", q.Get("from"))
	to, err2 := time.Parse("2006-01-02", q.Get("to"))
	if err1 != nil || err2 != nil || to.Before(from) || to.Sub(from) > 3*366*24*time.Hour {
		writeErr(w, http.StatusBadRequest, "بازهٔ تاریخ معتبر نیست.")
		return
	}
	set := s.ucrmLoadSettings()

	repWhere, repArgs := "", []any{}
	if repID > 0 {
		repWhere, repArgs = " AND c.rep_id = ?", []any{repID}
	}

	// ── مشتریان نیازمند پیگیری ──
	args := append([]any{ucrmOrg}, repArgs...)
	args = append(args, set.FollowupDays)
	rows, err := s.db.Query(`
		SELECT c.id, c.name, COALESCE(c.rep_id,0), TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))),
		       COALESCE(DATE_FORMAT(c.last_followup_at,'%Y-%m-%d %H:%i:%s'),''),
		       DATEDIFF(CURDATE(), DATE(COALESCE(c.last_followup_at, c.created_at))) AS days
		FROM ucrm_customers c
		LEFT JOIN users u ON u.id = c.rep_id
		WHERE c.organization_id = ? AND c.is_deleted = 0 AND c.status = 'active'`+repWhere+`
		HAVING days > ?
		ORDER BY days DESC, c.name`, args...)
	if err != nil {
		writeDBErr(w, "ucrmDashboard needs", err)
		return
	}
	needs := []ucrmNeedFollowup{}
	for rows.Next() {
		var n ucrmNeedFollowup
		if err := rows.Scan(&n.ID, &n.Name, &n.RepID, &n.RepName, &n.LastFollowup, &n.Days); err != nil {
			rows.Close()
			writeDBErr(w, "ucrmDashboard needs scan", err)
			return
		}
		needs = append(needs, n)
	}
	rows.Close()

	// ── ماتریس تارگت و فروش ──
	// کارشناس‌ها: واحد فروش (به‌جز حساب پشتیبان) + هر کسی که مشتری فعال دارد
	names := map[int64]string{}
	rr, err := s.db.Query(`
		SELECT u.id, TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')))
		FROM users u
		WHERE u.is_active = 1 AND u.organization_id = 1 AND u.id <> 1
		  AND (u.activity_section = 'sales'
		    OR EXISTS (SELECT 1 FROM user_activity_sections s WHERE s.user_id = u.id AND s.section_key = 'sales')
		    OR EXISTS (SELECT 1 FROM ucrm_customers c WHERE c.rep_id = u.id AND c.organization_id = ? AND c.is_deleted = 0 AND c.status = 'active'))`, ucrmOrg)
	if err != nil {
		writeDBErr(w, "ucrmDashboard reps", err)
		return
	}
	for rr.Next() {
		var id int64
		var nm string
		if rr.Scan(&id, &nm) == nil {
			names[id] = nm
		}
	}
	rr.Close()

	custRep := map[int64]int64{}
	cr, err := s.db.Query(`SELECT c.id, c.rep_id FROM ucrm_customers c
		WHERE c.organization_id = ? AND c.is_deleted = 0 AND c.status = 'active' AND c.rep_id IS NOT NULL`+repWhere,
		append([]any{ucrmOrg}, repArgs...)...)
	if err != nil {
		writeDBErr(w, "ucrmDashboard customers", err)
		return
	}
	for cr.Next() {
		var cid, rep int64
		if cr.Scan(&cid, &rep) == nil {
			custRep[cid] = rep
		}
	}
	cr.Close()

	target := map[int64]float64{}
	keys, overlap, mdays := monthsInRange(from, to)
	for _, k := range keys {
		eff, err := s.ucrmEffective(custRep, "monthly", k)
		if err != nil {
			writeDBErr(w, "ucrmDashboard targets", err)
			return
		}
		ratio := float64(overlap[k]) / float64(mdays[k])
		for cid, e := range eff {
			if e.Active && e.Target != nil {
				target[custRep[cid]] += float64(*e.Target) * ratio
			}
		}
	}

	matrix := []ucrmMatrixRow{}
	for id, nm := range names {
		if repID > 0 && id != repID {
			continue
		}
		matrix = append(matrix, ucrmMatrixRow{RepID: id, RepName: nm, Target: int64(target[id] + 0.5)})
	}
	sort.Slice(matrix, func(a, b int) bool {
		if matrix[a].Target != matrix[b].Target {
			return matrix[a].Target > matrix[b].Target
		}
		return matrix[a].RepName < matrix[b].RepName
	})

	reps := []map[string]any{}
	if seesAll {
		for id, nm := range names {
			reps = append(reps, map[string]any{"id": id, "name": nm})
		}
		sort.Slice(reps, func(a, b int) bool { return reps[a]["name"].(string) < reps[b]["name"].(string) })
	}

	writeJSON(w, http.StatusOK, map[string]any{
		"needs_followup": needs,
		"matrix":         matrix,
		"sales_ready":    false, // فروش واقعی بعد از ساخت فاکتورهای CRM
		"reps":           reps,
		"roles":          ro,
		"settings":       set,
	})
}

// POST /ucrm/customers/{id}/nudge
func (s *server) ucrmNudge(w http.ResponseWriter, r *http.Request) {
	uid := userOf(r.Context()).ID
	ro := s.ucrmRolesOf(uid)
	if !ro.Manager && !ro.Accounting {
		writeErr(w, http.StatusForbidden, "فقط مدیر یا حسابداری می‌تواند به کارشناس یادآوری کند.")
		return
	}
	c, err := s.ucrmLoad(idParam(r), uid, ro)
	if err != nil {
		writeDBErr(w, "ucrmNudge", err)
		return
	}
	if c == nil {
		writeErr(w, http.StatusNotFound, "مشتری یافت نشد")
		return
	}
	if c.RepID <= 0 {
		writeErr(w, http.StatusBadRequest, "این مشتری کارشناس فروش ندارد.")
		return
	}
	if c.RepID == uid {
		writeErr(w, http.StatusBadRequest, "کارشناس این مشتری خود شما هستید.")
		return
	}
	// جلوی زدن پشت‌سرهم: هر مشتری حداکثر یک یادآوری در یک ساعت
	var recent int
	_ = s.db.QueryRow(`SELECT COUNT(*) FROM ucrm_customer_events
		WHERE customer_id = ? AND action = 'followup_nudge' AND created_at > NOW() - INTERVAL 1 HOUR`, c.ID).Scan(&recent)
	if recent > 0 {
		writeErr(w, http.StatusConflict, "در یک ساعت گذشته برای این مشتری به کارشناس اطلاع داده شده است.")
		return
	}
	var days int
	_ = s.db.QueryRow(`SELECT DATEDIFF(CURDATE(), DATE(COALESCE(last_followup_at, created_at))) FROM ucrm_customers WHERE id = ?`, c.ID).Scan(&days)
	actor := s.userName(uid)
	note := fmt.Sprintf("%s به کارشناس «%s» اطلاع داد که مشتری «%s» %s روز است پیگیری نشده است.",
		actor, c.RepName, c.Name, faDigits(strconv.Itoa(days)))
	ev := s.ucrmEvent(s.db, c.ID, uid, "followup_nudge", note)
	if ev == 0 {
		writeErr(w, http.StatusInternalServerError, "ثبت یادآوری ناموفق بود.")
		return
	}
	s.ucrmNotifyAsync(r, []int64{ev})
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "message": "به کارشناس «" + c.RepName + "» اطلاع داده شد."})
}

type ucrmOpenFollowup struct {
	ID           int64  `json:"id"`
	CustomerID   int64  `json:"customer_id"`
	CustomerName string `json:"customer_name"`
	Title        string `json:"title"`
	Type         string `json:"type"`
	DueAt        string `json:"due_at"`
	Overdue      bool   `json:"overdue"`
}

// GET /ucrm/followups/open
func (s *server) ucrmOpenFollowups(w http.ResponseWriter, r *http.Request) {
	uid := userOf(r.Context()).ID
	rows, err := s.db.Query(`
		SELECT f.id, c.id, c.name, f.title, f.type, DATE_FORMAT(f.due_at,'%Y-%m-%d %H:%i:%s'), (f.due_at < NOW())
		FROM ucrm_followups f
		JOIN ucrm_customers c ON c.id = f.customer_id
		WHERE f.status = 'pending' AND f.is_deleted = 0
		  AND c.organization_id = ? AND c.is_deleted = 0 AND c.rep_id = ?
		ORDER BY f.due_at, f.id
		LIMIT 500`, ucrmOrg, uid)
	if err != nil {
		writeDBErr(w, "ucrmOpenFollowups", err)
		return
	}
	defer rows.Close()
	items := []ucrmOpenFollowup{}
	for rows.Next() {
		var f ucrmOpenFollowup
		var od int
		if err := rows.Scan(&f.ID, &f.CustomerID, &f.CustomerName, &f.Title, &f.Type, &f.DueAt, &od); err != nil {
			writeDBErr(w, "ucrmOpenFollowups scan", err)
			return
		}
		f.Overdue = od == 1
		items = append(items, f)
	}
	writeJSON(w, http.StatusOK, map[string]any{"items": items})
}
