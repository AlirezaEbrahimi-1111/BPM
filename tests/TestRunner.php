<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  TestRunner.php — چارچوب تست سبک
 *  محل: /tests/TestRunner.php
 * ───────────────────────────────────────────────────────────────────
 *  چرا چارچوب اختصاصی و نه PHPUnit؟
 *    میزبانی اشتراکی است و نصب Composer در دسترس نیست. این چارچوب
 *    بدون هیچ وابستگی کار می‌کند و همان مفاهیم را پیاده می‌کند:
 *    گروه تست (suite)، مورد تست (test case)، و ادعا (assertion).
 *
 *  نحوهٔ نوشتن تست جدید:
 *    یک فایل در tests/unit/ یا tests/integration/ بسازید که یک
 *    آرایه return کند:
 *
 *      return [
 *          'name'  => 'نام گروه تست',
 *          'tests' => [
 *              'شرح این تست' => function (Assert $a) {
 *                  $a->equals(4, 2 + 2, 'جمع درست کار می‌کند');
 *              },
 *          ],
 *      ];
 * ═══════════════════════════════════════════════════════════════════
 */


/**
 * کلاس ادعاها — هر تست یک نمونه از این دریافت می‌کند
 */
class Assert
{
    public array $results = [];

    /** بررسی برابری دقیق */
    public function equals($expected, $actual, string $message = ''): void
    {
        $ok = ($expected === $actual);
        $this->record($ok, $message, $expected, $actual);
    }

    /** بررسی برابری با تبدیل نوع (برای مقایسهٔ عدد و رشته) */
    public function looseEquals($expected, $actual, string $message = ''): void
    {
        $ok = ($expected == $actual);
        $this->record($ok, $message, $expected, $actual);
    }

    /** باید true باشد */
    public function true($value, string $message = ''): void
    {
        $this->record($value === true, $message, 'true', $this->stringify($value));
    }

    /** باید false باشد */
    public function false($value, string $message = ''): void
    {
        $this->record($value === false, $message, 'false', $this->stringify($value));
    }

    /** باید در آرایه باشد */
    public function contains($needle, array $haystack, string $message = ''): void
    {
        $ok = in_array($needle, $haystack, true);
        $this->record($ok, $message, "شامل «{$needle}»", implode(', ', $haystack));
    }

    /** باید استثنا پرتاب شود */
    public function throws(callable $fn, string $message = ''): void
    {
        try {
            $fn();
            $this->record(false, $message, 'استثنا', 'بدون استثنا');
        } catch (Throwable $e) {
            $this->record(true, $message, 'استثنا', get_class($e));
        }
    }

    /** ثبت نتیجه */
    private function record(bool $ok, string $message, $expected, $actual): void
    {
        $this->results[] = [
            'ok'       => $ok,
            'message'  => $message ?: '(بدون شرح)',
            'expected' => $this->stringify($expected),
            'actual'   => $this->stringify($actual),
        ];
    }

    private function stringify($v): string
    {
        if (is_bool($v))  return $v ? 'true' : 'false';
        if (is_null($v))  return 'null';
        if (is_array($v)) return json_encode($v, JSON_UNESCAPED_UNICODE);
        return (string) $v;
    }
}


/**
 * اجراکنندهٔ تست‌ها
 */
class TestRunner
{
    private array $suites = [];
    private int $passed = 0;
    private int $failed = 0;

    /** بارگذاری یک مجموعه‌تست از یک آرایه (برای اسکریپت‌های تستِ مستقل/موقت) */
    public function loadFromArray(array $suite): void
    {
        if (!isset($suite['tests'])) return;

        $suite['file'] = $suite['file'] ?? ($suite['name'] ?? 'inline');
        $this->suites[] = $suite;
    }

    /** بارگذاری تمام فایل‌های تست از یک پوشه */
    public function loadDir(string $dir): void
    {
        if (!is_dir($dir)) return;

        foreach (glob($dir . '/*.php') as $file) {
            $suite = require $file;

            if (!is_array($suite) || !isset($suite['tests'])) {
                continue;   // فایل تست معتبر نیست
            }

            $suite['file']  = basename($file);
            $this->suites[] = $suite;
        }
    }

    /** اجرای همهٔ تست‌ها */
    public function run(): array
    {
        $report = [];

        foreach ($this->suites as $suite) {

            $suiteReport = [
                'name'   => $suite['name'] ?? $suite['file'],
                'file'   => $suite['file'],
                'cases'  => [],
                'passed' => 0,
                'failed' => 0,
            ];

            foreach ($suite['tests'] as $title => $testFn) {

                $assert = new Assert();
                $error  = null;

                try {
                    $testFn($assert);
                } catch (Throwable $e) {
                    $error = get_class($e) . ': ' . $e->getMessage();
                }

                // شمارش ادعاهای ناموفق
                $caseFailed = $error !== null;
                foreach ($assert->results as $r) {
                    if (!$r['ok']) $caseFailed = true;
                }

                $suiteReport['cases'][] = [
                    'title'      => $title,
                    'ok'         => !$caseFailed,
                    'error'      => $error,
                    'assertions' => $assert->results,
                ];

                if ($caseFailed) {
                    $suiteReport['failed']++;
                    $this->failed++;
                } else {
                    $suiteReport['passed']++;
                    $this->passed++;
                }
            }

            $report[] = $suiteReport;
        }

        return $report;
    }

    public function totalPassed(): int { return $this->passed; }
    public function totalFailed(): int { return $this->failed; }
    public function isGreen(): bool    { return $this->failed === 0; }
}
