<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  مهاجرت: زیرساختِ «گرافِ مراحلِ روتین» (فاز ۵ — فقط اسکیما)
 * ───────────────────────────────────────────────────────────────────
 *  طبقِ تصمیمِ ۱۴۰۵/۰۷/۰۹: دیگه «آبشاری/موازی» به‌عنوانِ یک تنظیمِ جداگانه
 *  وجود نداره — همه‌چیز (ترتیب، موازی‌بودن، انشعاب، هم‌گراییِ چندمسیر)
 *  فقط با رسمِ یال (انشعاب) بینِ مرحله‌ها توی نمودار تعیین می‌شه:
 *    • مرحله‌ای که هیچ یالِ ورودی نداره → همون اولِ روتین فعال می‌شه
 *    • یک مرحله می‌تونه چندتا یالِ خروجی داشته باشه (فورک/موازی)
 *    • یک مرحله می‌تونه چندتا یالِ ورودی داشته باشه (هم‌گرایی/join) —
 *      تا همه‌یِ پیش‌نیازهاش تموم نشن، فعال نمی‌شه
 *    • مرحله‌یِ «نقطه‌یِ تصمیم» یالِ خروجیِ شرطی داره (فقط approve یا
 *      فقط reject از بینِ یال‌هاش واقعا فعال می‌شه، نه هردو)
 *
 *  🔒 قالب‌هایِ قدیمی (که با execution_mode=cascade/parallel +
 *  on_approve_step_order ساخته شدن) دست‌نخورده می‌مونن و با همون موتورِ
 *  قدیمی اجرا می‌شن — طبقِ تصمیمِ صریح، نه حذف می‌شن نه migrate. ستونِ
 *  جدیدِ workflow_templates.engine_version مشخص می‌کنه کدوم موتور
 *  استفاده بشه: ۱ = قدیمی (پیش‌فرض، برایِ قالب‌هایِ موجود)، ۲ = گراف‌محور
 *  (قالب‌هایِ جدید از این به بعد).
 *
 *  منطقِ موتور (فعال‌سازیِ مبتنی‌بر گراف) و رابطِ کاربریِ ساختِ یال، در
 *  فازهایِ بعدی میان — این فایل فقط اسکیماست.
 * ═══════════════════════════════════════════════════════════════════
 */

return [

    'description' => 'Phase 5: schema for graph-based workflow steps (transitions table) — replaces cascade/parallel for new templates only',

    'up' => function (PDO $db) {

        $colExists = function (string $table, string $col) use ($db): bool {
            $s = $db->prepare("
                SELECT 1 FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
            ");
            $s->execute([$table, $col]);
            return (bool) $s->fetchColumn();
        };

        // ── ستونِ نسخه‌ی موتور رویِ workflow_templates ──────────────────
        // 🔒 پیش‌فرض ۱ (قدیمی) تا قالب‌هایِ موجود بی‌هیچ تغییری همون‌جوری
        // که بودن کار کنن؛ فقط قالب‌هایِ تازه‌ساخته‌شده صراحتا ۲ می‌گیرن.
        if (!$colExists('workflow_templates', 'engine_version')) {
            $db->exec("ALTER TABLE `workflow_templates`
                ADD COLUMN `engine_version` TINYINT(1) NOT NULL DEFAULT 1
                COMMENT '1=قدیمی (cascade/parallel ستونی)، 2=گراف‌محور (جدولِ transitions)'");
        }

        // ── جدولِ یال‌هایِ گراف ───────────────────────────────────────
        $db->exec("
            CREATE TABLE IF NOT EXISTS `workflow_step_transitions` (
                `id`              INT AUTO_INCREMENT PRIMARY KEY,
                `template_id`     INT NOT NULL,
                `from_step_order` INT NULL COMMENT 'NULL = بدونِ پیش‌نیاز؛ همونِ اولِ روتین فعال می‌شه',
                `to_step_order`   INT NOT NULL,
                `condition`       ENUM('always','approve','reject') NOT NULL DEFAULT 'always',
                `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_wst_template` (`template_id`),
                INDEX `idx_wst_from` (`template_id`, `from_step_order`),
                INDEX `idx_wst_to` (`template_id`, `to_step_order`),
                CONSTRAINT `fk_wst_template` FOREIGN KEY (`template_id`)
                    REFERENCES `workflow_templates` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
        ");
    },

    'down' => function (PDO $db) {
        $db->exec("DROP TABLE IF EXISTS `workflow_step_transitions`");

        $s = $db->prepare("
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'workflow_templates' AND COLUMN_NAME = 'engine_version'
        ");
        $s->execute();
        if ($s->fetchColumn()) {
            $db->exec("ALTER TABLE `workflow_templates` DROP COLUMN `engine_version`");
        }
    },

];
