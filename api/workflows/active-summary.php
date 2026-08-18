<?php

/**
 * ═══════════════════════════════════════════════════════════════════
 *  API خلاصه‌ی فرآیندهای جاری (برای داشبورد مدیریت)
 * ───────────────────────────────────────────────────────────────────
 *  خروجی: لیست روتین‌ها (قالب‌ها) به‌همراه تعداد نمونه‌های فعال هر کدام.
 *  «فعال» یعنی وضعیت in_progress یا delayed.
 *  فقط روتین‌های سازمانِ کاربر جاری را برمی‌گرداند.
 * ═══════════════════════════════════════════════════════════════════
 */

header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/cors.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/user-sections.php';

try {
    // ۱) احراز هویت
    $user_id = requireAuth();

    $database = new Database();
    $db = $database->getConnection();

    // ۲) سازمان کاربر جاری را پیدا کن
    $orgStmt = $db->prepare("SELECT organization_id FROM users WHERE id = ? AND is_deleted = 0");
    $orgStmt->execute([$user_id]);
    $org_id = $orgStmt->fetchColumn();

    if (!$org_id) {
        echo json_encode(['success' => false, 'message' => 'سازمان کاربر یافت نشد'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 🆕 scope=mine → فقط روتین‌هایی که مرحلهٔ فعلاً فعالشان به یکی از
    // واحدهای کاربر جاری تعلق دارد (نه کلِ سازمان)
    $scope = (($_GET['scope'] ?? 'all') === 'mine') ? 'mine' : 'all';

    $params = [$org_id];
    $sectionFilterSql = '';
    if ($scope === 'mine') {
        $sections = us_getUserSections($db, $user_id);
        $ph = us_placeholders($sections);
        $sectionFilterSql = "
               AND EXISTS (
                   SELECT 1
                   FROM workflow_instance_steps wis
                   JOIN workflow_steps ws2 ON ws2.id = wis.step_id
                   WHERE wis.instance_id = wi.id
                     AND wis.status IN ('active', 'pending', 'delayed')
                     AND ws2.activity_section IN ($ph)
               )";
        $params = array_merge($params, $sections);
    }

    // ۳) روتین‌ها را با تعداد نمونه‌های فعالشان بگیر
    //    JOIN بین قالب‌ها و نمونه‌های فعال، گروه‌بندی بر اساس قالب
    $sql = "SELECT
                wt.id           AS template_id,
                wt.name         AS template_name,
                COUNT(wi.id)    AS active_count
            FROM workflow_templates wt
            INNER JOIN workflow_instances wi
                    ON wi.template_id = wt.id
                   AND wi.is_deleted = 0
                   AND wi.status IN ('in_progress', 'delayed')
            WHERE wt.organization_id = ?
                  $sectionFilterSql
            GROUP BY wt.id, wt.name
            HAVING active_count > 0
            ORDER BY active_count DESC, wt.name ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ۴) تبدیل نوع عددها (تا در JSON عدد باشند نه رشته)
    $result = array_map(function ($r) {
        return [
            'template_id'   => (int) $r['template_id'],
            'template_name' => $r['template_name'],
            'active_count'  => (int) $r['active_count'],
        ];
    }, $rows);

    echo json_encode([
        'success'   => true,
        'routines'  => $result,
        'total'     => count($result)
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور'], JSON_UNESCAPED_UNICODE);
    error_log("workflows/active-summary error: " . $e->getMessage());
}
