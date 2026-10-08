<?php
/**
 * علامت‌زدن ردیف‌های قدیمی «تمدید موعد» که به نام خود درخواست‌دهنده ثبت شده‌اند.
 *
 * ردیف deadline_extended در تاریخچه دو شکل دارد:
 *   (۱) from_user = تأییدکننده،     to_user = درخواست‌دهنده
 *        — تأیید توسط تأییدکننده، از ۱۴۰۵/۰۵/۱۳ (کامیت ee05799) به بعد
 *   (۲) from_user = درخواست‌دهنده،  to_user = تأییدکننده یا سازندهٔ کار
 *        — «تأیید خودکار»، و همهٔ تأییدهای قبل از ۱۴۰۵/۰۵/۱۳ (قرارداد قدیمی)
 *
 * تاریخچه از این نسخه برای شکل (۱) «به درخواست: <to_user>» نشان می‌دهد. اگر
 * ردیف‌های شکل (۲) علامت نخورند، همان خط برایشان نام تأییدکننده/سازنده را
 * اشتباها «درخواست‌دهنده» نشان می‌دهد. این migration آن‌ها را با
 * from_is_requester در JSON یادداشت‌شان علامت می‌زند (ردیف‌های جدید تأیید
 * خودکار را خود request-deadline.php علامت می‌زند).
 *
 * تشخیص: یک درخواست تأییدشده برای همان کار، از همان from_user، که در فاصلهٔ
 * حداکثر دو دقیقه از ثبت ردیف تاریخچه بسته شده باشد — یعنی کسی که ردیف به
 * نامش ثبت شده خودش درخواست‌دهنده بوده.
 *
 * فقط کلید به JSON اضافه می‌شود؛ بقیهٔ یادداشت (موعد قبلی/جدید/دلیل) دست
 * نمی‌خورد. کلید from_is_requester_backfilled فقط برای این است که down همین
 * ردیف‌ها را بشناسد.
 */

return [

    'description' => 'Flag old deadline_extended history rows recorded under the requester (notes JSON: from_is_requester)',

    'up' => function (PDO $db) {
        $rows = $db->query("
            SELECT DISTINCT h.id, h.notes
            FROM task_history h
            JOIN deadline_requests dr
              ON dr.task_id = h.task_id
             AND dr.requested_by = h.from_user_id
             AND dr.status = 'approved'
             AND ABS(TIMESTAMPDIFF(SECOND, dr.updated_at, h.created_at)) <= 120
            WHERE h.action = 'deadline_extended'
        ")->fetchAll(PDO::FETCH_ASSOC);

        $upd = $db->prepare("UPDATE task_history SET notes = ? WHERE id = ?");
        foreach ($rows as $r) {
            $n = json_decode((string) $r['notes'], true);
            if (!is_array($n) || array_key_exists('from_is_requester', $n)) {
                continue; // یادداشت JSON نیست، یا قبلا علامت خورده
            }
            $n['from_is_requester'] = true;
            $n['from_is_requester_backfilled'] = true;
            $upd->execute([json_encode($n, JSON_UNESCAPED_UNICODE), (int) $r['id']]);
        }
    },

    'down' => function (PDO $db) {
        $rows = $db->query("
            SELECT id, notes FROM task_history
            WHERE action = 'deadline_extended' AND notes LIKE '%from_is_requester_backfilled%'
        ")->fetchAll(PDO::FETCH_ASSOC);

        $upd = $db->prepare("UPDATE task_history SET notes = ? WHERE id = ?");
        foreach ($rows as $r) {
            $n = json_decode((string) $r['notes'], true);
            if (!is_array($n) || empty($n['from_is_requester_backfilled'])) {
                continue;
            }
            unset($n['from_is_requester'], $n['from_is_requester_backfilled']);
            $upd->execute([json_encode($n, JSON_UNESCAPED_UNICODE), (int) $r['id']]);
        }
    },

];
