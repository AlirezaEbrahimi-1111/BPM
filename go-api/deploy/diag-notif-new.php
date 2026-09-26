<?php
/**
 * تشخیص یک‌باره: کدوم نوتیفیکیشن دقیقا باعث اختلاف new_count بین
 * PHP و Go شده؟ فقط GET می‌زنه، امن است.
 *
 *   php go-api/deploy/diag-notif-new.php <user_id>
 */

$userId = isset($argv[1]) ? (int) $argv[1] : 0;
if (!$userId) {
    fwrite(STDERR, "Usage: php diag-notif-new.php <user_id>\n");
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
$p = call("$base/api/notifications/new.php?since=0", $token);
$g = call("$base/go/api/notifications/new?since=0", $token);

$pIds = array_column($p['notifications'] ?? [], 'id');
$gIds = array_column($g['notifications'] ?? [], 'id');

echo "PHP new_count={$p['new_count']} تعداد واقعی آرایه=" . count($pIds) . "\n";
echo "GO  new_count={$g['new_count']} تعداد واقعی آرایه=" . count($gIds) . "\n\n";

$onlyInGo  = array_diff($gIds, $pIds);
$onlyInPhp = array_diff($pIds, $gIds);

echo "فقط در GO (نمایش داده می‌شه ولی PHP فیلترش کرده): " . implode(',', $onlyInGo) . "\n";
echo "فقط در PHP: " . implode(',', $onlyInPhp) . "\n\n";

foreach (array_merge($onlyInGo, $onlyInPhp) as $id) {
    // خود ردیف رو مستقیم از دیتابیس بخون تا کامل ببینیمش
    $stmt = $db->prepare("SELECT id, related_type, related_id, bypass_self_filter, message FROM notifications WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "── id=$id ──\n";
    echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    if ($row && $row['related_type'] === 'task' && $row['related_id']) {
        $t = $db->prepare("SELECT creator_id, assignee_id FROM tasks WHERE id = ?");
        $t->execute([$row['related_id']]);
        $task = $t->fetch(PDO::FETCH_ASSOC);
        echo "  task: " . json_encode($task, JSON_UNESCAPED_UNICODE) . " (userId=$userId)\n";
    }
    echo "\n";
}
