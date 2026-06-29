<?php
// api/workflows/list.php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://bpm.computeryekta.com');

error_reporting(0);
ini_set('display_errors', 0);

try {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
    
    $database = new Database();
    $db = $database->getConnection();
    
    // ✅ باید بشه — فقط workflows سازمان کاربر جاری
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
    
    $user_id = requireAuth();
    $user = getUserInfo($user_id);
    $org_id = $user['organization_id'];

    // دسترسی: مدیریت/سرپرست همه را می‌بینند؛ بقیه فقط روتین‌هایی که مرحلهٔ فعالشان مالِ واحد اوست
    $role      = $user['role'] ?? 'employee';
    $u_section = $user['activity_section'] ?? null;
    $isManager = ((int)$user_id === 1) || in_array($role, ['management', 'supervisor']);

    $visibilityCond = '';
    $execParams = ['org_id' => $org_id];
    if (!$isManager) {
        $visibilityCond = " AND EXISTS (
            SELECT 1 FROM workflow_instance_steps wisV
            JOIN workflow_steps wsV ON wisV.step_id = wsV.id
            WHERE wisV.instance_id = wi.id
              AND wisV.status = 'active'
              AND wsV.activity_section = :usec
        )";
        $execParams['usec'] = $u_section;
    }

    $sql = "SELECT
                wi.id,
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
                AND wis.status = 'active'
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
                        AND wis2.status = 'active'
                        AND wis2.started_at IS NOT NULL
                        AND NOW() > COALESCE(
                            wis2.deadline,
                            DATE_ADD(wis2.started_at, INTERVAL ws2.time_limit_hours HOUR)
                        )
                    ) THEN 1
                    ELSE 0
                END as is_delayed,
                (SELECT u.first_name 
                 FROM workflow_instance_steps wis
                 JOIN workflow_steps ws ON wis.step_id = ws.id
                 LEFT JOIN users u ON ws.activity_section = u.activity_section
                 WHERE wis.instance_id = wi.id AND wis.status = 'active'
                 LIMIT 1) as assignee_first_name,
                (SELECT u.last_name 
                 FROM workflow_instance_steps wis
                 JOIN workflow_steps ws ON wis.step_id = ws.id
                 LEFT JOIN users u ON ws.activity_section = u.activity_section
                 WHERE wis.instance_id = wi.id AND wis.status = 'active'
                 LIMIT 1) as assignee_last_name
            FROM workflow_instances wi
            LEFT JOIN workflow_templates wt ON wi.template_id = wt.id
            LEFT JOIN users creator ON wi.created_by = creator.id
            WHERE wi.organization_id = :org_id
            AND wi.is_deleted = 0
            $visibilityCond
            ORDER BY
            CASE 
                WHEN EXISTS (
                    SELECT 1 FROM workflow_instance_steps wis2
                    JOIN workflow_steps ws2 ON wis2.step_id = ws2.id
                    WHERE wis2.instance_id = wi.id 
                    AND wis2.status = 'active'
                    AND wis2.started_at IS NOT NULL
                    AND NOW() > COALESCE(
                        wis2.deadline,
                        DATE_ADD(wis2.started_at, INTERVAL ws2.time_limit_hours HOUR)
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
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}