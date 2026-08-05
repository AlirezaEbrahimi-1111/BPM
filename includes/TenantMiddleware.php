<?php
class TenantMiddleware {
    private PDO $db;
    private Auth $auth;
    
    public function __construct(PDO $db, Auth $auth) {
        $this->db = $db;
        $this->auth = $auth;
    }
    
    /**
     * بررسی اشتراک و بازگشت organization_id
     */
    public function handle(): int {
        // گرفتن کاربر از توکن
        $user = $this->auth->getUserFromToken();
        if (!$user) {
            $this->sendError(401, 'توکن معتبر نیست');
        }

        $org_id = (int)$user['organization_id'];
        if (!$org_id) {
            $this->sendError(403, 'کاربر به سازمانی متصل نیست', $user['id'] ?? null);
        }
        
        // بررسی وضعیت سازمان
        $stmt = $this->db->prepare("
            SELECT o.is_active, s.end_date, s.is_active as sub_active, s.max_users
            FROM organizations o
            LEFT JOIN subscriptions s
              ON s.organization_id = o.id
              AND s.is_active = 1
            WHERE o.id = ?
            ORDER BY s.end_date DESC
            LIMIT 1
        ");
        $stmt->execute([$org_id]);
        $org = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$org || !$org['is_active']) {
            $this->sendError(403, 'سازمان غیرفعال است', $user['id'] ?? null, $org_id);
        }

        if (!$org['sub_active'] || strtotime($org['end_date']) < time()) {
            $this->sendError(402, 'اشتراک شما منقضی شده است', $user['id'] ?? null, $org_id);
        }

        return $org_id;
    }

    private function sendError(int $code, string $message, $user_id = null, $org_id = null): void {
        error_log('TenantMiddleware denied | code=' . $code . ' | reason=' . $message . ' | user_id=' . ($user_id ?? 'unknown') . ' | organization_id=' . ($org_id ?? 'unknown') . ' | uri=' . ($_SERVER['REQUEST_URI'] ?? 'unknown') . ' | ip=' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'message' => $message
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
