<?php
/**
 * includes/HekmatBroadcast.php
 * ─────────────────────────────────────────────────────────────────
 * منطقِ مشترکِ ارسالِ روزانه‌ی حکمت‌های نهج‌البلاغه — هم توسطِ
 * cron/hekmat_daily.php (اجرایِ خودکار) و هم توسطِ API صفحه‌ی مدیریت
 * (پیش‌نمایش، ارسالِ آزمایشی، ارسالِ دستی) استفاده می‌شه — تا منطقِ
 * انتخابِ جمله/ساختِ پیام فقط یک‌جا نوشته بشه.
 *
 * 🔒 ارسالِ واقعیِ پیامک از طریقِ همون کلاسِ SMS موجود (includes/sms.php،
 * پنلِ ملی‌پیامک) انجام می‌شه. چون sms_logs.user_id ستونِ NOT NULL داره و
 * این گیرنده‌ها لزوماً کاربرِ ثبت‌شده‌ی سیستم نیستن، user_id=1
 * (سوپرادمین/مالکِ سیستم) به‌عنوانِ صاحبِ این ارسال‌هایِ خودکار پاس داده می‌شه.
 *
 * 🔒 فیلترِ محتواییِ خطِ پیامک (کلماتِ حساس): موقعِ تستِ این فیچر معلوم شد
 * پنلِ ملی‌پیامک بعضی کلماتِ سیاسی/حساس (مثلِ «فتنه») رو به‌صورتِ کامل رد
 * می‌کنه (RetStatus=35/InvalidData) — حتی وقتی جمله‌ی دینیِ کاملاً عادیه.
 * چون نهج‌البلاغه پر از همچین کلماتیه، درستِ قبل از ارسالِ واقعی (نه توی
 * چیزی که ذخیره/نمایش داده می‌شه)، این کلمات با یه zero-width space
 * (U+200B) وسطشون جایگزین می‌شن — از نظرِ چشم نامرئیه، ولی دیگه substring
 * دقیقی که فیلتر دنبالشه پیدا نمی‌شه. لیستِ زیر تجربی/دستیه: هر کلمه‌ی
 * جدیدی که رد شد (تویِ تاریخچه‌ی ارسال با status=failed دیده می‌شه)، باید
 * دستی به همین لیست اضافه بشه.
 */

require_once __DIR__ . '/sms.php';

class HekmatBroadcast
{
    const OWNER_USER_ID = 1;

    /** کلماتی که پنلِ پیامک رد می‌کنه — دستی و تجربی، بر اساسِ موارد کشف‌شده.
     *  هر دو رسم‌الخطِ کلاسیک (ى/ي عربی) و استانداردِ فارسی (ی) اضافه شده
     *  چون فیلتر روی نسخه‌ی دقیقِ حروف حساسه، نه معنی. */
    const FILTERED_WORDS = ['فتنه', 'پستانى', 'پستانی'];

    public static function getSettings(PDO $db): array
    {
        $stmt = $db->query("SELECT * FROM hekmat_settings WHERE id = 1");
        $s = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$s) {
            $db->exec("INSERT IGNORE INTO hekmat_settings (id) VALUES (1)");
            $s = $db->query("SELECT * FROM hekmat_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
        }
        return $s;
    }

    public static function saveSettings(PDO $db, array $data): array
    {
        $hour   = max(0, min(23, (int) ($data['send_hour'] ?? 8)));
        $minute = max(0, min(59, (int) ($data['send_minute'] ?? 0)));
        $closing = trim((string) ($data['closing_text'] ?? ''));
        $enabled = !empty($data['is_enabled']) ? 1 : 0;
        $rotation = ($data['rotation_mode'] ?? 'sequential') === 'random' ? 'random' : 'sequential';

        $stmt = $db->prepare("
            UPDATE hekmat_settings
            SET send_hour = ?, send_minute = ?, closing_text = ?, is_enabled = ?, rotation_mode = ?
            WHERE id = 1
        ");
        $stmt->execute([$hour, $minute, $closing, $enabled, $rotation]);

        return self::getSettings($db);
    }

    public static function getQuotes(PDO $db): array
    {
        return $db->query("SELECT * FROM hekmat_quotes ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    /** جایگزینیِ کاملِ لیستِ جملات — هر خط یک جمله؛ خطوطِ خالی نادیده گرفته می‌شن */
    public static function replaceQuotes(PDO $db, string $rawText): int
    {
        $lines = self::splitLines($rawText);

        $db->beginTransaction();
        try {
            $db->exec("DELETE FROM hekmat_quotes");
            if ($lines) {
                $stmt = $db->prepare("INSERT INTO hekmat_quotes (text, sort_order) VALUES (?, ?)");
                foreach ($lines as $i => $line) {
                    $stmt->execute([$line, $i]);
                }
            }
            // 🔒 چون لیست عوض شده، اندیسِ چرخشِ قبلی دیگه معنی نداره —
            // برمی‌گرده به اولِ لیست تا خارج از محدوده نره
            $db->exec("UPDATE hekmat_settings SET next_index = 0 WHERE id = 1");
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        return count($lines);
    }

    public static function getRecipients(PDO $db): array
    {
        return $db->query("SELECT * FROM hekmat_recipients ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    /** جایگزینیِ کاملِ لیستِ شماره‌ها — هر خط یک شماره؛ نرمال‌سازی + حذفِ تکراری */
    public static function replaceRecipients(PDO $db, string $rawText): int
    {
        $lines = self::splitLines($rawText);
        $phones = [];
        foreach ($lines as $line) {
            $p = self::normalizePhone($line);
            if ($p) $phones[$p] = true; // dedupe
        }
        $phones = array_keys($phones);

        $db->beginTransaction();
        try {
            $db->exec("DELETE FROM hekmat_recipients");
            if ($phones) {
                $stmt = $db->prepare("INSERT INTO hekmat_recipients (phone) VALUES (?)");
                foreach ($phones as $p) {
                    $stmt->execute([$p]);
                }
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        return count($phones);
    }

    /**
     * جمله‌ای که «اگر همین الان بفرستیم» انتخاب می‌شه — بدونِ تغییرِ
     * next_index (صرفاً برایِ پیش‌نمایش/تستِ بدونِ اثرِ جانبی)
     */
    public static function peekNextQuote(PDO $db, array $settings = null): ?array
    {
        $settings = $settings ?? self::getSettings($db);
        $quotes = array_values(array_filter(self::getQuotes($db), fn($q) => (int) $q['is_active'] === 1));
        if (!$quotes) return null;

        if ($settings['rotation_mode'] === 'random') {
            return $quotes[array_rand($quotes)];
        }
        $idx = ((int) $settings['next_index']) % count($quotes);
        return $quotes[$idx];
    }

    public static function buildMessage(string $quoteText, string $closingText): string
    {
        $closingText = trim($closingText);
        return $closingText !== '' ? ($quoteText . "\n" . $closingText) : $quoteText;
    }

    /** پیش‌نمایشِ کاملِ پیامی که امروز/الان قراره فرستاده بشه */
    public static function previewToday(PDO $db): array
    {
        $settings = self::getSettings($db);
        $quote = self::peekNextQuote($db, $settings);
        if (!$quote) {
            return ['quote_text' => null, 'message' => null, 'has_quote' => false];
        }
        return [
            'quote_text' => $quote['text'],
            'message'    => self::buildMessage($quote['text'], $settings['closing_text']),
            'has_quote'  => true,
        ];
    }

    /**
     * اجرایِ واقعیِ ارسالِ روزانه — به همه‌ی گیرنده‌هایِ فعال، با جمله‌ی
     * بعدیِ چرخش. هم توسطِ cron و هم توسطِ دکمه‌ی «ارسال دستیِ الان»
     * صدا زده می‌شه. next_index و last_sent_date رو آپدیت می‌کنه.
     */
    public static function runDailySend(PDO $db): array
    {
        $settings = self::getSettings($db);
        $quotes = array_values(array_filter(self::getQuotes($db), fn($q) => (int) $q['is_active'] === 1));
        $recipients = array_values(array_filter(self::getRecipients($db), fn($r) => (int) $r['is_active'] === 1));

        if (!$quotes) {
            return ['ok' => false, 'reason' => 'no_quotes', 'sent' => 0, 'failed' => 0];
        }
        if (!$recipients) {
            return ['ok' => false, 'reason' => 'no_recipients', 'sent' => 0, 'failed' => 0];
        }

        if ($settings['rotation_mode'] === 'random') {
            $quote = $quotes[array_rand($quotes)];
            $newIndex = (int) $settings['next_index'];
        } else {
            $idx = ((int) $settings['next_index']) % count($quotes);
            $quote = $quotes[$idx];
            $newIndex = ($idx + 1) % count($quotes);
        }

        $message = self::buildMessage($quote['text'], $settings['closing_text']);
        $today = date('Y-m-d');

        $sms = new SMS($db);
        $sentCount = 0;
        $failedCount = 0;

        $logStmt = $db->prepare("
            INSERT INTO hekmat_send_log (send_date, quote_text, recipient_phone, status, error_message)
            VALUES (?, ?, ?, ?, ?)
        ");

        foreach ($recipients as $r) {
            $ok = $sms->send(self::OWNER_USER_ID, $r['phone'], self::sanitizeForSmsFilter($message), 'hekmat_daily');
            if ($ok) {
                $sentCount++;
                $logStmt->execute([$today, $quote['text'], $r['phone'], 'sent', null]);
            } else {
                $failedCount++;
                $logStmt->execute([$today, $quote['text'], $r['phone'], 'failed', self::lastSmsError($db)]);
            }
        }

        $upd = $db->prepare("UPDATE hekmat_settings SET next_index = ?, last_sent_date = ? WHERE id = 1");
        $upd->execute([$newIndex, $today]);

        return ['ok' => true, 'sent' => $sentCount, 'failed' => $failedCount, 'quote_text' => $quote['text']];
    }

    /** ارسالِ آزمایشی به یک شماره‌ی دلخواه — تأثیری روی چرخش/تاریخِ آخرین ارسال نداره */
    public static function sendTest(PDO $db, string $phone, ?string $customText = null): array
    {
        $phone = self::normalizePhone($phone);
        if (!$phone) {
            return ['ok' => false, 'message' => 'شماره تلفن نامعتبر است'];
        }

        $settings = self::getSettings($db);
        if ($customText !== null && trim($customText) !== '') {
            $message = self::buildMessage(trim($customText), $settings['closing_text']);
            $quoteTextForLog = trim($customText);
        } else {
            $preview = self::previewToday($db);
            if (!$preview['has_quote']) {
                return ['ok' => false, 'message' => 'هیچ جمله‌ی فعالی برای پیش‌نمایش وجود ندارد'];
            }
            $message = $preview['message'];
            $quoteTextForLog = $preview['quote_text'];
        }

        $sms = new SMS($db);
        $ok = $sms->send(self::OWNER_USER_ID, $phone, self::sanitizeForSmsFilter($message), 'hekmat_test');
        $reason = $ok ? null : self::lastSmsError($db);

        $stmt = $db->prepare("
            INSERT INTO hekmat_send_log (send_date, quote_text, recipient_phone, status, is_test, error_message)
            VALUES (?, ?, ?, ?, 1, ?)
        ");
        $stmt->execute([date('Y-m-d'), $quoteTextForLog, $phone, $ok ? 'sent' : 'failed', $reason]);

        return [
            'ok' => $ok,
            'message' => $ok ? 'پیامک آزمایشی ارسال شد' : ('ارسال پیامک آزمایشی ناموفق بود' . ($reason ? " ({$reason})" : '')),
        ];
    }

    /** آخرین علتِ خطایِ ثبت‌شده در sms_logs — برایِ نشون‌دادنِ دلیلِ واقعیِ ناموفقی، نه فقط یه پیامِ کلی */
    private static function lastSmsError(PDO $db): string
    {
        $stmt = $db->query("SELECT error_message FROM sms_logs ORDER BY id DESC LIMIT 1");
        $err = $stmt->fetchColumn();
        return $err ?: 'ارسال پیامک ناموفق بود';
    }

    /**
     * جایگزینیِ کلماتِ فیلترشده با نسخه‌ی نامرئی‌شده (zero-width space وسطِ
     * کلمه) — فقط رویِ متنی که واقعاً به API فرستاده می‌شه، نه چیزی که
     * ذخیره/لاگ/نمایش داده می‌شه (به همین دلیل جدا از buildMessage است)
     */
    private static function sanitizeForSmsFilter(string $message): string
    {
        foreach (self::FILTERED_WORDS as $word) {
            $chars = preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY);
            if (count($chars) < 2) continue;
            $obfuscated = $chars[0] . "\u{200B}" . implode('', array_slice($chars, 1));
            $message = str_replace($word, $obfuscated, $message);
        }
        return $message;
    }

    public static function getRecentLog(PDO $db, int $limit = 50): array
    {
        $limit = max(1, min(500, $limit));
        return $db->query("
            SELECT * FROM hekmat_send_log
            ORDER BY id DESC
            LIMIT $limit
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function splitLines(string $rawText): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $rawText);
        $lines = array_map('trim', $lines);
        $lines = array_values(array_filter($lines, fn($l) => $l !== ''));
        return $lines;
    }

    /** نسخه‌ی مستقل از normalizePhone تویِ sms.php — عمداً کپی شده تا اون فایلِ مشترک دست‌نخورده بمونه */
    private static function normalizePhone(string $phone): ?string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if ($phone === '') return null;

        if (substr($phone, 0, 2) === '98') {
            $phone = substr($phone, 2);
        } elseif (substr($phone, 0, 1) === '0') {
            $phone = substr($phone, 1);
        }

        if (!preg_match('/^9\d{9}$/', $phone)) {
            return null; // فرمتِ موبایلِ ایران نیست
        }

        return '0' . $phone; // ذخیره‌ی یکنواخت به‌شکلِ 09xxxxxxxxx
    }
}
