package main

import (
	"database/sql"
	"math"
	"net/http"
	"strconv"
	"strings"
	"time"
)

// ═══════════════════════════════════════════════════════════════════
//  «همکار» در فاکتور رسمی + سهمِ سودِ ماهانه (شمسی) + گزارشِ همکاران
// ───────────────────────────────────────────────────────────────────
//  همکار = شخصی که فاکتور به‌درخواستش صادر می‌شود و درصدی از «مبلغِ پیش از
//  مالیاتِ» فاکتور را به‌عنوان سهمِ سود می‌گیرد. درصد برای هر (همکار، ماهِ
//  شمسی) جداگانه تعیین می‌شود و زنده است: تغییرِ درصدِ ماه، سودِ همهٔ
//  فاکتورهای همان ماه را بازمحاسبه می‌کند (چیزی snapshot نمی‌شود).
//
//  مبلغِ سود = round( (subtotal - discount) × percent / 100 )
// ═══════════════════════════════════════════════════════════════════

// ───────────────────────── همکار: مدل ─────────────────────────

type partnerOut struct {
	ID       int64  `json:"id"`
	Name     string `json:"name"`
	Phone    string `json:"phone"`
	Note     string `json:"note"`
	IsActive bool   `json:"is_active"`
}

type partnerIn struct {
	Name     string `json:"name"`
	Phone    string `json:"phone"`
	Note     string `json:"note"`
	IsActive *bool  `json:"is_active"`
}

func (p *partnerIn) normalize() {
	p.Name = strings.TrimSpace(p.Name)
	p.Phone = strings.TrimSpace(p.Phone)
	p.Note = strings.TrimSpace(p.Note)
}

// GET /crm/api/inv/partners?q=&page=&per=&active=1
func (s *server) listPartners(w http.ResponseWriter, r *http.Request) {
	pg := parsePage(r)
	u := userOf(r.Context())

	where := "is_deleted = 0 AND organization_id = ?"
	args := []any{u.OrgID}
	if r.URL.Query().Get("active") == "1" {
		where += " AND is_active = 1"
	}
	if pg.Q != "" {
		where += " AND (name LIKE ? OR phone LIKE ?)"
		like := "%" + pg.Q + "%"
		args = append(args, like, like)
	}

	var total int
	if err := s.db.QueryRow("SELECT COUNT(*) FROM inv_partners WHERE "+where, args...).Scan(&total); err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}

	rows, err := s.db.Query(`
		SELECT id, name, COALESCE(phone,''), COALESCE(note,''), is_active
		FROM inv_partners WHERE `+where+`
		ORDER BY name
		LIMIT ? OFFSET ?`, append(args, pg.Limit, pg.Offset)...)
	if err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	defer rows.Close()

	items := []partnerOut{}
	for rows.Next() {
		var it partnerOut
		var active int
		if err := rows.Scan(&it.ID, &it.Name, &it.Phone, &it.Note, &active); err != nil {
			writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
			return
		}
		it.IsActive = active == 1
		items = append(items, it)
	}
	writeJSON(w, http.StatusOK, map[string]any{"items": items, "total": total, "page": pg.Page, "per": pg.Per})
}

// POST /crm/api/inv/partners
func (s *server) createPartner(w http.ResponseWriter, r *http.Request) {
	var in partnerIn
	if !decodeJSON(w, r, &in, 1<<20) {
		return
	}
	in.normalize()
	if in.Name == "" {
		writeErr(w, http.StatusBadRequest, "نامِ همکار الزامی است")
		return
	}
	u := userOf(r.Context())
	active := 1
	if in.IsActive != nil && !*in.IsActive {
		active = 0
	}
	res, err := s.db.Exec(`
		INSERT INTO inv_partners (organization_id, name, phone, note, is_active, created_by)
		VALUES (?, ?, ?, ?, ?, ?)`,
		u.OrgID, in.Name, nullIfEmpty(in.Phone), nullIfEmpty(in.Note), active, u.ID)
	if err != nil {
		writeErr(w, http.StatusInternalServerError, "درجِ همکار ناموفق بود")
		return
	}
	id, _ := res.LastInsertId()
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "id": id})
}

// PUT /crm/api/inv/partners/{id}
func (s *server) updatePartner(w http.ResponseWriter, r *http.Request) {
	id := idParam(r)
	var in partnerIn
	if !decodeJSON(w, r, &in, 1<<20) {
		return
	}
	in.normalize()
	if in.Name == "" {
		writeErr(w, http.StatusBadRequest, "نامِ همکار الزامی است")
		return
	}
	u := userOf(r.Context())
	active := 1
	if in.IsActive != nil && !*in.IsActive {
		active = 0
	}
	res, err := s.db.Exec(`
		UPDATE inv_partners SET name = ?, phone = ?, note = ?, is_active = ?
		WHERE id = ? AND organization_id = ? AND is_deleted = 0`,
		in.Name, nullIfEmpty(in.Phone), nullIfEmpty(in.Note), active, id, u.OrgID)
	if err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	if n, _ := res.RowsAffected(); n == 0 {
		writeErr(w, http.StatusNotFound, "همکار یافت نشد")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true})
}

// DELETE /crm/api/inv/partners/{id}  — حذفِ نرم.
func (s *server) deletePartner(w http.ResponseWriter, r *http.Request) {
	id := idParam(r)
	u := userOf(r.Context())
	res, err := s.db.Exec(
		"UPDATE inv_partners SET is_deleted = 1 WHERE id = ? AND organization_id = ? AND is_deleted = 0", id, u.OrgID)
	if err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	if n, _ := res.RowsAffected(); n == 0 {
		writeErr(w, http.StatusNotFound, "همکار یافت نشد")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true})
}

// ───────────────────────── درصدِ ماهانه ─────────────────────────

// ownsPartner: همکار متعلق به سازمانِ کاربر است؟
func (s *server) ownsPartner(partnerID, orgID int64) bool {
	var x int
	err := s.db.QueryRow(
		"SELECT 1 FROM inv_partners WHERE id = ? AND organization_id = ? AND is_deleted = 0", partnerID, orgID).Scan(&x)
	return err == nil && x == 1
}

// partnerPercent: درصدِ سهمِ یک همکار در یک ماهِ شمسی (۰ اگر ثبت نشده).
func (s *server) partnerPercent(partnerID int64, jy, jm int) float64 {
	var p sql.NullFloat64
	_ = s.db.QueryRow(
		"SELECT percent FROM inv_partner_month_share WHERE partner_id = ? AND jy = ? AND jm = ?",
		partnerID, jy, jm).Scan(&p)
	if p.Valid {
		return p.Float64
	}
	return 0
}

// GET /crm/api/inv/partners/{id}/shares?jy=1405   → همهٔ ۱۲ ماهِ آن سال
func (s *server) listPartnerShares(w http.ResponseWriter, r *http.Request) {
	id := idParam(r)
	u := userOf(r.Context())
	if !s.ownsPartner(id, u.OrgID) {
		writeErr(w, http.StatusNotFound, "همکار یافت نشد")
		return
	}
	jy, _ := strconv.Atoi(r.URL.Query().Get("jy"))
	if jy < 1300 || jy > 1500 {
		nowY, _ := jalaliYMOf(time.Time{})
		jy = nowY
	}

	got := map[int]float64{}
	rows, err := s.db.Query("SELECT jm, percent FROM inv_partner_month_share WHERE partner_id = ? AND jy = ?", id, jy)
	if err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	for rows.Next() {
		var jm int
		var p float64
		if err := rows.Scan(&jm, &p); err != nil {
			rows.Close()
			writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
			return
		}
		got[jm] = p
	}
	rows.Close()

	months := make([]map[string]any, 0, 12)
	for m := 1; m <= 12; m++ {
		months = append(months, map[string]any{"jm": m, "percent": got[m]})
	}
	writeJSON(w, http.StatusOK, map[string]any{"jy": jy, "months": months})
}

// PUT /crm/api/inv/partners/{id}/shares   body: {jy, jm, percent}
func (s *server) setPartnerShare(w http.ResponseWriter, r *http.Request) {
	id := idParam(r)
	u := userOf(r.Context())
	if !s.ownsPartner(id, u.OrgID) {
		writeErr(w, http.StatusNotFound, "همکار یافت نشد")
		return
	}
	var in struct {
		JY      int     `json:"jy"`
		JM      int     `json:"jm"`
		Percent float64 `json:"percent"`
	}
	if !decodeJSON(w, r, &in, 1<<20) {
		return
	}
	if in.JY < 1300 || in.JY > 1500 {
		writeErr(w, http.StatusBadRequest, "سالِ شمسی نامعتبر است")
		return
	}
	if in.JM < 1 || in.JM > 12 {
		writeErr(w, http.StatusBadRequest, "ماهِ شمسی باید بینِ ۱ تا ۱۲ باشد")
		return
	}
	if in.Percent < 0 || in.Percent > 100 {
		writeErr(w, http.StatusBadRequest, "درصد باید بینِ ۰ تا ۱۰۰ باشد")
		return
	}
	if _, err := s.db.Exec(`
		INSERT INTO inv_partner_month_share (partner_id, jy, jm, percent, updated_by)
		VALUES (?, ?, ?, ?, ?)
		ON DUPLICATE KEY UPDATE percent = VALUES(percent), updated_by = VALUES(updated_by)`,
		id, in.JY, in.JM, in.Percent, u.ID); err != nil {
		writeErr(w, http.StatusInternalServerError, "ذخیره‌ی درصد ناموفق بود")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true})
}

// GET /crm/api/inv/partner-share?partner_id=&jy=&jm=   → {percent}
// برای پیش‌نمایشِ «مبلغِ سود» در فرمِ فاکتور.
func (s *server) getPartnerShare(w http.ResponseWriter, r *http.Request) {
	u := userOf(r.Context())
	pid, _ := strconv.ParseInt(r.URL.Query().Get("partner_id"), 10, 64)
	jy, _ := strconv.Atoi(r.URL.Query().Get("jy"))
	jm, _ := strconv.Atoi(r.URL.Query().Get("jm"))
	if pid <= 0 || !s.ownsPartner(pid, u.OrgID) {
		writeErr(w, http.StatusNotFound, "همکار یافت نشد")
		return
	}
	if jy < 1300 || jy > 1500 || jm < 1 || jm > 12 {
		writeErr(w, http.StatusBadRequest, "ماهِ شمسی نامعتبر است")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"percent": s.partnerPercent(pid, jy, jm)})
}

// ───────────────────────── گزارشِ همکاران ─────────────────────────

// GET /crm/api/inv/partner-report?jy=&jm=&partner_id=&doc_type=&status=
func (s *server) partnerReport(w http.ResponseWriter, r *http.Request) {
	u := userOf(r.Context())
	q := r.URL.Query()

	where := "i.organization_id = ? AND i.partner_id IS NOT NULL"
	args := []any{u.OrgID}

	if v := strings.TrimSpace(q.Get("partner_id")); v != "" {
		if pid, _ := strconv.ParseInt(v, 10, 64); pid > 0 {
			where += " AND i.partner_id = ?"
			args = append(args, pid)
		}
	}
	if v := strings.TrimSpace(q.Get("doc_type")); v == "official" || v == "proforma" {
		where += " AND i.doc_type = ?"
		args = append(args, v)
	}
	if v := strings.TrimSpace(q.Get("status")); v == "draft" || v == "approved" || v == "cancelled" {
		where += " AND i.status = ?"
		args = append(args, v)
	} else {
		where += " AND i.status <> 'cancelled'"
	}

	filterJY, _ := strconv.Atoi(q.Get("jy"))
	filterJM, _ := strconv.Atoi(q.Get("jm"))

	rows, err := s.db.Query(`
		SELECT i.id, i.doc_type, COALESCE(i.number,''), i.status,
		       COALESCE(c.name,'—'),
		       COALESCE(DATE_FORMAT(i.issue_date,'%Y-%m-%d'), DATE_FORMAT(i.created_at,'%Y-%m-%d')),
		       i.partner_id, COALESCE(p.name,'—'),
		       i.subtotal_amount, i.discount_amount, i.tax_amount, i.total_amount,
		       i.partner_profit_recorded, i.settlement_status, i.moadian_status, COALESCE(i.moadian_code,'')
		FROM inv_invoices i
		LEFT JOIN crm_customers c ON c.id = i.customer_id
		LEFT JOIN inv_partners  p ON p.id = i.partner_id
		WHERE `+where+`
		ORDER BY i.issue_date DESC, i.id DESC`, args...)
	if err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	defer rows.Close()

	pctCache := map[[3]int]float64{}

	items := []map[string]any{}
	for rows.Next() {
		var (
			id                             int64
			docType, number, status        string
			customerName, issueDate        string
			partnerID                      int64
			partnerName                    string
			subtotal, discount, tax, total int64
			recorded                       int
			settlement, moadian, mcode     string
		)
		if err := rows.Scan(&id, &docType, &number, &status, &customerName, &issueDate,
			&partnerID, &partnerName, &subtotal, &discount, &tax, &total,
			&recorded, &settlement, &moadian, &mcode); err != nil {
			writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
			return
		}

		var jy, jm int
		if t, e := time.Parse("2006-01-02", issueDate); e == nil {
			jy, jm = jalaliYMOf(t)
		} else {
			jy, jm = jalaliYMOf(time.Time{})
		}
		if filterJY >= 1300 && jy != filterJY {
			continue
		}
		if filterJM >= 1 && filterJM <= 12 && jm != filterJM {
			continue
		}

		key := [3]int{int(partnerID), jy, jm}
		pct, ok := pctCache[key]
		if !ok {
			pct = s.partnerPercent(partnerID, jy, jm)
			pctCache[key] = pct
		}
		preTax := subtotal - discount
		if preTax < 0 {
			preTax = 0
		}
		profit := int64(math.Round(float64(preTax) * pct / 100))

		items = append(items, map[string]any{
			"id":                      id,
			"doc_type":                docType,
			"number":                  number,
			"status":                  status,
			"customer_name":           customerName,
			"issue_date":              issueDate,
			"partner_id":              partnerID,
			"partner_name":            partnerName,
			"jy":                      jy,
			"jm":                      jm,
			"percent":                 pct,
			"invoice_amount":          total,
			"pre_tax_amount":          preTax,
			"tax_amount":              tax,
			"profit_amount":           profit,
			"partner_profit_recorded": recorded == 1,
			"settlement_status":       settlement,
			"moadian_status":          moadian,
			"moadian_code":            mcode,
		})
	}
	writeJSON(w, http.StatusOK, map[string]any{"items": items})
}

// PATCH /crm/api/inv/invoices/{id}/partner-status
// body (همه اختیاری): {partner_profit_recorded, settlement_status, moadian_status, moadian_code}
func (s *server) setInvoicePartnerStatus(w http.ResponseWriter, r *http.Request) {
	id := idParam(r)
	u := userOf(r.Context())

	var in struct {
		ProfitRecorded *bool   `json:"partner_profit_recorded"`
		Settlement     *string `json:"settlement_status"`
		Moadian        *string `json:"moadian_status"`
		MoadianCode    *string `json:"moadian_code"`
	}
	if !decodeJSON(w, r, &in, 1<<20) {
		return
	}

	// وضعیتِ فعلی برای اعتبارسنجیِ «کدِ مودیان هنگامِ ثبت‌شده»
	var curMoadian, curCode string
	if err := s.db.QueryRow(
		"SELECT moadian_status, COALESCE(moadian_code,'') FROM inv_invoices WHERE id = ? AND organization_id = ?",
		id, u.OrgID).Scan(&curMoadian, &curCode); err == sql.ErrNoRows {
		writeErr(w, http.StatusNotFound, "فاکتور یافت نشد")
		return
	} else if err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}

	sets := []string{}
	args := []any{}

	if in.ProfitRecorded != nil {
		v := 0
		if *in.ProfitRecorded {
			v = 1
		}
		sets = append(sets, "partner_profit_recorded = ?")
		args = append(args, v)
	}
	if in.Settlement != nil {
		v := strings.TrimSpace(*in.Settlement)
		if v != "unsettled" && v != "settled" && v != "partial" {
			writeErr(w, http.StatusBadRequest, "وضعیتِ تسویه نامعتبر است")
			return
		}
		sets = append(sets, "settlement_status = ?")
		args = append(args, v)
	}

	newMoadian := curMoadian
	if in.Moadian != nil {
		v := strings.TrimSpace(*in.Moadian)
		if v != "unregistered" && v != "registered" {
			writeErr(w, http.StatusBadRequest, "وضعیتِ مودیان نامعتبر است")
			return
		}
		newMoadian = v
		sets = append(sets, "moadian_status = ?")
		args = append(args, v)
	}
	newCode := curCode
	if in.MoadianCode != nil {
		newCode = strings.TrimSpace(*in.MoadianCode)
		sets = append(sets, "moadian_code = ?")
		args = append(args, nullIfEmpty(newCode))
	}
	if newMoadian == "registered" && strings.TrimSpace(newCode) == "" {
		writeErr(w, http.StatusUnprocessableEntity, "برای «مودیان ثبت‌شده» کدِ ثبت در سامانه لازم است")
		return
	}
	if newMoadian == "unregistered" && in.MoadianCode == nil && curCode != "" {
		// «ثبت‌نشده» شدن → کدِ قبلی پاک شود
		sets = append(sets, "moadian_code = NULL")
	}

	if len(sets) == 0 {
		writeErr(w, http.StatusBadRequest, "چیزی برای تغییر ارسال نشده")
		return
	}
	args = append(args, id, u.OrgID)
	res, err := s.db.Exec(
		"UPDATE inv_invoices SET "+strings.Join(sets, ", ")+" WHERE id = ? AND organization_id = ?", args...)
	if err != nil {
		writeErr(w, http.StatusInternalServerError, "به‌روزرسانی ناموفق بود")
		return
	}
	if n, _ := res.RowsAffected(); n == 0 {
		writeErr(w, http.StatusNotFound, "فاکتور یافت نشد")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true})
}
