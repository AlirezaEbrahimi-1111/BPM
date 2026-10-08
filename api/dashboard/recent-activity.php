<?php

/**
 * ═══════════════════════════════════════════════════════════════════
 *  API: dashboard/recent-activity.php
 *  «فعالیت‌های اخیر» — یک لاگ تاریخچه‌ای (نه یک لیست کارهای باز فعلی؛
 *  اون‌ها توی تب‌های «کارهای من»/«کارهای واگذار شده» هستن).
 *
 *  دو منبع ترکیب می‌شه:
 *    ۱) task_history — همه‌ی رویدادهای کارهای معمولی و کارهای فرآیندی
 *       (چون هر مرحله‌ی روتین هم یک ردیف tasks داره).
 *    ۲) task_checklist_items — ارجاع/تکمیل آیتم‌های چک‌لیستی که مستقیما
 *       به یه کاربر خاص (نه واحد) اختصاص داده شده.
 *
 *  scope=personal (پیش‌فرض): فقط فعالیت خود کاربر جاری.
 *  scope=org: فعالیت کل سازمان. محدودیت مجوز خاصی نداره؛ هر کسی که این
 *  ویجت رو می‌بینه (یعنی manager/supervisor — دسترسی خود صفحه از قبل
 *  به این دو نقش محدوده) می‌تونه تب سازمانی رو هم ببینه.
 *
 *  نام عامل (actor_name) در هر دو حالت برگردونده می‌شه — توی حالت
 *  شخصی هم، تا همیشه معلوم باشه چه کسی این رویداد رو ایجاد کرده.
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
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

const RECENT_ACTIVITY_LIMIT = 50;
const RECENT_ACTIVITY_DAYS  = 30;
const RECENT_ACTIVITY_MAX_PER_PAGE = 100;

try {
    $user_id = requireAuth();
    $scope   = (($_GET['scope'] ?? '') === 'org') ? 'org' : 'personal';

    $database = new Database();
    $db = $database->getConnection();

    $orgStmt = $db->prepare("SELECT organization_id FROM users WHERE id = ?");
    $orgStmt->execute([$user_id]);
    $org_id = (int) $orgStmt->fetchColumn();

    // ── حالت صفحه‌بندی‌شده (صفحه‌ی «همه‌ی فعالیت‌ها» — pages/recent-activity.php) ──
    // فقط وقتی پارامتر page فرستاده شده باشد؛ داشبورد و bootstrap.php آن را
    // نمی‌فرستند و همان خروجی قبلی (۵۰ ردیف از ۳۰ روز اخیر) را می‌گیرند.
    if (isset($_GET['page'])) {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';

        // 🔒 «کل سازمان» فقط برای همان نقش‌هایی که سوییچر نطاق را در داشبورد
        // می‌بینند (همان شرط api/dashboard/bootstrap.php)؛ بقیه همیشه «شخصی»
        $me = loadUserForPermissions($db, (int) $user_id);
        $canSeeOrg = in_array($me['role'] ?? '', ['manager', 'supervisor'], true) || isSuperAdmin($me);
        $isOrg = ($scope === 'org') && $canSeeOrg;

        $page   = max(1, (int) $_GET['page']);
        $per    = min(RECENT_ACTIVITY_MAX_PER_PAGE, max(1, (int) ($_GET['per'] ?? RECENT_ACTIVITY_LIMIT)));
        $offset = ($page - 1) * $per;
        $search = trim((string) ($_GET['q'] ?? ''));

        // همان سه منبع حالت قدیمی، این‌بار در یک UNION تا صفحه‌بندی و شمارش
        // را خود دیتابیس انجام دهد (بدون سقف ۳۰ روز)
        $hCond = $isOrg ? '' : 'AND th.from_user_id = ?';
        $aCond = $isOrg ? '' : 'AND ci.assignee_value = ?';
        $dCond = $isOrg ? '' : 'AND ci.done_by = ?';
        $union = "
            SELECT 1 AS src, th.id AS rid, th.task_id, t.title, NULL AS item_title, t.is_workflow_task,
                   th.action AS action, th.created_at AS ts,
                   COALESCE(
                       NULLIF(TRIM(CONCAT(COALESCE(fu.first_name,''), ' ', COALESCE(fu.last_name,''))), ''),
                       NULLIF(TRIM(CONCAT(COALESCE(tu.first_name,''), ' ', COALESCE(tu.last_name,''))), '')
                   ) AS actor_name
            FROM task_history th
            JOIN tasks t ON t.id = th.task_id
            LEFT JOIN users fu ON fu.id = th.from_user_id
            LEFT JOIN users tu ON tu.id = th.to_user_id
            WHERE t.organization_id = ? AND t.is_deleted = 0 $hCond
            UNION ALL
            SELECT 2, ci.id, ci.task_id, t.title, ci.title, t.is_workflow_task,
                   'checklist_assigned', ci.created_at,
                   NULLIF(TRIM(CONCAT(COALESCE(au.first_name,''), ' ', COALESCE(au.last_name,''))), '')
            FROM task_checklist_items ci
            JOIN tasks t ON t.id = ci.task_id
            LEFT JOIN users au ON au.id = ci.assignee_value
            WHERE ci.assignee_type = 'user' AND ci.created_at IS NOT NULL
              AND t.organization_id = ? AND t.is_deleted = 0 $aCond
            UNION ALL
            SELECT 3, ci.id, ci.task_id, t.title, ci.title, t.is_workflow_task,
                   'checklist_done', ci.done_at,
                   NULLIF(TRIM(CONCAT(COALESCE(du.first_name,''), ' ', COALESCE(du.last_name,''))), '')
            FROM task_checklist_items ci
            JOIN tasks t ON t.id = ci.task_id
            LEFT JOIN users du ON du.id = ci.done_by
            WHERE ci.is_done = 1 AND ci.done_at IS NOT NULL
              AND t.organization_id = ? AND t.is_deleted = 0 $dCond
        ";
        $params = [];
        foreach ([(int) $user_id, (string) $user_id, (int) $user_id] as $uidParam) {
            $params[] = $org_id;
            if (!$isOrg) {
                $params[] = $uidParam;
            }
        }

        // جستجو در عنوان کار، عنوان آیتم چک‌لیست و نام انجام‌دهنده
        $where = '';
        if ($search !== '') {
            $like = '%' . addcslashes($search, '\\%_') . '%';
            $where = 'WHERE (a.title LIKE ? OR a.item_title LIKE ? OR a.actor_name LIKE ?)';
            array_push($params, $like, $like, $like);
        }

        $cnt = $db->prepare("SELECT COUNT(*) FROM ($union) a $where");
        $cnt->execute($params);
        $total = (int) $cnt->fetchColumn();

        // src و rid فقط برای ترتیب ثابت ردیف‌های هم‌ثانیه‌اند (تا یک ردیف در دو صفحه تکرار نشود)
        $stmt = $db->prepare("SELECT a.* FROM ($union) a $where
                              ORDER BY a.ts DESC, a.src ASC, a.rid DESC
                              LIMIT $per OFFSET $offset");
        $stmt->execute($params);

        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[] = [
                'task_id'          => (int) $row['task_id'],
                'title'            => $row['title'],
                'item_title'       => $row['item_title'],
                'is_workflow_task' => (int) $row['is_workflow_task'],
                'action'           => $row['action'],
                'timestamp'        => $row['ts'],
                'actor_name'       => $row['actor_name'] ?: null,
            ];
        }

        echo json_encode([
            'success'     => true,
            'activities'  => $rows,
            'total'       => $total,
            'page'        => $page,
            'per'         => $per,
            'scope'       => $isOrg ? 'org' : 'personal',
            'can_see_org' => $canSeeOrg,
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    $activities = [];
    $isOrg = ($scope === 'org');

    // ── ۱) رویدادهای task_history (کارهای معمولی + مراحل فرآیندی) ──
    // 🔒 «شخصی» یعنی فقط کارهایی که خود کاربر انجام داده (from_user_id) —
    // قبلا to_user_id هم حساب می‌شد (یعنی رویدادهایی که کس دیگه‌ای انجام
    // داده بود ولی به این کاربر ارجاع/نیاز تأیید داشت)، که چون حالا نام
    // عامل هر ردیف هم نشون داده می‌شه، باعث می‌شد اسم افراد دیگه توی
    // فید «شخصی» ظاهر بشه — گیج‌کننده و غیرمنتظره بود
    $userCond = $isOrg ? '' : 'AND th.from_user_id = :uid';
    $stmt = $db->prepare("
        SELECT
            th.task_id,
            t.title,
            t.is_workflow_task,
            th.action,
            th.created_at AS ts,
            -- 🔒 اولویت با from_user_id (کسی که این اقدام رو انجام داده)؛ اگه
            -- اون کاربر دیگه در دسترس نبود (مثلا حذف فیزیکی)، to_user_id
            -- به‌عنوان جایگزین — تا ردیف بدون نام نمونه
            COALESCE(
                NULLIF(TRIM(CONCAT(COALESCE(fu.first_name,''), ' ', COALESCE(fu.last_name,''))), ''),
                NULLIF(TRIM(CONCAT(COALESCE(tu.first_name,''), ' ', COALESCE(tu.last_name,''))), '')
            ) AS actor_name
        FROM task_history th
        JOIN tasks t ON t.id = th.task_id
        LEFT JOIN users fu ON fu.id = th.from_user_id
        LEFT JOIN users tu ON tu.id = th.to_user_id
        WHERE t.organization_id = :org_id
          AND t.is_deleted = 0
          AND th.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
          $userCond
        ORDER BY th.created_at DESC
        LIMIT " . RECENT_ACTIVITY_LIMIT . "
    ");
    $params = ['org_id' => $org_id, 'days' => RECENT_ACTIVITY_DAYS];
    if (!$isOrg) {
        $params['uid'] = $user_id;
    }
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $activities[] = [
            'task_id'          => (int) $row['task_id'],
            'title'            => $row['title'],
            'item_title'       => null,
            'is_workflow_task' => (int) $row['is_workflow_task'],
            'action'           => $row['action'],
            'timestamp'        => $row['ts'],
            'actor_name'       => $row['actor_name'] ?: null,
        ];
    }

    // ── ۲الف) آیتم‌های چک‌لیستی که به یه کاربر ارجاع شدن ──
    // (شخصی: فقط خود کاربر جاری — سازمانی: هر کسی، با نام همون مسئول)
    $assigneeCond = $isOrg ? '' : 'AND ci.assignee_value = :uid';
    $stmt = $db->prepare("
        SELECT ci.task_id, t.title AS task_title, t.is_workflow_task, ci.title AS item_title, ci.created_at AS ts,
               TRIM(CONCAT(COALESCE(au.first_name,''), ' ', COALESCE(au.last_name,''))) AS actor_name
        FROM task_checklist_items ci
        JOIN tasks t ON t.id = ci.task_id
        LEFT JOIN users au ON au.id = ci.assignee_value
        WHERE ci.assignee_type = 'user'
          AND t.organization_id = :org_id
          AND t.is_deleted = 0
          AND ci.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
          $assigneeCond
        ORDER BY ci.created_at DESC
        LIMIT " . RECENT_ACTIVITY_LIMIT . "
    ");
    $params = ['org_id' => $org_id, 'days' => RECENT_ACTIVITY_DAYS];
    if (!$isOrg) {
        $params['uid'] = (string) $user_id;
    }
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $activities[] = [
            'task_id'          => (int) $row['task_id'],
            'title'            => $row['task_title'],
            'item_title'       => $row['item_title'],
            'is_workflow_task' => (int) $row['is_workflow_task'],
            'action'           => 'checklist_assigned',
            'timestamp'        => $row['ts'],
            'actor_name'       => $row['actor_name'] ?: null,
        ];
    }

    // ── ۲ب) آیتم‌های چک‌لیستی که تکمیل شدن ──
    // (شخصی: فقط خود کاربر جاری تکمیل کرده باشه — سازمانی: هر کسی)
    $doneCond = $isOrg ? '' : 'AND ci.done_by = :uid';
    $stmt = $db->prepare("
        SELECT ci.task_id, t.title AS task_title, t.is_workflow_task, ci.title AS item_title, ci.done_at AS ts,
               TRIM(CONCAT(COALESCE(du.first_name,''), ' ', COALESCE(du.last_name,''))) AS actor_name
        FROM task_checklist_items ci
        JOIN tasks t ON t.id = ci.task_id
        LEFT JOIN users du ON du.id = ci.done_by
        WHERE ci.is_done = 1
          AND ci.done_at IS NOT NULL
          AND t.organization_id = :org_id
          AND t.is_deleted = 0
          AND ci.done_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
          $doneCond
        ORDER BY ci.done_at DESC
        LIMIT " . RECENT_ACTIVITY_LIMIT . "
    ");
    $params = ['org_id' => $org_id, 'days' => RECENT_ACTIVITY_DAYS];
    if (!$isOrg) {
        $params['uid'] = $user_id;
    }
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $activities[] = [
            'task_id'          => (int) $row['task_id'],
            'title'            => $row['task_title'],
            'item_title'       => $row['item_title'],
            'is_workflow_task' => (int) $row['is_workflow_task'],
            'action'           => 'checklist_done',
            'timestamp'        => $row['ts'],
            'actor_name'       => $row['actor_name'] ?: null,
        ];
    }

    // ── ادغام و مرتب‌سازی نزولی بر اساس زمان ──
    usort($activities, fn($a, $b) => strcmp($b['timestamp'], $a['timestamp']));
    $activities = array_slice($activities, 0, RECENT_ACTIVITY_LIMIT);

    echo json_encode(['success' => true, 'activities' => $activities], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    error_log("dashboard/recent-activity.php failed | " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطای سرور'], JSON_UNESCAPED_UNICODE);
}
