<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

try {
    $user_id = requireAuth();
    
    $query = $_GET['q'] ?? '';
    $type = $_GET['type'] ?? 'all'; // all, tasks, reports
    
    if (empty($query) || strlen($query) < 2) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'حداقل 2 کاراکتر برای جستجو لازم است']);
        exit;
    }
    
    $database = new Database();
    $db = $database->getConnection();
    
    $results = [];
    $search_term = "%{$query}%";
    
    // جستجو در کارها
    if ($type === 'all' || $type === 'tasks') {
        $sql = "SELECT t.id, t.title, t.description, t.status, t.priority, t.due_date, t.created_at,
                       'task' as type,
                       CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as creator_name
                FROM tasks t 
                LEFT JOIN users u ON t.creator_id = u.id
                WHERE (t.assignee_id = ? OR t.creator_id = ?)
                AND (t.title LIKE ? OR t.description LIKE ?)
                ORDER BY t.updated_at DESC
                LIMIT 25";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$user_id, $user_id, $search_term, $search_term]);
        $tasks = $stmt->fetchAll();
        
        foreach ($tasks as $task) {
            $snippet = '';
            if (!empty($task['description'])) {
                $pos = stripos($task['description'], $query);
                if ($pos !== false) {
                    $start = max(0, $pos - 50);
                    $snippet = substr($task['description'], $start, 100);
                    if ($start > 0) $snippet = '...' . $snippet;
                    if (strlen($task['description']) > $start + 100) $snippet = $snippet . '...';
                } else {
                    $snippet = substr($task['description'], 0, 100);
                    if (strlen($task['description']) > 100) $snippet = $snippet . '...';
                }
            } else {
                $snippet = 'بدون توضیحات';
            }
            
            $results[] = [
                'id' => $task['id'],
                'type' => 'task',
                'title' => $task['title'],
                'snippet' => $snippet,
                'status' => $task['status'],
                'priority' => $task['priority'],
                'date' => $task['due_date'] ?: $task['created_at'],
                'creator_name' => $task['creator_name']
            ];
        }
    }
    
    // جستجو در گزارش‌ها
    if ($type === 'all' || $type === 'reports') {
        $sql = "SELECT r.id, r.unique_code, r.content, r.activity_unit, r.report_date, r.created_at,
                       'report' as type
                FROM reports r
                WHERE r.user_id = ?
                AND (r.content LIKE ? OR r.unique_code LIKE ?)
                ORDER BY r.created_at DESC
                LIMIT 25";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$user_id, $search_term, $search_term]);
        $reports = $stmt->fetchAll();
        
        foreach ($reports as $report) {
            $content = $report['content'];
            $pos = stripos($content, $query);
            
            if ($pos !== false) {
                $start = max(0, $pos - 50);
                $snippet = substr($content, $start, 150);
                if ($start > 0) $snippet = '...' . $snippet;
                if (strlen($content) > $start + 150) $snippet = $snippet . '...';
            } else {
                $snippet = substr($content, 0, 150);
                if (strlen($content) > 150) $snippet = $snippet . '...';
            }
            
            $results[] = [
                'id' => $report['id'],
                'type' => 'report',
                'title' => 'گزارش ' . $report['unique_code'],
                'snippet' => $snippet,
                'unit' => $report['activity_unit'],
                'date' => $report['report_date'],
                'created_at' => $report['created_at']
            ];
        }
    }
    
    // مرتب‌سازی نتایج بر اساس تاریخ
    usort($results, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
    
    echo json_encode(['success' => true, 'results' => $results, 'query' => $query, 'type' => $type]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای داخلی سرور']);
    error_log("Global search error: " . $e->getMessage());
}
?>