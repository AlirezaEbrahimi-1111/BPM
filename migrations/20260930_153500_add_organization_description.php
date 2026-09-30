<?php
/**
 * جدولِ organizations از اول ستونِ description نداشت، با اینکه هم فرمِ
 * «مشخصات سازمان» (pages/organization.php) این فیلد رو داره هم
 * api/organization/update.php همیشه توی UPDATE ست می‌کنه — نتیجه: هر
 * ذخیره‌ای (چه توضیحات پر باشه چه خالی) با خطای «ستون پیدا نشد» ۵۰۰
 * می‌داد. این migration فقط همون یک ستونِ جاافتاده رو اضافه می‌کنه،
 * هم‌نوع با address (TEXT، قابل‌خالی، بدونِ مقدارِ پیش‌فرض).
 */

return [

    'description' => 'Add missing description column to organizations table',

    'up' => function (PDO $db) {
        $db->exec("ALTER TABLE `organizations` ADD COLUMN `description` TEXT NULL AFTER `address`");
    },

    'down' => function (PDO $db) {
        $db->exec("ALTER TABLE `organizations` DROP COLUMN `description`");
    },

];
