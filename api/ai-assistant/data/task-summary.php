<?php
/**
 * API دستیارِ هوش‌مصنوعی: خلاصه‌ی کارهایِ یک کاربر + فهرستِ ریزِ کارها
 * GET /api/ai-assistant/data/task-summary.php?user_id=
 *
 * طبقِ بندِ ۶ سندِ docs/ai-assistant/spec-v1.md — یکی از فهرستِ ثابتِ
 * اندپوینت‌هایِ خواندنیِ Orchestrator (نه یک ابزارِ عمومی/Agent). دقیقاً
 * همان کلاسِ Auth و همان منطقِ آمار (TaskManager::getTaskStats) که بقیه‌ی
 * BPM استفاده می‌کند را به‌کار می‌برد — بدونِ منطقِ موازی.
 *
 * ⚠️ در استقرارِ نهایی، این مسیر فقط از سمتِ ai-service (سرور-به-سرور)
 * فراخوانی می‌شود، نه از مرورگر — ولی کنترلِ دسترسیِ زیر (JWT + RBAC)
 * صرف‌نظر از فراخواننده، همیشه برقرار است.
 *
 * user_id (اختیاری): اگر خالی باشد یا برابرِ کاربرِ جاری باشد، خلاصه‌ی
 * خودِ کاربر برمی‌گردد. اگر کاربرِ دیگری از همان سازمان درخواست شود،
 * فقط مدیر/سرپرست مجاز است (همان قاعده‌ی api/tasks/overview.php).
 *
 * علاوه‌بر آمارِ کلی، فهرستِ ریزِ کارها (`tasks`) هم برمی‌گردد — چون
 * سؤالاتی مثلِ «کدام کارها مالِ من است» یا «جزئیاتِ کارِ X چیست» به
 * چیزی بیش از یک شمارشِ ساده نیاز دارند. قاعده‌یِ دقیقِ اینکه چه کاری
 * برایِ کدام کاربر نمایش داده می‌شود (طبقِ تصمیمِ صریحِ کاربر):
 *   ۱) مدیر/سرپرست همیشه اجازه‌ی دیدنِ خلاصه‌یِ هرکسی در سازمانِ خودش را دارد
 *   ۲) تعریف‌کننده‌یِ کار همیشه در فهرستش می‌بیند
 *   ۳) ارجاع‌دهنده تا وقتی کار به وضعیتِ پایانی نرسیده در فهرستش می‌بیند
 *      (چون فیلدِ صریحِ «تاریخِ بازگشت» وجود ندارد، این تفسیرِ عملیِ
 *      «تا وقتی برمی‌گردد» است — از task_history با action='delegated')
 *   ۴) مسئولِ انجامِ *فعلی* (assignee_id) در فهرستش می‌بیند
 *   ۵) فقط کارهایِ همان سازمان — هرگز سازمانِ دیگر
 *   ۶) کارهایِ حذف‌شده (is_deleted=1) تحتِ هیچ شرایطی نمایش داده نمی‌شوند
 * برایِ کنترلِ حجم/لتنسی (بندِ ۱۵)، حداکثر ۵۰ موردِ اخیر برمی‌گردد؛ فازِ
 * بعد می‌تواند فیلتر/صفحه‌بندی اضافه کند.
 *
 * نکته‌یِ «کارهایِ امروز»: طبقِ تعریفِ صریحِ سازمان، «امروز» یعنی امروز +
 * تمامِ کارهایِ معوقه (نه فقط due_date === امروز). به همین دلیل، به‌جایِ
 * تکیه بر `stats.today` (که از منطقِ عمومیِ TaskManager می‌آید و این
 * تعریف را نمی‌داند)، فیلدِ `due_today_or_earlier_count` را هم اضافه
 * کردیم — محاسبه‌شده در همین فایل، مخصوصِ دستیار.
 */

header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/TaskManager.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/working-days-helper.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/task-dates-helper.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/task-status-helper.php';

try {
    $requester_id = requireAuth();

    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("SELECT id, role, organization_id, is_manager FROM users WHERE id = ?");
    $stmt->execute([$requester_id]);
    $requester = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$requester) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'عدم احراز هویت']);
        exit;
    }

    $targetUserId = (int) ($_GET['user_id'] ?? $requester_id);
    if (!$targetUserId) {
        $targetUserId = $requester_id;
    }

    if ($targetUserId !== $requester_id) {
        // 🔒 فقط مدیر/سرپرست اجازه دارد خلاصه‌ی کارهایِ فردِ دیگری را ببیند —
        // همان قاعده‌ی api/tasks/overview.php:205
        $isManager = in_array($requester['role'], ['manager', 'supervisor'], true)
            || (int) ($requester['is_manager'] ?? 0) === 1;
        if (!$isManager) {
            http_response_code(403);
            error_log("AI task-summary denied | requester_id={$requester_id} | target_user_id={$targetUserId}");
            echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
            exit;
        }

        $stmt = $db->prepare("SELECT organization_id FROM users WHERE id = ?");
        $stmt->execute([$targetUserId]);
        $targetOrgId = $stmt->fetchColumn();
        if (!$targetOrgId || (int) $targetOrgId !== (int) $requester['organization_id']) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'کاربرِ موردنظر یافت نشد']);
            exit;
        }
    }

    $taskManager = new TaskManager($db);
    $stats = $taskManager->getTaskStats($targetUserId);

    // ─── قاعده‌یِ دسترسیِ ریزِ کارها (طبقِ تصمیمِ صریحِ کاربر) ───
    // ۱) مدیر همیشه دسترسی دارد → همین بالاتر، در گیتِ requester/target اعمال شد
    // ۲) تعریف‌کننده (creator_id) همیشه دسترسی دارد
    // ۳) ارجاع‌دهنده — کسی که این کار را در task_history با action='delegated'
    //    از خودش به دیگری ارجاع داده — «تا وقتی که برمی‌گردد» دسترسی دارد؛
    //    چون هیچ فیلدِ صریحِ «بازگشت» وجود ندارد، این را این‌طور تفسیر کردیم:
    //    تا وقتی که وضعیتِ کار به حالتِ پایانی (تکمیل/رد/توقف/پایانِ دوره) نرسیده
    // ۴) مسئولِ انجام (assignee_id) — چون این ستون فقط مقدارِ *فعلی* را نگه
    //    می‌دارد (نه تاریخچه)، همین «assignee_id = هدف» خودش دقیقاً همان قاعده‌ی
    //    «فقط وقتی که الان مسئولِ انجام است» را برآورده می‌کند
    // ۵) فقط سازمانِ خودش — با organization_id در WHERE، نه فقط با اعتماد به تطبیقِ کاربر
    // ۶) کارهای حذف‌شده هرگز نمایش داده نمی‌شوند — is_deleted = 0 (بدونِ استثنا)
    $terminalStatuses = "'completed', 'rejected', 'stopped', 'period_done'";
    // 🔒 ستون‌ها و JOINهایِ dr/ph دقیقاً همون‌هایی هستن که api/tasks/my-tasks.php
    // برایِ تشخیصِ «در انتظارِ تأییدِ من» / «در انتظارِ تمدیدِ من» استفاده می‌کنه —
    // بدونِ این‌ها، taskIsOverdue()/taskIsDueToday() نمی‌تونن این دو حالتِ خاص
    // رو تشخیص بدن (includes/task-status-helper.php)
    $stmt = $db->prepare("
        SELECT
            t.id, t.title, t.status, t.priority, t.task_type,
            t.due_date, t.deadline, t.original_deadline, t.start_date, t.end_date,
            t.assignee_id, t.creator_id, t.is_workflow_task, t.is_pending_approval,
            t.has_pending_deadline_request,
            dr.current_approver_id,
            dr.created_at AS deadline_request_date,
            ph.last_pending_date
        FROM tasks t
        LEFT JOIN deadline_requests dr ON t.id = dr.task_id AND dr.status = 'pending'
        LEFT JOIN (
            SELECT h1.task_id, h1.created_at AS last_pending_date
            FROM task_history h1
            WHERE h1.action = 'pending_approval'
              AND NOT EXISTS (
                  SELECT 1 FROM task_history h2
                  WHERE h2.task_id = h1.task_id AND h2.action = 'rejected' AND h2.created_at > h1.created_at
              )
              AND h1.created_at = (
                  SELECT MAX(h3.created_at) FROM task_history h3
                  WHERE h3.task_id = h1.task_id AND h3.action = 'pending_approval'
                    AND NOT EXISTS (
                        SELECT 1 FROM task_history h4
                        WHERE h4.task_id = h3.task_id AND h4.action = 'rejected' AND h4.created_at > h3.created_at
                    )
              )
        ) ph ON t.id = ph.task_id
        WHERE t.organization_id = ?
          AND t.is_deleted = 0
          AND (
              t.creator_id = ?
              OR t.assignee_id = ?
              OR EXISTS (
                  SELECT 1 FROM task_history th
                  WHERE th.task_id = t.id
                    AND th.from_user_id = ?
                    AND th.action = 'delegated'
                    AND t.status NOT IN ($terminalStatuses)
              )
          )
        ORDER BY
            CASE WHEN t.status NOT IN ($terminalStatuses) THEN 0 ELSE 1 END ASC,
            COALESCE(t.deadline, t.due_date) ASC,
            t.id DESC
        LIMIT 50
    ");
    $stmt->execute([$requester['organization_id'], $targetUserId, $targetUserId, $targetUserId]);
    $taskRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // برایِ enrichTaskDates()/pe_state() — همون الگویِ بقیه‌یِ ماژولِ کارها
    // (بدونِ organization_id، طبقِ رفتارِ فعلیِ سراسریِ این ماژول)
    $holidays = getHolidaySet($db);

    // ─── فهرستِ کارهایی که هدف در آن‌ها هنوز «ارجاع‌دهنده‌یِ فعال» است (برایِ برچسبِ نقش) ───
    $stmt = $db->prepare("
        SELECT DISTINCT th.task_id
        FROM task_history th
        JOIN tasks t ON t.id = th.task_id
        WHERE th.from_user_id = ? AND th.action = 'delegated' AND t.status NOT IN ($terminalStatuses)
    ");
    $stmt->execute([$targetUserId]);
    $activeReferrerTaskIds = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN));

    // 🔒 معوقه/امروز/برچسبِ وضعیت — دیگه این‌جا دوباره و ساده‌شده محاسبه
    // نمی‌شن؛ همون طبقه‌بندی‌کننده‌یِ کاننیکِ includes/task-status-helper.php
    // صدا زده می‌شه (که خودش دقیقاً هم‌معنیِ TF.isOverdue/TF.isDueToday در
    // assets/js/task-filters.js است — یعنی دستیار دقیقاً همون چیزی رو
    // می‌بینه که خودِ کاربر روی داشبورد می‌بینه، نه یک تفسیرِ ساده‌شده‌یِ
    // جداگانه). تاریخ عمداً سمتِ PHP محاسبه می‌شه، نه به‌عهده‌یِ LLM.
    $today = date('Y-m-d');
    $dueTodayOrEarlierCount = 0;

    $tasks = array_map(function ($t) use ($targetUserId, $activeReferrerTaskIds, $today, $db, $holidays, &$dueTodayOrEarlierCount) {
        $roles = [];
        if ((int) $t['creator_id'] === $targetUserId) {
            $roles[] = 'تعریف‌کننده';
        }
        if ((int) $t['assignee_id'] === $targetUserId) {
            $roles[] = 'مسئولِ انجام';
        }
        if (isset($activeReferrerTaskIds[$t['id']])) {
            $roles[] = 'ارجاع‌دهنده (در حالِ پیگیری)';
        }

        // next_due_date/overdue_periods (برایِ کارِ دوره‌ای) از همین‌جا میان —
        // همون موتورِ مشترکی که api/tasks/detail.php و بقیه‌یِ صفحات استفاده می‌کنن
        $t = enrichTaskDates($t, $db, $holidays, $today);
        $statusInfo = taskStatusInfo($t, $targetUserId, $today);

        if ($statusInfo['is_overdue'] || $statusInfo['is_due_today']) {
            $dueTodayOrEarlierCount++;
        }

        $dueDate = $t['next_due_date'] ?: ($t['deadline'] ?: $t['due_date']);

        return [
            'id'                    => (int) $t['id'],
            'title'                 => $t['title'],
            'status'                => $statusInfo['label'],
            'priority'              => $t['priority'],
            'due_date'              => $dueDate,
            'is_overdue'            => $statusInfo['is_overdue'],
            'is_due_today'          => $statusInfo['is_due_today'],
            'due_today_or_earlier'  => $statusInfo['is_overdue'] || $statusInfo['is_due_today'],
            'role'                  => implode(' و ', $roles),
        ];
    }, $taskRows);

    // آمارِ «معوقه/امروز» برایِ خلاصه‌یِ کلی، از همین فهرست جمع زده می‌شه (نه
    // از TaskManager::getTaskStats) — تا خلاصه و ریزِ کارها همیشه با هم
    // بخونن؛ getTaskStats قاعده‌یِ متفاوتی برایِ «معوقه» داره (کارهایِ
    // فرآیندی رو نمی‌بینه، تمدیدِ مهلت رو لحاظ نمی‌کنه) که فعلاً دست‌نخورده
    // مونده چون جایِ دیگه هم استفاده می‌شه
    $overdueCount = count(array_filter($tasks, fn($t) => $t['is_overdue']));

    echo json_encode([
        'success' => true,
        'user_id' => $targetUserId,
        'stats'   => $stats,
        // طبقِ تعریفِ سازمان: امروز + معوقه = «بایدهایِ امروز» — جایگزینِ
        // stats.today (که فقط due_date دقیقاً امروز را می‌شمارد و باعثِ
        // پاسخِ ناقص می‌شد)
        'due_today_or_earlier_count' => $dueTodayOrEarlierCount,
        // 🆕 معوقه‌یِ دقیق و هم‌خوان با فهرستِ ریزِ زیر — به‌جایِ stats.overdue
        'overdue_count' => $overdueCount,
        'tasks'   => $tasks,
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("AI task-summary error: " . $e->getMessage());
}
