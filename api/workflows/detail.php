<?php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://bpm.computeryekta.com');

try {
    if (empty($_GET['id'])) {
        throw new Exception('شناسه workflow الزامی است');
    }
    
    $workflow_id = (int)$_GET['id'];
    
    require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/error_config.php';

    $user_id = requireAuth();
    $user = getUserInfo($user_id);
    $org_id = $user['organization_id'];

    $database = new Database();
    $db = $database->getConnection();
    
    // دریافت اطلاعات اصلی workflow — فیلتر بر اساس organization_id
    $sql = "SELECT 
                wi.id,
                wi.title,
                wt.description,
                wi.status,
                wi.current_step,
                wi.started_at,
                wi.completed_at,
                wi.created_at,
                creator.first_name as creator_first_name,
                creator.last_name as creator_last_name,
                (SELECT COUNT(*) FROM workflow_instance_steps WHERE instance_id = wi.id) as total_stages,
                (SELECT ws.step_name 
                 FROM workflow_instance_steps wis
                 JOIN workflow_steps ws ON wis.step_id = ws.id
                 WHERE wis.instance_id = wi.id AND wis.step_order = wi.current_step 
                 LIMIT 1) as current_stage_name,
                ROUND((
                    (SELECT COUNT(*) FROM workflow_instance_steps 
                     WHERE instance_id = wi.id AND status = 'completed') 
                    / 
                    (SELECT COUNT(*) FROM workflow_instance_steps WHERE instance_id = wi.id)
                ) * 100) as progress
            FROM workflow_instances wi
            LEFT JOIN workflow_templates wt ON wi.template_id = wt.id
            LEFT JOIN users creator ON wi.created_by = creator.id
            WHERE wi.id = ? AND wi.organization_id = ? AND wi.is_deleted = 0";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$workflow_id, $org_id]);
    $workflow = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$workflow) {
        throw new Exception('workflow یافت نشد');
    }
    
    // دریافت تمام مراحل با جزئیات کامل
    // زمان پیش‌بینی: بر اساس بیشترین تاریخ از (deadline, original_deadline, due_date) در جدول tasks
    // زمان واقعی: تا زمان تکمیل یا الان
    // تأخیر: بر اساس بیشترین تاریخ از tasks
    $sql = "SELECT 
                wis.id,
                wis.step_order as stage_sequence,
                ws.step_name,
                wis.status,
                ws.time_limit_hours,
                wis.task_id,
                wis.started_at,
                wis.completed_at,
                wis.deadline,
                wis.completion_notes,
                ws.activity_section,
                ws.assignee_type,
                ws.assignee_user_id,
                assignee.first_name as assignee_first_name,
                assignee.last_name  as assignee_last_name,
                (SELECT t.status FROM tasks t WHERE t.id = wis.task_id) as task_status,
                completed.first_name as completed_by_first_name,
                completed.last_name as completed_by_last_name,

                -- بیشترین تاریخ از tasks (برای استفاده در محاسبات)
                (SELECT GREATEST(
                    COALESCE(t.deadline, '1000-01-01'),
                    COALESCE(t.original_deadline, '1000-01-01'),
                    COALESCE(t.due_date, '1000-01-01')
                ) FROM tasks t WHERE t.id = wis.task_id) as effective_deadline,
                CASE
                    WHEN wis.started_at IS NOT NULL THEN
                        TIMESTAMPDIFF(MINUTE,
                            DATE_ADD(wis.started_at, INTERVAL ws.time_limit_hours HOUR),
                            COALESCE(wis.deadline, DATE_ADD(wis.started_at, INTERVAL ws.time_limit_hours HOUR))
                        )
                    ELSE 0
                END as extended_minutes,
                -- زمان پیش‌بینی: بر اساس بیشترین تاریخ از tasks
                CASE 
                    WHEN wis.started_at IS NOT NULL THEN
                        TIMESTAMPDIFF(MINUTE, wis.started_at,
                            COALESCE(wis.deadline, DATE_ADD(wis.started_at, INTERVAL ws.time_limit_hours HOUR))
                        )
                    ELSE ws.time_limit_hours * 60
                END as expected_duration_minutes,

                -- زمان واقعی
                CASE 
                    WHEN wis.completed_at IS NOT NULL THEN
                        TIMESTAMPDIFF(MINUTE, wis.started_at, wis.completed_at)
                    WHEN wis.started_at IS NOT NULL AND wis.status = 'active' THEN
                        TIMESTAMPDIFF(MINUTE, wis.started_at, NOW())
                    ELSE NULL
                END as duration_minutes,

            -- تأخیر: بر اساس موعد مؤثر (wis.deadline که با تمدید آپدیت می‌شود)
            CASE 
                WHEN wis.started_at IS NOT NULL AND wis.status = 'active' THEN
                    CASE WHEN NOW() > COALESCE(wis.deadline, DATE_ADD(wis.started_at, INTERVAL ws.time_limit_hours HOUR))
                    THEN 1 ELSE 0 END
                WHEN wis.started_at IS NOT NULL AND wis.completed_at IS NOT NULL THEN
                    CASE WHEN wis.completed_at > COALESCE(wis.deadline, DATE_ADD(wis.started_at, INTERVAL ws.time_limit_hours HOUR))
                    THEN 1 ELSE 0 END
                ELSE 0
            END as is_delayed

            FROM workflow_instance_steps wis
            LEFT JOIN workflow_steps ws ON wis.step_id = ws.id
            LEFT JOIN users completed ON wis.completed_by = completed.id
            LEFT JOIN users assignee ON (ws.assignee_type = 'user' AND ws.assignee_user_id = assignee.id)
            WHERE wis.instance_id = ?
            ORDER BY wis.step_order ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$workflow_id]);
    $steps = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'workflow' => $workflow,
        'steps' => $steps
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}