# استقرارِ go-api روی سرور (یک‌بار)

الگو دقیقاً مثلِ `crm-service` است، فقط پورت/نام/مسیر فرق دارد. **تا این مراحل انجام
نشده، سرویس روی سرور کاری نمی‌کند و کدِ `go-api/` صرفاً بی‌اثر روی دیسک می‌مانَد.**

پیش‌فرض‌ها: پورتِ داخلی `8091`، مسیرِ نصب `/opt/bpm-go-api`، کاربر `www-data`.

---

## ۱) کاربرِ محدودِ دیتابیس

```
# رمز را در فایل عوض کن، بعد:
sudo mysql < /var/www/itmalek/go-api/deploy/go-api-db-user.sql
```

## ۲) ساختِ باینری (روی سرور — ساده‌ترین راه، مثلِ crm-service)

```
cd /var/www/itmalek/go-api
/usr/local/go/bin/go build -trimpath -ldflags "-s -w" -o /opt/bpm-go-api/go-api .
sudo chown -R www-data:www-data /opt/bpm-go-api
```

## ۳) config

```
sudo -u www-data cp /var/www/itmalek/go-api/config.example.json /opt/bpm-go-api/config.json
sudo -u www-data nano /opt/bpm-go-api/config.json
```
پر کن:
- `db_name` = نامِ دیتابیسِ پروداکشن
- `db_user` = `go_api` ، `db_pass` = همان رمزِ مرحلهٔ ۱
- `jwt_secret` = **عیناً** همان مقدارِ `/var/www/itmalek/config/config.php`

```
sudo chmod 600 /opt/bpm-go-api/config.json
```

## ۴) systemd

```
sudo cp /var/www/itmalek/go-api/deploy/bpm-go-api.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now bpm-go-api
systemctl status bpm-go-api          # باید active (running)
curl -s http://127.0.0.1:8091/go/api/health   # {"db":true,"ok":true,...}
```

## ۵) پروکسیِ Apache

`deploy/apache-go-api-proxy.conf` را داخلِ `<VirtualHost *:443>`ِ
`/etc/apache2/sites-available/itmalek.conf` (کنارِ بلوکِ `/crm/api/`) بگذار.

```
sudo apachectl configtest        # Syntax OK
sudo systemctl reload apache2     # reload — نه restart
curl -s https://itmalek.com/go/api/health
```

## ۶) (بعداً) اتصال به CI

وقتی این کار کرد، در `.github/workflows/deploy.yml` یک مرحلهٔ build+scp+apply
برای `go-api` هم مثلِ `crm-service` اضافه کن (باینری → `/opt/bpm-go-api/incoming/`
→ اسکریپتِ `apply` که نصب و `systemctl restart bpm-go-api` می‌کند). تا آن موقع،
هر تغییرِ Go را دستی با مرحلهٔ ۲ روی سرور rebuild کن.

---

## برگشتِ فوری

سه خطِ `ProxyPass /go/api/` را در vhost کامنت کن + `systemctl reload apache2`.
بلافاصله همه‌چیز به PHP برمی‌گردد. سرویس را هم می‌شود `systemctl stop bpm-go-api` کرد.
