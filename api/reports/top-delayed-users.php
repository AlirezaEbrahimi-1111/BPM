<?php

/**
 * ═══════════════════════════════════════════════════════════════════
 *  api/reports/top-delayed-users.php
 *  کاربران (و واحدهای) دارای بیشترین کارهای تأخیردار — سازمان جاری
 * ───────────────────────────────────────────────────────────────────
 *  سه نوع کار شمرده می‌شود:
 *    • مقطعی (periodic)   → due_date < امروز و تکمیل‌نشده
 *    • دوره‌ای (continuous) → overdue_periods > 0 (از موتور مشترک)
 *    • روتین (workflow)    → مرحلهٔ active که is_delayed = 1
 *
 *  نسبت‌دادن تأخیر:
 *    • اگر assignee_id باشد → پای همان کاربر
 *    • اگر نباشد (روتین ارجاع‌به‌واحد) → پای واحد (activity_section)
 *
 *  خروجی: فهرست نزولی بر اساس مجموع تأخیر، با تفکیک نوع
 *
 *  🔒 چرا 'rejected' هم باید کنار 'completed'/'approved'/'stopped' حذف بشه؟
 *  terminate-period.php با اتمام زودهنگام یک کار دوره‌ای/مقطعی توسط
 *  تعریف‌کننده، فقط status رو 'rejected' می‌کنه — due_date/end_date دست‌نخورده
 *  می‌مونه. بدون این حذف، یه کار سال‌ها پیش بسته‌شده هنوز هر روز تأخیر
 *  «زنده» تولید می‌کنه و مجموع رو کاذب بالا می‌بره (مثلا بجای ۱۰۰ روز
 *  واقعی، ۱۲۳۳ روز). tasks-overview.php و my-tasks.php از قبل rejected رو
 *  کنار می‌ذارن؛ این فایل، تنها جایی بود که یادش رفته بود.
 * ═══════════════════════════════════════════════════════════════════
 */

header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com', 'https://bpm.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/working-days-helper.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/period-engine.php';

try {
    // ── احراز هویت ───────────────────────────────────
    $auth    = new Auth();
    $user_id = $auth->getUserFromToken();
    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'احراز هویت الزامی است'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    // ── دسترسی: باید بتواند کارهای کل سازمان را ببیند ──
    $me = loadUserForPermissions($db, $user_id);
    if (!hasPermission($me, 'view_all_org_tasks') && !hasPermission($me, 'view_org_dashboard_reports')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $org_id = (int) $me['organization_id'];
    if ($org_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'سازمان نامعتبر'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $today    = date('Y-m-d');
    $now      = date('Y-m-d H:i:s');
    $holidays = getHolidaySet($db);

    // انباشتگر: کلید = "user:ID" یا "section:NAME"
    // مقدار = ['type'=>..., 'name'=>..., 'periodic'=>0, 'continuous'=>0, 'workflow'=>0]
    $acc = [];

    // نگاشت شناسهٔ کاربر → نام — فقط کاربران فعال و حذف‌نشده
    // 🔒 طبق درخواست صریح: کارهای یک کاربر غیرفعال/حذف‌شده نه نشون داده
    // بشه نه توی مجموع تأخیر حساب بشه — قبلا (کامنت قدیمی که این‌جا بود)
    // عمدا برعکس این بود (نشون‌دادن نامش با برچسب «(غیرفعال)»)، ولی طبق
    // تصمیم تازه، اون رفتار برعکس شده: پایین‌تر، $bucket() برای
    // assignee_id متعلق به کاربر غیرفعال/حذف‌شده، null برمی‌گردونه و
    // خود کار کلا نادیده گرفته می‌شه (نه حتی زیر واحدش جمع بشه)
    $activeUserNames = [];
    $stmt = $db->prepare("
        SELECT id, first_name, last_name
        FROM users
        WHERE organization_id = ? AND is_deleted = 0 AND is_active = 1
    ");
    $stmt->execute([$org_id]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $u) {
        $activeUserNames[(int) $u['id']] = trim($u['first_name'] . ' ' . $u['last_name']);
    }

    /**
     * کلید کاربر یا واحد را برمی‌گرداند؛ اگر assignee_id متعلق به کاربر
     * غیرفعال/حذف‌شده باشه، null برمی‌گردونه — یعنی صدازننده باید کل اون
     * کار رو نادیده بگیره (نه حتی زیر واحد جمعش بزنه)
     */
    $bucket = function ($assignee_id, $section) use (&$acc, $activeUserNames) {
        if (!empty($assignee_id)) {
            $aid = (int) $assignee_id;
            if (!isset($activeUserNames[$aid])) {
                return null;
            }
            $key = 'user:' . $aid;
            if (!isset($acc[$key])) {
                $acc[$key] = [
                    'kind'       => 'user',
                    'ref_id'     => $aid,
                    'name'       => $activeUserNames[$aid],
                    'periodic'    => 0,
                    'continuous'  => 0,
                    'workflow'    => 0,
                    'delay_days'  => 0,
                    'delay_hours' => 0,
                ];
            }
            return $key;
        }
        // واحد
        $sec = $section ?: 'نامشخص';
        $key = 'section:' . $sec;
        if (!isset($acc[$key])) {
            $acc[$key] = [
                'kind'        => 'section',
                'ref_id'      => $sec,
                'name'        => $sec,
                'periodic'    => 0,
                'continuous'  => 0,
                'workflow'    => 0,
                'delay_days'  => 0,
                'delay_hours' => 0,
            ];
        }
        return $key;
    };

    // ══════════════════════════════════════════════
    //  ۱) کارهای مقطعی تأخیردار
    // ══════════════════════════════════════════════
    // 🔒 موعد واقعی، due_date خام نیست — بزرگ‌ترین due_date/deadline/
    // original_deadline است (دقیقا مثل effectiveDue در task-filters.js
    // و ORDER BY در my-tasks.php)؛ چون تمدید موعد فقط ستون deadline رو
    // آپدیت می‌کنه (api/tasks/approve-deadline.php)، نه due_date. بدون این،
    // کاری که موعدش تمدید شده هنوز بر اساس due_date قدیمی‌اش «تأخیردار»
    // حساب می‌شه — برای همیشه، چون due_date دیگه هیچ‌وقت آپدیت نمی‌شه
    // 🔒 effective_due با HAVING (نه max/array_filter سمت PHP) محاسبه می‌شه —
    // چون اگه هر سه‌تا ستون یه مقدار غیرواقعی/تهی داشته باشن (مثلا '' یا
    // ردیف قدیمی خراب)، max() سمت PHP روی آرایه‌ی خالی خطای کشنده می‌ده،
    // و اگه سنتینل '1000-01-01' به‌جای همچین تاریخی به
    // calcPeriodicDelayWorkingDays برسه، حلقه‌ی روزبه‌روزش باید ~۳۷۵هزار روز
    // رو بشمره → timeout و خرابی کل ویجت. HAVING این ردیف‌ها رو قبل از
    // رسیدن به PHP حذف می‌کنه
    // 🔒 CAST(...AS DATE) — بدون این، GREATEST/COALESCE این سه ستون رو به‌عنوان
    // رشته می‌بینه، و چون due_date/deadline/original_deadline collation
    // یکسانی باهم ندارن (ناهماهنگی قدیمی خود اسکیما)، مقایسه‌ی HAVING با
    // خطای «Illegal mix of collations» (1267) کرش می‌کنه. تبدیل به DATE این
    // مشکل رو کاملا کنار می‌ذاره چون DATE اصلا collation نداره
    $stmt = $db->prepare("
        SELECT id, assignee_id, activity_section,
            GREATEST(
                COALESCE(CAST(due_date AS DATE), CAST('1000-01-01' AS DATE)),
                COALESCE(CAST(deadline AS DATE), CAST('1000-01-01' AS DATE)),
                COALESCE(CAST(original_deadline AS DATE), CAST('1000-01-01' AS DATE))
            ) AS effective_due
        FROM tasks
        WHERE organization_id = ?
          AND is_deleted = 0
          AND task_type = 'periodic'
          AND status NOT IN ('completed', 'approved', 'stopped', 'rejected')
        HAVING effective_due > '1000-01-01' AND effective_due < ?
    ");
    $stmt->execute([$org_id, $today]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $t) {
        $k = $bucket($t['assignee_id'], $t['activity_section']);
        if ($k === null) continue;
        $acc[$k]['periodic']++;
        $acc[$k]['delay_days'] += calcPeriodicDelayWorkingDays(substr($t['effective_due'], 0, 10), $today, $holidays);
    }

    // ══════════════════════════════════════════════
    //  ۲) کارهای دوره‌ای تأخیردار (از موتور مشترک)
    // ══════════════════════════════════════════════
    $stmt = $db->prepare("
        SELECT * FROM tasks
        WHERE organization_id = ?
          AND is_deleted = 0
          AND task_type = 'continuous'
          AND status NOT IN ('completed', 'approved', 'stopped', 'rejected')
    ");
    $stmt->execute([$org_id]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $t) {
        $state = pe_state($db, $t, $holidays, $today);
        if ($state['overdue_periods'] > 0) {
            $k = $bucket($t['assignee_id'], $t['activity_section']);
            if ($k === null) continue;
            $acc[$k]['continuous']++;
            $acc[$k]['delay_days'] += $state['working_days_delayed'];
        }
    }

    // ══════════════════════════════════════════════
    //  ۳) کارهای روتین تأخیردار (مرحلهٔ active و از موعد گذشته)
    // ══════════════════════════════════════════════
    // 🔒 قبلا این کوئری فقط t.deadline رو می‌دید (AND deadline IS NOT NULL) —
    // با این فرض که برای کار روتین همیشه پره. این فرض همیشه درست نبود:
    // چندتا کار روتین واقعی پیدا شدن که deadline‌شون خالی بود ولی
    // due_date پر بود (و ماه‌ها گذشته)، و این کوئری کلا حذفشون می‌کرد —
    // یعنی نه فقط توی این ویجت دیده نمی‌شدن، بلکه delay_hours مجموع
    // واحد/کاربر هم کمتر از واقعیت حساب می‌شد. الان مثل
    // get-today-activities.php از GREATEST(due_date/deadline/
    // original_deadline) استفاده می‌کنه؛ اگه فقط due_date (بدون ساعت)
    // موجود بود، انتهای همون روز (۲۳:۵۹:۵۹) در نظر گرفته می‌شه.
    // 🔒 CAST(...AS DATETIME) — بدون این، GREATEST/COALESCE این سه ستون رو
    // به‌عنوان رشته می‌بینه، و چون due_date/deadline/original_deadline
    // collation یکسانی باهم ندارن (همون ناهماهنگی قدیمی اسکیما که
    // effective_due پایین‌تر هم با CAST AS DATE ازش دور می‌زنه)، خطای
    // «Illegal mix of collations» (1267) می‌ده — روی دیتای واقعی تست
    // محلی گرفت.
    $stmt = $db->prepare("
        SELECT t.id, t.assignee_id, t.activity_section,
            GREATEST(
                COALESCE(CAST(CONCAT(t.due_date, ' 23:59:59') AS DATETIME), CAST('1000-01-01 00:00:00' AS DATETIME)),
                COALESCE(CAST(t.deadline AS DATETIME), CAST('1000-01-01 00:00:00' AS DATETIME)),
                COALESCE(CAST(t.original_deadline AS DATETIME), CAST('1000-01-01 00:00:00' AS DATETIME))
            ) AS effective_deadline
        FROM tasks t
        JOIN workflow_instance_steps wis ON wis.task_id = t.id
        WHERE t.organization_id = ?
          AND t.is_deleted = 0
          AND t.is_workflow_task = 1
          AND wis.status IN ('active', 'pending', 'delayed')
          AND (t.due_date IS NOT NULL OR t.deadline IS NOT NULL OR t.original_deadline IS NOT NULL)
          AND t.status NOT IN ('completed', 'approved', 'stopped', 'rejected')
        HAVING effective_deadline > '1000-01-01 00:00:00' AND effective_deadline < ?
    ");
    $stmt->execute([$org_id, $now]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $t) {
        $k = $bucket($t['assignee_id'], $t['activity_section']);
        if ($k === null) continue;
        $acc[$k]['workflow']++;
        // 🔒 روتین/فرآیندی ساعتی حساب می‌شه، نه روز کاری — طبق قاعده‌ی
        // «کارهای روتین همیشه ساعتی» — بدون کوتاه‌کردن deadline به روز
        $acc[$k]['delay_hours'] += calcHourDelay($t['effective_deadline'], $now);
    }

    // ── خروجی: مرتب نزولی — اول بر اساس روز کاری (مقطعی+دوره‌ای)، بعد ساعت
    // روتین؛ دو واحد قاطی نمی‌شن، هرکدوم جدا نمایش داده می‌شه (لایه‌ی UI) ──
    $result = [];
    foreach ($acc as $row) {
        if ($row['delay_days'] <= 0 && $row['delay_hours'] <= 0) continue;
        $row['total'] = $row['delay_days']; // 🔒 برای سازگاری عقب‌رو با هر مصرف‌کننده‌ی قدیمی
        $result[] = $row;
    }

    usort($result, fn($a, $b) => ($b['delay_days'] <=> $a['delay_days']) ?: ($b['delay_hours'] <=> $a['delay_hours']));

    echo json_encode([
        'success' => true,
        'users'   => $result,
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور'], JSON_UNESCAPED_UNICODE);
}
