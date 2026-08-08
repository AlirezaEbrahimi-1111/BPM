<?php
/**
 * API دستیارِ هوش‌مصنوعی: تنها نقطه‌ای که مرورگر می‌بیند
 * POST /api/ai-assistant/ask.php   body: { question: string, conversation_id?: int }
 *
 * طبقِ بندِ ۲.۱/۶/۱۰/۱۶ سندِ docs/ai-assistant/spec-v1.md:
 *  ۱) JWT را با همان Auth موجود اعتبارسنجی می‌کند (بدونِ اعتماد به ورودیِ کاربر
 *     برایِ user_id/organization_id — طبقِ بندِ ۱۳).
 *  ۲) گفتگو را resolve/create می‌کند (حافظه‌ی کوتاه‌مدت، بندِ ۱۰ — انقضا بعدِ ۹۰ دقیقه).
 *  ۳) با یک HMACِ سرور-به-سرور به ai-service وصل می‌شود (بندِ ۲.۱).
 *  ۴) هر پرسش را در ai_query_logs ثبت می‌کند (بندِ ۱۶) — چه موفق چه خطا.
 *
 * ⚠️ نسخه‌ی فعلی synchronous است (نه SSE). چون ai-service هنوز مستقر نشده،
 * پیاده‌سازیِ استریم غیرِقابلِ‌آزمایش بود؛ وقتی ai-service بالا آمد و اتصال
 * تأیید شد، این فایل به SSE (بندِ ۱۵) ارتقا پیدا می‌کند — منطقِ Auth/حافظه/لاگ
 * همان می‌ماند، فقط لایه‌ی ارسالِ پاسخ عوض می‌شود.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://bpm.computeryekta.com');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

const CONVERSATION_IDLE_MINUTES = 90;
const CONVERSATION_HISTORY_TURNS = 8;
const RATE_LIMIT_PER_MINUTE = 20;
// طبقِ تستِ واقعی با AvalAI: اولین درخواست (احتمالاً به‌خاطرِ گرم‌شدنِ
// اتصال) حدودِ ۹۰ ثانیه طول کشید، ولی درخواست‌هایِ بعدی حدودِ ۱۴ ثانیه —
// هنوز بالاترِ بودجه‌ی ایده‌آلِ بندِ ۱۵ (۱۰ ثانیه)، ولی نه نزدیکِ ۹۰. یک
// حاشیه‌ی امنِ معقول گذاشته شده؛ اگر Gateway کندتر شد باید بازبینی شود.
const AI_SERVICE_TIMEOUT_SECONDS = 30;

try {
    $user_id = requireAuth();

    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("SELECT organization_id, role, is_manager FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'عدم احراز هویت']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $question = trim((string) ($input['question'] ?? ''));
    $conversationId = (int) ($input['conversation_id'] ?? 0);

    if ($question === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'متنِ سؤال الزامی است']);
        exit;
    }

    // 🔒 محدودیتِ نرخ — طبقِ بندِ ۱۴ (حدودِ ۲۰ پرسش/دقیقه به‌ازایِ هر کاربر)
    $stmt = $db->prepare("SELECT COUNT(*) FROM ai_query_logs WHERE user_id = ? AND created_at > (NOW() - INTERVAL 1 MINUTE)");
    $stmt->execute([$user_id]);
    if ((int) $stmt->fetchColumn() >= RATE_LIMIT_PER_MINUTE) {
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => 'تعدادِ درخواست‌ها بیش از حدِ مجاز است — کمی صبر کنید']);
        exit;
    }

    // ─── Resolve/create conversation (حافظه‌ی کوتاه‌مدت، بندِ ۱۰) ───
    $conversation = null;
    if ($conversationId) {
        $stmt = $db->prepare("
            SELECT id, last_active_at FROM ai_conversations
            WHERE id = ? AND user_id = ? AND last_active_at > (NOW() - INTERVAL " . CONVERSATION_IDLE_MINUTES . " MINUTE)
        ");
        $stmt->execute([$conversationId, $user_id]);
        $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if (!$conversation) {
        $stmt = $db->prepare("INSERT INTO ai_conversations (user_id, organization_id) VALUES (?, ?)");
        $stmt->execute([$user_id, $user['organization_id']]);
        $conversationId = (int) $db->lastInsertId();
    } else {
        $conversationId = (int) $conversation['id'];
        $db->prepare("UPDATE ai_conversations SET last_active_at = NOW() WHERE id = ?")->execute([$conversationId]);
    }

    // ─── آخرین چند پیامِ همین گفتگو، برایِ حلِ ارجاعِ سؤالاتِ دنباله‌دار ───
    $stmt = $db->prepare("
        SELECT role, content FROM ai_conversation_messages
        WHERE conversation_id = ?
        ORDER BY id DESC LIMIT " . CONVERSATION_HISTORY_TURNS
    );
    $stmt->execute([$conversationId]);
    $history = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));

    $db->prepare("INSERT INTO ai_conversation_messages (conversation_id, role, content) VALUES (?, 'user', ?)")
        ->execute([$conversationId, $question]);

    $startedAt = microtime(true);

    // یک JWTِ کوتاه‌مدت برایِ ai-service تا با همان مسیرِ استانداردِ Authِ خودِ
    // BPM (requireAuth) به اندپوینت‌هایِ data/ برگردد — بدونِ نیاز به یک لایه‌ی
    // احرازِ موازی/جدید برایِ آن اندپوینت‌ها؛ کدِ آن‌ها دقیقاً همان می‌ماند که
    // در بندِ ۶ تست و تأیید شد. این توکن هرگز به مرورگر برنمی‌گردد.
    $auth = new Auth($db);
    $serviceToken = $auth->generateJWTToken($user_id, null, $user['organization_id']);

    $config = require $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
    $result = callAiService($config, [
        'user_id'         => $user_id,
        'organization_id' => (int) $user['organization_id'],
        'role'            => $user['role'],
        'is_manager'      => (bool) $user['is_manager'],
        'auth_token'      => $serviceToken,
        'question'        => $question,
        'history'         => $history,
    ]);

    $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

    $stmt = $db->prepare("
        INSERT INTO ai_query_logs (user_id, organization_id, conversation_id, question, sources_used, answer, status, latency_ms)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $user_id,
        $user['organization_id'],
        $conversationId,
        $question,
        $result['sources'] !== null ? json_encode($result['sources'], JSON_UNESCAPED_UNICODE) : null,
        $result['answer'],
        $result['status'],
        $latencyMs,
    ]);
    $logId = (int) $db->lastInsertId();

    if ($result['status'] === 'error') {
        http_response_code(502);
        echo json_encode([
            'success' => false,
            'message' => 'دستیار در حالِ حاضر در دسترس نیست — لطفاً دوباره تلاش کنید',
            'log_id'  => $logId,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $db->prepare("INSERT INTO ai_conversation_messages (conversation_id, role, content, sources_json) VALUES (?, 'assistant', ?, ?)")
        ->execute([
            $conversationId,
            $result['answer'],
            $result['sources'] !== null ? json_encode($result['sources'], JSON_UNESCAPED_UNICODE) : null,
        ]);

    echo json_encode([
        'success'         => true,
        'conversation_id' => $conversationId,
        'log_id'          => $logId,
        'status'          => $result['status'],
        'answer'          => $result['answer'],
        'sources'         => $result['sources'],
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("AI ask error: " . $e->getMessage());
}

/**
 * فراخوانیِ سرور-به-سرورِ ai-service با HMAC (بندِ ۲.۱) — هرگز از مرورگر قابلِ‌دسترسی نیست.
 * هویتِ کاربر (user_id/organization_id/role) همیشه از اینجا (سمتِ PHP) می‌آید، نه از بدنه‌یِ
 * ورودیِ کاربر — این دقیقاً کنترلِ «دورزدنِ سطحِ دسترسی»یِ بندِ ۱۴ است.
 */
function callAiService(array $config, array $payload): array
{
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $timestamp = (string) time();
    $signature = hash_hmac('sha256', $timestamp . '.' . $body, $config['ai_service_secret']);

    $ch = curl_init(rtrim($config['ai_service_url'], '/') . '/ask');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'X-Internal-Timestamp: ' . $timestamp,
            'X-Internal-Signature: ' . $signature,
        ],
        CURLOPT_TIMEOUT        => AI_SERVICE_TIMEOUT_SECONDS,
        CURLOPT_CONNECTTIMEOUT => 3,
    ]);
    $raw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($raw === false || $httpCode !== 200) {
        error_log("AI ask: ai-service unreachable | http_code={$httpCode} | curl_error={$curlError}");
        return ['status' => 'error', 'answer' => null, 'sources' => null];
    }

    $data = json_decode($raw, true);
    if (!is_array($data) || !isset($data['answer'])) {
        error_log("AI ask: ai-service returned unexpected payload: " . substr((string) $raw, 0, 500));
        return ['status' => 'error', 'answer' => null, 'sources' => null];
    }

    return [
        'status'  => $data['status'] ?? 'success',
        'answer'  => $data['answer'],
        'sources' => $data['sources'] ?? [],
    ];
}
