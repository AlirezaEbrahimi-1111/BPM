<?php
/**
 * توابع مشترک بررسی دسترسی گروه چت — یک‌جا، تا هرجای دیگه که بعدا
 * لازم شد (پین، افزودن/حذف عضو، تغییر عکس، حذف پیام دیگران، ...) از
 * همینا استفاده کنه، نه یک کپی دیگه از همون کوئری.
 *
 * دو سطح دسترسی:
 *   - «مدیر» (manager) = سازنده‌ی گروه یا role='admin' — برای کارهای
 *     روزمره‌ی مدیریتی (سنجاق، افزودن/حذف عضو عادی، تغییر عکس).
 *   - «سازنده» (creator) = فقط created_by — برای کارهای حساس‌تر که
 *     نباید حتی بین خود مدیرها دست‌به‌دست بشه (عزل یک مدیر دیگه، حذف
 *     گروه، حذف پیام هرکسی).
 */

function chatUserIsGroupCreator(PDO $db, int $conversationId, int $userId): bool
{
    // 🔒 type='group' اجباریه — وگرنه در گفتگوهای دوطرفه هم created_by
    // (یعنی هرکسی که شروع‌کننده‌ی چت بوده) اشتباها «سازنده» حساب می‌شد
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

/** نقش فعلی یک عضو ('admin'|'member')، یا null اگر اصلا عضو نیست */
function chatMemberRole(PDO $db, int $conversationId, int $userId): ?string
{
    $stmt = $db->prepare("SELECT role FROM chat_participants WHERE conversation_id = ? AND user_id = ?");
    $stmt->execute([$conversationId, $userId]);
    $role = $stmt->fetchColumn();
    return $role === false ? null : $role;
}

/**
 * اختیارات اختصاصی قابل‌واگذاری به هر مدیر گروه — سازنده می‌تونه برای
 * هر مدیر جداگانه انتخاب کنه کدوم‌ها رو داشته باشه (لزوما همه‌شون نه).
 */
const CHAT_GROUP_ADMIN_PERMISSIONS = ['pin', 'add_member', 'remove_member', 'avatar'];

/**
 * اختیارات مؤثر یک عضو (مدیر یا نه) — عمدا دیگه به role='admin' گره نخورده:
 * طبق درخواست صریح، یک عضو عادی هم می‌تونه یکی از این اختیارات رو داشته
 * باشه بدون اینکه «مدیر» باشه/بج مدیر بگیره؛ و برعکس، مدیرشدن به‌تنهایی
 * دیگه به‌معنی داشتن همه‌ی اختیارات نیست.
 *
 * NULL در ستون permissions یعنی «هنوز سفارشی نشده» — فقط برای مدیرهای
 * ازقبل‌موجود (قبل این تغییر) پیش‌فرض همه‌ی اختیاراته، تا رفتارشون عوض
 * نشه؛ برای عضو عادی NULL یعنی هیچ اختیاری (چون هیچ‌وقت پیش‌فرضی برای
 * عضو عادی وجود نداشته).
 *
 * @return string[] زیرمجموعه‌ای از CHAT_GROUP_ADMIN_PERMISSIONS
 */
function chatGroupAdminEffectivePermissions(PDO $db, int $conversationId, int $userId): array
{
    $stmt = $db->prepare("SELECT role, permissions FROM chat_participants WHERE conversation_id = ? AND user_id = ?");
    $stmt->execute([$conversationId, $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return [];
    }
    if ($row['permissions'] === null) {
        return $row['role'] === 'admin' ? CHAT_GROUP_ADMIN_PERMISSIONS : [];
    }
    $decoded = json_decode($row['permissions'], true);
    return is_array($decoded) ? array_values(array_intersect($decoded, CHAT_GROUP_ADMIN_PERMISSIONS)) : [];
}

/**
 * آیا این کاربر اجازه‌ی یک اقدام مشخص (مثلا 'pin') رو توی این گروه داره؟
 * سازنده همیشه true — صرف‌نظر از هر تنظیم اختصاصی. مدیر عادی فقط اگر
 * اون اختیار خاص براش فعال باشه (یا کلا سفارشی نشده باشه، یعنی پیش‌فرض
 * همه‌ی اختیارات). عضو عادی همیشه false.
 */
function chatUserHasGroupPermission(PDO $db, int $conversationId, int $userId, string $permission): bool
{
    if (chatUserIsGroupCreator($db, $conversationId, $userId)) {
        return true;
    }
    return in_array($permission, chatGroupAdminEffectivePermissions($db, $conversationId, $userId), true);
}
