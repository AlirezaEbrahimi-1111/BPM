<?php

/**
 * کاربرانِ «نظاره‌گرِ تیکت» — دسترسیِ خواندنِ همهٔ تیکت‌ها را دارند، ولی هیچ
 * اعلان / پیامک / بجِ «توپ در زمینِ تو» برایشان نمی‌رود، مگر تیکت متعلق به
 * خودشان باشد (سازنده یا متصدی).
 *
 * تفاوتش با getSuperAdminIds(): آن لیست «پشتیبان‌ها»ست که واقعاً به تیکت‌ها
 * رسیدگی می‌کنند و باید خبردار شوند؛ این‌ها فقط تماشاچی‌اند.
 */
function ticketObserverIds(): array
{
    return [19]; // رضا فضایلی — فقط مشاهده
}

function isTicketObserver($userId): bool
{
    return in_array((int) $userId, ticketObserverIds(), true);
}

/**
 * آیا باید به این کاربر دربارهٔ این تیکت اعلان/پیامک داد؟
 * $ticket باید کلیدهای created_by و assigned_to را داشته باشد.
 */
function shouldNotifyTicketUser($notifyUserId, array $ticket): bool
{
    $uid = (int) $notifyUserId;
    if ($uid <= 0) {
        return false;
    }
    if (!isTicketObserver($uid)) {
        return true;
    }
    return $uid === (int) ($ticket['created_by'] ?? 0)
        || $uid === (int) ($ticket['assigned_to'] ?? 0);
}
