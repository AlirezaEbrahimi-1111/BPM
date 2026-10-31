package main

// ═══════════════════════════════════════════════════════════════════
//  اتصال به نرم‌افزار حسابداری «شایگان» (Cyber Account) برای تب
//  «انتخاب از حسابداری» در صفحهٔ مشتریان CRM (pages/ucrm-customers.php).
//
//  مسیر شبکه: این سرویس روی سرور BPM است و شایگان روی یک VM داخلِ شرکت.
//  از طریق تونل WireGuard (server↔server) شایگان با آدرس داخلی
//  http://10.8.0.2:2030 در دسترس است (cfg.ShygunURL). مرورگر مستقیم به
//  شایگان دسترسی ندارد (HTTPS→HTTP + IP خصوصی) — همیشه از همین سرویس.
//
//  مشتری‌ها = «تفصیلی شناورِ» زیرِ معینِ «حساب‌های دریافتنی تجاری»
//  (کد ۱۱۲۰۳، cfg.ShygunCustomerAccount). این معین مشتری را از تأمین‌کننده
//  (پرداختنی تجاری ۳۱۰۰۱) و بقیه جدا می‌کند.
//
//  دو متدِ خواندنیِ شایگان:
//    • Job/GetJobRemainByAccount → نام + شمارهٔ تفصیلی (JobNo) + ماندهٔ حساب
//      (فیلتر AccountNumber روی بازهٔ معین). این فهرستِ تب است.
//    • Job/Get (فیلتر JobCostingCode = JobNo) → تلفن + GuId یک تفصیلی،
//      برای پر کردن فرم و ذخیرهٔ پیوند هنگام انتخاب.
//  ⚠️ وب‌سرویسِ خواندن کد ملی / آدرس / کد اقتصادی را برنمی‌گرداند؛ کاربر
//     آن‌ها را خودش کامل می‌کند.
// ═══════════════════════════════════════════════════════════════════

import (
	"bytes"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"sort"
	"strings"
	"sync"
	"time"
)

// ──────────────── بدنه/پاسخ شایگان ────────────────

type shygunConnConfig struct {
	ConnectionName string `json:"ConnectionName"`
}

type shygunFilter struct {
	From string `json:"From"`
	To   string `json:"To"`
}

// بدنهٔ مشترک متدهای Get (Domain بسته به متد فرق می‌کند).
type shygunGetReq struct {
	Config shygunConnConfig `json:"Config"`
	Domain map[string]any   `json:"Domain"`
}

// ردیفِ GetJobRemainByAccount (نام کلیدها PascalCase است؛ json در Go
// بی‌توجه به بزرگی/کوچکی حروف تطبیق می‌دهد).
type shygunRemainRow struct {
	JobName string  `json:"JobName"`
	JobNo   string  `json:"JobNo"`
	Remain  float64 `json:"Remain"`
}

// ردیفِ Job/Get (پروجکشن سبک — همین چند فیلد برمی‌گردد).
type shygunJobRow struct {
	JobName       string  `json:"JobName"`
	JobNo         string  `json:"JobNo"`
	Mobile        string  `json:"Mobile"`
	Telephone1    string  `json:"Telephone1"`
	Tel1          string  `json:"Tel1"`
	Tel2          string  `json:"Tel2"`
	GuId          string  `json:"GuId"`
	RemainAccount float64 `json:"RemainAccount"` // ماندهٔ کلّ تفصیلی (برای تفصیلی‌های بدون گردشِ فروش)
}

// ──────────────── مشتریِ ساده‌شده برای فرانت ────────────────

type shygunCustomer struct {
	JobNo  string  `json:"job_no"`
	Name   string  `json:"name"`
	Remain float64 `json:"remain"`
}

// ──────────────── کلاینت HTTP ────────────────

// shygunPost یک درخواست POST/JSON به شایگان می‌زند و بدنهٔ خام را برمی‌گرداند.
func (s *server) shygunPost(path string, body any, timeout time.Duration) ([]byte, error) {
	buf, err := json.Marshal(body)
	if err != nil {
		return nil, err
	}
	url := strings.TrimRight(s.cfg.ShygunURL, "/") + path
	req, err := http.NewRequest(http.MethodPost, url, bytes.NewReader(buf))
	if err != nil {
		return nil, err
	}
	req.Header.Set("Content-Type", "application/json")
	resp, err := (&http.Client{Timeout: timeout}).Do(req)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()
	data, _ := io.ReadAll(io.LimitReader(resp.Body, 16<<20))
	if resp.StatusCode != http.StatusOK {
		return nil, fmt.Errorf("کد وضعیت %d از حسابداری", resp.StatusCode)
	}
	return data, nil
}

// shygunResult پاسخ را باز می‌کند؛ اگر شایگان ErrorMessage داده باشد، خطا می‌دهد.
func shygunResult[T any](data []byte) ([]T, error) {
	var env struct {
		Result       []T    `json:"Result"`
		ErrorMessage string `json:"ErrorMessage"`
	}
	if err := json.Unmarshal(data, &env); err != nil {
		return nil, fmt.Errorf("پاسخ حسابداری قابل خواندن نبود")
	}
	if strings.TrimSpace(env.ErrorMessage) != "" {
		return nil, fmt.Errorf("%s", env.ErrorMessage)
	}
	return env.Result, nil
}

// ──────────────── کش فهرست مشتریان (TTL کوتاه) ────────────────

var shygunCustCache struct {
	mu        sync.Mutex
	rows      []shygunCustomer
	fetchedAt time.Time
}

const shygunCacheTTL = 5 * time.Minute

// shygunFetchCustomers همهٔ تفصیلی‌های زیرِ معینِ مشتری را صفحه‌به‌صفحه می‌خواند
// (سقف ۱۰۰ ردیف در هر صفحه = قاعدهٔ شایگان). JobNoِ تکراری (چند زیرحساب) را
// یکی می‌کند و مانده را جمع می‌زند.
func (s *server) shygunFetchCustomers() ([]shygunCustomer, error) {
	acct := strings.TrimSpace(s.cfg.ShygunCustomerAccount)
	flt := shygunFilter{From: acct, To: acct + "999"}
	const per = 100
	agg := map[string]int{} // JobNo → ایندکس در out
	out := []shygunCustomer{}
	for page := 0; page < 100; page++ { // سقف امنیتی ۱۰هزار ردیف
		req := shygunGetReq{
			Config: shygunConnConfig{ConnectionName: s.cfg.ShygunConn},
			Domain: map[string]any{"AccountNumber": flt},
		}
		data, err := s.shygunPost(
			fmt.Sprintf("/api/Job/GetJobRemainByAccount?RowStart=%d&RowCount=%d", page*per, per), req, 25*time.Second)
		if err != nil {
			return nil, err
		}
		rows, err := shygunResult[shygunRemainRow](data)
		if err != nil {
			return nil, err
		}
		for _, r := range rows {
			jn := strings.TrimSpace(r.JobNo)
			if jn == "" {
				continue
			}
			if i, ok := agg[jn]; ok {
				out[i].Remain += r.Remain
				continue
			}
			agg[jn] = len(out)
			out = append(out, shygunCustomer{JobNo: jn, Name: faLetters(strings.TrimSpace(r.JobName)), Remain: r.Remain})
		}
		if len(rows) < per {
			break
		}
	}
	return out, nil
}

// shygunCustomers کش را می‌دهد؛ اگر منقضی شد تازه می‌کند. اگر تازه‌سازی شکست
// خورد ولی کش قبلی داریم، همان را می‌دهیم تا قطعِ لحظه‌ایِ تونل صفحه را خالی نکند.
func (s *server) shygunCustomers(force bool) ([]shygunCustomer, time.Time, error) {
	shygunCustCache.mu.Lock()
	defer shygunCustCache.mu.Unlock()
	if !force && shygunCustCache.rows != nil && time.Since(shygunCustCache.fetchedAt) < shygunCacheTTL {
		return shygunCustCache.rows, shygunCustCache.fetchedAt, nil
	}
	rows, err := s.shygunFetchCustomers()
	if err != nil {
		if shygunCustCache.rows != nil {
			return shygunCustCache.rows, shygunCustCache.fetchedAt, nil
		}
		return nil, time.Time{}, err
	}
	shygunCustCache.rows = rows
	shygunCustCache.fetchedAt = time.Now()
	return rows, shygunCustCache.fetchedAt, nil
}

// ──────────────── کش «همهٔ تفصیلی‌ها» (گزینهٔ ج) ────────────────
// مشتریِ بدونِ گردش زیر معینِ دریافتنی در GetJobRemainByAccount نمی‌آید؛ برای
// جستجوی چنین مواردی (مثل یک مشتری تازه بدون فاکتور) کلِ تفصیلی‌ها را از
// Job/Get می‌خوانیم (نام/شماره/ماندهٔ کل). این شامل تأمین‌کننده/غیرمشتری هم
// هست، برای همین در فرانت با برچسب «بدون گردش فروش» از مشتریِ قطعی جدا می‌شود.

var shygunJobsCache struct {
	mu        sync.Mutex
	rows      []shygunCustomer
	fetchedAt time.Time
}

func (s *server) shygunFetchAllJobs() ([]shygunCustomer, error) {
	const per = 100
	out := []shygunCustomer{}
	seen := map[string]bool{}
	for page := 0; page < 100; page++ { // سقف امنیتی ۱۰هزار ردیف
		req := shygunGetReq{
			Config: shygunConnConfig{ConnectionName: s.cfg.ShygunConn},
			Domain: map[string]any{},
		}
		data, err := s.shygunPost(fmt.Sprintf("/api/Job/Get?RowStart=%d&RowCount=%d", page*per, per), req, 25*time.Second)
		if err != nil {
			return nil, err
		}
		rows, err := shygunResult[shygunJobRow](data)
		if err != nil {
			return nil, err
		}
		for _, j := range rows {
			jn := strings.TrimSpace(j.JobNo)
			name := faLetters(strings.TrimSpace(j.JobName))
			if jn == "" || name == "" || seen[jn] {
				continue
			}
			seen[jn] = true
			out = append(out, shygunCustomer{JobNo: jn, Name: name, Remain: j.RemainAccount})
		}
		if len(rows) < per {
			break
		}
	}
	return out, nil
}

func (s *server) shygunAllJobs(force bool) ([]shygunCustomer, time.Time, error) {
	shygunJobsCache.mu.Lock()
	defer shygunJobsCache.mu.Unlock()
	if !force && shygunJobsCache.rows != nil && time.Since(shygunJobsCache.fetchedAt) < shygunCacheTTL {
		return shygunJobsCache.rows, shygunJobsCache.fetchedAt, nil
	}
	rows, err := s.shygunFetchAllJobs()
	if err != nil {
		if shygunJobsCache.rows != nil {
			return shygunJobsCache.rows, shygunJobsCache.fetchedAt, nil
		}
		return nil, time.Time{}, err
	}
	shygunJobsCache.rows = rows
	shygunJobsCache.fetchedAt = time.Now()
	return rows, shygunJobsCache.fetchedAt, nil
}

// ──────────────── جستجوی کلمه‌به‌کلمه + رتبه‌بندی ────────────────
// هر کلمهٔ جستجو که در نام (یا شماره) پیدا شود امتیاز می‌گیرد؛ هرچه کلماتِ
// بیشتری بخورد بالاتر می‌آید، و تطبیقِ عبارتِ کامل بالاترین اولویت را دارد.

func shygunMatch(rows []shygunCustomer, q string) []shygunCustomer {
	q = strings.TrimSpace(q)
	if q == "" {
		return rows
	}
	words := strings.Fields(q)
	qDigits := digitsOnly(q)
	type scored struct {
		c     shygunCustomer
		score int
	}
	out := make([]scored, 0, 64)
	for _, c := range rows {
		matched := 0
		for _, wd := range words {
			d := digitsOnly(wd)
			if strings.Contains(c.Name, wd) || (d != "" && strings.Contains(c.JobNo, d)) {
				matched++
			}
		}
		if matched == 0 {
			continue
		}
		score := matched * 100
		if strings.Contains(c.Name, q) {
			score += 1000 // عبارتِ کامل
		}
		if qDigits != "" && strings.Contains(c.JobNo, qDigits) {
			score += 50
		}
		if strings.HasPrefix(c.Name, words[0]) {
			score += 20 // نامی که با اولین کلمه شروع شود
		}
		out = append(out, scored{c, score})
	}
	sort.SliceStable(out, func(i, j int) bool {
		if out[i].score != out[j].score {
			return out[i].score > out[j].score
		}
		li, lj := len([]rune(out[i].c.Name)), len([]rune(out[j].c.Name))
		if li != lj {
			return li < lj // نامِ کوتاه‌تر (مچ‌تر) اول
		}
		return out[i].c.Name < out[j].c.Name
	})
	res := make([]shygunCustomer, len(out))
	for i := range out {
		res[i] = out[i].c
	}
	return res
}

// jalaliDateTime: زمانِ سرور → «۱۴۰۵/۰۸/۰۹ ۱۹:۱۰» (لاتین؛ فرانت فارسی‌اش می‌کند).
func jalaliDateTime(t time.Time) string {
	if t.IsZero() {
		return ""
	}
	jy, jm, jd := gregorianToJalali(t.Year(), int(t.Month()), t.Day())
	return fmt.Sprintf("%04d/%02d/%02d %02d:%02d", jy, jm, jd, t.Hour(), t.Minute())
}

// shygunLinkedJobNos: شمارهٔ تفصیلی‌هایی که قبلا به مشتری BPM وصل شده‌اند
// (تا در فهرست «قبلا اضافه شده» علامت بخورند و دوبار وارد نشوند).
func (s *server) shygunLinkedJobNos() map[string]bool {
	set := map[string]bool{}
	rows, err := s.db.Query(`SELECT shygun_job_no FROM ucrm_customers
		WHERE organization_id = ? AND is_deleted = 0 AND shygun_job_no IS NOT NULL AND shygun_job_no <> ''`, ucrmOrg)
	if err != nil {
		return set
	}
	defer rows.Close()
	for rows.Next() {
		var jn string
		if rows.Scan(&jn) == nil {
			set[strings.TrimSpace(jn)] = true
		}
	}
	return set
}

// ──────────────── GET /ucrm/shygun/customers?q=&page=&per=&refresh=1 ────────────────

func (s *server) ucrmShygunCustomers(w http.ResponseWriter, r *http.Request) {
	pp := parsePage(r)
	force := r.URL.Query().Get("refresh") == "1"
	all := r.URL.Query().Get("scope") == "all" // گزینهٔ ج: جستجو در همهٔ تفصیلی‌ها

	var dataset []shygunCustomer
	var fetchedAt time.Time
	var err error
	custSet := map[string]bool{} // در حالتِ «همه»: کدام‌ها مشتریِ قطعی‌اند (گردش زیر ۱۱۲۰۳)
	if all {
		dataset, fetchedAt, err = s.shygunAllJobs(force)
		if err == nil {
			if cs, _, e := s.shygunCustomers(false); e == nil {
				for _, c := range cs {
					custSet[c.JobNo] = true
				}
			}
		}
	} else {
		dataset, fetchedAt, err = s.shygunCustomers(force)
	}
	if err != nil {
		writeErr(w, http.StatusBadGateway, "اتصال به نرم‌افزار حسابداری برقرار نشد: "+err.Error())
		return
	}

	matched := shygunMatch(dataset, pp.Q) // جستجوی کلمه‌به‌کلمه + رتبه‌بندی
	total := len(matched)
	start := pp.Offset
	if start > total {
		start = total
	}
	end := start + pp.Per
	if end > total {
		end = total
	}

	linked := s.shygunLinkedJobNos()
	items := make([]map[string]any, 0, end-start)
	for _, c := range matched[start:end] {
		m := map[string]any{"job_no": c.JobNo, "name": c.Name, "remain": c.Remain, "linked": linked[c.JobNo]}
		if all {
			m["is_customer"] = custSet[c.JobNo]
		}
		items = append(items, m)
	}
	writeJSON(w, http.StatusOK, map[string]any{
		"items": items, "total": total, "page": pp.Page, "per": pp.Per, "scope_all": all,
		"fetched_at": jalaliDateTime(fetchedAt),
	})
}

// ──────────────── GET /ucrm/shygun/customers/{jobno} ────────────────
// جزئیاتِ یک تفصیلی برای پر کردن فرم: نام، تلفن، GuId.

func (s *server) ucrmShygunCustomerDetail(w http.ResponseWriter, r *http.Request) {
	jobNo := digitsOnly(r.PathValue("jobno"))
	if jobNo == "" {
		writeErr(w, http.StatusBadRequest, "شمارهٔ تفصیلی نامعتبر است")
		return
	}
	req := shygunGetReq{
		Config: shygunConnConfig{ConnectionName: s.cfg.ShygunConn},
		Domain: map[string]any{"JobCostingCode": shygunFilter{From: jobNo, To: jobNo}},
	}
	data, err := s.shygunPost("/api/Job/Get?RowStart=0&RowCount=2", req, 15*time.Second)
	if err != nil {
		writeErr(w, http.StatusBadGateway, "اتصال به نرم‌افزار حسابداری برقرار نشد: "+err.Error())
		return
	}
	rows, err := shygunResult[shygunJobRow](data)
	if err != nil {
		writeErr(w, http.StatusBadGateway, "پاسخ حسابداری نامعتبر بود")
		return
	}
	if len(rows) == 0 {
		writeErr(w, http.StatusNotFound, "این مشتری در حسابداری پیدا نشد")
		return
	}
	j := rows[0]
	phone := firstNonEmpty(j.Mobile, j.Telephone1, j.Tel1, j.Tel2)
	writeJSON(w, http.StatusOK, map[string]any{
		"job_no": strings.TrimSpace(j.JobNo),
		"name":   faLetters(strings.TrimSpace(j.JobName)),
		"guid":   strings.TrimSpace(j.GuId),
		"phone":  digitsOnly(phone),
	})
}

func firstNonEmpty(vals ...string) string {
	for _, v := range vals {
		if strings.TrimSpace(v) != "" {
			return v
		}
	}
	return ""
}
