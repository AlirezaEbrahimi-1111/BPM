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

-- ماژولِ گزارش‌ها (پورتِ api/reports/*: stats/today/history/list/detail/
-- search/save/submit/delete/generate). ⚠️ این فایل قبل از این batch اصلاً
-- گرنتی روی `reports` نداشت با اینکه stats/today/history/list از قبل روی
-- پروداکشن زنده بودند — یعنی یا این فایل با چیزی که واقعاً روی سرور اجرا
-- شده هم‌خوانی نداشت، یا گرنتِ فعلیِ go_api رویِ سرور از این محدودتر نیست.
-- قبل از اجرا حتماً با `SHOW GRANTS FOR 'go_api'@'localhost';` روی خودِ
-- سرور وضعیتِ واقعی را چک کن؛ اجرای دوباره‌ی یک GRANT مشابه بی‌ضرر است.
GRANT SELECT, INSERT, DELETE
    ON `computeryekta_todo_system`.`reports` TO 'go_api'@'localhost';
GRANT SELECT (user_id, activity_unit)
    ON `computeryekta_todo_system`.`user_activity_units` TO 'go_api'@'localhost';
-- ستون‌هایِ اضافیِ users که submit.php/generate.php لازم دارند (این GRANT
-- به فهرستِ ستون‌هایِ فازِ ۱ بالا «اضافه» می‌شود، جایگزینش نمی‌کند).
GRANT SELECT (activity_unit, report_prefix, report_suffix)
    ON `computeryekta_todo_system`.`users` TO 'go_api'@'localhost';
GRANT SELECT (id, assignee_id, activity_section, task_type, due_date,
              start_date, priority, created_at, title, status, description)
    ON `computeryekta_todo_system`.`tasks` TO 'go_api'@'localhost';

-- ── با هر ماژولِ بعدی این‌جا اضافه می‌شود، مثال‌ها (فعلاً کامنت): ──
-- GRANT SELECT ON `computeryekta_todo_system`.`notifications` TO 'go_api'@'localhost';
-- GRANT UPDATE (is_read, read_at) ON `computeryekta_todo_system`.`notifications` TO 'go_api'@'localhost';
-- GRANT INSERT ON `computeryekta_todo_system`.`sms_logs` TO 'go_api'@'localhost';
-- GRANT SELECT ON `computeryekta_todo_system`.`sms_templates` TO 'go_api'@'localhost';

FLUSH PRIVILEGES;
