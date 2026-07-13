<?php
// api/admin/routine-create.php - ایجاد روتین جدید
header('Content-Type: application/json; charset=utf-8');
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'متد غیرمجاز']);
    exit;
}

try {
    $user_id = requireAuth();
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['name']) || empty($input['steps'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'نام و مراحل الزامی است']);
        exit;
    }
    
    $database = new Database();
    $db = $database->getConnection();
    
    // بررسی دسترسی ادمین
    $currentUser = loadUserForPermissions($db, $user_id);
    requirePermission($currentUser, 'create_routine_template');
    
    $db->beginTransaction();
    
    try {
        // ایجاد روتین
        $insertRoutine = $db->prepare("
            INSERT INTO routine_workflows (name, description, is_active, created_by) 
            VALUES (?, ?, ?, ?)
        ");
        $insertRoutine->execute([
            $input['name'],
            $input['description'] ?? '',
            $input['is_active'] ? 1 : 0,
            $user_id
        ]);
        
        $routine_id = $db->lastInsertId();
        
        // ایجاد مراحل
        $insertStep = $db->prepare("
            INSERT INTO routine_steps 
            (routine_id, step_order, step_title, step_description, assigned_to_type, activity_section, estimated_days) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($input['steps'] as $step) {
            $insertStep->execute([
                $routine_id,
                $step['step_order'],
                $step['step_title'],
                $step['step_description'] ?? '',
                $step['assigned_to_type'] ?? 'unit',
                $step['activity_section'] ?? null,
                $step['estimated_days'] ?? 1
            ]);
        }
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'روتین با موفقیت ایجاد شد',
            'routine_id' => $routine_id
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("Create routine error: " . $e->getMessage());
}
?>