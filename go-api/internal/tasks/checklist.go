package tasks

import (
	"database/sql"
	"strings"
)

// AttachChecklistTitles — پورتِ دقیقِ attachChecklistTitles() در
// includes/checklist-search-helper.php: به هر تسک یک فیلدِ
// checklist_titles (رشته‌ی جست‌وجوپذیر از عنوانِ آیتم‌های چک‌لیست) اضافه می‌کند.
func AttachChecklistTitles(db *sql.DB, tasksList []map[string]any) error {
	if len(tasksList) == 0 {
		return nil
	}

	ids := make([]int64, 0, len(tasksList))
	seen := map[int64]bool{}
	for _, t := range tasksList {
		id := int64Of(t, "id")
		if id != 0 && !seen[id] {
			seen[id] = true
			ids = append(ids, id)
		}
	}
	if len(ids) == 0 {
		return nil
	}

	placeholders := strings.TrimSuffix(strings.Repeat("?,", len(ids)), ",")
	args := make([]any, len(ids))
	for i, id := range ids {
		args[i] = id
	}

	rows, err := db.Query(`
		SELECT task_id, GROUP_CONCAT(title SEPARATOR ' ') AS titles
		FROM task_checklist_items
		WHERE task_id IN (`+placeholders+`)
		GROUP BY task_id
	`, args...)
	if err != nil {
		return err
	}
	defer rows.Close()

	titleMap := map[int64]string{}
	for rows.Next() {
		var taskID int64
		var titles sql.NullString
		if err := rows.Scan(&taskID, &titles); err != nil {
			return err
		}
		titleMap[taskID] = titles.String
	}
	if err := rows.Err(); err != nil {
		return err
	}

	for _, t := range tasksList {
		t["checklist_titles"] = titleMap[int64Of(t, "id")]
	}
	return nil
}
