<?php
// api/reports/generate.php - تولید گزارش روزانه
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'متد غیرمجاز']);
    exit;
}

try {
    $user_id = requireAuth();
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['unit']) || empty($input['date'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'واحد و تاریخ الزامی است']);
        exit;
    }
    
    $unit = $input['unit'];
    $date = $input['date'];
    
    $database = new Database();
    $db = $database->getConnection();
    
    // دریافت اطلاعات کاربر
    $stmt = $db->prepare("SELECT report_prefix, report_suffix FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    // دریافت کارهای روز
    $sql = "SELECT t.*, 
                   CASE 
                       WHEN t.status = 'completed' THEN 'انجام شد'
                       WHEN t.status = 'in_progress' THEN 'در حال انجام'
                       WHEN t.status = 'stopped' THEN 'متوقف شد'
                       WHEN t.status = 'delegated' THEN 'ارجاع شد'
                       ELSE t.status
                   END as status_text
            FROM tasks t
            WHERE t.assignee_id = ? 
            AND (t.activity_section = ? OR t.activity_section IS NULL)
            AND (
                (t.task_type = 'periodic' AND t.due_date = ?)
                OR 
                (t.task_type = 'continuous' AND t.start_date <= ?)
            )
            ORDER BY t.priority DESC, t.created_at ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$user_id, $unit, $date, $date]);
    $tasks = $stmt->fetchAll();
    
    // تولید متن گزارش
    $report_content = $user['report_prefix'] ? $user['report_prefix'] . "\n\n" : "";
    
    $report_content .= "گزارش کاری - تاریخ: " . date('Y/m/d', strtotime($date)) . "\n";
    $report_content .= "واحد: " . $unit . "\n\n";
    
    if (count($tasks) > 0) {
        $report_content .= "فعالیت‌های انجام شده:\n\n";
        
        foreach ($tasks as $index => $task) {
            $report_content .= ($index + 1) . ". " . $task['title'] . " - وضعیت: " . $task['status_text'] . "\n";
            if (!empty($task['description'])) {
                $report_content .= "   توضیحات: " . $task['description'] . "\n";
            }
        }
    } else {
        $report_content .= "هیچ کاری برای این تاریخ ثبت نشده است.\n";
    }
    
    $report_content .= "\n" . ($user['report_suffix'] ? $user['report_suffix'] : "");
    
    echo json_encode([
        'success' => true, 
        'content' => $report_content,
        'task_count' => count($tasks)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای داخلی سرور']);
}
?>