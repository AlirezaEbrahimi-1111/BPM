<?php
/**
 * API: api/admin/error-log.php
 * مشاهده‌ی error_logِ خودِ اپلیکیشنِ BPM (نه فروشگاه/WooCommerce) —
 * فقط برایِ سوپرادمین (user id = 1)، چون این فایل ممکنه اطلاعاتِ
 * فنیِ حساس (مسیرهای سرور، پیام‌های خطایِ داخلی) داشته باشه.
 *
 *   GET /api/admin/error-log.php?q=&from=&to=&limit=
 *   → {"success":true,"entries":[{timestamp,level,message}],"truncated":bool}
 *
 * مسیرِ فایل عمداً ثابت و هاردکدشده‌ست (نه از ورودیِ کاربر) — تا هیچ‌جور
 * path traversal ممکن نباشه.
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/middleware.php';

// همون الگویِ error log که Apache روی پروداکشن برایِ vhostِ bpm.itmalek.com
// می‌سازه. لوکال معمولاً این فایل رو نداره — پایین‌تر با یه پیامِ روشن
// handle می‌شه، نه کرش.
const BPM_ERROR_LOG_PATH = '/var/log/apache2/bpm-itmalek-ssl-error.log';

// اگه فایل از این بزرگ‌تر بود، فقط همین مقدار از انتهاش خونده می‌شه —
// برایِ جلوگیری از پرشدنِ حافظه با یه لاگِ خیلی بزرگ (بعد از ماه‌ها).
const MAX_READ_BYTES = 8 * 1024 * 1024; // 8MB

try {
    $user_id = requireAuth();
    if ((int) $user_id !== 1) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز — فقط سوپرادمین'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $q     = trim((string) ($_GET['q'] ?? ''));
    $from  = trim((string) ($_GET['from'] ?? '')); // YYYY-MM-DD
    $to    = trim((string) ($_GET['to'] ?? ''));
    $limit = min(max((int) ($_GET['limit'] ?? 200), 1), 500);

    if (!is_readable(BPM_ERROR_LOG_PATH)) {
        echo json_encode([
            'success'   => true,
            'entries'   => [],
            'truncated' => false,
            'message'   => 'فایلِ لاگ در این محیط در دسترس نیست (این endpoint فقط روی پروداکشن کار می‌کند).',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $fromTs = $from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? strtotime($from . ' 00:00:00') : null;
    $toTs   = $to   !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)   ? strtotime($to   . ' 23:59:59') : null;

    $result = readErrorLog(BPM_ERROR_LOG_PATH, $limit, $q, $fromTs, $toTs);

    echo json_encode([
        'success'   => true,
        'entries'   => $result['entries'],
        'truncated' => $result['truncated'],
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور'], JSON_UNESCAPED_UNICODE);
    error_log('error-log.php failed | ' . $e->getMessage());
}

/**
 * فایلِ لاگِ Apache رو می‌خونه، به رکورد تقسیم می‌کنه (هر رکورد با
 * «[Day Mon DD HH:MM:SS YYYY]» شروع می‌شه؛ خط‌هایی که این الگو رو ندارن
 * ادامه‌ی رکوردِ قبلی‌ان — مثلاً یک stack traceِ چندخطی)، فیلترِ
 * متن/بازه‌یِ تاریخ رو اعمال می‌کنه، و جدیدترین‌ها رو اول برمی‌گردونه.
 */
function readErrorLog(string $path, int $limit, string $q, ?int $fromTs, ?int $toTs): array
{
    $fileSize = filesize($path);
    $truncatedByCap = false;

    if ($fileSize > MAX_READ_BYTES) {
        $fp = fopen($path, 'rb');
        fseek($fp, $fileSize - MAX_READ_BYTES);
        $content = fread($fp, MAX_READ_BYTES);
        fclose($fp);
        // اولین خط احتمالاً بریده‌ست (وسطِ یک رکورد شروع کردیم) — دورش می‌ریزیم.
        $nl = strpos($content, "\n");
        $content = $nl !== false ? substr($content, $nl + 1) : '';
        $truncatedByCap = true;
    } else {
        $content = file_get_contents($path);
    }

    $lines = explode("\n", $content);
    $datePattern = '/^\[(\w+ \w+ +\d+ \d+:\d+:\d+ \d+)\]/';

    $records = [];
    $current = null;
    foreach ($lines as $ln) {
        if ($ln === '') continue;
        if (preg_match($datePattern, $ln, $m)) {
            if ($current !== null) $records[] = $current;
            $current = ['ts' => strtotime($m[1]), 'raw' => $ln];
        } elseif ($current !== null) {
            $current['raw'] .= "\n" . $ln;
        }
    }
    if ($current !== null) $records[] = $current;

    // جدیدترین اول
    $records = array_reverse($records);

    $entries = [];
    $matchedMoreThanLimit = false;
    foreach ($records as $r) {
        if ($fromTs !== null && $r['ts'] !== false && $r['ts'] < $fromTs) continue;
        if ($toTs   !== null && $r['ts'] !== false && $r['ts'] > $toTs)   continue;
        if ($q !== '' && mb_stripos($r['raw'], $q) === false) continue;

        if (count($entries) >= $limit) {
            $matchedMoreThanLimit = true;
            break;
        }

        $level = null;
        if (preg_match('/^\[[^\]]+\]\s*\[([^\]]+)\]/', $r['raw'], $lm)) {
            $level = $lm[1];
        }

        $entries[] = [
            'timestamp' => $r['ts'] !== false ? date('Y-m-d H:i:s', $r['ts']) : null,
            'level'     => $level,
            'message'   => $r['raw'],
        ];
    }

    return [
        'entries'   => $entries,
        'truncated' => $truncatedByCap || $matchedMoreThanLimit,
    ];
}
