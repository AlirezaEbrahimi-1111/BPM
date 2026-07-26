<?php
// api/reports/attendance-summary.php

ob_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://bpm.computeryekta.com');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once '../AttendanceManager.php';
require_once '../JalaliDate.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'متد غیرمجاز']);
    exit;
}

try {
    $managerId = requireAuth();
    
    // بررسی اینکه کاربر مدیر است
    $database = new Database();
    $db = $database->getConnection();
    
    $stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$managerId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || !in_array($user['role'], ['manager', 'superior', 'admin'])) {
        http_response_code(403);
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'دسترسی رد شد']);
        exit;
    }

    // دریافت تمام کارمندان تحت نظارت
    $stmt = $db->prepare("
        SELECT id, first_name, last_name
        FROM users
        WHERE manager_id = ? AND role = 'employee'
        ORDER BY first_name, last_name
    ");
    $stmt->execute([$managerId]);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $attendanceManager = new AttendanceManager($db);
    $summaryData = [];

    foreach ($employees as $employee) {
        $summary = $attendanceManager->getAttendanceSummary($employee['id']);
        $summaryData[] = [
            'user_id' => $employee['id'],
            'full_name' => $employee['first_name'] . ' ' . $employee['last_name'],
            'summary' => $summary
        ];
    }

    http_response_code(200);
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'data' => $summaryData
    ]);

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'خطای داخلی سرور'
    ]);
}

?>