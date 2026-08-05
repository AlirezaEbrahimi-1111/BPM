<?php
class OrganizationHelper {
    private PDO $db;
    
    public function __construct(PDO $db) {
        $this->db = $db;
    }
    
    /**
     * ثبت سازمان جدید + اولین ادمین
     */
    public function registerOrganization(array $data): array {
        $this->db->beginTransaction();
        
        try {
            // 1. ایجاد سازمان
            $stmt = $this->db->prepare("
                INSERT INTO organizations (name, phone, max_users)
                VALUES (?, ?, 10)
            ");
            $stmt->execute([
                trim($data['org_name']),
                $data['phone'] ?? null
            ]);
            $org_id = (int)$this->db->lastInsertId();
            
            // 2. اشتراک آزمایشی 14 روزه
            $stmt = $this->db->prepare("
                INSERT INTO subscriptions
                  (organization_id, plan_type, max_users, price, start_date, end_date, is_active)
                VALUES (?, 'trial', 10, 0, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 14 DAY), 1)
            ");
            $stmt->execute([$org_id]);
            
            // 3. ایجاد کاربر ادمین
            $hashed_password = password_hash($data['password'], PASSWORD_BCRYPT);
            $stmt = $this->db->prepare("
                INSERT INTO users
                  (organization_id, phone, username, password, first_name, last_name,
                   role, is_active, activity_section,is_supervisor,activity_unit,can_create_routine,can_create_workflow,is_manager)
                VALUES (?, ?, ?, ?, ?, ?, 'supervisor', 1, 'management',1,'all',1,1,1)
            ");
            $stmt->execute([
                $org_id,
                $data['phone'],
                $data['phone'],
                $hashed_password,
                trim($data['first_name']),
                trim($data['last_name'])
            ]);
            $user_id = (int)$this->db->lastInsertId();
            
            $this->db->commit();
            
            return [
                'success'         => true,
                'organization_id' => $org_id,
                'user_id'         => $user_id
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("registerOrganization failed (rolled back) | phone=" . ($data['phone'] ?? 'null') . " | " . $e->getMessage());
            throw $e;
        }
    }

    
    /**
     * بررسی محدودیت تعداد کاربران
     */
    public function canAddUser(int $org_id): bool {
        $stmt = $this->db->prepare("
            SELECT
              s.max_users,
              COUNT(u.id) AS current_users
            FROM subscriptions s
            LEFT JOIN users u
              ON u.organization_id = s.organization_id
              AND u.is_active = 1
            WHERE s.organization_id = ?
              AND s.is_active = 1
            GROUP BY s.max_users
            ORDER BY s.end_date DESC
            LIMIT 1
        ");
        $stmt->execute([$org_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) return false;
        return $result['current_users'] < $result['max_users'];
    }
    
    /**
     * اطلاعات کامل سازمان + اشتراک
     */
    public function getOrganizationInfo(int $org_id): ?array {
        $stmt = $this->db->prepare("
            SELECT
              o.id, o.name, o.phone, o.max_users, o.is_active,
              o.created_at,
              s.plan_type, s.start_date, s.end_date,
              s.max_users AS sub_max_users, s.is_active AS sub_is_active,
              DATEDIFF(s.end_date, CURDATE()) AS days_remaining,
              (SELECT COUNT(*) FROM users
               WHERE organization_id = o.id AND is_active = 1) AS active_users_count
            FROM organizations o
            LEFT JOIN subscriptions s ON s.organization_id = o.id AND s.is_active = 1
            WHERE o.id = ?
            ORDER BY s.end_date DESC
            LIMIT 1
        ");
        $stmt->execute([$org_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
