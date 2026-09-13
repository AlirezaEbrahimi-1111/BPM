<?php
header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com', 'https://bpm.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

try {
    $user_id = requireAuth();
    
    $type = isset($_GET['type']) ? $_GET['type'] : 'all';
    $status = isset($_GET['status']) ? $_GET['status'] : 'all';
    
    $database = new Database();
    $db = $database->getConnection();
    
    $my_requests = [];
    
    // درخواست‌های مرخصی
    if ($type == 'all' || $type == 'leave') {
        $sql = "SELECT * FROM leave_requests WHERE user_id = ?";
        if ($status != 'all') {
            $sql .= " AND status = ?";
        }
        $sql .= " ORDER BY created_at DESC";
        
        $stmt = $db->prepare($sql);
        if ($status != 'all') {
            $stmt->execute([$user_id, $status]);
        } else {
            $stmt->execute([$user_id]);
        }
        $leaves = $stmt->fetchAll();
        
        foreach ($leaves as $req) {
            $my_requests[] = [
                'type' => 'leave',
                'id' => $req['id'],
                'code' => $req['request_code'],
                'start_date' => $req['start_date'],
                'end_date' => $req['end_date'],
                'duration' => $req['duration_days'] . ' روز',
                'reason' => $req['reason'],
                'status' => $req['status'],
                'substitute_approval' => $req['substitute_approval'],
                'manager_approval' => $req['manager_approval'],
                'supervisor_approval' => $req['supervisor_approval'],
                'can_edit' => $req['can_edit'],
                'can_delete' => $req['can_delete'],
                'created_at' => $req['created_at']
            ];
        }
    }
    
    // درخواست‌های پاس
    if ($type == 'all' || $type == 'pass') {
        $sql = "SELECT * FROM pass_requests WHERE user_id = ?";
        if ($status != 'all') {
            $sql .= " AND status = ?";
        }
        $sql .= " ORDER BY created_at DESC";
        
        $stmt = $db->prepare($sql);
        if ($status != 'all') {
            $stmt->execute([$user_id, $status]);
        } else {
            $stmt->execute([$user_id]);
        }
        $passes = $stmt->fetchAll();
        
        foreach ($passes as $req) {
            $my_requests[] = [
                'type' => 'pass',
                'id' => $req['id'],
                'code' => $req['request_code'],
                'pass_date' => $req['pass_date'],
                'start_time' => $req['start_time'],
                'end_time' => $req['end_time'],
                'duration' => $req['duration_hours'] . ' ساعت',
                'reason' => $req['reason'],
                'status' => $req['status'],
                'can_edit' => $req['can_edit'],
                'can_delete' => $req['can_delete'],
                'created_at' => $req['created_at']
            ];
        }
    }
    
    // درخواست‌های مأموریت
    if ($type == 'all' || $type == 'mission') {
        $sql = "SELECT * FROM mission_requests WHERE user_id = ?";
        if ($status != 'all') {
            $sql .= " AND status = ?";
        }
        $sql .= " ORDER BY created_at DESC";
        
        $stmt = $db->prepare($sql);
        if ($status != 'all') {
            $stmt->execute([$user_id, $status]);
        } else {
            $stmt->execute([$user_id]);
        }
        $missions = $stmt->fetchAll();
        
        foreach ($missions as $req) {
            $my_requests[] = [
                'type' => 'mission',
                'id' => $req['id'],
                'code' => $req['request_code'],
                'start_date' => $req['start_date'],
                'end_date' => $req['end_date'],
                'duration' => round($req['duration_hours'], 2) . ' ساعت',
                'destination' => $req['destination'],
                'purpose' => $req['purpose'],
                'status' => $req['status'],
                'manager_approval' => $req['manager_approval'],
                'supervisor_approval' => $req['supervisor_approval'],
                'can_edit' => $req['can_edit'],
                'can_delete' => $req['can_delete'],
                'created_at' => $req['created_at']
            ];
        }
    }
    
    // درخواست‌های فراموشی
    if ($type == 'all' || $type == 'forget') {
        $sql = "SELECT * FROM forget_requests WHERE user_id = ?";
        if ($status != 'all') {
            $sql .= " AND status = ?";
        }
        $sql .= " ORDER BY created_at DESC";
        
        $stmt = $db->prepare($sql);
        if ($status != 'all') {
            $stmt->execute([$user_id, $status]);
        } else {
            $stmt->execute([$user_id]);
        }
        $forgets = $stmt->fetchAll();
        
        foreach ($forgets as $req) {
            $my_requests[] = [
                'type' => 'forget',
                'id' => $req['id'],
                'code' => $req['request_code'],
                'forget_date' => $req['forget_date'],
                'forget_type' => $req['forget_type'],
                'reason' => $req['reason'],
                'status' => $req['status'],
                'manager_approval' => $req['manager_approval'],
                'supervisor_approval' => $req['supervisor_approval'],
                'can_edit' => $req['can_edit'],
                'can_delete' => $req['can_delete'],
                'created_at' => $req['created_at']
            ];
        }
    }
    
    // مرتب‌سازی
    usort($my_requests, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    echo json_encode([
        'success' => true,
        'count' => count($my_requests),
        'requests' => $my_requests
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای داخلی سرور']);
}
?>