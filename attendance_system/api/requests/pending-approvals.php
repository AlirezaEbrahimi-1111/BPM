<?php
/**
 * API: دریافت درخواست‌های منتظر تأیید کاربر فعلی
 * مسیر: /attendance_system/api/requests/pending-approvals.php
 * متد: GET
 */

if (session_status() === PHP_SESSION_NONE) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
}
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Tehran');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
// $db حالا آماده‌ست
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/settings_helper.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/working-days-helper.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
$auth = new Auth($db);

// احراز هویت
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id)
    $user_id = $auth->getUserFromToken();
if (!$user_id && isset($_COOKIE['auth_token']))
    $user_id = $auth->validateToken($_COOKIE['auth_token']);

if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'لطفا وارد شوید'], JSON_UNESCAPED_UNICODE);
    exit;
}

// دریافت اطلاعات کاربر فعلی
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$current_user = $stmt->fetch(PDO::FETCH_ASSOC);

$pending_requests = [];
try {
    // ============================================
    // 1. درخواست‌های مرخصی منتظر تأیید جانشین
    // ============================================
    $stmt = $db->prepare("
        SELECT 
            lr.id,
            'leave' as type,
            lr.request_code,
            lr.user_id,
            u.first_name,
            u.last_name,
            CONCAT(lr.start_date, ' ', lr.start_time) as start_datetime,
            CONCAT(lr.end_date, ' ', lr.end_time) as end_datetime,
            lr.reason as description,
            lr.status,
            lr.substitute_id,
            CONCAT(su.first_name, ' ', su.last_name) as substitute_name,
            lr.substitute_approval,
            lr.manager_approval,
            lr.supervisor_approval,
            lr.created_at,
            'substitute' as pending_role
        FROM leave_requests lr
        JOIN users u ON lr.user_id = u.id
        LEFT JOIN users su ON lr.substitute_id = su.id
        WHERE lr.substitute_id = ?
        AND lr.substitute_approval = 'pending'
        AND lr.status = 'pending'
    ");
    $stmt->execute([$user_id]);
    $pending_requests = array_merge($pending_requests, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // ============================================
    // 2. درخواست‌های مرخصی منتظر تأیید مدیر
    // ============================================
    $stmt = $db->prepare("
        SELECT 
            lr.id,
            'leave' as type,
            lr.request_code,
            lr.user_id,
            u.first_name,
            u.last_name,
            CONCAT(lr.start_date, ' ', lr.start_time) as start_datetime,
            CONCAT(lr.end_date, ' ', lr.end_time) as end_datetime,
            lr.reason as description,
            lr.status,
            lr.substitute_id,
            CONCAT(su.first_name, ' ', su.last_name) as substitute_name,
            lr.substitute_approval,
            lr.manager_approval,
            lr.supervisor_approval,
            lr.created_at,
            'manager' as pending_role
        FROM leave_requests lr
        JOIN users u ON lr.user_id = u.id
        LEFT JOIN users su ON lr.substitute_id = su.id
        WHERE lr.substitute_approval = 'approved'
        AND lr.manager_approval = 'pending'
        AND lr.status = 'pending'
        AND u.manager_id = ?
    ");
    $stmt->execute([$user_id]);
    $pending_requests = array_merge($pending_requests, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // ============================================
    // 3. درخواست‌های مرخصی منتظر تأیید مسئول
    // ============================================
    if ($current_user['id'] == 19 || $current_user['is_supervisor'] == 1) {
        $stmt = $db->prepare("
            SELECT
                lr.id,
                'leave' as type,
                lr.request_code,
                lr.user_id,
                u.first_name,
                u.last_name,
                CONCAT(lr.start_date, ' ', lr.start_time) as start_datetime,
                CONCAT(lr.end_date, ' ', lr.end_time) as end_datetime,
                lr.reason as description,
                lr.status,
                lr.substitute_id,
                CONCAT(su.first_name, ' ', su.last_name) as substitute_name,
                lr.substitute_approval,
                lr.manager_approval,
                lr.supervisor_approval,
                lr.created_at,
                'supervisor' as pending_role
            FROM leave_requests lr
            JOIN users u ON lr.user_id = u.id
            LEFT JOIN users su ON lr.substitute_id = su.id
            WHERE lr.manager_approval = 'approved'
            AND lr.supervisor_approval = 'pending'
            AND lr.status = 'pending'
            AND u.organization_id = ?
        ");
        $stmt->execute([$current_user['organization_id']]);
        $pending_requests = array_merge($pending_requests, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    // ============================================
    // 4. درخواست‌های مأموریت منتظر تأیید مدیر
    // ============================================
    $stmt = $db->prepare("
        SELECT 
            mr.id,
            'mission' as type,
            mr.request_code,
            mr.user_id,
            u.first_name,
            u.last_name,
            mr.start_date as start_datetime,
            mr.end_date as end_datetime,
            mr.purpose as description,
            mr.status,
            mr.manager_approval,
            mr.supervisor_approval,
            mr.created_at,
            'manager' as pending_role
        FROM mission_requests mr
        JOIN users u ON mr.user_id = u.id
        WHERE mr.manager_approval = 'pending'
        AND mr.status = 'pending'
        AND u.manager_id = ?
    ");
    $stmt->execute([$user_id]);
    $pending_requests = array_merge($pending_requests, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // ============================================
    // 5. درخواست‌های مأموریت منتظر تأیید مسئول
    // ============================================
    if ($current_user['id'] == 19 || $current_user['is_supervisor'] == 1) {
        $stmt = $db->prepare("
            SELECT
                mr.id,
                'mission' as type,
                mr.request_code,
                mr.user_id,
                u.first_name,
                u.last_name,
                mr.start_date as start_datetime,
                mr.end_date as end_datetime,
                mr.purpose as description,
                mr.status,
                mr.manager_approval,
                mr.supervisor_approval,
                mr.created_at,
                'supervisor' as pending_role
            FROM mission_requests mr
            JOIN users u ON mr.user_id = u.id
            WHERE mr.manager_approval = 'approved'
            AND mr.supervisor_approval = 'pending'
            AND mr.status = 'pending'
            AND u.organization_id = ?
        ");
        $stmt->execute([$current_user['organization_id']]);
        $pending_requests = array_merge($pending_requests, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    // ============================================
    // 6. درخواست‌های فراموشی منتظر تأیید مدیر
    // ============================================
    $stmt = $db->prepare("
        SELECT 
            fr.id,
            'forget' as type,
            fr.request_code,
            fr.user_id,
            u.first_name,
            u.last_name,
            fr.start_date as start_datetime,
            fr.end_date as end_datetime,
            fr.description,
            fr.status,
            fr.manager_approval,
            fr.supervisor_approval,
            fr.created_at,
            'manager' as pending_role
        FROM forget_requests fr
        JOIN users u ON fr.user_id = u.id
        WHERE fr.manager_approval = 'pending'
        AND fr.status = 'pending'
        AND u.manager_id = ?
    ");
    $stmt->execute([$user_id]);
    $pending_requests = array_merge($pending_requests, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // ============================================
    // 7. درخواست‌های فراموشی منتظر تأیید مسئول
    // ============================================
    if ($current_user['id'] == 19 || $current_user['is_supervisor'] == 1) {
        $stmt = $db->prepare("
            SELECT
                fr.id,
                'forget' as type,
                fr.request_code,
                fr.user_id,
                u.first_name,
                u.last_name,
                fr.start_date as start_datetime,
                fr.end_date as end_datetime,
                fr.description,
                fr.status,
                fr.manager_approval,
                fr.supervisor_approval,
                fr.created_at,
                'supervisor' as pending_role
            FROM forget_requests fr
            JOIN users u ON fr.user_id = u.id
            WHERE fr.manager_approval = 'approved'
            AND fr.supervisor_approval = 'pending'
            AND fr.status = 'pending'
            AND u.organization_id = ?
        ");
        $stmt->execute([$current_user['organization_id']]);
        $pending_requests = array_merge($pending_requests, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    // ============================================
    // 8. درخواست‌های مشکل فنی (برای admin/supervisور همان سازمان)
    // ============================================
    if ($current_user['is_supervisor'] == 1 || $current_user['role'] === 'admin') {
        $stmt = $db->prepare("
            SELECT
                ti.id,
                'technical' as type,
                ti.request_code,
                ti.user_id,
                u.first_name,
                u.last_name,
                CONCAT(DATE(ti.start_date), ' ', ti.start_time) as start_datetime,
                CONCAT(DATE(ti.end_date), ' ', ti.end_time) as end_datetime,
                ti.description,
                ti.status,
                ti.created_at,
                'admin' as pending_role
            FROM technical_issues ti
            JOIN users u ON ti.user_id = u.id
            WHERE ti.status = 'pending'
            AND u.organization_id = ?
        ");
        $stmt->execute([$current_user['organization_id']]);
        $pending_requests = array_merge($pending_requests, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    // مرتب‌سازی بر اساس تاریخ ایجاد
    usort($pending_requests, function ($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    // ✅ require_once و loadSettings رو قبل از حلقه بیار
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/settings_helper.php';
    $app_settings = loadSettings($db);
    $deadline_days = $app_settings['approval_deadline_days'] ?? 3; // مقدار پیش‌فرض
    
    // ============================================
    // محاسبه is_expired برای هر درخواست
    // ============================================
    $today_str = date('Y-m-d');
    $__org_id = (int) ($current_user['organization_id'] ?? 0);
    $holidays = getHolidaySet($db, $__org_id);
    $recurringWeekdays = getRecurringHolidayWeekdays($db, $__org_id);
    foreach ($pending_requests as &$req) {
        $created_date = substr($req['created_at'], 0, 10);
        $working_days = countWorkingDaysBetween(new DateTime($created_date), new DateTime($today_str), $holidays, $recurringWeekdays);
        $req['is_expired'] = ($working_days > $app_settings['approval_deadline_days']);
    }
    unset($req);
    echo json_encode([
        'success' => true,
        'data' => $pending_requests,
        'count' => count($pending_requests)
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("Pending approvals error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطا در دریافت اطلاعات']);
}
?>