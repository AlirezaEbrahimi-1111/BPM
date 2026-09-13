package core

import "database/sql"

// UserSections — پورتِ us_getUserSections() در includes/user-sections.php:
// فهرستِ واحدهایِ کاربر از جدولِ جدید (چندواحدی)، با بازگشتِ امن به
// ستونِ قدیمیِ users.activity_section اگر جدولِ جدید برایِ این کاربر خالی بود.
func UserSections(db *sql.DB, userID int64) ([]string, error) {
	rows, err := db.Query(`
		SELECT section_key FROM user_activity_sections
		WHERE user_id = ? ORDER BY is_primary DESC, section_key ASC
	`, userID)
	if err != nil {
		return nil, err
	}
	var sections []string
	for rows.Next() {
		var s string
		if err := rows.Scan(&s); err != nil {
			rows.Close()
			return nil, err
		}
		sections = append(sections, s)
	}
	rows.Close()
	if len(sections) > 0 {
		return sections, nil
	}

	var legacy sql.NullString
	err = db.QueryRow("SELECT activity_section FROM users WHERE id = ?", userID).Scan(&legacy)
	if err != nil && err != sql.ErrNoRows {
		return nil, err
	}
	if legacy.Valid && legacy.String != "" {
		return []string{legacy.String}, nil
	}
	return []string{}, nil
}
