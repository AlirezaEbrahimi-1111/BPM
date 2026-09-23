<?php
/**
 * global.php
 * سرچ سراسریِ زیر هدر — روی تسک‌ها، تیکت‌ها، اطلاعیه‌ها، نوتیفیکیشن‌ها،
 * و فرآیندهای در حال اجرا (workflow) هم‌زمان جستجو می‌کنه، با همون قواعد
 * دسترسیِ APIهای اصلیِ هر بخش (quick-search.php برای تسک/تیکت،
 * api/announcements/list.php برای اطلاعیه، Notification.php برای
 * نوتیفیکیشن) تا نتیجه هرگز چیزی بیرون از دسترسِ کاربر رو نشون نده.
 *
 * تطبیق: کلمه‌به‌کلمه (AND) — هر کلمه‌ی جستجوشده باید جایی در فیلدهای
 * قابل‌جستجوی همون آیتم پیدا بشه، نه اینکه کل عبارتِ تایپ‌شده باید عیناً
 * پشت‌سرهم پیدا بشه.
 *
 * 🔒 اولویت‌بندیِ نتایجِ «تسک»: دیگه یه دسته‌ی جداگانه به‌اسمِ «تاریخچه‌ی
 * تسک» وجود نداره — عنوان/توضیحات/آیتم‌هایِ چک‌لیست/تاریخچه‌ی هر تسک همه
 * زیرِ همون نتیجه‌یِ «کار» جستجو می‌شن، و رتبه‌ی هر کار توسطِ بالاترین‌
 * اولویت‌ترین لایه‌ای که عبارتِ جست‌وجو توش پیدا شده تعیین می‌شه: عنوان
 * (۱) > توضیحات (۲) > چک‌لیست (۳) > تاریخچه (۴).
 */

header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';

function gs_snippet($text, $len = 90) {
    $text = trim(preg_replace('/\s+/u', ' ', (string) $text));
    if ($text === '') return '';
    return mb_strlen($text) > $len ? mb_substr($text, 0, $len) . '…' : $text;
}

/** هر کلمه باید در حداقل یکی از فیلدهای داده‌شده پیدا بشه؛ کلمات با AND بهم وصل می‌شن */
function gs_word_conditions(array $fields, array $words, array &$params) {
    $wordClauses = [];
    foreach ($words as $w) {
        $fieldClauses = [];
        foreach ($fields as $f) {
            $fieldClauses[] = "$f LIKE ?";
            $params[] = '%' . $w . '%';
        }
        $wordClauses[] = '(' . implode(' OR ', $fieldClauses) . ')';
    }
    return implode(' AND ', $wordClauses);
}

try {
    $user_id = requireAuth();

    $q = trim($_GET['q'] ?? '');
    // نرمال‌سازی ارقام فارسی/عربی به لاتین (برای جستجوی شناسه با رقم فارسی)
    $q = strtr($q, [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ]);

    if (mb_strlen($q) < 2) {
        echo json_encode(['success' => true, 'results' => []], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // کلماتِ جستجو (فاصله‌جدا، خالی‌ها حذف)
    $words = array_values(array_filter(preg_split('/\s+/u', $q), function ($w) {
        return mb_strlen($w) >= 1;
    }));
    if (!$words) $words = [$q];

    // انواعِ درخواستی — پیش‌فرض همه
    $allTypes = ['task', 'ticket', 'announcement', 'notification', 'workflow'];
    $reqTypes = isset($_GET['types']) ? explode(',', $_GET['types']) : $allTypes;
    $activeTypes = array_values(array_intersect($allTypes, $reqTypes));
    if (!$activeTypes) $activeTypes = $allTypes;
    $want = array_flip($activeTypes);

    $database = new Database();
    $db = $database->getConnection();

    $me = loadUserForPermissions($db, (int) $user_id);
    $isSuper = isSuperAdmin($me);
    $orgId = (int) ($me['organization_id'] ?? 0);

    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/user-sections.php';
    $userSections = us_getUserSections($db, (int) $user_id);
    $sectionsCsv = implode(',', $userSections);

    $idMatch = ctype_digit($q) ? (int) $q : 0;
    $perType = 6;
    $results = [];

    // ───── تسک‌ها ─────
    if (isset($want['task'])) {
        // 🔒 شرطِ دسترسی باید با taskUserAccess() صفحهٔ جزئیات هم‌راستا باشد؛
        // قبلاً فقط creator/assignee/تاریخچه بود، پس کاری که یک مدیر/سرپرست
        // می‌توانست باز کند ولی سازنده/مسئولش نبود، در سرچ دیده نمی‌شد.
        $canViewAllOrg = hasPermission($me, 'view_all_org_tasks');

        if ($canViewAllOrg) {
            $accessSql = 't.organization_id = ?';
            $accessParams = [$orgId];
        } else {
            $subIds = getSubordinateIds($db, (int) $user_id);
            $subList = $subIds ?: [0];
            $ph = implode(',', array_fill(0, count($subList), '?'));
            $accessSql = "(
                  t.creator_id = ? OR t.assignee_id = ?
                  OR t.creator_id IN ($ph) OR t.assignee_id IN ($ph)
                  OR EXISTS (
                      SELECT 1 FROM task_history th
                      WHERE th.task_id = t.id AND (th.from_user_id = ? OR th.to_user_id = ?)
                  )
                  OR EXISTS (
                      SELECT 1 FROM task_viewers tv
                      WHERE tv.task_id = t.id AND tv.user_id = ?
                  )
              )";
            $accessParams = array_merge(
                [$user_id, $user_id],
                $subList, $subList,
                [$user_id, $user_id, $user_id]
            );
        }

        // 🔒 اولویت‌بندیِ نتایج: هر کاری که کلِ عبارتِ جست‌وجو (همه‌ی کلمات، AND)
        // توی «فقط عنوان» پیدا بشه، رتبه‌ی ۱ می‌گیره؛ وگرنه اگه توی «عنوان+
        // توضیحات» پیدا بشه رتبه‌ی ۲؛ وگرنه اگه توی «عنوان+توضیحات+چک‌لیست»
        // پیدا بشه رتبه‌ی ۳؛ وگرنه (یعنی فقط با کمکِ تاریخچه پیدا شده) رتبه‌ی
        // ۴. چون چک‌لیست/تاریخچه رابطه‌ی یک‌به‌چند با کار دارن (هر کار چند
        // آیتم/رویداد داره)، به‌جایِ LIKEِ مستقیم از EXISTS استفاده می‌شه —
        // هر کلمه باید *جایی* (حتی اگه هرکلمه توی یه آیتم/رکوردِ متفاوت باشه)
        // در دسترسِ همون لایه پیدا بشه.
        $p1 = [];
        $tier1Sql = gs_word_conditions(['t.title'], $words, $p1);

        $p2 = [];
        $tier2Sql = gs_word_conditions(['t.title', 't.description'], $words, $p2);

        $p3 = [];
        $tier3Clauses = [];
        foreach ($words as $w) {
            $tier3Clauses[] = "(t.title LIKE ? OR t.description LIKE ? OR EXISTS (
                SELECT 1 FROM task_checklist_items tci
                WHERE tci.task_id = t.id AND (tci.title LIKE ? OR tci.description LIKE ?)
            ))";
            array_push($p3, '%' . $w . '%', '%' . $w . '%', '%' . $w . '%', '%' . $w . '%');
        }
        $tier3Sql = implode(' AND ', $tier3Clauses);

        $p4 = [];
        $tier4Clauses = [];
        foreach ($words as $w) {
            $tier4Clauses[] = "(t.title LIKE ? OR t.description LIKE ? OR EXISTS (
                SELECT 1 FROM task_checklist_items tci
                WHERE tci.task_id = t.id AND (tci.title LIKE ? OR tci.description LIKE ?)
            ) OR EXISTS (
                SELECT 1 FROM task_history th2
                WHERE th2.task_id = t.id AND th2.notes LIKE ?
            ))";
            array_push($p4, '%' . $w . '%', '%' . $w . '%', '%' . $w . '%', '%' . $w . '%', '%' . $w . '%');
        }
        $tier4Sql = implode(' AND ', $tier4Clauses);

        // ترتیبِ زیر باید دقیقاً با ترتیبِ ظاهرشدنِ «?» در متنِ کوئری یکی
        // باشه: اول CASE (p1,p2,p3) توی SELECT، بعد accessSql، بعد شرطِ
        // نهاییِ WHERE (p4)، بعد دوتا idMatch
        $params = array_merge($p1, $p2, $p3, $accessParams, $p4, [$idMatch, $idMatch]);

        $stmt = $db->prepare("
            SELECT t.id, t.title, t.description,
              CASE
                WHEN ($tier1Sql) THEN 1
                WHEN ($tier2Sql) THEN 2
                WHEN ($tier3Sql) THEN 3
                ELSE 4
              END AS match_tier
            FROM tasks t
            WHERE t.is_deleted = 0
              AND t.status NOT IN ('completed', 'approved', 'stopped', 'rejected')
              AND $accessSql
              AND ($tier4Sql OR t.id = ? OR t.workflow_instance_id = ?)
            ORDER BY match_tier ASC, t.created_at DESC
            LIMIT $perType
        ");
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $results[] = [
                'type' => 'task',
                'type_label' => 'کار',
                'icon' => 'bi-list-task',
                'id' => (int) $row['id'],
                'title' => $row['title'],
                'snippet' => gs_snippet($row['description']),
                'link' => 'task-detail.php?id=' . $row['id'],
            ];
        }
    }

    // ───── تیکت‌ها (عنوان + پیام‌ها) ─────
    if (isset($want['ticket'])) {
        $msgParams = [];
        $msgWordSql = gs_word_conditions(['tm.message'], $words, $msgParams);
        $params = array_merge($msgParams, [$orgId, $isSuper ? 1 : 0]);
        $subjectWordSql = gs_word_conditions(['t.subject'], $words, $params);
        $params[] = $idMatch;
        $stmt = $db->prepare("
            SELECT DISTINCT t.id, t.subject, t.ticket_number
            FROM tickets t
            LEFT JOIN ticket_messages tm ON tm.ticket_id = t.id AND tm.deleted_at IS NULL AND ($msgWordSql)
            WHERE t.deleted_at IS NULL
              AND (t.organization_id = ? OR ?)
              AND ($subjectWordSql OR t.id = ? OR tm.id IS NOT NULL)
            ORDER BY t.created_at DESC
            LIMIT $perType
        ");
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $results[] = [
                'type' => 'ticket',
                'type_label' => 'تیکت',
                'icon' => 'bi-headset',
                'id' => (int) $row['id'],
                'title' => $row['subject'],
                'snippet' => $row['ticket_number'] ? ('#' . $row['ticket_number']) : '',
                'link' => 'ticket-detail.php?id=' . $row['id'],
            ];
        }
    }

    // ───── اطلاعیه‌ها ─────
    if (isset($want['announcement'])) {
        if ($isSuper) {
            $params = [];
            $wordSql = gs_word_conditions(['title', 'content'], $words, $params);
            $stmt = $db->prepare("
                SELECT id, title, content, priority, created_at
                FROM announcements
                WHERE is_active = 1 AND ($wordSql)
                ORDER BY created_at DESC
                LIMIT $perType
            ");
            $stmt->execute($params);
        } else {
            $params = [];
            $wordSql = gs_word_conditions(['a.title', 'a.content'], $words, $params);
            array_push($params, $user_id, $orgId, $sectionsCsv);
            $stmt = $db->prepare("
                SELECT id, title, content, priority, created_at
                FROM announcements a
                WHERE a.is_active = 1
                  AND ($wordSql)
                  AND (
                      a.target_user_id = ?
                      OR (
                          a.target_user_id IS NULL
                          AND (
                              a.organization_id IS NULL
                              OR (a.organization_id = ? AND (a.target_section IS NULL OR FIND_IN_SET(a.target_section, ?)))
                          )
                      )
                  )
                ORDER BY a.created_at DESC
                LIMIT $perType
            ");
            $stmt->execute($params);
        }
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $results[] = [
                'type' => 'announcement',
                'type_label' => 'اطلاعیه',
                'icon' => 'bi-megaphone',
                'id' => (int) $row['id'],
                'title' => $row['title'],
                'snippet' => gs_snippet($row['content']),
                'content' => $row['content'],
                'priority' => $row['priority'],
                'created_at' => $row['created_at'],
                'link' => null,
            ];
        }
    }

    // ───── نوتیفیکیشن‌ها (فقط مالِ خودِ کاربر) ─────
    if (isset($want['notification'])) {
        $params = [$user_id];
        $wordSql = gs_word_conditions(['message'], $words, $params);
        $stmt = $db->prepare("
            SELECT id, message, related_type, related_id, is_read
            FROM notifications
            WHERE user_id = ? AND ($wordSql)
            ORDER BY created_at DESC
            LIMIT $perType
        ");
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $link = null;
            if ($row['related_type'] === 'task' && $row['related_id']) {
                $link = 'task-detail.php?id=' . $row['related_id'];
            } elseif ($row['related_type'] === 'ticket' && $row['related_id']) {
                $link = 'ticket-detail.php?id=' . $row['related_id'];
            }
            $results[] = [
                'type' => 'notification',
                'type_label' => 'نوتیفیکیشن',
                'icon' => 'bi-bell',
                'id' => (int) $row['id'],
                'title' => gs_snippet($row['message'], 70),
                'snippet' => '',
                'message' => $row['message'],
                'is_read' => (bool) $row['is_read'],
                'link' => $link,
            ];
        }
    }

    // ───── فرآیندهای در حال اجرا (workflow instances / روتین‌ها) ─────
    if (isset($want['workflow'])) {
        $params = [$orgId];
        $wordSql = gs_word_conditions(['wt.name', 'wi.title'], $words, $params);
        $params[] = $idMatch;   // wi.id — جستجوی روتین با شناسهٔ آن
        $stmt = $db->prepare("
            SELECT wi.id, COALESCE(NULLIF(wi.title, ''), wt.name) AS name
            FROM workflow_instances wi
            LEFT JOIN workflow_templates wt ON wt.id = wi.template_id
            WHERE wi.organization_id = ? AND wi.is_deleted = 0
              AND ($wordSql OR wi.id = ?)
            ORDER BY wi.started_at DESC
            LIMIT $perType
        ");
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $results[] = [
                'type' => 'workflow',
                'type_label' => 'روتین',
                'icon' => 'bi-diagram-3',
                'id' => (int) $row['id'],
                'title' => '#' . $row['id'] . ' — ' . ($row['name'] ?? 'روتین'),
                'snippet' => '',
                // به صفحهٔ مانیتورینگ برو و همان شناسه را در کادر جستجو فیلتر کن
                'link' => 'workflow-monitor.php?search=' . $row['id'],
            ];
        }
    }

    echo json_encode(['success' => true, 'query' => $q, 'results' => $results], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log('global search error: ' . $e->getMessage());
}
