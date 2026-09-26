package tasks

import (
	"database/sql"
	"net/http"
	"strconv"
	"strings"

	"bmp/go-api/internal/core"
)

func sectionsPlaceholder(sections []string) string {
	if len(sections) == 0 {
		return "NULL"
	}
	return strings.TrimSuffix(strings.Repeat("?,", len(sections)), ",")
}

func sectionsArgs(sections []string) []any {
	args := make([]any, len(sections))
	for i, s := range sections {
		args[i] = s
	}
	return args
}

// MyTasks — پورت دقیق api/tasks/my-tasks.php.
func MyTasks(db *sql.DB) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		u := core.UserOf(r.Context())

		var orgID sql.NullInt64
		var activitySection sql.NullString
		err := db.QueryRow(
			"SELECT organization_id, activity_section FROM users WHERE id = ? AND is_active = 1 AND is_deleted = 0",
			u.ID,
		).Scan(&orgID, &activitySection)
		if err == sql.ErrNoRows {
			core.WriteErr(w, http.StatusUnauthorized, "کاربر یافت نشد")
			return
		}
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}

		sections, err := core.UserSections(db, u.ID)
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}
		secPh := sectionsPlaceholder(sections)

		if r.URL.Query().Get("filter") == "checklist_archive" {
			handleChecklistArchive(w, db, u.ID, orgID.Int64, sections, secPh)
			return
		}

		// my-tasks.php عمدا getHolidaySet($db) رو بدون org_id صدا می‌زنه —
		// یعنی فقط تعطیلات سراسری (organization_id IS NULL) رو می‌بینه، نه
		// تعطیلات مخصوص سازمان کاربر. اینجا هم دقیقا همون رفتار (nil) تکرار می‌شه.
		holidays, err := core.HolidaySet(db, nil)
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}

		today := core.TehranNow().Format("2006-01-02")

		q := `
SELECT DISTINCT
    t.id,
    t.title,
    t.description,
    t.task_type,
    t.priority,
    t.status,
    t.due_date,
    t.start_date,
    t.end_date,
    t.last_approved_date,
    t.period_type,
    t.overdue_forgiven_credit,
    t.is_pending_approval,
    t.has_pending_renewal_request,
    t.is_workflow_task,
    t.workflow_instance_id,
    t.deadline,
    t.original_deadline,
    t.activity_section,
    t.creator_id,
    t.assignee_id,
    t.created_at,
    t.updated_at,
    dr.current_approver_id,
    dr.created_at AS deadline_request_date,
    t.has_pending_deadline_request,
    t.has_pending_overdue_request,
    ph.last_pending_date,
    creator.first_name as creator_first_name,
    creator.last_name as creator_last_name,
    CONCAT(COALESCE(creator.first_name, ''), ' ', COALESCE(creator.last_name, '')) as creator_name,
    assignee.first_name as assignee_first_name,
    assignee.last_name as assignee_last_name,
    CONCAT(COALESCE(assignee.first_name, ''), ' ', COALESCE(assignee.last_name, '')) as assignee_name,
    (
        SELECT CONCAT_WS(' ',
            (
                SELECT GROUP_CONCAT(
                    CONCAT_WS(' ', fu.first_name, fu.last_name, tu.first_name, tu.last_name,
                        CASE WHEN th.notes LIKE '{%' THEN JSON_UNQUOTE(JSON_EXTRACT(th.notes, '$.reason')) ELSE th.notes END)
                    SEPARATOR ' '
                )
                FROM task_history th
                LEFT JOIN users fu ON th.from_user_id = fu.id
                LEFT JOIN users tu ON th.to_user_id = tu.id
                WHERE th.task_id = t.id
            ),
            (
                SELECT GROUP_CONCAT(ta.file_original_name SEPARATOR ' ')
                FROM task_attachments ta
                WHERE ta.task_id = t.id
            )
        )
    ) AS history_text,
    t.group_id,
    tg.name as group_name,
    tg.color as group_color,
    tg.icon as group_icon
FROM tasks t
LEFT JOIN users creator ON t.creator_id = creator.id AND creator.is_active = 1 AND creator.is_deleted = 0
LEFT JOIN users assignee ON t.assignee_id = assignee.id AND assignee.is_active = 1 AND assignee.is_deleted = 0
LEFT JOIN task_groups tg ON t.group_id = tg.id
LEFT JOIN deadline_requests dr ON t.id = dr.task_id AND dr.status = 'pending'
LEFT JOIN overdue_clear_requests ocr ON t.id = ocr.task_id AND ocr.status = 'pending'
LEFT JOIN (
    SELECT h1.task_id, h1.created_at as last_pending_date
    FROM task_history h1
    WHERE h1.action = 'pending_approval'
    AND NOT EXISTS (
        SELECT 1 FROM task_history h2
        WHERE h2.task_id = h1.task_id
        AND h2.action = 'rejected'
        AND h2.created_at > h1.created_at
    )
    AND h1.created_at = (
        SELECT MAX(h3.created_at) FROM task_history h3
        WHERE h3.task_id = h1.task_id
        AND h3.action = 'pending_approval'
        AND NOT EXISTS (
            SELECT 1 FROM task_history h4
            WHERE h4.task_id = h3.task_id
            AND h4.action = 'rejected'
            AND h4.created_at > h3.created_at
        )
    )
) ph ON t.id = ph.task_id
WHERE t.is_deleted = 0
AND t.status != 'rejected'
  AND (
    dr.current_approver_id = ?
    OR ocr.current_approver_id = ?
    OR (
      dr.current_approver_id IS NULL
    AND (
      (
        t.assignee_id = ?
        AND NOT (
          t.is_workflow_task = 1
          AND NOT EXISTS (
              SELECT 1 FROM workflow_instance_steps wis
              WHERE wis.task_id = t.id AND wis.status IN ('active', 'pending', 'delayed')
          )
        )
      )
      OR (
        t.is_workflow_task = 1
        AND t.activity_section IN (` + secPh + `)
        AND t.organization_id = ?
        AND t.status NOT IN ('completed', 'cancelled')
        AND (t.assignee_id IS NULL OR t.assignee_id = 0)
        AND EXISTS (
            SELECT 1 FROM workflow_instance_steps wis
            WHERE wis.task_id = t.id AND wis.status IN ('active', 'pending', 'delayed')
        )
      )
      OR (t.is_pending_approval = 1 AND t.assignee_id = ?)
    )
    )
     OR EXISTS (
        SELECT 1 FROM task_checklist_items ci
        WHERE ci.task_id = t.id
        AND ci.is_done = 0
          AND (
              (ci.assignee_type = 'user'    AND ci.assignee_value = ?)
              OR (ci.assignee_type = 'section' AND ci.assignee_value IN (` + secPh + `) AND t.organization_id = ?)
          )
    )
  )
ORDER BY
    CASE WHEN t.has_pending_deadline_request = 1 AND t.creator_id = ? THEN 0 ELSE 1 END,
    CASE WHEN t.has_pending_overdue_request = 1 AND ocr.current_approver_id = ? THEN 0 ELSE 1 END,
    CASE WHEN t.is_pending_approval = 1 THEN 1 ELSE 2 END,

    GREATEST(
        COALESCE(t.original_deadline, '1000-01-01'),
        COALESCE(t.deadline, '1000-01-01'),
        COALESCE(t.due_date, '1000-01-01')
    ) ASC,

    t.created_at ASC
`

		args := []any{u.ID, u.ID, u.ID}
		args = append(args, sectionsArgs(sections)...)
		args = append(args, orgID.Int64, u.ID, strconv.FormatInt(u.ID, 10))
		args = append(args, sectionsArgs(sections)...)
		args = append(args, orgID.Int64, u.ID, u.ID)

		rows, err := db.Query(q, args...)
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}
		allTasks, err := core.ScanRowsToMaps(rows)
		rows.Close()
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}

		var continuousIDs []int64
		for _, t := range allTasks {
			if strOf(t, "task_type") == "continuous" {
				continuousIDs = append(continuousIDs, int64Of(t, "id"))
			}
		}
		completionMap, err := core.PePreloadCompletionDates(db, continuousIDs)
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}

		processed := make([]map[string]any, 0, len(allTasks))
		for _, task := range allTasks {
			if strOf(task, "task_type") == "continuous" && strOf(task, "start_date") != "" {
				MaybeStartNextPeriod(db, task, today, holidays, completionMap)
			}
			EnrichTaskDates(task, db, holidays, today, completionMap)

			if strOf(task, "task_type") == "continuous" && strOf(task, "end_date") != "" {
				if strOf(task, "next_due_date") > strOf(task, "end_date") {
					continue
				}
			}
			processed = append(processed, task)
		}

		if err := AttachChecklistTitles(db, processed); err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}

		var activitySectionOut any
		if activitySection.Valid {
			activitySectionOut = activitySection.String
		}

		core.WriteJSON(w, http.StatusOK, map[string]any{
			"success": true,
			"data": map[string]any{
				"tasks":            processed,
				"count":            len(processed),
				"user_id":          u.ID,
				"activity_section": activitySectionOut,
				"today":            today,
			},
			"message": "کارها با موفقیت بارگذاری شدند",
		})
	}
}

func handleChecklistArchive(w http.ResponseWriter, db *sql.DB, userID int64, orgID int64, sections []string, secPh string) {
	q := `
        SELECT
            t.id, t.title, t.description, t.task_type, t.priority, t.status,
            t.due_date, t.created_at, t.creator_id, t.assignee_id,
            CONCAT(COALESCE(creator.first_name,''),' ',COALESCE(creator.last_name,'')) AS creator_name,
            CONCAT(COALESCE(assignee.first_name,''),' ',COALESCE(assignee.last_name,'')) AS assignee_name
        FROM tasks t
        LEFT JOIN users creator  ON t.creator_id  = creator.id  AND creator.is_active = 1  AND creator.is_deleted = 0
        LEFT JOIN users assignee ON t.assignee_id = assignee.id AND assignee.is_active = 1 AND assignee.is_deleted = 0
        WHERE t.is_deleted = 0
          AND t.organization_id = ?
          AND t.assignee_id <> ?
          AND EXISTS (
              SELECT 1 FROM task_checklist_items ci
              WHERE ci.task_id = t.id
               AND (
                    (ci.assignee_type = 'user'    AND ci.assignee_value = ?)
                    OR (ci.assignee_type = 'section' AND ci.assignee_value IN (` + secPh + `))
                )
          )
          AND NOT EXISTS (
              SELECT 1 FROM task_checklist_items ci2
              WHERE ci2.task_id = t.id
                AND ci2.is_done = 0
                AND (
                    (ci2.assignee_type = 'user'    AND ci2.assignee_value = ?)
                    OR (ci2.assignee_type = 'section' AND ci2.assignee_value IN (` + secPh + `))
                )
          )
        ORDER BY t.created_at DESC
    `
	args := []any{orgID, userID, strconv.FormatInt(userID, 10)}
	args = append(args, sectionsArgs(sections)...)
	args = append(args, strconv.FormatInt(userID, 10))
	args = append(args, sectionsArgs(sections)...)

	rows, err := db.Query(q, args...)
	if err != nil {
		core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
		return
	}
	archived, err := core.ScanRowsToMaps(rows)
	rows.Close()
	if err != nil {
		core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
		return
	}

	if err := AttachChecklistTitles(db, archived); err != nil {
		core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
		return
	}

	core.WriteJSON(w, http.StatusOK, map[string]any{
		"success": true,
		"data": map[string]any{
			"tasks":   archived,
			"count":   len(archived),
			"user_id": userID,
			"filter":  "checklist_archive",
		},
		"message": "کارهای تمام‌شده‌ی چک‌لیستی",
	})
}
