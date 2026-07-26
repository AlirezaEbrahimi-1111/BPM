<?php
header('Content-Type: application/json; charset=utf-8');
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'متد غیرمجاز']);
    exit;
}

try {
    $admin_id = requireAuth();
    
    if ($admin_id != 1) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'فقط ادمین دسترسی دارد']);
        exit;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['name']) || empty($input['steps'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'نام و مراحل الزامی است']);
        exit;
    }
    
    $database = new Database();
    $db = $database->getConnection();
    
    $db->beginTransaction();
    
    // ایجاد روتین
    $sql = "INSERT INTO routine_templates (name, description, created_by) VALUES (?, ?, ?)";
    $stmt = $db->prepare($sql);
    $stmt->execute([$input['name'], $input['description'] ?? '', $admin_id]);
    $routine_id = $db->lastInsertId();
    
    // ایجاد مراحل
    $sql = "INSERT INTO routine_steps (routine_id, step_order, title, description, activity_section, duration_days) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $db->prepare($sql);
    
    foreach ($input['steps'] as $step) {
        $stmt->execute([
            $routine_id,
            $step['order'],
            $step['title'],
            $step['description'] ?? '',
            $step['unit'],
            $step['duration']
        ]);
    }
    
    $db->commit();
    
    echo json_encode(['success' => true, 'routine_id' => $routine_id]);
    
} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
}
?>