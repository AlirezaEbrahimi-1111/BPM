package main

import (
	"database/sql"
	"net/http"
)

// تنظیمات فاکتور + سربرگ فروشنده — هر دو تک‌ردیفی (id=1).

type sellerInfo struct {
	CompanyName  string `json:"company_name"`
	NationalID   string `json:"national_id"`
	EconomicCode string `json:"economic_code"`
	RegNumber    string `json:"reg_number"`
	BranchCode   string `json:"branch_code"`
	Province     string `json:"province"`
	Shahrestan   string `json:"shahrestan"`
	City         string `json:"city"`
	Address      string `json:"address"`
	PostalCode   string `json:"postal_code"`
	Phone        string `json:"phone"`
	IBAN         string `json:"iban"`
	CardNumber   string `json:"card_number"`
	BankName     string `json:"bank_name"`
}

type invSettings struct {
	VatRate      float64    `json:"vat_rate"`
	Currency     string     `json:"currency"`
	NumberPrefix string     `json:"number_prefix"`
	FooterNote   string     `json:"invoice_footer_note"`
	Seller       sellerInfo `json:"seller"`
}

func (s *server) getSettings() (invSettings, error) {
	var out invSettings
	var np, fn sql.NullString
	err := s.db.QueryRow(`
		SELECT vat_rate, currency, number_prefix, COALESCE(invoice_footer_note,'')
		FROM inv_settings WHERE id = 1`).Scan(&out.VatRate, &out.Currency, &np, &fn)
	if err != nil {
		return out, err
	}
	out.NumberPrefix = np.String
	out.FooterNote = fn.String

	var se sellerInfo
	var cn, ni, ec, rn, bc, pv, sh, ct, ad, pc, ph, ib, cd, bk sql.NullString
	err = s.db.QueryRow(`
		SELECT COALESCE(company_name,''), COALESCE(national_id,''), COALESCE(economic_code,''),
		       COALESCE(reg_number,''), COALESCE(branch_code,''),
		       COALESCE(province,''), COALESCE(shahrestan,''), COALESCE(city,''),
		       COALESCE(address,''), COALESCE(postal_code,''), COALESCE(phone,''),
		       COALESCE(iban,''), COALESCE(card_number,''), COALESCE(bank_name,'')
		FROM inv_seller WHERE id = 1`).
		Scan(&cn, &ni, &ec, &rn, &bc, &pv, &sh, &ct, &ad, &pc, &ph, &ib, &cd, &bk)
	if err != nil {
		return out, err
	}
	se.CompanyName, se.NationalID, se.EconomicCode = cn.String, ni.String, ec.String
	se.RegNumber, se.BranchCode = rn.String, bc.String
	se.Province, se.Shahrestan, se.City = pv.String, sh.String, ct.String
	se.Address, se.PostalCode, se.Phone = ad.String, pc.String, ph.String
	se.IBAN, se.CardNumber, se.BankName = ib.String, cd.String, bk.String
	out.Seller = se
	return out, nil
}

// GET /crm/api/inv/settings
func (s *server) handleGetSettings(w http.ResponseWriter, r *http.Request) {
	st, err := s.getSettings()
	if err != nil {
		writeErr(w, http.StatusInternalServerError, "خواندن تنظیمات ناموفق بود")
		return
	}
	writeJSON(w, http.StatusOK, st)
}

// PUT /crm/api/inv/settings   body: invSettings (کاملا بازنویسی می‌شود)
func (s *server) handlePutSettings(w http.ResponseWriter, r *http.Request) {
	var in invSettings
	if !decodeJSON(w, r, &in, 1<<20) {
		return
	}
	if in.VatRate < 0 || in.VatRate > 100 {
		writeErr(w, http.StatusBadRequest, "نرخ مالیات باید بین ۰ تا ۱۰۰ باشد")
		return
	}
	if in.Currency == "" {
		in.Currency = "IRR"
	}

	tx, err := s.db.Begin()
	if err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	defer tx.Rollback()

	if _, err := tx.Exec(`
		UPDATE inv_settings
		SET vat_rate = ?, currency = ?, number_prefix = ?, invoice_footer_note = ?
		WHERE id = 1`,
		in.VatRate, in.Currency, in.NumberPrefix, nullIfEmpty(in.FooterNote)); err != nil {
		writeErr(w, http.StatusInternalServerError, "ذخیره‌ی تنظیمات ناموفق بود")
		return
	}

	se := in.Seller
	if _, err := tx.Exec(`
		UPDATE inv_seller
		SET company_name = ?, national_id = ?, economic_code = ?, reg_number = ?, branch_code = ?,
		    province = ?, shahrestan = ?, city = ?, address = ?, postal_code = ?, phone = ?,
		    iban = ?, card_number = ?, bank_name = ?
		WHERE id = 1`,
		nullIfEmpty(se.CompanyName), nullIfEmpty(se.NationalID), nullIfEmpty(se.EconomicCode),
		nullIfEmpty(se.RegNumber), nullIfEmpty(se.BranchCode),
		nullIfEmpty(se.Province), nullIfEmpty(se.Shahrestan), nullIfEmpty(se.City),
		nullIfEmpty(se.Address), nullIfEmpty(se.PostalCode), nullIfEmpty(se.Phone),
		nullIfEmpty(se.IBAN), nullIfEmpty(se.CardNumber), nullIfEmpty(se.BankName)); err != nil {
		writeErr(w, http.StatusInternalServerError, "ذخیره‌ی سربرگ فروشنده ناموفق بود")
		return
	}

	if err := tx.Commit(); err != nil {
		writeErr(w, http.StatusInternalServerError, "خطای دیتابیس")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true})
}
