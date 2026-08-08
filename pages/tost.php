<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$__user_id = $_SESSION['user_id'] ?? $auth->getUserFromToken();
$__me = $__user_id ? loadUserForPermissions($db, (int) $__user_id) : null;
if (!isSuperAdmin($__me)) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']));
}

header('Content-Type: application/json');
echo json_encode([
    'HTTP_AUTHORIZATION' => $_SERVER['HTTP_AUTHORIZATION'] ?? 'ندارد',
    'REDIRECT_HTTP_AUTHORIZATION' => $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? 'ندارد',
    'all_headers' => function_exists('getallheaders') ? getallheaders() : 'getallheaders وجود ندارد',
    'user_id' => $auth->getUserFromToken()
]);