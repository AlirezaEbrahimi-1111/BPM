<?php
// ⚠️ به‌نظر بلااستفاده می‌رسه — هیچ فراخوانی‌ای (JS fetch یا PHP require/
// include) به «sync_task» توی کلِ پروژه پیدا نشد. حذف نشده چون grep
// نمی‌تونه صددرصد یه فراخوان‌کننده‌ی خارجی/مخفی رو رد کنه؛ اگه بعد از
// مدتی لاگِ اجرا نداشت، کاندیدِ حذفه.
header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com', 'https://bpm.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('متد غیرمجاز');
    }

    require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/error_config.php';

    // 🔒 خط قرمز: این فایل قبلاً بدون هیچ احراز هویتی، اجازهٔ تغییر وضعیت
    // هر workflow instance را (در هر سازمانی) با فقط دادن یک task_id می‌داد
    $user_id = requireAuth();
    $user    = getUserInfo($user_id);
    $org_id  = $user['organization_id'];

    $database = new Database();
    $db = $database->getConnection();

    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['task_id'])) {
        throw new Exception('شناسه task الزامی است');
    }

    $task_id = (int)$input['task_id'];

    // دریافت اطلاعات task — فقط اگر متعلق به همین سازمان باشد
    $stmt = $db->prepare("SELECT * FROM tasks WHERE id = ? AND is_workflow_task = 1 AND organization_id = ?");
    $stmt->execute([$task_id, $org_id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$task) {
        throw new Exception('task یافت نشد یا جزو workflow نیست');
    }

    $db->beginTransaction();

    try {
        // بروزرسانی workflow_instance_steps
        if ($task['status'] == 'approved' || $task['status'] == 'completed') {
            // تکمیل مرحله
            $stmt = $db->prepare("
                UPDATE workflow_instance_steps 
                SET status = 'completed',
                    completed_at = NOW(),
                    completed_by = ?
                WHERE task_id = ?
            ");
            $stmt->execute([$task['assignee_id'], $task_id]);

            // دریافت instance_id
            $stmt = $db->prepare("SELECT instance_id FROM workflow_instance_steps WHERE task_id = ?");
            $stmt->execute([$task_id]);
            $step = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($step) {
                $instance_id = $step['instance_id'];

                // تعداد کل مراحل
                $stmt = $db->prepare("SELECT COUNT(*) as total FROM workflow_instance_steps WHERE instance_id = ?");
                $stmt->execute([$instance_id]);
                $total_steps = $stmt->fetch()['total'];

                // تعداد مراحل تکمیل شده
                $stmt = $db->prepare("SELECT COUNT(*) as completed FROM workflow_instance_steps WHERE instance_id = ? AND status = 'completed'");
                $stmt->execute([$instance_id]);
                $completed_steps = $stmt->fetch()['completed'];

                // بروزرسانی workflow_instances
                if ($completed_steps >= $total_steps) {
                    // همه مراحل تکمیل شده
                    $stmt = $db->prepare("
                        UPDATE workflow_instances 
                        SET status = 'completed',
                            completed_at = NOW(),
                            current_step = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$total_steps, $instance_id]);
                } else {
                    // رفتن به مرحله بعد
                    $stmt = $db->prepare("
                        UPDATE workflow_instances 
                        SET current_step = current_step + 1
                        WHERE id = ?
                    ");
                    $stmt->execute([$instance_id]);

                    // ایجاد task برای مرحله بعد
                    $stmt = $db->prepare("
                        SELECT wis.*, ws.step_name, ws.activity_section
                        FROM workflow_instance_steps wis
                        JOIN workflow_steps ws ON wis.step_id = ws.id
                        WHERE wis.instance_id = ? AND wis.step_order = ?
                    ");
                    $stmt->execute([$instance_id, $completed_steps + 1]);
                    $next_step = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($next_step) {
                        // ایجاد task جدید برای مرحله بعد
                        $stmt = $db->prepare("
                            INSERT INTO tasks 
                            (title, description, creator_id, assignee_id, activity_section,
                             organization_id, workflow_instance_id,
                             task_type, status, priority, is_workflow_task, created_at)
                            SELECT 
                                CONCAT(wi.title, ' - مرحله ', ?),
                                ?,
                                wi.created_by,
                                NULL,
                                ?,
                                wi.organization_id,
                                wi.id,
                                'periodic',
                                'not_started',
                                'medium',
                                1,
                                NOW()
                            FROM workflow_instances wi
                            WHERE wi.id = ?
                        ");
                        $stmt->execute([
                            $next_step['step_order'],
                            $next_step['step_name'],
                            $next_step['activity_section'],
                            $instance_id
                        ]);

                        $new_task_id = $db->lastInsertId();

                        // بروزرسانی task_id در workflow_instance_steps
                        $stmt = $db->prepare("
                            UPDATE workflow_instance_steps 
                            SET task_id = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([$new_task_id, $next_step['id']]);
                    }
                }
            }
        } elseif ($task['status'] == 'in_progress') {
            // شروع مرحله — فقط اگر واقعاً نوبتش رسیده باشد
            // (مرحله‌ای که هنوز pending است، نباید با شروع کار active شود؛
            //  فعال‌سازی مراحل آبشاری فقط از طریق completeStep انجام می‌شود)
            $stmt = $db->prepare("
                UPDATE workflow_instance_steps 
                SET status = 'active',
                    started_at = NOW()
                WHERE task_id = ? 
                  AND started_at IS NULL
                  AND status = 'active'
            ");
            $stmt->execute([$task_id]);
            
        } elseif ($task['status'] == 'delegated') {
            // ⚠️ نقطه‌یِ ارجاع (approve-and-delegate.php، TaskManager::delegateTask)
            // دیگه status='delegated' ست نمی‌کنه، همیشه 'not_started' می‌شه —
            // این شرط از این به بعد فقط برایِ رکوردهایِ قدیمیِ باقی‌مونده
            // فعاله؛ عمداً 'not_started' رو این‌جا اضافه نکردم چون اون status
            // دلایلِ دیگه‌ای هم می‌تونه داشته باشه (نه فقط تازه‌ارجاع‌شدن) و
            // این فایل به‌نظر بلااستفاده می‌رسه، نمی‌خوام رفتارِ ناشناخته‌ای
            // براش اضافه کنم
            $stmt = $db->prepare("
                UPDATE workflow_instance_steps 
                SET status = 'pending'
                WHERE task_id = ?
            ");
            $stmt->execute([$task_id]);
        }

        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => 'همگام‌سازی با موفقیت انجام شد'
        ], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
    error_log('[' . basename(__FILE__) . '] ' . $e->getMessage());
        $db->rollBack();
        throw $e;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'خطای سرور',
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
}
