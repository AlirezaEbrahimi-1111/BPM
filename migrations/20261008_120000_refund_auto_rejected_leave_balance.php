<?php
/**
 * جبران سهمیهٔ مرخصی‌هایی که «رد» شده‌اند ولی سهمیه‌شان برنگشته.
 *
 * ثبت درخواست مرخصی همان لحظه از سهمیه کم می‌کند. رد دستی آن را
 * برمی‌گرداند، ولی رد خودکار کران (cron/auto_approval_check.php — وقتی
 * درخواست تا مهلت تأیید نمی‌شد) فقط وضعیت را «رد شده» می‌کرد و سهمیه برای
 * همیشه کم می‌ماند. کد از این نسخه اصلاح شده؛ این migration موارد قبلی را
 * جبران می‌کند.
 *
 * برای هر مرخصی ردشده، «خالص» همهٔ تراکنش‌های همان درخواست حساب می‌شود
 * (کسر + اصلاح‌های ویرایش + برگشت‌های قبلی). اگر هنوز منفی باشد، همان مقدار
 * برمی‌گردد. پس مرخصی‌ای که قبلا برگشت خورده دست نمی‌خورد و اجرای دوباره
 * هم چیزی اضافه نمی‌کند.
 *
 * ردیف‌های این migration با متن یادداشت زیر مشخص‌اند تا down فقط همان‌ها
 * را بردارد.
 */

const LEAVE_AUTO_REJECT_REFUND_NOTE = 'بازگشت سهمیه — جبران رد خودکار قبلی که سهمیه را برنگردانده بود';

return [

    'description' => 'Refund leave quota for rejected leave requests whose deduction was never returned (cron auto-reject)',

    'up' => function (PDO $db) {
        $rows = $db->query("
            SELECT lr.id, lr.user_id, SUM(t.amount) AS net
            FROM leave_requests lr
            JOIN leave_balance_transactions t
              ON t.related_request_id = lr.id AND t.related_request_type = 'leave'
            WHERE lr.status = 'rejected'
            GROUP BY lr.id, lr.user_id
            HAVING SUM(t.amount) < 0
        ")->fetchAll(PDO::FETCH_ASSOC);

        $ins = $db->prepare("
            INSERT INTO leave_balance_transactions (user_id, type, amount, related_request_id, related_request_type, note)
            VALUES (?, 'manual_adjustment', ?, ?, 'leave', ?)
        ");
        foreach ($rows as $r) {
            $ins->execute([(int) $r['user_id'], -(int) $r['net'], (int) $r['id'], LEAVE_AUTO_REJECT_REFUND_NOTE]);
        }
    },

    'down' => function (PDO $db) {
        $stmt = $db->prepare("DELETE FROM leave_balance_transactions WHERE note = ?");
        $stmt->execute([LEAVE_AUTO_REJECT_REFUND_NOTE]);
    },

];
