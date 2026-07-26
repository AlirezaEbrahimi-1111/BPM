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
    
    $report_id = null;
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $report_id = $_GET['id'] ?? null;
    } else {
        $input = json_decode(file_get_contents('php://input'), true);
        $report_id = $input['report_id'] ?? null;
    }
    
    if (empty($report_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه گزارش الزامی است']);
        exit;
    }
    
    $database = new Database();
    $db = $database->getConnection();
    
    // بررسی مالکیت گزارش
    $stmt = $db->prepare("SELECT user_id FROM reports WHERE id = ?");
    $stmt->execute([$report_id]);
    $report = $stmt->fetch();
    
    if (!$report) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'گزارش یافت نشد']);
        exit;
    }
    
    if ($report['user_id'] != $user_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'شما مجاز به حذف این گزارش نیستید']);
        exit;
    }
    
    // حذف گزارش
    $stmt = $db->prepare("DELETE FROM reports WHERE id = ?");
    
    if ($stmt->execute([$report_id])) {
        echo json_encode(['success' => true, 'message' => 'گزارش با موفقیت حذف شد']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'خطا در حذف گزارش']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای داخلی سرور']);
}
?>