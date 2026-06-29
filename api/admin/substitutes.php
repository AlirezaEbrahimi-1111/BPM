<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://bpm.computeryekta.com');
header('Access-Control-Allow-Methods: GET, POST, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

try {
    $user_id = requireAuth();
    
    $database = new Database();
    $db = $database->getConnection();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // دریافت جانشین‌های کاربر
        $target_user = isset($_GET['user_id']) ? (int)$_GET['user_id'] : $user_id;
        
        $stmt = $db->prepare("
            SELECT s.*, u.first_name, u.last_name, u.phone
            FROM substitutes s
            JOIN users u ON s.substitute_user_id = u.id
            WHERE s.user_id = ? AND s.is_active = 1
            ORDER BY s.id ASC
        ");
        $stmt->execute([$target_user]);
        $substitutes = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'substitutes' => $substitutes
        ]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // افزودن جانشین
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['substitute_user_id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'شناسه جانشین الزامی است']);
            exit;
        }
        
        $substitute_id = $input['substitute_user_id'];
        
        // بررسی: نمی‌تواند خود را جانشین خود انتخاب کند
        if ($substitute_id == $user_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'نمی‌توانید خود را جانشین خود انتخاب کنید']);
            exit;
        }
        
        // بررسی: نمی‌تواند مسئول را جانشین خود انتخاب کند
        $stmt = $db->prepare("SELECT is_supervisor FROM users WHERE id = ?");
        $stmt->execute([$substitute_id]);
        $substitute_user = $stmt->fetch();
        
        if ($substitute_user && $substitute_user['is_supervisor']) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'نمی‌توانید مسئول را به عنوان جانشین انتخاب کنید']);
            exit;
        }
        
        // افزودن جانشین
        $stmt = $db->prepare("
            INSERT INTO substitutes (user_id, substitute_user_id)
            VALUES (?, ?)
        ");
        
        try {
            if ($stmt->execute([$user_id, $substitute_id])) {
                echo json_encode(['success' => true, 'message' => 'جانشین با موفقیت افزوده شد']);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'خطا در افزودن جانشین']);
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'این جانشین قبلاً اضافه شده است']);
            } else {
                throw $e;
            }
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        // حذف جانشین
        $substitute_id = $_GET['id'] ?? null;
        
        if (!$substitute_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'شناسه جانشین الزامی است']);
            exit;
        }
        
        $stmt = $db->prepare("DELETE FROM substitutes WHERE id = ? AND user_id = ?");
        if ($stmt->execute([$substitute_id, $user_id])) {
            echo json_encode(['success' => true, 'message' => 'جانشین با موفقیت حذف شد']);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'خطا در حذف جانشین']);
        }
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای داخلی سرور']);
    error_log("Substitutes API error: " . $e->getMessage());
}
?>