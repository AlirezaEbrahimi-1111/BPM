<?php
ob_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://bpm.computeryekta.com');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

try {
    $auth    = new Auth();
    $user_id = $auth->getUserFromToken();
    if (!$user_id) {
        ob_end_clean();
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'احراز هویت الزامی است']);
        exit;
    }

    $database = new Database();
    $db       = $database->getConnection();

    // تعطیلات ۶ ماه آینده کافیه
    $stmt = $db->prepare("
        SELECT holiday_date, title 
        FROM holidays 
        WHERE holiday_date >= CURDATE() - INTERVAL 1 MONTH
          AND holiday_date <= CURDATE() + INTERVAL 6 MONTH
        ORDER BY holiday_date ASC
    ");
    $stmt->execute();
    $holidays = $stmt->fetchAll(PDO::FETCH_ASSOC);

    ob_clean();
    echo json_encode(['success' => true, 'holidays' => $holidays]);

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}