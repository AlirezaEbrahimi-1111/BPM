<?php

header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/error_config.php';

try {
    $user_id = requireAuth();
    
    // دریافت داده‌ها
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['instance_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه نمونه کار روتین الزامی است']);
        exit;
    }
    
    if (empty($input['steps']) || !is_array($input['steps'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'مراحل باید به صورت آرایه ارسال شوند']);
        exit;
    }
    
    $instance_id = intval($input['instance_id']);
    $steps = $input['steps'];
    
    $database = new Database();
    $db = $database->getConnection();
    
    // بررسی دسترسی کاربر به این نمونه کار روتین
    $stmt = $db->prepare("
        SELECT id, created_by 
        FROM workflow_instances 
        WHERE id = ?
    ");
    $stmt->execute([$instance_id]);
    $instance = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$instance) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'نمونه کار روتین یافت نشد']);
        exit;
    }
    
    // فقط سازنده کار روتین می‌تواند توضیحات را اضافه کند
    if ($instance['created_by'] != $user_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'شما اجازه تغییر توضیحات این کار را ندارید']);
        exit;
    }
    
    // بروزرسانی توضیحات مراحل
    $successCount = 0;
    foreach ($steps as $step) {
        if (empty($step['step_id']) || !isset($step['description'])) {
            continue;
        }
        
        $step_id = intval($step['step_id']);
        $description = trim($step['description']);
        
        // 🆕 ذخیره توضیح روی همان اجرا (instance) — مستقل از قالب
        $stmt = $db->prepare("
            UPDATE workflow_instance_steps
            SET step_description = ?
            WHERE instance_id = ? AND step_id = ?
        ");

        if ($stmt->execute([$description, $instance_id, $step_id])) {
            $successCount++;
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => "توضیحات {$successCount} مرحله با موفقیت ذخیره شد",
        'updated_count' => $successCount
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("Save step descriptions error: " . $e->getMessage());
}