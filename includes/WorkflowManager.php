<?php
require_once __DIR__ . '/user-sections.php';
class WorkflowManager
{
    private $db;

    public function __construct($database)
    {
        $this->db = $database;
    }

    public function createTemplate($data, $creator_id)
    {
        try {
            $this->db->beginTransaction();
            // ایجاد template
            $stmt = $this->db->prepare("INSERT INTO workflow_templates (name, description, is_active, created_by, organization_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['name'],
                $data['description'] ?? '',
                $data['is_active'] ?? 1,
                $creator_id,
                $data['organization_id']
            ]);

            $template_id = $this->db->lastInsertId();
            // ایجاد مراحل
            if (!empty($data['steps'])) {
                foreach ($data['steps'] as $step) {
                    $this->addStep($template_id, $step);
                }
            }

            $this->db->commit();
            return ['success' => true, 'template_id' => $template_id, 'message' => 'الگوی کار روتین با موفقیت ایجاد شد'];
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("CreateTemplate error: " . $e->getMessage());
            return ['success' => false, 'message' => 'خطا: ' . $e->getMessage()];  // موقت
        }
    }
    private function updateStep($step_id, $step_data, $step_order)
    {
        $assignee_type = $step_data['assignee_type'] ?? 'section';
        $assignee_user_id = null;
        $activity_section = $step_data['activity_section'] ?? null;

        if ($assignee_type === 'user' && !empty($step_data['assignee_value'])) {
            $assignee_user_id = (int)$step_data['assignee_value'];
            $stmt = $this->db->prepare("SELECT activity_section FROM users WHERE id = ?");
            $stmt->execute([$assignee_user_id]);
            $u = $stmt->fetch(PDO::FETCH_ASSOC);
            $activity_section = $u ? $u['activity_section'] : $activity_section;
        } elseif ($assignee_type === 'creator') {
            // 🆕 مسئول = ایجادکنندهٔ روتین
            $assignee_user_id = null;
            $activity_section = null;
        } else {
            $assignee_type = 'section';
            $activity_section = $step_data['assignee_value'] ?? $activity_section;
        }

        $execution_mode = (($step_data['execution_mode'] ?? 'cascade') === 'parallel') ? 'parallel' : 'cascade';

        $stmt = $this->db->prepare("
            UPDATE workflow_steps
            SET step_order = ?, step_name = ?, activity_section = ?, time_limit_hours = ?,
                assignee_type = ?, assignee_user_id = ?, execution_mode = ?
            WHERE id = ?
        ");
        return $stmt->execute([
            $step_order,
            $step_data['step_name'],
            $activity_section,
            $step_data['time_limit_hours'] ?? 24,
            $assignee_type,
            $assignee_user_id,
            $execution_mode,
            $step_id
        ]);
    }
    // اضافه کردن مرحله به الگو
    private function addStep($template_id, $step_data)
    {
        $assignee_type = $step_data['assignee_type'] ?? 'section';
        $assignee_user_id = null;
        $activity_section = $step_data['activity_section'] ?? null;

        if ($assignee_type === 'user' && !empty($step_data['assignee_value'])) {
            $assignee_user_id = (int)$step_data['assignee_value'];
            // واحدِ فعلیِ همان کاربر به‌صورتِ خودکار به‌عنوانِ بازگشتِ پیش‌فرض ذخیره می‌شود
            $stmt = $this->db->prepare("SELECT activity_section FROM users WHERE id = ?");
            $stmt->execute([$assignee_user_id]);
            $u = $stmt->fetch(PDO::FETCH_ASSOC);
            $activity_section = $u ? $u['activity_section'] : $activity_section;
        } elseif ($assignee_type === 'creator') {
            // 🆕 مسئول = ایجادکنندهٔ روتین (موقع اجرا معلوم می‌شود)
            $assignee_user_id = null;
            $activity_section = '';   // رشتهٔ خالی به‌جای NULL (ستون NOT NULL است)
        } else {
            $assignee_type = 'section';
            $activity_section = $step_data['assignee_value'] ?? $activity_section;
        }

        $execution_mode = (($step_data['execution_mode'] ?? 'cascade') === 'parallel') ? 'parallel' : 'cascade';
        $stmt = $this->db->prepare("INSERT INTO workflow_steps (template_id, step_order, step_name, activity_section, time_limit_hours, assignee_type, assignee_user_id, execution_mode) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        return $stmt->execute([
            $template_id,
            $step_data['step_order'],
            $step_data['step_name'],
            $activity_section,
            $step_data['time_limit_hours'] ?? 24,
            $assignee_type,
            $assignee_user_id,
            $execution_mode
        ]);
    }

    // دریافت لیست الگوهای فعال
    public function getActiveTemplates($organization_id)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT wt.*, 
                       u.first_name as creator_first_name, 
                       u.last_name as creator_last_name,
                       (SELECT COUNT(*) FROM workflow_steps WHERE template_id = wt.id) as steps_count,
                       (SELECT COUNT(*) FROM workflow_instances 
                          WHERE template_id = wt.id 
                            AND is_deleted = 0 
                            AND status NOT IN ('completed','cancelled')) as active_instances
                FROM workflow_templates wt
                LEFT JOIN users u ON wt.created_by = u.id
                WHERE wt.is_active = 1 AND wt.organization_id = ?
                ORDER BY wt.created_at DESC
            ");
            $stmt->execute([$organization_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("GetActiveTemplates error: " . $e->getMessage());
            return [];
        }
    }
    // دریافت همهٔ الگوها (فعال + غیرفعال) — مخصوص صفحهٔ مدیریت
    public function getAllTemplates($organization_id)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT wt.*, 
                       u.first_name as creator_first_name, 
                       u.last_name as creator_last_name,
                       (SELECT COUNT(*) FROM workflow_steps WHERE template_id = wt.id) as steps_count,
                       (SELECT COUNT(*) FROM workflow_instances 
                          WHERE template_id = wt.id 
                            AND is_deleted = 0 
                            AND status NOT IN ('completed','cancelled')) as active_instances
                FROM workflow_templates wt
                LEFT JOIN users u ON wt.created_by = u.id
                WHERE wt.organization_id = ?
                ORDER BY wt.is_active DESC, wt.created_at DESC
            ");
            $stmt->execute([$organization_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("GetAllTemplates error: " . $e->getMessage());
            return [];
        }
    }
    // فعال/غیرفعال کردن قالب (روتین‌های در حال اجرا دست‌نخورده می‌مانند)
    public function setTemplateActive($template_id, $organization_id, $is_active)
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE workflow_templates
                SET is_active = ?, updated_at = NOW()
                WHERE id = ? AND organization_id = ?
            ");
            $stmt->execute([$is_active ? 1 : 0, $template_id, $organization_id]);

            if ($stmt->rowCount() === 0) {
                // یا قالب وجود ندارد، یا متعلق به سازمانِ دیگری است، یا مقدار تغییری نکرده
                // برای اطمینان، وجودِ قالب را چک می‌کنیم
                $chk = $this->db->prepare("SELECT COUNT(*) FROM workflow_templates WHERE id = ? AND organization_id = ?");
                $chk->execute([$template_id, $organization_id]);
                if ((int)$chk->fetchColumn() === 0) {
                    return ['success' => false, 'message' => 'قالب یافت نشد یا دسترسی ندارید'];
                }
            }

            return [
                'success'   => true,
                'is_active' => $is_active ? 1 : 0,
                'message'   => $is_active ? 'قالب فعال شد' : 'قالب غیرفعال شد'
            ];
        } catch (Exception $e) {
            error_log("SetTemplateActive error: " . $e->getMessage());
            return ['success' => false, 'message' => 'خطا در تغییر وضعیت قالب'];
        }
    }
    // دریافت جزئیات الگو با مراحل آن
    public function getTemplateDetails($template_id, $organization_id)
    {
        $stmt = $this->db->prepare("SELECT * FROM workflow_templates WHERE id = ? AND organization_id = ?");
        $stmt->execute([$template_id, $organization_id]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($template) {
            // دریافت مراحل الگو
            $stmt = $this->db->prepare("
        SELECT ws.id, ws.template_id, ws.step_order, ws.step_name, ws.step_description,
               ws.activity_section, ws.time_limit_hours, ws.assignee_type, ws.assignee_user_id,
               ws.execution_mode,
               CONCAT(u.first_name, ' ', u.last_name) AS assignee_user_name
        FROM workflow_steps ws
        LEFT JOIN users u ON u.id = ws.assignee_user_id
        WHERE ws.template_id = ?
        ORDER BY ws.step_order
    ");
            $stmt->execute([$template_id]);
            $template['steps'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return $template;
    }


    // بروزرسانی الگو
    public function updateTemplate($template_id, $data)
    {
        try {
            // 🔒 قفلِ دائمی: ویرایش/جابه‌جاییِ قالب پس از ایجاد ممنوع است. برای تغییر، از «بازتعریف» استفاده کنید.
            return [
                'success' => false,
                'locked'  => true,
                'message' => 'ویرایش یا جابه‌جایی مراحلِ قالب امکان‌پذیر نیست. برای تغییر، از «بازتعریف» یک نسخهٔ جدید بسازید.'
            ];

            // ⬇️ کدِ قدیمی غیرفعال شد (هرگز اجرا نمی‌شود)
            $this->db->beginTransaction();

            // بروزرسانی template
            $stmt = $this->db->prepare("UPDATE workflow_templates SET name = ?, description = ?, is_active = ?, updated_at = NOW() WHERE id = ? AND organization_id = ?");
            $stmt->execute([
                $data['name'],
                $data['description'] ?? '',
                $data['is_active'] ?? 1,
                $template_id,
                $data['organization_id']
            ]);

            $newSteps = array_values($data['steps'] ?? []);

            // idهای مراحلِ فعلی (به‌ترتیب) — برای حفظِ id و جلوگیری از یتیم‌شدنِ روتین‌های در حال اجرا
            $stmt = $this->db->prepare("SELECT id FROM workflow_steps WHERE template_id = ? ORDER BY step_order ASC, id ASC");
            $stmt->execute([$template_id]);
            $existingIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // مراحلِ موجود را با همان id به‌روزرسانی کن؛ مرحله‌های جدید را اضافه کن
            foreach ($newSteps as $i => $step) {
                if (isset($existingIds[$i])) {
                    $this->updateStep($existingIds[$i], $step, $i + 1);
                } else {
                    $step['step_order'] = $i + 1;
                    $this->addStep($template_id, $step);
                }
            }

            // اگر تعداد مراحل کم شده، فقط مرحله‌های اضافیِ قدیمی را حذف کن
            if (count($existingIds) > count($newSteps)) {
                $toDelete = array_slice($existingIds, count($newSteps));
                $ph = implode(',', array_fill(0, count($toDelete), '?'));
                $this->db->prepare("DELETE FROM workflow_steps WHERE id IN ($ph)")->execute($toDelete);
            }

            $this->db->commit();
            return ['success' => true, 'message' => 'الگو با موفقیت بروزرسانی شد'];
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("UpdateTemplate error: " . $e->getMessage());
            return ['success' => false, 'message' => 'خطا در بروزرسانی الگو'];
        }
    }

    // حذف الگو
    public function deleteTemplate($template_id, $organization_id)
    {
        try {
            // 🔒 قفلِ دائمی: حذفِ قالب ممنوع است. به‌جای حذف، قالب را «غیرفعال» کنید.
            return [
                'success' => false,
                'locked'  => true,
                'message' => 'حذفِ قالب امکان‌پذیر نیست. برای کنار گذاشتنِ یک قالب، آن را «غیرفعال» کنید.'
            ];
            // ↓↓↓ کدِ قدیمی دیگر اجرا نمی‌شود ↓↓↓

            // حذف الگو (مراحل به صورت خودکار حذف می‌شوند به دلیل CASCADE)
            $stmt = $this->db->prepare("DELETE FROM workflow_templates WHERE id = ? AND organization_id = ?");
            $stmt->execute([$template_id, $organization_id]);

            return ['success' => true, 'message' => 'الگو با موفقیت حذف شد'];
        } catch (Exception $e) {
            error_log("DeleteTemplate error: " . $e->getMessage());
            return ['success' => false, 'message' => 'خطا در حذف الگو'];
        }
    }

    // ====================================
    // مدیریت اجرای Workflow
    // ====================================

    // ✅ متد اصلاح شده - شروع یک workflow جدید
    public function startWorkflow($template_id, $title, $creator_id, $organization_id = null, $execution_mode = 'cascade')
    {
        // فقط دو حالت مجاز
        $execution_mode = in_array($execution_mode, ['cascade', 'parallel']) ? $execution_mode : 'cascade';
        try {
            $this->db->beginTransaction();
            // ✅ محافظ: بدون سازمان، کار روتین نباید ساخته شود
            if (empty($organization_id)) {
                throw new Exception('شناسهٔ سازمان مشخص نیست');
            }

            // 🔒 خط قرمز: قالب باید متعلق به همین سازمان باشد، وگرنه یک سازمان
            // می‌تواند با حدسِ template_id، از ساختار/مراحلِ قالبِ خصوصیِ سازمان
            // دیگر برای ساختن یک نمونهٔ اجراییِ خودش استفاده کند
            $tmplCheck = $this->db->prepare("SELECT id FROM workflow_templates WHERE id = ? AND organization_id = ?");
            $tmplCheck->execute([$template_id, $organization_id]);
            if (!$tmplCheck->fetch()) {
                throw new Exception('قالب یافت نشد یا متعلق به سازمان شما نیست');
            }

            // دریافت مراحل الگو
            $stmt = $this->db->prepare("
                SELECT * FROM workflow_steps
                WHERE template_id = ?
                ORDER BY step_order ASC
            ");
            $stmt->execute([$template_id]);
            $steps = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($steps)) {
                throw new Exception('هیچ مرحله‌ای برای این الگو تعریف نشده');
            }

            // ایجاد instance
            $stmt = $this->db->prepare("
            INSERT INTO workflow_instances (template_id, title, current_step, execution_mode, status, created_by, organization_id, started_at) 
            VALUES (?, ?, 1, ?, 'in_progress', ?, ?, NOW())
            ");
            $stmt->execute([$template_id, $title, $execution_mode, $creator_id, $organization_id]);
            $instance_id = $this->db->lastInsertId();

            // ✅ ایجاد task و رکورد در workflow_instance_steps برای همه مراحل
            $first_task_id = null;

            // 🆕 اولین مرحلهٔ آبشاری را پیدا کن (چون بر اساس step_order مرتب است، اولین موردِ cascade)
            $firstCascadeOrder = null;
            foreach ($steps as $s) {
                if (($s['execution_mode'] ?? 'cascade') === 'cascade') {
                    $firstCascadeOrder = (int)$s['step_order'];
                    break;
                }
            }

            foreach ($steps as $index => $step) {
                $is_first_step = ($index === 0);
                $step_mode = ($step['execution_mode'] ?? 'cascade');
                // 🆕 per-step: مرحلهٔ موازی همیشه از ابتدا فعال؛ آبشاری فقط اگر «اولین آبشاری» باشد
                $is_active_now = ($step_mode === 'parallel') || ((int)$step['step_order'] === $firstCascadeOrder);
                $step_status = $is_active_now ? 'active' : 'pending';
                $task_status = 'not_started';

                // محاسبه deadline
                $deadline = date('Y-m-d H:i:s', strtotime("+{$step['time_limit_hours']} hours"));

                // ✅ تشخیص مسئولِ واقعیِ این مرحله (کاربرِ مشخص، ایجادکننده، یا بازگشت به واحد)
                $resolved_assignee_id = null;
                if (($step['assignee_type'] ?? 'section') === 'user' && !empty($step['assignee_user_id'])) {
                    // 🔒 خط قرمز: مسئولِ ثابتِ مرحله هم باید از همین سازمان باشد
                    $checkStmt = $this->db->prepare("SELECT id FROM users WHERE id = ? AND is_active = 1 AND organization_id = ?");
                    $checkStmt->execute([$step['assignee_user_id'], $organization_id]);
                    if ($checkStmt->fetch()) {
                        $resolved_assignee_id = $step['assignee_user_id'];
                    }
                } elseif (($step['assignee_type'] ?? '') === 'creator') {
                    // 🆕 مسئول = ایجادکنندهٔ همین نمونهٔ روتین
                    $resolved_assignee_id = $creator_id;
                }

                // ✅ ایجاد task برای این مرحله
                // نکته: موعد با دقتِ ساعت/دقیقه باید در ستونِ `deadline` (DATETIME) ذخیره شود، نه `due_date`
                // (`due_date` از نوع DATE است و بخشِ ساعت را بی‌صدا حذف می‌کند — دقیقاً همان ستونی که
                // approve-deadline.php / request-deadline.php هم برای موعدِ کارهای روتین به‌کار می‌برند)
                $stmt = $this->db->prepare("
                    INSERT INTO tasks (
                        workflow_instance_id,
                        organization_id,
                        is_workflow_task,
                        title,
                        description,
                        creator_id,
                        activity_section,
                        assignee_id,
                        task_type,
                        priority,
                        deadline,
                        status,
                        current_stage_id
                    )
                    VALUES (?, ?, 1, ?, '', ?, ?, ?, 'periodic', 'high', ?, ?, ?)
                ");

                $stmt->execute([
                    $instance_id,
                    $organization_id,                          // ✅ سازمان — این جا افتاده بود
                    $title . ' - ' . $step['step_name'],
                    $creator_id,
                    $step['activity_section'],
                    $resolved_assignee_id,
                    $deadline,
                    $task_status,
                    $step['id']  // current_stage_id
                ]);

                $task_id = $this->db->lastInsertId();

                if ($is_first_step) {
                    $first_task_id = $task_id;
                }

                // ✅ ایجاد رکورد در workflow_instance_steps
                $stmt = $this->db->prepare("
                    INSERT INTO workflow_instance_steps (
                        instance_id,
                        step_id,
                        task_id,
                        step_order,
                        status,
                        deadline,
                        started_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)
                ");

                $started_at = $is_active_now ? date('Y-m-d H:i:s') : null;
                $stmt->execute([
                    $instance_id,
                    $step['id'],
                    $task_id,
                    $step['step_order'],
                    $step_status,
                    $deadline,
                    $started_at
                ]);

                // ✅ ثبت تاریخچه و نوتیفیکیشن برای هر مرحلهٔ فعال (موازی: همه، آبشاری: فقط اول)
                if ($is_active_now) {
                    $stmt = $this->db->prepare("
                        INSERT INTO task_history (task_id, from_user_id, action, notes) 
                        VALUES (?, ?, 'created', 'کار روتین ایجاد شد')
                    ");
                    $stmt->execute([$task_id, $creator_id]);
                    // ✅ ارسال نوتیفیکیشن به اعضای واحد این مرحله
                    // ✅ ارسال نوتیفیکیشن به مسئولِ مرحلهٔ اول (کاربرِ مشخص یا اعضای واحد)
                    try {
                        if ($resolved_assignee_id) {
                            $this->createNotification(
                                $resolved_assignee_id,
                                'info',
                                'کار روتین جدید',
                                "کار روتین '{$title} - {$step['step_name']}' ایجاد شد و آماده انجام است",
                                "/pages/task-detail.php?id={$task_id}",
                                $instance_id
                            );
                        } else {
                            $this->notifySectionMembers(
                                $step['activity_section'],
                                'info',
                                'کار روتین جدید',
                                "کار روتین '{$title} - {$step['step_name']}' ایجاد شد و آماده انجام است",
                                "/pages/task-detail.php?id={$task_id}",
                                $instance_id
                            );
                        }
                    } catch (Exception $notif_error) {
                        error_log("StartWorkflow notification error: " . $notif_error->getMessage());
                    }
                }
            }

            $this->db->commit();

            return [
                'success' => true,
                'instance_id' => $instance_id,
                'task_id' => $first_task_id,
                'message' => 'کار روتین با موفقیت ایجاد شد'
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("StartWorkflow error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا: ' . $e->getMessage()
            ];
        }
    }

    // ✅ تکمیل یک مرحله و رفتن به مرحله بعد
    public function completeStep($task_id, $user_id)
    {
        try {
            $this->db->beginTransaction();

            // دریافت اطلاعات کار
            $stmt = $this->db->prepare("SELECT * FROM tasks WHERE id = ? AND is_workflow_task = 1");
            $stmt->execute([$task_id]);
            $task = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$task) {
                throw new Exception('کار یافت نشد یا بخشی از روتین نیست');
            }

            $instance_id = $task['workflow_instance_id'];

            // دریافت اطلاعات مرحله فعلی
            $stmt = $this->db->prepare("SELECT * FROM workflow_instance_steps WHERE task_id = ?");
            $stmt->execute([$task_id]);
            $current_step = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$current_step) {
                throw new Exception('مرحله یافت نشد');
            }

            // ✅ اجازه تکمیل حتی اگر status 'pending' باشد
            if (!in_array($current_step['status'], ['active', 'pending'])) {
                throw new Exception('این مرحله قابل تکمیل نیست (وضعیت: ' . $current_step['status'] . ')');
            }

            // 1. بستن task فعلی
            $stmt = $this->db->prepare("
                UPDATE tasks 
                SET status = 'completed', updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$task_id]);

            // 2. علامت‌گذاری مرحله به عنوان تکمیل شده
            $stmt = $this->db->prepare("
                UPDATE workflow_instance_steps 
                SET status = 'completed', completed_at = NOW(), completed_by = ?
                WHERE id = ?
            ");
            $stmt->execute([$user_id, $current_step['id']]);

            // ✅ ثبت در task_history
            $stmt = $this->db->prepare("
                INSERT INTO task_history (task_id, from_user_id, action, notes) 
                VALUES (?, ?, 'completed', 'مرحله تکمیل شد')
            ");
            $stmt->execute([$task_id, $user_id]);

            // 🆕 per-step: حالتِ اجرای «همین مرحله» را از workflow_steps بخوان
            $stmt = $this->db->prepare("SELECT execution_mode FROM workflow_steps WHERE id = ?");
            $stmt->execute([$current_step['step_id']]);
            $current_mode = $stmt->fetchColumn() ?: 'cascade';

            // 3. مرحلهٔ بعدی فقط وقتی فعال می‌شود که «همین مرحله آبشاری» باشد
            //    بعدی = نزدیک‌ترین مرحلهٔ «آبشاریِ» بعدی که هنوز در انتظار (pending) است
            $next_step = null;
            if ($current_mode === 'cascade') {
                $stmt = $this->db->prepare("
                    SELECT wis.*
                    FROM workflow_instance_steps wis
                    JOIN workflow_steps ws ON ws.id = wis.step_id
                    WHERE wis.instance_id = ?
                      AND wis.step_order > ?
                      AND ws.execution_mode = 'cascade'
                      AND wis.status = 'pending'
                    ORDER BY wis.step_order ASC
                    LIMIT 1
                ");
                $stmt->execute([$instance_id, $current_step['step_order']]);
                $next_step = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            if ($next_step) {
                // ✅ موعدِ مرحلهٔ بعدی باید نسبتِ به لحظهٔ فعال‌شدنش حساب شود، نه لحظهٔ شروعِ کل روتین
                // (deadline قبلی در startWorkflow() برای همهٔ مراحل از یک "الان" مشترک محاسبه شده بود)
                $stmt = $this->db->prepare("SELECT time_limit_hours FROM workflow_steps WHERE id = ?");
                $stmt->execute([$next_step['step_id']]);
                $nextTimeLimitHours = $stmt->fetchColumn() ?: 24;
                $nextDeadline = date('Y-m-d H:i:s', strtotime("+{$nextTimeLimitHours} hours"));

                // فعال کردن مرحله بعدی
                $stmt = $this->db->prepare("
                    UPDATE workflow_instance_steps
                    SET status = 'active', started_at = NOW(), deadline = ?
                    WHERE id = ?
                ");
                $stmt->execute([$nextDeadline, $next_step['id']]);

                // فعال کردن کار مرحله بعدی
                $stmt = $this->db->prepare("
                    UPDATE tasks
                    SET status = 'in_progress', deadline = ?
                    WHERE id = ?
                ");
                $stmt->execute([$nextDeadline, $next_step['task_id']]);

                // بروزرسانی مرحله فعلی در workflow_instances
                $stmt = $this->db->prepare("
                    UPDATE workflow_instances 
                    SET current_step = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$next_step['step_order'], $instance_id]);
                // دریافت اطلاعات مرحله بعدی
                $stmt = $this->db->prepare("
                    SELECT ws.activity_section, ws.step_name, t.title, t.assignee_id
                    FROM workflow_steps ws 
                    JOIN tasks t ON t.id = ?
                    WHERE ws.id = ?
                ");
                $stmt->execute([$next_step['task_id'], $next_step['step_id']]);
                $next_info = $stmt->fetch(PDO::FETCH_ASSOC);

                // ✅ ارسال نوتیفیکیشن به مسئولِ مرحلهٔ بعدی (کاربرِ مشخص یا اعضای واحد)
                try {
                    if (!empty($next_info['assignee_id'])) {
                        $this->createNotification(
                            $next_info['assignee_id'],
                            'workflow_ready',
                            'نوبت شما رسید',
                            "کار روتین '{$next_info['title']}' - {$next_info['step_name']} آماده انجام است",
                            "/pages/task-detail.php?id={$next_step['task_id']}",
                            $instance_id
                        );
                    } else {
                        $this->notifySectionMembers(
                            $next_info['activity_section'],
                            'workflow_ready',
                            'نوبت شما رسید',
                            "کار روتین '{$next_info['title']}' - {$next_info['step_name']} آماده انجام است",
                            "/pages/task-detail.php?id={$next_step['task_id']}",
                            $instance_id
                        );
                    }
                } catch (Exception $notif_error) {
                    error_log("Notification error: " . $notif_error->getMessage());
                }

                $message = 'مرحله تکمیل شد و به مرحله بعدی منتقل شد';
            } else {
                // بررسی باقی‌ماندهٔ مراحل (در موازی، تکمیل یک مرحله لزوماً پایان روتین نیست)
                $remStmt = $this->db->prepare("
                    SELECT COUNT(*) FROM workflow_instance_steps
                    WHERE instance_id = ? AND status NOT IN ('completed', 'cancelled')
                ");
                $remStmt->execute([$instance_id]);
                $remaining = (int) $remStmt->fetchColumn();

                if ($remaining === 0) {
                    // همهٔ مراحل تمام شد → کل روتین تکمیل
                    $stmt = $this->db->prepare("
                        UPDATE workflow_instances 
                        SET status = 'completed', completed_at = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([$instance_id]);

                    $message = 'کار روتین با موفقیت تکمیل شد';

                    // ✅ نوتیفیکیشن به ایجادکننده
                    try {
                        $stmt = $this->db->prepare("
                            SELECT wi.created_by, wi.title 
                            FROM workflow_instances wi 
                            WHERE wi.id = ?
                        ");
                        $stmt->execute([$instance_id]);
                        $workflow_info = $stmt->fetch(PDO::FETCH_ASSOC);

                        if ($workflow_info) {
                            $this->createNotification(
                                $workflow_info['created_by'],
                                'workflow_completed',
                                'کار روتین تکمیل شد',
                                "کار روتین '{$workflow_info['title']}' با موفقیت تکمیل شد",
                                "../pages/workflow-detail.php?id={$instance_id}",
                                $instance_id
                            );
                        }
                    } catch (Exception $notif_error) {
                        error_log("Final notification error: " . $notif_error->getMessage());
                    }
                } else {
                    // حالت موازی: این مرحله تمام شد ولی هنوز مراحل دیگری باز است
                    $message = 'مرحلهٔ شما تکمیل شد؛ در انتظار تکمیل سایر مراحل';
                }
            }

            $this->db->commit();
            return ['success' => true, 'message' => $message];
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("CompleteStep error: " . $e->getMessage());
            return ['success' => false, 'message' => 'خطا در تکمیل مرحله: ' . $e->getMessage()];
        }
    }

    // دریافت لیست workflow های در حال اجرا
    public function getActiveWorkflows($filters = [], $org_id = null)
    {
        try {
            $where_conditions = ["wi.status IN ('in_progress', 'delayed')"];
            $params = [];

            // امنیت: فقط workflowهای سازمانِ کاربر
            if ($org_id !== null) {
                $where_conditions[] = "wi.organization_id = ?";
                $params[] = $org_id;
            }

            if (!empty($filters['template_id'])) {
                $where_conditions[] = "wi.template_id = ?";
                $params[] = $filters['template_id'];
            }

            if (!empty($filters['status'])) {
                $where_conditions[] = "wi.status = ?";
                $params[] = $filters['status'];
            }

            $sql = "
                SELECT 
                    wi.*,
                    wt.name as template_name,
                    creator.first_name as creator_first_name,
                    creator.last_name as creator_last_name,
                    (SELECT COUNT(*) FROM workflow_steps WHERE template_id = wi.template_id) as total_steps,
                    (SELECT COUNT(*) FROM workflow_instance_steps WHERE instance_id = wi.id AND status = 'completed') as completed_steps,
                    TIMESTAMPDIFF(HOUR, wi.started_at, NOW()) as hours_elapsed
                FROM workflow_instances wi
                JOIN workflow_templates wt ON wi.template_id = wt.id
                JOIN users creator ON wi.created_by = creator.id
                WHERE " . implode(' AND ', $where_conditions) . "
                ORDER BY wi.started_at DESC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("GetActiveWorkflows error: " . $e->getMessage());
            return [];
        }
    }

    public function getWorkflowDetails($instance_id, $org_id = null)
    {
        try {
            // اطلاعات اصلی
            $sql = "
                SELECT 
                    wi.*,
                    wt.name as template_name,
                    wt.description as template_description,
                    creator.first_name as creator_first_name,
                    creator.last_name as creator_last_name,
                    CONCAT(COALESCE(creator.first_name, ''), ' ', COALESCE(creator.last_name, '')) as creator_name
                FROM workflow_instances wi
                JOIN workflow_templates wt ON wi.template_id = wt.id
                JOIN users creator ON wi.created_by = creator.id
                WHERE wi.id = ?
            ";
            $params = [$instance_id];

            // امنیت: محدودسازی به سازمانِ کاربر
            if ($org_id !== null) {
                $sql .= " AND wi.organization_id = ?";
                $params[] = $org_id;
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $workflow = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$workflow) {
                return null;
            }

            // دریافت مراحل
            $stmt = $this->db->prepare("
                SELECT 
                    wis.*,
                    ws.step_name,
                    ws.activity_section,
                    ws.time_limit_hours,
                    t.title as task_title,
                    t.status as task_status,
                    completed_user.first_name as completed_by_first_name,
                    completed_user.last_name as completed_by_last_name,
                    TIMESTAMPDIFF(MINUTE, wis.started_at, wis.completed_at) as duration_minutes,
                    CASE 
                        WHEN wis.status = 'active' AND wis.deadline < NOW() THEN 1
                        ELSE 0
                    END as is_delayed
                FROM workflow_instance_steps wis
                JOIN workflow_steps ws ON wis.step_id = ws.id
                LEFT JOIN tasks t ON wis.task_id = t.id
                LEFT JOIN users completed_user ON wis.completed_by = completed_user.id
                WHERE wis.instance_id = ?
                ORDER BY wis.step_order ASC
            ");
            $stmt->execute([$instance_id]);
            $workflow['steps'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // محاسبه آمار
            $workflow['total_steps'] = count($workflow['steps']);
            $workflow['completed_steps'] = count(array_filter($workflow['steps'], function ($s) {
                return $s['status'] == 'completed';
            }));
            $workflow['progress'] = $workflow['total_steps'] > 0 ? round(($workflow['completed_steps'] / $workflow['total_steps']) * 100, 2) : 0;

            return $workflow;
        } catch (Exception $e) {
            error_log("GetWorkflowDetails error: " . $e->getMessage());
            return null;
        }
    }

    // لغو یک workflow
    public function cancelWorkflow($instance_id, $user_id, $reason = '')
    {
        try {
            $this->db->beginTransaction();

            // بروزرسانی وضعیت
            $stmt = $this->db->prepare("UPDATE workflow_instances SET status = 'cancelled', completed_at = NOW() WHERE id = ?");
            $stmt->execute([$instance_id]);

            // لغو همه کارهای مرتبط
            $stmt = $this->db->prepare("UPDATE tasks SET status = 'stopped' WHERE workflow_instance_id = ? AND status != 'completed'");
            $stmt->execute([$instance_id]);

            // لغو مراحل باقی‌مانده
            $stmt = $this->db->prepare("UPDATE workflow_instance_steps SET status = 'cancelled' WHERE instance_id = ? AND status IN ('pending', 'active')");
            $stmt->execute([$instance_id]);

            $this->db->commit();
            return ['success' => true, 'message' => 'کار روتین لغو شد'];
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("CancelWorkflow error: " . $e->getMessage());
            return ['success' => false, 'message' => 'خطا در لغو کار روتین'];
        }
    }

    // ====================================
    // سیستم نوتیفیکیشن
    // ====================================

    // ارسال نوتیفیکیشن به اعضای یک واحد (فقط اعضای همان سازمانِ workflow instance)
    private function notifySectionMembers($section, $type, $title, $message, $link, $related_id)
    {
        try {
            require_once __DIR__ . '/Notification.php';
            $notification = new Notification($this->db);

            $stmt = $this->db->prepare("SELECT organization_id FROM workflow_instances WHERE id = ?");
            $stmt->execute([$related_id]);
            $organization_id = $stmt->fetchColumn();

            // 🆕 همه‌ی اعضای واحد (شاملِ کسانی که این واحد، واحدِ دومشان است)
            $userIds = us_getSectionUserIds($this->db, $section, $organization_id);

            foreach ($userIds as $uid) {
                $notification->create([
                    'to_user_id' => $uid,
                    'title' => $title,
                    'message' => $message,
                    'type' => $type,
                    'link' => $link,
                    'related_type' => 'workflow',
                    'related_id' => $related_id,
                    'is_read' => 0
                ]);
            }
        } catch (Exception $e) {
            error_log("NotifySectionMembers error: " . $e->getMessage());
        }
    }

    // ارسال نوتیفیکیشن به مدیران (فقط مدیرانِ همان سازمانِ workflow instance)
    private function notifyManagers($type, $title, $message, $link, $related_id)
    {
        try {
            $stmt = $this->db->prepare("SELECT organization_id FROM workflow_instances WHERE id = ?");
            $stmt->execute([$related_id]);
            $organization_id = $stmt->fetchColumn();

            $stmt = $this->db->prepare("SELECT id FROM users WHERE is_manager = 1 AND is_active = 1 AND organization_id = ?");
            $stmt->execute([$organization_id]);
            $managers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($managers as $manager) {
                $this->createNotification($manager['id'], $type, $title, $message, $link, $related_id);
            }
        } catch (Exception $e) {
            error_log("NotifyManagers error: " . $e->getMessage());
        }
    }

    // ایجاد نوتیفیکیشن
    private function createNotification($user_id, $type, $title, $message, $link = null, $related_id = null)
    {
        try {
            require_once __DIR__ . '/Notification.php';
            $notification = new Notification($this->db);

            $notification->create([
                'to_user_id' => $user_id,
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'link' => $link,
                'related_type' => 'workflow',
                'related_id' => $related_id,
                'is_read' => 0
            ]);
        } catch (Exception $e) {
            error_log("CreateNotification error: " . $e->getMessage());
        }
    }

    // دریافت نوتیفیکیشن‌های کاربر
    public function getUserNotifications($user_id, $unread_only = false)
    {
        try {
            $sql = "SELECT * FROM notifications WHERE user_id = ?";
            $params = [$user_id];

            if ($unread_only) {
                $sql .= " AND is_read = 0";
            }

            $sql .= " ORDER BY created_at DESC LIMIT 50";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("GetUserNotifications error: " . $e->getMessage());
            return [];
        }
    }

    // علامت‌گذاری نوتیفیکیشن به عنوان خوانده شده
    public function markNotificationAsRead($notification_id, $user_id)
    {
        try {
            $stmt = $this->db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
            $stmt->execute([$notification_id, $user_id]);
            return ['success' => true];
        } catch (Exception $e) {
            error_log("MarkNotificationAsRead error: " . $e->getMessage());
            return ['success' => false];
        }
    }

    // ====================================
    // گزارش‌گیری و آمار
    // ====================================

    // آمار کلی workflow ها
    public function getWorkflowStats()
    {
        try {
            $stats = [];

            // تعداد کل workflow های فعال
            $stmt = $this->db->query("SELECT COUNT(*) as count FROM workflow_instances WHERE status = 'in_progress'");
            $stats['active_workflows'] = $stmt->fetch()['count'];

            // تعداد workflow های تأخیر داشته
            $stmt = $this->db->query("SELECT COUNT(*) as count FROM workflow_instances WHERE status = 'delayed'");
            $stats['delayed_workflows'] = $stmt->fetch()['count'];

            // تعداد کل workflow های تکمیل شده
            $stmt = $this->db->query("SELECT COUNT(*) as count FROM workflow_instances WHERE status = 'completed'");
            $stats['completed_workflows'] = $stmt->fetch()['count'];

            // تعداد مراحل فعال
            $stmt = $this->db->query("SELECT COUNT(*) as count FROM workflow_instance_steps WHERE status = 'active'");
            $stats['active_steps'] = $stmt->fetch()['count'];

            // میانگین زمان تکمیل workflow ها (به ساعت)
            $stmt = $this->db->query("SELECT AVG(TIMESTAMPDIFF(HOUR, started_at, completed_at)) as avg_hours FROM workflow_instances WHERE status = 'completed' AND completed_at IS NOT NULL");
            $stats['avg_completion_hours'] = round($stmt->fetch()['avg_hours'] ?? 0, 2);

            return $stats;
        } catch (Exception $e) {
            error_log("GetWorkflowStats error: " . $e->getMessage());
            return [];
        }
    }

    // بررسی workflow های تأخیر داشته
    public function checkDelays()
    {
        try {
            // بروزرسانی مراحل تأخیر داشته
            $stmt = $this->db->prepare("
                UPDATE workflow_instance_steps
                SET status = 'delayed'
                WHERE status = 'active'
                AND deadline < NOW()
                AND deadline IS NOT NULL
            ");
            $stmt->execute();

            // بروزرسانی وضعیت کلی workflow
            $stmt = $this->db->prepare("
                UPDATE workflow_instances wi
                SET status = 'delayed'
                WHERE wi.id IN (
                    SELECT DISTINCT instance_id 
                    FROM workflow_instance_steps 
                    WHERE status = 'delayed'
                )
                AND wi.status = 'in_progress'
            ");
            $stmt->execute();

            // دریافت لیست workflow های جدیداً تأخیر خورده
            $stmt = $this->db->query("
                SELECT DISTINCT wi.id, wi.title, wi.created_by
                FROM workflow_instances wi
                JOIN workflow_instance_steps wis ON wi.id = wis.instance_id
                WHERE wi.status = 'delayed'
                AND wis.status = 'delayed'
                AND wis.deadline BETWEEN DATE_SUB(NOW(), INTERVAL 15 MINUTE) AND NOW()
            ");
            $delayed_workflows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // ارسال نوتیفیکیشن برای workflow های تأخیر داشته
            foreach ($delayed_workflows as $workflow) {
                // نوتیف به ایجادکننده
                $this->createNotification(
                    $workflow['created_by'],
                    'workflow_delayed',
                    'کار روتین تأخیر دارد',
                    "کار روتین '{$workflow['title']}' از زمان مقرر عقب افتاده است",
                    "workflow-detail.php?id={$workflow['id']}",
                    $workflow['id']
                );

                // نوتیف به مدیران
                $this->notifyManagers(
                    'workflow_delayed',
                    'تأخیر در کار روتین',
                    "کار روتین '{$workflow['title']}' تأخیر دارد",
                    "workflow-detail.php?id={$workflow['id']}",
                    $workflow['id']
                );
            }

            return ['success' => true, 'delayed_count' => count($delayed_workflows)];
        } catch (Exception $e) {
            error_log("CheckDelays error: " . $e->getMessage());
            return ['success' => false, 'message' => 'خطا در بررسی تأخیرها'];
        }
    }
}

/**
 * 🔒 هم‌راستا با چک‌لیست: کسی که فقط عضوِ واحدِ یک/چند مرحله از این
 * workflow است (نه سازنده، نه نقشِ سازمانی‌ِ کل‌بین)، فقط باید همان
 * مرحله‌هایی را ببیند که واحدش مسئولِ آن‌هاست — نه کل مراحل را.
 * $hasFullAccess=true یعنی بدون فیلتر، همه‌ی مراحل برگردانده شود.
 */
function filterWorkflowStepsForViewer(array $steps, bool $hasFullAccess, string $user_section): array
{
    if ($hasFullAccess) return $steps;

    return array_values(array_filter($steps, function ($s) use ($user_section) {
        return !empty($s['activity_section']) && $s['activity_section'] === $user_section;
    }));
}

/**
 * محاسبهٔ آمارِ پیشرفت روی یک آرایه از مراحل (کل یا فیلترشده).
 */
function computeWorkflowProgress(array $steps): array
{
    $total = count($steps);
    $completed = count(array_filter($steps, function ($s) {
        return $s['status'] == 'completed';
    }));
    return [
        'total_steps'     => $total,
        'completed_steps' => $completed,
        'progress'        => $total > 0 ? round(($completed / $total) * 100, 2) : 0,
    ];
}
