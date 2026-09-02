# استقرارِ crm-service روی VPS (Apache)

> این کارها **هنوز انجام نشده** — راهنمای بعداً است. اپِ PHPِ فعلی هیچ‌جای این
> فرآیند دست نمی‌خورد؛ فقط یک بلوکِ کوچک به vhostِ Apache اضافه می‌شود و یک
> سرویسِ جدیدِ systemd بالا می‌آید.

فرض‌ها: ریشه‌ی اپ `/var/www/itmalek`، وب‌سرور Apache، دیتابیس MariaDB روی همان سرور،
پوشه‌ی سرویس `/opt/bmp-crm/` (بیرونِ ریشه‌ی وب).

---

## ۱) جدول‌های دیتابیس (یک‌بار)

مهاجرت `migrations/20260902_140000_create_crm_foundation.php` در همان خطِ
دیپلویِ فعلیِ PHP اجرا می‌شود (`sudo -u www-data php /var/www/itmalek/migrate.php up`).
یعنی دفعه‌ی بعد که PHP را دیپلوی کنی، جدول‌های `crm_*` و ستونِ `users.is_sales_manager`
خودشان ساخته می‌شوند. کارِ جداگانه‌ای لازم نیست.

## ۲) یوزرِ دیتابیسِ محدود (یک‌بار)

فایلِ `crm_service_db_user.sql` را باز کن، رمز و در صورتِ نیاز نامِ دیتابیس را عوض کن، بعد:
```bash
sudo mariadb < crm_service_db_user.sql
```

## ۳) ساختِ باینریِ لینوکسی (روی سیستمِ خودت، هر بار که کد عوض شد)

```bash
cd d:/Software/xampp/htdocs/crm-service
# کامپایل برای لینوکسِ سرور:
set GOOS=linux
set GOARCH=amd64
go build -o crm-service-linux .
```
(در PowerShell به‌جای `set X=Y` بزن `$env:GOOS='linux'` و `$env:GOARCH='amd64'`.)

## ۴) کپی به سرور (یک‌بار پوشه را بساز، بعد هر بار فقط باینری)

```bash
# روی سرور، یک‌بار:
sudo mkdir -p /opt/bmp-crm
sudo chown www-data:www-data /opt/bmp-crm

# از سیستمِ خودت:
scp crm-service-linux   deploy@SERVER:/tmp/crm-service
scp deploy/bmp-crm.service  deploy@SERVER:/tmp/

# روی سرور:
sudo mv /tmp/crm-service /opt/bmp-crm/crm-service
sudo chmod +x /opt/bmp-crm/crm-service
```

## ۵) فایلِ پیکربندی روی سرور (یک‌بار)

```bash
sudo nano /opt/bmp-crm/config.json
```
محتوا (مقادیر را از `/var/www/itmalek/config/config.php` بردار، ولی `db_user`/`db_pass`
همان یوزرِ محدودِ مرحله‌ی ۲):
```json
{
  "port": "8090",
  "db_host": "127.0.0.1",
  "db_port": "3306",
  "db_name": "computeryekta_todo_system",
  "db_user": "crm_service",
  "db_pass": "همان رمزِ یوزرِ محدود",
  "jwt_secret": "دقیقاً همان jwt_secret در config/config.php"
}
```
```bash
sudo chown www-data:www-data /opt/bmp-crm/config.json
sudo chmod 600 /opt/bmp-crm/config.json
```

## ۶) سرویسِ systemd (یک‌بار)

```bash
sudo mv /tmp/bmp-crm.service /etc/systemd/system/bmp-crm.service
sudo systemctl daemon-reload
sudo systemctl enable --now bmp-crm
sudo systemctl status bmp-crm         # باید active (running) باشد
curl http://127.0.0.1:8090/crm/api/health   # روی خودِ سرور — باید {"ok":true,...}
```

## ۷) پروکسیِ Apache (یک‌بار)

```bash
sudo a2enmod proxy proxy_http
```
بلوکِ `apache-crm-proxy.conf` را داخلِ همان `<VirtualHost *:443>`ِ itmalek.com اضافه کن، بعد:
```bash
sudo apachectl configtest        # باید Syntax OK
sudo systemctl reload apache2    # reload، نه restart
```

## ۸) تستِ نهایی

```bash
curl https://itmalek.com/crm/api/health
# → {"ok":true,"service":"crm-service","db":true,...}
```
سایتِ اصلی هم باید کاملاً سالم باشد.

---

## استقرارِ خودکار از GitHub Actions (یک‌بار راه‌اندازی)

بعد از این، «Deploy to VPS» هم کدِ PHP و هم باینریِ Go را با هم می‌فرستد و
دیگر هیچ `scp`ای روی ویندوز لازم نیست. `.github/workflows/deploy.yml` باینری را
روی رانرِ گیت‌هاب build می‌کند، با scp در `/opt/bmp-crm/incoming/` می‌گذارد،
و روی سرور `sudo /opt/bmp-crm/apply-crm-binary.sh` را صدا می‌زند.

**یک‌بار روی سرور (با دسترسیِ root):**

```bash
# اسکریپتِ نصب (root-owned تا deploy نتواند تغییرش دهد)
sudo install -o root -g root -m 0755 \
  /var/www/itmalek/crm-service/deploy/apply-crm-binary.sh /opt/bmp-crm/apply-crm-binary.sh

# پوشه‌ی مقصدِ scp — فقط deploy در آن می‌نویسد
sudo install -o deploy -g deploy -d /opt/bmp-crm/incoming

# یک خط sudoers: اجازه‌ی اجرای بی‌رمزِ فقط همین اسکریپت
sudo install -m 0440 \
  /var/www/itmalek/crm-service/deploy/sudoers-bmp-crm /etc/sudoers.d/bmp-crm
sudo visudo -c        # باید «parsed OK»
```

> اگر نامِ کاربرِ SSHِ دیپلوی «deploy» نیست، در `sudoers-bmp-crm` عوضش کن و
> `secrets.VPS_USER` را هم چک کن.
> نیازی به secretِ جدید نیست — همان `VPS_HOST` / `VPS_USER` / `VPS_SSH_KEY`.

بعد از این، به‌روزرسانی = فقط زدنِ **Actions → Deploy to VPS → Run workflow**.

### به‌روزرسانیِ دستی (فقط اگر CI در دسترس نبود)

```bash
# سیستمِ خودت:
$env:GOOS='linux'; $env:GOARCH='amd64'; go build -o crm-service-linux .
scp crm-service-linux deploy@SERVER:/opt/bmp-crm/incoming/crm-service-linux
# سرور:
sudo /opt/bmp-crm/apply-crm-binary.sh
```

## متوقف‌کردن / برگرداندن

```bash
sudo systemctl stop bmp-crm       # سرویس خاموش — فقط /crm/api/ از کار می‌افتد، بقیه‌ی سایت سالم
sudo systemctl disable bmp-crm
# برای حذفِ کاملِ اثرِ دیتابیس:  php migrate.php down  (مهاجرتِ CRM را برمی‌گرداند)
```
