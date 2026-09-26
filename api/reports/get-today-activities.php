<?php
/**
 * API: get-today-activities.php
 * دریافت فعالیت‌های امروز + کارهای معوقه
 */

header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com', 'https://bpm.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/working-days-helper.php';

    $user_id = requireAuth();
    $user = getUserInfo($user_id);

    if (!$user || !isset($user['id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'کاربر یافت نشد'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
    
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'فرمت تاریخ نامعتبر'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        throw new Exception('اتصال به پایگاه داده ناموفق');
    }

    // =====================================
    // 1. فعالیت‌های امروز
    // =====================================
    $sql = "
        SELECT 
            th.id, th.task_id, th.from_user_id, th.to_user_id,
            th.action, th.notes, th.created_at,
            t.title as task_title, t.description as task_description,
            t.status as task_status, t.priority as task_priority,
            t.due_date as task_due_date, t.task_type, t.activity_section,
            CONCAT(COALESCE(fu.first_name,''),' ',COALESCE(fu.last_name,'')) as from_user_name,
            CONCAT(COALESCE(tu.first_name,''),' ',COALESCE(tu.last_name,'')) as to_user_name
        FROM task_history th
        LEFT JOIN tasks t ON th.task_id = t.id
        LEFT JOIN users fu ON th.from_user_id = fu.id AND fu.is_active = 1 AND fu.is_deleted = 0
        LEFT JOIN users tu ON th.to_user_id = tu.id AND tu.is_active = 1 AND tu.is_deleted = 0
        WHERE DATE(th.created_at) = ? AND th.from_user_id = ?
          AND (t.is_deleted = 0 OR t.is_deleted IS NULL)
        ORDER BY th.created_at DESC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([$date, $user_id]);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // گروه‌بندی
    $grouped = [
        'created' => [], 'assigned' => [], 'completed' => [],
        'approved' => [], 'rejected' => [], 'delegated' => [],
        'updated' => [], 'pending_approval' => [], 'stopped' => []
    ];
    foreach ($activities as $a) {
        if (isset($grouped[$a['action']])) {
            $grouped[$a['action']][] = $a;
        }
    }

    // =====================================
    // 2. کارهای معوقه
    // =====================================
    // 🔒 دو مدل تأخیر: روتین/فرآیندی (is_workflow_task=1) ساعتی، بقیه
    // روز کاری — و موعد مؤثر (نه due_date خام) چون تمدید موعد فقط
    // deadline رو آپدیت می‌کنه (هم‌راستا با api/reports/top-delayed-users.php)
    // 🔒 effective_deadline (با ساعت) هم اضافه شد — قبلا شاخه‌ی ساعتی فقط
    // با «is_workflow_task && !empty(deadline)» انتخاب می‌شد، با این فرض که
    // deadline برای کار روتین همیشه پره. این فرض همیشه درست نبود: چندتا
    // کار روتین واقعی پیدا شدن که deadline‌شون خالی بود ولی due_date پر
    // بود (و ماه‌ها گذشته) — این‌ها به‌جای شاخه‌ی ساعتی، بی‌سروصدا می‌رفتن
    // شاخه‌ی روز کاری، برخلاف قاعده‌ی «روتین همیشه ساعتی». الان شاخه‌ی
    // ساعتی از effective_deadline (GREATEST، با فرض ۲۳:۵۹:۵۹ برای
    // due_date/original_deadline بدون ساعت) استفاده می‌کنه، هم‌راستا با
    // فیکس مشابه در top-delayed-users.php و includes/task-dates-helper.php.
    // 🔒 CAST(...AS DATETIME) لازمه — بدون این، به‌خاطر ناهماهنگی
    // collation due_date/deadline/original_deadline (همون مشکل قدیمی
    // اسکیما که effective_due پایین‌تر هم با CAST AS DATE ازش دور می‌زنه)،
    // خطای «Illegal mix of collations» (1267) می‌ده — روی دیتای واقعی
    // تست محلی گرفت.
    $overdue_sql = "
        SELECT
            t.id, t.title, t.priority, t.task_type, t.is_workflow_task,
            t.due_date, t.deadline, t.original_deadline,
            GREATEST(
                COALESCE(CAST(t.due_date AS DATE), CAST('1000-01-01' AS DATE)),
                COALESCE(CAST(t.deadline AS DATE), CAST('1000-01-01' AS DATE)),
                COALESCE(CAST(t.original_deadline AS DATE), CAST('1000-01-01' AS DATE))
            ) AS effective_due,
            GREATEST(
                COALESCE(CAST(CONCAT(t.due_date, ' 23:59:59') AS DATETIME), CAST('1000-01-01 00:00:00' AS DATETIME)),
                COALESCE(CAST(t.deadline AS DATETIME), CAST('1000-01-01 00:00:00' AS DATETIME)),
                COALESCE(CAST(t.original_deadline AS DATETIME), CAST('1000-01-01 00:00:00' AS DATETIME))
            ) AS effective_deadline,
            CONCAT(COALESCE(c.first_name,''),' ',COALESCE(c.last_name,'')) as creator_name
        FROM tasks t
        LEFT JOIN users c ON t.creator_id = c.id AND c.is_active = 1 AND c.is_deleted = 0
        WHERE t.assignee_id = ? AND t.is_deleted = 0
          AND t.status NOT IN ('completed','approved','stopped','rejected')
          AND (t.due_date IS NOT NULL OR t.deadline IS NOT NULL OR t.original_deadline IS NOT NULL)
        HAVING effective_due > '1000-01-01' AND effective_due < CURDATE()
        ORDER BY effective_due ASC
        LIMIT 15
    ";
    $stmt = $db->prepare($overdue_sql);
    $stmt->execute([$user_id]);
    $overdue_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $holidays = getHolidaySet($db, $user['organization_id'] ?? null);
    $today_str = date('Y-m-d');
    $now_str   = date('Y-m-d H:i:s');
    foreach ($overdue_tasks as &$ot) {
        if (!empty($ot['is_workflow_task'])) {
            $ot['unit']          = 'hours';
            $ot['hours_overdue'] = calcHourDelay($ot['effective_deadline'], $now_str);
            $ot['days_overdue']  = null;
        } else {
            $ot['unit']          = 'days';
            $ot['days_overdue']  = calcPeriodicDelayWorkingDays(substr($ot['effective_due'], 0, 10), $today_str, $holidays);
            $ot['hours_overdue'] = null;
        }
    }
    unset($ot);

    // =====================================
    // 3. آمار
    // =====================================
    $summary = [
        'total_activities' => count($activities),
        'tasks_created' => count($grouped['created']),
        'tasks_completed' => count($grouped['completed']),
        'tasks_approved' => count($grouped['approved']),
        'tasks_rejected' => count($grouped['rejected']),
        'tasks_delegated' => count($grouped['delegated']),
        'tasks_assigned' => count($grouped['assigned']),
        'notes_added' => count($grouped['updated']),
        'sent_for_approval' => count($grouped['pending_approval']),
        'tasks_stopped' => count($grouped['stopped']),
        'overdue_count' => count($overdue_tasks)
    ];

    // =====================================
    // 4. اطلاعات کاربر + مدیر
    // =====================================
    $userInfo = [
        'id' => $user['id'],
        'first_name' => $user['first_name'] ?? '',
        'last_name' => $user['last_name'] ?? '',
        'full_name' => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')),
        'activity_unit' => $user['activity_unit'] ?? '',
        'official_code' => $user['official_code'] ?? '',
        'manager_id' => $user['manager_id'] ?? null,
        'manager_code' => $user['manager_code'] ?? '',
        'manager_name' => $user['manager_name'] ?? '',
        'manager_lastname' => $user['manager_lastname'] ?? '',
        'report_prefix' => $user['report_prefix'] ?? '',
        'report_suffix' => $user['report_suffix'] ?? ''
    ];

    echo json_encode([
        'success' => true,
        'data' => [
            'date' => $date,
            'user' => $userInfo,
            'activities' => $activities,
            'grouped_activities' => $grouped,
            'summary' => $summary,
            'overdue_tasks' => $overdue_tasks
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور'], JSON_UNESCAPED_UNICODE);
}
?>