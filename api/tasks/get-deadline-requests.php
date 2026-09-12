<?php
// ==================================================
// api/tasks/get-deadline-requests.php
// دریافت درخواست‌های تمدید موعد - نسخه نهایی اصلاح شده
// ==================================================

header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/JalaliHelper.php';

try {
    $user_id = requireAuth();

    if (empty($_GET['task_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه کار الزامی است'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $task_id = (int)$_GET['task_id'];

    $database = new Database();
    $db = $database->getConnection();

    error_log("🔍 User $user_id requesting deadline requests for task $task_id");

    // ✅ دو حالت:
    // 1. کاربر current_approver است (برای نمایش modal بررسی)
    // 2. کاربر assignee است (برای نمایش ساعت شنی)
    
    $stmt = $db->prepare("
        SELECT dr.*,
               u1.first_name as requester_first_name,
               u1.last_name as requester_last_name,
               t.title as task_title,
               t.deadline as current_deadline,
               t.assignee_id
        FROM deadline_requests dr
        LEFT JOIN users u1 ON dr.requested_by = u1.id AND u1.is_active = 1
        JOIN tasks t ON dr.task_id = t.id
        WHERE dr.task_id = ? 
        AND dr.status = 'pending'
        AND (
            dr.current_approver_id = ?     -- کاربر approver است
            OR t.assignee_id = ?            -- کاربر assignee است
        )
        ORDER BY dr.created_at DESC
    ");
    $stmt->execute([$task_id, $user_id, $user_id]);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    error_log("📊 Found " . count($requests) . " pending requests for user $user_id");

    // تبدیل تاریخ‌ها به شمسی
    foreach ($requests as &$request) {
        $request['requester_name'] = $request['requester_first_name'] . ' ' . $request['requester_last_name'];
        $request['requested_new_deadline_jalali'] = JalaliHelper::formatJalaliDate($request['requested_new_deadline']);
        $request['current_deadline_jalali'] = JalaliHelper::formatJalaliDate($request['current_deadline']);
        
        // ✅ مشخص کردن نقش کاربر
        $request['is_approver'] = ($request['current_approver_id'] == $user_id);
        $request['is_assignee'] = ($request['assignee_id'] == $user_id);
    }

    echo json_encode([
        'success' => true,
        'requests' => $requests
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("❌ get-deadline-requests error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'خطای سرور'
    ], JSON_UNESCAPED_UNICODE);
}
?>