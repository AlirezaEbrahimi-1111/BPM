<?php
/**
 * API: دریافت جزئیات یک درخواست
 * مسیر: /attendance_system/api/requests/get.php
 * متد: GET
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Tehran');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

try {
    $database = new Database();
    $db = $database->getConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection error']);
    exit;
}
$auth = new Auth($db);

// احراز هویت
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) $user_id = $auth->getUserFromToken();
if (!$user_id && isset($_COOKIE['auth_token'])) $user_id = $auth->validateToken($_COOKIE['auth_token']);

if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'لطفاً وارد شوید']);
    exit;
}

$request_id = $_GET['id'] ?? null;
$request_type = $_GET['type'] ?? null;

if (!$request_id || !$request_type) {
    echo json_encode(['success' => false, 'message' => 'اطلاعات ناقص است']);
    exit;
}

// تعیین جدول
$tables = [
    'mission' => 'mission_requests',
    'leave' => 'leave_requests',
    'pass' => 'pass_requests',
    'forget' => 'forget_requests',
    'technical' => 'technical_issues'
];

if (!isset($tables[$request_type])) {
    echo json_encode(['success' => false, 'message' => 'نوع درخواست نامعتبر است']);
    exit;
}

$table = $tables[$request_type];

// ✅ تابع ساخت timeline تأییدات
function getApprovalTimeline($db, $request, $type) {
    $timeline = [];
    
    if ($type === 'leave') {
        // جانشین
        if ($request['substitute_approval'] !== 'pending') {
            $approver_name = '';
            if ($request['substitute_id']) {
                $stmt = $db->prepare("SELECT CONCAT(first_name, ' ', last_name) as name FROM users WHERE id = ?");
                $stmt->execute([$request['substitute_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                $approver_name = $user['name'] ?? '';
            }
            
            $timeline[] = [
                'role' => 'جانشین',
                'approver_name' => $approver_name,
                'status' => $request['substitute_approval'],
                'date' => $request['substitute_date'],
                'notes' => $request['substitute_notes']
            ];
        }
        
        // مدیر
        if ($request['manager_approval'] !== 'pending') {
            $approver_name = '';
            if ($request['manager_id']) {
                $stmt = $db->prepare("SELECT CONCAT(first_name, ' ', last_name) as name FROM users WHERE id = ?");
                $stmt->execute([$request['manager_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                $approver_name = $user['name'] ?? '';
            }
            
            $timeline[] = [
                'role' => 'مدیر',
                'approver_name' => $approver_name,
                'status' => $request['manager_approval'],
                'date' => $request['manager_date'],
                'notes' => $request['manager_notes']
            ];
        }
        
        // مسئول
        if ($request['supervisor_approval'] !== 'pending') {
            $approver_name = '';
            if ($request['supervisor_id']) {
                $stmt = $db->prepare("SELECT CONCAT(first_name, ' ', last_name) as name FROM users WHERE id = ?");
                $stmt->execute([$request['supervisor_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                $approver_name = $user['name'] ?? '';
            }
            
            $timeline[] = [
                'role' => 'مسئول',
                'approver_name' => $approver_name,
                'status' => $request['supervisor_approval'],
                'date' => $request['supervisor_date'],
                'notes' => $request['supervisor_notes']
            ];
        }
    }
    
    elseif ($type === 'mission' || $type === 'forget') {
        // مدیر
        if ($request['manager_approval'] !== 'pending') {
            $approver_name = '';
            if ($request['manager_id']) {
                $stmt = $db->prepare("SELECT CONCAT(first_name, ' ', last_name) as name FROM users WHERE id = ?");
                $stmt->execute([$request['manager_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                $approver_name = $user['name'] ?? '';
            }
            
            $timeline[] = [
                'role' => 'مدیر',
                'approver_name' => $approver_name,
                'status' => $request['manager_approval'],
                'date' => $request['manager_date'],
                'notes' => $request['manager_notes']
            ];
        }
        
        // مسئول
        if ($request['supervisor_approval'] !== 'pending') {
            $approver_name = '';
            if ($request['supervisor_id']) {
                $stmt = $db->prepare("SELECT CONCAT(first_name, ' ', last_name) as name FROM users WHERE id = ?");
                $stmt->execute([$request['supervisor_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                $approver_name = $user['name'] ?? '';
            }
            
            $timeline[] = [
                'role' => 'مسئول',
                'approver_name' => $approver_name,
                'status' => $request['supervisor_approval'],
                'date' => $request['supervisor_date'],
                'notes' => $request['supervisor_notes']
            ];
        }
    }
    
    elseif ($type === 'technical') {
        if ($request['status'] !== 'pending') {
            $approver_name = '';
            if ($request['admin_id']) {
                $stmt = $db->prepare("SELECT CONCAT(first_name, ' ', last_name) as name FROM users WHERE id = ?");
                $stmt->execute([$request['admin_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                $approver_name = $user['name'] ?? '';
            }
            
            $timeline[] = [
                'role' => 'مسئول',
                'approver_name' => $approver_name,
                'status' => $request['status'],
                'date' => $request['admin_date'],
                'notes' => $request['admin_notes']
            ];
        }
    }
    
    return $timeline;
}

// ✅ تابع تعیین وضعیت کامل
function getFullStatus($request, $type) {
    if ($request['status'] === 'approved') return 'تأیید شده';
    if ($request['status'] === 'rejected') return 'رد شده';
    
    if ($type === 'leave') {
        if ($request['substitute_approval'] === 'pending') 
            return 'در انتظار تأیید جانشین';
        if ($request['manager_approval'] === 'pending') 
            return 'در انتظار تأیید مدیر';
        if ($request['supervisor_approval'] === 'pending') 
            return 'در انتظار تأیید مسئول';
    }
    
    if ($type === 'mission' || $type === 'forget') {
        if ($request['manager_approval'] === 'pending') 
            return 'در انتظار تأیید مدیر';
        if ($request['supervisor_approval'] === 'pending') 
            return 'در انتظار تأیید مسئول';
    }
    
    if ($type === 'technical') {
        return 'در انتظار تأیید مسئول';
    }
    
    return 'در انتظار';
}

// ✅ تابع دریافت دلیل رد
function getRejectNotes($request, $type) {
    if ($type === 'leave') {
        if ($request['substitute_approval'] === 'rejected') return $request['substitute_notes'];
        if ($request['manager_approval'] === 'rejected') return $request['manager_notes'];
        if ($request['supervisor_approval'] === 'rejected') return $request['supervisor_notes'];
    }
    elseif ($type === 'mission' || $type === 'forget') {
        if ($request['manager_approval'] === 'rejected') return $request['manager_notes'];
        if ($request['supervisor_approval'] === 'rejected') return $request['supervisor_notes'];
    }
    elseif ($type === 'technical') {
        if ($request['status'] === 'rejected') return $request['admin_notes'];
    }
    return null;
}

try {
    // دریافت اطلاعات درخواست
    $stmt = $db->prepare("SELECT * FROM {$table} WHERE id = ? AND user_id = ?");
    $stmt->execute([$request_id, $user_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        echo json_encode(['success' => false, 'message' => 'درخواست یافت نشد']);
        exit;
    }

    // فرمت‌دهی داده‌ها برای فرم
    $formatted = [
        'id' => $request['id'],
        'type' => $request_type,
        'status' => $request['status'],
        'full_status' => getFullStatus($request, $request_type),
        'reject_notes' => getRejectNotes($request, $request_type),
        'created_at' => $request['created_at']
    ];

    if ($request_type === 'mission') {
        $formatted['start_date'] = substr($request['start_date'], 0, 10);
        $formatted['start_time'] = substr($request['start_date'], 11, 5);
        $formatted['end_date'] = substr($request['end_date'], 0, 10);
        $formatted['end_time'] = substr($request['end_date'], 11, 5);
        $formatted['description'] = $request['purpose'];
    }
    elseif ($request_type === 'leave') {
        $formatted['start_date'] = $request['start_date'];
        $formatted['start_time'] = substr($request['start_time'], 0, 5);
        $formatted['end_date'] = $request['end_date'];
        $formatted['end_time'] = substr($request['end_time'], 0, 5);
        $formatted['reason'] = $request['reason'];
    }
    elseif ($request_type === 'pass') {
        $formatted['pass_date'] = $request['pass_date'];
        $formatted['start_time'] = substr($request['start_time'], 0, 5);
        $formatted['end_time'] = substr($request['end_time'], 0, 5);
        $formatted['reason'] = $request['reason'];
    }
    elseif ($request_type === 'forget') {
        $formatted['date'] = substr($request['start_date'], 0, 10);
        $formatted['start_time'] = substr($request['start_date'], 11, 5);
        $formatted['end_time'] = substr($request['end_date'], 11, 5);
        $formatted['description'] = $request['description'];
    }
    elseif ($request_type === 'technical') {
        $formatted['date'] = substr($request['start_date'], 0, 10);
        $formatted['start_time'] = substr($request['start_time'], 0, 5);
        $formatted['end_time'] = substr($request['end_time'], 0, 5);
        $formatted['description'] = $request['description'];
    }

    // بررسی امکان ویرایش و حذف
    $can_edit = false;
    $can_delete = false;

    if ($request_type === 'pass') {
        $created_at = new DateTime($request['created_at']);
        $now = new DateTime();
        $diff = $now->getTimestamp() - $created_at->getTimestamp();
        $hours_passed = $diff / 3600;
        $can_edit = $can_delete = ($hours_passed <= 24);
    } else {
        $has_approval = false;
        if ($request_type === 'leave') {
            if ($request['substitute_approval'] === 'approved') $has_approval = true;
            if ($request['manager_approval'] === 'approved') $has_approval = true;
            if ($request['supervisor_approval'] === 'approved') $has_approval = true;
        } elseif ($request_type === 'mission' || $request_type === 'forget') {
            if ($request['manager_approval'] === 'approved') $has_approval = true;
            if ($request['supervisor_approval'] === 'approved') $has_approval = true;
        } elseif ($request_type === 'technical') {
            if ($request['status'] === 'approved') $has_approval = true;
        }
        $can_edit = $can_delete = !$has_approval;
    }

    $formatted['can_edit'] = $can_edit;
    $formatted['can_delete'] = $can_delete;
    
    // ✅ اضافه کردن timeline
    $formatted['timeline'] = getApprovalTimeline($db, $request, $request_type);

    echo json_encode(['success' => true, 'data' => $formatted], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("Get request error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطا در دریافت اطلاعات']);
}
?>