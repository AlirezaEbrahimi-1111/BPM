package reports

import (
	"database/sql"
	"net/http"
	"sort"

	"bmp/go-api/internal/core"
)

type bottleneckInstance struct {
	InstanceID    int64 `json:"instance_id"`
	InstanceTitle any   `json:"instance_title"`
	StartedAt     any   `json:"started_at"`
	DelayHours    int64 `json:"delay_hours"`
}

type bottleneckGroup struct {
	StepName        any                  `json:"step_name"`
	TemplateID      any                  `json:"template_id"`
	TemplateName    any                  `json:"template_name"`
	ActivitySection any                  `json:"activity_section"`
	Count           int                  `json:"count"`
	AvgDelayHours   float64              `json:"avg_delay_hours"`
	MaxDelayHours   int64                `json:"max_delay_hours"`
	Severity        float64              `json:"severity"`
	Instances       []bottleneckInstance `json:"instances"`

	totalDelay int64 // داخلی — در JSON نمی‌آید (دقیقا مثل PHP که total_delay را در خروجی نمی‌گذارد)
}

// Bottleneck — پورت دقیق api/reports/bottleneck-report.php
//
//	GET /go/api/reports/bottleneck-report
//	→ {"success":true,"summary":{...},"bottlenecks":[...]}
//
// همان کوئری (مراحل active/delayed از موعد گذشته)، همان گروه‌بندی بر
// اساس «قالب::نام مرحله»، همان محاسبه‌ی شدت (count × میانگین تأخیر) و
// همان مرتب‌سازی نزولی.
func Bottleneck(db *sql.DB) http.HandlerFunc {
	const q = `
        SELECT
            ws.step_name,
            ws.activity_section,
            wt.id   AS template_id,
            wt.name AS template_name,
            wi.id        AS instance_id,
            wi.title     AS instance_title,
            wis.started_at,
            COALESCE(
                wis.deadline,
                DATE_ADD(wis.started_at, INTERVAL ws.time_limit_hours HOUR)
            ) AS effective_deadline,
            TIMESTAMPDIFF(
                HOUR,
                COALESCE(wis.deadline, DATE_ADD(wis.started_at, INTERVAL ws.time_limit_hours HOUR)),
                NOW()
            ) AS delay_hours
        FROM workflow_instance_steps wis
        JOIN workflow_steps ws        ON ws.id = wis.step_id
        JOIN workflow_instances wi    ON wi.id = wis.instance_id
        LEFT JOIN workflow_templates wt ON wt.id = wi.template_id
        WHERE wi.organization_id = ?
          AND wi.is_deleted = 0
          AND wi.status NOT IN ('completed', 'cancelled')
          AND wis.status IN ('active', 'delayed')
          AND wis.started_at IS NOT NULL
          AND NOW() > COALESCE(
                wis.deadline,
                DATE_ADD(wis.started_at, INTERVAL ws.time_limit_hours HOUR)
              )
        ORDER BY delay_hours DESC
    `

	return func(w http.ResponseWriter, r *http.Request) {
		u := core.UserOf(r.Context())

		me, err := core.LoadUser(db, u.ID)
		if err != nil || me == nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}
		if !core.HasPermission(me, "monitor_all_workflows") && !core.HasPermission(me, "view_org_dashboard_reports") {
			core.WriteErr(w, http.StatusForbidden, "دسترسی غیرمجاز")
			return
		}

		rows, err := db.Query(q, me.OrganizationID)
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}
		list, err := core.ScanRowsToMaps(rows)
		rows.Close()
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}

		// گروه‌بندی — ترتیب کلیدها باید مثل PHP حفظ شود (PHP آرایه‌ی
		// انجمنی را به ترتیب درج نگه می‌دارد)، چون مرتب‌سازی نهایی
		// پایدار است و در تساوی severity همین ترتیب تعیین‌کننده می‌شود.
		order := []string{}
		groups := map[string]*bottleneckGroup{}

		for _, row := range list {
			templateIDRaw := row["template_id"]
			templateKey := "0"
			if templateIDRaw != nil {
				templateKey = fmtAny(templateIDRaw)
			}
			key := templateKey + "::" + fmtAny(row["step_name"])

			g, ok := groups[key]
			if !ok {
				// PHP: `$r['template_id'] ? (int) $r['template_id'] : null`
				// — شرط truthy است، پس هم NULL و هم 0 به null تبدیل می‌شود.
				var templateID any
				if tid := core.ToInt64(templateIDRaw); tid != 0 {
					templateID = tid
				}
				templateName := row["template_name"]
				if templateName == nil {
					templateName = "نامشخص"
				}
				g = &bottleneckGroup{
					StepName:        row["step_name"],
					TemplateID:      templateID,
					TemplateName:    templateName,
					ActivitySection: row["activity_section"],
					Instances:       []bottleneckInstance{},
				}
				groups[key] = g
				order = append(order, key)
			}

			delay := core.ToInt64(row["delay_hours"])
			if delay < 0 {
				delay = 0
			}

			g.Count++
			g.totalDelay += delay
			if delay > g.MaxDelayHours {
				g.MaxDelayHours = delay
			}
			g.Instances = append(g.Instances, bottleneckInstance{
				InstanceID:    core.ToInt64(row["instance_id"]),
				InstanceTitle: row["instance_title"],
				StartedAt:     row["started_at"],
				DelayHours:    delay,
			})
		}

		result := make([]*bottleneckGroup, 0, len(order))
		for _, key := range order {
			g := groups[key]
			if g.Count > 0 {
				g.AvgDelayHours = round1(float64(g.totalDelay) / float64(g.Count))
			}
			g.Severity = round1(float64(g.Count) * g.AvgDelayHours)
			result = append(result, g)
		}

		// usort در PHP پایدار نیست، ولی از PHP 8.0 پایدار شده — sort.SliceStable
		// همان رفتار را می‌دهد.
		sort.SliceStable(result, func(i, j int) bool {
			return result[i].Severity > result[j].Severity
		})

		totalStuck := 0
		for _, g := range result {
			totalStuck += g.Count
		}
		var worstStage any
		worstCount := 0
		if len(result) > 0 {
			worstStage = result[0].StepName
			worstCount = result[0].Count
		}

		core.WriteJSON(w, http.StatusOK, map[string]any{
			"success": true,
			"summary": map[string]any{
				"bottleneck_stages": len(result),
				"total_stuck":       totalStuck,
				"worst_stage":       worstStage,
				"worst_count":       worstCount,
			},
			"bottlenecks": result,
		})
	}
}
