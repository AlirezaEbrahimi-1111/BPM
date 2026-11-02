<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  مهاجرت: هر تارگت یک مبنا و یک مبلغ — کف فروش یا سقف فروش
 *  تاریخ: ۱۴۰۵/۰۸/۱۱
 * ───────────────────────────────────────────────────────────────────
 *  ucrm_targets.basis: 'target' = سقف فروش | 'floor' = کف فروش؛ مبلغ در target_amount
 *  - ردیف فقط‌کف: مبلغ به target_amount می‌رود و مبنا کف فروش می‌شود
 *  - ردیف دارای هر دو: سقف فروش می‌ماند (طبق تصمیم ۱۴۰۵/۰۸/۱۱)
 *  - floor_amount خوانده نمی‌شود و فقط به‌عنوان نسخهٔ پشتیبان به floor_amount_legacy تغییر نام می‌دهد
 *  - قواعد: برای هر (دوره، کارشناس) یک قاعدهٔ فعال؛ اگر هر دو نوع فعال بود، قاعدهٔ کف غیرفعال می‌شود
 * ═══════════════════════════════════════════════════════════════════
 */

return [

    'description' => 'یک مبنا و یک مبلغ برای هر تارگت (کف فروش یا سقف فروش)',

    'up' => function (PDO $db) {
        if (!$db->query("SHOW COLUMNS FROM `ucrm_targets` LIKE 'basis'")->fetch()) {
            $db->exec("ALTER TABLE `ucrm_targets` ADD COLUMN `basis` VARCHAR(10) NOT NULL DEFAULT 'target' COMMENT 'target = سقف فروش | floor = کف فروش' AFTER `target_amount`");
        }
        if ($db->query("SHOW COLUMNS FROM `ucrm_targets` LIKE 'floor_amount'")->fetch()) {
            $db->exec("UPDATE `ucrm_targets` SET `target_amount` = `floor_amount`, `basis` = 'floor' WHERE `target_amount` IS NULL AND `floor_amount` IS NOT NULL");
            $db->exec("ALTER TABLE `ucrm_targets` CHANGE `floor_amount` `floor_amount_legacy` BIGINT NULL COMMENT 'پشتیبان؛ استفاده نمی‌شود'");
        }
        $db->exec("
            UPDATE `ucrm_target_rules` f
            JOIN `ucrm_target_rules` t
              ON t.organization_id = f.organization_id AND t.period_type = f.period_type
             AND COALESCE(t.rep_id, 0) = COALESCE(f.rep_id, 0)
             AND t.basis = 'target' AND t.is_active = 1
            SET f.is_active = 0
            WHERE f.basis = 'floor' AND f.is_active = 1
        ");
    },

    'down' => function (PDO $db) {
        if ($db->query("SHOW COLUMNS FROM `ucrm_targets` LIKE 'floor_amount_legacy'")->fetch()) {
            $db->exec("ALTER TABLE `ucrm_targets` CHANGE `floor_amount_legacy` `floor_amount` BIGINT NULL COMMENT 'کف فروش (تومان)'");
        }
        if ($db->query("SHOW COLUMNS FROM `ucrm_targets` LIKE 'basis'")->fetch()) {
            $db->exec("UPDATE `ucrm_targets` SET `floor_amount` = `target_amount`, `target_amount` = NULL WHERE `basis` = 'floor'");
            $db->exec("ALTER TABLE `ucrm_targets` DROP COLUMN `basis`");
        }
    },

];
