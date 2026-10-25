<?php

/**
 * ═══════════════════════════════════════════════════════════════════
 *  API: dashboard/prefs-set.php
 *  ذخیره‌ی تنظیمات داشبورد — سمت سرور، با تاریخ سرور (نه کلاینت).
 *  تب پیش‌فرض و فیلتر پیش‌فرض روزانه‌اند؛ «منتخب» (starred_tasks) از
 *  ۱۴۰۵/۰۸/۰۳ دائمی و مشترک بین وب و اپ است.
 * ═══════════════════════════════════════════════════════════════════
 */

header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com', 'https://bpm.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'روش غیرمجاز']);
    exit;
}

// فقط همین کلیدها مجازن — جلوگیری از استفاده‌ی این endpoint به‌عنوان
// یه key-value store عمومی
// delegated_scope = تب انتخاب‌شده‌ی ویجت «کارهای واگذار شده (تاخیردار)» (personal|org).
// برخلاف سه کلید دیگه روزانه ریست نمی‌شه — bootstrap.php بدون شرط تاریخ می‌خوندش.
$ALLOWED_KEYS = ['starred_tasks', 'default_tab', 'default_filter', 'delegated_scope'];

try {
    $user_id = requireAuth();
    $input = json_decode(file_get_contents('php://input'), true);

    $prefKey = $input['pref_key'] ?? '';
    if (!in_array($prefKey, $ALLOWED_KEYS, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'کلید نامعتبر']);
        exit;
    }

    // مقدار همیشه به‌صورت رشته ذخیره می‌شه (starred_tasks خودش JSON رشته‌شده می‌فرسته)
    $prefValue = $input['pref_value'] ?? '';
    if (!is_string($prefValue)) {
        $prefValue = json_encode($prefValue, JSON_UNESCAPED_UNICODE);
    }
    if ($prefKey === 'delegated_scope' && !in_array($prefValue, ['personal', 'org'], true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'مقدار نامعتبر']);
        exit;
    }
    // محدودیت طول برای جلوگیری از سوءاستفاده (لیست ستاره‌ها معمولا خیلی کوچیکه)
    if (strlen($prefValue) > 20000) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'مقدار خیلی بزرگ است']);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    // ─── «منتخب» با عملیات تکی (۱۴۰۵/۰۸/۰۳) — وب و اپ هر دو این را می‌فرستند ───
    //   { pref_key:'starred_tasks', op:'add'|'remove', item:'task:123' }
    //   { pref_key:'starred_tasks', op:'merge', items:['task:1', ...] }  (یک‌باره: ستاره‌های قدیمی گوشی)
    // سرور فهرست را خودش زیر قفل به‌روز می‌کند تا دو دستگاه هم‌زمان کار هم را پاک نکنند
    // (ارسال کل فهرست = آخرین ارسال برنده). منتخب دائمی است؛ pref_date برایش خوانده نمی‌شود.
    $op = $input['op'] ?? '';
    if ($prefKey === 'starred_tasks' && $op !== '') {
        if (!in_array($op, ['add', 'remove', 'merge'], true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'عملیات نامعتبر'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $items = $op === 'merge' ? (array) ($input['items'] ?? []) : [$input['item'] ?? ''];
        $items = array_slice(array_values(array_unique(array_map('strval', $items))), 0, 500);
        foreach ($items as $it) {
            if (!preg_match('/^(task|wf):\d+$/', $it)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'شناسهٔ کار نامعتبر است'], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        $db->beginTransaction();
        $cur = $db->prepare("SELECT pref_value FROM user_dashboard_prefs WHERE user_id = ? AND pref_key = 'starred_tasks' FOR UPDATE");
        $cur->execute([$user_id]);
        $list = json_decode((string) $cur->fetchColumn(), true);
        $list = is_array($list) ? array_values(array_filter($list, 'is_string')) : [];
        if ($op === 'remove') {
            $list = array_values(array_diff($list, $items));
        } else {
            $list = array_values(array_unique(array_merge($list, $items)));
        }
        $list = array_slice($list, -1000);
        $save = $db->prepare("
            INSERT INTO user_dashboard_prefs (user_id, pref_key, pref_value, pref_date)
            VALUES (?, 'starred_tasks', ?, CURDATE())
            ON DUPLICATE KEY UPDATE pref_value = VALUES(pref_value), pref_date = VALUES(pref_date)
        ");
        $save->execute([$user_id, json_encode($list, JSON_UNESCAPED_UNICODE)]);
        $db->commit();

        echo json_encode(['success' => true, 'starred_tasks' => $list], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $db->prepare("
        INSERT INTO user_dashboard_prefs (user_id, pref_key, pref_value, pref_date)
        VALUES (?, ?, ?, CURDATE())
        ON DUPLICATE KEY UPDATE pref_value = VALUES(pref_value), pref_date = VALUES(pref_date)
    ");
    $stmt->execute([$user_id, $prefKey, $prefValue]);

    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    http_response_code(500);
    error_log("dashboard/prefs-set.php failed | " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطای سرور'], JSON_UNESCAPED_UNICODE);
}
