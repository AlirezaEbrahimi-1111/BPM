<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  مهاجرت: نقش «مدیر گروه» برای اعضای چت
 *  تاریخ: ۱۴۰۵/۰۶/۲۸
 * ───────────────────────────────────────────────────────────────────
 *  چرا؟
 *    تا امروز فقط «سازنده‌ی گروه» (chat_conversations.created_by) اجازه‌ی
 *    سنجاق‌کردن پیام، افزودن/حذف عضو، و تغییر عکس گروه رو داشت — بدون
 *    امکان واگذاری این اختیارات به کس دیگه. این مهاجرت یک ستون role
 *    به chat_participants اضافه می‌کنه ('member' | 'admin')، پیش‌فرض
 *    'member'، و برای داده‌های قدیمی، سازنده‌ی هر گروه رو خودکار admin
 *    می‌کنه — تا این ویژگی برای گروه‌های از‌قبل‌موجود هم بدون نیاز به
 *    کار دستی درست کار کنه.
 *
 *  نکته: خود «سازنده» (created_by) هنوز یک سطح بالاتر از admin عادیه —
 *  فقط سازنده می‌تونه یک admin رو عزل کنه یا گروه رو حذف کنه. این تمایز
 *  توی کد اپلیکیشن چک می‌شه (created_by vs role='admin')، نه اینجا.
 * ═══════════════════════════════════════════════════════════════════
 */

return [

    'description' => 'افزودن ستون role (member/admin) به chat_participants + سازنده‌ی هر گروه = admin',

    'up' => function (PDO $db) {
        $stmt = $db->query("SHOW COLUMNS FROM `chat_participants` LIKE 'role'");
        if (!$stmt->fetch()) {
            $db->exec("
                ALTER TABLE `chat_participants`
                ADD COLUMN `role` ENUM('member','admin') NOT NULL DEFAULT 'member' AFTER `user_id`
            ");
        }

        // بک‌فیل: سازنده‌ی هر گروه ازقبل‌موجود، admin بشه
        $db->exec("
            UPDATE chat_participants cp
            JOIN chat_conversations c
                ON c.id = cp.conversation_id
               AND c.type != 'direct'
               AND c.created_by = cp.user_id
            SET cp.role = 'admin'
            WHERE cp.role != 'admin'
        ");
    },

    'down' => function (PDO $db) {
        $stmt = $db->query("SHOW COLUMNS FROM `chat_participants` LIKE 'role'");
        if ($stmt->fetch()) {
            $db->exec("ALTER TABLE `chat_participants` DROP COLUMN `role`");
        }
    },

];
