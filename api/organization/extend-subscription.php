<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
header('Content-Type: application/json; charset=utf-8');

$allowed_admins  = [1];
$current_user_id = (int)($_SESSION['user_id'] ?? 0);

if (!in_array($current_user_id, $allowed_admins, true)) {
    http_response_code(403);
    echo json_encode(
        ['success' => false, 'message' => 'دسترسی غیرمجاز'],
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

$data   = json_decode(file_get_contents('php://input'), true);
$org_id = (int)($data['org_id'] ?? 0);
$months = (int)($data['months'] ?? 1);

if (!$org_id || $months < 1 || $months > 24) {
    echo json_encode(
        ['success' => false, 'message' => 'پارامترهای نامعتبر'],
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

try {
    // اگر اشتراک فعال دارد، از انتهای آن تمدید کن؛ اگر ندارد، از امروز.
    $stmt = $db->prepare("
        UPDATE subscriptions
        SET end_date = DATE_ADD(
              IF(end_date > CURDATE(), end_date, CURDATE()),
              INTERVAL ? MONTH
            ),
            is_active = 1
        WHERE organization_id = ?
          AND is_active = 1
        ORDER BY end_date DESC
        LIMIT 1
    ");
    $stmt->execute([$months, $org_id]);

    if ($stmt->rowCount() === 0) {
        // اشتراک فعالی نبود، یکی جدید بساز
        $db->prepare("
            INSERT INTO subscriptions
              (organization_id, plan_type, max_users, price, start_date, end_date, is_active)
            VALUES (?, 'monthly', 50, 0, CURDATE(), DATE_ADD(CURDATE(), INTERVAL ? MONTH), 1)
        ")->execute([$org_id, $months]);
    }

    echo json_encode([
        'success' => true,
        'message' => "اشتراک سازمان {$months} ماه تمدید شد",
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'خطا: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}