<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
require_once '../includes/version.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';

if (!isset($db)) {
    $database = new Database();
    $db = $database->getConnection();
}
$auth = new Auth($db);
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) $user_id = $auth->getUserFromToken();
if (!$user_id && isset($_COOKIE['auth_token'])) $user_id = $auth->validateToken($_COOKIE['auth_token']);

if (!$user_id) {
    header('Location: ../index.php');
    exit;
}

$__me = loadUserForPermissions($db, (int) $user_id);
if (!$__me) {
    header('Location: ../index.php');
    exit;
}

// 🔒 اولویت «بحرانی» فقط برای مدیران/سوپروایزرها قابل انتخاب است، نه کارمند عادی
$currentUserRole = $__me['role'] ?? 'employee';
$canSetCriticalPriority = ($currentUserRole !== 'employee');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تیکت جدید - سیستم مدیریت کار</title>

    <!-- Bootstrap 5 RTL -->
    <link href="<?= asset('../assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">
    <link href="<?= asset('../assets/js/cdn/fonts/bootstrap-icons.woff2?30af91bf14e37666a085fb8a161ff36d') ?>" rel="stylesheet">
    <script src="<?= asset('../assets/js/jalali.js') ?>"></script>
    <script src="<?= asset('../assets/js/cdn/bootstrap.bundle.min.js') ?>"></script>
    <script src="<?= asset('../assets/js/config.js') ?>"></script>
    <script src="<?= asset('../assets/js/cdn/jquery.min.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('../assets/css/custom.css') ?>">

    <style>
        .overview-container {
            max-width: 1200px;
            margin: 78px auto 40px;
            padding: 0 16px;
        }

        /* ───── هدرِ صفحه — قابِ مجزا و بالای صفحه (هم‌سبک با تیکت‌ها/مدیریت کارها) ───── */
        .ctkt-head-card {
            background: #fff;
            border-radius: 14px;
            padding: 18px 22px;
            box-shadow: 0 2px 12px rgba(0,0,0,.04);
            border: 1px solid #f0f0f0;
            margin-bottom: 16px;
            margin-top: 10px;
        }
        .ctkt-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }
        .ctkt-head-text h1 {
            font-size: 1.3rem;
            font-weight: 700;
            color: #1a1a1a;
            margin: 0 0 4px;
            display: flex;
            align-items: center;
        }
        .ctkt-head-text h1 i { color: #744ca4; margin-left: 8px; }
        .ctkt-head-text p {
            font-size: .84rem;
            color: #888;
            margin: 0;
        }

        /* ───── فرم کارت ───── */
        .form-card {
            background: #fff;
            border-radius: 14px;
            padding: 28px 30px 32px;
            box-shadow: 0 2px 16px rgba(0,0,0,.04);
            border: 1px solid #f0f0f0;
        }
        .form-card .form-label {
            font-weight: 600;
            font-size: .86rem;
            color: #444;
            margin-bottom: 5px;
        }
        .form-card .form-control,
        .form-card .form-select {
            border-radius: 10px;
            border: 1.5px solid #e5e7eb;
            font-size: .87rem;
            font-family: inherit;
            padding: 10px 14px;
            transition: border-color .2s, box-shadow .2s;
        }
        .form-card .form-control:focus,
        .form-card .form-select:focus {
            border-color: #744ca4;
            box-shadow: 0 0 0 3px rgba(116,76,164,.1);
        }
        .form-card textarea.form-control {
            min-height: 130px;
            resize: vertical;
        }

        /* ───── آپلود ───── */
        .upload-zone {
            border: 2px dashed #d0d5dd;
            border-radius: 12px;
            padding: 24px;
            text-align: center;
            cursor: pointer;
            background: #fafafa;
            transition: all .2s;
        }
        .upload-zone:hover,
        .upload-zone.dragover {
            border-color: #744ca4;
            background: #f8f5ff;
        }
        .upload-zone i.cloud-icon { font-size: 2rem; color: #744ca4; }
        .upload-zone p { margin: 8px 0 0; font-size: .84rem; color: #666; }

        .file-preview { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 10px; }
        .file-chip {
            background: #f0f4ff;
            padding: 5px 12px;
            border-radius: 8px;
            font-size: .8rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .file-chip .remove-file {
            cursor: pointer;
            color: #ef4444;
            font-weight: 700;
            font-size: .9rem;
        }
        .file-chip .remove-file:hover { color: #dc2626; }

        /* پیش‌نمایشِ مربعیِ تصاویر — کنارِ هم */
        .file-thumb {
            position: relative;
            width: 74px;
            height: 74px;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid #e5e7eb;
            flex-shrink: 0;
        }
        .file-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .file-thumb .thumb-remove {
            position: absolute;
            top: 3px;
            left: 3px;
            width: 19px;
            height: 19px;
            border-radius: 50%;
            background: rgba(0,0,0,.55);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .68rem;
            cursor: pointer;
        }
        .file-thumb .thumb-remove:hover { background: rgba(220,38,38,.85); }

        /* ───── دکمه‌ها ───── */
        .btn-submit-ticket {
            background: linear-gradient(135deg, #744ca4, #9b6dd7);
            color: #fff;
            border: none;
            padding: 10px 28px;
            border-radius: 10px;
            font-weight: 600;
            font-size: .88rem;
            font-family: inherit;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: opacity .2s;
        }
        .btn-submit-ticket:hover { opacity: .88; }
        .btn-submit-ticket:disabled { opacity: .45; cursor: default; }

        .btn-back-ticket {
            background: #f1f3f5;
            color: #495057;
            border: 1px solid #dee2e6;
            padding: 10px 20px;
            border-radius: 10px;
            font-size: .86rem;
            font-family: inherit;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            text-decoration: none;
            transition: background .15s;
        }
        .btn-back-ticket:hover { background: #e9ecef; color: #333; }

        /* ───── فیلدهای کنار هم ───── */
        .fields-row {
            display: flex;
            gap: 16px;
        }
        .fields-row > div { flex: 1; }

        @media (max-width: 992px) {
            .ctkt-head { flex-direction: column; align-items: flex-start; gap: 10px; }
        }
        @media (max-width: 576px) {
            .overview-container { margin-top: 70px; }
            .form-card { padding: 20px 18px 24px; }
            .fields-row { flex-direction: column; gap: 0; }
        }

        /* ───── جستجوی تسک برای پیوست ───── */
        .task-search-row {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .task-search-row #linkedTaskWrap:empty { display: none; }
        .task-search-box { position: relative; flex: 1; min-width: 0; }
        .task-search-results {
            position: absolute;
            top: calc(100% + 4px);
            right: 0;
            left: 0;
            z-index: 20;
            max-height: 260px;
            overflow-y: auto;
            background: #fff;
            border: 1.5px solid #e5e7eb;
            border-radius: 10px;
            box-shadow: 0 8px 24px rgba(0,0,0,.1);
            display: none;
        }
        .task-search-results.show { display: block; }
        .task-search-item {
            padding: 9px 14px;
            font-size: .84rem;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .task-search-item:last-child { border-bottom: none; }
        .task-search-item:hover { background: #f8f5ff; }
        .task-search-item i { color: #744ca4; }
        .task-search-empty {
            padding: 12px 14px;
            font-size: .82rem;
            color: #999;
            text-align: center;
        }
        .linked-task-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #f0e6ff;
            border: 1px solid #e0d0f5;
            border-radius: 10px;
            padding: 7px 12px;
            font-size: .84rem;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .linked-task-chip a {
            color: #744ca4;
            font-weight: 600;
            text-decoration: none;
        }
        .linked-task-chip a:hover { text-decoration: underline; }
        .linked-task-chip .remove-linked-task {
            cursor: pointer;
            color: #ef4444;
            font-weight: 700;
            margin-right: auto;
        }

        /* ───── تم تاریک ───── */
        :root[data-theme="dark"] .ctkt-head-card {
            background: var(--surface);
            border-color: var(--border-soft);
        }
        :root[data-theme="dark"] .ctkt-head-text h1 { color: var(--text-strong); }
        :root[data-theme="dark"] .ctkt-head-text p { color: var(--text-muted); }
        :root[data-theme="dark"] .form-card {
            background: var(--surface);
            border-color: var(--border-soft);
        }
        :root[data-theme="dark"] .form-card .form-label { color: var(--text-strong); }
        :root[data-theme="dark"] .form-card .form-control,
        :root[data-theme="dark"] .form-card .form-select,
        :root[data-theme="dark"] .task-search-results {
            background: var(--info-box-bg);
            border-color: var(--border-soft);
            color: var(--text-strong);
        }
        :root[data-theme="dark"] .upload-zone {
            background: var(--info-box-bg);
            border-color: var(--border-soft);
        }
        :root[data-theme="dark"] .upload-zone:hover,
        :root[data-theme="dark"] .upload-zone.dragover {
            background: rgba(116,76,164,.12);
            border-color: var(--icon-accent);
        }
        :root[data-theme="dark"] .upload-zone p { color: var(--text-muted); }
        :root[data-theme="dark"] .file-chip {
            background: #232a3a;
            color: var(--text-strong);
        }
        :root[data-theme="dark"] .file-thumb {
            border-color: var(--border-soft);
        }
        :root[data-theme="dark"] .btn-back-ticket {
            background: var(--info-box-bg);
            color: var(--text-strong);
            border-color: var(--border-soft);
        }
        :root[data-theme="dark"] .btn-back-ticket:hover { background: #2b3242; }
        :root[data-theme="dark"] .task-search-item {
            border-bottom-color: var(--border-soft);
        }
        :root[data-theme="dark"] .task-search-item:hover { background: #232a3a; }
        :root[data-theme="dark"] .task-search-empty { color: var(--text-muted); }
        :root[data-theme="dark"] .linked-task-chip {
            background: rgba(116,76,164,.18);
            border-color: rgba(116,76,164,.4);
        }
        :root[data-theme="dark"] .linked-task-chip a { color: var(--icon-accent); }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="overview-container">

        <div class="ctkt-head-card">
            <div class="ctkt-head">
                <div class="ctkt-head-text">
                    <h1><i class="bi bi-plus-circle"></i> ثبت تیکت جدید</h1>
                    <p>مشکل یا درخواست خود را ثبت کنید</p>
                </div>
                <a href="tickets.php" class="btn-back-ticket">
                    <i class="bi bi-arrow-right"></i>بازگشت به لیست
                </a>
            </div>
        </div>

        <div class="form-card">

                    <!-- عنوان -->
                    <div class="mb-3">
                        <label class="form-label">عنوان تیکت <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="ticketSubject" maxlength="255"
                               placeholder="مشکل خود را به طور خلاصه بنویسید...">
                    </div>

                    <!-- متن پیام -->
                    <div class="mb-3">
                        <label class="form-label">شرح مشکل <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="ticketMessage"
                                  placeholder="جزئیات مشکل یا درخواست خود را بنویسید..."></textarea>
                    </div>

                    <!-- اولویت + دسته‌بندی در یک خط -->
                    <div class="fields-row mb-3">
                        <div>
                            <label class="form-label">اولویت</label>
                            <select class="form-select" id="ticketPriority">
                                <option value="1">کم</option>
                                <option value="2" selected>متوسط</option>
                                <option value="3">بالا</option>
                                <?php if ($canSetCriticalPriority): ?>
                                <option value="4">بحرانی</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">دسته‌بندی</label>
                            <select class="form-select" id="ticketCategory">
                                <option value="">انتخاب کنید...</option>
                            </select>
                        </div>
                    </div>

                    <!-- تسکِ مرتبط (اختیاری) -->
                    <div class="mb-3">
                        <label class="form-label">تسکِ مرتبط <small class="text-muted">(اختیاری — برای نمایش به پشتیبانی)</small></label>
                        <div class="task-search-row">
                            <div class="task-search-box">
                                <input type="text" class="form-control" id="taskSearchInput"
                                       placeholder="جستجو بر اساس عنوان یا شناسه تسک..." autocomplete="off">
                                <div class="task-search-results" id="taskSearchResults"></div>
                            </div>
                            <div id="linkedTaskWrap"></div>
                        </div>
                    </div>

                    <!-- آپلود فایل -->
                    <div class="mb-4">
                        <label class="form-label">فایل پیوست <small class="text-muted">(اختیاری — Ctrl+V هم برای چسباندنِ عکس کار می‌کند)</small></label>
                        <div class="upload-zone" id="uploadZone">
                            <input type="file" id="fileInput" multiple style="display:none"
                                   accept=".jpg,.jpeg,.png,.pdf,.docx,.doc,.xls,.xlsx,.mp3,.m4a,.ogg">
                            <i class="bi bi-cloud-upload cloud-icon"></i>
                            <p><strong>کلیک کنید یا فایل بکشید</strong><br>
                            <small class="text-muted">حداکثر 20MB — فرمت: jpg, png, pdf, docx, xlsx, mp3</small></p>
                        </div>
                        <div class="file-preview" id="filePreview"></div>
                    </div>

                    <!-- دکمه ارسال -->
                    <div class="d-flex gap-2 justify-content-end">
                        <button class="btn-submit-ticket" id="submitBtn" onclick="submitTicket()">
                            <i class="bi bi-send"></i>
                            <span class="btn-text">ارسال تیکت</span>
                        </button>
                    </div>

                </div>

    </div>

    <script src="<?= asset('../assets/js/alert.js') ?>"></script>
    <script>
        var selectedFiles = [];

        document.addEventListener('DOMContentLoaded', function() {
            authToken = localStorage.getItem('auth_token');

            if (!authToken) {
                window.location.href = '../index.php';
                return;
            }

            loadCategories();
            initUpload();
            initTaskSearch();
            initPasteUpload();
        });

        // ─── بارگذاری دسته‌بندی‌ها ───
        async function loadCategories() {
            try {
                var res = await fetch('../api/tickets/categories.php', {
                    headers: { 'Authorization': 'Bearer ' + authToken }
                });
                var data = await res.json();
                console.log('categories response:', data);

                // ساپورت هر دو فرمت: {success, categories:[...]} یا مستقیم [...]
                var cats = [];
                if (Array.isArray(data)) {
                    cats = data;
                } else if (data.success && data.categories) {
                    cats = data.categories;
                } else if (data.data && Array.isArray(data.data)) {
                    cats = data.data;
                } else if (data.categories) {
                    cats = data.categories;
                }

                var sel = document.getElementById('ticketCategory');
                cats.forEach(function(c) {
                    // اگر is_active وجود نداره یا فعاله، اضافه کن
                    if (c.is_active === undefined || c.is_active == 1 || c.is_active === true) {
                        var opt = document.createElement('option');
                        opt.value = c.id;
                        opt.textContent = c.name;
                        sel.appendChild(opt);
                    }
                });
            } catch(e) { console.error('loadCategories:', e); }
        }

        // ─── مدیریت آپلود ───
        function initUpload() {
            var zone  = document.getElementById('uploadZone');
            var input = document.getElementById('fileInput');

            zone.addEventListener('click', function() { input.click(); });

            zone.addEventListener('dragover', function(e) {
                e.preventDefault();
                zone.classList.add('dragover');
            });
            zone.addEventListener('dragleave', function(e) {
                e.preventDefault();
                zone.classList.remove('dragover');
            });
            zone.addEventListener('drop', function(e) {
                e.preventDefault();
                zone.classList.remove('dragover');
                addFiles(e.dataTransfer.files);
            });

            input.addEventListener('change', function() {
                addFiles(input.files);
                input.value = '';
            });
        }

        function addFiles(fileList) {
            var maxSize = 20 * 1024 * 1024;
            for (var i = 0; i < fileList.length; i++) {
                var f = fileList[i];
                if (f.size > maxSize) {
                    showToast('فایل «' + f.name + '» بیش از 20MB است', 'warning');
                    continue;
                }
                var dup = selectedFiles.some(function(s) { return s.name === f.name && s.size === f.size; });
                if (!dup) selectedFiles.push(f);
            }
            renderFilePreview();
        }

        var filePreviewUrls = []; // object URLهای ساخته‌شده برای پیش‌نمایش — برای جلوگیری از نشتِ حافظه آزاد می‌شوند

        function renderFilePreview() {
            var el = document.getElementById('filePreview');

            // آزادسازیِ URLهای پیش‌نمایشِ قبلی قبل از بازسازی
            filePreviewUrls.forEach(function(u) { URL.revokeObjectURL(u); });
            filePreviewUrls = [];

            if (selectedFiles.length === 0) { el.innerHTML = ''; return; }

            var html = '';
            selectedFiles.forEach(function(f, idx) {
                var isImage = f.type && f.type.indexOf('image/') === 0;
                if (isImage) {
                    var url = URL.createObjectURL(f);
                    filePreviewUrls.push(url);
                    html += '<div class="file-thumb" title="' + escHtml(f.name) + '">';
                    html += '<img src="' + url + '" alt="' + escHtml(f.name) + '">';
                    html += '<span class="thumb-remove" onclick="removeFile(' + idx + ')">✕</span>';
                    html += '</div>';
                } else {
                    var kb = (f.size / 1024).toFixed(0);
                    html += '<div class="file-chip">';
                    html += '<i class="bi bi-paperclip"></i>';
                    html += '<span>' + escHtml(f.name) + ' (' + kb + ' KB)</span>';
                    html += '<span class="remove-file" onclick="removeFile(' + idx + ')">✕</span>';
                    html += '</div>';
                }
            });
            el.innerHTML = html;
        }

        function removeFile(idx) {
            selectedFiles.splice(idx, 1);
            renderFilePreview();
        }

        // ─── چسباندنِ تصویر از کلیپ‌بورد با Ctrl+V ───
        function initPasteUpload() {
            document.addEventListener('paste', function(e) {
                var items = (e.clipboardData || window.clipboardData).items;
                if (!items) return;
                var pastedFiles = [];
                for (var i = 0; i < items.length; i++) {
                    if (items[i].type.indexOf('image/') === 0) {
                        var file = items[i].getAsFile();
                        if (file) {
                            // فایل‌های کلیپ‌بورد معمولاً نام ندارند
                            var ext = (file.type.split('/')[1] || 'png').replace('jpeg', 'jpg');
                            var namedFile = new File([file], 'clipboard-' + Date.now() + '.' + ext, { type: file.type });
                            pastedFiles.push(namedFile);
                        }
                    }
                }
                if (pastedFiles.length > 0) {
                    e.preventDefault();
                    addFiles(pastedFiles);
                    showToast('تصویر از کلیپ‌بورد اضافه شد', 'success');
                }
            });
        }

        // ─── جستجوی تسک برای پیوست ───
        var linkedTaskId = null;
        var taskSearchTimer = null;

        function initTaskSearch() {
            var input = document.getElementById('taskSearchInput');
            var results = document.getElementById('taskSearchResults');

            input.addEventListener('input', function() {
                clearTimeout(taskSearchTimer);
                var q = input.value.trim();
                if (!q) { results.classList.remove('show'); results.innerHTML = ''; return; }
                taskSearchTimer = setTimeout(function() { runTaskSearch(q); }, 350);
            });

            document.addEventListener('click', function(e) {
                if (!e.target.closest('.task-search-box')) {
                    results.classList.remove('show');
                }
            });
        }

        async function runTaskSearch(q) {
            var results = document.getElementById('taskSearchResults');
            try {
                var res = await fetch('../api/tasks/quick-search.php?q=' + encodeURIComponent(q), {
                    headers: { 'Authorization': 'Bearer ' + authToken }
                });
                var data = await res.json();
                var tasks = (data.success && data.tasks) ? data.tasks : [];

                if (tasks.length === 0) {
                    results.innerHTML = '<div class="task-search-empty">تسکی یافت نشد</div>';
                } else {
                    results.innerHTML = tasks.map(function(t) {
                        return '<div class="task-search-item" onclick="selectLinkedTask(' + t.id + ', \'' +
                            escHtml(t.title).replace(/'/g, '&#39;') + '\')">' +
                            '<i class="bi bi-card-checklist"></i><span>' + escHtml(t.title) + '</span>' +
                            '<small class="text-muted" style="margin-right:auto;">#' + t.id + '</small>' +
                            '</div>';
                    }).join('');
                }
                results.classList.add('show');
            } catch (e) {
                console.error('runTaskSearch:', e);
            }
        }

        function selectLinkedTask(id, title) {
            linkedTaskId = id;
            document.getElementById('taskSearchResults').classList.remove('show');
            document.getElementById('taskSearchInput').value = '';
            renderLinkedTask(title);
        }

        function renderLinkedTask(title) {
            var wrap = document.getElementById('linkedTaskWrap');
            if (!linkedTaskId) { wrap.innerHTML = ''; return; }
            wrap.innerHTML =
                '<div class="linked-task-chip">' +
                    '<i class="bi bi-link-45deg"></i>' +
                    '<span>' + escHtml(title) + '</span>' +
                    '<span class="remove-linked-task" onclick="removeLinkedTask()">✕</span>' +
                '</div>';
        }

        function removeLinkedTask() {
            linkedTaskId = null;
            document.getElementById('linkedTaskWrap').innerHTML = '';
        }

        // ─── ارسال تیکت ───
        async function submitTicket() {
            var subject  = document.getElementById('ticketSubject').value.trim();
            var message  = document.getElementById('ticketMessage').value.trim();
            var priority = document.getElementById('ticketPriority').value;
            var category = document.getElementById('ticketCategory').value;

            if (!subject) {
                showToast('عنوان تیکت الزامی است', 'warning');
                document.getElementById('ticketSubject').focus();
                return;
            }
            if (!message) {
                showToast('شرح مشکل الزامی است', 'warning');
                document.getElementById('ticketMessage').focus();
                return;
            }

            var btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.querySelector('.btn-text').textContent = 'در حال ارسال...';

            try {
                var fd = new FormData();
                fd.append('subject', subject);
                fd.append('message', message);
                fd.append('priority_id', priority);
                if (category) fd.append('category_id', category);
                if (linkedTaskId) fd.append('task_id', linkedTaskId);
                selectedFiles.forEach(function(f) { fd.append('attachments[]', f); });

                var res = await fetch('../api/tickets/create.php', {
                    method: 'POST',
                    headers: { 'Authorization': 'Bearer ' + authToken },
                    body: fd
                });
                var data = await res.json();

                if (data.success) {
                    showToast('تیکت با موفقیت ثبت شد — شماره: ' + data.ticket_number, 'success');
                    setTimeout(function() {
                        window.location.href = 'ticket-detail.php?id=' + data.ticket_id;
                    }, 1300);
                } else {
                    showToast(data.message || 'خطا در ثبت تیکت', 'error');
                    btn.disabled = false;
                    btn.querySelector('.btn-text').textContent = 'ارسال تیکت';
                }
            } catch(e) {
                console.error('submitTicket:', e);
                showToast('خطا در ارتباط با سرور', 'error');
                btn.disabled = false;
                btn.querySelector('.btn-text').textContent = 'ارسال تیکت';
            }
        }

        function escHtml(str) {
            if (!str) return '';
            var d = document.createElement('div');
            d.textContent = str;
            return d.innerHTML;
        }
    </script>
    <?php include 'footer.php'; ?>
</body>
</html>