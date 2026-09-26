<?php
/**
 * quick-search.php
 * جستجوی سبک بین تیکت‌های قابل‌دسترس کاربر فعلی (سازمان خودش، یا id=1 همه‌ی سازمان‌ها)،
 * برای استفاده در پیوند یک تیکت به یک پیام چت (chat.php).
 * هم‌الگو با api/tasks/quick-search.php.
 */

header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

try {
    $user_id = requireAuth();

    $q = trim($_GET['q'] ?? '');
    // نرمال‌سازی ارقام فارسی/عربی به لاتین (برای جستجوی شناسه با رقم فارسی)
    $q = strtr($q, [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ]);

    if ($q === '') {
        echo json_encode(['success' => true, 'tickets' => []]);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("SELECT organization_id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $orgId = (int) ($stmt->fetchColumn() ?: 0);

    $idMatch = ctype_digit($q) ? (int) $q : 0;

    $stmt = $db->prepare("
        SELECT t.id, t.subject, t.ticket_number, ts.name AS status
        FROM tickets t
        JOIN ticket_statuses ts ON t.status_id = ts.id
        WHERE t.deleted_at IS NULL
          AND (t.organization_id = ? OR ? = 1)
          AND (t.subject LIKE ? OR t.ticket_number LIKE ? OR t.id = ?)
        ORDER BY t.created_at DESC
        LIMIT 15
    ");
    $stmt->execute([$orgId, $user_id, '%' . $q . '%', '%' . $q . '%', $idMatch]);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'tickets' => $tickets], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log('tickets quick-search error: ' . $e->getMessage());
}
