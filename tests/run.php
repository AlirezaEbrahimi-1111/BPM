<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  run.php — اجراکنندهٔ تست‌های خودکار
 *  محل: /tests/run.php
 * ───────────────────────────────────────────────────────────────────
 *  اجرا از خط فرمان:
 *      php tests/run.php
 *      php tests/run.php unit          (فقط تست‌های واحد)
 *      php tests/run.php integration   (فقط تست‌های یکپارچه)
 *
 *  اجرا از مرورگر:
 *      https://bpm.computeryekta.com/tests/run.php?key=SECRET
 *
 *  کد خروجی (برای CI):
 *      0 = همهٔ تست‌ها موفق
 *      1 = حداقل یک تست ناموفق
 * ═══════════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/TestRunner.php';

$cfg   = require __DIR__ . '/config.php';
$isCli = (php_sapi_name() === 'cli');

// ── کنترل دسترسی برای اجرا از مرورگر ──────────────────
if (!$isCli) {
    if (($_GET['key'] ?? '') !== $cfg['run_secret']) {
        http_response_code(403);
        exit('دسترسی غیرمجاز.');
    }
    header('Content-Type: text/html; charset=utf-8');
}

// ── تعیین دامنهٔ اجرا ──────────────────────────────────
$scope = $isCli ? ($argv[1] ?? 'all') : ($_GET['scope'] ?? 'all');

$runner = new TestRunner();

if ($scope === 'all' || $scope === 'unit') {
    $runner->loadDir(__DIR__ . '/unit');
}
if ($scope === 'all' || $scope === 'integration') {
    $runner->loadDir(__DIR__ . '/integration');
}

$report = $runner->run();
$passed = $runner->totalPassed();
$failed = $runner->totalFailed();
$green  = $runner->isGreen();


// ═══════════════════════════════════════════════════════
//  خروجی خط فرمان
// ═══════════════════════════════════════════════════════
if ($isCli) {

    echo "\n";
    echo "════════════════════════════════════════════\n";
    echo "  اجرای تست‌های خودکار\n";
    echo "════════════════════════════════════════════\n\n";

    foreach ($report as $suite) {
        echo "▸ {$suite['name']}\n";

        foreach ($suite['cases'] as $case) {
            $mark = $case['ok'] ? '  ✓' : '  ✗';
            echo "{$mark} {$case['title']}\n";

            if (!$case['ok']) {
                if ($case['error']) {
                    echo "      خطا: {$case['error']}\n";
                }
                foreach ($case['assertions'] as $as) {
                    if (!$as['ok']) {
                        echo "      → {$as['message']}\n";
                        echo "        انتظار: {$as['expected']}  |  دریافت: {$as['actual']}\n";
                    }
                }
            }
        }
        echo "\n";
    }

    echo "────────────────────────────────────────────\n";
    echo $green
        ? "✅ همهٔ تست‌ها موفق — {$passed} مورد\n"
        : "❌ {$failed} تست ناموفق از " . ($passed + $failed) . " مورد\n";
    echo "────────────────────────────────────────────\n\n";

    exit($green ? 0 : 1);
}


// ═══════════════════════════════════════════════════════
//  خروجی مرورگر
// ═══════════════════════════════════════════════════════
?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>تست‌های خودکار</title>
    <style>
        body { background: #f8fafc; padding: 28px; color: #1f2937; }
        .wrap { max-width: 900px; margin: 0 auto; }

        .summary {
            border-radius: 12px; padding: 18px 22px; margin-bottom: 20px;
            font-size: 1.05rem; font-weight: bold;
        }
        .summary.green { background: rgba(27, 123, 57, 0.12); color: #1b7b39; border: 1px solid rgba(27, 123, 57, 0.3); }
        .summary.red   { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }

        .suite {
            background: #fff; border: 1px solid #e9e9e9;
            border-radius: 12px; margin-bottom: 16px; overflow: hidden;
        }
        .suite-head {
            background: #e9e9e9; border-bottom: 1px solid #e9e9e9;
            padding: 12px 18px; font-weight: bold; font-size: .95rem;
            display: flex; justify-content: space-between; align-items: center;
        }
        .badge {
            font-size: .78rem; padding: 3px 10px; border-radius: 999px; font-weight: 600;
        }
        .badge.ok   { background: rgba(27, 123, 57, 0.12); color: #1b7b39; }
        .badge.fail { background: #fee2e2; color: #b91c1c; }

        .case {
            padding: 10px 18px; border-bottom: 1px solid #e9e9e9;
            font-size: .88rem; display: flex; gap: 10px; align-items: flex-start;
        }
        .case:last-child { border-bottom: none; }
        .case .mark { flex-shrink: 0; font-weight: bold; }
        .case.ok   .mark { color: #1b7b39; }
        .case.fail .mark { color: #dc2626; }
        .case.fail { background: #fef2f2; }

        .detail {
            margin-top: 6px; font-size: .8rem; color: #6b7280;
            background: #fff; border: 1px solid #fecaca;
            border-radius: 6px; padding: 8px 10px;
        }
        .detail code { color: #b91c1c; font-family: monospace; direction: ltr; display: inline-block; }

        .nav { margin-bottom: 18px; display: flex; gap: 8px; }
        .nav a {
            text-decoration: none; padding: 6px 14px; border-radius: 8px;
            font-size: .82rem; border: 1px solid #e9e9e9; color: #374151; background: #fff;
        }
        .nav a:hover { background: #e9e9e9; }
    </style>
</head>
<body>
<div class="wrap">

    <div class="nav">
        <?php $k = urlencode($cfg['run_secret']); ?>
        <a href="?key=<?= $k ?>&scope=all">همهٔ تست‌ها</a>
        <a href="?key=<?= $k ?>&scope=unit">فقط تست واحد</a>
        <a href="?key=<?= $k ?>&scope=integration">فقط تست یکپارچه</a>
    </div>

    <div class="summary <?= $green ? 'green' : 'red' ?>">
        <?php if ($green): ?>
            ✅ همهٔ تست‌ها موفق — <?= $passed ?> مورد
        <?php else: ?>
            ❌ <?= $failed ?> تست ناموفق از <?= $passed + $failed ?> مورد
        <?php endif; ?>
    </div>

    <?php foreach ($report as $suite): ?>
        <div class="suite">
            <div class="suite-head">
                <span><?= htmlspecialchars($suite['name']) ?></span>
                <span class="badge <?= $suite['failed'] === 0 ? 'ok' : 'fail' ?>">
                    <?= $suite['passed'] ?> موفق<?= $suite['failed'] ? " / {$suite['failed']} ناموفق" : '' ?>
                </span>
            </div>

            <?php foreach ($suite['cases'] as $case): ?>
                <div class="case <?= $case['ok'] ? 'ok' : 'fail' ?>">
                    <span class="mark"><?= $case['ok'] ? '✓' : '✗' ?></span>
                    <div style="flex:1">
                        <?= htmlspecialchars($case['title']) ?>

                        <?php if (!$case['ok']): ?>
                            <div class="detail">
                                <?php if ($case['error']): ?>
                                    <div>خطا: <code><?= htmlspecialchars($case['error']) ?></code></div>
                                <?php endif; ?>
                                <?php foreach ($case['assertions'] as $as): ?>
                                    <?php if (!$as['ok']): ?>
                                        <div>
                                            → <?= htmlspecialchars($as['message']) ?><br>
                                            انتظار: <code><?= htmlspecialchars($as['expected']) ?></code>
                                            &nbsp;|&nbsp;
                                            دریافت: <code><?= htmlspecialchars($as['actual']) ?></code>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

    <?php if (empty($report)): ?>
        <div class="suite">
            <div class="case">هیچ فایل تستی پیدا نشد.</div>
        </div>
    <?php endif; ?>

</div>
</body>
</html>
