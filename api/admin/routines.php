<?php
// api/admin/routines.php - مدیریت روتین‌ها (CRUD)
header('Content-Type: application/json; charset=utf-8');
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';

try {
    $admin_id = requireAuth();

    $database = new Database();
    $db = $database->getConnection();

    // 🔒 سیستم روتین قدیمی چندسازمانی نیست (routine_templates ستون
    // organization_id ندارد) و از رابط کاربری فراخوانی نمی‌شود. فقط سوپرادمین
    // تا IDOR بین‌سازمانی رخ ندهد. (چک قبلی activity_section==='management' بود.)
    if (!in_array((int) $admin_id, getSuperAdminIds(), true)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
        exit;
    }
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        // لیست روتین‌ها
        $sql = "SELECT rt.*, 
                       COUNT(rs.id) as steps_count,
                       u.first_name as creator_name, u.last_name as creator_lastname
                FROM routine_templates rt
                LEFT JOIN routine_steps rs ON rt.id = rs.routine_template_id
                LEFT JOIN users u ON rt.created_by = u.id
                GROUP BY rt.id
                ORDER BY rt.created_at DESC";
        
        $stmt = $db->query($sql);
        $routines = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'routines' => $routines
        ]);
        
    } elseif ($method === 'POST') {
        // ایجاد روتین جدید
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['name'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'نام روتین الزامی است']);
            exit;
        }
        
        $insertStmt = $db->prepare("
            INSERT INTO routine_templates (name, description, created_by)
            VALUES (?, ?, ?)
        ");
        $insertStmt->execute([
            $input['name'],
            $input['description'] ?? '',
            $admin_id
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'روتین با موفقیت ایجاد شد',
            'routine_id' => $db->lastInsertId()
        ]);
        
    } elseif ($method === 'PUT') {
        // بروزرسانی روتین
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'شناسه روتین الزامی است']);
            exit;
        }
        
        $updateStmt = $db->prepare("
            UPDATE routine_templates 
            SET name = ?, description = ?, is_active = ?
            WHERE id = ?
        ");
        $updateStmt->execute([
            $input['name'],
            $input['description'] ?? '',
            $input['is_active'] ?? 1,
            $input['id']
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'روتین بروزرسانی شد'
        ]);
        
    } elseif ($method === 'DELETE') {
        // حذف روتین
        $id = $_GET['id'] ?? null;
        
        if (empty($id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'شناسه روتین الزامی است']);
            exit;
        }
        
        $deleteStmt = $db->prepare("DELETE FROM routine_templates WHERE id = ?");
        $deleteStmt->execute([$id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'روتین حذف شد'
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
