<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once '../../includes/TaskManager.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once '../../includes/OrganizationHelper.php';

// ایجاد اتصال به دیتابیس
$database = new Database();
$db = $database->getConnection();

header('Content-Type: application/json; charset=utf-8');

$subdomain = strtolower(trim($_GET['subdomain'] ?? ''));

if (!$subdomain || !preg_match('/^[a-z0-9\-]{3,30}$/', $subdomain)) {
    echo json_encode([
        'available' => false,
        'message'   => 'فرمت زیردامنه نامعتبر است'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// کلمات رزرو شده
$reserved = ['admin','api','www','mail','app','saas','panel','support','help','test'];
if (in_array($subdomain, $reserved)) {
    echo json_encode([
        'available' => false,
        'message'   => 'این زیردامنه رزرو شده است'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$helper    = new OrganizationHelper($db);
$available = $helper->isSubdomainAvailable($subdomain);

echo json_encode([
    'available' => $available,
    'message'   => $available ? 'این زیردامنه در دسترس است ✓' : 'این زیردامنه قبلاً ثبت شده است'
], JSON_UNESCAPED_UNICODE);
