<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

header('Content-Type: application/json');
echo json_encode([
    'HTTP_AUTHORIZATION' => $_SERVER['HTTP_AUTHORIZATION'] ?? 'ندارد',
    'REDIRECT_HTTP_AUTHORIZATION' => $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? 'ندارد',
    'all_headers' => function_exists('getallheaders') ? getallheaders() : 'getallheaders وجود ندارد',
    'user_id' => $auth->getUserFromToken()
]);