<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  user-sections.php — واحدهای فعالیت کاربر (چندگانه)
 *  محل: /includes/user-sections.php
 * ───────────────────────────────────────────────────────────────────
 *
 *  ⚠️ این فایل تنها مرجع خواندن «واحدهای یک کاربر» است.
 *     هیچ فایل دیگری نباید مستقیما users.activity_section را
 *     برای تصمیم‌گیری دسترسی بخواند.
 *
 *  ┌─ مدل داده ────────────────────────────────────────────────────┐
 *  │                                                                │
 *  │  users.activity_section       →  واحد «اصلی» (برای نمایش)      │
 *  │  user_activity_sections       →  فهرست کامل واحدها            │
 *  │                                  (شامل خود واحد اصلی)         │
 *  │                                                                │
 *  │  چرا هر دو؟ تا در دورهٔ مهاجرت، کدی که هنوز به‌روز نشده         │
 *  │  دقیقا مثل قبل کار کند. ستون قدیمی حذف نمی‌شود.               │
 *  └────────────────────────────────────────────────────────────────┘
 *
 *  نمونهٔ استفاده در یک کوئری:
 *
 *      $secs = us_getUserSections($db, $user_id);
 *      $ph   = us_placeholders($secs);
 *      $sql  = "... AND t.activity_section IN ($ph)";
 *      $stmt->execute(array_merge([$task_id], $secs));
 *
 * ═══════════════════════════════════════════════════════════════════
 */


/**
 * فهرست واحدهای یک کاربر — واحد اصلی همیشه اول است.
 *
 * اگر جدول جدید برای این کاربر خالی باشد، به ستون قدیمی برمی‌گردد.
 * این «بازگشت امن» باعث می‌شود سیستم حتی پیش از پرکردن جدول هم
 * درست کار کند.
 *
 * @return string[]  آرایهٔ section_key — ممکن است خالی باشد
 */
function us_getUserSections(PDO $db, $user_id): array
{
    $user_id = (int) $user_id;
    if ($user_id <= 0) return [];

    // کش در سطح یک request — یک کاربر معمولا چند بار پرسیده می‌شود
    static $cache = [];
    if (array_key_exists($user_id, $cache)) return $cache[$user_id];

    $sections = [];

    try {
        $stmt = $db->prepare("
            SELECT section_key
            FROM user_activity_sections
            WHERE user_id = ?
            ORDER BY is_primary DESC, section_key ASC
        ");
        $stmt->execute([$user_id]);
        $sections = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (PDOException $e) {
        // جدول هنوز ساخته نشده؟ خطا را می‌بلعیم و به مسیر قدیمی می‌رویم
        error_log("us_getUserSections: " . $e->getMessage());
    }

    // ── بازگشت امن به ستون قدیمی ──────────────────────
    if (empty($sections)) {
        try {
            $stmt = $db->prepare("SELECT activity_section FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $legacy = $stmt->fetchColumn();
            if (!empty($legacy)) {
                $sections = [$legacy];
            }
        } catch (PDOException $e) {
            error_log("us_getUserSections legacy: " . $e->getMessage());
        }
    }

    return $cache[$user_id] = $sections;
}


/**
 * واحد اصلی کاربر — برای نمایش و گزارش‌گیری.
 * (اولین عضو فهرست، چون مرتب‌سازی is_primary DESC است)
 */
function us_getPrimarySection(PDO $db, $user_id): ?string
{
    $sections = us_getUserSections($db, $user_id);
    return $sections[0] ?? null;
}


/**
 * آیا کاربر عضو این واحد است؟
 *
 * جایگزین مستقیم الگوی قدیمی:
 *      $user['activity_section'] === $section
 */
function us_userInSection(PDO $db, $user_id, ?string $section): bool
{
    if ($section === null || $section === '') return false;
    return in_array($section, us_getUserSections($db, $user_id), true);
}


/**
 * رشتهٔ placeholder برای IN — مثلا '?,?,?'
 *
 * اگر فهرست خالی بود، 'NULL' برمی‌گرداند تا شرط IN هیچ ردیفی
 * را برنگرداند (به‌جای خطای نحوی).
 */
function us_placeholders(array $sections): string
{
    if (empty($sections)) return 'NULL';
    return implode(',', array_fill(0, count($sections), '?'));
}


/**
 * شناسهٔ کاربران یک واحد — جهت معکوس.
 *
 * جایگزین الگوی قدیمی:
 *      SELECT id FROM users WHERE activity_section = ?
 *
 * @param int|null $org_id  اگر داده شود، فقط کاربران همان سازمان
 * @return int[]
 */
function us_getSectionUserIds(PDO $db, string $section_key, $org_id = null): array
{
    if ($section_key === '') return [];

    try {
        $sql = "
            SELECT DISTINCT u.id
            FROM users u
            JOIN user_activity_sections uas ON uas.user_id = u.id
            WHERE uas.section_key = ?
              AND u.is_active = 1
              AND u.is_deleted = 0
        ";
        $params = [$section_key];

        if ($org_id !== null) {
            $sql .= " AND u.organization_id = ?";
            $params[] = (int) $org_id;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);

        if (!empty($ids)) return $ids;
    } catch (PDOException $e) {
        error_log("us_getSectionUserIds: " . $e->getMessage());
    }

    // ── بازگشت امن به ستون قدیمی ──────────────────────
    try {
        $sql = "SELECT id FROM users
                WHERE activity_section = ? AND is_active = 1 AND is_deleted = 0";
        $params = [$section_key];

        if ($org_id !== null) {
            $sql .= " AND organization_id = ?";
            $params[] = (int) $org_id;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    } catch (PDOException $e) {
        error_log("us_getSectionUserIds legacy: " . $e->getMessage());
        return [];
    }
}


/**
 * جایگزینی کامل واحدهای یک کاربر (برای فرم مدیریت کاربران).
 *
 * ستون قدیمی users.activity_section هم با واحد اصلی هماهنگ می‌شود
 * تا کد مهاجرت‌نکرده درست کار کند.
 *
 * @param string[] $sections        فهرست واحدها
 * @param string   $primary_section واحد اصلی (باید در فهرست باشد)
 */
function us_setUserSections(PDO $db, $user_id, array $sections, string $primary_section): bool
{
    $user_id = (int) $user_id;
    if ($user_id <= 0) return false;

    // تمیزکاری: حذف تکراری و مقادیر خالی
    $sections = array_values(array_unique(array_filter(array_map('trim', $sections))));
    if (empty($sections)) return false;

    // واحد اصلی باید عضو فهرست باشد؛ وگرنه اولی را اصلی می‌گیریم
    if (!in_array($primary_section, $sections, true)) {
        $primary_section = $sections[0];
    }

    try {
        $db->beginTransaction();

        $db->prepare("DELETE FROM user_activity_sections WHERE user_id = ?")
            ->execute([$user_id]);

        $ins = $db->prepare("
            INSERT INTO user_activity_sections (user_id, section_key, is_primary)
            VALUES (?, ?, ?)
        ");
        foreach ($sections as $s) {
            $ins->execute([$user_id, $s, ($s === $primary_section) ? 1 : 0]);
        }

        // هماهنگ‌سازی ستون قدیمی با واحد اصلی
        $db->prepare("UPDATE users SET activity_section = ? WHERE id = ?")
            ->execute([$primary_section, $user_id]);

        $db->commit();
        return true;
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log("us_setUserSections: " . $e->getMessage());
        return false;
    }
}
