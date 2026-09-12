<?php
// api/workflows/list.php
header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));

error_reporting(0);
ini_set('display_errors', 0);

try {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';

    $database = new Database();
    $db = $database->getConnection();

    // ✅ باید بشه — فقط workflows سازمان کاربر جاری
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/user-sections.php';

    $user_id = requireAuth();
    $user = getUserInfo($user_id);
    $org_id = $user['organization_id'];

    // دسترسی: مدیریت/سرپرست همه را می‌بینند؛ بقیه فقط روتین‌هایی که مرحلهٔ فعالشان مالِ واحد اوست.
    // ⚠️ personal_only=1 این قاعده رو برایِ مدیر/سرپرست/سوپرادمین هم کنار می‌ذاره —
    // برایِ تبِ «فعالیت‌های اخیر»ِ خودِ کاربر در داشبورد (که نباید کلِ سازمان رو نشون بده)،
    // برخلافِ صفحه‌ی نظارتِ کاملِ روتین‌ها (workflow-monitor.php) که همون دیدِ کاملِ
    // مدیریتی رو عمداً می‌خواد و بدونِ این پارامتر صدا می‌زنه.
    $role         = $user['role'] ?? 'employee';
    $personalOnly = (($_GET['personal_only'] ?? '') === '1');
    $isManager    = !$personalOnly && (in_array((int)$user_id, getSuperAdminIds(), true) || in_array($role, ['management', 'supervisor']));

    $visibilityCond = '';
    $execParams = ['org_id' => $org_id];
    if (!$isManager) {
        // 🆕 عضویت در هر یک از واحدهایِ کاربر (چندواحدی) — نه فقط واحدِ اصلی
        $userSections = us_getUserSections($db, $user_id);
        if (empty($userSections)) {
            $secCond = 'NULL';
        } else {
            $secKeys = [];
            foreach ($userSections as $i => $sec) {
                $key = "usec{$i}";
                $secKeys[] = ":{$key}";
                $execParams[$key] = $sec;
            }
            $secCond = implode(',', $secKeys);
        }
        $visibilityCond = " AND (
            EXISTS (
                SELECT 1 FROM workflow_instance_steps wisV
                JOIN workflow_steps wsV ON wisV.step_id = wsV.id
                LEFT JOIN tasks tV ON tV.id = wisV.task_id
                WHERE wisV.instance_id = wi.id
                  -- 🔒 'active' تنها لحظه‌ی کوتاهیه؛ به‌محضِ گذشتنِ ددلاین، یه
                  -- کرون (checkDelays در WorkflowManager.php) وضعیتِ مرحله رو
                  -- برایِ همیشه به 'delayed' تغییر می‌ده. تویِ دیتابیسِ واقعی،
                  -- تقریباً همه‌ی مراحلِ «در جریان» همین الان delayed هستن، نه
                  -- active (۶۷ به ۱) — پس هرجا فقط status='active' چک بشه، عملاً
                  -- تقریباً هیچی رو نمی‌بینه
                  AND wisV.status IN ('active', 'delayed')
                  AND (
                      wsV.activity_section IN ($secCond)
                      -- 🆕 مرحله‌ای که مستقیم به یه کاربرِ خاص واگذار شده (نه به یه
                      -- واحد) هم باید دیده بشه — قبلاً فقط activity_section چک
                      -- می‌شد، پس مرحله‌ای که assignee_type='user' بود (یا با
                      -- ارجاع، مسئولِ واقعی‌اش عوض شده بود) هیچ‌وقت اینجا دیده
                      -- نمی‌شد، حتی اگه همین لحظه رویِ میزِ خودِ کاربر بود
                      OR COALESCE(tV.assignee_id, CASE WHEN wsV.assignee_type = 'user' THEN wsV.assignee_user_id END) = :ucurrentassignee
                  )
            )
            OR wi.created_by = :ucreator
        )";
        // 🔒 نه :ucreator با مقدارِ تکراری: این کانکشن با پریپِرهایِ نیتیو
        // (نه emulated) کار می‌کنه، پس یک نامِ placeholder نمی‌تونه دوبار
        // در یک کوئری استفاده بشه — even اگه مقدارش یکی باشه
        $execParams['ucreator'] = $user_id;
        $execParams['ucurrentassignee'] = $user_id;
    }

    $sql = "SELECT
                wi.id,
                wi.created_by,
                wi.title,
                wt.description,
                wi.status,
                wi.execution_mode,
                wi.current_step as current_stage,
                wi.started_at,
                wi.completed_at,
                wt.id AS workflow_id,
                (
                SELECT GROUP_CONCAT(DISTINCT ws.activity_section)
                FROM workflow_steps ws
                WHERE ws.template_id = wi.template_id
                AND ws.activity_section IS NOT NULL
                ) AS sections_csv,
                (SELECT ws.activity_section
                FROM workflow_instance_steps wis
                JOIN workflow_steps ws ON wis.step_id = ws.id
                WHERE wis.instance_id = wi.id
                AND wis.status IN ('active', 'delayed')
                ORDER BY wis.step_order ASC
                LIMIT 1
                ) AS current_section,
                wi.started_at as updated_at,
                creator.first_name as creator_first_name,
                creator.last_name as creator_last_name,
                (SELECT COUNT(*) FROM workflow_instance_steps WHERE instance_id = wi.id) as total_stages,
                (SELECT ws.step_name 
                 FROM workflow_instance_steps wis
                 JOIN workflow_steps ws ON wis.step_id = ws.id
                 WHERE wis.instance_id = wi.id AND wis.step_order = wi.current_step 
                 LIMIT 1) as current_stage_name,
                wi.current_step as current_stage_sequence,
                ROUND((
                    (SELECT COUNT(*) FROM workflow_instance_steps 
                     WHERE instance_id = wi.id AND status = 'completed') 
                    / 
                    NULLIF((SELECT COUNT(*) FROM workflow_instance_steps WHERE instance_id = wi.id), 0)
                ) * 100) as progress,
                CASE
                    WHEN EXISTS (
                        SELECT 1 FROM workflow_instance_steps wis2
                        JOIN workflow_steps ws2 ON wis2.step_id = ws2.id
                        WHERE wis2.instance_id = wi.id
                        AND (
                            -- 🔒 کرون (checkDelays) وضعیتِ مرحله رو دائمی به
                            -- 'delayed' تغییر می‌ده — پس اگه همین الان delayed
                            -- هست، دیگه لازم نیست ددلاین رو دوباره زنده محاسبه کنیم
                            wis2.status = 'delayed'
                            OR (
                                wis2.status = 'active'
                                AND wis2.started_at IS NOT NULL
                                AND NOW() > COALESCE(
                                    wis2.deadline,
                                    DATE_ADD(wis2.started_at, INTERVAL ws2.time_limit_hours HOUR)
                                )
                            )
                        )
                    ) THEN 1
                    ELSE 0
                END as is_delayed,
                -- 🔒 مسئولِ واقعیِ الان: تسکِ زیرینِ مرحله رو چک می‌کنه (اگه claim یا
                -- به فردِ دیگه‌ای ارجاع شده، assignee_id واقعیِ اونه)، نه یه کاربرِ
                -- دلبخواه که واحدش با واحدِ قالب یکی بوده (باگِ قبلی)
                (SELECT u.first_name
                 FROM workflow_instance_steps wis
                 JOIN workflow_steps ws ON wis.step_id = ws.id
                 LEFT JOIN tasks t ON t.id = wis.task_id
                 LEFT JOIN users u ON u.id = COALESCE(t.assignee_id, CASE WHEN ws.assignee_type = 'user' THEN ws.assignee_user_id END) AND u.is_active = 1
                 WHERE wis.instance_id = wi.id AND wis.status IN ('active', 'delayed')
                 LIMIT 1) as assignee_first_name,
                (SELECT u.last_name
                 FROM workflow_instance_steps wis
                 JOIN workflow_steps ws ON wis.step_id = ws.id
                 LEFT JOIN tasks t ON t.id = wis.task_id
                 LEFT JOIN users u ON u.id = COALESCE(t.assignee_id, CASE WHEN ws.assignee_type = 'user' THEN ws.assignee_user_id END) AND u.is_active = 1
                 WHERE wis.instance_id = wi.id AND wis.status IN ('active', 'delayed')
                 LIMIT 1) as assignee_last_name
            FROM workflow_instances wi
            LEFT JOIN workflow_templates wt ON wi.template_id = wt.id
            LEFT JOIN users creator ON wi.created_by = creator.id AND creator.is_active = 1
            WHERE wi.organization_id = :org_id
            AND wi.is_deleted = 0
            $visibilityCond
            ORDER BY
            CASE
                WHEN EXISTS (
                    SELECT 1 FROM workflow_instance_steps wis2
                    JOIN workflow_steps ws2 ON wis2.step_id = ws2.id
                    WHERE wis2.instance_id = wi.id
                    AND (
                        wis2.status = 'delayed'
                        OR (
                            wis2.status = 'active'
                            AND wis2.started_at IS NOT NULL
                            AND NOW() > COALESCE(
                                wis2.deadline,
                                DATE_ADD(wis2.started_at, INTERVAL ws2.time_limit_hours HOUR)
                            )
                        )
                    )
                ) THEN 1
                    WHEN wi.status = 'in_progress' THEN 2
                    WHEN wi.status = 'completed' THEN 3
                    ELSE 4
                END,
                wi.started_at DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($execParams);
    $workflows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($workflows as &$workflow) {
        if ($workflow['is_delayed'] == 1 && $workflow['status'] == 'in_progress') {
            $workflow['status'] = 'delayed';
        } elseif ($workflow['is_delayed'] == 0 && $workflow['status'] == 'delayed') {
            // دیگر مرحلهٔ فعالِ تأخیری ندارد → از حالت گلوگاه خارج شود (نمایشی)
            $workflow['status'] = 'in_progress';
        }
        $workflow['priority'] = 'medium';

        // ✅ این دو خط را اضافه کن:
        $workflow['workflow_id'] = (int)$workflow['workflow_id'];
        $workflow['sections'] = !empty($workflow['sections_csv'])
            ? explode(',', $workflow['sections_csv'])
            : [];
        unset($workflow['sections_csv']);
    }

    echo json_encode([
        'success' => true,
        'workflows' => $workflows
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('[' . basename(__FILE__) . '] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'خطای سرور'
    ], JSON_UNESCAPED_UNICODE);
}
