<?php
if (!function_exists('requireAuth')) {

    function requireAuth($db = null)
    {
        if ($db === null) {
            global $db;
        }

        $auth    = new Auth($db);
        $user_id = $auth->getUserFromToken();

        if (!$user_id) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'احراز هویت الزامی است. لطفاً وارد شوید.'
            ]);
            exit;
        }

        $stmt = $db->prepare("
            SELECT id, username, first_name, last_name,
                   role, organization_id, is_active
            FROM users
            WHERE id = ? AND is_active = 1
        ");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'کاربر یافت نشد یا غیرفعال است.'
            ]);
            exit;
        }

        return $user;
    }

}