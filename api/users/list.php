<?php
header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com', 'https://bpm.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/cors.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

try {
    $user_id = requireAuth();

    $database = new Database();
    $db = $database->getConnection();

    // دریافت role و unit کاربر فعلی
    $stmt = $db->prepare("
        SELECT role, activity_unit, organization_id
        FROM users
        WHERE id = ?
    ");
    $stmt->execute([$user_id]);
    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$currentUser) {
        throw new Exception("User not found");
    }

    $role = $currentUser['role'];
    $unit = $currentUser['activity_unit'];
    $org_id = $currentUser['organization_id'];

    // اگر supervisor باشد → همه کاربران
    // 🔒 همچنین اگر واحدِ خودِ کاربر «all» باشه → همه کاربران. «all» یک
    // مقدارِ ویژه‌ست به‌معنیِ «همهٔ واحدها» (دقیقا همون‌جوری که
    // api/organization/my-org-data.php هم برچسبش می‌زنه «همه واحدها»)، نه
    // اسمِ یه واحدِ واقعی — پس نباید وابسته به این باشه که جدولِ
    // unit_visibility برایِ هر واحدِ تازه‌ساخته‌شده هم یه ردیفِ
    // «all → آن واحد» داشته باشه. قبلا این‌جا wildcard بودنِ «all» در نظر
    // گرفته نمی‌شد و طبقِ همون جدول (که فقط یک ردیفِ all→all داره) رفتار
    // می‌کرد؛ یعنی کاربرِ واحد «all» فقط کاربرانِ دیگری با واحدِ دقیقا
    // «all» رو می‌دید، نه همه رو.
    if ($role === 'supervisor' || $unit === 'all') {

        $sql = "SELECT id, first_name, last_name, phone, activity_section, activity_unit,
                       CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) as full_name
                FROM users 
                WHERE is_active = 1 AND organization_id = ? AND is_deleted = 0
                ORDER BY first_name, last_name";

        $stmt = $db->prepare($sql);
        $stmt->execute([ $org_id]);
    } else {

        // کاربران مجاز بر اساس unit_visibility
        $sql = "SELECT u.id, u.first_name, u.last_name, u.phone, u.activity_section, u.activity_unit,
                       CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as full_name
                FROM users u
                JOIN unit_visibility v ON v.unit_to = u.activity_unit
                WHERE v.unit_from = ?
                  AND u.is_active = 1
                  AND u.is_deleted = 0
                  AND u.organization_id = ?
        
                UNION
        
                SELECT id, first_name, last_name, phone, activity_section, activity_unit,
                       CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) as full_name
                FROM users
                WHERE id = ?
                  AND is_active = 1
                  AND is_deleted = 0
        
                ORDER BY first_name, last_name";

        $stmt = $db->prepare($sql);
    $stmt->execute([$unit, $org_id, $user_id]);
    }

    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'users' => $users
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای داخلی سرور']);
    error_log("Get users list error: " . $e->getMessage());
}
?>