<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  مهاجرت: افزودن توضیحاتِ تکمیلِ مرحله به کارهای روتین
 *  تاریخ: ۱۴۰۵/۰۵/۱۲
 * ───────────────────────────────────────────────────────────────────
 *  چرا؟
 *    وقتی یک مرحله از کار روتین توسط فردی تکمیل می‌شود، باید بتواند
 *    توضیحی دربارهٔ کاری که انجام داده بنویسد؛ این توضیح باید برای
 *    مسئولِ مرحلهٔ بعد، ایجادکنندهٔ روتین و مدیران قابل مشاهده باشد.
 *
 *    این ستون، توضیحاتِ هر مرحله را کنار completed_by/completed_at
 *    نگه می‌دارد تا در جزئیات روتین (workflow-monitor.php) قابل نمایش
 *    باشد. علاوه بر این، همین متن در task_history هم ثبت می‌شود تا در
 *    تاریخچهٔ خودِ تسک (هم مرحلهٔ فعلی، هم مرحلهٔ بعد) دیده شود.
 * ═══════════════════════════════════════════════════════════════════
 */

return [

    'description' => 'افزودن ستون completion_notes به workflow_instance_steps',

    'up' => function (PDO $db) {

        $stmt = $db->prepare("
            SELECT COUNT(*) FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name   = 'workflow_instance_steps'
              AND column_name  = 'completion_notes'
        ");
        $stmt->execute();

        if ((int) $stmt->fetchColumn() === 0) {
            $db->exec("
                ALTER TABLE `workflow_instance_steps`
                ADD COLUMN `completion_notes` TEXT NULL
                COMMENT 'توضیحاتی که انجام‌دهنده هنگام تکمیل این مرحله وارد کرده'
                AFTER `completed_by`
            ");
        }
    },

    'down' => function (PDO $db) {

        $stmt = $db->prepare("
            SELECT COUNT(*) FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name   = 'workflow_instance_steps'
              AND column_name  = 'completion_notes'
        ");
        $stmt->execute();

        if ((int) $stmt->fetchColumn() > 0) {
            $db->exec("ALTER TABLE `workflow_instance_steps` DROP COLUMN `completion_notes`");
        }
    },

];
