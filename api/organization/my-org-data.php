<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php'; // ⚠️ مسیر فایل کلاس Auth خودت
header('Content-Type: application/json; charset=utf-8');

/* ── تشخیص کاربر از روی توکن ── */
$auth    = new Auth($db);
$user_id = $auth->getUserFromToken();

if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'توکن نامعتبر است'], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ── خواندن نقش و سازمان از دیتابیس (مطمئن‌تر از توکن) ── */
$me = $db->prepare("
    SELECT organization_id, role
    FROM users
    WHERE id = ? AND is_active = 1 AND is_deleted = 0
    LIMIT 1
");
$me->execute([$user_id]);
$me = $me->fetch(PDO::FETCH_ASSOC);

// فقط مدیر و ادمین سازمان اجازه دارند
if (!$me || !in_array($me['role'], ['managment', 'supervisor'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز'], JSON_UNESCAPED_UNICODE);
    exit;
}

$org_id = (int)$me['organization_id'];

/* ── تبدیل تاریخ میلادی به شمسی ── */
function gregorian_to_jalali($gy, $gm, $gd) {
    $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = 355666 + (365 * $gy) + ((int)(($gy2 + 3) / 4)) - ((int)(($gy2 + 99) / 100))
          + ((int)(($gy2 + 399) / 400)) + $gd + $g_d_m[$gm - 1];
    $jy = -1595 + (33 * ((int)($days / 12053)));
    $days %= 12053;
    $jy += 4 * ((int)($days / 1461));
    $days %= 1461;
    if ($days > 365) { $jy += (int)(($days - 1) / 365); $days = ($days - 1) % 365; }
    if ($days < 186) { $jm = 1 + (int)($days / 31);        $jd = 1 + ($days % 31); }
    else             { $jm = 7 + (int)(($days - 186) / 30); $jd = 1 + (($days - 186) % 30); }
    return [$jy, $jm, $jd];
}
function to_jalali($datetime, $withTime = false) {
    if (empty($datetime) || $datetime === '0000-00-00' || strtotime($datetime) === false) return '—';
    $ts = strtotime($datetime);
    [$y, $m, $d] = gregorian_to_jalali((int)date('Y', $ts), (int)date('n', $ts), (int)date('j', $ts));
    $out = sprintf('%04d/%02d/%02d', $y, $m, $d);
    if ($withTime) $out .= ' ' . date('H:i', $ts);
    return $out;
}

try {
    /* ── اطلاعات سازمان ── */
    $orgStmt = $db->prepare("SELECT name, logo, phone, address, plan_type, max_users, created_at
                             FROM organizations WHERE id = ? LIMIT 1");
    $orgStmt->execute([$org_id]);
    $org = $orgStmt->fetch(PDO::FETCH_ASSOC);

    if (!$org) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'سازمان یافت نشد'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $orgPlanLabels = ['trial' => 'آزمایشی', 'basic' => 'پایه', 'professional' => 'حرفه‌ای', 'enterprise' => 'سازمانی'];

    /* ── اشتراک فعال ── */
    $subStmt = $db->prepare("SELECT plan_type, end_date, is_active
                             FROM subscriptions WHERE organization_id = ?
                             ORDER BY is_active DESC, end_date DESC LIMIT 1");
    $subStmt->execute([$org_id]);
    $sub = $subStmt->fetch(PDO::FETCH_ASSOC);

    $subPlanLabels = ['trial' => 'آزمایشی', 'monthly' => 'ماهانه', 'yearly' => 'سالانه'];

    if ($sub) {
        $days_left  = (int)ceil((strtotime($sub['end_date']) - time()) / 86400);
        $is_expired = !$sub['is_active'] || $days_left < 0;
        $bar_pct    = max(0, min(100, ($days_left / 30) * 100));
        $bar_class  = $days_left > 7 ? 'ok' : ($days_left > 3 ? 'warn' : 'crit');
        $subscription = [
            'status'     => $is_expired ? 'expired' : 'active',
            'plan_label' => $subPlanLabels[$sub['plan_type']] ?? '—',
            'end_jalali' => to_jalali($sub['end_date']),
            'days_left'  => max(0, $days_left),
            'bar_pct'    => round($bar_pct),
            'bar_class'  => $bar_class,
        ];
    } else {
        $subscription = ['status' => 'none', 'plan_label' => '—', 'end_jalali' => '—', 'days_left' => 0, 'bar_pct' => 0, 'bar_class' => 'crit'];
    }

    /* ── فهرست پرسنل سازمان ── */
    $perStmt = $db->prepare("
        SELECT first_name, last_name, username, phone, role, activity_unit, is_active, last_login
        FROM users
        WHERE organization_id = ? AND is_deleted = 0
        ORDER BY is_active DESC, last_login DESC
    ");
    $perStmt->execute([$org_id]);
    $rows = $perStmt->fetchAll(PDO::FETCH_ASSOC);

    $roleLabels = ['employee' => 'کارمند', 'manager' => 'مدیر', 'supervisor' => 'سرپرست', 'admin' => 'ادمین'];

    $personnel = [];
    $active_count = 0;
    foreach ($rows as $r) {
        if ($r['is_active']) $active_count++;
        $name = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
        if ($name === '') $name = $r['username'] ?: $r['phone'] ?: 'بدون نام';
        $personnel[] = [
            'name'              => $name,
            'role_label'        => $roleLabels[$r['role']] ?? $r['role'],
            'unit_label'        => ($r['activity_unit'] === 'all') ? 'همه واحدها' : ($r['activity_unit'] ?? '—'),
            'is_active'         => (bool)$r['is_active'],
            'last_login_jalali' => $r['last_login'] ? to_jalali($r['last_login'], true) : 'هرگز',
        ];
    }

    $total    = count($rows);
    $capacity = (int)($org['max_users'] ?: 0);
    $cap_pct  = $capacity > 0 ? round(($total / $capacity) * 100) : 0;

    /* ── خروجی نهایی ── */
    echo json_encode([
        'success' => true,
        'org' => [
            'name'          => $org['name'],
            'logo'          => $org['logo'],
            'phone'         => $org['phone'],
            'address'       => $org['address'],
            'plan_label'    => $orgPlanLabels[$org['plan_type']] ?? '—',
            'created_jalali'=> to_jalali($org['created_at']),
            'max_users'     => $capacity,
        ],
        'subscription' => $subscription,
        'stats' => [
            'total'        => $total,
            'active'       => $active_count,
            'capacity'     => $capacity,
            'capacity_pct' => min(100, $cap_pct),
        ],
        'personnel' => $personnel,
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    error_log("organization/my-org-data.php failed | " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطای سرور'], JSON_UNESCAPED_UNICODE);
}
