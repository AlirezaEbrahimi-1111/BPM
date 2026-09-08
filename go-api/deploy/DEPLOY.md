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

## ۶) اتصال به CI (بعد از این‌که مراحلِ ۱–۵ کار کردند)

### ۶الف) نصبِ یک‌بارهٔ ابزارِ استقرارِ خودکار روی سرور

```
sudo install -o root -g root -m 0755 /var/www/itmalek/go-api/deploy/apply-go-api.sh /opt/bpm-go-api/apply-go-api.sh
sudo install -o deploy -g deploy -d /opt/bpm-go-api/incoming
sudo install -m 0440 /var/www/itmalek/go-api/deploy/sudoers-bpm-go-api /etc/sudoers.d/bpm-go-api
sudo visudo -c
```
`visudo -c` باید «parsed OK» بدهد.

### ۶ب) افزودن به `.github/workflows/deploy.yml`

سه چیز اضافه می‌شود، دقیقاً مثلِ `crm-service`:
1. مرحلهٔ `Build go-api (linux/amd64)` — `GOOS=linux GOARCH=amd64 CGO_ENABLED=0 go build ... -o go-api-linux .` در `working-directory: go-api`
2. مرحلهٔ `scp-action` — `source: go-api/go-api-linux` → `target: /opt/bpm-go-api/incoming` با `strip_components: 1`
3. در مرحلهٔ آخرِ ssh، یک خط: `sudo /opt/bpm-go-api/apply-go-api.sh`

> تا وقتی ۶الف انجام نشده، **این خط را به deploy.yml اضافه نکن** — وگرنه دیپلویِ بعدی
> روی مرحلهٔ scp یا apply شکست می‌خورد.

بعد از هر دو: یک‌بار «Deploy to VPS» را بزن و مطمئن شو `apply-go-api` در لاگ
«go-api به‌روزرسانی و ری‌استارت شد» می‌دهد.

---

## برگشتِ فوری

سه خطِ `ProxyPass /go/api/` را در vhost کامنت کن + `systemctl reload apache2`.
بلافاصله همه‌چیز به PHP برمی‌گردد. سرویس را هم می‌شود `systemctl stop bpm-go-api` کرد.
