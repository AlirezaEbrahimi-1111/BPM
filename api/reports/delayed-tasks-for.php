<?php

/**
 * ═══════════════════════════════════════════════════════════════════
 *  api/reports/delayed-tasks-for.php
 *  فهرستِ واقعیِ کارهایِ تأخیردارِ یک کاربر یا یک واحدِ سازمانیِ خاص —
 *  برایِ مودالِ «کارهای تأخیردار» در pages/delayed-users.php.
 * ───────────────────────────────────────────────────────────────────
 *  🔒 همون سه کوئری/تعریفِ «تأخیردار» که api/reports/top-delayed-users.php
 *  برایِ شمارشِ تجمیعی استفاده می‌کنه، از includes/delayed-tasks-helper.php
 *  میاد — اینجا فقط دیگه جمع نمی‌زنه، ردیفِ خودِ کارها رو (فیلترشده برای
 *  همین یک کاربر/واحد) برمی‌گردونه. اگه این دو فایل از هم جدا نوشته
 *  می‌شدن، جمعِ نشون‌داده‌شده در لیست با تعدادِ واقعیِ کارهایِ توی مودال
 *  می‌تونست به‌مرور جفت‌وجور نباشه.
 *
 *  GET /api/reports/delayed-tasks-for.php?kind=user|section&ref_id=...
 * ═══════════════════════════════════════════════════════════════════
 */

header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com', 'https://bpm.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/working-days-helper.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/period-engine.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/delayed-tasks-helper.php';

try {
    // ── احراز هویت ───────────────────────────────────
    $auth    = new Auth();
    $user_id = $auth->getUserFromToken();
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/plan-access.php';
    requirePlanFeature($user_id, 'monitoring');
    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'احراز هویت الزامی است'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    // ── دسترسی: دقیقا همون قاعده‌ی top-delayed-users.php ──
    $me = loadUserForPermissions($db, $user_id);
    if (!hasPermission($me, 'view_all_org_tasks') && !hasPermission($me, 'view_org_dashboard_reports')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $org_id = (int) $me['organization_id'];
    if ($org_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'سازمان نامعتبر'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $kind   = $_GET['kind'] ?? '';
    $ref_id = $_GET['ref_id'] ?? '';
    if (!in_array($kind, ['user', 'section'], true) || $ref_id === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'پارامتر نامعتبر'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 🔒 اگه kind=user باشه، فقط کاربرِ فعال/حذف‌نشده‌ی همین سازمان معتبره —
    // دقیقا همون شرطی که $bucket() توی top-delayed-users.php داره؛ کارهای
    // یک کاربرِ غیرفعال/حذف‌شده نباید اینجا هم نشون داده بشن
    $targetUserId = null;
    if ($kind === 'user') {
        $targetUserId = (int) $ref_id;
        $stmt = $db->prepare("SELECT id FROM users WHERE id = ? AND organization_id = ? AND is_deleted = 0 AND is_active = 1");
        $stmt->execute([$targetUserId, $org_id]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => true, 'tasks' => []], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    $today    = date('Y-m-d');
    $now      = date('Y-m-d H:i:s');
    $holidays = getHolidaySet($db);

    // آیا این ردیفِ خام مربوط به کاربر/واحدِ درخواست‌شده هست؟ — دقیقا همون
    // منطقِ تشخیصِ user-vs-section که $bucket() توی top-delayed-users.php داره
    $matches = function ($t) use ($kind, $targetUserId, $ref_id) {
        if (!empty($t['assignee_id'])) {
            return $kind === 'user' && (int) $t['assignee_id'] === $targetUserId;
        }
        $sec = $t['activity_section'] ?: 'نامشخص';
        return $kind === 'section' && $sec === $ref_id;
    };

    $tasks = [];

    foreach (getDelayedPeriodicTasks($db, $org_id, $today) as $t) {
        if (!$matches($t)) continue;
        $delayDays = calcPeriodicDelayWorkingDays(substr($t['effective_due'], 0, 10), $today, $holidays);
        $tasks[] = [
            'id'          => (int) $t['id'],
            'title'       => $t['title'],
            'status'      => $t['status'],
            'priority'    => $t['priority'],
            'task_type'   => 'periodic',
            'due'         => $t['effective_due'],
            'delay_days'  => $delayDays,
            'delay_hours' => 0,
            'sort_hours'  => $delayDays * 24, // 🔒 فقط برای مرتب‌سازی — روزِ کاری به ساعت (مثلِ combinedHours در دشبورد)
        ];
    }

    foreach (getOpenContinuousTasks($db, $org_id) as $t) {
        if (!$matches($t)) continue;
        $state = pe_state($db, $t, $holidays, $today);
        if ($state['overdue_periods'] <= 0) continue;
        $delayDays = $state['working_days_delayed'];
        $tasks[] = [
            'id'          => (int) $t['id'],
            'title'       => $t['title'],
            'status'      => $t['status'],
            'priority'    => $t['priority'],
            'task_type'   => 'continuous',
            'due'         => $t['end_date'] ?? $t['due_date'] ?? null,
            'delay_days'  => $delayDays,
            'delay_hours' => 0,
            'sort_hours'  => $delayDays * 24,
        ];
    }

    foreach (getDelayedWorkflowTasks($db, $org_id, $now) as $t) {
        if (!$matches($t)) continue;
        $delayHours = calcHourDelay($t['effective_deadline'], $now);
        $tasks[] = [
            'id'          => (int) $t['id'],
            'title'       => $t['title'],
            'status'      => $t['status'],
            'priority'    => $t['priority'],
            'task_type'   => 'workflow',
            'due'         => $t['effective_deadline'],
            'delay_days'  => 0,
            'delay_hours' => $delayHours,
            'sort_hours'  => $delayHours,
        ];
    }

    usort($tasks, fn($a, $b) => $b['sort_hours'] <=> $a['sort_hours']);
    foreach ($tasks as &$t) {
        unset($t['sort_hours']);
    }
    unset($t);

    echo json_encode(['success' => true, 'tasks' => $tasks], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور'], JSON_UNESCAPED_UNICODE);
}
