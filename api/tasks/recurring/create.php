<?php
// api/tasks/recurring/create.php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://bpm.computeryekta.com');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../../config/database.php';
require_once '../../../includes/auth.php';
require_once '../../../includes/TaskManager.php';
require_once '../../../includes/middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'متد غیرمجاز']);
    exit;
}

try {
    $user_id = requireAuth();
    $input = json_decode(file_get_contents('php://input'), true);
    
    // اعتبارسنجی ورودی‌ها
    if (empty($input['title'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'عنوان کار الزامی است']);
        exit;
    }
    
    if (!in_array($input['recurring_pattern'], ['daily', 'weekly', 'monthly'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'الگوی تکرار نامعتبر است']);
        exit;
    }
    
    if (empty($input['next_due_date'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'موعد انجام الزامی است']);
        exit;
    }
    
    $database = new Database();
    $db = $database->getConnection();
    $taskManager = new TaskManager($db);
    
    $result = $taskManager->createRecurringTask($input, $user_id);
    
    if ($result['success']) {
        http_response_code(201);
    } else {
        http_response_code(400);
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای داخلی سرور']);
    error_log("Create recurring task error: " . $e->getMessage());
}
?>

// api/tasks/recurring/complete.php
<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://bpm.computeryekta.com');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../../config/database.php';
require_once '../../../includes/auth.php';
require_once '../../../includes/TaskManager.php';
require_once '../../../includes/middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'متد غیرمجاز']);
    exit;
}

try {
    $user_id = requireAuth();
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['task_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه کار الزامی است']);
        exit;
    }
    
    $database = new Database();
    $db = $database->getConnection();
    $taskManager = new TaskManager($db);
    
    $result = $taskManager->completeRecurringTask(
        $input['task_id'],
        $user_id,
        $input['notes'] ?? ''
    );
    
    if ($result['success']) {
        http_response_code(200);
    } else {
        http_response_code(400);
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای داخلی سرور']);
    error_log("Complete recurring task error: " . $e->getMessage());
}
?>

// api/tasks/recurring/list.php
<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://bpm.computeryekta.com');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../../config/database.php';
require_once '../../../includes/auth.php';
require_once '../../../includes/TaskManager.php';
require_once '../../../includes/middleware.php';

try {
    $user_id = requireAuth();
    
    $database = new Database();
    $db = $database->getConnection();
    $taskManager = new TaskManager($db);
    
    $tasks = $taskManager->getUserRecurringTasks($user_id);
    $stats = $taskManager->getRecurringTasksStats($user_id);
    
    echo json_encode([
        'success' => true, 
        'tasks' => $tasks,
        'stats' => $stats
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای داخلی سرور']);
    error_log("Get recurring tasks error: " . $e->getMessage());
}
?>

// api/tasks/recurring/due.php
<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://bpm.computeryekta.com');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../../config/database.php';
require_once '../../../includes/auth.php';
require_once '../../../includes/TaskManager.php';
require_once '../../../includes/middleware.php';

try {
    $user_id = requireAuth();
    
    $database = new Database();
    $db = $database->getConnection();
    $taskManager = new TaskManager($db);
    
    // فقط کارهای سررسید کاربر فعلی
    $due_tasks = $taskManager->getDueRecurringTasks($user_id);
    
    echo json_encode([
        'success' => true, 
        'tasks' => $due_tasks,
        'count' => count($due_tasks)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای داخلی سرور']);
    error_log("Get due recurring tasks error: " . $e->getMessage());
}
?>

// api/tasks/recurring/pause.php
<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://bpm.computeryekta.com');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../../config/database.php';
require_once '../../../includes/auth.php';
require_once '../../../includes/TaskManager.php';
require_once '../../../includes/middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'متد غیرمجاز']);
    exit;
}

try {
    $user_id = requireAuth();
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['task_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه کار الزامی است']);
        exit;
    }
    
    $database = new Database();
    $db = $database->getConnection();
    $taskManager = new TaskManager($db);
    
    $result = $taskManager->pauseRecurringTask(
        $input['task_id'],
        $user_id,
        $input['notes'] ?? 'کار متداول موقتاً متوقف شد'
    );
    
    if ($result['success']) {
        http_response_code(200);
    } else {
        http_response_code(400);
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای داخلی سرور']);
    error_log("Pause recurring task error: " . $e->getMessage());
}
?>

// api/tasks/recurring/resume.php
<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://bpm.computeryekta.com');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../../config/database.php';
require_once '../../../includes/auth.php';
require_once '../../../includes/TaskManager.php';
require_once '../../../includes/middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'متد غیرمجاز']);
    exit;
}

try {
    $user_id = requireAuth();
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['task_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه کار الزامی است']);
        exit;
    }
    
    $database = new Database();
    $db = $database->getConnection();
    $taskManager = new TaskManager($db);
    
    $result = $taskManager->resumeRecurringTask($input['task_id'], $user_id);
    
    if ($result['success']) {
        http_response_code(200);
    } else {
        http_response_code(400);
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای داخلی سرور']);
    error_log("Resume recurring task error: " . $e->getMessage());
}
?>

// api/tasks/recurring/can-complete.php
<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://bpm.computeryekta.com');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../../config/database.php';
require_once '../../../includes/auth.php';
require_once '../../../includes/middleware.php';

try {
    $user_id = requireAuth();
    
    if (empty($_GET['task_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه کار الزامی است']);
        exit;
    }
    
    $task_id = $_GET['task_id'];
    
    $database = new Database();
    $db = $database->getConnection();
    
    // دریافت اطلاعات کار
    $stmt = $db->prepare("
        SELECT next_due_date, can_complete_early, early_completion_hours, title
        FROM tasks 
        WHERE id = ? AND assignee_id = ? AND is_recurring = 1
    ");
    $stmt->execute([$task_id, $user_id]);
    $task = $stmt->fetch();
    
    if (!$task) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'کار یافت نشد']);
        exit;
    }
    
    $current_time = new DateTime();
    $due_date = new DateTime($task['next_due_date']);
    
    // اگر موعد رسیده باشد
    if ($current_time >= $due_date) {
        echo json_encode([
            'success' => true,
            'can_complete' => true,
            'message' => 'کار قابل تکمیل است'
        ]);
        exit;
    }
    
    // بررسی امکان تکمیل زودهنگام
    if ($task['can_complete_early']) {
        $earliest_allowed = clone $due_date;
        $earliest_allowed->sub(new DateInterval('PT' . $task['early_completion_hours'] . 'H'));
        
        if ($current_time >= $earliest_allowed) {
            echo json_encode([
                'success' => true,
                'can_complete' => true,
                'message' => 'تکمیل زودهنگام مجاز است'
            ]);
            exit;
        }
        
        // محاسبه زمان باقی‌مانده
        $diff = $earliest_allowed->diff($current_time);
        $hours_remaining = ($diff->days * 24) + $diff->h;
        
        echo json_encode([
            'success' => true,
            'can_complete' => false,
            'message' => "این کار {$hours_remaining} ساعت دیگر قابل تکمیل است"
        ]);
        exit;
    }
    
    // محاسبه روزهای باقی‌مانده
    $diff = $due_date->diff($current_time);
    $days_remaining = $diff->days + 1;
    
    echo json_encode([
        'success' => true,
        'can_complete' => false,
        'message' => "این کار {$days_remaining} روز دیگر قابل تکمیل است"
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای داخلی سرور']);
    error_log("Check can complete recurring task error: " . $e->getMessage());
}
?>