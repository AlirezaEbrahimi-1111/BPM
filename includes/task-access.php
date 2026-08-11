<?php
/**
 * قاعده‌ی مشترکِ «آیا این کاربر به این کار دسترسی داره؟» — قبلاً به‌طورِ
 * مستقل در detail.php، get-attachments.php، و upload-attachment.php کپی
 * شده بود و به‌مرورِ زمان از هم واگرا شده بود (مثلاً upload-attachment.php
 * نه قانونِ task_viewers رو داشت نه قانونِ چک‌لیستِ چندواحدی رو). این فایل
 * تنها منبعِ حقیقتِ زنجیره‌یِ اصلیِ دسترسیه؛ قوانینِ تخصصیِ خودِ detail.php
 * (عضویتِ مرحله‌ی workflow، عضویتِ سراسریِ واحد برایِ کارهایِ روتین) همچنان
 * جداگانه در خودِ detail.php می‌مونن چون مخصوصِ نمایشِ کاملِ جزئیاتن، نه
 * لیست‌کردن/آپلودِ پیوست.
 *
 * $task باید حداقل این کلیدها رو داشته باشه: id, creator_id, assignee_id, organization_id
 */

function taskUserAccess(PDO $db, int $userId, array $task): array
{
    $taskId = (int) $task['id'];
    $result = [
        'has_access' => false,
        'is_checklist_only' => false,
        'is_viewer_only' => false,
        'viewer_can_view_attachments' => true,
        'viewer_can_view_history' => true,
    ];

    // ۱. سازنده یا مسئولِ فعلی
    if ((int) $task['creator_id'] === $userId || (int) $task['assignee_id'] === $userId) {
        $result['has_access'] = true;
        return $result;
    }

    // سوپرادمین یا supervisor/adminِ هم‌سازمانِ این کار
    $me = loadUserForPermissions($db, $userId);
    if (hasPermission($me, 'view_all_org_tasks') && isSameOrganization($me, $task['organization_id'] ?? 0)) {
        $result['has_access'] = true;
        return $result;
    }

    // managerِ فقط اگر سازنده/مسئولِ این کار زیرمجموعهٔ خودش باشد (نه هر «مدیر»ی در سازمان)
    if (canManageTargetUser($db, $me, (int) $task['creator_id'])
        || canManageTargetUser($db, $me, (int) $task['assignee_id'])) {
        $result['has_access'] = true;
        return $result;
    }

    // افرادی که در زنجیره‌ی ارجاعاتِ کار بوده‌اند (تاریخچه)
    $stmt = $db->prepare("
        SELECT COUNT(*) as count
        FROM task_history
        WHERE task_id = ?
          AND (from_user_id = ? OR to_user_id = ?)
          AND action NOT LIKE 'checklist%'
          AND action <> ''
          AND action IS NOT NULL
    ");
    $stmt->execute([$taskId, $userId, $userId]);
    if ((int) $stmt->fetch()['count'] > 0) {
        $result['has_access'] = true;
        return $result;
    }

    // مسئولِ حداقل یک آیتم چک‌لیست (کاربر مستقیم، یا هر یک از واحدهایش)
    $orgStmt = $db->prepare("SELECT organization_id FROM users WHERE id = ?");
    $orgStmt->execute([$userId]);
    $userOrg = $orgStmt->fetchColumn();
    $sameOrg = ((int) $userOrg === (int) ($task['organization_id'] ?? -1));

    require_once __DIR__ . '/user-sections.php';
    $userSections = $sameOrg ? us_getUserSections($db, $userId) : [];
    $ph = us_placeholders($userSections);

    $chkStmt = $db->prepare("
        SELECT COUNT(*) FROM task_checklist_items ci
        WHERE ci.task_id = ?
          AND (
              (ci.assignee_type = 'user'    AND ci.assignee_value = ?)
              OR (ci.assignee_type = 'section' AND ci.assignee_value IN ($ph))
          )
    ");
    $chkStmt->execute(array_merge([$taskId, (string) $userId], $userSections));
    if ((int) $chkStmt->fetchColumn() > 0) {
        $result['has_access'] = true;
        $result['is_checklist_only'] = true;
        return $result;
    }

    // بیننده‌هایِ صریحاً اضافه‌شده (فقط مشاهده — task_viewers)
    $stmt = $db->prepare("SELECT can_view_attachments, can_view_history FROM task_viewers WHERE task_id = ? AND user_id = ?");
    $stmt->execute([$taskId, $userId]);
    $viewerRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($viewerRow) {
        $result['has_access'] = true;
        $result['is_viewer_only'] = true;
        $result['viewer_can_view_attachments'] = (bool) $viewerRow['can_view_attachments'];
        $result['viewer_can_view_history'] = (bool) $viewerRow['can_view_history'];
    }

    return $result;
}
