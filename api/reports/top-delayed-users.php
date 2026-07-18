<?php

/**
 * ═══════════════════════════════════════════════════════════════════
 *  api/reports/top-delayed-users.php
 *  کاربران (و واحدهای) دارای بیشترین کارهای تأخیردار — سازمان جاری
 * ───────────────────────────────────────────────────────────────────
 *  سه نوع کار شمرده می‌شود:
 *    • مقطعی (periodic)   → due_date < امروز و تکمیل‌نشده
 *    • دوره‌ای (continuous) → overdue_periods > 0 (از موتور مشترک)
 *    • روتین (workflow)    → مرحلهٔ active که is_delayed = 1
 *
 *  نسبت‌دادن تأخیر:
 *    • اگر assignee_id باشد → پای همان کاربر
 *    • اگر نباشد (روتینِ ارجاع‌به‌واحد) → پای واحد (activity_section)
 *
 *  خروجی: فهرست نزولی بر اساس مجموع تأخیر، با تفکیک نوع
 * ═══════════════════════════════════════════════════════════════════
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://bpm.computeryekta.com');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/working-days-helper.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/period-engine.php';

try {
    // ── احراز هویت ───────────────────────────────────
    $auth    = new Auth();
    $user_id = $auth->getUserFromToken();
    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'احراز هویت الزامی است'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    // ── دسترسی: باید بتواند کارهای کل سازمان را ببیند ──
    $me = loadUserForPermissions($db, $user_id);
    requirePermission($me, 'view_all_org_tasks');

    $org_id = (int) $me['organization_id'];
    if ($org_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'سازمان نامعتبر'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $today    = date('Y-m-d');
    $holidays = getHolidaySet($db);

    // انباشتگر: کلید = "user:ID" یا "section:NAME"
    // مقدار = ['type'=>..., 'name'=>..., 'periodic'=>0, 'continuous'=>0, 'workflow'=>0]
    $acc = [];

    // نگاشت شناسهٔ کاربر → نام، و نام واحد → برچسب
    $userNames = [];
    $stmt = $db->prepare("
        SELECT id, first_name, last_name, activity_section
        FROM users
        WHERE organization_id = ? AND is_deleted = 0
    ");
    $stmt->execute([$org_id]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $u) {
        $userNames[(int)$u['id']] = trim($u['first_name'] . ' ' . $u['last_name']);
    }

    /** کلید کاربر یا واحد را برمی‌گرداند و در صورت نبود، می‌سازد */
    $bucket = function ($assignee_id, $section) use (&$acc, $userNames) {
        if (!empty($assignee_id) && isset($userNames[(int)$assignee_id])) {
            $key = 'user:' . (int)$assignee_id;
            if (!isset($acc[$key])) {
                $acc[$key] = [
                    'kind'       => 'user',
                    'ref_id'     => (int)$assignee_id,
                    'name'       => $userNames[(int)$assignee_id],
                    'periodic'   => 0,
                    'continuous' => 0,
                    'workflow'   => 0,
                ];
            }
            return $key;
        }
        // واحد
        $sec = $section ?: 'نامشخص';
        $key = 'section:' . $sec;
        if (!isset($acc[$key])) {
            $acc[$key] = [
                'kind'       => 'section',
                'ref_id'     => $sec,
                'name'       => $sec,
                'periodic'   => 0,
                'continuous' => 0,
                'workflow'   => 0,
            ];
        }
        return $key;
    };

    // ══════════════════════════════════════════════
    //  ۱) کارهای مقطعی تأخیردار
    // ══════════════════════════════════════════════
    $stmt = $db->prepare("
        SELECT id, assignee_id, activity_section
        FROM tasks
        WHERE organization_id = ?
          AND is_deleted = 0
          AND task_type = 'periodic'
          AND due_date < ?
          AND status NOT IN ('completed', 'approved', 'stopped')
    ");
    $stmt->execute([$org_id, $today]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $t) {
        $k = $bucket($t['assignee_id'], $t['activity_section']);
        $acc[$k]['periodic']++;
    }

    // ══════════════════════════════════════════════
    //  ۲) کارهای دوره‌ای تأخیردار (از موتور مشترک)
    // ══════════════════════════════════════════════
    $stmt = $db->prepare("
        SELECT * FROM tasks
        WHERE organization_id = ?
          AND is_deleted = 0
          AND task_type = 'continuous'
          AND status NOT IN ('completed', 'approved', 'stopped')
    ");
    $stmt->execute([$org_id]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $t) {
        $state = pe_state($db, $t, $holidays, $today);
        if ($state['overdue_periods'] > 0) {
            $k = $bucket($t['assignee_id'], $t['activity_section']);
            $acc[$k]['continuous']++;
        }
    }

    // ══════════════════════════════════════════════
    //  ۳) کارهای روتین تأخیردار (مرحلهٔ active و از موعد گذشته)
    // ══════════════════════════════════════════════
    $stmt = $db->prepare("
        SELECT t.id, t.assignee_id, t.activity_section
        FROM tasks t
        JOIN workflow_instance_steps wis ON wis.task_id = t.id
        WHERE t.organization_id = ?
          AND t.is_deleted = 0
          AND t.is_workflow_task = 1
          AND wis.status = 'active'
          AND t.deadline IS NOT NULL
          AND t.deadline < NOW()
          AND t.status NOT IN ('completed', 'approved', 'stopped')
    ");
    $stmt->execute([$org_id]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $t) {
        $k = $bucket($t['assignee_id'], $t['activity_section']);
        $acc[$k]['workflow']++;
    }

    // ── خروجی: مرتب نزولی بر اساس مجموع ──────────────
    $result = [];
    foreach ($acc as $row) {
        $total = $row['periodic'] + $row['continuous'] + $row['workflow'];
        if ($total <= 0) continue;
        $row['total'] = $total;
        $result[] = $row;
    }

    usort($result, fn($a, $b) => $b['total'] - $a['total']);

    echo json_encode([
        'success' => true,
        'users'   => $result,
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور'], JSON_UNESCAPED_UNICODE);
    error_log("top-delayed-users error: " . $e->getMessage());
}
