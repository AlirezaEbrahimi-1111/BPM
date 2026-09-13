<?php
header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

try {
    $user_id = requireAuth();
    
    $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
    
    $database = new Database();
    $db = $database->getConnection();
    
    // بررسی نقش
    $stmt = $db->prepare("SELECT role, is_supervisor, organization_id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $current_user = $stmt->fetch();

    $team_members = [];

    if ($current_user['is_supervisor']) {
        // مسئول می‌تواند همه‌ی سازمان خودش را ببیند
        $stmt = $db->prepare("SELECT id, first_name, last_name, phone, role FROM users WHERE is_active = 1 AND is_deleted = 0 AND organization_id = ?");
        $stmt->execute([$current_user['organization_id']]);
        $team_members = $stmt->fetchAll();
    } elseif ($current_user['role'] == 'manager') {
        // مدیر فقط تیم خود را می‌بیند
        $stmt = $db->prepare("SELECT id, first_name, last_name, phone, role FROM users WHERE manager_id = ? AND is_active = 1 AND is_deleted = 0");
        $stmt->execute([$user_id]);
        $team_members = $stmt->fetchAll();
    } else {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'شما مجاز به مشاهده گزارش تیم نیستید']);
        exit;
    }
    
    $team_status = [];
    
    foreach ($team_members as $member) {
        // وضعیت حضور امروز
        $stmt = $db->prepare("
            SELECT * FROM attendance_records
            WHERE user_id = ? AND date = ?
            ORDER BY shift_number DESC
            LIMIT 1
        ");
        $stmt->execute([$member['id'], $date]);
        $attendance = $stmt->fetch();
        
        $status = 'absent';
        $check_in = null;
        $check_out = null;
        
        if ($attendance) {
            $check_in = $attendance['check_in'];
            $check_out = $attendance['check_out'];
            
            if ($attendance['check_out']) {
                $status = 'completed';
            } else {
                $status = 'present';
            }
            
            if ($attendance['late_minutes'] > 0) {
                $status = 'late';
            }
        } else {
            // بررسی مرخصی
            $stmt = $db->prepare("
                SELECT id FROM leave_requests
                WHERE user_id = ? AND ? BETWEEN start_date AND end_date
                AND status = 'approved'
            ");
            $stmt->execute([$member['id'], $date]);
            if ($stmt->fetch()) {
                $status = 'leave';
            }
            
            // بررسی مأموریت
            $stmt = $db->prepare("
                SELECT id FROM mission_requests
                WHERE user_id = ? AND ? BETWEEN DATE(start_date) AND DATE(end_date)
                AND status = 'approved'
            ");
            $stmt->execute([$member['id'], $date]);
            if ($stmt->fetch()) {
                $status = 'mission';
            }
        }
        
        $team_status[] = [
            'user_id' => $member['id'],
            'name' => $member['first_name'] . ' ' . $member['last_name'],
            'phone' => $member['phone'],
            'role' => $member['role'],
            'status' => $status,
            'check_in' => $check_in,
            'check_out' => $check_out
        ];
    }
    
    echo json_encode([
        'success' => true,
        'date' => $date,
        'team' => $team_status
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای داخلی سرور']);
}
?>