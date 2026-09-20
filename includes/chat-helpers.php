<?php
/**
 * توابعِ مشترکِ بررسیِ دسترسیِ گروهِ چت — یک‌جا، تا هرجایِ دیگه که بعداً
 * لازم شد (پین، افزودن/حذفِ عضو، تغییرِ عکس، حذفِ پیامِ دیگران، ...) از
 * همینا استفاده کنه، نه یک کپیِ دیگه از همون کوئری.
 *
 * دو سطحِ دسترسی:
 *   - «مدیر» (manager) = سازنده‌یِ گروه یا role='admin' — برایِ کارهایِ
 *     روزمره‌یِ مدیریتی (سنجاق، افزودن/حذفِ عضوِ عادی، تغییرِ عکس).
 *   - «سازنده» (creator) = فقط created_by — برایِ کارهایِ حساس‌تر که
 *     نباید حتی بینِ خودِ مدیرها دست‌به‌دست بشه (عزلِ یک مدیرِ دیگه، حذفِ
 *     گروه، حذفِ پیامِ هرکسی).
 */

function chatUserIsGroupCreator(PDO $db, int $conversationId, int $userId): bool
{
    // 🔒 type='group' اجباریه — وگرنه در گفتگوهای دوطرفه هم created_by
    // (یعنی هرکسی که شروع‌کننده‌ی چت بوده) اشتباهاً «سازنده» حساب می‌شد
    $stmt = $db->prepare("SELECT 1 FROM chat_conversations WHERE id = ? AND created_by = ? AND type = 'group'");
    $stmt->execute([$conversationId, $userId]);
    return (bool) $stmt->fetchColumn();
}

function chatUserIsGroupManager(PDO $db, int $conversationId, int $userId): bool
{
    $stmt = $db->prepare("
        SELECT 1
        FROM chat_conversations c
        JOIN chat_participants cp ON cp.conversation_id = c.id AND cp.user_id = ?
        WHERE c.id = ? AND c.type = 'group' AND (c.created_by = ? OR cp.role = 'admin')
    ");
    $stmt->execute([$userId, $conversationId, $userId]);
    return (bool) $stmt->fetchColumn();
}

/** نقشِ فعلیِ یک عضو ('admin'|'member')، یا null اگر اصلاً عضو نیست */
function chatMemberRole(PDO $db, int $conversationId, int $userId): ?string
{
    $stmt = $db->prepare("SELECT role FROM chat_participants WHERE conversation_id = ? AND user_id = ?");
    $stmt->execute([$conversationId, $userId]);
    $role = $stmt->fetchColumn();
    return $role === false ? null : $role;
}

/**
 * اختیاراتِ اختصاصیِ قابل‌واگذاری به هر مدیرِ گروه — سازنده می‌تونه برایِ
 * هر مدیر جداگانه انتخاب کنه کدوم‌ها رو داشته باشه (لزوماً همه‌شون نه).
 */
const CHAT_GROUP_ADMIN_PERMISSIONS = ['pin', 'add_member', 'remove_member', 'avatar'];

/**
 * اختیاراتِ مؤثرِ یک مدیر — اگر ستونِ permissions برایِ اون ردیف NULL باشه
 * (یعنی سازنده هنوز سفارشی‌اش نکرده)، پیش‌فرض همه‌ی اختیاراته (رفتارِ
 * قدیمی، برایِ سازگاری با مدیرهایِ ازقبل‌موجود). فقط وقتی سازنده صراحتاً
 * یک آرایه ثبت کرده، همون آرایه مرجعه.
 *
 * @return string[] زیرمجموعه‌ای از CHAT_GROUP_ADMIN_PERMISSIONS
 */
function chatGroupAdminEffectivePermissions(PDO $db, int $conversationId, int $userId): array
{
    $stmt = $db->prepare("SELECT role, permissions FROM chat_participants WHERE conversation_id = ? AND user_id = ?");
    $stmt->execute([$conversationId, $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || $row['role'] !== 'admin') {
        return [];
    }
    if ($row['permissions'] === null) {
        return CHAT_GROUP_ADMIN_PERMISSIONS;
    }
    $decoded = json_decode($row['permissions'], true);
    if (!is_array($decoded)) {
        return CHAT_GROUP_ADMIN_PERMISSIONS;
    }
    return array_values(array_intersect($decoded, CHAT_GROUP_ADMIN_PERMISSIONS));
}

/**
 * آیا این کاربر اجازه‌ی یک اقدامِ مشخص (مثلاً 'pin') رو توی این گروه داره؟
 * سازنده همیشه true — صرف‌نظر از هر تنظیمِ اختصاصی. مدیرِ عادی فقط اگر
 * اون اختیارِ خاص براش فعال باشه (یا کلاً سفارشی نشده باشه، یعنی پیش‌فرضِ
 * همه‌ی اختیارات). عضوِ عادی همیشه false.
 */
function chatUserHasGroupPermission(PDO $db, int $conversationId, int $userId, string $permission): bool
{
    if (chatUserIsGroupCreator($db, $conversationId, $userId)) {
        return true;
    }
    return in_array($permission, chatGroupAdminEffectivePermissions($db, $conversationId, $userId), true);
}
