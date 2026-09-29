<?php

/**
 * ═══════════════════════════════════════════════════════════════════
 *  attendance_system/api/attendance/settings.php
 *  لایه‌ی داده‌ی «تنظیمات سیستم» — جدا از HTML.
 * ───────────────────────────────────────────────────────────────────
 *  قبلا pages/settings.php هم مستقیم به جدول settings وصل می‌شد هم کل
 *  HTML صفحه رو می‌ساخت. حالا منطقِ داده (تعریفِ تنظیمات + خواندن/
 *  نوشتنِ جدول) توی دو تابعِ این فایل جمع شده:
 *
 *    attendanceSettingsGetAll(PDO $db): array
 *    attendanceSettingsSave(PDO $db, array $input): array
 *
 *  این فایل به دو شکل استفاده می‌شه:
 *   ۱) مستقیم به‌عنوان API (GET/POST JSON) — برایِ اپِ موبایل و هر
 *      کلاینتِ دیگه.
 *   ۲) require شده از pages/settings.php — همون دو تابع رو مستقیم صدا
 *      می‌زنه (بدونِ HTTP loopback)، چون صفحه‌ی وب همچنان با فرمِ سنتیِ
 *      POST/رندرِ سمتِ سرور کار می‌کنه و رفتارش نباید عوض بشه.
 *      تشخیصِ این‌که «مستقیم صدا زده شده یا require شده» با مقایسه‌ی
 *      SCRIPT_FILENAME با همین فایله — اگه require شده باشه، بخشِ
 *      پایینِ فایل (auth + دیسپچِ HTTP) اصلا اجرا نمی‌شه.
 *
 *  🔒 دسترسی: هر دو مسیر از همون یک قاعده استفاده می‌کنن —
 *  hasPermission($__me, 'view_org_settings'). فقط روشِ احرازهویت فرق
 *  داره: مسیرِ HTTP مستقیم (برایِ اپِ موبایل) فقط JWT (Authorization
 *  header) رو می‌پذیره؛ صفحه‌ی وب همون منطقِ قبلیِ خودش (سشن/کوکی/هدر)
 *  رو دستِ‌نخورده نگه می‌داره، چون این تابع اصلا auth رو انجام نمی‌ده،
 *  از $db و $__me ای که pages/settings.php از قبل ساخته استفاده می‌کنه.
 * ═══════════════════════════════════════════════════════════════════
 */

/**
 * تعریفِ کاملِ همه‌ی تنظیمات، گروه‌بندی‌شده — عینِ آرایه‌ای که قبلا
 * داخلِ pages/settings.php هاردکد بود، بدونِ هیچ تغییری.
 */
function attendanceSettingsDefinitions(): array
{
    return [
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
            ]
        ],
        'calculation' => [
            'title' => 'قوانین محاسبه حقوق',
            'icon' => 'bi-calculator',
            'color' => '#8e57fe',
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
            'color' => '#1b7b39',
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
}

/**
 * تعریف‌ها + مقدارِ فعلیِ هرکدوم از جدولِ settings. جدول رو اگه نبود
 * می‌سازه، ردیف‌هایِ جاافتاده رو با مقدارِ پیش‌فرض seed می‌کنه — عینِ
 * منطقی که قبلا بالایِ pages/settings.php بود.
 *
 * 🔒 هر آیتمِ تنظیم یک کلیدِ 'key' هم می‌گیره (که قبلا فقط کلیدِ آرایه
 * بود، فیلدِ جدا نبود) — چون یک کلاینتِ JSON (مثلا اپِ موبایل) این‌جوری
 * راحت‌تر می‌تونه هر آیتم رو مستقل از ساختارِ تودرتو پردازش کنه.
 */
function attendanceSettingsGetAll(PDO $db): array
{
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

    $groups = attendanceSettingsDefinitions();

    $default_settings = [];
    foreach ($groups as $group) {
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
        // تنظیمات شاید قبلا ایجاد شده‌اند
    }

    // دریافت تمام تنظیمات (بدون try/catch اینجا — خطا باید به صدازننده
    // برسه تا اون تصمیم بگیره چطور نشونش بده: JSON خطا یا پیامِ صفحه)
    $stmt = $db->prepare("SELECT setting_key, setting_value FROM settings");
    $stmt->execute();
    $current_settings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $current_settings[$row['setting_key']] = $row['setting_value'];
    }

    foreach ($groups as &$group) {
        foreach ($group['settings'] as $key => &$setting) {
            $setting['key'] = $key;
            $setting['value'] = $current_settings[$key] ?? $setting['value'];
        }
        unset($setting);
    }
    unset($group);

    return $groups;
}

/**
 * اعتبارسنجی + ذخیره‌سازی. $input یک آرایه‌ی associative کلید→مقدار
 * است (چه از JSON بدنه‌ی درخواست، چه ساخته‌شده از $_POST توسطِ
 * pages/settings.php) — عینِ همون منطقِ قبلی، فقط به‌جایِ $_POST از
 * $input می‌خونه و به‌جایِ throw کردن به بیرون، خودش catch می‌کنه و
 * {success, message} برمی‌گردونه.
 *
 * متن‌هایِ خطا/موفقیت عینِ قبل، دست‌نخورده.
 */
function attendanceSettingsSave(PDO $db, array $input): array
{
    $groups = attendanceSettingsDefinitions();
    $default_settings = [];
    foreach ($groups as $group) {
        foreach ($group['settings'] as $key => $setting) {
            $default_settings[$key] = $setting;
        }
    }

    try {
        foreach ($default_settings as $key => $setting) {
            if ($setting['type'] === 'boolean') {
                $value = !empty($input[$key]) ? '1' : '0';
            } else {
                $value = $input[$key] ?? '';

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

        return ['success' => true, 'message' => 'تنظیمات با موفقیت ذخیره شدند'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// ═══════════════════════════════════════════════════════════════════
//  دیسپچِ HTTP — فقط وقتی این فایل مستقیم به‌عنوانِ endpoint صدا زده
//  شده باشه اجرا می‌شه (نه وقتی require شده از pages/settings.php)
// ═══════════════════════════════════════════════════════════════════
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    header('Content-Type: application/json; charset=utf-8');

    require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';

    try {
        $database = new Database();
        $db = $database->getConnection();

        $user_id = requireAuth(); // اگه توکن نامعتبر باشه، خودش 401 می‌ده و exit می‌کنه

        $__me = loadUserForPermissions($db, (int) $user_id);
        if (!$__me || !hasPermission($__me, 'view_org_settings')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $method = $_SERVER['REQUEST_METHOD'];

        if ($method === 'GET') {
            $groups = attendanceSettingsGetAll($db);
            echo json_encode(['success' => true, 'groups' => $groups], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($method === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!is_array($input)) $input = [];
            $result = attendanceSettingsSave($db, $input);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            exit;
        }

        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'متد مجاز نیست'], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        error_log('attendance_system/api/attendance/settings.php failed | ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'خطای سرور'], JSON_UNESCAPED_UNICODE);
    }
}
