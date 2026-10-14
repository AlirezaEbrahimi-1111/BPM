<?php
/**
 * FinanceBalances — بازسازیِ منطقِ اکسلِ «بدهکاران/بستانکاران» (report.xlsm) در PHP.
 *
 * ورودی: سه مجموعه‌ی خام (که در مرورگر از فایل‌های شایگان استخراج و به سرور
 * فرستاده می‌شوند):
 *   $trial[]   = ['name'=>..,'debit'=>..,'credit'=>..]   (تراز کلیه حسابها)
 *   $detail[]  = ['name'=>..,'result'=>..]                (تفصیل شناور → سررسید)
 *   $overdue[] = ['name'=>..,'amount'=>..,'due'=>..]      (فاکتورهای معوق، ۳۰ روزه)
 *
 * خروجی: بدهکاران و بستانکاران به تفکیک «سررسید شده/نشده» + خلاصه + تطابق.
 *
 * نکتهٔ کلیدی: همهٔ تطبیق‌ها بر اساس «نام» است، پس نام‌ها باید دقیقاً مثل ماکروی
 * اکسل (CleanReportText) پاک‌سازی شوند وگرنه تطبیق بین سه فایل خراب می‌شود.
 */
class FinanceBalances
{
    /** پیشوندهای حسابداری که از ابتدای نام حذف می‌شوند (شکلِ فارسی‌شده). */
    private const PREFIXES = [
        "حساب های دریافتنی تجاری",
        "حساب های پرداختنی تجاری",
        "حساب های ارزش افزوده",
        "حساب های تسویه دار",
        "حساب های امانی",
    ];

    /** معادلِ VBA‌ِ CleanPersianText + RemoveAccountingPrefix. */
    public static function cleanName(string $s): string
    {
        // عربی → فارسی
        $s = strtr($s, [
            "\u{064A}" => "\u{06CC}", // ي → ی
            "\u{0649}" => "\u{06CC}", // ى (الف مقصوره) → ی
            "\u{0643}" => "\u{06A9}", // ك → ک
            "\u{0629}" => "\u{0647}", // ة → ه
            "\u{06C0}" => "\u{0647}", // ۀ → ه
        ]);

        // فاصله‌های خاص → فاصلهٔ معمولی
        $s = strtr($s, [
            "\u{00A0}" => " ", "\u{200C}" => " ", "\u{202F}" => " ",
            "\u{2000}" => " ", "\u{2001}" => " ", "\u{2002}" => " ", "\u{2003}" => " ",
            "\u{2004}" => " ", "\u{2005}" => " ", "\u{2006}" => " ", "\u{2007}" => " ",
            "\t" => " ", "\r" => " ", "\n" => " ",
        ]);

        // کاراکترهای نامرئی → حذف
        $s = strtr($s, [
            "\u{200B}" => "", "\u{2060}" => "", "\u{200D}" => "", "\u{FEFF}" => "",
        ]);

        // انواع خط تیره → "-"
        $s = strtr($s, [
            "\u{2010}" => "-", "\u{2011}" => "-", "\u{2012}" => "-",
            "\u{2013}" => "-", "\u{2014}" => "-", "\u{2212}" => "-",
        ]);

        // نقطه‌گذاری عربی
        $s = strtr($s, ["\u{060C}" => ",", "\u{061B}" => ";", "\u{061F}" => "?"]);

        // فاصله‌های تکراری → یکی، و trim
        $s = preg_replace('/ {2,}/u', ' ', $s);
        $s = trim($s);

        // حذف پیشوند حسابداری + جداکننده‌های بعدش
        $s = self::removeAccountingPrefix($s);

        $s = preg_replace('/ {2,}/u', ' ', $s);
        $s = trim($s);

        if (self::isOnlyDashes($s)) {
            $s = '';
        }
        return $s;
    }

    private static function removeAccountingPrefix(string $s): string
    {
        $temp = trim($s);
        foreach (self::PREFIXES as $prefix) {
            if (mb_strpos($temp, $prefix) === 0) {
                $temp = trim(mb_substr($temp, mb_strlen($prefix)));
                // حذف خط‌تیره/فاصله‌های ابتدایی بعد از پیشوند
                $temp = preg_replace('/^[-\s]+/u', '', $temp);
                $temp = trim($temp);
                break;
            }
        }
        if (self::isOnlyDashes($temp)) {
            $temp = '';
        }
        return $temp;
    }

    private static function isOnlyDashes(string $s): bool
    {
        $s = trim($s);
        if ($s === '') return true;
        return (bool) preg_match('/^[-\s]+$/u', $s);
    }

    // ───────────────── نگاشتِ ماتریسِ خام (از SheetJS) به ردیف‌های ساختارمند ──────
    // مرورگر فایل را به «آرایه‌ای از ردیف‌ها» تبدیل می‌کند؛ این‌جا از روی سرستون‌ها
    // ستونِ درست را پیدا می‌کنیم (مقاوم در برابر جابه‌جاییِ ستون‌ها).

    private static function hdr(string $s): string
    {
        $s = strtr($s, ["\u{064A}" => "\u{06CC}", "\u{0649}" => "\u{06CC}", "\u{0643}" => "\u{06A9}"]);
        $s = preg_replace('/\s+/u', ' ', $s);
        // حذف «:»/«،» و فاصله‌های ابتدا/انتها (یونی‌کد-امن) — سرستون‌های شایگان مثل «نام مشتری :»
        $s = preg_replace('/^[\s:：،]+|[\s:：،]+$/u', '', $s);
        return $s;
    }

    private static function findHeaderRow(array $m, array $keywords): int
    {
        $limit = min(count($m), 6);
        for ($r = 0; $r < $limit; $r++) {
            foreach ((array)$m[$r] as $cell) {
                $h = self::hdr((string)$cell);
                foreach ($keywords as $kw) if ($h !== '' && mb_strpos($h, $kw) !== false) return $r;
            }
        }
        return -1;
    }

    // اول تطبیقِ دقیقِ سرستون، بعد «شامل‌بودن» — تا «مبلغ» سراغِ «مبلغ پرداختی» و
    // «نام مشتری» سراغِ «کد مشتری» نرود.
    private static function colBy(array $headerRow, array $keywords): int
    {
        foreach ($keywords as $kw) {
            foreach ((array)$headerRow as $i => $cell) {
                if (self::hdr((string)$cell) === $kw) return (int)$i;
            }
        }
        foreach ($keywords as $kw) {
            foreach ((array)$headerRow as $i => $cell) {
                $h = self::hdr((string)$cell);
                if ($h !== '' && mb_strpos($h, $kw) !== false) return (int)$i;
            }
        }
        return -1;
    }

    private static function numify($v): float
    {
        if (is_numeric($v)) return (float)$v;
        $s = strtr((string)$v, ['،' => '', ',' => '', ' ' => '']);
        $s = strtr($s, ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9']);
        return is_numeric($s) ? (float)$s : 0.0;
    }

    /** ماتریس‌های خام → ['trial'=>[], 'detail'=>[], 'overdue'=>[]] */
    public static function mapMatrices(array $input): array
    {
        $trial = $detail = $overdue = [];

        $m = $input['trial'] ?? [];
        if ($m) {
            $hr = self::findHeaderRow($m, ['بدهکار']);
            if ($hr >= 0) {
                $cDebit  = self::colBy($m[$hr], ['بدهکار']);
                $cCredit = self::colBy($m[$hr], ['بستانکار', 'مانده']);
                $cName   = $cDebit > 0 ? $cDebit - 1 : -1;
                for ($r = $hr + 1; $r < count($m); $r++) {
                    $row = (array)$m[$r];
                    $name = $cName >= 0 ? (string)($row[$cName] ?? '') : '';
                    if (trim($name) === '') continue;
                    $trial[] = ['name' => $name, 'debit' => self::numify($row[$cDebit] ?? 0), 'credit' => self::numify($row[$cCredit] ?? 0)];
                }
            }
        }

        $m = $input['detail'] ?? [];
        if ($m) {
            $hr = self::findHeaderRow($m, ['نتیجه', 'تفصیلی']);
            if ($hr >= 0) {
                $cName = self::colBy($m[$hr], ['نام تفصیلی']);
                $cRes  = self::colBy($m[$hr], ['نتیجه']);
                for ($r = $hr + 1; $r < count($m); $r++) {
                    $row = (array)$m[$r];
                    $name = $cName >= 0 ? (string)($row[$cName] ?? '') : '';
                    if (trim($name) === '') continue;
                    $detail[] = ['name' => $name, 'result' => self::numify($row[$cRes] ?? 0)];
                }
            }
        }

        $m = $input['overdue'] ?? [];
        if ($m) {
            $hr = self::findHeaderRow($m, ['نام مشتری', 'سررسید']);
            if ($hr >= 0) {
                $cName = self::colBy($m[$hr], ['نام مشتری']);
                $cAmt  = self::colBy($m[$hr], ['مبلغ']);
                $cDue  = self::colBy($m[$hr], ['سررسید', 'تاریخ -سررسید']);
                for ($r = $hr + 1; $r < count($m); $r++) {
                    $row = (array)$m[$r];
                    $name = $cName >= 0 ? (string)($row[$cName] ?? '') : '';
                    if (trim($name) === '') continue;
                    $overdue[] = ['name' => $name, 'amount' => self::numify($row[$cAmt] ?? 0), 'due' => (string)($row[$cDue] ?? '')];
                }
            }
        }

        return ['trial' => $trial, 'detail' => $detail, 'overdue' => $overdue];
    }

    /** محاسبهٔ کامل. */
    public static function compute(array $trial, array $detail, array $overdue): array
    {
        // --- تراز: نام پاک‌شده → بدهکار/بستانکار ---
        $bal = []; // name => ['debit'=>, 'credit'=>]
        foreach ($trial as $row) {
            $n = self::cleanName((string)($row['name'] ?? ''));
            if ($n === '' || $n === 'جمع') continue;
            if (!isset($bal[$n])) $bal[$n] = ['debit' => 0.0, 'credit' => 0.0];
            $bal[$n]['debit']  += (float)($row['debit'] ?? 0);
            $bal[$n]['credit'] += (float)($row['credit'] ?? 0);
        }

        // --- تفصیل شناور: نام پاک‌شده → جمع نتیجه (= سررسید) ---
        $detailSum = []; // name => sum
        foreach ($detail as $row) {
            $n = self::cleanName((string)($row['name'] ?? ''));
            if ($n === '') continue;
            $detailSum[$n] = ($detailSum[$n] ?? 0.0) + (float)($row['result'] ?? 0);
        }

        $debtors = ['matured' => [], 'unmatured' => []];
        $creditors = ['matured' => [], 'unmatured' => []];
        $debtorTotal = 0.0;
        $creditorTotal = 0.0;

        foreach ($bal as $name => $v) {
            $mande = $v['debit'] - $v['credit'];
            if (abs($mande) < 0.0001) continue; // مانده صفر = نه بدهکار نه بستانکار
            $hasDetail = isset($detailSum[$name]);
            $sarresid  = $detailSum[$name] ?? 0.0;
            $excluded  = (mb_strpos($name, 'تست') !== false) || (mb_strpos($name, 'امانی') !== false);

            if ($mande > 0) { // بدهکار
                $debtorTotal += $mande;
                if ($hasDetail && !$excluded) {
                    $debtors['matured'][] = [
                        'name' => $name, 'mande' => $mande, 'sarresid' => $sarresid,
                        'actual' => min($mande, $sarresid), 'alarm' => ($sarresid > $mande),
                    ];
                } else {
                    $debtors['unmatured'][] = ['name' => $name, 'mande' => $mande];
                }
            } else { // بستانکار (منفی)
                $creditorTotal += $mande;
                if ($hasDetail && !$excluded) {
                    $creditors['matured'][] = [
                        'name' => $name, 'mande' => $mande, 'sarresid' => $sarresid,
                        'actual' => max($mande, $sarresid), 'alarm' => ($sarresid < $mande),
                    ];
                } else {
                    $creditors['unmatured'][] = ['name' => $name, 'mande' => $mande];
                }
            }
        }

        // مرتب‌سازی: بدهکاران نزولی بر اساس مانده، بستانکاران صعودی (منفی‌ترین اول)
        usort($debtors['matured'],   fn($a, $b) => $b['sarresid'] <=> $a['sarresid']);
        usort($debtors['unmatured'], fn($a, $b) => $b['mande'] <=> $a['mande']);
        usort($creditors['matured'],   fn($a, $b) => $a['mande'] <=> $b['mande']);
        usort($creditors['unmatured'], fn($a, $b) => $a['mande'] <=> $b['mande']);

        // سررسیدهای ۳۰ روزه (فاکتورهای معوق)
        $due30 = [];
        foreach ($overdue as $row) {
            $n = self::cleanName((string)($row['name'] ?? ''));
            if ($n === '') continue;
            $due30[] = ['name' => $n, 'amount' => (float)($row['amount'] ?? 0), 'due' => (string)($row['due'] ?? '')];
        }

        $reportTotal = $debtorTotal + $creditorTotal;

        return [
            'debtors' => $debtors,
            'creditors' => $creditors,
            'due30' => $due30,
            'summary' => [
                'debtor_total' => $debtorTotal,
                'creditor_total' => $creditorTotal,
                'report_total' => $reportTotal,
                'reconciliation' => $reportTotal - $reportTotal, // صفرِ ساختاری؛ در endpoint با جمعِ خام مقایسه می‌شود
                'debtor_count' => count($debtors['matured']) + count($debtors['unmatured']),
                'creditor_count' => count($creditors['matured']) + count($creditors['unmatured']),
            ],
        ];
    }
}
