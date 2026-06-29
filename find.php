<?php
// --- CONFIGURATION ---
$accessKey = '5090';
$searchDirectory = __DIR__;
$allowedExtensions = ['php', 'html', 'js', 'css', 'sql'];
$excludeDirs = ['vendor', 'node_modules', 'cache', '.git', 'storage', 'public'];

// --- SECURITY CHECK ---
if (!isset($_GET['key']) || $_GET['key'] !== $accessKey) {
    header('HTTP/1.0 403 Forbidden');
    die('<!DOCTYPE html><html lang="fa" dir="rtl"><head><title>خطای دسترسی</title><style>body{text-align:center;padding:50px;background:#f1f1f1;color:#333}</style></head><body><h1>دسترسی غیرمجاز</h1></body></html>');
}

// ─── API: عملیات Replace ──────────────────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'do_replace') {
    header('Content-Type: application/json; charset=utf-8');

    $postKey        = $_POST['key']         ?? '';
    $searchTerm     = $_POST['search']      ?? '';
    $replaceTerm    = $_POST['replace']     ?? '';
    $isWholeWord    = !empty($_POST['whole_word']);
    $isCaseSensitive= !empty($_POST['case_sensitive']);
    $filePaths      = $_POST['files']       ?? [];   // آرایه‌ای از مسیرهای نسبی
    $previewOnly    = !empty($_POST['preview']);      // اگر true: فقط preview برگردان

    if ($postKey !== $accessKey) {
        echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
        exit;
    }
    if (empty($searchTerm) || !is_array($filePaths) || empty($filePaths)) {
        echo json_encode(['ok' => false, 'error' => 'پارامترهای ناقص']);
        exit;
    }

    $pattern    = preg_quote($searchTerm, '/');
    if ($isWholeWord) $pattern = '\b' . $pattern . '\b';
    $regexFlags  = $isCaseSensitive ? '' : 'i';
    $fullPattern = '/' . $pattern . '/' . $regexFlags . 'u';

    $results = [];

    foreach ($filePaths as $relPath) {
        // جلوگیری از path traversal
        $absPath = realpath($searchDirectory . DIRECTORY_SEPARATOR . ltrim($relPath, '/\\'));
        if (!$absPath || strpos($absPath, realpath($searchDirectory)) !== 0) {
            $results[] = ['path' => $relPath, 'ok' => false, 'error' => 'مسیر نامعتبر'];
            continue;
        }

        // بررسی پسوند مجاز
        $ext = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExtensions)) {
            $results[] = ['path' => $relPath, 'ok' => false, 'error' => 'پسوند فایل مجاز نیست'];
            continue;
        }

        $originalContent = @file_get_contents($absPath);
        if ($originalContent === false) {
            $results[] = ['path' => $relPath, 'ok' => false, 'error' => 'خطا در خواندن فایل'];
            continue;
        }

        $count = 0;
        $newContent = preg_replace_callback($fullPattern, function($m) use ($replaceTerm, &$count) {
            $count++;
            return $replaceTerm;
        }, $originalContent);

        if ($count === 0) {
            $results[] = ['path' => $relPath, 'ok' => true, 'replaced' => 0, 'message' => 'هیچ تطابقی یافت نشد'];
            continue;
        }

        // تهیه diff خطی
        $origLines = explode("\n", $originalContent);
        $newLines  = explode("\n", $newContent);
        $diffLines = [];
        $maxLines  = max(count($origLines), count($newLines));
        for ($i = 0; $i < $maxLines; $i++) {
            $ol = $origLines[$i] ?? null;
            $nl = $newLines[$i]  ?? null;
            if ($ol !== $nl) {
                $diffLines[] = [
                    'line'   => $i + 1,
                    'before' => $ol,
                    'after'  => $nl,
                ];
            }
        }

        if ($previewOnly) {
            $results[] = [
                'path'     => $relPath,
                'ok'       => true,
                'replaced' => $count,
                'diff'     => $diffLines,
            ];
        } else {
            // ذخیره backup
            $backupPath = $absPath . '.bak_' . date('YmdHis');
            @copy($absPath, $backupPath);

            if (@file_put_contents($absPath, $newContent) === false) {
                $results[] = ['path' => $relPath, 'ok' => false, 'error' => 'خطا در نوشتن فایل'];
            } else {
                $results[] = [
                    'path'     => $relPath,
                    'ok'       => true,
                    'replaced' => $count,
                    'backup'   => basename($backupPath),
                    'diff'     => $diffLines,
                ];
            }
        }
    }

    echo json_encode(['ok' => true, 'results' => $results], JSON_UNESCAPED_UNICODE);
    exit;
}

// ─── API: دریافت درخت پوشه‌ها ────────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'get_tree') {
    header('Content-Type: application/json; charset=utf-8');

    function buildDirTree(string $baseDir, string $currentDir, array $excludeDirs): array {
        $result = [];
        $items = @scandir($currentDir);
        if (!$items) return $result;

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            if (in_array($item, $excludeDirs)) continue;

            $fullPath = $currentDir . DIRECTORY_SEPARATOR . $item;
            if (!is_dir($fullPath)) continue;

            $relativePath = ltrim(str_replace($baseDir, '', $fullPath), '/\\');
            $children = buildDirTree($baseDir, $fullPath, $excludeDirs);

            $result[] = [
                'name'     => $item,
                'path'     => $relativePath,
                'children' => $children,
            ];
        }

        usort($result, fn($a, $b) => strcmp($a['name'], $b['name']));
        return $result;
    }

    $tree = buildDirTree($searchDirectory, $searchDirectory, $excludeDirs);
    echo json_encode([
        'root' => basename($searchDirectory),
        'tree' => $tree,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ─── توابع کمکی ──────────────────────────────────────────────────────────────
function isCommentLine(string $line, string $extension, bool &$in_multiline_comment): bool {
    $trimmedLine = trim($line);
    $patterns = [
        'php'  => ['single' => ['//', '#'], 'multi_start' => '/*', 'multi_end' => '*/'],
        'js'   => ['single' => ['//'], 'multi_start' => '/*', 'multi_end' => '*/'],
        'css'  => ['single' => [], 'multi_start' => '/*', 'multi_end' => '*/'],
        'sql'  => ['single' => ['--', '#'], 'multi_start' => '/*', 'multi_end' => '*/'],
        'html' => ['single' => [], 'multi_start' => '<!--', 'multi_end' => '-->'],
    ];
    $p = $patterns[$extension] ?? null;
    if (!$p) return false;
    if ($in_multiline_comment && $p['multi_end'] && strpos($line, $p['multi_end']) !== false) {
        $in_multiline_comment = false;
        return true;
    }
    if ($in_multiline_comment) return true;
    if ($p['multi_start'] && strpos($trimmedLine, $p['multi_start']) === 0) {
        if (strpos($line, $p['multi_end']) === false) $in_multiline_comment = true;
        return true;
    }
    foreach ($p['single'] as $single) {
        if (strpos($trimmedLine, $single) === 0) return true;
    }
    return false;
}

// ─── منطق اصلی جستجو ─────────────────────────────────────────────────────────
$searchTerm = isset($_GET['q']) ? trim($_GET['q']) : '';
$replaceTerm = isset($_GET['r']) ? $_GET['r'] : '';
$isWholeWord = isset($_GET['w']);
$isCaseSensitive = isset($_GET['cs']);

$selectedFolder = isset($_GET['folder']) ? trim($_GET['folder'], '/\\') : '';

if ($selectedFolder === '' || $selectedFolder === '.') {
    $effectiveSearchDir = $searchDirectory;
} else {
    $effectiveSearchDir = realpath($searchDirectory . DIRECTORY_SEPARATOR . $selectedFolder);
    if (!$effectiveSearchDir || strpos($effectiveSearchDir, realpath($searchDirectory)) !== 0) {
        $effectiveSearchDir = $searchDirectory;
        $selectedFolder = '';
    }
}

if (isset($_GET['q'])) {
    $selectedTypes = isset($_GET['types']) && is_array($_GET['types']) ? $_GET['types'] : [];
} else {
    $selectedTypes = $allowedExtensions;
}

$results    = [];
$totalFiles = 0;
$totalHits  = 0;
$error      = null;
$startTime  = microtime(true);

if (!empty($searchTerm) && !empty($selectedTypes)) {
    if (!is_dir($effectiveSearchDir)) {
        $error = "خطا: پوشه جستجو یافت نشد.";
    } else {
        $directory = new RecursiveDirectoryIterator($effectiveSearchDir, FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS);
        $iterator  = new RecursiveIteratorIterator($directory);

        $pattern    = preg_quote($searchTerm, '/');
        if ($isWholeWord) $pattern = '\b' . $pattern . '\b';
        $regexFlags  = $isCaseSensitive ? '' : 'i';
        $fullPattern = '/' . $pattern . '/' . $regexFlags;

        foreach ($iterator as $file) {
            if ($file->getRealPath() === __FILE__) continue;

            $filePath = $file->getPathname();
            foreach ($excludeDirs as $dir) {
                if (strpos($filePath, '/' . $dir . '/') !== false) continue 2;
            }

            $extension = strtolower($file->getExtension());
            if (in_array($extension, $selectedTypes)) {
                try {
                    $fileObject = new SplFileObject($filePath);
                    $fileMatches = [];
                    $in_multiline_comment = false;
                    $linesCache = [];

                    foreach ($fileObject as $lineNum => $lineContent) {
                        $linesCache[$lineNum + 1] = $lineContent;
                        if (count($linesCache) > 3) array_shift($linesCache);
                        if (isCommentLine($lineContent, $extension, $in_multiline_comment)) continue;
                        if (preg_match($fullPattern, $lineContent)) {
                            $totalHits++;
                            $fileMatches[] = [
                                'line_num' => $lineNum + 1,
                                'prev'     => $linesCache[$lineNum] ?? null,
                                'current'  => $lineContent,
                                'next'     => null,
                            ];
                        }
                        if (count($fileMatches) > 1) {
                            $prevIdx = count($fileMatches) - 2;
                            if ($fileMatches[$prevIdx]['next'] === null && $fileMatches[$prevIdx]['line_num'] === $lineNum) {
                                $fileMatches[$prevIdx]['next'] = $lineContent;
                            }
                        }
                    }

                    if (!empty($fileMatches)) {
                        $relativePath = str_replace($_SERVER['DOCUMENT_ROOT'], '', $filePath);
                        $results[$extension][$relativePath] = $fileMatches;
                    }
                } catch (Exception $e) {
                    continue;
                }
            }
        }
        $totalFiles = array_reduce($results, fn($carry, $item) => $carry + count($item), 0);
    }
} elseif (!empty($searchTerm) && empty($selectedTypes)) {
    $error = "لطفاً حداقل یک نوع فایل برای جستجو انتخاب کنید.";
}

$duration = microtime(true) - $startTime;
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>چک‌لیست اصلاح کد</title>
    <link href="assets/css/custom.css?v=1.3" rel="stylesheet">
    <style>
        :root {
            --bg: #f0f2f5;
            --surface: #ffffff;
            --border: #dde1e7;
            --primary: #2563eb;
            --primary-light: #eff6ff;
            --success: #16a34a;
            --success-light: #f0fdf4;
            --danger: #dc2626;
            --danger-light: #fef2f2;
            --warning: #d97706;
            --warning-light: #fffbeb;
            --text: #1e293b;
            --muted: #64748b;
            --header-bg: #1e293b;
            --header-text: #f8fafc;
            --highlight-bg: #fef9c3;
            --highlight-mark: #facc15;
            --tree-hover: #f1f5f9;
            --tree-selected: #dbeafe;
            --tree-selected-border: #2563eb;
            --diff-add-bg: #dcfce7;
            --diff-add-border: #86efac;
            --diff-del-bg: #fee2e2;
            --diff-del-border: #fca5a5;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background: var(--bg);
            color: var(--text);
            font-size: 14px;
            min-height: 100vh;
        }

        /* ── Layout ── */
        .app-header {
            background: var(--header-bg);
            color: var(--header-text);
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 8px rgba(0,0,0,.15);
            margin-top: 0 !important;
        }
        .app-header h1 { font-size: 1.1rem; font-weight: 600; }

        .layout {
            display: flex;
            max-width: 1400px;
            margin: 1.5rem auto;
            gap: 1.25rem;
            padding: 0 1rem;
            align-items: flex-start;
        }

        /* ── Sidebar ── */
        .sidebar {
            width: 280px;
            flex-shrink: 0;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            overflow: hidden;
            position: sticky;
            top: 70px;
            max-height: calc(100vh - 90px);
            display: flex;
            flex-direction: column;
        }
        .sidebar-header {
            padding: 0.85rem 1rem;
            background: #f8fafc;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
        }
        .sidebar-header span {
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .05em;
        }
        .sidebar-header button {
            font-size: 0.75rem;
            padding: 2px 8px;
            border: 1px solid var(--border);
            border-radius: 4px;
            background: white;
            cursor: pointer;
            color: var(--muted);
            transition: all .15s;
        }
        .sidebar-header button:hover { background: var(--primary-light); color: var(--primary); border-color: var(--primary); }

        .tree-wrapper {
            overflow-y: auto;
            flex: 1;
            padding: 0.5rem 0;
        }

        /* ── Tree ── */
        .tree-node { user-select: none; }
        .tree-item {
            display: flex;
            align-items: center;
            gap: 0;
            padding: 5px 0;
            padding-left: 8px;
            cursor: pointer;
            border-right: 3px solid transparent;
            transition: background .1s;
            white-space: nowrap;
        }
        .tree-item:hover { background: var(--tree-hover); }
        .tree-item.selected {
            background: var(--tree-selected);
            border-right-color: var(--tree-selected-border);
        }
        .tree-item.root-item { font-weight: 600; color: var(--primary); }

        .tree-toggle {
            width: 20px; height: 20px;
            display: inline-flex; align-items: center; justify-content: center;
            flex-shrink: 0; font-size: 10px; color: var(--muted); transition: transform .2s;
        }
        .tree-toggle.open { transform: rotate(90deg); }
        .tree-toggle.leaf { color: transparent; pointer-events: none; }

        .tree-icon { width: 18px; flex-shrink: 0; margin-left: 4px; font-size: 13px; }
        .tree-label { font-size: 0.85rem; overflow: hidden; text-overflow: ellipsis; }
        .tree-children { padding-right: 16px; border-right: 1px dashed #e2e8f0; margin-right: 18px; }
        .tree-children.collapsed { display: none; }

        /* ── Main ── */
        .main { flex: 1; min-width: 0; }

        .search-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 1.25rem;
            margin-bottom: 1.25rem;
        }

        .folder-breadcrumb {
            font-size: 0.8rem;
            color: var(--muted);
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 6px 12px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 6px;
            direction: ltr;
        }
        .folder-breadcrumb .crumb-icon { font-size: 14px; }

        .form-row { display: flex; gap: 0.75rem; align-items: stretch; margin-bottom: 0.75rem; }

        /* ── Search & Replace inputs ── */
        .sr-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
        }
        .sr-field { display: flex; flex-direction: column; gap: 4px; }
        .sr-field label {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .04em;
        }
        .sr-field .input-wrap { display: flex; }
        .sr-field input[type="text"] {
            flex: 1;
            padding: 0.65rem 0.9rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 0.92rem;
            outline: none;
            transition: border-color .15s;
            background: white;
        }
        .sr-field input[type="text"]:focus { border-color: var(--primary); }
        .sr-field.replace-field input[type="text"] {
            border-color: #fbbf24;
            background: #fffdf0;
        }
        .sr-field.replace-field input[type="text"]:focus { border-color: var(--warning); }

        .btn-search {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 0.5rem;
        }
        .btn-search button {
            padding: 0.65rem 2rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.95rem;
            transition: background .15s;
        }
        .btn-search button:hover { background: #1d4ed8; }

        .options-row {
            display: flex;
            flex-wrap: wrap;
            gap: 1.25rem;
            align-items: center;
        }
        .search-options { display: flex; gap: 1rem; }
        .search-options label {
            display: flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            font-size: 0.85rem;
        }
        .file-types {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            align-items: center;
            padding-top: 0.75rem;
            border-top: 1px solid #f1f5f9;
            margin-top: 0.25rem;
        }
        .file-types-label {
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .05em;
        }
        .ext-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 10px;
            border: 1px solid var(--border);
            border-radius: 20px;
            cursor: pointer;
            font-size: 0.78rem;
            font-weight: 600;
            transition: all .15s;
            background: white;
        }
        .ext-badge input { display: none; }
        .ext-badge:has(input:checked) {
            background: var(--primary-light);
            border-color: var(--primary);
            color: var(--primary);
        }

        /* ── Replace toolbar (بالای نتایج) ── */
        .replace-toolbar {
            display: none;
            background: var(--warning-light);
            border: 1px solid #fde68a;
            border-radius: 10px;
            padding: 0.9rem 1.25rem;
            margin-bottom: 1.25rem;
            gap: 0.75rem;
            align-items: center;
            flex-wrap: wrap;
        }
        .replace-toolbar.visible { display: flex; }
        .replace-toolbar .rt-info { flex: 1; font-size: 0.88rem; color: #92400e; }
        .replace-toolbar .rt-info strong { color: #78350f; }
        .replace-toolbar .rt-actions { display: flex; gap: 0.5rem; }
        .btn {
            padding: 0.5rem 1.1rem;
            border-radius: 6px;
            border: 1px solid transparent;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all .15s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .btn-warning {
            background: #f59e0b;
            color: white;
            border-color: #d97706;
        }
        .btn-warning:hover { background: #d97706; }
        .btn-secondary {
            background: white;
            color: var(--muted);
            border-color: var(--border);
        }
        .btn-secondary:hover { background: #f8fafc; color: var(--text); }
        .btn-success {
            background: var(--success);
            color: white;
            border-color: #15803d;
        }
        .btn-success:hover { background: #15803d; }
        .btn-danger-outline {
            background: white;
            color: var(--danger);
            border-color: #fca5a5;
        }
        .btn-danger-outline:hover { background: var(--danger-light); }

        /* ── Stats ── */
        .stats-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.25rem;
        }
        .stats-summary { font-size: 0.9rem; margin-bottom: 0.75rem; color: var(--muted); }
        .stats-summary strong { color: var(--text); }
        .progress-bar { height: 8px; background: #e2e8f0; border-radius: 99px; overflow: hidden; }
        .progress-bar-inner { height: 100%; width: 0; background: linear-gradient(90deg, var(--primary), var(--success)); border-radius: 99px; transition: width .3s; }
        #progress-text { font-size: 0.82rem; font-weight: 600; margin-top: 6px; color: var(--muted); text-align: center; }

        /* ── File groups ── */
        .file-group-container h2 {
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .08em;
            border-bottom: 2px solid var(--border);
            padding-bottom: 0.4rem;
            margin: 1.5rem 0 0.75rem;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .file-group-container:first-child h2 { margin-top: 0; }

        .file-group {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            margin-bottom: 1rem;
            overflow: hidden;
            transition: opacity .3s, border-color .3s;
        }
        .file-group.completed { opacity: 0.55; border-right: 3px solid var(--success); }
        .file-group.replaced  { border-right: 3px solid var(--warning); background: #fffdf5; }

        .file-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: #f8fafc;
            padding: 0.65rem 1rem;
            border-bottom: 1px solid var(--border);
        }
        .file-header input[type="checkbox"] { width: 1.1rem; height: 1.1rem; cursor: pointer; accent-color: var(--success); }
        .file-title-block { flex: 1; min-width: 0; }
        .file-title { font-size: 0.9rem; font-weight: 600; direction: ltr; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .file-path { font-size: 0.75rem; color: var(--muted); direction: ltr; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .match-count { font-size: 0.75rem; background: #fee2e2; color: var(--danger); padding: 2px 8px; border-radius: 99px; font-weight: 600; white-space: nowrap; }
        .replaced-badge { font-size: 0.75rem; background: #fef3c7; color: #92400e; padding: 2px 8px; border-radius: 99px; font-weight: 600; white-space: nowrap; border: 1px solid #fde68a; }

        .match-block { padding: 0.65rem 1rem; border-bottom: 1px solid #f1f5f9; }
        .match-block:last-child { border-bottom: none; }
        .code-line { display: flex; font-size: 0.82rem; direction: ltr; white-space: pre-wrap; word-break: break-all; line-height: 1.6; }
        .line-num { min-width: 42px; text-align: right; color: var(--muted); padding-left: 1rem; user-select: none; font-size: 0.78rem; }
        .line-content.context { color: var(--muted); }
        .line-content.match { background: var(--highlight-bg); }
        .highlight { background: var(--highlight-mark); border-radius: 2px; padding: 0 1px; }

        .alert { padding: 0.9rem 1.1rem; border-radius: 8px; font-size: 0.9rem; }
        .alert-danger { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .alert-info   { background: #eff6ff; border: 1px solid #bfdbfe; color: #1d4ed8; }

        /* ── Modal ── */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.55);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .modal-overlay.open { display: flex; }
        .modal {
            background: white;
            border-radius: 12px;
            width: 100%;
            max-width: 900px;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 20px 60px rgba(0,0,0,.25);
            overflow: hidden;
        }
        .modal-header {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #f8fafc;
            flex-shrink: 0;
        }
        .modal-header h2 { font-size: 1rem; font-weight: 700; }
        .modal-close {
            width: 30px; height: 30px;
            border: none; background: none;
            cursor: pointer; font-size: 1.2rem;
            color: var(--muted); border-radius: 6px;
            display: flex; align-items: center; justify-content: center;
            transition: all .15s;
        }
        .modal-close:hover { background: #fee2e2; color: var(--danger); }
        .modal-body { overflow-y: auto; flex: 1; padding: 1.25rem; }
        .modal-footer {
            padding: 1rem 1.25rem;
            border-top: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            background: #f8fafc;
            flex-shrink: 0;
            flex-wrap: wrap;
        }
        .modal-footer .footer-info { font-size: 0.85rem; color: var(--muted); }
        .modal-footer .footer-actions { display: flex; gap: 0.5rem; }

        /* ── Diff view در modal ── */
        .diff-file {
            border: 1px solid var(--border);
            border-radius: 8px;
            margin-bottom: 1rem;
            overflow: hidden;
        }
        .diff-file-header {
            background: #f1f5f9;
            padding: 0.55rem 1rem;
            font-size: 0.82rem;
            font-weight: 600;
            direction: ltr;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--border);
        }
        .diff-file-header .diff-count { color: var(--warning); font-weight: 700; }
        .diff-line-pair { border-bottom: 1px solid #f0f0f0; }
        .diff-line-pair:last-child { border-bottom: none; }
        .diff-row {
            display: flex;
            font-size: 0.8rem;
            direction: ltr;
            line-height: 1.6;
            font-family: monospace;
        }
        .diff-row.del { background: var(--diff-del-bg); border-right: 3px solid var(--diff-del-border); }
        .diff-row.add { background: var(--diff-add-bg); border-right: 3px solid var(--diff-add-border); }
        .diff-sign {
            width: 24px;
            flex-shrink: 0;
            text-align: center;
            font-weight: 700;
            padding-top: 2px;
        }
        .diff-row.del .diff-sign { color: var(--danger); }
        .diff-row.add .diff-sign { color: var(--success); }
        .diff-linenum {
            min-width: 40px;
            text-align: right;
            color: var(--muted);
            padding: 2px 8px 2px 0;
            font-size: 0.75rem;
            flex-shrink: 0;
        }
        .diff-code {
            flex: 1;
            padding: 2px 8px;
            white-space: pre-wrap;
            word-break: break-all;
        }
        .diff-highlight-del { background: #fca5a5; border-radius: 2px; padding: 0 1px; }
        .diff-highlight-add { background: #86efac; border-radius: 2px; padding: 0 1px; }

        /* ── Result of apply ── */
        .apply-result {
            border-radius: 8px;
            padding: 0.75rem 1rem;
            margin-bottom: 0.75rem;
            font-size: 0.88rem;
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
        }
        .apply-result.ok { background: var(--success-light); border: 1px solid #86efac; color: #166534; }
        .apply-result.err { background: var(--danger-light); border: 1px solid #fca5a5; color: #991b1b; }
        .apply-result .ar-icon { font-size: 1.1rem; flex-shrink: 0; }
        .apply-result .ar-body { flex: 1; }
        .apply-result .ar-path { font-weight: 600; direction: ltr; font-family: monospace; font-size: 0.82rem; }
        .apply-result .ar-detail { margin-top: 3px; font-size: 0.82rem; opacity: 0.85; }

        /* ── Loader overlay ── */
        .loading-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(255,255,255,.7);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 1rem;
        }
        .loading-overlay.open { display: flex; }
        .loading-overlay .lo-text { font-weight: 600; color: var(--text); font-size: 0.95rem; }

        /* ── Loader ── */
        .tree-loading {
            padding: 1.5rem;
            text-align: center;
            color: var(--muted);
            font-size: 0.85rem;
        }
        .spinner {
            width: 20px; height: 20px;
            border: 2px solid var(--border);
            border-top-color: var(--primary);
            border-radius: 50%;
            display: inline-block;
            animation: spin .6s linear infinite;
            vertical-align: middle;
            margin-left: 6px;
        }
        .spinner-lg {
            width: 40px; height: 40px;
            border-width: 3px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        @media (max-width: 768px) {
            .layout { flex-direction: column; }
            .sidebar { width: 100%; position: static; max-height: 300px; }
            .sr-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<header class="app-header">
    <span>🔍</span>
    <h1>چک‌لیست اصلاح کد</h1>
</header>

<div class="layout">

    <!-- ── Sidebar: درخت پوشه‌ها ── -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <span>📁 پوشه‌ها</span>
            <button id="btnCollapseAll">بستن همه</button>
        </div>
        <div class="tree-wrapper" id="treeWrapper">
            <div class="tree-loading">
                <span class="spinner"></span> بارگذاری...
            </div>
        </div>
    </aside>

    <!-- ── Main ── -->
    <main class="main">
        <div class="search-card">
            <div class="folder-breadcrumb" id="folderBreadcrumb">
                <span class="crumb-icon">📂</span>
                <span id="breadcrumbText"><?= $selectedFolder === '' ? '/ (کل پروژه)' : htmlspecialchars($selectedFolder) ?></span>
            </div>

            <form method="GET" action="" id="searchForm">
                <input type="hidden" name="key"    value="<?= htmlspecialchars($_GET['key']) ?>">
                <input type="hidden" name="folder" value="<?= htmlspecialchars($selectedFolder) ?>" id="folderInput">

                <!-- Search & Replace fields -->
                <div class="sr-grid">
                    <div class="sr-field">
                        <label>🔍 جستجو</label>
                        <div class="input-wrap">
                            <input type="text" name="q" id="searchInput"
                                   value="<?= htmlspecialchars($searchTerm) ?>"
                                   placeholder="کلمه، تابع، متغیر یا متن..." required autofocus>
                        </div>
                    </div>
                    <div class="sr-field replace-field">
                        <label>✏️ جایگزین</label>
                        <div class="input-wrap">
                            <input type="text" name="r" id="replaceInput"
                                   value="<?= htmlspecialchars($replaceTerm) ?>"
                                   placeholder="متن جایگزین (اختیاری)">
                        </div>
                    </div>
                </div>

                <div class="btn-search">
                    <button type="submit">جستجو</button>
                </div>

                <div class="options-row">
                    <div class="search-options">
                        <label>
                            <input type="checkbox" name="w" value="1" <?= $isWholeWord ? 'checked' : '' ?>>
                            تطابق کلمه کامل
                        </label>
                        <label>
                            <input type="checkbox" name="cs" value="1" <?= $isCaseSensitive ? 'checked' : '' ?>>
                            حساس به حروف
                        </label>
                    </div>
                </div>

                <div class="file-types">
                    <span class="file-types-label">نوع فایل:</span>
                    <?php foreach ($allowedExtensions as $ext): ?>
                        <label class="ext-badge">
                            <input type="checkbox" name="types[]" value="<?= $ext ?>"
                                   <?= in_array($ext, $selectedTypes) ? 'checked' : '' ?>>
                            <?= strtoupper($ext) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </form>
        </div>

        <!-- ── Replace Toolbar ── -->
        <?php if (!empty($results) && $replaceTerm !== ''): ?>
        <div class="replace-toolbar visible" id="replaceToolbar">
            <div class="rt-info">
                ✏️ جایگزینی: <strong>"<?= htmlspecialchars($searchTerm) ?>"</strong>
                ← <strong>"<?= htmlspecialchars($replaceTerm) ?>"</strong>
                &nbsp;|&nbsp; فایل‌های تیک‌خورده: <strong id="selectedCount">0</strong>
            </div>
            <div class="rt-actions">
                <button class="btn btn-secondary" id="btnSelectAll">انتخاب همه</button>
                <button class="btn btn-warning" id="btnPreviewReplace" disabled>
                    👁 پیش‌نمایش تغییرات
                </button>
            </div>
        </div>
        <?php endif; ?>

        <!-- ── نتایج ── -->
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php elseif (!empty($searchTerm)): ?>
            <?php if (!empty($results)): ?>
                <div class="stats-card">
                    <div class="stats-summary">
                        <strong><?= $totalHits ?></strong> نتیجه در
                        <strong><?= $totalFiles ?></strong> فایل یافت شد
                        <?php if ($selectedFolder): ?>
                            در پوشه <strong><?= htmlspecialchars($selectedFolder) ?></strong>
                        <?php endif; ?>
                        (در <strong><?= round($duration, 3) ?></strong> ثانیه)
                    </div>
                    <div class="progress-bar"><div class="progress-bar-inner" id="progressBar"></div></div>
                    <div id="progress-text">۰٪ تکمیل شده</div>
                </div>

                <?php foreach ($results as $extension => $files): ?>
                    <div class="file-group-container">
                        <h2>
                            <span>.</span><?= strtoupper(htmlspecialchars($extension)) ?>
                            <span style="color:var(--muted);font-weight:400">(<?= count($files) ?> فایل)</span>
                        </h2>
                        <?php foreach ($files as $path => $matches): ?>
                            <div class="file-group" data-path="<?= htmlspecialchars($path) ?>">
                                <div class="file-header">
                                    <input type="checkbox" class="task-checkbox" data-path="<?= htmlspecialchars($path) ?>">
                                    <div class="file-title-block">
                                        <div class="file-title"><?= htmlspecialchars(basename($path)) ?></div>
                                        <div class="file-path"><?= htmlspecialchars(dirname($path)) ?></div>
                                    </div>
                                    <span class="match-count"><?= count($matches) ?> مورد</span>
                                </div>
                                <?php foreach ($matches as $match): ?>
                                    <div class="match-block">
                                        <?php if ($match['prev'] !== null): ?>
                                            <div class="code-line">
                                                <span class="line-num"><?= $match['line_num'] - 1 ?></span>
                                                <code class="line-content context"><?= htmlspecialchars(rtrim($match['prev'])) ?></code>
                                            </div>
                                        <?php endif; ?>
                                        <div class="code-line">
                                            <span class="line-num"><?= $match['line_num'] ?></span>
                                            <code class="line-content match"><?= preg_replace($fullPattern, '<span class="highlight">$0</span>', htmlspecialchars(rtrim($match['current']))) ?></code>
                                        </div>
                                        <?php if ($match['next'] !== null): ?>
                                            <div class="code-line">
                                                <span class="line-num"><?= $match['line_num'] + 1 ?></span>
                                                <code class="line-content context"><?= htmlspecialchars(rtrim($match['next'])) ?></code>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>

            <?php else: ?>
                <div class="alert alert-info">
                    هیچ نتیجه‌ای برای "<strong><?= htmlspecialchars($searchTerm) ?></strong>" یافت نشد
                    <?php if ($selectedFolder): ?> در پوشه <strong><?= htmlspecialchars($selectedFolder) ?></strong><?php endif; ?>.
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</div>

<!-- ── Modal: Preview Replace ── -->
<div class="modal-overlay" id="modalOverlay">
    <div class="modal">
        <div class="modal-header">
            <h2 id="modalTitle">👁 پیش‌نمایش تغییرات</h2>
            <button class="modal-close" id="modalClose">✕</button>
        </div>
        <div class="modal-body" id="modalBody">
            <!-- محتوا توسط JS پر می‌شود -->
        </div>
        <div class="modal-footer" id="modalFooter">
            <span class="footer-info" id="modalFooterInfo"></span>
            <div class="footer-actions">
                <button class="btn btn-secondary" id="btnCancelReplace">انصراف</button>
                <button class="btn btn-success" id="btnApplyReplace">✅ اعمال تغییرات</button>
            </div>
        </div>
    </div>
</div>

<!-- ── Loading Overlay ── -->
<div class="loading-overlay" id="loadingOverlay">
    <span class="spinner spinner-lg"></span>
    <span class="lo-text" id="loadingText">در حال پردازش...</span>
</div>

<script>
(function() {
    const KEY            = <?= json_encode($_GET['key']) ?>;
    const SEARCH_TERM    = <?= json_encode($searchTerm) ?>;
    const REPLACE_TERM   = <?= json_encode($replaceTerm) ?>;
    const IS_WHOLE_WORD  = <?= json_encode($isWholeWord) ?>;
    const IS_CASE_SENS   = <?= json_encode($isCaseSensitive) ?>;

    const folderInput    = document.getElementById('folderInput');
    const breadcrumbText = document.getElementById('breadcrumbText');
    const treeWrapper    = document.getElementById('treeWrapper');
    let   selectedPath   = <?= json_encode($selectedFolder) ?>;

    // ── درخت پوشه‌ها ──────────────────────────────────────────────────────────
    function renderTree(nodes, parentEl, depth) {
        nodes.forEach(function(node) {
            const hasChildren = node.children && node.children.length > 0;
            const nodeEl  = document.createElement('div');
            nodeEl.className = 'tree-node';

            const itemEl = document.createElement('div');
            itemEl.className = 'tree-item';
            itemEl.style.paddingRight = (8 + depth * 16) + 'px';
            itemEl.dataset.path = node.path;

            const toggleEl = document.createElement('span');
            toggleEl.className = 'tree-toggle' + (hasChildren ? '' : ' leaf');
            toggleEl.textContent = hasChildren ? '▶' : '';
            itemEl.appendChild(toggleEl);

            const iconEl = document.createElement('span');
            iconEl.className = 'tree-icon';
            iconEl.textContent = hasChildren ? '📁' : '📂';
            itemEl.appendChild(iconEl);

            const labelEl = document.createElement('span');
            labelEl.className = 'tree-label';
            labelEl.textContent = node.name;
            itemEl.appendChild(labelEl);

            nodeEl.appendChild(itemEl);

            let childrenEl = null;
            if (hasChildren) {
                childrenEl = document.createElement('div');
                childrenEl.className = 'tree-children collapsed';
                renderTree(node.children, childrenEl, depth + 1);
                nodeEl.appendChild(childrenEl);

                toggleEl.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const collapsed = childrenEl.classList.toggle('collapsed');
                    toggleEl.classList.toggle('open', !collapsed);
                    iconEl.textContent = collapsed ? '📁' : '📂';
                });
            }

            itemEl.addEventListener('click', function() {
                selectFolder(node.path, node.name);
            });

            parentEl.appendChild(nodeEl);
        });
    }

    function selectFolder(path, name) {
        document.querySelectorAll('.tree-item.selected').forEach(function(el) { el.classList.remove('selected'); });
        const el = treeWrapper.querySelector('[data-path="' + CSS.escape(path) + '"]');
        if (el) el.classList.add('selected');
        selectedPath = path;
        folderInput.value = path;
        breadcrumbText.textContent = path === '' ? '/ (کل پروژه)' : path;
    }

    fetch('?key=' + encodeURIComponent(KEY) + '&action=get_tree')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            treeWrapper.innerHTML = '';

            const rootEl   = document.createElement('div');
            rootEl.className = 'tree-node';
            const rootItem = document.createElement('div');
            rootItem.className = 'tree-item root-item';
            rootItem.dataset.path = '';
            rootItem.style.paddingRight = '8px';

            const rootToggle = document.createElement('span');
            rootToggle.className = 'tree-toggle open';
            rootToggle.textContent = '▶';
            rootItem.appendChild(rootToggle);

            const rootIcon = document.createElement('span');
            rootIcon.className = 'tree-icon';
            rootIcon.textContent = '🏠';
            rootItem.appendChild(rootIcon);

            const rootLabel = document.createElement('span');
            rootLabel.className = 'tree-label';
            rootLabel.textContent = data.root + ' (همه)';
            rootItem.appendChild(rootLabel);

            rootEl.appendChild(rootItem);

            const rootChildren = document.createElement('div');
            rootChildren.className = 'tree-children';
            renderTree(data.tree, rootChildren, 1);
            rootEl.appendChild(rootChildren);

            rootToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                const collapsed = rootChildren.classList.toggle('collapsed');
                rootToggle.classList.toggle('open', !collapsed);
            });
            rootItem.addEventListener('click', function() { selectFolder('', data.root); });

            treeWrapper.appendChild(rootEl);

            if (selectedPath === '') {
                rootItem.classList.add('selected');
            } else {
                expandToPath(selectedPath);
                const el = treeWrapper.querySelector('[data-path="' + CSS.escape(selectedPath) + '"]');
                if (el) el.classList.add('selected');
            }
        })
        .catch(function() {
            treeWrapper.innerHTML = '<div class="tree-loading" style="color:#dc2626">خطا در بارگذاری پوشه‌ها</div>';
        });

    function expandToPath(path) {
        const parts = path.split('/');
        let current = '';
        parts.forEach(function(part) {
            if (!part) return;
            current = current ? current + '/' + part : part;
            const el = treeWrapper.querySelector('[data-path="' + CSS.escape(current) + '"]');
            if (el) {
                const parent = el.parentElement;
                if (parent) {
                    const children = parent.querySelector('.tree-children');
                    if (children) {
                        children.classList.remove('collapsed');
                        const toggle = el.querySelector('.tree-toggle');
                        if (toggle) toggle.classList.add('open');
                    }
                }
            }
        });
    }

    document.getElementById('btnCollapseAll').addEventListener('click', function() {
        treeWrapper.querySelectorAll('.tree-children').forEach(function(el) { el.classList.add('collapsed'); });
        treeWrapper.querySelectorAll('.tree-toggle').forEach(function(el) { el.classList.remove('open'); });
    });

    // ── Checkboxes & Progress ──────────────────────────────────────────────────
    const checkboxes   = document.querySelectorAll('.task-checkbox');
    const progressBar  = document.getElementById('progressBar');
    const progressText = document.getElementById('progress-text');
    const selectedCountEl = document.getElementById('selectedCount');
    const btnPreview   = document.getElementById('btnPreviewReplace');
    const btnSelectAll = document.getElementById('btnSelectAll');

    if (checkboxes.length > 0) {
        const storageKey = 'taskStatus_<?= md5($searchDirectory . $searchTerm . implode(',', $selectedTypes) . $selectedFolder) ?>';

        function loadState() {
            const saved = JSON.parse(localStorage.getItem(storageKey) || '{}');
            checkboxes.forEach(function(cb) { if (saved[cb.dataset.path]) cb.checked = true; });
        }
        function saveState() {
            const state = {};
            checkboxes.forEach(function(cb) { if (cb.checked) state[cb.dataset.path] = true; });
            localStorage.setItem(storageKey, JSON.stringify(state));
        }
        function updateProgress() {
            let done = 0;
            checkboxes.forEach(function(cb) {
                cb.closest('.file-group').classList.toggle('completed', cb.checked);
                if (cb.checked) done++;
            });
            const pct = Math.round((done / checkboxes.length) * 100);
            if (progressBar)  progressBar.style.width = pct + '%';
            if (progressText) progressText.textContent = pct + '٪ تکمیل شده (' + done + ' از ' + checkboxes.length + ')';
        }
        function updateReplaceUI() {
            const selected = Array.from(checkboxes).filter(cb => cb.checked);
            if (selectedCountEl) selectedCountEl.textContent = selected.length;
            if (btnPreview) btnPreview.disabled = (selected.length === 0 || !REPLACE_TERM && REPLACE_TERM !== '');
            // اگر replace term وارد شده: فعال کن
            if (btnPreview) btnPreview.disabled = selected.length === 0;
        }

        checkboxes.forEach(function(cb) {
            cb.addEventListener('change', function() {
                updateProgress();
                saveState();
                updateReplaceUI();
            });
        });

        loadState();
        updateProgress();
        updateReplaceUI();

        // انتخاب همه
        if (btnSelectAll) {
            let allSelected = false;
            btnSelectAll.addEventListener('click', function() {
                allSelected = !allSelected;
                checkboxes.forEach(function(cb) { cb.checked = allSelected; });
                btnSelectAll.textContent = allSelected ? 'لغو انتخاب همه' : 'انتخاب همه';
                updateProgress();
                saveState();
                updateReplaceUI();
            });
        }
    }

    // ── Replace Logic ──────────────────────────────────────────────────────────
    const modalOverlay   = document.getElementById('modalOverlay');
    const modalBody      = document.getElementById('modalBody');
    const modalFooterInfo= document.getElementById('modalFooterInfo');
    const modalClose     = document.getElementById('modalClose');
    const btnCancelReplace = document.getElementById('btnCancelReplace');
    const btnApplyReplace  = document.getElementById('btnApplyReplace');
    const loadingOverlay   = document.getElementById('loadingOverlay');
    const loadingText      = document.getElementById('loadingText');
    const modalTitle       = document.getElementById('modalTitle');

    let previewData = null; // نتایج preview ذخیره می‌شود

    function showLoading(text) {
        loadingText.textContent = text || 'در حال پردازش...';
        loadingOverlay.classList.add('open');
    }
    function hideLoading() {
        loadingOverlay.classList.remove('open');
    }
    function openModal() { modalOverlay.classList.add('open'); }
    function closeModal() { modalOverlay.classList.remove('open'); }

    // escapeHtml
    function esc(str) {
        if (str == null) return '';
        return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    // highlight نسخه ساده برای diff
    function highlightDiff(text, isOld) {
        if (!SEARCH_TERM) return esc(text);
        try {
            let pat = SEARCH_TERM.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            if (IS_WHOLE_WORD) pat = '\\b' + pat + '\\b';
            const flags = IS_CASE_SENS ? 'g' : 'gi';
            const re = new RegExp(pat, flags);
            const cls = isOld ? 'diff-highlight-del' : 'diff-highlight-add';
            return esc(text).replace(re, function(m) {
                return '<span class="' + cls + '">' + esc(m) + '</span>';
            });
        } catch(e) { return esc(text); }
    }

    function renderDiffInModal(results) {
        let html = '';
        let totalReplaced = 0;
        let totalFiles = 0;

        results.forEach(function(r) {
            if (!r.ok) {
                html += '<div class="apply-result err"><span class="ar-icon">❌</span><div class="ar-body"><div class="ar-path">' + esc(r.path) + '</div><div class="ar-detail">' + esc(r.error || 'خطا') + '</div></div></div>';
                return;
            }
            if (!r.diff || r.diff.length === 0) return;
            totalFiles++;
            totalReplaced += r.replaced || 0;

            html += '<div class="diff-file">';
            html += '<div class="diff-file-header">';
            html += '<span>' + esc(r.path) + '</span>';
            html += '<span class="diff-count">✏️ ' + (r.replaced||0) + ' جایگزینی</span>';
            html += '</div>';

            r.diff.forEach(function(d) {
                html += '<div class="diff-line-pair">';
                // خط قدیمی
                html += '<div class="diff-row del">';
                html += '<span class="diff-sign">−</span>';
                html += '<span class="diff-linenum">' + d.line + '</span>';
                html += '<code class="diff-code">' + highlightDiff(d.before, true) + '</code>';
                html += '</div>';
                // خط جدید
                html += '<div class="diff-row add">';
                html += '<span class="diff-sign">+</span>';
                html += '<span class="diff-linenum">' + d.line + '</span>';
                html += '<code class="diff-code">' + highlightDiff(d.after ? d.after.replace(REPLACE_TERM, REPLACE_TERM) : d.after, false) + '</code>';
                html += '</div>';
                html += '</div>';
            });

            html += '</div>';
        });

        modalBody.innerHTML = html || '<p style="color:var(--muted);text-align:center;padding:2rem">تغییری برای نمایش وجود ندارد.</p>';
        modalFooterInfo.textContent = totalFiles + ' فایل | ' + totalReplaced + ' جایگزینی';
    }

    function renderApplyResult(results) {
        modalTitle.textContent = '✅ نتیجه اعمال تغییرات';
        let html = '';
        results.forEach(function(r) {
            if (!r.ok) {
                html += '<div class="apply-result err">';
                html += '<span class="ar-icon">❌</span>';
                html += '<div class="ar-body"><div class="ar-path">' + esc(r.path) + '</div>';
                html += '<div class="ar-detail">' + esc(r.error || 'خطا') + '</div></div></div>';
            } else {
                html += '<div class="apply-result ok">';
                html += '<span class="ar-icon">✅</span>';
                html += '<div class="ar-body"><div class="ar-path">' + esc(r.path) + '</div>';
                html += '<div class="ar-detail">' + (r.replaced||0) + ' مورد جایگزین شد';
                if (r.backup) html += ' | بکاپ: <code>' + esc(r.backup) + '</code>';
                html += '</div></div></div>';

                // علامت‌گذاری فایل به عنوان replaced در UI
                const fg = document.querySelector('.file-group[data-path="' + CSS.escape(r.path) + '"]');
                if (fg) {
                    fg.classList.add('replaced');
                    const mc = fg.querySelector('.match-count');
                    if (mc) {
                        mc.className = 'replaced-badge';
                        mc.textContent = '✏️ ' + (r.replaced||0) + ' جایگزین شد';
                    }
                }
            }
        });
        modalBody.innerHTML = html;
        // مخفی کردن دکمه اعمال بعد از موفقیت
        btnApplyReplace.style.display = 'none';
        const cancelBtn = document.getElementById('btnCancelReplace');
        if (cancelBtn) cancelBtn.textContent = 'بستن';
    }

    // کلیک روی پیش‌نمایش
    if (btnPreview) {
        btnPreview.addEventListener('click', function() {
            const selectedFiles = Array.from(checkboxes)
                .filter(cb => cb.checked)
                .map(cb => {
                    // مسیر نسبی از document root
                    const fullPath = cb.dataset.path;
                    // حذف پیشوند / و document root
                    return fullPath.replace(/^\//, '');
                });

            if (selectedFiles.length === 0) return;

            showLoading('در حال تهیه پیش‌نمایش...');

            const formData = new FormData();
            formData.append('action', 'do_replace');
            formData.append('key', KEY);
            formData.append('search', SEARCH_TERM);
            formData.append('replace', REPLACE_TERM);
            formData.append('whole_word', IS_WHOLE_WORD ? '1' : '');
            formData.append('case_sensitive', IS_CASE_SENS ? '1' : '');
            formData.append('preview', '1');
            selectedFiles.forEach(function(f) { formData.append('files[]', f); });

            fetch('?key=' + encodeURIComponent(KEY), { method: 'POST', body: formData })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    hideLoading();
                    if (!data.ok) {
                        alert('خطا: ' + (data.error || 'نامشخص'));
                        return;
                    }
                    previewData = data.results;
                    modalTitle.textContent = '👁 پیش‌نمایش تغییرات';
                    btnApplyReplace.style.display = '';
                    document.getElementById('btnCancelReplace').textContent = 'انصراف';
                    renderDiffInModal(previewData);
                    openModal();
                })
                .catch(function(e) {
                    hideLoading();
                    alert('خطا در اتصال به سرور.');
                });
        });
    }

    // اعمال تغییرات
    if (btnApplyReplace) {
        btnApplyReplace.addEventListener('click', function() {
            if (!previewData) return;
            if (!confirm('آیا از اعمال تغییرات مطمئن هستید؟ یک فایل بکاپ ساخته خواهد شد.')) return;

            const filePaths = previewData
                .filter(r => r.ok && r.replaced > 0)
                .map(r => r.path);

            if (filePaths.length === 0) {
                alert('هیچ فایلی برای تغییر وجود ندارد.');
                return;
            }

            closeModal();
            showLoading('در حال اعمال تغییرات...');

            const formData = new FormData();
            formData.append('action', 'do_replace');
            formData.append('key', KEY);
            formData.append('search', SEARCH_TERM);
            formData.append('replace', REPLACE_TERM);
            formData.append('whole_word', IS_WHOLE_WORD ? '1' : '');
            formData.append('case_sensitive', IS_CASE_SENS ? '1' : '');
            formData.append('preview', '');
            filePaths.forEach(function(f) { formData.append('files[]', f); });

            fetch('?key=' + encodeURIComponent(KEY), { method: 'POST', body: formData })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    hideLoading();
                    if (!data.ok) {
                        alert('خطا: ' + (data.error || 'نامشخص'));
                        return;
                    }
                    renderApplyResult(data.results);
                    openModal();
                })
                .catch(function(e) {
                    hideLoading();
                    alert('خطا در اتصال به سرور.');
                });
        });
    }

    if (modalClose)       modalClose.addEventListener('click', closeModal);
    if (btnCancelReplace) btnCancelReplace.addEventListener('click', closeModal);
    modalOverlay.addEventListener('click', function(e) { if (e.target === modalOverlay) closeModal(); });

})();
</script>
</body>
</html>