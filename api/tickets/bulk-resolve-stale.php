<?php
/**
 * تغییرِ دسته‌جمعیِ وضعیتِ تیکت‌هایِ «در انتظارِ پاسخِ کاربر» که از آخرین پیامشان
 * ۲۱ روز گذشته → «حل شده». فقط برایِ مدیرِ اصلیِ سیستم (user_id = 1).
 */

header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/Notification.php';

const BULK_RESOLVE_STALE_DAYS = 21;

try {
    $database = new Database();
    $db = $database->getConnection();
    $auth = new Auth($db);
    $user_id = $auth->getUserFromToken();

    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'عدم احراز هویت'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 🔒 فقط مدیرِ اصلیِ سیستم
    if ((int) $user_id !== 1) {
        http_response_code(403);
        error_log("bulk-resolve-stale denied | user_id={$user_id}");
        echo json_encode(['success' => false, 'message' => 'فقط مدیر اصلی سیستم مجاز است'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // شناسهٔ وضعیت‌هایِ مبدأ/مقصد
    $stmt = $db->prepare("SELECT id, name, label FROM ticket_statuses WHERE name IN ('waiting_reply','resolved')");
    $stmt->execute();
    $statuses = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $statuses[$row['name']] = $row;
    }
    if (empty($statuses['waiting_reply']) || empty($statuses['resolved'])) {
        echo json_encode(['success' => false, 'message' => 'وضعیت‌های تیکت پیدا نشد'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $resolvedId    = (int) $statuses['resolved']['id'];
    $resolvedLabel = $statuses['resolved']['label'];

    // تیکت‌هایِ واجدِ شرایط: وضعیت = waiting_reply و آخرین فعالیت (آخرین پیام،
    // یا اگر پیامی نبود تاریخِ ساختِ تیکت) دستِ‌کم ۲۱ روزِ پیش بوده.
    $sql = "
        SELECT t.id, t.ticket_number, t.subject, t.created_by
        FROM tickets t
        JOIN ticket_statuses ts ON t.status_id = ts.id
        LEFT JOIN (
            SELECT ticket_id, MAX(created_at) AS last_msg
            FROM ticket_messages
            WHERE deleted_at IS NULL
            GROUP BY ticket_id
        ) m ON m.ticket_id = t.id
        WHERE t.deleted_at IS NULL
          AND ts.name = 'waiting_reply'
          AND COALESCE(m.last_msg, t.created_at) <= (NOW() - INTERVAL :days DAY)
    ";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':days', BULK_RESOLVE_STALE_DAYS, PDO::PARAM_INT);
    $stmt->execute();
    $targets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$targets) {
        echo json_encode(['success' => true, 'updated' => 0, 'message' => 'تیکتی با این شرایط پیدا نشد'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $updateStmt  = $db->prepare("UPDATE tickets SET status_id = ? WHERE id = ? AND status_id = ?");
    $historyStmt = $db->prepare("
        INSERT INTO ticket_history (ticket_id, user_id, action, old_value, new_value)
        VALUES (?, ?, 'status_changed', ?, ?)
    ");
    $notif = new Notification($db);
    $updated = 0;

    foreach ($targets as $t) {
        $ok = $updateStmt->execute([$resolvedId, (int) $t['id'], (int) $statuses['waiting_reply']['id']]);
        if (!$ok || $updateStmt->rowCount() === 0) {
            continue; // وضعیت بین کوئری و آپدیت عوض شده — رد کن
        }
        $updated++;

        $historyStmt->execute([
            (int) $t['id'], $user_id,
            $statuses['waiting_reply']['label'], $resolvedLabel,
        ]);

        if ((int) $t['created_by'] && (int) $t['created_by'] !== (int) $user_id) {
            try {
                $notif->create([
                    'to_user_id'   => (int) $t['created_by'],
                    'title'        => 'تغییر وضعیت تیکت: ' . $t['ticket_number'],
                    'message'      => 'تیکت «' . $t['subject'] . '» به دلیلِ بی‌پاسخ ماندن به مدت ۲۱ روز، «' . $resolvedLabel . '» شد.',
                    'type'         => 'info',
                    'link'         => '/pages/ticket-detail.php?id=' . (int) $t['id'],
                    'related_type' => 'ticket',
                    'related_id'   => (int) $t['id'],
                    'sms_pattern'  => 'ticket_status_changed',
                    'sms_args'     => [$t['subject'], $resolvedLabel],
                ]);
            } catch (Throwable $e) {
                error_log('bulk-resolve-stale notify skipped for ticket ' . $t['id'] . ': ' . $e->getMessage());
            }
        }
    }

    echo json_encode([
        'success' => true,
        'updated' => $updated,
        'message' => $updated > 0
            ? ($updated . ' تیکت به «' . $resolvedLabel . '» تغییر یافت')
            : 'تغییری اعمال نشد',
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    error_log('bulk-resolve-stale error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطای سرور: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
