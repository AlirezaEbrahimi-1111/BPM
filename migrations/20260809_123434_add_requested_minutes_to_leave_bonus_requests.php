<?php
/**
 * افزودن مقدار درخواستی — کارفرما متوجه شد در فهرست سرپرست مشخص نیست
 * کارمند چقدر سهمیهٔ تشویقی می‌خواد؛ الان کارمند موقع ارسال درخواست
 * مقدار موردنظرش (دقیقه) رو هم مشخص می‌کنه و همون در فهرست نمایش داده
 * و به‌عنوان مقدار پیش‌فرض اعطا استفاده می‌شه.
 */

return [

    'description' => 'Add requested_minutes to leave_bonus_requests',

    'up' => function (PDO $db) {
        $db->exec("ALTER TABLE `leave_bonus_requests`
            ADD COLUMN `requested_minutes` INT NULL AFTER `note`
        ");
    },

    'down' => function (PDO $db) {
        $db->exec("ALTER TABLE `leave_bonus_requests` DROP COLUMN `requested_minutes`");
    },

];
