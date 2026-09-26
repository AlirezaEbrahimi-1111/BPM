<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) $user_id = $auth->getUserFromToken();
if (!$user_id && isset($_COOKIE['auth_token'])) $user_id = $auth->validateToken($_COOKIE['auth_token']);

if (!$user_id) {
    header('Location: ../index.php');
    exit;
}

// dashboard.php از این به بعد فقط یک رودایرکت نازکه؛ خود dashboard-manager.php
// کاربران غیر manager/supervisor رو به dashboard-user.php هدایت می‌کنه، پس
// نیازی به تکرار منطق تشخیص نقش این‌جا نیست
header('Location: dashboard-manager.php');
exit;
