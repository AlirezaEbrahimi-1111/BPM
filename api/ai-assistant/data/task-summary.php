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
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/TaskManager.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

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
    $stmt = $db->prepare("
        SELECT id, title, status, priority, task_type, due_date, deadline, assignee_id, creator_id
        FROM tasks
        WHERE organization_id = ?
          AND is_deleted = 0
          AND (
              creator_id = ?
              OR assignee_id = ?
              OR EXISTS (
                  SELECT 1 FROM task_history th
                  WHERE th.task_id = tasks.id
                    AND th.from_user_id = ?
                    AND th.action = 'delegated'
                    AND tasks.status NOT IN ($terminalStatuses)
              )
          )
        ORDER BY
            CASE WHEN status NOT IN ($terminalStatuses) THEN 0 ELSE 1 END ASC,
            COALESCE(deadline, due_date) ASC,
            id DESC
        LIMIT 50
    ");
    $stmt->execute([$requester['organization_id'], $targetUserId, $targetUserId, $targetUserId]);
    $taskRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ─── فهرستِ کارهایی که هدف در آن‌ها هنوز «ارجاع‌دهنده‌یِ فعال» است (برایِ برچسبِ نقش) ───
    $stmt = $db->prepare("
        SELECT DISTINCT th.task_id
        FROM task_history th
        JOIN tasks t ON t.id = th.task_id
        WHERE th.from_user_id = ? AND th.action = 'delegated' AND t.status NOT IN ($terminalStatuses)
    ");
    $stmt->execute([$targetUserId]);
    $activeReferrerTaskIds = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN));

    // طبقِ مقادیرِ واقعیِ ستونِ status در جدولِ tasks (نه فرضی)
    $statusLabels = [
        'not_started'      => 'شروع‌نشده',
        'in_progress'      => 'در حالِ انجام',
        'pending_approval' => 'منتظرِ تأیید',
        'completed'        => 'تکمیل‌شده',
        'rejected'         => 'ردشده',
        'stopped'          => 'متوقف‌شده',
        'delegated'        => 'ارجاع‌شده',
        'period_done'      => 'دوره‌ی جاری تکمیل‌شده',
    ];

    // 🔒 «کارهایِ امروز» طبقِ تعریفِ سازمان (نه فقط due_date=امروز): هر کارِ فعالی
    // که موعدش امروز یا زودتر است (یعنی امروز + معوقه‌ها با هم). این عمداً اینجا
    // (سمتِ PHP، با تاریخِ واقعیِ سرور) محاسبه می‌شود، نه به‌عهده‌ی LLM گذاشته
    // می‌شود — چون استدلالِ تاریخ با مدل‌هایِ زبانی قابلِ‌اعتماد نیست.
    $today = date('Y-m-d');
    $dueTodayOrEarlierCount = 0;

    $tasks = array_map(function ($t) use ($targetUserId, $statusLabels, $activeReferrerTaskIds, $today, &$dueTodayOrEarlierCount) {
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

        $dueDate = $t['deadline'] ?: $t['due_date'];
        $isTerminal = in_array($t['status'], ['completed', 'rejected', 'stopped', 'period_done'], true);
        $isDueTodayOrEarlier = $dueDate && !$isTerminal && substr($dueDate, 0, 10) <= $today;
        if ($isDueTodayOrEarlier) {
            $dueTodayOrEarlierCount++;
        }

        return [
            'id'                    => (int) $t['id'],
            'title'                 => $t['title'],
            'status'                => $statusLabels[$t['status']] ?? $t['status'],
            'priority'              => $t['priority'],
            'due_date'              => $dueDate,
            'due_today_or_earlier'  => $isDueTodayOrEarlier,
            'role'                  => implode(' و ', $roles),
        ];
    }, $taskRows);

    echo json_encode([
        'success' => true,
        'user_id' => $targetUserId,
        'stats'   => $stats,
        // طبقِ تعریفِ سازمان: امروز + معوقه = «بایدهایِ امروز» — جایگزینِ
        // stats.today (که فقط due_date دقیقاً امروز را می‌شمارد و باعثِ
        // پاسخِ ناقص می‌شد)
        'due_today_or_earlier_count' => $dueTodayOrEarlierCount,
        'tasks'   => $tasks,
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("AI task-summary error: " . $e->getMessage());
}
