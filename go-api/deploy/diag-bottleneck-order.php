<?php
/**
 * تشخیصِ یک‌باره: آیا MISMATCH در bottleneck-report واقعاً فقط ترتیبِ
 * instanceهایِ هم‌تأخیره یا یه چیزِ دیگه؟ فقط GET می‌زنه، امن است.
 *
 *   php go-api/deploy/diag-bottleneck-order.php <user_id>
 */

$userId = isset($argv[1]) ? (int) $argv[1] : 0;
if (!$userId) {
    fwrite(STDERR, "Usage: php diag-bottleneck-order.php <user_id>\n");
    exit(1);
}

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
require $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';

$db = (new Database())->getConnection();
$auth = new Auth($db);
$token = $auth->generateJWTToken($userId, 3600, 1);

function call($url, $token) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

$base = 'https://bpm.itmalek.com';
$p = call("$base/api/reports/bottleneck-report.php", $token);
$g = call("$base/go/api/reports/bottleneck-report", $token);

if (!$p || !$g) {
    echo "خطا در دریافتِ پاسخ (توکن/دسترسی را چک کن)\n";
    exit(1);
}

echo "تعداد گروه‌ها: PHP=" . count($p['bottlenecks'] ?? []) . " GO=" . count($g['bottlenecks'] ?? []) . "\n";
echo "summary یکسان؟ " . (json_encode($p['summary'] ?? null) === json_encode($g['summary'] ?? null) ? 'بله' : 'خیر') . "\n\n";

$allOk = true;
foreach (($p['bottlenecks'] ?? []) as $i => $pg) {
    $gg = $g['bottlenecks'][$i] ?? null;
    if (!$gg) { echo "گروه $i فقط در PHP هست!\n"; $allOk = false; continue; }

    $pm = $pg; $gm = $gg; unset($pm['instances'], $gm['instances']);
    $metaSame = json_encode($pm) === json_encode($gm);

    $pi = $pg['instances']; $gi = $gg['instances'];
    usort($pi, fn($a, $b) => $a['instance_id'] <=> $b['instance_id']);
    usort($gi, fn($a, $b) => $a['instance_id'] <=> $b['instance_id']);
    $setSame = json_encode($pi) === json_encode($gi);

    $label = "گروه $i ({$pg['step_name']})";
    if ($metaSame && $setSame) {
        echo "$label: OK — فقط ترتیب ممکنه فرق داشته باشه (بی‌خطر)\n";
    } else {
        $allOk = false;
        echo "$label: مشکل واقعی!\n";
        echo "  متادیتا یکسان: " . ($metaSame ? 'بله' : 'خیر') . "\n";
        echo "  مجموعه‌ی instanceها یکسان: " . ($setSame ? 'بله' : 'خیر') . "\n";
        if (!$metaSame) {
            echo "  PHP: " . json_encode($pm, JSON_UNESCAPED_UNICODE) . "\n";
            echo "  GO : " . json_encode($gm, JSON_UNESCAPED_UNICODE) . "\n";
        }
        if (!$setSame) {
            echo "  PHP IDs: " . implode(',', array_column($pi, 'instance_id')) . "\n";
            echo "  GO  IDs: " . implode(',', array_column($gi, 'instance_id')) . "\n";
        }
    }
}

echo "\n" . ($allOk ? "نتیجه: فقط ترتیب فرق دارد، امن است." : "نتیجه: مشکل واقعی پیدا شد — بررسیِ بیشتر لازم است.") . "\n";
