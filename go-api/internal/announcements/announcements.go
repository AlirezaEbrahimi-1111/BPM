// Package announcements — پورتِ api/announcements/list.php (فقط بخشِ
// خواندنی؛ create.php/update.php/users.php — که فقط مدیران برایِ
// ساختن/ویرایشِ اطلاعیه استفاده می‌کنن، نه چیزی که هر بارگذاریِ صفحه صدا
// بزنه — عمداً فعلاً پورت نشدن).
package announcements

import (
	"database/sql"
	"net/http"
	"strconv"
	"strings"

	"bmp/go-api/internal/core"
)

// userSections — پورتِ us_getUserSections(): فهرستِ واحدهایِ کاربر از
// جدولِ جدید، با بازگشتِ امن به ستونِ قدیمیِ users.activity_section اگر
// جدولِ جدید برایِ این کاربر خالی بود.
func userSections(db *sql.DB, userID int64) ([]string, error) {
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

func parseIntDefault(s string, def int) int {
	if s == "" {
		return def
	}
	n, err := strconv.Atoi(s)
	if err != nil {
		return def
	}
	return n
}

// List — پورتِ دقیقِ api/announcements/list.php
//
//	GET /go/api/announcements/list?limit=&offset=&all=1&unread_only=1
//	→ {"success":true,"announcements":[...],"unread_count":N,"total":N}
func List(db *sql.DB) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		u := core.UserOf(r.Context())

		// معادلِ getUserInfo(): فقط کاربرِ فعال.
		var role, section sql.NullString
		var orgID sql.NullInt64
		err := db.QueryRow("SELECT role, organization_id, activity_section FROM users WHERE id = ? AND is_active = 1", u.ID).
			Scan(&role, &orgID, &section)
		if err == sql.ErrNoRows {
			core.WriteErr(w, http.StatusUnauthorized, "عدم احراز هویت")
			return
		}
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}

		limit := parseIntDefault(r.URL.Query().Get("limit"), 10)
		if limit > 50 {
			limit = 50
		}
		offset := parseIntDefault(r.URL.Query().Get("offset"), 0)

		sections, err := userSections(db, u.ID)
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}
		sectionsCsv := strings.Join(sections, ",")

		isSuper := u.ID == 1
		canManage := role.String == "management" || role.String == "supervisor"
		showAll := r.URL.Query().Get("all") == "1" && (isSuper || canManage)
		unreadOnly := r.URL.Query().Get("unread_only") == "1" && !showAll

		var rows *sql.Rows
		if showAll {
			scopeWhere := "(a.organization_id = ? OR a.organization_id IS NULL)"
			args := []any{orgID.Int64}
			if isSuper {
				scopeWhere = "1 = 1"
				args = []any{}
			}
			q := `SELECT a.*,
					CONCAT(u.first_name, ' ', u.last_name) AS author_name,
					u.role AS author_role,
					(SELECT COUNT(*) FROM announcement_reads ar2 WHERE ar2.announcement_id = a.id) AS read_count,
					(SELECT COUNT(*) FROM users uu
						WHERE uu.is_active = 1
						  AND (a.organization_id IS NULL OR uu.organization_id = a.organization_id)
						  AND (a.target_section IS NULL OR uu.activity_section = a.target_section)
					) AS total_users
				FROM announcements a
				LEFT JOIN users u ON a.author_id = u.id
				WHERE ` + scopeWhere + `
				ORDER BY a.is_pinned DESC, a.created_at DESC
				LIMIT ? OFFSET ?`
			args = append(args, limit, offset)
			rows, err = db.Query(q, args...)
		} else {
			where := `a.is_active = 1
				  AND a.publish_at <= NOW()
				  AND (a.expire_at IS NULL OR a.expire_at > NOW())
				  AND (
					  a.target_user_id = ?
					  OR (
						  a.target_user_id IS NULL
						  AND (
							  a.organization_id IS NULL
							  OR (
								  a.organization_id = ?
								  AND (a.target_section IS NULL OR FIND_IN_SET(a.target_section, ?))
							  )
						  )
					  )
				  )`
			args := []any{u.ID, orgID.Int64, sectionsCsv}
			if unreadOnly {
				where += " AND a.id NOT IN (SELECT announcement_id FROM announcement_reads WHERE user_id = ?)"
				args = append(args, u.ID)
			}
			q := `SELECT a.*,
					CONCAT(u.first_name, ' ', u.last_name) AS author_name,
					u.role AS author_role,
					(CASE WHEN EXISTS (
						SELECT 1 FROM announcement_reads ar
						WHERE ar.announcement_id = a.id AND ar.user_id = ?
					) THEN 1 ELSE 0 END) AS is_read
				FROM announcements a
				LEFT JOIN users u ON a.author_id = u.id
				WHERE ` + where + `
				ORDER BY a.is_pinned DESC, a.created_at DESC
				LIMIT ? OFFSET ?`
			args = append([]any{u.ID}, args...)
			args = append(args, limit, offset)
			rows, err = db.Query(q, args...)
		}
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}
		announcements, err := core.ScanRowsToMaps(rows)
		rows.Close()
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}

		// ───── تعداد نخوانده (همان دامنهٔ دیدِ کاربر عادی، مستقل از showAll) ─────
		var unreadCount int
		err = db.QueryRow(`
			SELECT COUNT(*) FROM announcements a
			WHERE a.is_active = 1
			  AND a.publish_at <= NOW()
			  AND (a.expire_at IS NULL OR a.expire_at > NOW())
			  AND (
				  a.target_user_id = ?
				  OR (
					  a.target_user_id IS NULL
					  AND (
						  a.organization_id IS NULL
						  OR (
							  a.organization_id = ?
							  AND (a.target_section IS NULL OR FIND_IN_SET(a.target_section, ?))
						  )
					  )
				  )
			  )
			  AND a.id NOT IN (SELECT announcement_id FROM announcement_reads WHERE user_id = ?)
		`, u.ID, orgID.Int64, sectionsCsv, u.ID).Scan(&unreadCount)
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}

		for _, ann := range announcements {
			ann["is_pinned"] = truthy(ann["is_pinned"])
			ann["is_active"] = truthy(ann["is_active"])
			if v, ok := ann["is_read"]; ok {
				ann["is_read"] = truthy(v)
			} else {
				ann["is_read"] = nil
			}

			if toInt64(ann["target_user_id"]) != 0 {
				ann["scope_label"] = "شخصی"
			} else if ann["organization_id"] == nil {
				ann["scope_label"] = "سراسری"
			} else if s, _ := ann["target_section"].(string); s == "" {
				ann["scope_label"] = "کل سازمان"
			} else {
				ts, _ := ann["target_section"].(string)
				ann["scope_label"] = "واحد: " + ts
			}
		}

		core.WriteJSON(w, http.StatusOK, map[string]any{
			"success":       true,
			"announcements": announcements,
			"unread_count":  unreadCount,
			"total":         len(announcements),
		})
	}
}

func truthy(v any) bool {
	switch n := v.(type) {
	case int64:
		return n != 0
	case float64:
		return n != 0
	case bool:
		return n
	}
	return false
}

func toInt64(v any) int64 {
	switch n := v.(type) {
	case int64:
		return n
	case float64:
		return int64(n)
	}
	return 0
}
