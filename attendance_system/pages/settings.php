<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';

try {
    $database = new Database();
    $db = $database->getConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection error']);
    exit;
}
$auth = new Auth($db);

$user_id = null;

// روش 1: SESSION
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    error_log("✅ User ID from SESSION: " . $user_id);
}

// روش 2: JWT Token از header
if (!$user_id) {
    $user_id = $auth->getUserFromToken();
    if ($user_id) {
        error_log("✅ User ID from JWT header: " . $user_id);
    }
}

// روش 3: JWT Token از Cookie
if (!$user_id && isset($_COOKIE['auth_token'])) {
    error_log("🔍 Trying cookie token...");
    $token = $_COOKIE['auth_token'];
    $user_id = $auth->validateToken($token);
    if ($user_id) {
        error_log("✅ User ID from cookie: " . $user_id);
    }
}

// دریافت اطلاعات کاربر و بررسی دسترسی
$user = null;
try {
    $stmt = $db->prepare("SELECT first_name, last_name, role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        die('❌ کاربر یافت نشد');
    }

    $__me = loadUserForPermissions($db, (int) $user_id);
    if (!$__me || !hasPermission($__me, 'view_org_settings')) {
        http_response_code(403);
        die('❌ دسترسی رد شده - فقط ادمین‌ها می‌توانند تنظیمات را مدیریت کنند');
    }
} catch (Exception $e) {
    die('❌ خطا: ' . $e->getMessage());
}

// ایجاد جدول تنظیمات اگر موجود نیست
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS settings (
            id INT PRIMARY KEY AUTO_INCREMENT,
            setting_key VARCHAR(50) UNIQUE NOT NULL,
            setting_value VARCHAR(255) NOT NULL,
            description TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (Exception $e) {
    // جدول شاید وجود دارد
}

// ============================================
// تعریف تمام تنظیمات با گروه‌بندی
// ============================================
$settings_groups = [
    'attendance' => [
        'title' => 'حضور و غیاب',
        'icon' => 'bi-calendar-check',
        'color' => '#3B82F6',
        'settings' => [
            'clickable_days_limit' => [
                'value' => '5',
                'label' => 'محدودیت روزهای قابل کلیک',
                'type' => 'number',
                'min' => '1',
                'max' => '30',
                'help' => 'تعداد روزهای قبل از امروز که کاربران می‌توانند درخواست ارسال کنند',
                'unit' => 'روز'
            ],
            'exclude_holidays' => [
                'value' => '1',
                'label' => 'روزهای تعطیل از محدودیت کم شوند',
                'type' => 'boolean',
                'help' => 'اگر فعال باشد، روزهای تعطیل در محاسبه محدودیت روزها نادیده گرفته می‌شوند'
            ],
        ]
    ],
    'calculation' => [
        'title' => 'قوانین محاسبه حقوق',
        'icon' => 'bi-calculator',
        'color' => '#8B5CF6',
        'settings' => [
            'shortage_multiplier' => [
                'value' => '2',
                'label' => 'ضریب کسری بدون درخواست',
                'type' => 'number',
                'min' => '1',
                'max' => '10',
                'help' => 'کسری‌هایی که درخواست تأیید‌شده ندارند، در این عدد ضرب می‌شوند',
                'unit' => 'برابر'
            ],
            'salary_round_to' => [
                'value' => '100000',
                'label' => 'گرد کردن مبالغ ریالی',
                'type' => 'number',
                'min' => '1000',
                'max' => '1000000',
                'help' => 'مبالغ ریالی به نزدیک‌ترین مضرب این عدد گرد می‌شوند',
                'unit' => 'ریال'
            ],
            'working_days_per_month' => [
                'value' => '30',
                'label' => 'تعداد روزهای کاری ماه (برای محاسبه نرخ ساعتی)',
                'type' => 'number',
                'min' => '20',
                'max' => '31',
                'help' => 'حقوق ماهانه تقسیم بر این عدد می‌شود تا نرخ روزانه محاسبه شود',
                'unit' => 'روز'
            ],
        ]
    ],
    'approval' => [
        'title' => 'تأیید و مهلت‌ها',
        'icon' => 'bi-clock-history',
        'color' => '#F59E0B',
        'settings' => [
            'approval_deadline_days' => [
                'value' => '3',
                'label' => 'مهلت تأیید/رد درخواست',
                'type' => 'number',
                'min' => '1',
                'max' => '30',
                'help' => 'بعد از این مدت، مدیر/جانشین/مسئول نمی‌تواند درخواست را تأیید یا رد کند',
                'unit' => 'روز کاری'
            ],
            'pass_edit_hours' => [
                'value' => '24',
                'label' => 'مهلت ویرایش/حذف پاس',
                'type' => 'number',
                'min' => '1',
                'max' => '168',
                'help' => 'کاربر تا این مدت بعد از ثبت پاس می‌تواند آن را ویرایش یا حذف کند',
                'unit' => 'ساعت'
            ],
        ]
    ],
    'leave' => [
        'title' => 'محدودیت مرخصی',
        'icon' => 'bi-house-door',
        'color' => '#10B981',
        'settings' => [
            'leave_max_consecutive' => [
                'value' => '20',
                'label' => 'حداکثر روزهای متوالی مرخصی',
                'type' => 'number',
                'min' => '1',
                'max' => '365',
                'help' => 'حداکثر تعداد روزهایی که می‌توان پیاپی مرخصی گرفت',
                'unit' => 'روز'
            ],
            'leave_initial_quota' => [
                'value' => '30',
                'label' => 'سهم مرخصی سالانه',
                'type' => 'number',
                'min' => '0',
                'max' => '365',
                'help' => 'تعداد روزهای مرخصی اولیه برای هر کاربر در سال',
                'unit' => 'روز'
            ],
        ]
    ],
    'limits' => [
        'title' => 'سقف درخواست‌ها',
        'icon' => 'bi-shield-check',
        'color' => '#EF4444',
        'settings' => [
            'mission_max_hours_monthly' => [
                'value' => '0',
                'label' => 'حداکثر ساعت مأموریت در ماه',
                'type' => 'number',
                'min' => '0',
                'help' => '۰ = نامحدود',
                'unit' => 'ساعت'
            ],
            'pass_max_count_monthly' => [
                'value' => '0',
                'label' => 'حداکثر تعداد پاس در ماه',
                'type' => 'number',
                'min' => '0',
                'help' => '۰ = نامحدود. تعداد دفعات پاسی که کاربر می‌تواند در یک ماه ثبت کند',
                'unit' => 'بار'
            ],
            'pass_max_hours_daily' => [
                'value' => '0',
                'label' => 'حداکثر ساعت پاس در روز',
                'type' => 'number',
                'min' => '0',
                'help' => '۰ = نامحدود',
                'unit' => 'ساعت'
            ],
            'pass_max_hours_monthly' => [
                'value' => '0',
                'label' => 'حداکثر ساعت پاس در ماه',
                'type' => 'number',
                'min' => '0',
                'help' => '۰ = نامحدود. مجموع ساعات پاس مجاز در یک ماه',
                'unit' => 'ساعت'
            ],
            'technical_max_monthly' => [
                'value' => '0',
                'label' => 'حداکثر مشکل فنی در ماه',
                'type' => 'number',
                'min' => '0',
                'help' => '۰ = نامحدود',
                'unit' => 'بار'
            ],
            'forget_max_monthly' => [
                'value' => '0',
                'label' => 'حداکثر فراموشی در ماه',
                'type' => 'number',
                'min' => '0',
                'help' => '۰ = نامحدود',
                'unit' => 'بار'
            ],
        ]
    ],
];

// استخراج flat default_settings برای سازگاری
$default_settings = [];
foreach ($settings_groups as $group) {
    foreach ($group['settings'] as $key => $setting) {
        $default_settings[$key] = $setting;
    }
}

// ایجاد تنظیمات در صورت عدم وجود
try {
    foreach ($default_settings as $key => $setting) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        if ($stmt->fetchColumn() == 0) {
            $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
            $stmt->execute([$key, $setting['value']]);
        }
    }
} catch (Exception $e) {
    // تنظیمات شاید قبلاً ایجاد شده‌اند
}

// دریافت تمام تنظیمات
$current_settings = [];
try {
    $stmt = $db->prepare("SELECT setting_key, setting_value FROM settings");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $current_settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    die('❌ خطا در دریافت تنظیمات: ' . $e->getMessage());
}

// ارسال درخواست
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        foreach ($default_settings as $key => $setting) {
            if ($setting['type'] === 'boolean') {
                $value = isset($_POST[$key]) ? '1' : '0';
            } else {
                $value = $_POST[$key] ?? '';

                // اعتبارسنجی
                if ($setting['type'] === 'number') {
                    $value = intval($value);
                    if (isset($setting['min']) && $value < $setting['min']) {
                        throw new Exception("{$setting['label']}: حداقل مقدار {$setting['min']} است");
                    }
                    if (isset($setting['max']) && $value > $setting['max']) {
                        throw new Exception("{$setting['label']}: حداکثر مقدار {$setting['max']} است");
                    }
                }
            }

            $stmt = $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
            $stmt->execute([$value, $key]);
        }
        $success_message = 'تنظیمات با موفقیت ذخیره شدند';

        // بروزرسانی متغیرهای محلی
        foreach ($default_settings as $key => $setting) {
            if ($setting['type'] === 'boolean') {
                $current_settings[$key] = isset($_POST[$key]) ? '1' : '0';
            } else {
                $current_settings[$key] = $_POST[$key] ?? $setting['value'];
            }
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// تبدیل اعداد به فارسی
function toPersianNumber($num) {
    $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    return str_replace(['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'], $persian, $num);
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت تنظیمات</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/js/cdn/bootstrap-icons.css">
    <link href="../../assets/js/cdn/fonts/bootstrap-icons.woff2?30af91bf14e37666a085fb8a161ff36d" rel="stylesheet">
    <script src="../../assets/js/config.js"></script>
    <script src="../../assets/js/cdn/intro.min.js"></script>
    <link rel="stylesheet" href="../../assets/js/cdn/introjs.min.css">
    <script src="../../assets/js/cdn/jquery-3.6.0.min.js"></script>
    <script src="../../assets/js/persian-datepicker.js"></script>
    <link rel="stylesheet" href="../../assets/css/persian-datepicker.css">
    <link rel="stylesheet" href="../../assets/css/deadline-toast.css">
    <link rel="stylesheet" href="../../assets/css/custom.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Vazir', sans-serif;
        }

        html, body {
            height: 100%;
            background: #F1F5F9;
        }

        body {
            display: flex;
            flex-direction: column;
        }

        /* ===== Header ===== */
        .header {
            background: white;
            border-bottom: 1px solid #E2E8F0;
            padding: 16px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 24px;
        }

        .header-logo {
            font-weight: 700;
            font-size: 18px;
            color: #1E293B;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .header-logo i {
            font-size: 22px;
            color: #6366F1;
        }

        .nav-links {
            display: flex;
            gap: 4px;
            list-style: none;
        }

        .nav-links a {
            padding: 8px 16px;
            color: #64748B;
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .nav-links a:hover {
            background: #F1F5F9;
            color: #334155;
        }

        .nav-links a.active {
            color: #6366F1;
            background: #EEF2FF;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            color: #334155;
            font-weight: 500;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, #6366F1, #8B5CF6);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 16px;
        }

        .logout-btn {
            padding: 8px 16px;
            background: #F1F5F9;
            color: #64748B;
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .logout-btn:hover {
            background: #FEE2E2;
            color: #DC2626;
            border-color: #FECACA;
        }

        /* ===== Main Content ===== */
        .container {
            flex: 1;
            padding: 32px;
            max-width: 960px;
            margin: 0 auto;
            width: 100%;
        }

        .page-header {
            margin-bottom: 32px;
        }

        .page-title {
            font-size: 24px;
            font-weight: 700;
            color: #1E293B;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-subtitle {
            font-size: 14px;
            color: #64748B;
        }

        /* ===== Messages ===== */
        .toast-message {
            padding: 14px 20px;
            border-radius: 10px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            font-weight: 500;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .toast-message.success {
            background: #F0FDF4;
            color: #166534;
            border: 1px solid #BBF7D0;
        }

        .toast-message.error {
            background: #FEF2F2;
            color: #991B1B;
            border: 1px solid #FECACA;
        }

        .toast-message i {
            font-size: 18px;
        }

        /* ===== Settings Groups ===== */
        .settings-grid {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .settings-group {
            background: white;
            border-radius: 14px;
            border: 1px solid #E2E8F0;
            overflow: hidden;
            transition: box-shadow 0.2s ease;
        }

        .settings-group:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
        }

        .group-header {
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid #F1F5F9;
            cursor: pointer;
            user-select: none;
            transition: background 0.2s ease;
        }

        .group-header:hover {
            background: #FAFBFC;
        }

        .group-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
            flex-shrink: 0;
        }

        .group-title {
            font-size: 15px;
            font-weight: 700;
            color: #1E293B;
            flex: 1;
        }

        .group-count {
            font-size: 12px;
            color: #94A3B8;
            background: #F1F5F9;
            padding: 3px 10px;
            border-radius: 20px;
        }

        .group-toggle {
            color: #94A3B8;
            font-size: 14px;
            transition: transform 0.3s ease;
        }

        .group-toggle.collapsed {
            transform: rotate(-90deg);
        }

        .group-body {
            padding: 0;
            max-height: 1000px;
            overflow: hidden;
            transition: max-height 0.3s ease, padding 0.3s ease;
        }

        .group-body.collapsed {
            max-height: 0;
            padding: 0;
        }

        .setting-item {
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            border-bottom: 1px solid #F8FAFC;
            transition: background 0.15s ease;
        }

        .setting-item:last-child {
            border-bottom: none;
        }

        .setting-item:hover {
            background: #FAFBFC;
        }

        .setting-info {
            flex: 1;
            min-width: 0;
        }

        .setting-label {
            font-size: 14px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 3px;
        }

        .setting-help {
            font-size: 12px;
            color: #94A3B8;
            line-height: 1.4;
        }

        .setting-control {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }

        .setting-control input[type="number"] {
            width: 100px;
            padding: 8px 12px;
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Vazir', sans-serif;
            text-align: center;
            font-weight: 600;
            color: #1E293B;
            transition: all 0.2s ease;
            direction: ltr;
        }

        .setting-control input[type="number"]:focus {
            outline: none;
            border-color: #6366F1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .setting-unit {
            font-size: 12px;
            color: #94A3B8;
            font-weight: 500;
            min-width: 50px;
        }

        /* Toggle Switch for boolean */
        .toggle-switch {
            position: relative;
            width: 48px;
            height: 26px;
            flex-shrink: 0;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: #CBD5E1;
            border-radius: 26px;
            transition: all 0.3s ease;
        }

        .toggle-slider:before {
            content: '';
            position: absolute;
            height: 20px;
            width: 20px;
            left: 3px;
            bottom: 3px;
            background: white;
            border-radius: 50%;
            transition: all 0.3s ease;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.15);
        }

        .toggle-switch input:checked + .toggle-slider {
            background: #6366F1;
        }

        .toggle-switch input:checked + .toggle-slider:before {
            transform: translateX(22px);
        }

        /* ===== Buttons ===== */
        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
            position: sticky;
            bottom: 0;
            background: #F1F5F9;
            padding: 20px 0;
            z-index: 10;
        }

        .btn {
            padding: 12px 28px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-save {
            background: #6366F1;
            color: white;
        }

        .btn-save:hover {
            background: #4F46E5;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }

        .btn-reset {
            background: white;
            color: #64748B;
            border: 1px solid #E2E8F0;
        }

        .btn-reset:hover {
            background: #F8FAFC;
            color: #334155;
        }

        .btn-back {
            background: white;
            color: #64748B;
            border: 1px solid #E2E8F0;
            margin-right: auto;
        }

        .btn-back:hover {
            background: #F8FAFC;
            color: #334155;
        }

        /* ===== Responsive ===== */
        @media (max-width: 768px) {
            .header {
                padding: 12px 16px;
                flex-wrap: wrap;
                gap: 12px;
            }

            .container {
                padding: 16px;
            }

            .setting-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .setting-control {
                width: 100%;
            }

            .setting-control input[type="number"] {
                flex: 1;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .btn-back {
                margin-right: 0;
            }
        }
    </style>
</head>
<body>
        <?php include '../../pages/header.php'; ?>


    <!-- Main Content -->
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">
                <i class="bi bi-sliders" style="color: #6366F1;"></i>
                مدیریت تنظیمات
            </h1>
            <p class="page-subtitle">محدودیت‌ها، سیاست‌ها و قوانین محاسباتی سیستم حضور و غیاب را تنظیم کنید</p>
        </div>

        <!-- Messages -->
        <?php if ($success_message): ?>
            <div class="toast-message success">
                <i class="bi bi-check-circle-fill"></i>
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="toast-message error">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Settings Form -->
        <form method="POST">
            <div class="settings-grid">
                <?php foreach ($settings_groups as $group_key => $group): ?>
                    <div class="settings-group">
                        <div class="group-header" onclick="toggleGroup('<?php echo $group_key; ?>')">
                            <div class="group-icon" style="background: <?php echo $group['color']; ?>;">
                                <i class="bi <?php echo $group['icon']; ?>"></i>
                            </div>
                            <div class="group-title"><?php echo $group['title']; ?></div>
                            <div class="group-count"><?php echo toPersianNumber(count($group['settings'])); ?> تنظیم</div>
                            <div class="group-toggle" id="toggle-<?php echo $group_key; ?>">
                                <i class="bi bi-chevron-down"></i>
                            </div>
                        </div>
                        <div class="group-body" id="body-<?php echo $group_key; ?>">
                            <?php foreach ($group['settings'] as $key => $setting): ?>
                                <div class="setting-item">
                                    <div class="setting-info">
                                        <div class="setting-label"><?php echo $setting['label']; ?></div>
                                        <div class="setting-help"><?php echo $setting['help']; ?></div>
                                    </div>
                                    <div class="setting-control">
                                        <?php if ($setting['type'] === 'boolean'): ?>
                                            <label class="toggle-switch">
                                                <input type="checkbox" name="<?php echo $key; ?>" value="1"
                                                    <?php echo ($current_settings[$key] ?? $setting['value']) == '1' ? 'checked' : ''; ?>>
                                                <span class="toggle-slider"></span>
                                            </label>
                                        <?php else: ?>
                                            <input
                                                type="number"
                                                name="<?php echo $key; ?>"
                                                id="<?php echo $key; ?>"
                                                value="<?php echo htmlspecialchars($current_settings[$key] ?? $setting['value']); ?>"
                                                <?php if (isset($setting['min'])): ?>min="<?php echo $setting['min']; ?>"<?php endif; ?>
                                                <?php if (isset($setting['max'])): ?>max="<?php echo $setting['max']; ?>"<?php endif; ?>
                                                required
                                            >
                                            <?php if (isset($setting['unit'])): ?>
                                                <span class="setting-unit"><?php echo $setting['unit']; ?></span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Buttons -->
            <div class="form-actions">
                <button type="button" class="btn btn-back" onclick="window.location.href='dashboard.php'">
                    <i class="bi bi-arrow-right"></i> بازگشت
                </button>
                <button type="reset" class="btn btn-reset">
                    <i class="bi bi-arrow-clockwise"></i> بازنشانی
                </button>
                <button type="submit" class="btn btn-save">
                    <i class="bi bi-check2-circle"></i> ذخیره تنظیمات
                </button>
            </div>
        </form>
    </div>
    <script src="../../assets/js/cdn/bootstrap.bundle.min.js"></script>

    <script>
        function toggleGroup(groupKey) {
            const body = document.getElementById('body-' + groupKey);
            const toggle = document.getElementById('toggle-' + groupKey);

            body.classList.toggle('collapsed');
            toggle.classList.toggle('collapsed');
        }

        function logout() {
            uiConfirm('آیا می‌خواهید خروج کنید؟', function () {
                localStorage.removeItem('auth_token');
                window.location.href = '../index.php';
            }, { danger: true, yesText: 'بله، خروج', noText: 'انصراف' });
        }

        // Auto-hide success message after 4 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const toast = document.querySelector('.toast-message.success');
            if (toast) {
                setTimeout(() => {
                    toast.style.opacity = '0';
                    toast.style.transform = 'translateY(-10px)';
                    toast.style.transition = 'all 0.3s ease';
                    setTimeout(() => toast.remove(), 300);
                }, 4000);
            }
        });
    </script>
</body>
</html>
