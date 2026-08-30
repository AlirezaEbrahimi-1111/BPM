<?php
// api/admin/routine-steps.php - مدیریت مراحل روتین
header('Content-Type: application/json; charset=utf-8');
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';

try {
    $admin_id = requireAuth();

    $database = new Database();
    $db = $database->getConnection();

    // 🔒 سیستمِ روتینِ قدیمی چندسازمانی نیست (routine_templates ستونِ
    // organization_id ندارد) و از رابطِ کاربری هم فراخوانی نمی‌شود. برای
    // جلوگیری از IDORِ بین‌سازمانی، فقط سوپرادمین. (چکِ قبلی فقط
    // activity_section==='management' بود که هر واحدِ مدیریتِ هر سازمانی را می‌پذیرفت.)
    if (!in_array((int) $admin_id, getSuperAdminIds(), true)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
        exit;
    }
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        // لیست مراحل یک روتین
        $routine_id = $_GET['routine_id'] ?? null;
        
        if (empty($routine_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'شناسه روتین الزامی است']);
            exit;
        }
        
        $stmt = $db->prepare("
            SELECT rs.*,
                   u.first_name, u.last_name
            FROM routine_steps rs
            LEFT JOIN users u ON rs.target_user_id = u.id
            WHERE rs.routine_template_id = ?
            ORDER BY rs.step_order
        ");
        $stmt->execute([$routine_id]);
        $steps = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'steps' => $steps
        ]);
        
    } elseif ($method === 'POST') {
        // اضافه کردن مرحله جدید
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['routine_template_id']) || empty($input['step_title'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'داده‌های ناقص']);
            exit;
        }
        
        // پیدا کردن آخرین شماره مرحله
        $maxOrderStmt = $db->prepare("
            SELECT COALESCE(MAX(step_order), 0) as max_order 
            FROM routine_steps 
            WHERE routine_template_id = ?
        ");
        $maxOrderStmt->execute([$input['routine_template_id']]);
        $maxOrder = $maxOrderStmt->fetch()['max_order'];
        
        $insertStmt = $db->prepare("
            INSERT INTO routine_steps (
                routine_template_id, step_order, step_title, step_description,
                target_unit, target_user_id, estimated_duration, is_parallel
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insertStmt->execute([
            $input['routine_template_id'],
            $maxOrder + 1,
            $input['step_title'],
            $input['step_description'] ?? '',
            $input['target_unit'] ?? null,
            $input['target_user_id'] ?? null,
            $input['estimated_duration'] ?? 24,
            $input['is_parallel'] ?? 0
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'مرحله اضافه شد',
            'step_id' => $db->lastInsertId()
        ]);
        
    } elseif ($method === 'PUT') {
        // بروزرسانی مرحله
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'شناسه مرحله الزامی است']);
            exit;
        }
        
        $updateStmt = $db->prepare("
            UPDATE routine_steps 
            SET step_title = ?, step_description = ?, 
                target_unit = ?, target_user_id = ?, 
                estimated_duration = ?, is_parallel = ?
            WHERE id = ?
        ");
        $updateStmt->execute([
            $input['step_title'],
            $input['step_description'] ?? '',
            $input['target_unit'] ?? null,
            $input['target_user_id'] ?? null,
            $input['estimated_duration'] ?? 24,
            $input['is_parallel'] ?? 0,
            $input['id']
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'مرحله بروزرسانی شد'
        ]);
        
    } elseif ($method === 'DELETE') {
        // حذف مرحله
        $id = $_GET['id'] ?? null;
        
        if (empty($id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'شناسه مرحله الزامی است']);
            exit;
        }
        
        $deleteStmt = $db->prepare("DELETE FROM routine_steps WHERE id = ?");
        $deleteStmt->execute([$id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'مرحله حذف شد'
        ]);
    }
    
} catch (Exception $e) {
    error_log('[' . basename(__FILE__) . '] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'خطای سرور',
        'error' => 'internal_error'
    ]);
}
?>