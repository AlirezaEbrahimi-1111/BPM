<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  مهاجرت: اصلاحِ mime_type نادرست/خالیِ پیوست‌هایِ تیکتِ از‌قبل‌آپلودشده
 *  تاریخ: ۱۴۰۵/۰۶/۲۲
 * ───────────────────────────────────────────────────────────────────
 *  چرا؟
 *    api/tickets/reply.php و create.php تا امروز مقدارِ mime_type رو از
 *    رویِ Content-Type ای که خودِ مرورگر توی آپلود می‌فرستاد ذخیره
 *    می‌کردن — که قابلِ‌اعتماد نیست (مثلاً Samsung Internet برایِ
 *    عکسِ چسبانده‌شده گاهی نوعِ خالی/نادرست می‌فرسته). نتیجه: توی
 *    پیشخوانِ تیکت، به‌جایِ تصویرِ واقعی فقط آیکنِ فایلِ عمومی نشون داده
 *    می‌شد، چون فرانت‌اند فقط رویِ mime_type ای که با 'image/' شروع بشه
 *    عکس رو رندر می‌کنه.
 *
 *    از این به بعد mime_type از رویِ خودِ فایل تشخیص داده می‌شه
 *    (includes/ticket-attachment-helper.php). این مهاجرت فقط رکوردهایِ
 *    قدیمیِ از‌قبل‌آپلودشده رو، صرفاً از رویِ پسوندِ stored_name، اصلاح
 *    می‌کنه — نه اسکن‌کردنِ خودِ فایل‌ها (چون این‌جا به دیسکِ سرور دسترسی
 *    نداریم، فقط دیتابیس).
 *
 *  نکته: این فقط داده است، نه اسکیما.
 * ═══════════════════════════════════════════════════════════════════
 */

return [

    'description' => 'اصلاحِ mime_type پیوست‌هایِ قدیمیِ تیکت بر اساسِ پسوندِ فایل',

    'up' => function (PDO $db) {
        $byExt = [
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
            'pdf'  => 'application/pdf',
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls'  => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'mp3'  => 'audio/mpeg',
            'm4a'  => 'audio/mp4',
            'ogg'  => 'audio/ogg',
            'txt'  => 'text/plain',
        ];

        $stmt = $db->query("SELECT id, stored_name, mime_type FROM ticket_attachments");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $upd = $db->prepare("UPDATE ticket_attachments SET mime_type = ? WHERE id = ?");
        foreach ($rows as $row) {
            $ext = strtolower(pathinfo((string) $row['stored_name'], PATHINFO_EXTENSION));
            if (!isset($byExt[$ext])) {
                continue; // پسوندِ ناشناخته — دست‌نخورده بماند
            }
            $correct = $byExt[$ext];
            if ($row['mime_type'] !== $correct) {
                $upd->execute([$correct, $row['id']]);
            }
        }
    },

    /**
     * بازگرداندن تغییر
     *
     * ⚠️ این یک اصلاحِ داده‌ایِ یک‌طرفه است — mime_type نادرستِ قبلی خودش
     * یک باگ بود، نه یک وضعیتِ معتبر که بخواهیم بازش گردانیم.
     */
    'down' => function (PDO $db) {
        // عمداً خالی — نگاه کن به توضیحِ بالا.
    },

];
