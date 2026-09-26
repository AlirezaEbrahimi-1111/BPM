<?php
class RoutineManager {
    private $db;
    private $notif; // 🆕 تزریق اختیاری کلاس Notification (اگر null باشد، رفتار قبلی حفظ می‌شود)

    // 🆕 پارامتر دوم اختیاری است؛ کدهای قدیمی که فقط $database می‌دهند بدون تغییر کار می‌کنند
    public function __construct($database, $notification = null) {
        $this->db = $database;
        $this->notif = $notification;
    }

    // دریافت لیست کارهای روتین فعال
    public function getActiveRoutines() {
        try {
            $sql = "SELECT * FROM routine_templates WHERE is_active = 1 ORDER BY name";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("GetActiveRoutines error: " . $e->getMessage());
            return [];
        }
    }

    // دریافت مراحل یک روتین
    public function getRoutineSteps($routine_id) {
        try {
            $sql = "SELECT * FROM routine_steps WHERE routine_id = ? ORDER BY step_order";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$routine_id]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("GetRoutineSteps error: " . $e->getMessage());
            return [];
        }
    }

    // ایجاد نمونه روتین و کارهای مربوطه
    public function createRoutineInstance($routine_id, $title, $creator_id) {
        try {
            $this->db->beginTransaction();

            // بررسی دسترسی کاربر
            if (!$this->userCanCreateRoutine($creator_id)) {
                return ['success' => false, 'message' => 'شما دسترسی ایجاد کار روتین ندارید'];
            }

            // بررسی وجود روتین
            $routine = $this->getRoutineById($routine_id);
            if (!$routine) {
                return ['success' => false, 'message' => 'کار روتین یافت نشد'];
            }

            // دریافت مراحل
            $steps = $this->getRoutineSteps($routine_id);
            if (empty($steps)) {
                return ['success' => false, 'message' => 'این کار روتین مراحلی ندارد'];
            }

            // ایجاد instance
            $sql = "INSERT INTO routine_instances (routine_id, title, creator_id, current_step, status) 
                    VALUES (?, ?, ?, 1, 'in_progress')";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$routine_id, $title, $creator_id]);
            $instance_id = $this->db->lastInsertId();

            // ایجاد کارها برای تمام مراحل
            foreach ($steps as $step) {
                $this->createRoutineTask($instance_id, $step, $title, $creator_id);
            }

            $this->db->commit();

            // 🆕 اعلان «مرحله جدید» برای مرحله اول (بعد از commit، خارج از تراکنش)
            $this->notifyNewStage($instance_id, 1);

            return [
                'success' => true, 
                'message' => 'کار روتین با موفقیت ایجاد شد',
                'instance_id' => $instance_id,
                'tasks_created' => count($steps)
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("CreateRoutineInstance error: " . $e->getMessage());
            return ['success' => false, 'message' => 'خطا در ایجاد کار روتین'];
        }
    }

    // ایجاد یک کار برای یک مرحله
    private function createRoutineTask($instance_id, $step, $main_title, $creator_id) {
        // عنوان کامل کار
        $task_title = $main_title . ' - ' . $step['title'];

        // محاسبه موعد
        $due_date = date('Y-m-d', strtotime('+' . $step['duration_days'] . ' days'));

        // وضعیت اولیه: فقط مرحله اول فعال است
        $initial_status = ($step['step_order'] == 1) ? 'in_progress' : 'pending';

        // ایجاد کار در جدول tasks
        $sql = "INSERT INTO tasks (title, description, creator_id, activity_section, task_type, 
                status, priority, due_date, created_at) 
                VALUES (?, ?, ?, ?, 'periodic', ?, 'medium', ?, NOW())";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $task_title,
            $step['description'],
            $creator_id,
            $step['activity_section'],
            $initial_status,
            $due_date
        ]);

        $task_id = $this->db->lastInsertId();

        // ثبت ارتباط در routine_tasks
        $sql = "INSERT INTO routine_tasks (instance_id, step_id, task_id, step_order, activity_section) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $instance_id,
            $step['id'],
            $task_id,
            $step['step_order'],
            $step['activity_section']
        ]);

        return $task_id;
    }

    // تکمیل یک مرحله و فعال کردن مرحله بعدی
    public function completeRoutineStep($task_id, $user_id) {
        try {
            $this->db->beginTransaction();

            // پیدا کردن routine task
            $sql = "SELECT rt.*, t.activity_section 
                    FROM routine_tasks rt 
                    JOIN tasks t ON rt.task_id = t.id 
                    WHERE rt.task_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$task_id]);
            $routine_task = $stmt->fetch();

            if (!$routine_task) {
                // این کار عادی است، نه روتین
                return ['success' => true, 'is_routine' => false];
            }

            $instance_id = $routine_task['instance_id'];
            $current_step_order = $routine_task['step_order'];
            $activity_section = $routine_task['activity_section'];

            // تکمیل کار فعلی
            $sql = "UPDATE tasks SET status = 'completed', updated_at = NOW() WHERE id = ?";
            $this->db->prepare($sql)->execute([$task_id]);

            // تکمیل تمام کارهای همین مرحله (برای همه اعضای واحد)
            $sql = "UPDATE tasks t
                    JOIN routine_tasks rt ON t.id = rt.task_id
                    SET t.status = 'completed', t.updated_at = NOW()
                    WHERE rt.instance_id = ? 
                    AND rt.step_order = ?
                    AND t.activity_section = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$instance_id, $current_step_order, $activity_section]);

            // فعال کردن مرحله بعدی
            $next_step_order = $current_step_order + 1;

            $sql = "UPDATE tasks t
                    JOIN routine_tasks rt ON t.id = rt.task_id
                    SET t.status = 'in_progress', t.updated_at = NOW()
                    WHERE rt.instance_id = ? 
                    AND rt.step_order = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$instance_id, $next_step_order]);

            // بررسی اتمام کار روتین
            $sql = "SELECT COUNT(*) as remaining 
                    FROM routine_tasks rt
                    JOIN tasks t ON rt.task_id = t.id
                    WHERE rt.instance_id = ? AND t.status != 'completed'";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$instance_id]);
            $result = $stmt->fetch();

            if ($result['remaining'] == 0) {
                // همه مراحل تکمیل شد
                $sql = "UPDATE routine_instances 
                        SET status = 'completed', completed_at = NOW() 
                        WHERE id = ?";
                $this->db->prepare($sql)->execute([$instance_id]);
            } else {
                // بروزرسانی مرحله جاری
                $sql = "UPDATE routine_instances 
                        SET current_step = ? 
                        WHERE id = ?";
                $this->db->prepare($sql)->execute([$next_step_order, $instance_id]);
            }

            $this->db->commit();

            // 🆕 اعلان‌ها بعد از commit (خارج از تراکنش، بدون اثر روی جریان اصلی)
            $is_completed = ($result['remaining'] == 0);
            if ($is_completed) {
                $this->notifyRoutineCompleted($instance_id);
            } else {
                $this->notifyNewStage($instance_id, $next_step_order);
            }

            return [
                'success' => true, 
                'is_routine' => true,
                'completed' => $is_completed,
                'next_step' => $next_step_order
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("CompleteRoutineStep error: " . $e->getMessage());
            return ['success' => false, 'message' => 'خطا در تکمیل مرحله'];
        }
    }

    // بررسی دسترسی کاربر
    private function userCanCreateRoutine($user_id) {
        $sql = "SELECT can_create_routine FROM users WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        return $user && $user['can_create_routine'] == 1;
    }

    // دریافت اطلاعات روتین
    private function getRoutineById($routine_id) {
        $sql = "SELECT * FROM routine_templates WHERE id = ? AND is_active = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$routine_id]);
        return $stmt->fetch();
    }

    // دریافت وضعیت یک routine instance
    public function getInstanceStatus($instance_id) {
        try {
            $sql = "SELECT ri.*, rt.name as routine_name,
                           u.first_name, u.last_name
                    FROM routine_instances ri
                    JOIN routine_templates rt ON ri.routine_id = rt.id
                    JOIN users u ON ri.creator_id = u.id
                    WHERE ri.id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$instance_id]);
            $instance = $stmt->fetch();

            if (!$instance) return null;

            // دریافت کارها
            $sql = "SELECT rt.step_order, rs.title as step_title, t.status, t.due_date, t.activity_section
                    FROM routine_tasks rt
                    JOIN routine_steps rs ON rt.step_id = rs.id
                    JOIN tasks t ON rt.task_id = t.id
                    WHERE rt.instance_id = ?
                    ORDER BY rt.step_order";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$instance_id]);
            $instance['tasks'] = $stmt->fetchAll();

            return $instance;

        } catch (Exception $e) {
            error_log("GetInstanceStatus error: " . $e->getMessage());
            return null;
        }
    }

    // ============================================================
    // 🆕 متدهای اعلان (همگی ایمن: اگر $this->notif نباشد، بی‌صدا رد می‌شوند)
    // هیچ‌کدام استثنا پرتاب نمی‌کنند تا جریان اصلی روتین آسیب نبیند.
    // ============================================================

    /**
     * اعلان «مرحله جدید» به مجریان مرحله‌ی جاری
     * الگو: routine_new_stage | متغیرها: {0}=عنوان کار، {1}=شماره مرحله
     */
    private function notifyNewStage($instance_id, $step_order) {
        if (!$this->notif) return;
        try {
            // کارهای فعال این مرحله (روتین‌ها به‌جای فرد، به «واحد فعالیت» تعلق دارند)
            $sql = "SELECT t.id AS task_id, t.title, t.activity_section, t.organization_id
                    FROM routine_tasks rt
                    JOIN tasks t ON rt.task_id = t.id
                    WHERE rt.instance_id = ?
                      AND rt.step_order = ?
                      AND t.status = 'in_progress'";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$instance_id, $step_order]);
            $tasks = $stmt->fetchAll();

            foreach ($tasks as $task) {
                if (empty($task['activity_section'])) continue;

                // اعضای واحد فعالیت این کار (از جدول اصلی + fallback روی users) — فقط همان سازمان
                $uSql = "SELECT user_id FROM user_activity_units uau
                         JOIN users u ON u.id = uau.user_id
                         WHERE uau.activity_unit = ? AND u.organization_id = ?
                         UNION
                         SELECT id AS user_id FROM users WHERE activity_unit = ? AND organization_id = ?";
                $uStmt = $this->db->prepare($uSql);
                $uStmt->execute([
                    $task['activity_section'], $task['organization_id'],
                    $task['activity_section'], $task['organization_id'],
                ]);
                $userIds = $uStmt->fetchAll(PDO::FETCH_COLUMN);

                foreach (array_unique($userIds) as $uid) {
                    if (empty($uid)) continue;
                    $this->safeCreate([
                        'to_user_id'   => $uid,
                        'title'        => 'مرحله جدید کار روتین',
                        'message'      => 'کار روتین «' . $task['title'] . '» مرحله ' . $step_order . ' آماده انجام است.',
                        'type'         => 'info',
                        'link'         => '/pages/task-detail.php?id=' . $task['task_id'],
                        'related_type' => 'routine',
                        'related_id'   => $instance_id,
                        'sms_pattern'  => 'routine_new_stage',
                        'sms_args'     => [$task['title'], $step_order],
                    ]);
                }
            }
        } catch (Exception $e) {
            error_log("notifyNewStage error: " . $e->getMessage());
        }
    }

    /**
     * اعلان «روتین تکمیل» به سازنده‌ی روتین
     * الگو: routine_completed | متغیرها: {0}=عنوان کار
     */
    private function notifyRoutineCompleted($instance_id) {
        if (!$this->notif) return;
        try {
            $sql = "SELECT title, creator_id FROM routine_instances WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$instance_id]);
            $inst = $stmt->fetch();
            if (!$inst || empty($inst['creator_id'])) return;

            $this->safeCreate([
                'to_user_id'   => $inst['creator_id'],
                'title'        => 'کار روتین تکمیل شد',
                'message'      => 'کار روتین «' . $inst['title'] . '» با موفقیت تکمیل شد.',
                'type'         => 'success',
                'link'         => 'routines.php',
                'related_type' => 'routine',
                'related_id'   => $instance_id,
                'sms_pattern'  => 'routine_completed',
                'sms_args'     => [$inst['title']],
            ]);
        } catch (Exception $e) {
            error_log("notifyRoutineCompleted error: " . $e->getMessage());
        }
    }

    /**
     * پوشش امن دور create — هر خطایی فقط لاگ می‌شود
     */
    private function safeCreate(array $data) {
        try {
            $this->notif->create($data);
        } catch (Exception $e) {
            error_log("Routine notif create error: " . $e->getMessage());
        }
    }
}
?>