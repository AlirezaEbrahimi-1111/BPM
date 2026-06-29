<?php
/**
 * تنظیمات Attendance System
 * مسیر: /attendance_system/config/config.php
 */

// پیش‌فرض‌ها
define('APP_NAME', 'سیستم مدیریت حضور و غیاب');
define('APP_VERSION', '1.0.0');

// تاریخ و زمان
date_default_timezone_set('Asia/Tehran');

// مسیرهای پایه
$base_url = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
define('BASE_URL', $base_url);
define('API_URL', BASE_URL . '/api');

// تنظیمات Attendance
define('DEFAULT_WORK_HOURS', 8);
define('SHIFT_1_START', '08:00:00');
define('SHIFT_1_END', '16:00:00');
define('SHIFT_2_START', '14:00:00');
define('SHIFT_2_END', '22:00:00');

// تنظیمات مرخصی
define('MAX_LEAVE_DAYS_PER_YEAR', 30);
define('MAX_PASS_HOURS_PER_MONTH', 6);
define('MAX_FORGET_REQUESTS_PER_MONTH', 6);

// تنظیمات سیستم
define('REQUEST_APPROVAL_DEADLINE_DAYS', 3);
define('OVERTIME_REQUIRES_APPROVAL', true);
define('LOG_ALL_ACTIVITIES', true);

define('SHIFT_2_EARLY_CHECK_IN_MINUTES', 30);
// دسترسی
$ALLOWED_ROLES = [
    'employee' => 'کارمند',
    'manager' => 'مدیر',
    'supervisor' => 'سرپرست',
    'it_manager' => 'مدیر فنی',
    'admin' => 'مدیر سیستم'
];

// Pagination
define('ITEMS_PER_PAGE', 20);

// Cache
define('CACHE_ENABLED', false);
define('CACHE_DIR', dirname(__FILE__) . '/../cache');
?>