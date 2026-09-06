<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  مهاجرت: تأییدِ حضور‌وغیاب در سطحِ «کاربر» (نه «دستگاه»)
 *  تاریخ: ۱۴۰۵/۰۶/۱۶
 * ───────────────────────────────────────────────────────────────────
 *  چرا؟
 *    شناسهٔ دستگاه (fingerprint) یک توکنِ تصادفی در localStorage/کوکیِ
 *    مرورگر است. «پاک‌کردنِ داده‌های سایت» آن را می‌بَرَد و کاربر با
 *    شناسهٔ نو دوباره «در انتظار تأییدِ سرپرست» می‌شود.
 *
 *    راهِ حل: تصمیمِ «تأیید» به‌جای «این مرورگر» به «این کاربر در این
 *    سازمان» چسبانده می‌شود. بعد از یک‌بار تأیید، هر مرورگر/دستگاه از
 *    IP مجاز بدونِ درخواستِ دوباره کار می‌کند. fingerprint فقط برای لاگ
 *    می‌ماند؛ دروازهٔ امنیتی همان IP شبکهٔ سازمان است (بدون تغییر).
 *
 *  up:
 *    ۱) جدولِ attendance_approved_users
 *    ۲) backfill از دستگاه‌های approvedِ موجود + از رکوردهای حضورِ موجود
 *       (تا هیچ کاربرِ فعالی با اجرای این مهاجرت «قفل» نشود).
 * ═══════════════════════════════════════════════════════════════════
 */

return [

    'description' => 'attendance_approved_users: user-level attendance approval (survives browser cache clear)',

    'up' => function (PDO $db) {

        $db->exec("
            CREATE TABLE IF NOT EXISTS `attendance_approved_users` (
                `id`              INT AUTO_INCREMENT PRIMARY KEY,
                `organization_id` INT NOT NULL,
                `user_id`         INT NOT NULL,
                `approved_by`     INT NULL COMMENT 'سرپرستی که تأیید کرد (NULL = backfill تاریخی)',
                `approved_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_att_appr_user` (`organization_id`, `user_id`),
                KEY `idx_att_appr_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // backfill ۱: هر کاربری که دستگاهِ approved دارد
        try {
            $db->exec("
                INSERT IGNORE INTO `attendance_approved_users` (organization_id, user_id, approved_at)
                SELECT organization_id, first_seen_user_id,
                       MAX(COALESCE(approved_at, created_at, NOW()))
                FROM `attendance_devices`
                WHERE status = 'approved' AND first_seen_user_id IS NOT NULL
                GROUP BY organization_id, first_seen_user_id
            ");
        } catch (Throwable $e) {
            error_log('att_user_approval backfill#1 skipped: ' . $e->getMessage());
        }

        // backfill ۲: هر کاربری که تا حالا حضور ثبت کرده (رفتارِ فعلیِ register.php)
        try {
            $db->exec("
                INSERT IGNORE INTO `attendance_approved_users` (organization_id, user_id, approved_at)
                SELECT organization_id, user_id, MIN(COALESCE(created_at, NOW()))
                FROM `attendance_records`
                WHERE user_id IS NOT NULL AND organization_id IS NOT NULL
                GROUP BY organization_id, user_id
            ");
        } catch (Throwable $e) {
            error_log('att_user_approval backfill#2 skipped: ' . $e->getMessage());
        }
    },

    'down' => function (PDO $db) {
        $db->exec("DROP TABLE IF EXISTS `attendance_approved_users`");
    },

];
