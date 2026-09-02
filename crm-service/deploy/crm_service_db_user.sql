-- یوزرِ دیتابیسِ محدود برای سرویسِ Go.
-- روی سرور به‌عنوانِ کاربرِ root/مدیرِ MySQL اجرا شود.
-- `computeryekta_todo_system` را در صورتِ نیاز با نامِ واقعیِ دیتابیس عوض کن.
-- رمز را با یک رشته‌ی تصادفیِ قوی جایگزین کن و همان را در /opt/bmp-crm/config.json بگذار.

CREATE USER IF NOT EXISTS 'crm_service'@'localhost' IDENTIFIED BY 'REPLACE_WITH_STRONG_RANDOM_PASSWORD';

-- نوشتن فقط روی جدول‌های crm_*
GRANT SELECT, INSERT, UPDATE, DELETE ON `computeryekta_todo_system`.`crm_customers`             TO 'crm_service'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON `computeryekta_todo_system`.`crm_assignment_requests`   TO 'crm_service'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON `computeryekta_todo_system`.`crm_targets`               TO 'crm_service'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON `computeryekta_todo_system`.`crm_followups`             TO 'crm_service'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON `computeryekta_todo_system`.`crm_activities`            TO 'crm_service'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON `computeryekta_todo_system`.`crm_sms_templates`         TO 'crm_service'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON `computeryekta_todo_system`.`crm_sms_log`               TO 'crm_service'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON `computeryekta_todo_system`.`crm_proformas`             TO 'crm_service'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON `computeryekta_todo_system`.`crm_proforma_items`        TO 'crm_service'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON `computeryekta_todo_system`.`crm_actual_sales`          TO 'crm_service'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON `computeryekta_todo_system`.`crm_org_accounting_config` TO 'crm_service'@'localhost';

-- خواندنِ فقط سه جدولِ اصلی که برای احراز هویت و شناختِ کارشناس/سازمان لازم است
GRANT SELECT ON `computeryekta_todo_system`.`users`               TO 'crm_service'@'localhost';
GRANT SELECT ON `computeryekta_todo_system`.`organizations`       TO 'crm_service'@'localhost';
GRANT SELECT ON `computeryekta_todo_system`.`user_activity_units` TO 'crm_service'@'localhost';

FLUSH PRIVILEGES;

-- نتیجه: حتی اگر کدِ Go باگ داشته باشد، به هیچ جدولِ دیگری (tasks/tickets/...) دست نمی‌زند.
