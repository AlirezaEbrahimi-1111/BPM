<?php
/**
 * quick-search.php
 * جستجوی سبک بین تسک‌های قابل‌دسترسِ کاربر فعلی (سازنده یا مسئول)،
 * برای استفاده در پیوستِ یک تسک به تیکتِ پشتیبانی (create-ticket.php).
 * برخلاف جستجوی صفحاتِ کارها، اینجا فقط شناسه/عنوان لازم است — سبک و سریع.
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
        echo json_encode(['success' => true, 'tasks' => []]);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("
        SELECT t.id, t.title, t.status, t.task_type
        FROM tasks t
        WHERE t.is_deleted = 0
          AND (t.creator_id = ? OR t.assignee_id = ?)
          AND (t.title LIKE ? OR t.id = ?)
        ORDER BY t.created_at DESC
        LIMIT 15
    ");
    $idMatch = ctype_digit($q) ? (int)$q : 0;
    $stmt->execute([$user_id, $user_id, '%' . $q . '%', $idMatch]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'tasks' => $tasks], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log('quick-search error: ' . $e->getMessage());
}
