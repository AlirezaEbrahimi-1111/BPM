<?php
/**
 * auto_renew_tasks.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Cron job: تمدید خودکار تسک‌های دوره‌ای
 *
 * زمان‌بندی پیشنهادی (crontab):
 *   0 1 * * * /usr/bin/php /var/www/html/cron/auto_renew_tasks.php >> /var/log/auto_renew.log 2>&1
 *
 * این اسکریپت هر شب ساعت ۱ صبح اجرا می‌شود و تسک‌هایی که:
 *   - auto_renew = 1 دارند
 *   - task_type = 'continuous' هستند
 *   - end_date آن‌ها امروز یا قبل از امروز است
 * را تمدید می‌کند.
 * ─────────────────────────────────────────────────────────────────────────────
 */

// اجرا فقط از CLI
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Access denied');
}

define('ROOT', dirname(__DIR__));
require_once ROOT . '/config/database.php';

// ─── اتصال به دیتابیس ────────────────────────────────────────────────────────
$database = new Database();
$db = $database->getConnection();

if (!$db) {
    logMessage("❌ خطا در اتصال به دیتابیس");
    exit(1);
}

$today = date('Y-m-d');
logMessage("🚀 شروع cron تمدید خودکار — تاریخ: {$today}");

// ─── پیدا کردن تسک‌های نیازمند تمدید ────────────────────────────────────────
$stmt = $db->prepare("
    SELECT
        id,
        title,
        description,
        task_type,
        priority,
        assignee_id,
        created_by,
        organization_id,
        start_date,
        end_date,
        period_type,
        auto_renew,
        renew_duration,
        renew_duration_unit,
        renew_behavior,
        renew_count
    FROM tasks
    WHERE auto_renew = 1
      AND task_type = 'continuous'
      AND end_date IS NOT NULL
      AND end_date <= :today
      AND status NOT IN ('cancelled')
");
$stmt->execute([':today' => $today]);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

logMessage("📋 تعداد تسک‌های یافت‌شده: " . count($tasks));

$renewed  = 0;
$failed   = 0;

foreach ($tasks as $task) {
    try {
        $newEndDate = calculateNewEndDate(
            $task['end_date'],
            (int)$task['renew_duration'],
            $task['renew_duration_unit']
        );

        if ($task['renew_behavior'] === 'extend') {
            // ── حالت extend: همان تسک آپدیت می‌شود ─────────────────────────
            $upd = $db->prepare("
                UPDATE tasks
                SET end_date        = :new_end_date,
                    start_date      = :new_start_date,
                    renew_count     = renew_count + 1,
                    last_renewed_at = NOW(),
                    status          = 'pending'
                WHERE id = :id
            ");
            $upd->execute([
                ':new_end_date'   => $newEndDate,
                ':new_start_date' => $task['end_date'], // شروع جدید = پایان قبلی
                ':id'             => $task['id'],
            ]);

            logMessage("✅ extend — تسک #{$task['id']} «{$task['title']}» تا {$newEndDate} تمدید شد");

        } else {
            // ── حالت new_task: تسک جدید ساخته می‌شود ───────────────────────
            $ins = $db->prepare("
                INSERT INTO tasks
                    (title, description, task_type, priority,
                     assignee_id, created_by, organization_id,
                     start_date, end_date, period_type,
                     auto_renew, renew_duration, renew_duration_unit,
                     renew_behavior, parent_task_id,
                     renew_count, status, created_at)
                VALUES
                    (:title, :description, :task_type, :priority,
                     :assignee_id, :created_by, :organization_id,
                     :start_date, :end_date, :period_type,
                     :auto_renew, :renew_duration, :renew_duration_unit,
                     :renew_behavior, :parent_task_id,
                     0, 'pending', NOW())
            ");
            $ins->execute([
                ':title'               => $task['title'],
                ':description'         => $task['description'],
                ':task_type'           => $task['task_type'],
                ':priority'            => $task['priority'],
                ':assignee_id'         => $task['assignee_id'],
                ':created_by'          => $task['created_by'],
                ':organization_id'     => $task['organization_id'],
                ':start_date'          => $task['end_date'],   // شروع جدید = پایان قبلی
                ':end_date'            => $newEndDate,
                ':period_type'         => $task['period_type'],
                ':auto_renew'          => 1,
                ':renew_duration'      => $task['renew_duration'],
                ':renew_duration_unit' => $task['renew_duration_unit'],
                ':renew_behavior'      => $task['renew_behavior'],
                ':parent_task_id'      => $task['id'],
            ]);

            $newTaskId = (int)$db->lastInsertId();

            // بستن تسک قدیمی
            $db->prepare("
                UPDATE tasks
                SET auto_renew      = 0,
                    renew_count     = renew_count + 1,
                    last_renewed_at = NOW(),
                    status          = 'completed'
                WHERE id = :id
            ")->execute([':id' => $task['id']]);

            logMessage("✅ new_task — تسک #{$task['id']} بسته شد؛ تسک جدید #{$newTaskId} ساخته شد (تا {$newEndDate})");
        }

        // ── ارسال نوتیفیکیشن به مسئول ────────────────────────────────────────
        sendRenewalNotification($db, $task, $newEndDate);

        $renewed++;

    } catch (Exception $e) {
        logMessage("❌ خطا در تمدید تسک #{$task['id']}: " . $e->getMessage());
        $failed++;
    }
}

logMessage("─────────────────────────────────────────");
logMessage("✔ تمدید موفق: {$renewed}  |  ✖ خطا: {$failed}");
logMessage("🏁 پایان cron\n");

exit(0);

// ─── توابع کمکی ──────────────────────────────────────────────────────────────

/**
 * محاسبه تاریخ پایان جدید بر اساس مدت تمدید
 */
function calculateNewEndDate(string $currentEndDate, int $duration, string $unit): string
{
    $dt = new DateTime($currentEndDate);

    switch ($unit) {
        case 'day':
            $dt->modify("+{$duration} days");
            break;
        case 'week':
            $dt->modify("+{$duration} weeks");
            break;
        case 'month':
            $dt->modify("+{$duration} months");
            break;
        default:
            throw new InvalidArgumentException("واحد نامعتبر: {$unit}");
    }

    return $dt->format('Y-m-d');
}

/**
 * ارسال نوتیفیکیشن برای مسئول تسک
 */
function sendRenewalNotification(PDO $db, array $task, string $newEndDate): void
{
    try {
        require_once ROOT . '/includes/Notification.php';

        $unitLabel = ['day' => 'روز', 'week' => 'هفته', 'month' => 'ماه'];
        $unit      = $unitLabel[$task['renew_duration_unit']] ?? $task['renew_duration_unit'];

        $notif = new Notification($db);
        $notif->create([
            'to_user_id'   => $task['assignee_id'],
            'title'        => 'تمدید خودکار کار',
            'message'      => "کار «{$task['title']}» به مدت {$task['renew_duration']} {$unit} تمدید شد (تا تاریخ {$newEndDate})",
            'type'         => 'info',
            'link'         => "/pages/task-detail.php?id={$task['id']}",
            'related_type' => 'task',
            'related_id'   => $task['id'],
        ]);
    } catch (Exception $e) {
        logMessage("⚠ خطا در ارسال نوتیفیکیشن برای تسک #{$task['id']}: " . $e->getMessage());
    }
}

/**
 * لاگ با timestamp
 */
function logMessage(string $msg): void
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
}
