<?php
/**
 * attendance_system/includes/request-supervisor-helper.php
 * ───────────────────────────────────────────────────────────────────
 * اختیارات «مسئول» (سرپرست سازمان) روی درخواست‌های حضور و غیاب — یک‌جا، تا لغو/حذف/
 * دیدن تاریخچه هم‌معنا بمانند (۱۴۰۵/۰۷/۳۰):
 *   reqIsOrgSupervisor  → بیننده سرپرست سازمانِ صاحب درخواست است؟ (یا مدیر کل سیستم)
 *   reqNotifyOwner      → اعلان + پیامک به صاحب درخواست (لغو/حذف توسط مسئول، با دلیل)
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';

if (!function_exists('reqTypeLabels')) {
    function reqTypeLabels(): array
    {
        return ['mission' => 'مأموریت', 'leave' => 'مرخصی', 'pass' => 'پاس', 'forget' => 'فراموشی', 'technical' => 'مشکل فنی'];
    }

    function reqTables(): array
    {
        return [
            'mission' => 'mission_requests', 'leave' => 'leave_requests', 'pass' => 'pass_requests',
            'forget' => 'forget_requests', 'technical' => 'technical_issues',
        ];
    }

    /**
     * $viewerId سرپرستِ سازمانِ $ownerId است؟ (role = supervisor در همان سازمان، یا سوپرادمین)
     * @return array|null  ردیف بیننده (id, role, organization_id, first_name, last_name) اگر بله، وگرنه null
     */
    function reqIsOrgSupervisor(PDO $db, int $viewerId, int $ownerId): ?array
    {
        $st = $db->prepare("SELECT id, role, organization_id, first_name, last_name FROM users WHERE id = ?");
        $st->execute([$viewerId]);
        $me = $st->fetch(PDO::FETCH_ASSOC);
        if (!$me) return null;
        if (isSuperAdmin($me)) return $me;
        if (($me['role'] ?? '') !== 'supervisor') return null;
        $st = $db->prepare("SELECT organization_id FROM users WHERE id = ?");
        $st->execute([$ownerId]);
        $ownerOrg = (int) $st->fetchColumn();
        return ($ownerOrg > 0 && $ownerOrg === (int) $me['organization_id']) ? $me : null;
    }

    /**
     * اعلان + پیامک به صاحب درخواست. $action = 'cancelled' | 'deleted'
     */
    function reqNotifyOwner(PDO $db, int $ownerId, string $action, string $type, string $code, string $byName, string $reason, ?int $requestId = null): void
    {
        try {
            require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/Notification.php';
            $label = reqTypeLabels()[$type] ?? 'درخواست';
            $verb = $action === 'cancelled' ? 'لغو' : 'حذف';
            $title = "درخواست {$label} شما {$verb} شد";
            $message = "درخواست {$label} شما (کد {$code}) توسط {$byName} {$verb} شد."
                . ($reason !== '' ? " دلیل: {$reason}" : '');
            (new Notification($db))->create([
                'to_user_id'      => $ownerId,
                'title'           => $title,
                'message'         => $message,
                'type'            => 'warning',
                'link'            => '/attendance_system/pages/requests.php',
                'related_type'    => $action === 'cancelled' ? $type : null,
                'related_id'      => $action === 'cancelled' ? $requestId : null,
                'skip_self_check' => true,
                'sms_pattern'     => 'general',           // {0}=عنوان {1}=پیام
                'sms_args'        => [$title, $message],
            ]);
        } catch (Throwable $e) {
            error_log("reqNotifyOwner failed | owner={$ownerId} action={$action} type={$type} | " . $e->getMessage());
        }
    }
}
