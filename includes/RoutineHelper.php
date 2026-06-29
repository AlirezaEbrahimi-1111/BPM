<?php
/**
 * RoutineHelper - کلاس کمکی برای مدیریت کارهای روتین
 * این کلاس باید در هنگام تغییر وضعیت کار فراخوانی شود
 */

class RoutineHelper {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * زمانی که یک کار تکمیل می‌شود این متد فراخوانی می‌شود
     * اگر کار جزو روتین باشد، کارهای مرحله فعلی را تکمیل و مرحله بعدی را فعال می‌کند
     */
    public function handleTaskCompletion($task_id, $user_id) {
        try {
            // بررسی اینکه کار جزو روتین است یا خیر
            $taskStmt = $this->db->prepare("
                SELECT t.*, ri.current_step, ri.routine_id, ri.status as instance_status
                FROM tasks t
                LEFT JOIN routine_instances ri ON t.routine_instance_id = ri.id
                WHERE t.id = ?
            ");
            $taskStmt->execute([$task_id]);
            $task = $taskStmt->fetch();
            
            if (!$task || !$task['routine_instance_id']) {
                // کار عادی است، نیازی به پردازش روتین نیست
                return ['success' => true, 'is_routine' => false];
            }
            
            // بررسی اینکه نمونه روتین فعال است
            if ($task['instance_status'] !== 'active') {
                return ['success' => false, 'message' => 'این نمونه روتین دیگر فعال نیست'];
            }
            
            $instance_id = $task['routine_instance_id'];
            $routine_id = $task['routine_id'];
            $current_step = $task['current_step'];
            
            $this->db->beginTransaction();
            
            // 1. تکمیل تمام کارهای مرحله فعلی برای همه اعضای واحد
            $completeStmt = $this->db->prepare("
                UPDATE tasks 
                SET status = 'completed', updated_at = NOW()
                WHERE routine_instance_id = ? 
                AND routine_step_id = (
                    SELECT id FROM routine_steps 
                    WHERE routine_id = ? AND step_order = ?
                )
                AND status != 'completed'
            ");
            $completeStmt->execute([$instance_id, $routine_id, $current_step]);
            
            // 2. بررسی وجود مرحله بعدی
            $nextStepStmt = $this->db->prepare("
                SELECT * FROM routine_steps 
                WHERE routine_id = ? AND step_order = ?
            ");
            $nextStepStmt->execute([$routine_id, $current_step + 1]);
            $nextStep = $nextStepStmt->fetch();
            
            if ($nextStep) {
                // 3. فعال کردن کارهای مرحله بعدی
                $activateStmt = $this->db->prepare("
                    UPDATE tasks 
                    SET status = 'in_progress', updated_at = NOW()
                    WHERE routine_instance_id = ? AND routine_step_id = ?
                ");
                $activateStmt->execute([$instance_id, $nextStep['id']]);
                
                // 4. بروزرسانی مرحله جاری در نمونه روتین
                $updateInstanceStmt = $this->db->prepare("
                    UPDATE routine_instances 
                    SET current_step = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $updateInstanceStmt->execute([$current_step + 1, $instance_id]);
                
                // 5. ثبت لاگ
                $logStmt = $this->db->prepare("
                    INSERT INTO routine_logs (instance_id, step_id, action, action_by, notes) 
                    VALUES (?, ?, 'step_completed', ?, ?)
                ");
                $logStmt->execute([
                    $instance_id,
                    $nextStep['id'],
                    $user_id,
                    "مرحله {$current_step} تکمیل شد، انتقال به مرحله {$nextStep['step_order']}: {$nextStep['step_title']}"
                ]);
                
                $this->db->commit();
                
                return [
                    'success' => true,
                    'is_routine' => true,
                    'moved_to_next_step' => true,
                    'next_step' => $nextStep,
                    'message' => "مرحله تکمیل شد. کار به واحد {$nextStep['activity_section']} منتقل شد"
                ];
                
            } else {
                // تمام مراحل تکمیل شده - بستن روتین
                $completeInstanceStmt = $this->db->prepare("
                    UPDATE routine_instances 
                    SET status = 'completed', completed_at = NOW()
                    WHERE id = ?
                ");
                $completeInstanceStmt->execute([$instance_id]);
                
                // ثبت لاگ
                $logStmt = $this->db->prepare("
                    INSERT INTO routine_logs (instance_id, action, action_by, notes) 
                    VALUES (?, 'completed', ?, ?)
                ");
                $logStmt->execute([
                    $instance_id,
                    $user_id,
                    "تمام مراحل روتین با موفقیت تکمیل شد"
                ]);
                
                $this->db->commit();
                
                return [
                    'success' => true,
                    'is_routine' => true,
                    'routine_completed' => true,
                    'message' => 'تبریک! تمام مراحل روتین با موفقیت تکمیل شد'
                ];
            }
            
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("RoutineHelper error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در پردازش روتین'
            ];
        }
    }
    
    /**
     * دریافت اطلاعات مرحله فعلی روتین
     */
    public function getCurrentStepInfo($instance_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT ri.current_step, rs.step_title, rs.step_description, 
                       rs.activity_section, rw.name as routine_name
                FROM routine_instances ri
                JOIN routine_workflows rw ON ri.routine_id = rw.id
                LEFT JOIN routine_steps rs ON rs.routine_id = ri.routine_id 
                    AND rs.step_order = ri.current_step
                WHERE ri.id = ?
            ");
            $stmt->execute([$instance_id]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Get current step error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * بررسی اینکه آیا کاربر می‌تواند این کار روتین را تکمیل کند
     */
    public function canUserCompleteTask($task_id, $user_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT t.*, rs.activity_section
                FROM tasks t
                LEFT JOIN routine_steps rs ON t.routine_step_id = rs.id
                WHERE t.id = ?
            ");
            $stmt->execute([$task_id]);
            $task = $stmt->fetch();
            
            if (!$task || !$task['routine_instance_id']) {
                // کار عادی است
                return true;
            }
            
            // بررسی اینکه کاربر عضو واحد مربوطه است
            if ($task['activity_section']) {
                $checkUnitStmt = $this->db->prepare("
                    SELECT 1 FROM user_activity_units 
                    WHERE user_id = ? AND activity_unit = ?
                    UNION
                    SELECT 1 FROM users 
                    WHERE id = ? AND activity_unit = ?
                ");
                $checkUnitStmt->execute([
                    $user_id, 
                    $task['activity_section'],
                    $user_id,
                    $task['activity_section']
                ]);
                
                return $checkUnitStmt->rowCount() > 0;
            }
            
            // اگر کار به کاربر واگذار شده
            return $task['assignee_id'] == $user_id;
            
        } catch (Exception $e) {
            error_log("Check user permission error: " . $e->getMessage());
            return false;
        }
    }
}
?>