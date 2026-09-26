<?php
/**
 * API: موجودی فعلی سهمیهٔ مرخصی استحقاقی + تاریخچه
 * GET ?user_id=123  (اگه ندید، خود کاربر درخواست‌دهنده)
 *
 * دسترسی: خود کاربر، یا مدیر/سرپرستی که canManageTargetUser بهش اجازه بده
 */

header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/leave-balance-helper.php';

try {
    $user_id = requireAuth();

    $database = new Database();
    $db = $database->getConnection();

    $me = loadUserForPermissions($db, $user_id);
    $targetUserId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : $user_id;

    if (!canManageTargetUser($db, $me, $targetUserId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
        exit;
    }

    ensureMonthlyLeaveAccrual($db, $targetUserId);
    $balanceMinutes = getLeaveBalance($db, $targetUserId);
    $dailyWorkMinutes = getUserDailyWorkMinutes($db, $targetUserId);

    echo json_encode([
        'success'          => true,
        'user_id'          => $targetUserId,
        'balance_minutes'  => $balanceMinutes,
        'balance_formatted'=> formatMinutesHM($balanceMinutes),
        'balance_color'    => getLeaveBalanceColor($balanceMinutes, $dailyWorkMinutes),
        'history'          => getLeaveBalanceHistory($db, $targetUserId, 30),
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log('leave-balance error: ' . $e->getMessage());
}
