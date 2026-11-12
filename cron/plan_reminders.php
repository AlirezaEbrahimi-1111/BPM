<?php
/**
 * cron/plan_reminders.php
 * یادآوری پایان پلن / دورهٔ رایگان ۱۴ روزه به سرپرست‌های هر سازمان.
 *
 * زمان‌ها: ۷، ۳ و ۱ روز مانده به پایان + روزی که سازمان قفل می‌شود (۰).
 * کانال: Notification::create — اعلان داخل سیستم + پیامک + Push اپ (یک‌جا).
 * گیرنده: کاربران فعال با نقش supervisor/admin همان سازمان (isOrgWideRole)؛
 *          مدیران کل (پشتیبانی) نه.
 * تکراری نمی‌فرستد: جدول plan_reminder_log (کلید سازمان + تاریخ پایان + روز).
 * منبع تاریخ: includes/plan-access.php::planEndInfo — همان که قفل را تعیین می‌کند.
 *
 *   Crontab (روزی یک بار، ساعت ۱۰ صبح):
 *   0 10 * * * php /var/www/itmalek/cron/plan_reminders.php >> /var/www/itmalek/cron/logs/plan_reminders.log 2>&1
 */

if (php_sapi_name() !== 'cli') {
    die('This script can only be run from command line.');
}

date_default_timezone_set('Asia/Tehran');

if (empty($_SERVER['DOCUMENT_ROOT'])) {
    $_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__);
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/plan-access.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/Notification.php';

const PLAN_REMINDER_DAYS = [7, 3, 1, 0];

function plan_reminder_log(string $msg): void
{
    echo '[' . date('Y-m-d H:i:s') . "] $msg\n";
}

function plan_reminder_text(string $kind, int $days): array
{
    // آرایه، نه دو رشته — strtr رشته‌ای بایت‌به‌بایت است و رقم فارسی (۲ بایت) را خراب می‌کند
    $fa = fn($n) => strtr((string) $n, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
    $what = $kind === 'trial' ? 'دورهٔ رایگان ۱۴ روزهٔ سازمان شما' : 'اشتراک سازمان شما';
    $verb = $kind === 'trial' ? 'خریداری' : 'تمدید';
    if ($days === 0) {
        return [
            'title'   => $kind === 'trial' ? 'دورهٔ رایگان تمام شد' : 'اشتراک سازمان تمام شد',
            'message' => "$what امروز تمام شد و دسترسی کارکنان به سیستم متوقف شده است. با {$verb} پلن، همه‌چیز (کارها و اطلاعات) بدون تغییر برمی‌گردد.",
        ];
    }
    $when = $days === 1 ? 'فردا' : $fa($days) . ' روز دیگر';
    return [
        'title'   => $kind === 'trial' ? 'پایان نزدیک دورهٔ رایگان' : 'پایان نزدیک اشتراک',
        'message' => "$what $when تمام می‌شود. برای اینکه کار سازمان متوقف نشود، پلن سازمان را {$verb} کنید.",
    ];
}

try {
    $db = (new Database())->getConnection();
    $superIds = getSuperAdminIds();

    $orgs = $db->query("SELECT id, name FROM organizations")->fetchAll(PDO::FETCH_ASSOC);
    $logIns = $db->prepare("
        INSERT IGNORE INTO plan_reminder_log (organization_id, end_date, days_before, recipients)
        VALUES (?, ?, ?, 0)
    ");
    $logUpd = $db->prepare("UPDATE plan_reminder_log SET recipients = ? WHERE id = ?");
    $usersStmt = $db->prepare("
        SELECT id FROM users
        WHERE organization_id = ? AND is_active = 1 AND COALESCE(is_deleted, 0) = 0
          AND role IN ('supervisor', 'admin')
    ");
    $notif = new Notification($db);
    $sentOrgs = 0;

    foreach ($orgs as $org) {
        $orgId = (int) $org['id'];
        $end = planEndInfo($db, $orgId);
        if ($end['kind'] === null || !in_array($end['days_remaining'], PLAN_REMINDER_DAYS, true)) continue;

        // اول ردیف گزارش: اگر قبلا (امروز یا اجرای قبلی) فرستاده شده، INSERT IGNORE چیزی نمی‌سازد
        $logIns->execute([$orgId, $end['end_date'], $end['days_remaining']]);
        if ($logIns->rowCount() === 0) continue;
        $logId = (int) $db->lastInsertId();

        $usersStmt->execute([$orgId]);
        $userIds = array_values(array_filter(
            array_map('intval', $usersStmt->fetchAll(PDO::FETCH_COLUMN)),
            fn($u) => !in_array($u, $superIds, true)
        ));

        $text = plan_reminder_text($end['kind'], $end['days_remaining']);
        foreach ($userIds as $uid) {
            try {
                $notif->create([
                    'to_user_id'   => $uid,
                    'title'        => $text['title'],
                    'message'      => $text['message'],
                    'type'         => $end['days_remaining'] <= 1 ? 'warning' : 'info',
                    'related_type' => 'plan',
                    'related_id'   => $orgId,
                    'link'         => '/pages/plan-buy.php',
                ]);
            } catch (Throwable $e) {
                plan_reminder_log("خطای اعلان | org=$orgId user=$uid | " . $e->getMessage());
            }
        }
        $logUpd->execute([count($userIds), $logId]);
        $sentOrgs++;
        plan_reminder_log("org=$orgId ({$org['name']}) | {$end['kind']} | پایان {$end['end_date']} | {$end['days_remaining']} روز | گیرنده: " . count($userIds));
    }

    plan_reminder_log("پایان — سازمان‌های یادآوری‌شده: $sentOrgs");
} catch (Throwable $e) {
    plan_reminder_log('خطا: ' . $e->getMessage());
}
