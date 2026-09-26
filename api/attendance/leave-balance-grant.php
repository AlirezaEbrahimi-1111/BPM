<?php
/**
 * API: اعطای سهمیهٔ تشویقی مرخصی/پاس به یک کارمند (فقط مدیر/سرپرست)
 * POST { user_id, amount, note? }   — amount بر‌حسب دقیقه
 *
 * amount می‌تونه منفی هم باشه (اصلاح دستی/کسر) — ولی خود کارمند
 * نمی‌تونه برای خودش ثبت کنه، فقط مدیر/سرپرست بالادستش
 */

header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/leave-balance-helper.php';

try {
    $user_id = requireAuth();

    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $targetUserId = (int) ($input['user_id'] ?? 0);
    $amount       = isset($input['amount']) ? (int) $input['amount'] : 0; // دقیقه
    $note         = trim($input['note'] ?? '') ?: 'سهمیهٔ تشویقی';

    if (!$targetUserId || !$amount) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'پارامترهای نامعتبر']);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    $me = loadUserForPermissions($db, $user_id);

    // خود کاربر نمی‌تونه برای خودش ثبت کنه — فقط مدیر/سرپرست واقعی
    if ($targetUserId === $user_id || !canManageTargetUser($db, $me, $targetUserId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
        exit;
    }

    ensureMonthlyLeaveAccrual($db, $targetUserId);

    $stmt = $db->prepare("
        INSERT INTO leave_balance_transactions (user_id, type, amount, granted_by, note)
        VALUES (?, 'bonus_grant', ?, ?, ?)
    ");
    $stmt->execute([$targetUserId, $amount, $user_id, $note]);

    $balanceMinutes = getLeaveBalance($db, $targetUserId);
    echo json_encode([
        'success'           => true,
        'message'           => 'سهمیه ثبت شد',
        'balance_minutes'   => $balanceMinutes,
        'balance_formatted' => formatMinutesHM($balanceMinutes),
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log('leave-balance-grant error: ' . $e->getMessage());
}
