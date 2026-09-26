<?php
/**
 * برچسب وضعیت‌های «باز» و «منتظر پاسخ» گنگ بودن — کاربر نمی‌فهمید نوبت
 * کیه. طبق منطق api/tickets/reply.php (پاسخ سوپرادمین → waiting_reply،
 * پاسخ کاربر → open)، این دو تا رو صریح می‌کنیم که الان نوبت کیه.
 */

return [

    'description' => 'Clarify open/waiting_reply ticket status labels',

    'up' => function (PDO $db) {
        $db->exec("UPDATE `ticket_statuses` SET `label` = 'در انتظار پاسخ پشتیبان' WHERE `name` = 'open'");
        $db->exec("UPDATE `ticket_statuses` SET `label` = 'در انتظار پاسخ کاربر' WHERE `name` = 'waiting_reply'");
    },

    'down' => function (PDO $db) {
        $db->exec("UPDATE `ticket_statuses` SET `label` = 'باز' WHERE `name` = 'open'");
        $db->exec("UPDATE `ticket_statuses` SET `label` = 'منتظر پاسخ' WHERE `name` = 'waiting_reply'");
    },

];
