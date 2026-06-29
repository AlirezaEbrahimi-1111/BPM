<?php

function getSmsPatterns(): array
{
    return [
        // ---- تسک ----
        'task_created'          => [464334, 2], // 🟢 {0}=عنوان {1}=نام
        'task_assigned'         => [464335, 2], // 🟢 {0}=عنوان {1}=نام
        'task_needs_approval'   => [464336, 1], // 🟢 {0}=عنوان
        'task_rejected'         => [464337, 2], // 🟢 {0}=عنوان {1}=نام
        'task_approved'         => [464561, 2], // 🟢 {0}=عنوان {1}=نام
        'task_reminder'         => [464564, 2], // 🟢 {0}=عنوان {1}=پیام

        // ---- تمدید موعد ----
        'deadline_request'      => [465555, 3], // 🟢 {0}=نام {1}=عنوان {2}=تاریخ
        'deadline_mid_approved' => [465556, 3], // 🟢 {0}=نام {1}=عنوان {2}=تاریخ
        'deadline_approved'     => [465558, 3], // 🟢 تأیید تمدید نهایی {0}=عنوان {1}=نام {2}=تاریخ
        'deadline_rejected'     => [465560, 2], // 🟢 {0}=عنوان {1}=نام

        // ---- اتمام کار ----
        'completion_request'    => [465561, 2], // 🟢 {0}=عنوان {1}=نام
        'completion_approved'   => [465562, 2], // 🟢 {0}=عنوان {1}=نام
        'completion_rejected'   => [465563, 3], // 🟢 {0}=عنوان {1}=نام {2}=دلیل
        'completion_by_manager' => [465564, 2], // 🟢 {0}=عنوان {1}=نام

        // ---- رفع دوره‌های معوقه ----
        'overdue_clear_request'  => [471258, 3], // 🟢 {0}=نام {1}=عنوان {2}=تعداد
        'overdue_clear_approved' => [471262, 2], // 🟢 {0}=عنوان {1}=تعداد
        'overdue_clear_rejected' => [471263, 1], // 🟢 {0}=عنوان

        // ---- روتین ----
        'routine_new_stage'     => [465566, 2], // 🟢 {0}=عنوان {1}=مرحله
        'routine_completed'     => [465567, 1], // 🟢 {0}=عنوان
        'routine_delayed'       => [465568, 1], // 🟢 {0}=عنوان

        // ---- تیکت ----
        'ticket_created'        => [465569, 2], // 🟢 {0}=شماره {1}=نام
        'ticket_replied'        => [465570, 2], // 🟢 {0}=نام {1}=عنوان
        'ticket_status_changed' => [465571, 2], // 🟢 {0}=عنوان {1}=وضعیت

        // ---- اتوماسیون ----
        'leave_to_deputy'       => [465572, 1], // 🟢 {0}=نام
        'mission_to_manager'    => [465573, 2], // 🟢 {0}=نوع {1}=نام
        'tech_issue_request'    => [465574, 1], // 🟢 {0}=نام
        'auto_mid_approved_req' => [465575, 2], // 🟢 {0}=نوع {1}=نقش
        'auto_mid_approved_next'=> [465576, 4], // 🟢 {0}=نوع {1}=نام {2}=نقش‌قبلی {3}=نقش‌شما
        'auto_final_approved'   => [465577, 1], // 🟢 {0}=نوع
        'auto_rejected'         => [465578, 2], // 🟢 {0}=نوع {1}=نقش

        // ---- دستگاه حضور و غیاب ----
        'device_request'        => [474376, 2], // 🟢 {0}=IP {1}=نام کاربر
        'device_approved'       => [474379, 1], // 🟢 {0}=IP
        'device_rejected'       => [474382, 1], // 🟢 {0}=IP

        // ---- عمومی ----
        'general'               => [466121, 2], // 🟢 {0}=عنوان {1}=پیام
    ];
}

/**
 * گرفتن bodyId از روی کلید الگو. اگر نبود، general برمی‌گرداند.
 */
function resolveBodyId(string $pattern_key): int
{
    $patterns = getSmsPatterns();
    if (isset($patterns[$pattern_key])) {
        return (int) $patterns[$pattern_key][0];
    }
    return (int) $patterns['general'][0];
}
