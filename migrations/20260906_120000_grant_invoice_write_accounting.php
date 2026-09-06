<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  مهاجرت: اجازهٔ «ساخت و ویرایشِ فاکتورِ رسمی» به کاربر id=19 و
 *          کلِ تیمِ حسابداریِ سازمانِ ۱
 *  تاریخ: ۱۴۰۵/۰۶/۱۵
 * ───────────────────────────────────────────────────────────────────
 *  چرا؟
 *    دیدنِ ماژولِ فروش/فاکتور از طریقِ includes/crm_access.php کنترل
 *    می‌شود (منو + بازکردنِ صفحه‌ها). ولی «نوشتن» — ساخت/ویرایش/تأییدِ
 *    فاکتور — هم در PHP و هم در سرویسِ Go فقط با مجوزِ
 *    users.is_create_official_invoice (یا is_sales_manager) اجازه داده
 *    می‌شود (crm-service/helpers.go → requireFlags).
 *
 *    این مهاجرت آن مجوز را برای این افراد ۱ می‌کند:
 *      • کاربر id = 19 (رضا فضایلی)
 *      • هر کاربرِ فعالِ سازمانِ ۱ که واحدِ فعالیتش 'AC' (حسابداری) باشد
 *        — هم از فیلدِ قدیمیِ users.activity_unit، هم از جدولِ چندواحدیِ
 *        user_activity_units.
 *
 *  نکته: این فقط داده است، نه اسکیما. ستونِ is_create_official_invoice
 *  قبلاً در مهاجرتِ 20260902_150000 ساخته شده.
 * ═══════════════════════════════════════════════════════════════════
 */

return [

    'description' => 'اجازهٔ ساخت/ویرایشِ فاکتورِ رسمی به id=19 و تیمِ حسابداریِ سازمانِ ۱',

    'up' => function (PDO $db) {

        // ۱) کاربر id = 19 (رضا فضایلی)
        $db->exec("UPDATE `users`
                   SET `is_create_official_invoice` = 1
                   WHERE `id` = 19 AND `is_active` = 1");

        // ۲) تیمِ حسابداریِ سازمانِ ۱ — از فیلدِ قدیمیِ users.activity_unit
        $db->exec("UPDATE `users`
                   SET `is_create_official_invoice` = 1
                   WHERE `is_active` = 1
                     AND `organization_id` = 1
                     AND `activity_unit` = 'AC'");

        // ۳) همان تیم — از جدولِ چندواحدیِ user_activity_units (اگر در این محیط وجود داشت)
        try {
            $db->exec("UPDATE `users` u
                       JOIN `user_activity_units` uau
                            ON uau.`user_id` = u.`id` AND uau.`activity_unit` = 'AC'
                       SET u.`is_create_official_invoice` = 1
                       WHERE u.`is_active` = 1 AND u.`organization_id` = 1");
        } catch (Throwable $e) {
            // جدولِ user_activity_units در این محیط نیست — بی‌صدا رد شو
        }
    },

    'down' => function (PDO $db) {

        $db->exec("UPDATE `users`
                   SET `is_create_official_invoice` = 0
                   WHERE `id` = 19");

        $db->exec("UPDATE `users`
                   SET `is_create_official_invoice` = 0
                   WHERE `organization_id` = 1
                     AND `activity_unit` = 'AC'");

        try {
            $db->exec("UPDATE `users` u
                       JOIN `user_activity_units` uau
                            ON uau.`user_id` = u.`id` AND uau.`activity_unit` = 'AC'
                       SET u.`is_create_official_invoice` = 0
                       WHERE u.`organization_id` = 1");
        } catch (Throwable $e) {
            // جدولِ user_activity_units در این محیط نیست — بی‌صدا رد شو
        }
    },

];
