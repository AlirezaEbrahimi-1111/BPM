<?php
// ==================================================
// includes/VoiceCall.php
// تماسِ صوتیِ هشدار (زرین‌کال) — الگویِ کاملاً موازیِ includes/sms.php
// ==================================================
class VoiceCall
{
    private $db;
    private $api_url = 'https://ws.zarincall.ir/apiv2/Message/Send';
    private $apikey = 'c39eea73-9a5f-4a18-88b3-a895ac89018b.d1933abb-7754-41b0-9518-72deaa7afb18'; // 🔴 پنلِ زرین‌کال

    // VoiceIdِ پیامِ هشدارِ «تیکتِ بحرانی» — از پنلِ زرین‌کال آپلود شده
    private $critical_ticket_voice_id = '21f2440a-1ab0-420a-97ea-6d5faad3e239';

    public function __construct($database)
    {
        $this->db = $database;
    }

    /**
     * تماسِ صوتیِ مستقیم با یک VoiceId مشخص، به یک یا چند شماره
     *
     * @param string[] $numbers شماره‌ها (هر فرمتی؛ خودش نرمال می‌شه)
     * @param string   $voiceId شناسه‌یِ فایلِ صوتیِ از‌قبل‌آپلودشده در زرین‌کال
     * @param string|null $context برایِ لاگ — مثلاً 'ticket:123'
     */
    public function call(array $numbers, string $voiceId, ?string $context = null): bool
    {
        $normalized = array_values(array_filter(array_map([$this, 'normalizePhone'], $numbers)));
        if (empty($normalized)) {
            $this->logCall([], $voiceId, $context, 'failed', null, 'شماره‌ی معتبری داده نشده');
            return false;
        }

        $data = [
            'VoiceId' => $voiceId,
            'Numbers' => $normalized,
        ];

        $ch = curl_init($this->api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: basic ' . $this->apikey,
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        $response  = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_err  = curl_error($ch);
        curl_close($ch);

        $response_data = json_decode((string) $response, true);
        $ok = ($http_code == 200 && !empty($response_data['R_Success']));

        $this->logCall(
            $normalized,
            $voiceId,
            $context,
            $ok ? 'sent' : 'failed',
            $response,
            $ok ? null : ($response_data['R_Message'] ?? $curl_err ?: 'خطای نامشخص')
        );

        return $ok;
    }

    /**
     * تماسِ هشدارِ «تیکتِ بحرانی ثبت شد» — مصرفِ اصلیِ این کلاس
     */
    public function callForCriticalTicket(array $numbers, ?int $ticketId = null): bool
    {
        return $this->call($numbers, $this->critical_ticket_voice_id, $ticketId ? 'ticket:' . $ticketId : 'ticket');
    }

    /**
     * دیسپچِ async (پروسه‌یِ پس‌زمینه) — عیناً الگویِ
     * Notification::sendSMSAsync، تا ثبتِ تیکت معطلِ جوابِ زرین‌کال نمونه
     */
    public static function dispatchCriticalTicketCallAsync(array $numbers, ?int $ticketId = null): void
    {
        try {
            $worker_path = $_SERVER['DOCUMENT_ROOT'] . '/includes/voicecall-worker.php';

            $disabled = array_map('trim', explode(',', ini_get('disable_functions')));
            $exec_available = !in_array('exec', $disabled, true) && file_exists($worker_path);

            if ($exec_available) {
                $payload = json_encode([
                    'numbers'   => $numbers,
                    'ticket_id' => $ticketId,
                ], JSON_UNESCAPED_UNICODE);
                $encoded = base64_encode($payload);

                $php_bin = PHP_BINARY ?: '/usr/bin/php';
                $cmd = sprintf(
                    '%s %s %s > /dev/null 2>&1 &',
                    escapeshellarg($php_bin),
                    escapeshellarg($worker_path),
                    escapeshellarg($encoded)
                );
                exec($cmd);
                error_log("📤 VoiceCall async dispatched for ticket #" . ($ticketId ?? '-'));
            } else {
                // ⚡ Fallback: تماسِ sync با timeoutِ کوتاه (curl خودش 8 ثانیه سقف داره)
                error_log("⚠️ exec not available for VoiceCall, falling back to sync");
                require_once __DIR__ . '/database.php';
                $database = new Database();
                $db = $database->getConnection();
                (new self($db))->callForCriticalTicket($numbers, $ticketId);
            }
        } catch (Exception $e) {
            error_log("❌ VoiceCall dispatch error: " . $e->getMessage());
        }
    }

    private function logCall(array $numbers, string $voiceId, ?string $context, string $status, $api_response, ?string $error_message): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO voice_call_logs
                (numbers, voice_id, context, status, api_response, error_message, sent_at)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                implode(',', $numbers),
                $voiceId,
                $context,
                $status,
                is_string($api_response) ? $api_response : json_encode($api_response, JSON_UNESCAPED_UNICODE),
                $error_message,
                $status === 'sent' ? date('Y-m-d H:i:s') : null,
            ]);
        } catch (Exception $e) {
            error_log("VoiceCall log error: " . $e->getMessage());
        }
    }

    /**
     * نرمال‌سازیِ شماره به فرمتِ محلی که زرین‌کال می‌خواد: 09121110000
     * (برخلافِ SMS::normalizePhone که فرمتِ 98... تولید می‌کنه — این API
     * طبقِ داکیومنتِ رسمی‌ش صریحاً فرمتِ صفرِ ابتدایی می‌خواد)
     */
    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (substr($phone, 0, 2) === '98') {
            $phone = '0' . substr($phone, 2);
        } elseif (substr($phone, 0, 1) !== '0') {
            $phone = '0' . $phone;
        }
        return $phone;
    }
}
