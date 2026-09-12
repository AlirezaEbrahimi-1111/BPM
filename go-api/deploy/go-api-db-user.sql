-- کاربرِ MySQL برای go-api.
-- تصمیم: به‌جایِ گرنتِ محدود و ریزبه‌ریزِ قبلی (که هر ماژولِ جدید یک بازبینیِ
-- دستیِ این فایل را لازم داشت)، go_api حالا دقیقاً هم‌سطحِ کاربرِ اصلیِ PHP
-- (`computeryekta_adminyekta`، در config/config.php) به همین یک دیتابیس
-- دسترسیِ کامل دارد — نه بیشتر (نه به سایرِ دیتابیس‌هایِ سرور، نه GRANT
-- OPTION). این یعنی هر ماژولِ بعدی که پورت شود، دیگر نیازی به آپدیتِ این
-- فایل یا اجرایِ دوباره‌ی SQL نیست.
--
-- اجرا:  sudo mysql < go-api-db-user.sql   (رمز را قبلش عوض کن)

CREATE USER IF NOT EXISTS 'go_api'@'localhost' IDENTIFIED BY 'CHANGE_ME_STRONG_PASSWORD';

GRANT ALL PRIVILEGES ON `computeryekta_todo_system`.* TO 'go_api'@'localhost';

FLUSH PRIVILEGES;
