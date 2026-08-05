<?php
/**
 * هلپر خواندن تنظیمات از دیتابیس
 * مسیر: /attendance_system/includes/settings_helper.php
 * 
 * استفاده در هر فایل:
 *   require_once 'includes/settings_helper.php';
 *   $settings = loadSettings($db);
 *   $multiplier = $settings['shortage_multiplier']; // مثلاً 2
 */

function loadSettings($db)
{
    // مقادیر پیش‌فرض (fallback اگر جدول وجود نداشت)
    $defaults = [
        'clickable_days_limit' => 5,
        'exclude_holidays' => 1,
        'shortage_multiplier' => 2,
        'salary_round_to' => 100000,
        'approval_deadline_days' => 3,
        'pass_edit_hours' => 24,
        'leave_max_consecutive' => 20,
        'leave_initial_quota' => 30,
        'mission_max_hours_monthly' => 0,
        'pass_max_hours_daily' => 0,
        'technical_max_monthly' => 0,
        'pass_max_count_monthly' => 6,
        'forget_max_monthly' => 0,
    ];

    try {
        $stmt = $db->prepare("SELECT setting_key, setting_value FROM settings");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = $defaults; // شروع با مقادیر پیش‌فرض
        foreach ($rows as $row) {
            if (array_key_exists($row['setting_key'], $defaults)) {
                $result[$row['setting_key']] = is_numeric($row['setting_value'])
                    ? (int) $row['setting_value']
                    : $row['setting_value'];
            }
        }

        return $result;
    } catch (Exception $e) {
        // اگر جدول نبود یا خطا خورد، مقادیر پیش‌فرض برگردان
        error_log("Settings helper error: " . $e->getMessage());
        return $defaults;
    }
}

/**
 * دریافت یک تنظیم خاص
 */
function getSetting($db, $key, $default = null)
{
    try {
        $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            return is_numeric($row['setting_value']) ? (int) $row['setting_value'] : $row['setting_value'];
        }
        return $default;
    } catch (Exception $e) {
        error_log("getSetting fallback to default (DB error) | key={$key} | " . $e->getMessage());
        return $default;
    }
}
?>