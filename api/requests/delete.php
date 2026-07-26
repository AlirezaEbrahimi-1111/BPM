<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://bpm.computeryekta.com');
header('Access-Control-Allow-Methods: DELETE, POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

if (!in_array($_SERVER['REQUEST_METHOD'], ['DELETE', 'POST'])) {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'متد غیرمجاز']);
    exit;
}

try {
    $user_id = requireAuth();
    
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $request_id = $_GET['id'] ?? null;
        $request_type = $_GET['type'] ?? null;
    } else {
        $input = json_decode(file_get_contents('php://input'), true);
        $request_id = $input['request_id'] ?? null;
        $request_type = $input['request_type'] ?? null;
    }
    
    if (empty($request_id) || empty($request_type)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'اطلاعات کامل نیست']);
        exit;
    }
    
    $database = new Database();
    $db = $database->getConnection();
    
    $table_map = [
        'leave' => 'leave_requests',
        'pass' => 'pass_requests',
        'mission' => 'mission_requests',
        'forget' => 'forget_requests',
        'technical' => 'technical_issues'
    ];
    
    $table = $table_map[$request_type] ?? null;
    if (!$table) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'نوع درخواست نامعتبر']);
        exit;
    }
    
    // بررسی مالکیت و امکان حذف
    $stmt = $db->prepare("SELECT user_id, can_delete, created_at FROM $table WHERE id = ?");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch();
    
    if (!$request) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'درخواست یافت نشد']);
        exit;
    }
    
    if ($request['user_id'] != $user_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'شما مجاز به حذف این درخواست نیستید']);
        exit;
    }
    
    // بررسی امکان حذف برای پاس (12 ساعت)
    if ($request_type == 'pass') {
        $created = new DateTime($request['created_at']);
        $now = new DateTime();
        $diff = $now->diff($created);
        $hours_diff = ($diff->days * 24) + $diff->h;
        
        if ($hours_diff > 12) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'فقط تا 12 ساعت بعد از ثبت می‌توانید پاس را حذف کنید']);
            exit;
        }
    } else {
        if (!$request['can_delete']) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'این درخواست قابل حذف نیست']);
            exit;
        }
    }
    
    // حذف درخواست
    $stmt = $db->prepare("DELETE FROM $table WHERE id = ?");
    if ($stmt->execute([$request_id])) {
        echo json_encode(['success' => true, 'message' => 'درخواست با موفقیت حذف شد']);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'خطا در حذف درخواست']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای داخلی سرور']);
}
?>