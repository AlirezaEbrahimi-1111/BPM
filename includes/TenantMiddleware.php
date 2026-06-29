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
            $this->sendError(403, 'کاربر به سازمانی متصل نیست');
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
            $this->sendError(403, 'سازمان غیرفعال است');
        }
        
        if (!$org['sub_active'] || strtotime($org['end_date']) < time()) {
            $this->sendError(402, 'اشتراک شما منقضی شده است');
        }
        
        return $org_id;
    }
    
    private function sendError(int $code, string $message): void {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'message' => $message
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
