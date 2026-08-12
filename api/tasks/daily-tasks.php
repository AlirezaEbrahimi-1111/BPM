<?php
// api/tasks/daily-tasks.php - دریافت کارهای روز برای واحد خاص
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

try {
    $user_id = requireAuth();
    
    if (empty($_GET['unit']) || empty($_GET['date'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'واحد و تاریخ الزامی است']);
        exit;
    }
    
    $unit = $_GET['unit'];
    $date = $_GET['date'];
    
    $database = new Database();
    $db = $database->getConnection();
    
    // دریافت کارهای این کاربر برای این واحد و تاریخ
    $sql = "SELECT t.*
            FROM tasks t
            WHERE t.assignee_id = ? 
            AND (t.assigned_unit = ? OR t.assigned_unit IS NULL)
            AND (
                (t.task_type = 'periodic' AND t.due_date = ?)
                OR 
                (t.task_type = 'continuous' AND t.start_date <= ?)
            )
            ORDER BY t.priority DESC, t.created_at ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$user_id, $unit, $date, $date]);
    $tasks = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'tasks' => $tasks]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای داخلی سرور']);
    error_log("Get daily tasks error: " . $e->getMessage());
}
?>