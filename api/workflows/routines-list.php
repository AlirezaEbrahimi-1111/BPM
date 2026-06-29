<?php
// api/workflows/routines-list.php
// لیست روتین‌های تعریف‌شده سازمان (برای dropdown فیلتر)
header('Content-Type: application/json; charset=utf-8');
error_reporting(0);
ini_set('display_errors', 0);

try {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

    $database = new Database();
    $db = $database->getConnection();

    $user_id = requireAuth();
    $user    = getUserInfo($user_id);
    $org_id  = $user['organization_id'];

    // فقط روتین‌هایی که حداقل یک instance فعال در این سازمان دارند
    $stmt = $db->prepare("
        SELECT DISTINCT
            wt.id,
            wt.name
        FROM workflow_templates wt
        INNER JOIN workflow_instances wi ON wi.template_id = wt.id
        WHERE wt.organization_id = :org_id_wt
          AND wi.organization_id = :org_id_wi
          AND wt.is_active = 1
        ORDER BY wt.name ASC
    ");
    $stmt->execute([
        'org_id_wt' => $org_id,
        'org_id_wi' => $org_id
    ]);
    $routines = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'  => true,
        'routines' => $routines
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
