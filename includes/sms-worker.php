<?php
date_default_timezone_set('Asia/Tehran');


if (!isset($argv[1])) { error_log("sms-worker: No payload"); exit(1); }
$payload = json_decode(base64_decode($argv[1]), true);
if (!$payload || empty($payload['to_user_id'])) { error_log("sms-worker: Invalid payload"); exit(1); }

try {
    if (empty($_SERVER['DOCUMENT_ROOT'])) {
        $_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__);
    }
    require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/sms.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/sms_patterns.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/error_config.php';
    $database = new Database();
    $db = $database->getConnection();
    if (!$db) { error_log("sms-worker: DB failed"); exit(1); }

    $sms = new SMS($db);

    // کلید الگو و متغیرها از payload (روش صریح)
    $pattern_key = $payload['sms_pattern'] ?? 'general';
    $args        = $payload['sms_args']    ?? [
        $payload['title'] ?? '',
        $payload['message'] ?? ''
    ];

    $bodyId = resolveBodyId($pattern_key);

    $result = $sms->sendPattern(
        $payload['to_user_id'],
        $bodyId,
        (array) $args,
        $payload['notification_id'] ?? null
    );

    error_log(($result ? "✅" : "⚠️") . " sms-worker: pattern={$pattern_key} bodyId={$bodyId} notif=#" . ($payload['notification_id'] ?? '-'));

} catch (Exception $e) {
    error_log("❌ sms-worker error: " . $e->getMessage());
    exit(1);
}
exit(0);
