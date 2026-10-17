<?php
/**
 * منبع فاکتور «import» = فاکتورهای قدیمیِ واردشده از اکسل (ابزار inv-import-legacy).
 *
 * فاکتورهای ۱ تا ۶۸۳ سال ۱۴۰۵ پیش از BPM در اکسل صادر شده بودند. با این برچسب:
 *   • از فاکتورهای صادرشده در خود BPM (staff/guest) جدا شناخته می‌شوند،
 *   • و اگر بعد از ورود مشکلی دیده شد، همه با یک دستور قابل حذف‌اند
 *     (POST /crm/api/inv/import-legacy/rollback) بی‌آن‌که به بقیه دست بخورد.
 * فقط مقدار جدید به ENUM اضافه می‌شود؛ ردیف‌های موجود تغییری نمی‌کنند.
 */

return [

    'description' => "inv_invoices.source — add 'import' (legacy invoices imported from Excel)",

    'up' => function (PDO $db) {
        $col = $db->query("SHOW COLUMNS FROM `inv_invoices` LIKE 'source'")->fetch(PDO::FETCH_ASSOC);
        if ($col && strpos($col['Type'], "'import'") === false) {
            $db->exec("ALTER TABLE `inv_invoices`
                       MODIFY COLUMN `source` ENUM('staff','guest','import') NOT NULL DEFAULT 'staff'");
        }
    },

    'down' => function (PDO $db) {
        // 🔒 تا وقتی فاکتورِ واردشده هست، حذف مقدار از ENUM آن ردیف‌ها را خراب می‌کند
        $n = (int) $db->query("SELECT COUNT(*) FROM `inv_invoices` WHERE source = 'import'")->fetchColumn();
        if ($n > 0) {
            throw new RuntimeException("{$n} فاکتورِ واردشده از اکسل هنوز وجود دارد؛ اول آن‌ها را از ابزار ورود حذف کنید.");
        }
        $db->exec("ALTER TABLE `inv_invoices`
                   MODIFY COLUMN `source` ENUM('staff','guest') NOT NULL DEFAULT 'staff'");
    },

];
