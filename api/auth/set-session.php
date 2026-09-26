<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
header('Content-Type: application/json; charset=utf-8');



require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

try {
    $database = new Database();
    $db = $database->getConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection error']);
    exit;
}

$auth = new Auth($db);

$user_id = $auth->getUserFromToken();

if ($user_id) {
    // ✅ Set کردن SESSION
    $_SESSION['user_id'] = $user_id;

    // دریافت اطلاعات کاربر
    $stmt = $db->prepare("SELECT username, first_name, last_name, organization_id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $_SESSION['username'] = $user['username'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'];

        // 🔒 قبلا این‌جا ست نمی‌شد — فقط api/auth/login.php این دوتا رو
        // می‌نوشت، و هیچ‌جای دیگه‌ای دوباره refresh نمی‌شدن. سشن PHP
        // عمر کوتاهی داره (gc_maxlifetime) ولی JWT طولانی‌مدته؛ این
        // endpoint دقیقا برای zندهnگه‌داشتن سشن با همون JWT ساخته شده
        // (هر بارگذاری صفحه + هر ۱۰ دقیقه، از header.php)، پس باید کامل
        // چیزی که login.php می‌نویسه رو دوباره بنویسه، نه فقط بخشی‌ش —
        // وگرنه organization_id/organization_name برای همیشه (تا لاگین
        // بعدی) خالی می‌موندن حتی وقتی این endpoint داره درست کار می‌کنه.
        // پیدا شد حین ریشه‌یابی باگ «گزینه‌ی ورود و خروج گاهی دیده
        // نمی‌شه» — همون کلاس‌باگ، همینجا هم می‌تونست باعث مشکل بشه.
        $_SESSION['organization_id'] = $user['organization_id'];
        try {
            $orgStmt = $db->prepare("SELECT name FROM organizations WHERE id = ?");
            $orgStmt->execute([$user['organization_id']]);
            $org = $orgStmt->fetch(PDO::FETCH_ASSOC);
            $_SESSION['organization_name'] = $org ? $org['name'] : 'یکتا همراهان ملک';
        } catch (Exception $e) {
            $_SESSION['organization_name'] = 'یکتا همراهان ملک';
        }
    }

    echo json_encode(['success' => true, 'user_id' => $user_id]);
} else {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid token']);
}
?>