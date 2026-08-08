<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';

// این ابزار کدِ کامل و حتی فایل‌هایِ .env رو قابلِ‌جستجو می‌کنه — قبلاً بدونِ
// هیچ احرازِ هویتی برایِ عموم در دسترس بود؛ الان محدود به سوپرادمین شد
$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$__user_id = $_SESSION['user_id'] ?? $auth->getUserFromToken();
$__me = $__user_id ? loadUserForPermissions($db, (int) $__user_id) : null;
if (!isSuperAdmin($__me)) {
    http_response_code(403);
    die('<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="UTF-8"><title>خطای دسترسی</title><style>body{text-align:center;padding:50px;background:#f1f1f1;color:#333;font-family:sans-serif}</style></head><body><h1>دسترسی غیرمجاز</h1></body></html>');
}

// --- CONFIGURATION ---
// مسیر جستجو (به صورت خودکار ریشه وبسایت + پوشه bpm را تشخیص می‌دهد)
$searchDirectory = $_SERVER['DOCUMENT_ROOT'] . '/bpm';
// پسوندهای مجاز برای جستجو
$allowedExtensions = ['php', 'html', 'htm', 'js', 'css', 'txt', 'json', 'xml', 'sql', 'md', 'env'];
// پوشه‌هایی که باید از جستجو مستثنی شوند
$excludeDirs = ['vendor', 'node_modules', 'cache', '.git', 'storage', 'public'];

// --- SCRIPT LOGIC ---
// ورودی‌های کاربر را دریافت و پاکسازی کن
$searchTerm = isset($_GET['q']) ? trim($_GET['q']) : '';
$isWholeWord = isset($_GET['w']);
$isCaseSensitive = isset($_GET['cs']);

// متغیرهای نتایج را مقداردهی اولیه کن
$results = [];
$totalHits = 0;
$error = null;
$startTime = microtime(true);

if (!empty($searchTerm)) {
    if (!is_dir($searchDirectory)) {
        $error = "خطا: پوشه جستجو یافت نشد: " . htmlspecialchars($searchDirectory);
    } else {
        $directory = new RecursiveDirectoryIterator($searchDirectory, FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS);
        $iterator = new RecursiveIteratorIterator($directory);

        // ساخت پترن جستجو بر اساس گزینه‌های کاربر
        $pattern = preg_quote($searchTerm, '/');
        if ($isWholeWord) {
            $pattern = '\b' . $pattern . '\b';
        }
        $regexFlags = $isCaseSensitive ? '' : 'i';
        $fullPattern = '/' . $pattern . '/' . $regexFlags;

        foreach ($iterator as $file) {
            $filePath = $file->getPathname();

            // بررسی پوشه‌های مستثنی
            foreach ($excludeDirs as $dir) {
                if (strpos($filePath, '/' . $dir . '/') !== false) {
                    continue 2; // برو به فایل بعدی در حلقه اصلی
                }
            }
            
            // بررسی پسوند فایل
            if (in_array(strtolower($file->getExtension()), $allowedExtensions)) {
                $lines = @file($filePath);
                if ($lines === false) continue;

                $fileMatches = [];
                foreach ($lines as $lineNum => $lineContent) {
                    if (preg_match($fullPattern, $lineContent)) {
                        $totalHits++;
                        $fileMatches[] = [
                            'line_num' => $lineNum + 1,
                            'prev'     => isset($lines[$lineNum - 1]) ? trim($lines[$lineNum - 1]) : null,
                            'current'  => trim($lineContent),
                            'next'     => isset($lines[$lineNum + 1]) ? trim($lines[$lineNum + 1]) : null,
                        ];
                    }
                }

                if (!empty($fileMatches)) {
                    $relativePath = str_replace($_SERVER['DOCUMENT_ROOT'], '', $filePath);
                    $results[$relativePath] = $fileMatches;
                }
            }
        }
    }
}
$duration = microtime(true) - $startTime;
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ابزار جستجوی کد</title>
    <style>
        :root {
            --bg-color: #f8f9fa;
            --font-color: #212529;
            --container-bg: #ffffff;
            --border-color: #dee2e6;
            --header-bg: #343a40;
            --header-color: #ffffff;
            --primary-color: #007bff;
            --highlight-bg: #fff3cd;
            --line-num-color: #6c757d;
        }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background-color: var(--bg-color); color: var(--font-color); margin: 0; padding: 1.5rem; font-size: 14px; }
        .container { max-width: 1000px; margin: 0 auto; background-color: var(--container-bg); border: 1px solid var(--border-color); border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .search-header { padding: 1.5rem; border-bottom: 1px solid var(--border-color); }
        .search-header h1 { margin: 0 0 1rem 0; font-size: 1.5rem; }
        form { display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; }
        .search-input { flex-grow: 1; display: flex; }
        .search-input input[type="text"] { flex-grow: 1; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 4px 0 0 4px; font-size: 1rem; }
        .search-input button { padding: 0.75rem 1.5rem; border: 1px solid var(--primary-color); background-color: var(--primary-color); color: white; cursor: pointer; border-radius: 0 4px 4px 0; font-size: 1rem; }
        .search-options { display: flex; gap: 1rem; user-select: none; }
        .search-options label { cursor: pointer; }
        .results-body { padding: 1.5rem; }
        .stats { background-color: var(--bg-color); border: 1px solid var(--border-color); padding: 1rem; margin-bottom: 1.5rem; border-radius: 4px; font-size: 0.9rem; }
        .file-group { border: 1px solid var(--border-color); border-radius: 4px; margin-bottom: 1.5rem; }
        .file-header { background-color: var(--header-bg); color: var(--header-color); padding: 0.75rem 1rem; font-family: "SF Mono", "Menlo", "Consolas", monospace; direction: ltr; font-size: 0.9rem; border-bottom: 1px solid var(--border-color); }
        .file-header strong { font-weight: 600; }
        .match-block { padding: 0.75rem; border-bottom: 1px solid #e9ecef; }
        .match-block:last-child { border-bottom: none; }
        .code-line { display: flex; font-family: "SF Mono", "Menlo", "Consolas", monospace; font-size: 0.85rem; direction: ltr; white-space: pre-wrap; word-break: break-all; }
        .line-num { min-width: 45px; text-align: right; color: var(--line-num-color); padding-right: 1rem; user-select: none; }
        .line-content { flex-grow: 1; }
        .line-content.context { color: var(--line-num-color); }
        .line-content.match { background-color: var(--highlight-bg); font-weight: 600; }
        .highlight { background-color: #ffd24a; border-radius: 2px; }
        .alert { padding: 1rem; margin-bottom: 1.5rem; border-radius: 4px; }
        .alert-danger { background-color: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .alert-info { background-color: #cce5ff; border: 1px solid #b8daff; color: #004085; }
    </style>
</head>
<body>
    <div class="container">
        <header class="search-header">
            <h1>ابزار جستجوی وابستگی‌ها</h1>
            <form method="GET" action="">
                <div class="search-input">
                    <input type="text" name="q" value="<?= htmlspecialchars($searchTerm) ?>" placeholder="کلمه، تابع، متغیر..." required>
                    <button type="submit">جستجو</button>
                </div>
                <div class="search-options">
                    <label><input type="checkbox" name="w" value="1" <?= $isWholeWord ? 'checked' : '' ?>> تطابق کلمه کامل</label>
                    <label><input type="checkbox" name="cs" value="1" <?= $isCaseSensitive ? 'checked' : '' ?>> حساس به بزرگی/کوچکی حروف</label>
                </div>
            </form>
        </header>

        <main class="results-body">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php elseif (!empty($searchTerm)): ?>
                <div class="stats">
                    <strong><?= count($results) ?></strong> فایل و <strong><?= $totalHits ?></strong> نتیجه در مدت <strong><?= round($duration, 3) ?></strong> ثانیه یافت شد.
                </div>
                
                <?php if (empty($results)): ?>
                    <div class="alert alert-info">هیچ نتیجه‌ای برای "<?= htmlspecialchars($searchTerm) ?>" یافت نشد.</div>
                <?php endif; ?>

                <?php foreach ($results as $path => $matches): ?>
                    <div class="file-group">
                        <div class="file-header">
                            📄 <strong><?= htmlspecialchars($path) ?></strong> (<?= count($matches) ?> مورد)
                        </div>
                        <?php foreach ($matches as $match): ?>
                            <div class="match-block">
                                <?php if ($match['prev'] !== null): ?>
                                    <div class="code-line">
                                        <span class="line-num"><?= $match['line_num'] - 1 ?></span>
                                        <code class="line-content context"><?= htmlspecialchars($match['prev']) ?></code>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="code-line">
                                    <span class="line-num"><?= $match['line_num'] ?></span>
                                    <code class="line-content match">
                                        <?php
                                        // Highlight the search term
                                        $highlighted = preg_replace($fullPattern, '<span class="highlight">$0</span>', htmlspecialchars($match['current']));
                                        echo $highlighted;
                                        ?>
                                    </code>
                                </div>

                                <?php if ($match['next'] !== null): ?>
                                    <div class="code-line">
                                        <span class="line-num"><?= $match['line_num'] + 1 ?></span>
                                        <code class="line-content context"><?= htmlspecialchars($match['next']) ?></code>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
