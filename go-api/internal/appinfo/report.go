// Package appinfo — نسخهٔ اپِ موبایلِ نصب‌شده روی گوشیِ هر کاربر (۱۴۰۵/۰۸/۱۹).
package appinfo

import (
	"database/sql"
	"encoding/json"
	"net/http"
	"strings"

	"bmp/go-api/internal/core"
)

// ReportVersion — اپ هنگامِ باز شدن نسخه‌اش را گزارش می‌دهد.
//
//	POST /go/api/app/report-version  {"platform":"android","version":"1.0.3","build":127,"abi":"arm64-v8a"}
//	→ {"success":true}
//
// یک ردیف برای هر کاربر (آخرین نسخه) در app_installs؛ صفحهٔ app-releases.php
// تعدادِ هر نسخه را از آن می‌شمارد.
func ReportVersion(db *sql.DB) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		u := core.UserOf(r.Context())

		var in struct {
			Platform string `json:"platform"`
			Version  string `json:"version"`
			Build    int    `json:"build"`
			Abi      string `json:"abi"`
		}
		if err := json.NewDecoder(http.MaxBytesReader(w, r.Body, 4096)).Decode(&in); err != nil || in.Build <= 0 {
			core.WriteErr(w, http.StatusBadRequest, "اطلاعات نسخه ناقص است")
			return
		}
		platform := strings.ToLower(strings.TrimSpace(in.Platform))
		if platform != "ios" {
			platform = "android"
		}
		version := clip(strings.TrimSpace(in.Version), 20)
		abi := clip(strings.TrimSpace(in.Abi), 20)

		var org any
		if u.OrgID > 0 {
			org = u.OrgID
		}
		_, err := db.Exec(`
			INSERT INTO app_installs (user_id, organization_id, platform, version, build, abi, first_seen, last_seen)
			VALUES (?, ?, ?, ?, ?, NULLIF(?, ''), NOW(), NOW())
			ON DUPLICATE KEY UPDATE organization_id = VALUES(organization_id), platform = VALUES(platform),
			    version = VALUES(version), build = VALUES(build), abi = VALUES(abi), last_seen = NOW()
		`, u.ID, org, platform, version, in.Build, abi)
		if err != nil {
			core.WriteErr(w, http.StatusInternalServerError, "خطای سرور")
			return
		}
		core.WriteJSON(w, http.StatusOK, map[string]any{"success": true})
	}
}

func clip(s string, n int) string {
	if r := []rune(s); len(r) > n {
		return string(r[:n])
	}
	return s
}
