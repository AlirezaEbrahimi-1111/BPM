<?php
/**
 * link-preview.php
 * دادهٔ پیش‌نمایش یک ارجاع کار/تیکت که در متن یک پیام چت درج شده
 * (توکن‌هایی مثل #task:123 یا #ticket:45) — عنوان، وضعیت، و لیست پیوست‌ها.
 *
 * GET ?type=task|ticket&id=123
 *
 * دسترسی: هم‌سازمانی بودن با ارجاع‌دهنده کافیه (نه صرفا سازنده/مسئول کار)،
 * چون هدف اینه که هرکسی که پیام چت رو می‌بینه (اگه هم‌سازمانه) بتونه
 * پیش‌نمایش رو هم ببینه — دقیقا مثل مدل دید تیکت‌ها (سازمان‌محور).
 * کاربر id=1 همه‌چیز رو می‌بینه.
 */

header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/working-days-helper.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/task-dates-helper.php';

/** اولین N کلمه از یک متن (برای خلاصهٔ آخرین پیام تیکت) */
function firstWords(string $text, int $n): string {
    $words = preg_split('/\s+/u', trim($text));
    $trimmed = implode(' ', array_slice($words, 0, $n));
    return $trimmed . (count($words) > $n ? ' …' : '');
}

const TASK_STATUS_LABELS = [
    'not_started'            => 'شروع نشده',
    'in_progress'             => 'در حال انجام',
    'pending_approval'        => 'در انتظار تأیید',
    'completed'                => 'تکمیل شده',
    'approved'                 => 'تأییدشده',
    'rejected'                 => 'ردشده',
    'stopped'                  => 'متوقف شده',
    'delegated'                => 'ارجاع‌شده',
    'termination_requested'    => 'درخواست توقف',
    'period_done'              => 'دورهٔ فعلی انجام شد',
];

try {
    $user_id = requireAuth();

    $type = $_GET['type'] ?? '';
    $id   = (int) ($_GET['id'] ?? 0);
    if (!$id || !in_array($type, ['task', 'ticket'], true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'پارامترهای نامعتبر']);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("SELECT organization_id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $orgId = (int) ($stmt->fetchColumn() ?: 0);

    if ($type === 'task') {
        $stmt = $db->prepare("
            SELECT t.id, t.title, t.status, t.organization_id, t.is_deleted,
                   t.task_type, t.start_date, t.end_date, t.period_type,
                   t.due_date, t.deadline, t.original_deadline, t.overdue_forgiven_credit,
                   t.assignee_id,
                   CONCAT(u.first_name, ' ', u.last_name) AS assignee_name
            FROM tasks t
            LEFT JOIN users u ON t.assignee_id = u.id
            WHERE t.id = ? AND t.is_deleted = 0 AND (t.organization_id = ? OR ? = 1)
        ");
        $stmt->execute([$id, $orgId, $user_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'کار یافت نشد یا دسترسی ندارید']);
            exit;
        }

        // موعد: برای کار دوره‌ای (continuous) دورهٔ بعدی، برای کار مقطعی
        // (periodic) موعد معمولی — همون منطق مشترک my-tasks.php/overview.php
        $holidays = getHolidaySet($db, $orgId);
        $enriched = enrichTaskDates($row, $db, $holidays, date('Y-m-d'));

        $stmt = $db->prepare("
            SELECT id, file_original_name AS name, file_path AS url, file_type, mime_type
            FROM task_attachments
            WHERE task_id = ?
            ORDER BY created_at DESC
            LIMIT 6
        ");
        $stmt->execute([$id]);
        $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($attachments as &$a) {
            $a['is_image'] = in_array(strtolower($a['file_type']), ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
        }
        unset($a);

        echo json_encode([
            'success'      => true,
            'type'         => 'task',
            'id'           => (int) $row['id'],
            'title'        => $row['title'],
            'status'       => $row['status'],
            'status_label' => TASK_STATUS_LABELS[$row['status']] ?? $row['status'],
            'assignee_name'=> trim($row['assignee_name'] ?? '') ?: null,
            'next_due_date'=> $enriched['next_due_date'],
            'detail_url'   => '/pages/task-detail.php?id=' . $row['id'],
            'attachments'  => $attachments,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── تیکت ──
    $stmt = $db->prepare("
        SELECT t.id, t.subject, t.organization_id, ts.name AS status, ts.label AS status_label
        FROM tickets t
        JOIN ticket_statuses ts ON t.status_id = ts.id
        WHERE t.id = ? AND t.deleted_at IS NULL AND (t.organization_id = ? OR ? = 1)
    ");
    $stmt->execute([$id, $orgId, $user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'تیکت یافت نشد یا دسترسی ندارید']);
        exit;
    }

    $stmt = $db->prepare("
        SELECT id, original_name AS name, mime_type
        FROM ticket_attachments
        WHERE ticket_id = ?
        ORDER BY created_at DESC
        LIMIT 6
    ");
    $stmt->execute([$id]);
    $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($attachments as &$a) {
        $a['is_image'] = strpos($a['mime_type'], 'image/') === 0;
        // فایل‌های تیکت پشت یک دانلود احرازهویت‌شده هستن (نه مسیر مستقیم)
        $a['url'] = '/api/tickets/download.php?id=' . $a['id'];
    }
    unset($a);

    // آخرین پیام عمومی (نه یادداشت داخلی پشتیبانی) — فقط ۱۰ کلمهٔ اول
    $stmt = $db->prepare("
        SELECT message FROM ticket_messages
        WHERE ticket_id = ? AND is_internal = 0
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $lastMessage = $stmt->fetchColumn();

    echo json_encode([
        'success'      => true,
        'type'         => 'ticket',
        'id'           => (int) $row['id'],
        'title'        => $row['subject'],
        'status'       => $row['status'],
        'status_label' => $row['status_label'] ?? $row['status'],
        'last_message' => $lastMessage ? firstWords($lastMessage, 10) : null,
        'detail_url'   => '/pages/ticket-detail.php?id=' . $row['id'],
        'attachments'  => $attachments,
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log('chat link-preview error: ' . $e->getMessage());
}
