# go-api — بازنویسیِ تدریجیِ لایهٔ `api/` به Go

این سرویس، **قدمِ اولِ مهاجرتِ PHP → Go** است (الگوی Strangler Fig: کدِ PHP دست‌نخورده
می‌مانَد؛ هر بار یک تکهٔ کوچک به Go می‌رود و با یک خط `ProxyPass` جلویش گرفته می‌شود،
و در چند ثانیه هم قابلِ برگشت است).

مستقل از `crm-service` است و مستقل از PHP. اگر بمیرد، فقط مسیرِ `/go/api/*` می‌افتد.

## الان چه دارد؟ (فاز ۱ — فقط اثباتِ کِرنِل)

| مسیر | احراز هویت | کار |
|---|---|---|
| `GET /go/api/health` | ندارد | سلامتِ سرویس + پینگِ دیتابیس |
| `GET /go/api/me` | همان JWTِ اپِ PHP | کاربرِ جاری + نقش + چند اجازهٔ نمونه |

## کِرنِل (`internal/core/`)

- `auth.go` — `AuthMiddleware`: **پورتِ دقیقِ `includes/auth.php::validateToken`**
  (امضای HS256 با همان `jwt_secret`، `exp`، و چکِ `is_active` + `token_version` در دیتابیس).
- `authz.go` — `LoadUser` / `HasPermission` / `IsSuperAdmin` / `IsSameOrg`:
  **پورتِ دقیقِ `includes/permissions.php`** (سوپرادمین → اختیارِ فردی → جدولِ نقش).
- `jalali.go` — تبدیلِ شمسی ↔ میلادی؛ همان الگوریتمِ مرجعِ PHP.
- `config.go` / `db.go` / `httpx.go` — پیکربندی، استخرِ MariaDB، پاسخِ JSON.

> ⚠️ نسخهٔ PHP تا پایانِ مهاجرت **مرجع** است. اگر `permissions.php` یا `auth.php` عوض شد،
> این پکیج هم باید هم‌زمان عوض شود. تست‌های `internal/core/*_test.go` این تطبیق را قفل می‌کنند.

## اجرای محلی

```
cd go-api
copy config.example.json config.json     # (روی ویندوز)  یا  cp روی لینوکس
# در config.json مقادیر را پر کن:
#   db_name  = computeryekta_todo_system  (همان دیتابیسِ لوکال)
#   db_user  = root   db_pass = (خالی، اگر XAMPP پیش‌فرض)
#   jwt_secret = عیناً همان مقدارِ config/config.php
go mod tidy
go test ./...        # باید سبز باشد (لنگرهای تقویم + جدولِ اجازه‌ها)
go run .             # روی 127.0.0.1:8091 بالا می‌آید
```

تستِ سریع:

```
curl http://127.0.0.1:8091/go/api/health
# یک توکنِ واقعی از PHP بگیر و امتحان کن:
# php -r '$_SERVER["DOCUMENT_ROOT"]="d:/Software/xampp/htdocs"; require "config/database.php"; require "includes/auth.php"; echo (new Auth((new Database())->getConnection()))->generateJWTToken(1,3600,1);'
curl -H "Authorization: Bearer <token>" http://127.0.0.1:8091/go/api/me
```

## دیباگ (یادداشتِ مبتدی)

- **«db: اتصال ناموفق»** → MySQLِ XAMPP روشن نیست، یا `db_host` را `localhost` گذاشته‌ای؛
  Go روی ویندوز `localhost` را گاهی IPv6 حل می‌کند — از `127.0.0.1` استفاده کن.
- **`/go/api/me` همیشه 401** → یا `jwt_secret` در `config.json` با `config/config.php` یکی نیست،
  یا توکن منقضی شده، یا `token_version` کاربر در دیتابیس با توکن نمی‌خواند (بعد از logout/تغییرِ رمز).
- **`go: ... GOFLAGS` یا خطای mod** → `go mod tidy` را بزن.
- لاگِ سرویس روی stdout است (زیرِ systemd: `journalctl -u bpm-go-api -f`).

## استقرار روی سرور

هنوز مستقر نشده. مراحلِ یک‌بارهٔ سرور در `deploy/DEPLOY.md` است (کاربرِ محدودِ MySQL →
باینری → systemd → `ProxyPass /go/api/` در Apache). تا وقتی مستقر نشده،
**به `.github/workflows/deploy.yml` دست نزن** — وگرنه دیپلوی روی مرحلهٔ کپیِ باینری شکست می‌خورد.
