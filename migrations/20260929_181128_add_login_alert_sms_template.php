<?php
/**
 * الگوی پیامکِ «هشدارِ ورود» — طبقِ درخواستِ صریح، هر بار که کاربری وارد
 * سامانه می‌شه (چه با رمز چه با کدِ تأیید)، این پیامک براش ارسال می‌شه.
 * از همون جدولِ sms_templates که بقیه‌ی پیامک‌های اعلان (task_created و
 * ...) ازش میان استفاده می‌کنه — تا متنش بعدا از صفحه‌ی مدیریتِ الگوها
 * (pages/sms-templates.php) هم قابل‌ویرایش باشه، بدون نیاز به تغییرِ کد.
 */

return [

    'description' => 'Add login_alert SMS template row',

    'up' => function (PDO $db) {
        $stmt = $db->prepare("
            INSERT INTO sms_templates (name, message, is_active)
            VALUES (?, ?, 1)
        ");
        $stmt->execute([
            'login_alert',
            "ورود به حساب کاربری شما در تاریخ {date} ساعت {time}\nسامانه BPM ملک",
        ]);
    },

    'down' => function (PDO $db) {
        $db->exec("DELETE FROM sms_templates WHERE name = 'login_alert'");
    },

];
