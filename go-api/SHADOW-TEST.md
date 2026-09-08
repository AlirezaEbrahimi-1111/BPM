# تستِ سایه‌ای (Shadow test)

قبل از این‌که هر endpointِ پورت‌شده پشتِ `ProxyPass` برود، باید ثابت شود خروجیِ Go
**عیناً** برابرِ خروجیِ PHP است — روی همان دیتابیس، با همان کاربر و همان ورودی.
این تنها «تستِ قرارداد»ِ ماست تا مهاجرت شکننده نشود.

## روش (محلی، ویندوز)

پیش‌نیاز: MySQLِ XAMPP روشن؛ `go-api/config.json` پر و درست.

```bash
# ۱) سرویسِ Go
cd go-api && ./go-api.exe &        # روی 127.0.0.1:8091

# ۲) سرورِ PHPِ توسعه روی ریشهٔ پروژه
php -S 127.0.0.1:8080 -t d:/Software/xampp/htdocs &

# ۳) یک توکنِ واقعی برای کاربرِ تست (مثلاً 12)
TOKEN=$(php -r '$_SERVER["DOCUMENT_ROOT"]="d:/Software/xampp/htdocs";
  require "config/database.php"; require "includes/auth.php";
  echo (new Auth((new Database())->getConnection()))->generateJWTToken(12,3600,1);')

# ۴) هر دو را صدا بزن و مقایسه کن (مقایسهٔ پارس‌شده، مستقل از ترتیبِ کلید)
PHP=$(curl -s -H "Authorization: Bearer $TOKEN" http://127.0.0.1:8080/api/reports/stats.php)
GO=$( curl -s -H "Authorization: Bearer $TOKEN" http://127.0.0.1:8091/go/api/reports/stats)
php -r '$a=json_decode($argv[1],true);$b=json_decode($argv[2],true);
  function n($x){if(is_array($x)){ksort($x);return array_map("n",$x);}return $x;}
  echo n($a)===n($b)?"MATCH\n":"MISMATCH\n".json_encode(["php"=>$a,"go"=>$b],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);' "$PHP" "$GO"
```

- اگر داده‌ی محیطِ توسعه کم است، چند ردیفِ نمونه بساز (با `unique_code` قابلِ‌تشخیص
  مثلِ `t-shadow-…`) و بعدِ تست پاکشان کن.
- برای endpointهای وابسته به نقش، با چند کاربر (سوپرادمین، مدیر، کارمند، خارج‌از‌سازمان) تکرار کن.
- **مقایسه، مقدار و نوع را می‌بیند نه ترتیبِ کلید را** — کلاینت‌ها JSON را پارس می‌کنند.

## روی سرور (قبل از افزودنِ ProxyPass)

همین کار، ولی PHP از خودِ Apache سرو می‌شود و Go روی `127.0.0.1:8091`:

```
curl -s -H "Authorization: Bearer $T" https://itmalek.com/api/reports/stats.php
curl -s -H "Authorization: Bearer $T" http://127.0.0.1:8091/go/api/reports/stats
```

چند روز هر دو را زیرِ نظر بگیر؛ بعد خطِ `ProxyPass /go/api/reports/stats` را اضافه کن.
endpointِ PHP **حذف نمی‌شود** — تا هفته‌ها بعد، به‌عنوانِ مسیرِ برگشتِ فوری می‌مانَد.
