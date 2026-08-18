<?php
date_default_timezone_set('Asia/Tehran');

if (!isset($argv[1])) { error_log("voicecall-worker: No payload"); exit(1); }
$payload = json_decode(base64_decode($argv[1]), true);
if (!$payload || empty($payload['numbers'])) { error_log("voicecall-worker: Invalid payload"); exit(1); }

try {
    if (empty($_SERVER['DOCUMENT_ROOT'])) {
        $_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__);
    }
    require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/VoiceCall.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/error_config.php';
    $database = new Database();
    $db = $database->getConnection();
    if (!$db) { error_log("voicecall-worker: DB failed"); exit(1); }

    $voiceCall = new VoiceCall($db);
    $result = $voiceCall->callForCriticalTicket($payload['numbers'], $payload['ticket_id'] ?? null);

    error_log(($result ? "✅" : "⚠️") . " voicecall-worker: ticket=#" . ($payload['ticket_id'] ?? '-'));

} catch (Exception $e) {
    error_log("❌ voicecall-worker error: " . $e->getMessage());
    exit(1);
}
exit(0);
