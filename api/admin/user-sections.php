<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  user-sections.php — خواندن واحدهای فعالیت یک کاربر
 *  محل: /api/admin/user-sections.php
 * ───────────────────────────────────────────────────────────────────
 *  فقط برای فرم ویرایش مدیریت کاربران.
 *  خروجی: { success, sections: ['warehouse', 'technical'], primary: 'warehouse' }
 * ═══════════════════════════════════════════════════════════════════
 */

header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/user-sections.php';

try {
    $user_id = requireAuth();
    $me = getUserInfo($user_id);
    if (!$me || !isset($me['id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'عدم احراز هویت'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // فقط مدیر/سوپروایزر یا سوپرادمین
    $role    = $me['role'] ?? 'employee';
    $isSuper = ((int) $me['id'] === 1);
    if (!$isSuper && !in_array($role, ['management', 'supervisor'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $target_id = (int) ($_GET['user_id'] ?? 0);
    if ($target_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسهٔ کاربر نامعتبر'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    // ── محدودیت سازمان: مدیر فقط کاربران سازمان خودش ──
    if (!$isSuper) {
        $chk = $db->prepare("SELECT organization_id FROM users WHERE id = ?");
        $chk->execute([$target_id]);
        $targetOrg = $chk->fetchColumn();
        if ((int) $targetOrg !== (int) ($me['organization_id'] ?? 0)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'این کاربر در سازمان شما نیست'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    $sections = us_getUserSections($db, $target_id);
    $primary  = us_getPrimarySection($db, $target_id);

    echo json_encode([
        'success'  => true,
        'sections' => $sections,
        'primary'  => $primary,
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('[' . basename(__FILE__) . '] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور'], JSON_UNESCAPED_UNICODE);
}
