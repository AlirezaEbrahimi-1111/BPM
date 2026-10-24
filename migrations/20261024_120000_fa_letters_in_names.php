<?php
/**
 * «ي/ى» و «ك» عربی → «ی» و «ک» فارسی در نام‌های ذخیره‌شده (درخواست ۱۴۰۵/۰۸/۰۲).
 *
 * متن کپی‌شده از شایگان (و فایل‌های اکسل خروجی آن) این حروف عربی را دارد؛ ظاهرشان
 * یکی است ولی جستجوی «کیبورد» نام «كيبورد» را پیدا نمی‌کرد. از این نسخه crm-service
 * هنگام ذخیره تبدیل می‌کند (crm-service/helpers.go::faLetters)؛ این مهاجرت داده‌های
 * قبلی را یکدست می‌کند.
 *
 * فقط اطلاعات پایه (کالا، مشتری، تأمین‌کننده، همکار) — ردیف‌های فاکتورهای صادرشده
 * عمدا دست نمی‌خورند (سند صادرشده نباید بی‌صدا عوض شود).
 * برگشت‌ناپذیر و بی‌خطر: down کاری نمی‌کند.
 */
return [

    'description' => 'Arabic yeh/kaf -> Persian in product/customer/supplier/partner names',

    'up' => function (PDO $db) {
        $cols = [
            'inv_products'   => ['name'],
            'crm_customers'  => ['name', 'address'],
            'inv_suppliers'  => ['name'],
            'inv_partners'   => ['name'],
            'ucrm_customers' => ['name', 'address', 'rep_name'],
        ];
        foreach ($cols as $table => $fields) {
            $exists = $db->query("SHOW TABLES LIKE " . $db->quote($table))->fetchColumn();
            if (!$exists) continue;
            $have = $db->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($fields as $f) {
                if (!in_array($f, $have, true)) continue;
                // ي (U+064A) / ى (U+0649) → ی (U+06CC) ، ك (U+0643) → ک (U+06A9)
                $db->exec("UPDATE `$table` SET `$f` =
                    REPLACE(REPLACE(REPLACE(`$f`, _utf8mb4 0xD98A, _utf8mb4 0xDB8C), _utf8mb4 0xD989, _utf8mb4 0xDB8C), _utf8mb4 0xD983, _utf8mb4 0xDAA9)
                    WHERE `$f` IS NOT NULL AND (
                        `$f` LIKE BINARY CONCAT('%', _utf8mb4 0xD98A, '%') OR
                        `$f` LIKE BINARY CONCAT('%', _utf8mb4 0xD989, '%') OR
                        `$f` LIKE BINARY CONCAT('%', _utf8mb4 0xD983, '%'))");
            }
        }
    },

    'down' => function (PDO $db) {
        // برگشت معنا ندارد (نمی‌دانیم کدام حرف عربی بوده) — عمدا خالی
    },

];
