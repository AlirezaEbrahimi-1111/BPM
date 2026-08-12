<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

try {
    $user_id = requireAuth();
    
    $database = new Database();
    $db = $database->getConnection();
    
    // دریافت نقش کاربر
    $stmt = $db->prepare("SELECT role, is_supervisor FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    $pending_requests = [];
    
    // درخواست‌های مرخصی (برای جانشین)
    $stmt = $db->prepare("
        SELECT lr.*, u.first_name, u.last_name, u.phone
        FROM leave_requests lr
        JOIN users u ON lr.user_id = u.id
        WHERE lr.substitute_id = ? 
        AND lr.substitute_approval = 'pending'
        AND lr.status = 'pending'
        ORDER BY lr.created_at ASC
    ");
    $stmt->execute([$user_id]);
    $leave_requests = $stmt->fetchAll();
    
    foreach ($leave_requests as $req) {
        $pending_requests[] = [
            'type' => 'leave',
            'id' => $req['id'],
            'code' => $req['request_code'],
            'user_name' => $req['first_name'] . ' ' . $req['last_name'],
            'user_phone' => $req['phone'],
            'start_date' => $req['start_date'],
            'end_date' => $req['end_date'],
            'duration' => $req['duration_days'] . ' روز',
            'reason' => $req['reason'],
            'created_at' => $req['created_at'],
            'my_role' => 'substitute'
        ];
    }
    
    // درخواست‌های مرخصی (برای مدیر)
    $stmt = $db->prepare("
        SELECT lr.*, u.first_name, u.last_name, u.phone
        FROM leave_requests lr
        JOIN users u ON lr.user_id = u.id
        WHERE lr.manager_id = ? 
        AND lr.manager_approval = 'pending'
        AND lr.status = 'pending'
        AND lr.substitute_approval = 'approved'
        ORDER BY lr.created_at ASC
    ");
    $stmt->execute([$user_id]);
    $leave_requests_manager = $stmt->fetchAll();
    
    foreach ($leave_requests_manager as $req) {
        $pending_requests[] = [
            'type' => 'leave',
            'id' => $req['id'],
            'code' => $req['request_code'],
            'user_name' => $req['first_name'] . ' ' . $req['last_name'],
            'user_phone' => $req['phone'],
            'start_date' => $req['start_date'],
            'end_date' => $req['end_date'],
            'duration' => $req['duration_days'] . ' روز',
            'reason' => $req['reason'],
            'created_at' => $req['created_at'],
            'my_role' => 'manager'
        ];
    }
    
    // درخواست‌های مأموریت (برای مدیر)
    $stmt = $db->prepare("
        SELECT mr.*, u.first_name, u.last_name, u.phone
        FROM mission_requests mr
        JOIN users u ON mr.user_id = u.id
        WHERE mr.manager_id = ? 
        AND mr.manager_approval = 'pending'
        AND mr.status = 'pending'
        ORDER BY mr.created_at ASC
    ");
    $stmt->execute([$user_id]);
    $mission_requests = $stmt->fetchAll();
    
    foreach ($mission_requests as $req) {
        $pending_requests[] = [
            'type' => 'mission',
            'id' => $req['id'],
            'code' => $req['request_code'],
            'user_name' => $req['first_name'] . ' ' . $req['last_name'],
            'user_phone' => $req['phone'],
            'start_date' => $req['start_date'],
            'end_date' => $req['end_date'],
            'duration' => round($req['duration_hours'], 2) . ' ساعت',
            'destination' => $req['destination'],
            'purpose' => $req['purpose'],
            'created_at' => $req['created_at'],
            'my_role' => 'manager'
        ];
    }
    
    // درخواست‌های فراموشی (برای مدیر)
    $stmt = $db->prepare("
        SELECT fr.*, u.first_name, u.last_name, u.phone
        FROM forget_requests fr
        JOIN users u ON fr.user_id = u.id
        WHERE fr.manager_id = ? 
        AND fr.manager_approval = 'pending'
        AND fr.status = 'pending'
        ORDER BY fr.created_at ASC
    ");
    $stmt->execute([$user_id]);
    $forget_requests = $stmt->fetchAll();
    
    foreach ($forget_requests as $req) {
        $pending_requests[] = [
            'type' => 'forget',
            'id' => $req['id'],
            'code' => $req['request_code'],
            'user_name' => $req['first_name'] . ' ' . $req['last_name'],
            'user_phone' => $req['phone'],
            'forget_date' => $req['forget_date'],
            'forget_type' => $req['forget_type'],
            'reason' => $req['reason'],
            'created_at' => $req['created_at'],
            'my_role' => 'manager'
        ];
    }
    
    // مشکلات فنی (برای ادمین)
    if ($user['role'] == 'admin') {
        $stmt = $db->prepare("
            SELECT ti.*, u.first_name, u.last_name, u.phone
            FROM technical_issues ti
            JOIN users u ON ti.user_id = u.id
            WHERE ti.status = 'pending'
            ORDER BY ti.created_at ASC
        ");
        $stmt->execute();
        $technical_issues = $stmt->fetchAll();
        
        foreach ($technical_issues as $req) {
            $pending_requests[] = [
                'type' => 'technical',
                'id' => $req['id'],
                'code' => $req['request_code'],
                'user_name' => $req['first_name'] . ' ' . $req['last_name'],
                'user_phone' => $req['phone'],
                'issue_date' => $req['issue_date'],
                'issue_type' => $req['issue_type'],
                'duration' => round($req['duration_hours'], 2) . ' ساعت',
                'description' => $req['description'],
                'created_at' => $req['created_at'],
                'my_role' => 'admin'
            ];
        }
    }
    
    // درخواست‌های در انتظار تأیید مسئول
    if ($user['is_supervisor']) {
        // مرخصی‌ها
        $stmt = $db->prepare("
            SELECT lr.*, u.first_name, u.last_name, u.phone
            FROM leave_requests lr
            JOIN users u ON lr.user_id = u.id
            WHERE lr.supervisor_approval = 'pending'
            AND lr.status = 'pending'
            AND lr.substitute_approval = 'approved'
            AND lr.manager_approval = 'approved'
            ORDER BY lr.created_at ASC
        ");
        $stmt->execute();
        $supervisor_leaves = $stmt->fetchAll();
        
        foreach ($supervisor_leaves as $req) {
            $pending_requests[] = [
                'type' => 'leave',
                'id' => $req['id'],
                'code' => $req['request_code'],
                'user_name' => $req['first_name'] . ' ' . $req['last_name'],
                'user_phone' => $req['phone'],
                'start_date' => $req['start_date'],
                'end_date' => $req['end_date'],
                'duration' => $req['duration_days'] . ' روز',
                'reason' => $req['reason'],
                'created_at' => $req['created_at'],
                'my_role' => 'supervisor'
            ];
        }
        
        // مأموریت‌ها
        $stmt = $db->prepare("
            SELECT mr.*, u.first_name, u.last_name, u.phone
            FROM mission_requests mr
            JOIN users u ON mr.user_id = u.id
            WHERE mr.supervisor_approval = 'pending'
            AND mr.status = 'pending'
            AND mr.manager_approval = 'approved'
            ORDER BY mr.created_at ASC
        ");
        $stmt->execute();
        $supervisor_missions = $stmt->fetchAll();
        
        foreach ($supervisor_missions as $req) {
            $pending_requests[] = [
                'type' => 'mission',
                'id' => $req['id'],
                'code' => $req['request_code'],
                'user_name' => $req['first_name'] . ' ' . $req['last_name'],
                'user_phone' => $req['phone'],
                'start_date' => $req['start_date'],
                'end_date' => $req['end_date'],
                'duration' => round($req['duration_hours'], 2) . ' ساعت',
                'destination' => $req['destination'],
                'purpose' => $req['purpose'],
                'created_at' => $req['created_at'],
                'my_role' => 'supervisor'
            ];
        }
        
        // فراموشی‌ها
        $stmt = $db->prepare("
            SELECT fr.*, u.first_name, u.last_name, u.phone
            FROM forget_requests fr
            JOIN users u ON fr.user_id = u.id
            WHERE fr.supervisor_approval = 'pending'
            AND fr.status = 'pending'
            AND fr.manager_approval = 'approved'
            ORDER BY fr.created_at ASC
        ");
        $stmt->execute();
        $supervisor_forgets = $stmt->fetchAll();
        
        foreach ($supervisor_forgets as $req) {
            $pending_requests[] = [
                'type' => 'forget',
                'id' => $req['id'],
                'code' => $req['request_code'],
                'user_name' => $req['first_name'] . ' ' . $req['last_name'],
                'user_phone' => $req['phone'],
                'forget_date' => $req['forget_date'],
                'forget_type' => $req['forget_type'],
                'reason' => $req['reason'],
                'created_at' => $req['created_at'],
                'my_role' => 'supervisor'
            ];
        }
    }
    
    // مرتب‌سازی بر اساس تاریخ
    usort($pending_requests, function($a, $b) {
        return strtotime($a['created_at']) - strtotime($b['created_at']);
    });
    
    echo json_encode([
        'success' => true,
        'count' => count($pending_requests),
        'requests' => $pending_requests
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای داخلی سرور']);
    error_log("Approval list API error: " . $e->getMessage());
}
?>