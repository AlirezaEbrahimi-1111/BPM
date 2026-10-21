<?php
/**
 * جبران سهمیهٔ مرخصی/پاسی که به‌اشتباه «سوخته» بود (۱۴۰۵/۰۷/۳۰).
 *
 * باگ: وقتی مدیرِ فرد همان مسئول است، create.php مرحلهٔ «تأیید مسئول» را از لحظهٔ ثبت
 * خودکار approved می‌کند. attendance_system/api/requests/delete.php همین علامت را
 * «تأییدگرفته» حساب می‌کرد → حذفِ مرخصیِ ماه جاری، حتی یک دقیقه بعد از ثبت و بدون هیچ
 * تأییدی، سهمیه را می‌سوزاند (نمونهٔ ثابت‌شده: رضا بادپر، مرخصی ۲۰۱، ۱:۱۵).
 * قانون درست (تأیید کاربر): سهمیه فقط وقتی می‌سوزد که مرخصی «تأیید نهایی» شده و بعد حذف
 * شود؛ پاس هیچ‌وقت نمی‌سوزد.
 *
 * این migration برای هر کسرِ مرخصی/پاس که:
 *   • درخواستش دیگر وجود ندارد (حذف شده)،
 *   • و جمع خالص تراکنش‌های همان درخواست هنوز منفی است (سهمیه برنگشته)،
 *   • و — فقط برای مرخصی — هیچ اعلان «تأیید نهایی» برای آن درخواست ثبت نشده
 *     (approve.php هنگام تأیید نهایی اعلان «درخواست مرخصی شما تأیید نهایی شد.» می‌فرستد)،
 * همان مقدار را با یک تراکنش manual_adjustment برمی‌گرداند.
 * مرخصی‌ای که واقعا تأیید نهایی شده و بعد حذف شده دست نمی‌خورد (سوختنش درست است).
 * تکرارپذیر: بعد از جبران، جمع خالص آن درخواست صفر است و دوباره انتخاب نمی‌شود — پس اگر
 * کسی دستی جبران شده باشد هم دوباره چیزی اضافه نمی‌شود.
 */

$refundNote = 'جبران خودکار سهمیهٔ سوخته (حذف درخواستِ تأیید نهایی‌نشده) — ۱۴۰۵/۰۷/۳۰';

$selectCandidates = function (PDO $db): array {
    return $db->query("
        SELECT t.user_id, t.related_request_type AS rtype, t.related_request_id AS rid,
               (SELECT COALESCE(SUM(x.amount), 0) FROM leave_balance_transactions x
                 WHERE x.related_request_id = t.related_request_id
                   AND x.related_request_type = t.related_request_type) AS net
        FROM leave_balance_transactions t
        WHERE t.type IN ('leave_deduction', 'pass_deduction')
          AND t.related_request_id IS NOT NULL
          AND (
                (t.related_request_type = 'leave'
                   AND NOT EXISTS (SELECT 1 FROM leave_requests r WHERE r.id = t.related_request_id)
                   AND NOT EXISTS (SELECT 1 FROM notifications n
                                    WHERE n.related_type = 'leave' AND n.related_id = t.related_request_id
                                      AND n.user_id = t.user_id AND n.message LIKE '%تأیید نهایی%'))
             OR (t.related_request_type = 'pass'
                   AND NOT EXISTS (SELECT 1 FROM pass_requests r WHERE r.id = t.related_request_id))
          )
        GROUP BY t.user_id, t.related_request_type, t.related_request_id
        HAVING net < 0
    ")->fetchAll(PDO::FETCH_ASSOC);
};

return [

    'description' => 'Refund leave/pass quota burned by deleting requests that were never finally approved',

    'up' => function (PDO $db) use ($selectCandidates, $refundNote) {
        $rows = $selectCandidates($db);
        if (!$rows) return;
        $ins = $db->prepare("
            INSERT INTO leave_balance_transactions (user_id, type, amount, related_request_id, related_request_type, note)
            VALUES (?, 'manual_adjustment', ?, ?, ?, ?)
        ");
        foreach ($rows as $r) {
            $ins->execute([(int) $r['user_id'], -(int) $r['net'], (int) $r['rid'], $r['rtype'], $refundNote]);
        }
    },

    'down' => function (PDO $db) use ($refundNote) {
        $db->prepare("DELETE FROM leave_balance_transactions WHERE type = 'manual_adjustment' AND note = ?")
           ->execute([$refundNote]);
    },

];
