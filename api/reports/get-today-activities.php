<?php
/**
 * API: get-today-activities.php
 * دریافت فعالیت‌های امروز + کارهای معوقه
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://bpm.computeryekta.com');
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
        LEFT JOIN users fu ON th.from_user_id = fu.id
        LEFT JOIN users tu ON th.to_user_id = tu.id
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
    $overdue_sql = "
        SELECT 
            t.id, t.title, t.priority, t.due_date, t.task_type,
            DATEDIFF(CURDATE(), t.due_date) as days_overdue,
            CONCAT(COALESCE(c.first_name,''),' ',COALESCE(c.last_name,'')) as creator_name
        FROM tasks t
        LEFT JOIN users c ON t.creator_id = c.id
        WHERE t.assignee_id = ? AND t.is_deleted = 0
          AND t.status NOT IN ('completed','approved')
          AND t.due_date < CURDATE()
        ORDER BY t.due_date ASC
        LIMIT 15
    ";
    $stmt = $db->prepare($overdue_sql);
    $stmt->execute([$user_id]);
    $overdue_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    error_log("API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور'], JSON_UNESCAPED_UNICODE);
}
?>