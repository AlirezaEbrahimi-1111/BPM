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
