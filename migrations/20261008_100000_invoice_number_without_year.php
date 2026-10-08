<?php
/**
 * شماره‌ی فاکتورهای قبلی از قالب «سال/شماره» (مثلا 1405/3) به «فقط شماره» (3).
 *
 * از این نسخه، crm-service شماره‌ی فاکتور را بدون سال می‌سازد
 * (پیشوند تنظیمات + seq_no). این migration ستون نمایشی `number` فاکتورهای
 * موجود را هم با همان قالب یکدست می‌کند تا فهرست و چاپ، قدیمی و جدید را
 * یک‌شکل نشان دهند.
 *
 * فقط متن `number` عوض می‌شود؛ seq_year و seq_no (مبنای یکتایی و ریست
 * سالانه) دست‌نخورده می‌مانند — برای همین down می‌تواند قالب قبلی را
 * دقیقا از روی همان دو ستون برگرداند.
 */

return [

    'description' => 'inv_invoices.number — drop the Jalali year from existing invoice numbers (1405/3 -> 3)',

    'up' => function (PDO $db) {
        $db->exec("
            UPDATE `inv_invoices`
            SET `number` = CONCAT(
                COALESCE((SELECT `number_prefix` FROM `inv_settings` WHERE `id` = 1), ''),
                `seq_no`)
            WHERE `seq_no` IS NOT NULL
        ");
    },

    'down' => function (PDO $db) {
        $db->exec("
            UPDATE `inv_invoices`
            SET `number` = CONCAT(
                COALESCE((SELECT `number_prefix` FROM `inv_settings` WHERE `id` = 1), ''),
                `seq_year`, '/', `seq_no`)
            WHERE `seq_no` IS NOT NULL AND `seq_year` IS NOT NULL
        ");
    },

];
