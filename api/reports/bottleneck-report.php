<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  bottleneck-report.php — تحلیل گلوگاه‌های فرآیندهای سازمان
 *  محل: /api/reports/bottleneck-report.php
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
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';

try {
    // ── احراز هویت (همان الگوی top-delayed-users) ──────
    $auth    = new Auth();
    $user_id = $auth->getUserFromToken();
    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'احراز هویت الزامی است'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    $me = loadUserForPermissions($db, $user_id);
    if (!hasPermission($me, 'monitor_all_workflows') && !hasPermission($me, 'view_org_dashboard_reports')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $orgId = (int) $me['organization_id'];

    /* ═══════════════════════════════════════════════════
       کوئری اصلی — مراحلِ فعالِ از موعد گذشته
       ═══════════════════════════════════════════════════ */
    $sql = "
        SELECT
            ws.step_name,
            ws.activity_section,
            wt.id   AS template_id,
            wt.name AS template_name,

            wi.id        AS instance_id,
            wi.title     AS instance_title,
            wis.started_at,

            -- موعد مؤثر مرحله
            COALESCE(
                wis.deadline,
                DATE_ADD(wis.started_at, INTERVAL ws.time_limit_hours HOUR)
            ) AS effective_deadline,

            -- ساعت تأخیر تا این لحظه
            TIMESTAMPDIFF(
                HOUR,
                COALESCE(wis.deadline, DATE_ADD(wis.started_at, INTERVAL ws.time_limit_hours HOUR)),
                NOW()
            ) AS delay_hours

        FROM workflow_instance_steps wis
        JOIN workflow_steps ws        ON ws.id = wis.step_id
        JOIN workflow_instances wi    ON wi.id = wis.instance_id
        LEFT JOIN workflow_templates wt ON wt.id = wi.template_id

        WHERE wi.organization_id = ?
          AND wi.is_deleted = 0
          AND wi.status NOT IN ('completed', 'cancelled')
          AND wis.status = 'active'
          AND wis.started_at IS NOT NULL
          AND NOW() > COALESCE(
                wis.deadline,
                DATE_ADD(wis.started_at, INTERVAL ws.time_limit_hours HOUR)
              )
        ORDER BY delay_hours DESC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute([$orgId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /* ═══════════════════════════════════════════════════
       گروه‌بندی بر اساس (قالب + مرحله)
       ═══════════════════════════════════════════════════ */
    $groups = [];

    foreach ($rows as $r) {
        // کلید یکتا: قالب + نام مرحله
        $key = ($r['template_id'] ?? '0') . '::' . $r['step_name'];

        if (!isset($groups[$key])) {
            $groups[$key] = [
                'step_name'        => $r['step_name'],
                'template_id'      => $r['template_id'] ? (int) $r['template_id'] : null,
                'template_name'    => $r['template_name'] ?? 'نامشخص',
                'activity_section' => $r['activity_section'],
                'count'            => 0,
                'total_delay'      => 0,
                'max_delay'        => 0,
                'instances'        => [],
            ];
        }

        $delay = max(0, (int) $r['delay_hours']);

        $groups[$key]['count']++;
        $groups[$key]['total_delay'] += $delay;
        $groups[$key]['max_delay'] = max($groups[$key]['max_delay'], $delay);
        $groups[$key]['instances'][] = [
            'instance_id'    => (int) $r['instance_id'],
            'instance_title' => $r['instance_title'],
            'started_at'     => $r['started_at'],
            'delay_hours'    => $delay,
        ];
    }

    /* ═══════════════════════════════════════════════════
       محاسبهٔ میانگین و شدت + مرتب‌سازی
       ═══════════════════════════════════════════════════ */
    $result = [];

    foreach ($groups as $g) {
        $avgDelay = $g['count'] > 0 ? round($g['total_delay'] / $g['count'], 1) : 0;

        // شدت = تعداد درگیر × میانگین تأخیر (برای مرتب‌سازی)
        $severity = $g['count'] * $avgDelay;

        $result[] = [
            'step_name'        => $g['step_name'],
            'template_id'      => $g['template_id'],
            'template_name'    => $g['template_name'],
            'activity_section' => $g['activity_section'],
            'count'            => $g['count'],
            'avg_delay_hours'  => $avgDelay,
            'max_delay_hours'  => $g['max_delay'],
            'severity'         => round($severity, 1),
            'instances'        => $g['instances'],
        ];
    }

    // نزولی بر اساس شدت
    usort($result, fn($a, $b) => $b['severity'] <=> $a['severity']);

    /* ═══════════════════════════════════════════════════
       خلاصهٔ کلی
       ═══════════════════════════════════════════════════ */
    $totalStuck   = array_sum(array_column($result, 'count'));
    $worstStage   = $result[0]['step_name'] ?? null;
    $worstCount   = $result[0]['count'] ?? 0;

    echo json_encode([
        'success' => true,
        'summary' => [
            'bottleneck_stages' => count($result),   // تعداد مراحل گلوگاه
            'total_stuck'       => $totalStuck,       // کل روتین‌های گیرکرده
            'worst_stage'       => $worstStage,       // بدترین مرحله
            'worst_count'       => $worstCount,
        ],
        'bottlenecks' => $result,
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    error_log("bottleneck-report.php failed | " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'خطای سرور: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
