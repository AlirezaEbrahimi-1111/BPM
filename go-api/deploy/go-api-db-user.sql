-- کاربرِ محدودِ MySQL برای go-api.
-- اصل: این سرویس در فازِ اول فقط باید بخواند. با هر ماژولِ جدید، فقط همان
-- جدول‌هایِ لازم را اینجا اضافه کن — نه بیشتر.
--
-- اجرا:  sudo mysql < go-api-db-user.sql   (رمز را قبلش عوض کن)

CREATE USER IF NOT EXISTS 'go_api'@'localhost' IDENTIFIED BY 'CHANGE_ME_STRONG_PASSWORD';

-- فاز ۱ (کِرنِل): فقط برای احراز هویت و بارگذاریِ کاربر.
GRANT SELECT (id, role, organization_id, activity_section,
              can_create_routine, can_create_workflow,
              is_active, is_deleted, token_version,
              first_name, last_name)
    ON `computeryekta_todo_system`.`users` TO 'go_api'@'localhost';

-- ── با هر ماژولِ بعدی این‌جا اضافه می‌شود، مثال‌ها (فعلاً کامنت): ──
-- GRANT SELECT ON `computeryekta_todo_system`.`notifications` TO 'go_api'@'localhost';
-- GRANT UPDATE (is_read, read_at) ON `computeryekta_todo_system`.`notifications` TO 'go_api'@'localhost';
-- GRANT INSERT ON `computeryekta_todo_system`.`sms_logs` TO 'go_api'@'localhost';
-- GRANT SELECT ON `computeryekta_todo_system`.`sms_templates` TO 'go_api'@'localhost';

FLUSH PRIVILEGES;
