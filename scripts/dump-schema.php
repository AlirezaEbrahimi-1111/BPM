<?php
/**
 * scripts/dump-schema.php — خروجی نرمال‌شده‌ی کل اسکیمای دیتابیس، برای
 * مقایسه‌ی لوکال ⇄ پروداکشن (رفع همون کلاس‌باگی که چند بار این پروژه رو
 * گزیده: یک GRANT یا ستون که فقط روی یکی از دو محیط اجرا شده).
 *
 * روی هر دو محیط اجرا کن، خروجی رو با diff مقایسه کن:
 *   php scripts/dump-schema.php > schema-local.txt      (این ماشین)
 *   php scripts/dump-schema.php > schema-prod.txt       (روی سرور، از راه SSH)
 *   diff schema-local.txt schema-prod.txt
 *
 * عمدا از mysqldump --no-data استفاده نمی‌کنیم — خروجی اون شامل
 * AUTO_INCREMENT فعلی/کامنت‌هایی می‌شه که بین دو محیط همیشه فرق می‌کنن و
 * دیف رو با نویز بی‌ربط پر می‌کنن. این‌جا فقط ستون‌ها، تایپ‌ها، ایندکس‌ها،
 * و GRANTهای کاربر دیتابیس رو می‌گیریم — دقیقا چیزی که واقعا اهمیت داره.
 */

require __DIR__ . '/../config/database.php';

$config = require __DIR__ . '/../config/config.php';

$database = new Database();
$db = $database->getConnection();

$dbName = $config['db_name'];
echo "# schema dump — db={$dbName} — " . date('Y-m-d H:i:s') . "\n\n";

// ── جدول‌ها + ستون‌ها ─────────────────────────────────────
$tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
sort($tables);

foreach ($tables as $table) {
    echo "## TABLE {$table}\n";
    $cols = $db->query("SHOW FULL COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        echo "  {$c['Field']}\t{$c['Type']}\t"
            . ($c['Null'] === 'YES' ? 'NULL' : 'NOT NULL') . "\t"
            . 'default=' . ($c['Default'] ?? 'NULL') . "\t"
            . ($c['Key'] ? "key={$c['Key']}" : '') . "\n";
    }

    $indexes = $db->query("SHOW INDEX FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
    $byName = [];
    foreach ($indexes as $idx) {
        $byName[$idx['Key_name']][] = $idx['Column_name'];
    }
    foreach ($byName as $name => $columns) {
        echo "  INDEX {$name} (" . implode(',', $columns) . ")\n";
    }
    echo "\n";
}

// ── GRANTهای کاربر دیتابیس همین اپ — دقیقا همون کلاس‌باگی که یک بار
// این پروژه رو گزید (GRANT مستندشده‌ی توی .sql ولی هیچ‌وقت روی پروداکشن
// اجرانشده) ──
echo "## GRANTS for {$config['db_user']}\n";
try {
    $grants = $db->query("SHOW GRANTS FOR CURRENT_USER()")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($grants as $g) {
        echo "  {$g}\n";
    }
} catch (Exception $e) {
    echo "  (نمی‌شه GRANTها رو خوند: " . $e->getMessage() . ")\n";
}
