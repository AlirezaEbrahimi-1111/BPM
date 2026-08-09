<?php

header('Content-Type: application/json; charset=utf-8');
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/error_config.php';

$userId = requireAuth();   // شناسه کاربر لاگین‌شده

$input = json_decode(file_get_contents('php://input'), true);

$currentPw = trim($input['current_password'] ?? '');
$newPw     = trim($input['new_password']     ?? '');

// ── اعتبارسنجی ──────────────────────────────────────
if (!$currentPw || !$newPw) {
    echo json_encode(['success' => false, 'message' => 'همه فیلدها الزامی است']);
    exit;
}

$__auth = new Auth();
if (!$__auth->validatePassword($newPw)) {
    echo json_encode(['success' => false, 'message' => 'رمز عبور جدید باید حداقل ۸ کاراکتر و شامل حداقل یک حرف و یک عدد باشد']);
    exit;
}

// ── خواندن رمز فعلی از دیتابیس ──────────────────────
try {
    $database = new Database();
    $db       = $database->getConnection();

    $stmt = $db->prepare('SELECT password FROM users WHERE id = ? AND is_active = 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'کاربر یافت نشد']);
        exit;
    }

    // ── تأیید رمز فعلی ──────────────────────────────
    if (!password_verify($currentPw, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'رمز عبور فعلی اشتباه است']);
        exit;
    }

    // ── هش رمز جدید با bcrypt ────────────────────────
    $newHash = password_hash($newPw, PASSWORD_BCRYPT, ['cost' => 12]);

    $update = $db->prepare('UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?');
    $update->execute([$newHash, $userId]);

    echo json_encode(['success' => true, 'message' => 'رمز عبور با موفقیت تغییر کرد']);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'خطای پایگاه داده']);
}