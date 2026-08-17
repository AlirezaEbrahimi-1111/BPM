<?php

/**
 * ═══════════════════════════════════════════════════════════════════
 *  API: dashboard/recent-activity.php
 *  «فعالیت‌های اخیرِ» خودِ کاربر — یک لاگِ تاریخچه‌ای (نه یک لیستِ کارهایِ
 *  بازِ فعلی؛ اون‌ها تویِ تب‌هایِ «کارهای من»/«کارهای واگذار شده» هستن).
 *
 *  دو منبع ترکیب می‌شه:
 *    ۱) task_history — همه‌ی رویدادهایِ کارهایِ معمولی و کارهایِ فرآیندی
 *       (چون هر مرحله‌ی روتین هم یک ردیفِ tasks داره)، هرجا کاربرِ جاری
 *       from_user_id یا to_user_id بوده.
 *    ۲) task_checklist_items — ارجاع/تکمیلِ آیتم‌هایِ چک‌لیستی که مستقیماً
 *       به خودِ کاربر (نه واحدش) اختصاص داده شده.
 * ═══════════════════════════════════════════════════════════════════
 */

header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

const RECENT_ACTIVITY_LIMIT = 50;
const RECENT_ACTIVITY_DAYS  = 30;

try {
    $user_id = requireAuth();

    $database = new Database();
    $db = $database->getConnection();

    $orgStmt = $db->prepare("SELECT organization_id FROM users WHERE id = ?");
    $orgStmt->execute([$user_id]);
    $org_id = (int) $orgStmt->fetchColumn();

    $activities = [];

    // ── ۱) رویدادهایِ task_history (کارهایِ معمولی + مراحلِ فرآیندی) ──
    $stmt = $db->prepare("
        SELECT
            th.task_id,
            t.title,
            t.is_workflow_task,
            th.action,
            th.created_at AS ts
        FROM task_history th
        JOIN tasks t ON t.id = th.task_id
        WHERE (th.from_user_id = :uid OR th.to_user_id = :uid2)
          AND t.organization_id = :org_id
          AND t.is_deleted = 0
          AND th.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
        ORDER BY th.created_at DESC
        LIMIT " . RECENT_ACTIVITY_LIMIT . "
    ");
    $stmt->execute([
        'uid'     => $user_id,
        'uid2'    => $user_id,
        'org_id'  => $org_id,
        'days'    => RECENT_ACTIVITY_DAYS,
    ]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $activities[] = [
            'task_id'          => (int) $row['task_id'],
            'title'            => $row['title'],
            'item_title'       => null,
            'is_workflow_task' => (int) $row['is_workflow_task'],
            'action'           => $row['action'],
            'timestamp'        => $row['ts'],
        ];
    }

    // ── ۲الف) آیتم‌هایِ چک‌لیستی که به خودِ کاربر ارجاع شدن ──
    $stmt = $db->prepare("
        SELECT ci.task_id, t.title AS task_title, t.is_workflow_task, ci.title AS item_title, ci.created_at AS ts
        FROM task_checklist_items ci
        JOIN tasks t ON t.id = ci.task_id
        WHERE ci.assignee_type = 'user'
          AND ci.assignee_value = :uid
          AND t.organization_id = :org_id
          AND t.is_deleted = 0
          AND ci.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
        ORDER BY ci.created_at DESC
        LIMIT " . RECENT_ACTIVITY_LIMIT . "
    ");
    $stmt->execute(['uid' => (string) $user_id, 'org_id' => $org_id, 'days' => RECENT_ACTIVITY_DAYS]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $activities[] = [
            'task_id'          => (int) $row['task_id'],
            'title'            => $row['task_title'],
            'item_title'       => $row['item_title'],
            'is_workflow_task' => (int) $row['is_workflow_task'],
            'action'           => 'checklist_assigned',
            'timestamp'        => $row['ts'],
        ];
    }

    // ── ۲ب) آیتم‌هایِ چک‌لیستی که خودِ کاربر تکمیل کرده ──
    $stmt = $db->prepare("
        SELECT ci.task_id, t.title AS task_title, t.is_workflow_task, ci.title AS item_title, ci.done_at AS ts
        FROM task_checklist_items ci
        JOIN tasks t ON t.id = ci.task_id
        WHERE ci.done_by = :uid
          AND ci.is_done = 1
          AND ci.done_at IS NOT NULL
          AND t.organization_id = :org_id
          AND t.is_deleted = 0
          AND ci.done_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
        ORDER BY ci.done_at DESC
        LIMIT " . RECENT_ACTIVITY_LIMIT . "
    ");
    $stmt->execute(['uid' => $user_id, 'org_id' => $org_id, 'days' => RECENT_ACTIVITY_DAYS]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $activities[] = [
            'task_id'          => (int) $row['task_id'],
            'title'            => $row['task_title'],
            'item_title'       => $row['item_title'],
            'is_workflow_task' => (int) $row['is_workflow_task'],
            'action'           => 'checklist_done',
            'timestamp'        => $row['ts'],
        ];
    }

    // ── ادغام و مرتب‌سازیِ نزولی بر اساسِ زمان ──
    usort($activities, fn($a, $b) => strcmp($b['timestamp'], $a['timestamp']));
    $activities = array_slice($activities, 0, RECENT_ACTIVITY_LIMIT);

    echo json_encode(['success' => true, 'activities' => $activities], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    error_log("dashboard/recent-activity.php failed | " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطای سرور'], JSON_UNESCAPED_UNICODE);
}
