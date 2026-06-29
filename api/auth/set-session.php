<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
header('Content-Type: application/json; charset=utf-8');



require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

try {
    $database = new Database();
    $db = $database->getConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection error']);
    exit;
}

$auth = new Auth($db);

$user_id = $auth->getUserFromToken();

if ($user_id) {
    // ✅ Set کردن SESSION
    $_SESSION['user_id'] = $user_id;
    
    // دریافت اطلاعات کاربر
    $stmt = $db->prepare("SELECT username, first_name, last_name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $_SESSION['username'] = $user['username'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'];
    }
    
    echo json_encode(['success' => true, 'user_id' => $user_id]);
} else {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid token']);
}
?>