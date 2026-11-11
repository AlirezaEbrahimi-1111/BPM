<?php
/**
 * تعطیلات رسمی آیندهٔ ۱۴۰۵ → سراسری
 *
 * تعطیلات رسمی امسال را سرپرست سازمان یکتا (کاربر ۱۲۹) ثبت کرده بود؛ فرم افزودن برای
 * هر کسی جز id=1 دامنه را بی‌صدا «فقط سازمان من» می‌گذارد، پس همه با organization_id=1
 * ذخیره شدند و سازمان‌های دیگر (و هر سازمان جدید) هیچ‌کدام را نداشتند.
 *
 * فقط ردیف‌های آینده (۲۶ تا ۳۳، از ۱۴۰۵/۱۰/۰۲ تا ۱۴۰۵/۱۲/۲۹) سراسری می‌شوند. ردیف‌های گذشتهٔ
 * فروردین (۱۹ تا ۲۵) عمدا دست نمی‌خورند تا غیبت/حقوق روزهای گذشتهٔ سازمان‌های دیگر عوض نشود.
 * برای یکتا تغییری نیست (همین الان هم تعطیل‌اند). هم‌تاریخ سراسری وجود ندارد (بررسی‌شده)،
 * ولی برای اطمینان ردیفی که هم‌تاریخش سراسری باشد تبدیل نمی‌شود.
 */

return [

    'description' => 'Make future official holidays (ids 26-33, org 1) global for all organizations',

    'up' => function (PDO $db) {
        $db->exec("
            UPDATE holidays h
            SET h.organization_id = NULL
            WHERE h.id BETWEEN 26 AND 33
              AND h.organization_id = 1
              AND h.type = 'date'
              AND NOT EXISTS (
                  SELECT 1 FROM (
                      SELECT holiday_date FROM holidays WHERE organization_id IS NULL AND type = 'date'
                  ) g WHERE g.holiday_date = h.holiday_date
              )
        ");
    },

    'down' => function (PDO $db) {
        $db->exec("
            UPDATE holidays
            SET organization_id = 1
            WHERE id BETWEEN 26 AND 33
              AND organization_id IS NULL
              AND type = 'date'
        ");
    },

];
