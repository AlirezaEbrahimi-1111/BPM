<?php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://bpm.computeryekta.com');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
require_once __DIR__ . '/path.php';   // یا اگر path.php لازم نیست، این خط را حذف کن

require_once  $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once  $_SERVER['DOCUMENT_ROOT'] .  '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/error_config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/cors.php';
try {
    $database = new Database();
    $db = $database->getConnection();
    
    $auth = new Auth($db);
    $user_id = $auth->getUserFromToken();

    if (!$user_id) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized'
        ]);
        exit;
    }

    // دریافت اطلاعات کاربر
    $stmt = $db->prepare("
        SELECT 
            id, 
            username, 
            phone, 
            first_name, 
            last_name, 
            email,
            activity_section,
            can_create_routine,
            is_active,
            created_at,
            updated_at,
            role
        FROM users 
        WHERE id = ? AND is_active = 1
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'User not found'
        ]);
        exit;
    }

    // Return user data
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'user' => $user
    ]);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Profile error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Internal Server Error'
    ]);
}
?>