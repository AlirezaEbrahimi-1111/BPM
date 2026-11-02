<?php
/**
 * ثبت تغییرات دسترسی «بیننده‌ها» (task_viewers) در تاریخچهٔ کار
 *
 * مشترک بین api/tasks/add-viewers.php، remove-viewer.php و create.php تا متن
 * ردیف‌ها یکی بماند.
 *
 * ⚠️ to_user_id عمدا NULL است (مثل ردیف‌های پیوست): نام بیننده در متن می‌آید
 * تا این ردیف‌ها در منطق‌های زنجیرهٔ ارجاع که روی to_user_id کار می‌کنند اثری نگذارند.
 */

/** برچسب هر مجوز بیننده، با نام ستون task_viewers */
function taskViewerPermissionLabels(): array
{
    return [
        'can_view_attachments' => 'پیوست‌ها',
        'can_view_history'     => 'تاریخچه',
        'can_view_checklist'   => 'چک‌لیست',
    ];
}

/** «شامل پیوست‌ها، تاریخچه» یا «فقط جزئیات اصلی» */
function taskViewerScopeText(array $permissions): string
{
    $on = [];
    foreach (taskViewerPermissionLabels() as $key => $label) {
        if (!empty($permissions[$key])) $on[] = $label;
    }
    return $on ? 'شامل ' . implode('، ', $on) : 'فقط جزئیات اصلی';
}

/** «پیوست‌ها خاموش، چک‌لیست روشن» فقط برای مجوزهای عوض‌شده؛ بدون تغییر: رشتهٔ خالی */
function taskViewerPermissionChanges(array $old, array $new): string
{
    $changes = [];
    foreach (taskViewerPermissionLabels() as $key => $label) {
        if (empty($old[$key]) !== empty($new[$key])) {
            $changes[] = $label . (empty($new[$key]) ? ' خاموش' : ' روشن');
        }
    }
    return implode('، ', $changes);
}

/** @param string $action viewer_added | viewer_updated | viewer_removed */
function logTaskViewerHistory(PDO $db, int $taskId, int $actorId, string $action, string $notes): void
{
    try {
        $db->prepare("INSERT INTO task_history (task_id, from_user_id, to_user_id, action, notes) VALUES (?, ?, NULL, ?, ?)")
            ->execute([$taskId, $actorId, $action, $notes]);
    } catch (Exception $e) {
        error_log("task viewer history insert failed | task_id={$taskId} | " . $e->getMessage());
    }
}
