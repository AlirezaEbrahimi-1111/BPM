<?php
header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';

// ✅ بعد
$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$user_id = $auth->getUserFromToken();
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'عدم احراز هویت'], JSON_UNESCAPED_UNICODE);
    exit;
}
// 🔒 هم‌راستا با قاعدهٔ api/tickets/detail.php (canManageTargetUser) — قبلاً
// اینجا فقط بر اساسِ سازمان فیلتر می‌شد، یعنی هر کارمندِ عادی لیستِ کلِ
// تیکت‌هایِ سازمان (نه فقط خودش) رو می‌دید؛ فقط بازکردنِ تکیِ تیکتِ کسِ
// دیگه بلاک می‌شد. الان همون قاعده اینجا هم اعمال می‌شه.
$me = loadUserForPermissions($db, $user_id);
if (!$me) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'کاربر یافت نشد'], JSON_UNESCAPED_UNICODE);
    exit;
}

$page     = max(1, intval($_GET['page'] ?? 1));
$limit    = min(200, max(1, intval($_GET['limit'] ?? 20)));
$status   = trim($_GET['status']   ?? '');
$priority = trim($_GET['priority'] ?? '');
$category = trim($_GET['category'] ?? '');
$search   = trim($_GET['search']   ?? '');
// awaiting=1 → فقط تیکت‌هایی که «توپ در زمینِ کاربرِ جاری است»: آخرین پیام از
// طرفِ مقابل بوده. برای کاربرِ عادی = آخرین پیام از پشتیبان (id ۱ یا ۱۹)؛
// برای خودِ پشتیبان = آخرین پیام از کاربری غیرِ پشتیبان.
$awaitingOnly = ($_GET['awaiting'] ?? '') === '1';
$offset   = ($page - 1) * $limit;

try {
    // فیلترِ پایه (سازمان + حذف‌نشده) — برایِ کارت‌هایِ آماری استفاده می‌شه، چون
    // اون کارت‌ها باید همیشه شکستِ کلیِ وضعیت‌ها رو نشون بدن، نه فقط بینِ
    // نتایجِ فیلترِ فعلی (وگرنه با زدنِ یک کارت، بقیه‌ی کارت‌ها صفر می‌شدن)
    $baseWhere  = [];
    $baseParams = [];
    $baseWhere[] = 't.deleted_at IS NULL';

    if (!isSuperAdmin($me)) {
        $role = $me['role'] ?? 'employee';
        if (in_array($role, ['supervisor', 'admin'], true)) {
            // سرپرست/ادمین: کلِ تیکت‌هایِ سازمانِ خودش
            $baseWhere[]  = 't.organization_id = ?';
            $baseParams[] = $me['organization_id'];
        } elseif ($role === 'manager') {
            // مدیر: تیکتِ خودش + زیرمجموعه‌اش
            $subIds   = getSubordinateIds($db, (int) $user_id);
            $subIds[] = (int) $user_id;
            $ph = implode(',', array_fill(0, count($subIds), '?'));
            $baseWhere[] = "t.created_by IN ($ph)";
            $baseParams  = array_merge($baseParams, $subIds);
        } else {
            // کارمندِ عادی: فقط تیکتِ خودش
            $baseWhere[]  = 't.created_by = ?';
            $baseParams[] = $user_id;
        }
    }
    $baseWhereSQL = 'WHERE ' . implode(' AND ', $baseWhere);

    // فیلترِ کامل (پایه + جستجو/وضعیت/اولویت/دسته) — برایِ خودِ لیستِ تیکت‌ها
    $where  = $baseWhere;
    $params = $baseParams;

    if ($status !== '') {
        $where[]  = 'ts.name = ?';
        $params[] = $status;
    }
    if ($priority !== '') {
        $where[]  = 'tp.name = ?';
        $params[] = $priority;
    }
    if ($category !== '') {
        $where[]  = 't.category_id = ?';
        $params[] = intval($category);
    }
    if ($search !== '') {
        $where[]  = '(t.subject LIKE ? OR t.ticket_number LIKE ?)';
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }

    // «پشتیبان» = دو کاربرِ سیستمی (getSuperAdminIds). مبنایِ منطقِ «توپ در
    // زمینِ کیست».
    $supportIds  = getSuperAdminIds();                              // [1, 19]
    $supportList = implode(',', array_map('intval', $supportIds));  // "1,19"
    $iAmSupport  = in_array((int) $user_id, $supportIds, true);

    // زیرکوئریِ «نویسنده‌ی آخرین پیامِ (حذف‌نشده‌ی) این تیکت»
    $lastAuthorSub = "(SELECT tm2.user_id FROM ticket_messages tm2
                        WHERE tm2.ticket_id = t.id AND tm2.deleted_at IS NULL
                        ORDER BY tm2.created_at DESC, tm2.id DESC LIMIT 1)";

    if ($awaitingOnly) {
        if ($iAmSupport) {
            // پشتیبان: هر تیکتی در سیستم که آخرین پیامش از یک کاربرِ غیرِپشتیبان است
            $where[] = "$lastAuthorSub IS NOT NULL AND $lastAuthorSub NOT IN ($supportList)";
        } else {
            // بقیه (کاربرِ عادی/سرپرست): فقط تیکت‌های خودِ کاربر که آخرین پیامشان از پشتیبان است
            $where[]  = '(t.created_by = ? OR t.assigned_to = ?)';
            $params[] = $user_id;
            $params[] = $user_id;
            $where[]  = "$lastAuthorSub IN ($supportList)";
        }
    }

    $whereSQL = 'WHERE ' . implode(' AND ', $where);

    // ── آمار (بر اساسِ فیلترِ پایه، نه فیلترِ فعلی) ──
    $statsSQL = "
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN ts.name = 'open' THEN 1 ELSE 0 END) as open_count,
            SUM(CASE WHEN ts.name = 'in_progress' THEN 1 ELSE 0 END) as in_progress_count,
            SUM(CASE WHEN ts.name = 'waiting_reply' THEN 1 ELSE 0 END) as waiting_reply_count,
            SUM(CASE WHEN ts.name = 'resolved' THEN 1 ELSE 0 END) as resolved_count,
            SUM(CASE WHEN ts.name = 'closed' THEN 1 ELSE 0 END) as closed_count
        FROM tickets t
        LEFT JOIN ticket_statuses ts ON t.status_id = ts.id
        LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
        {$baseWhereSQL}
    ";
    $stmtStats = $db->prepare($statsSQL);
    $stmtStats->execute($baseParams);
    $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

    // ── شمارش کل ──
    $total = intval($stats['total'] ?? 0);

    // «پیامِ دیده‌نشده»: تعدادِ پیام‌هایِ کاربرِ دیگر که بعد از آخرین‌باری که
    // کاربرِ جاری این تیکت را دید ثبت شده‌اند. اگر جدولِ ردیابی هنوز مایگریت
    // نشده باشد، ستون صفر برمی‌گردد (بدونِ شکستنِ لیست).
    $hasReads = false;
    try {
        $hasReads = (bool) $db->query("SHOW TABLES LIKE 'ticket_message_reads'")->fetchColumn();
    } catch (Throwable $e) {
    }
    $unseenSelect = $hasReads
        ? "(SELECT COUNT(*) FROM ticket_messages tmu
              WHERE tmu.ticket_id = t.id
                AND tmu.deleted_at IS NULL
                AND tmu.user_id <> ?
                AND tmu.created_at > COALESCE(
                    (SELECT tmr.last_read_at FROM ticket_message_reads tmr
                       WHERE tmr.ticket_id = t.id AND tmr.user_id = ?),
                    '1000-01-01 00:00:00')
           ) as unseen_count"
        : "0 as unseen_count";

    // ── لیست تیکت‌ها ──
    $listSQL = "
        SELECT
            t.id,
            t.ticket_number,
            t.subject,
            t.status_id,
            t.priority_id,
            t.category_id,
            t.created_by,
            t.assigned_to,
            t.created_at,
            t.updated_at,
            ts.label   as status_label,
            ts.name    as status,
            ts.color   as status_color,
            tp.label   as priority_label,
            tp.name    as priority,
            tp.color   as priority_color,
            tc.name    as category_name,
            CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')) as creator_name,
            (SELECT COUNT(*) FROM ticket_messages tm WHERE tm.ticket_id = t.id) as message_count,
            (SELECT COUNT(*) FROM ticket_attachments ta WHERE ta.ticket_id = t.id) as attachment_count,
            {$unseenSelect},
            {$lastAuthorSub} as last_msg_user_id
        FROM tickets t
        LEFT JOIN ticket_statuses ts ON t.status_id = ts.id
        LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
        LEFT JOIN ticket_categories tc ON t.category_id = tc.id
        LEFT JOIN users u ON t.created_by = u.id
        {$whereSQL}
        ORDER BY t.created_at DESC
        LIMIT {$limit} OFFSET {$offset}
    ";
    // دو placeholderِ subqueryِ unseen در متنِ SQL قبل از شرط‌های WHERE هستند،
    // پس باید ابتدایِ آرایهٔ پارامترها بیایند.
    $listParams = $hasReads ? array_merge([$user_id, $user_id], $params) : $params;
    $stmtList = $db->prepare($listSQL);
    $stmtList->execute($listParams);
    $tickets = $stmtList->fetchAll(PDO::FETCH_ASSOC);

    // awaiting_you — «توپ در زمینِ کاربرِ جاری است؟» (بدونِ وابستگی به
    // ticket_message_reads؛ فقط بر اساسِ نویسنده‌ی آخرین پیام)
    foreach ($tickets as &$tk) {
        $lu = ($tk['last_msg_user_id'] ?? null) !== null ? (int) $tk['last_msg_user_id'] : null;
        if ($lu === null) {
            $tk['awaiting_you'] = 0;
        } elseif ($iAmSupport) {
            $tk['awaiting_you'] = in_array($lu, $supportIds, true) ? 0 : 1;
        } else {
            $tk['awaiting_you'] = in_array($lu, $supportIds, true) ? 1 : 0;
        }
    }
    unset($tk);

    echo json_encode([
        'success'    => true,
        'tickets'    => $tickets,
        'stats'      => $stats,
        'pagination' => [
            'page'        => $page,
            'limit'       => $limit,
            'total'       => $total,
            'total_pages' => $total > 0 ? ceil($total / $limit) : 1
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('Ticket list error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطا در پردازش درخواست'], JSON_UNESCAPED_UNICODE);
}