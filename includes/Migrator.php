<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  Migrator — موتور مهاجرت پایگاه داده
 *  محل: /includes/Migrator.php
 * ───────────────────────────────────────────────────────────────────
 *  وظیفه:
 *    • نگه‌داری تاریخچهٔ تغییرات اسکیمای دیتابیس در جدول schema_migrations
 *    • اجرای مهاجرت‌های اجرانشده (up)
 *    • بازگرداندن آخرین دستهٔ اجراشده (down)
 *
 *  اصول طراحی:
 *    • هر مهاجرت فقط یک‌بار اجرا می‌شود
 *    • هر اجرا یک «دسته» (batch) دارد تا بتوان یکجا برگرداند
 *    • همه چیز در تراکنش انجام می‌شود (اگر پشتیبانی شود)
 *
 *  ⚠️ نکتهٔ مهم دربارهٔ MySQL:
 *    دستورات DDL (مثل ALTER TABLE) در MySQL «تراکنش‌پذیر» نیستند؛
 *    یعنی حتی اگر rollback کنیم، تغییر جدول برنمی‌گردد. برای همین
 *    هر مهاجرت باید تابع down() درست و آزموده داشته باشد.
 * ═══════════════════════════════════════════════════════════════════
 */

class Migrator
{
    private PDO $db;
    private string $path;      // مسیر پوشهٔ migrations
    private array $log = [];   // گزارش عملیات

    public function __construct(PDO $db, string $migrationsPath)
    {
        $this->db   = $db;
        $this->path = rtrim($migrationsPath, '/');
        $this->ensureTable();
    }

    /* ═══════════════════════════════════════════════════
       جدول تاریخچه
       ═══════════════════════════════════════════════════ */

    /** جدول schema_migrations را می‌سازد (اگر نباشد) */
    private function ensureTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS `schema_migrations` (
                `id`         INT AUTO_INCREMENT PRIMARY KEY,
                `migration`  VARCHAR(255) NOT NULL COMMENT 'نام فایل مهاجرت',
                `batch`      INT NOT NULL COMMENT 'شمارهٔ دستهٔ اجرا',
                `applied_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uk_migration` (`migration`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
              COMMENT='تاریخچهٔ مهاجرت‌های پایگاه داده'
        ");
    }

    /* ═══════════════════════════════════════════════════
       خواندن وضعیت
       ═══════════════════════════════════════════════════ */

    /** لیست فایل‌های موجود در پوشهٔ migrations (مرتب‌شده) */
    public function allFiles(): array
    {
        if (!is_dir($this->path)) return [];

        $files = glob($this->path . '/*.php');
        $files = array_filter($files, function ($f) {
            // فایل‌هایی که با _ شروع می‌شوند، مهاجرت نیستند (مثل _template.php)
            return substr(basename($f), 0, 1) !== '_';
        });

        $names = array_map('basename', $files);
        sort($names);   // ترتیب بر اساس نام = ترتیب زمانی
        return $names;
    }

    /** لیست مهاجرت‌هایی که قبلاً اجرا شده‌اند */
    public function appliedMigrations(): array
    {
        $stmt = $this->db->query(
            "SELECT migration FROM schema_migrations ORDER BY id ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /** لیست مهاجرت‌های اجرانشده */
    public function pendingMigrations(): array
    {
        return array_values(array_diff($this->allFiles(), $this->appliedMigrations()));
    }

    /** شمارهٔ آخرین دسته */
    private function lastBatch(): int
    {
        $n = $this->db->query("SELECT MAX(batch) FROM schema_migrations")->fetchColumn();
        return (int) ($n ?: 0);
    }

    /* ═══════════════════════════════════════════════════
       بارگذاری یک فایل مهاجرت
       ═══════════════════════════════════════════════════ */

    /**
     * فایل مهاجرت را می‌خواند و آرایه‌ای با کلیدهای up/down برمی‌گرداند.
     * هر فایل مهاجرت باید چنین آرایه‌ای return کند.
     */
    private function loadMigration(string $file): array
    {
        $full = $this->path . '/' . $file;

        if (!file_exists($full)) {
            throw new RuntimeException("فایل مهاجرت پیدا نشد: {$file}");
        }

        $migration = require $full;

        if (!is_array($migration) || !isset($migration['up'])) {
            throw new RuntimeException(
                "فایل مهاجرت «{$file}» باید آرایه‌ای با کلید up برگرداند"
            );
        }

        return $migration;
    }

    /* ═══════════════════════════════════════════════════
       اجرای مهاجرت‌ها (up)
       ═══════════════════════════════════════════════════ */

    /**
     * تمام مهاجرت‌های اجرانشده را به‌ترتیب اجرا می‌کند.
     *
     * @param bool $dryRun اگر true باشد، فقط نشان می‌دهد چه چیزی اجرا می‌شود
     */
    public function up(bool $dryRun = false): array
    {
        $pending = $this->pendingMigrations();

        if (empty($pending)) {
            $this->log[] = ['type' => 'info', 'msg' => 'هیچ مهاجرت اجرانشده‌ای وجود ندارد. دیتابیس به‌روز است.'];
            return $this->log;
        }

        $batch = $this->lastBatch() + 1;

        foreach ($pending as $file) {

            if ($dryRun) {
                $this->log[] = ['type' => 'dry', 'msg' => "اجرا می‌شد: {$file}"];
                continue;
            }

            try {
                $migration = $this->loadMigration($file);

                // اجرای تابع up
                ($migration['up'])($this->db);

                // ثبت در تاریخچه
                $stmt = $this->db->prepare(
                    "INSERT INTO schema_migrations (migration, batch) VALUES (?, ?)"
                );
                $stmt->execute([$file, $batch]);

                $this->log[] = ['type' => 'ok', 'msg' => "✅ اجرا شد: {$file}"];

            } catch (Throwable $e) {
                $this->log[] = [
                    'type' => 'error',
                    'msg'  => "❌ خطا در {$file}: " . $e->getMessage()
                ];
                // در صورت خطا، ادامه نمی‌دهیم — چون مهاجرت‌های بعدی
                // ممکن است به این یکی وابسته باشند
                break;
            }
        }

        return $this->log;
    }

    /* ═══════════════════════════════════════════════════
       بازگرداندن (down)
       ═══════════════════════════════════════════════════ */

    /**
     * آخرین دستهٔ اجراشده را برمی‌گرداند (به‌ترتیب معکوس).
     */
    public function down(bool $dryRun = false): array
    {
        $batch = $this->lastBatch();

        if ($batch === 0) {
            $this->log[] = ['type' => 'info', 'msg' => 'هیچ مهاجرتی برای بازگرداندن وجود ندارد.'];
            return $this->log;
        }

        $stmt = $this->db->prepare(
            "SELECT migration FROM schema_migrations WHERE batch = ? ORDER BY id DESC"
        );
        $stmt->execute([$batch]);
        $files = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($files as $file) {

            if ($dryRun) {
                $this->log[] = ['type' => 'dry', 'msg' => "برگردانده می‌شد: {$file}"];
                continue;
            }

            try {
                $migration = $this->loadMigration($file);

                if (!isset($migration['down'])) {
                    $this->log[] = [
                        'type' => 'error',
                        'msg'  => "⚠️ {$file} تابع down ندارد — قابل بازگشت نیست"
                    ];
                    continue;
                }

                ($migration['down'])($this->db);

                $del = $this->db->prepare("DELETE FROM schema_migrations WHERE migration = ?");
                $del->execute([$file]);

                $this->log[] = ['type' => 'ok', 'msg' => "↩️ برگردانده شد: {$file}"];

            } catch (Throwable $e) {
                $this->log[] = [
                    'type' => 'error',
                    'msg'  => "❌ خطا در بازگرداندن {$file}: " . $e->getMessage()
                ];
                break;
            }
        }

        return $this->log;
    }

    /* ═══════════════════════════════════════════════════
       وضعیت
       ═══════════════════════════════════════════════════ */

    /** گزارش وضعیت همهٔ مهاجرت‌ها */
    public function status(): array
    {
        $applied = $this->appliedMigrations();
        $all     = $this->allFiles();

        $rows = [];
        foreach ($all as $file) {
            $rows[] = [
                'migration' => $file,
                'applied'   => in_array($file, $applied, true),
            ];
        }
        return $rows;
    }
}
