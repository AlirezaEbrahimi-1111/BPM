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

// ============================================
// لایه‌ی داده — از همون API جدا (attendance_system/api/attendance/
// settings.php) میاد، فقط این‌جا به‌جایِ HTTP، مستقیم require و صدا
// زده می‌شه (بدونِ loopback)؛ چون این صفحه همچنان با فرمِ سنتیِ POST/
// رندرِ سمتِ سرور کار می‌کنه و نباید رفتارش عوض بشه. جزئیاتِ کامل:
// همون فایل.
// ============================================
require_once $_SERVER['DOCUMENT_ROOT'] . '/attendance_system/api/attendance/settings.php';

// ارسال درخواست
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = attendanceSettingsSave($db, $_POST);
    if ($result['success']) {
        $success_message = $result['message'];
    } else {
        $error_message = $result['message'];
    }
}

// همیشه بعد از (احتمالا) ذخیره، مقادیرِ تازه رو می‌خونیم — این‌جوری
// بعدِ یک ذخیره‌ی موفق هم صفحه دقیقا همون چیزی رو نشون می‌ده که واقعا
// توی دیتابیسه، نه یک کپیِ دستیِ محاسبه‌شده
try {
    $settings_groups = attendanceSettingsGetAll($db);
} catch (Exception $e) {
    die('❌ خطا در دریافت تنظیمات: ' . $e->getMessage());
}

// تبدیل اعداد به فارسی
function toPersianNumber($num)
{
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
    <script src="../../assets/js/config.js"></script>
    <script src="../../assets/js/cdn/intro.min.js"></script>
    <link rel="stylesheet" href="../../assets/js/cdn/introjs.min.css">
    <script src="../../assets/js/cdn/jquery.min.js"></script>
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

        html,
        body {
            /* height: 100%; */
            background: #e9e9e9;
        }

        /* body {
            display: flex;
            flex-direction: column;
        } */

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

        /* .header-left {
            display: flex;
            align-items: center;
            gap: 24px;
        } */

        /* .header-logo {
            font-weight: 700;
            font-size: 18px;
            color: #1E293B;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .header-logo i {
            font-size: 22px;
            color: #8e57fe;
        } */

        /* .nav-links {
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
            background: rgba(142, 87, 254, .12);
            color: #334155;
        }

        .nav-links a.active {
            color: #8e57fe;
            background: rgba(142, 87, 254, 0.1);
        } */

        /* .header-right {
            display: flex;
            align-items: center;
            gap: 16px;
        } */

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
            background: #8e57fe;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 16px;
        }

        .logout-btn {
            padding: 8px 16px;
            background: #e9e9e9;
            color: #64748B;
            border: 1px solid #E2E8F0;
            border-radius: 9px;
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
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .toast-message.success {
            background: rgba(27, 123, 57, 0.1);
            color: #1b7b39;
            border: 1px solid rgba(27, 123, 57, 0.3);
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
            border-bottom: 1px solid #e9e9e9;
            cursor: pointer;
            user-select: none;
            transition: background 0.2s ease;
        }

        .group-header:hover {
            background: rgba(142, 87, 254, .12);
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
            background: #e9e9e9;
            padding: 7px 10px;
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
            background: rgba(142, 87, 254, .12);
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
            border-color: #8e57fe;
            box-shadow: 0 0 0 3px rgba(142, 87, 254, 0.1);
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
            background: #e9e9e9;
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

        .toggle-switch input:checked+.toggle-slider {
            background: #8e57fe;
        }

        .toggle-switch input:checked+.toggle-slider:before {
            transform: translateX(22px);
        }

        /* ===== Buttons ===== */
        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
            position: sticky;
            bottom: 0;
            background: #e9e9e9;
            padding: 20px;
            z-index: 10;
        }

        .btn {
            padding: 12px 28px;
            border: none;
            border-radius: 9px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-save {
            background: #8e57fe;
            color: white;
        }

        .btn-save:hover {
            filter: brightness(0.9);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(142, 87, 254, 0.3);
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

        /* ─── دارک‌مود ─── */
        :root[data-theme="dark"] html,
        :root[data-theme="dark"] body {
            background: var(--bg-page);
        }

        :root[data-theme="dark"] .settings-group {
            background: var(--surface);
            border-color: var(--border-soft);
        }

        :root[data-theme="dark"] .group-header {
            border-bottom-color: var(--border-soft);
        }

        :root[data-theme="dark"] .group-count {
            background: var(--bg-page);
        }

        :root[data-theme="dark"] .group-header:hover,
        :root[data-theme="dark"] .setting-item:hover {
            background: rgba(142, 87, 254, .18);
        }

        :root[data-theme="dark"] .group-title,
        :root[data-theme="dark"] .setting-label,
        :root[data-theme="dark"] .setting-control input[type="number"] {
            color: var(--text-strong);
        }

        :root[data-theme="dark"] .group-count,
        :root[data-theme="dark"] .setting-help,
        :root[data-theme="dark"] .setting-unit,
        :root[data-theme="dark"] .group-toggle {
            color: var(--text-muted);
        }

        :root[data-theme="dark"] .setting-item {
            border-bottom-color: var(--border-soft);
        }

        :root[data-theme="dark"] .setting-control input[type="number"] {
            background: var(--bg-page);
            border-color: var(--border-soft);
        }

        :root[data-theme="dark"] .form-actions {
            background: var(--bg-page);
        }

        :root[data-theme="dark"] .btn-reset,
        :root[data-theme="dark"] .btn-back {
            background: var(--surface);
            border-color: var(--border-soft);
            color: var(--text-muted);
        }

        :root[data-theme="dark"] .btn-reset:hover,
        :root[data-theme="dark"] .btn-back:hover {
            background: var(--border-soft);
            color: var(--text-strong);
        }

        :root[data-theme="dark"] .toast-message.success {
            background: rgba(34, 197, 94, .12);
            color: #86efac;
            border-color: rgba(34, 197, 94, .3);
        }

        :root[data-theme="dark"] .toast-message.error {
            background: rgba(239, 68, 68, .12);
            color: #fca5a5;
            border-color: rgba(239, 68, 68, .3);
        }
    </style>
</head>

<body>
    <?php include '../../pages/header.php'; ?>


    <!-- Main Content -->
    <div class="overview-container">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">
                <i class="bi bi-sliders" style="color: #8e57fe;"></i>
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
                                                    <?php echo $setting['value'] == '1' ? 'checked' : ''; ?>>
                                                <span class="toggle-slider"></span>
                                            </label>
                                        <?php else: ?>
                                            <input
                                                type="number"
                                                name="<?php echo $key; ?>"
                                                id="<?php echo $key; ?>"
                                                value="<?php echo htmlspecialchars($setting['value']); ?>"
                                                <?php if (isset($setting['min'])): ?>min="<?php echo $setting['min']; ?>" <?php endif; ?>
                                                <?php if (isset($setting['max'])): ?>max="<?php echo $setting['max']; ?>" <?php endif; ?>
                                                required>
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
                <button type="button" class="btn btn-back" onclick="window.location.href='dashboard-manager.php'">
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
            uiConfirm('آیا می‌خواهید خروج کنید؟', function() {
                localStorage.removeItem('auth_token');
                window.location.href = '../index.php';
            }, {
                danger: true,
                yesText: 'بله، خروج',
                noText: 'انصراف'
            });
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