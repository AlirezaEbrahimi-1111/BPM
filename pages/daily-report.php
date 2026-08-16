<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
require_once '../includes/version.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) $user_id = $auth->getUserFromToken();
if (!$user_id && isset($_COOKIE['auth_token'])) $user_id = $auth->validateToken($_COOKIE['auth_token']);

if (!$user_id) {
    header('Location: ../index.php');
    exit;
}

// گزارشِ روزانهٔ خودِ کاربره — عمومیه، نه فقط برایِ مدیران
$__me = loadUserForPermissions($db, (int) $user_id);
if (!$__me) {
    header('Location: ../index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="گزارش روزانه عملکرد - سیستم مدیریت کار">
    <meta name="robots" content="noindex, nofollow">
    <title>گزارش روزانه - سیستم مدیریت کار</title>

    <link href="<?= asset('../assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">
    <link href="<?= asset('../assets/js/cdn/fonts/bootstrap-icons.woff2') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../../assets/css/custom.css') ?>">
</head>

<body>
    <?php include 'header.php'; ?>

    <main class="container-fluid main-content">
        <!-- نشانگر مراحل -->
        <div class="steps-section">
            <div class="steps-indicator">
                <div class="step active" id="step1">
                    <div class="step-circle">۱</div>
                    <div class="step-label">بررسی فعالیت‌ها</div>
                </div>
                <div class="step-divider"></div>
                <div class="step" id="step2">
                    <div class="step-circle">۲</div>
                    <div class="step-label">ویرایش گزارش</div>
                </div>
                <div class="step-divider"></div>
                <div class="step" id="step3">
                    <div class="step-circle">۳</div>
                    <div class="step-label">ارسال</div>
                </div>
            </div>
        </div>

        <!-- مرحله 1: فعالیت‌ها -->
        <section id="activitiesSection">
            <!-- آمار -->
            <div class="stats-grid" id="statsGrid">
                <div class="stat-card total">
                    <h3 class="stat-number">-</h3>
                    <p class="stat-label">کل فعالیت‌ها</p>
                </div>
                <div class="stat-card completed">
                    <h3 class="stat-number">-</h3>
                    <p class="stat-label">انجام شده</p>
                </div>
                <div class="stat-card approved">
                    <h3 class="stat-number">-</h3>
                    <p class="stat-label">تأیید شده</p>
                </div>
                <div class="stat-card created">
                    <h3 class="stat-number">-</h3>
                    <p class="stat-label">ایجاد شده</p>
                </div>
                <div class="stat-card delegated">
                    <h3 class="stat-number">-</h3>
                    <p class="stat-label">ارجاع شده</p>
                </div>
                <div class="stat-card overdue">
                    <h3 class="stat-number">-</h3>
                    <p class="stat-label">معوقه</p>
                </div>
            </div>

            <!-- لیست فعالیت‌ها -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="bi bi-activity me-2"></i>فعالیت‌های امروز</h5>
                    <span class="badge bg-light text-dark" id="activityDate"></span>
                </div>
                <div class="card-body">
                    <div id="activitiesContainer">
                        <div class="loading">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">در حال بارگذاری...</span>
                            </div>
                            <p class="mt-3">در حال بارگذاری فعالیت‌ها...</p>
                        </div>
                    </div>

                    <div class="d-flex gap-2 justify-content-center mt-4">
                        <button class="btn btn-primary" onclick="generateReport()">
                            <i class="bi bi-file-earmark-text me-2"></i>تولید گزارش
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <!-- مرحله 2: ویرایش گزارش -->
        <section id="reportSection" style="display: none;">
            <div class="card">
                <div class="card-header">
                    <h5><i class="bi bi-pencil-square me-2"></i>ویرایش و ارسال گزارش</h5>
                </div>
                <div class="card-body">
                    <textarea id="reportContent" class="report-textarea"
                              placeholder="گزارش شما..."
                              oninput="updateWordCount()"></textarea>
                    <div class="word-count" id="wordCount">۰ کلمه</div>

                    <div class="d-flex gap-3 justify-content-center mt-4">
                        <button class="btn btn-success" onclick="submitReport()">
                            <i class="bi bi-send me-2"></i>ارسال گزارش
                        </button>
                        <button class="btn btn-outline-secondary" onclick="goBackToActivities()">
                            <i class="bi bi-arrow-right me-2"></i>بازگشت
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <!-- مرحله 3: موفقیت -->
        <section id="successSection" style="display: none;">
            <div class="card">
                <div class="card-body success-section">
                    <div class="success-icon">
                        <i class="bi bi-check-lg"></i>
                    </div>
                    <h4 class="text-success mb-3">گزارش با موفقیت ارسال شد!</h4>
                    <div class="report-code" id="generatedCode"></div>

                    <div class="d-flex gap-2 justify-content-center mt-4 flex-wrap">
                        <button class="btn btn-outline-primary" onclick="copyReportToClipboard()">
                            <i class="bi bi-clipboard me-2"></i>کپی گزارش
                        </button>
                        <button class="btn btn-outline-success" onclick="shareToTelegram()">
                            <i class="bi bi-telegram me-2"></i>ارسال به تلگرام
                        </button>
                        <button class="btn btn-primary" onclick="startNewReport()">
                            <i class="bi bi-plus-lg me-2"></i>گزارش جدید
                        </button>
                    </div>
                </div>
            </div>
        </section>
    </main>
    <?php include 'footer.php'; ?>

    <!-- FAB -->
    <div class="quick-actions">
        <a href="reports.php" class="fab" title="گزارش‌های قبلی">
            <i class="bi bi-file-text"></i>
        </a>
    </div>

    <script src="<?= asset('../assets/js/cdn/bootstrap.bundle.min.js') ?>"></script>
    <script src="<?= asset('../assets/js/config.js') ?>"></script>

    <script>
        // متغیرهای سراسری
        let currentUser = JSON.parse(localStorage.getItem('user_info') || '{}');
        let todayActivities = [];
        let groupedActivities = {};
        let summary = {};
        let overdueTasks = [];
        let reportContent = '';
        let finalReportCode = '';

        // شروع
        document.addEventListener('DOMContentLoaded', () => {
            if (!authToken) {
                window.location.href = '../index.php';
                return;
            }
            loadTodayActivities();
        });

        // بارگذاری فعالیت‌ها
        async function loadTodayActivities() {
            document.getElementById('activityDate').textContent = getCurrentPersianDate();

            try {
                const response = await fetch(`../api/reports/get-today-activities.php?date=${getCurrentDate()}`, {
                    headers: { 'Authorization': 'Bearer ' + authToken }
                });
                const data = await response.json();

                if (data.success) {
                    todayActivities = data.data.activities || [];
                    groupedActivities = data.data.grouped_activities || {};
                    summary = data.data.summary || {};
                    overdueTasks = data.data.overdue_tasks || [];

                    if (data.data.user) {
                        currentUser = { ...currentUser, ...data.data.user };
                    }

                    renderStats();
                    renderActivities();
                } else {
                    showError(data.message || 'خطا در بارگذاری');
                }
            } catch (error) {
                console.error('Error:', error);
                showError('خطا در ارتباط با سرور');
            }
        }

        // نمایش آمار
        function renderStats() {
            const overdueCount = overdueTasks.length || 0;
            document.getElementById('statsGrid').innerHTML = `
                <div class="stat-card total">
                    <h3 class="stat-number">${toFa(summary.total_activities || 0)}</h3>
                    <p class="stat-label">کل فعالیت‌ها</p>
                </div>
                <div class="stat-card completed">
                    <h3 class="stat-number">${toFa(summary.tasks_completed || 0)}</h3>
                    <p class="stat-label">انجام شده</p>
                </div>
                <div class="stat-card approved">
                    <h3 class="stat-number">${toFa(summary.tasks_approved || 0)}</h3>
                    <p class="stat-label">تأیید شده</p>
                </div>
                <div class="stat-card created">
                    <h3 class="stat-number">${toFa(summary.tasks_created || 0)}</h3>
                    <p class="stat-label">ایجاد شده</p>
                </div>
                <div class="stat-card delegated">
                    <h3 class="stat-number">${toFa(summary.tasks_delegated || 0)}</h3>
                    <p class="stat-label">ارجاع شده</p>
                </div>
                <div class="stat-card overdue ${overdueCount > 0 ? 'bg-danger text-white' : ''}">
                    <h3 class="stat-number">${toFa(overdueCount)}</h3>
                    <p class="stat-label">${overdueCount > 0 ? '⚠️ معوقه' : 'معوقه'}</p>
                </div>
            `;
        }

        // نمایش فعالیت‌ها
        function renderActivities() {
            const container = document.getElementById('activitiesContainer');

            if (todayActivities.length === 0 && overdueTasks.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="bi bi-inbox"></i>
                        <h5>فعالیتی ثبت نشده</h5>
                        <p class="text-muted">امروز هنوز فعالیتی در سیستم ثبت نکرده‌اید.</p>
                    </div>
                `;
                return;
            }

            let html = '';

            // کارهای معوقه
            if (overdueTasks.length > 0) {
                html += `
                    <div class="activity-section">
                        <div class="activity-header">
                            <i class="bi bi-exclamation-triangle-fill text-danger"></i>
                            <h6 class="text-danger">کارهای معوقه</h6>
                            <span class="badge bg-danger">${toFa(overdueTasks.length)}</span>
                        </div>
                `;
                overdueTasks.forEach(task => {
                    const days = task.days_overdue || 0;
                    const icon = task.priority === 'high' ? '🔴' : (task.priority === 'medium' ? '🟡' : '🟢');
                    html += `
                        <div class="activity-item overdue">
                            <div class="activity-title">${esc(task.title)}</div>
                            <div class="activity-meta">
                                ${icon} ${toFa(days)} روز تأخیر
                                ${task.creator_name?.trim() ? ` │ از: ${esc(task.creator_name.trim())}` : ''}
                            </div>
                        </div>
                    `;
                });
                html += '</div>';
            }

            // بخش‌های فعالیت
            const sections = [
                { key: 'completed', icon: 'bi-check-circle-fill text-success', title: 'کارهای انجام شده' },
                { key: 'approved', icon: 'bi-patch-check-fill text-success', title: 'کارهای تأیید شده' },
                { key: 'rejected', icon: 'bi-x-circle-fill text-danger', title: 'کارهای رد شده' },
                { key: 'created', icon: 'bi-plus-circle-fill text-info', title: 'کارهای ایجاد شده' },
                { key: 'assigned', icon: 'bi-person-plus-fill text-info', title: 'کارهای واگذار شده' },
                { key: 'delegated', icon: 'bi-box-arrow-up-left text-purple', title: 'کارهای ارجاع شده' },
                { key: 'pending_approval', icon: 'bi-hourglass-split text-warning', title: 'در انتظار تأیید' },
                { key: 'updated', icon: 'bi-pencil-fill text-warning', title: 'توضیحات ثبت شده' }
            ];

            sections.forEach(section => {
                const items = groupedActivities[section.key] || [];
                if (items.length === 0) return;

                html += `
                    <div class="activity-section">
                        <div class="activity-header">
                            <i class="bi ${section.icon}"></i>
                            <h6>${section.title}</h6>
                            <span class="badge bg-secondary">${toFa(items.length)}</span>
                        </div>
                `;

                items.forEach(item => {
                    const time = getTime(item.created_at);
                    const notes = extractNotes(item.notes);
                    html += `
                        <div class="activity-item ${section.key}">
                            <div class="activity-title">${esc(item.task_title) || 'بدون عنوان'}</div>
                            <div class="activity-meta">
                                <i class="bi bi-clock me-1"></i>${toFa(time)}
                                ${item.to_user_name?.trim() ? ` │ <i class="bi bi-person me-1"></i>${esc(item.to_user_name.trim())}` : ''}
                            </div>
                            ${notes ? `<div class="activity-notes">${esc(notes)}</div>` : ''}
                        </div>
                    `;
                });

                html += '</div>';
            });

            container.innerHTML = html;
        }

        // تولید گزارش
        function generateReport() {
            updateStep(2);
            document.getElementById('activitiesSection').style.display = 'none';
            document.getElementById('reportSection').style.display = 'block';

            const today = getPersianDateFormatted();
            const fromName = currentUser.official_code || `${currentUser.first_name || ''} ${currentUser.last_name || ''}`.trim();
            const toName = currentUser.manager_code || `${currentUser.manager_name || ''} ${currentUser.manager_lastname || ''}`.trim() || 'مدیر';

            let content = '';

            if (currentUser.report_prefix) content += currentUser.report_prefix + '\n\n';

            content += `📊 گزارش روزانه عملکرد\n`;
            content += `📅 تاریخ: ${today}\n`;
            content += `👤 از: ${fromName}\n`;
            content += `👤 به: ${toName}\n`;
            content += `━━━━━━━━━\n\n`;

            if (todayActivities.length === 0 && overdueTasks.length === 0) {
                content += '📭 امروز فعالیتی در سیستم ثبت نشده است.\n';
            } else {
                // خلاصه
                content += `📈 خلاصه عملکرد امروز:\n`;
                content += `┌━━━━━━━━━\n`;
                content += `│ 📊 کل فعالیت‌ها: ${toFa(summary.total_activities || 0)} مورد\n`;
                if (summary.tasks_completed > 0) content += `│ ✅ انجام شده: ${toFa(summary.tasks_completed)} مورد\n`;
                if (summary.tasks_approved > 0) content += `│ ✔️ تأیید شده: ${toFa(summary.tasks_approved)} مورد\n`;
                if (summary.tasks_rejected > 0) content += `│ ❌ رد شده: ${toFa(summary.tasks_rejected)} مورد\n`;
                if (summary.tasks_created > 0) content += `│ ➕ ایجاد شده: ${toFa(summary.tasks_created)} مورد\n`;
                if (summary.tasks_assigned > 0) content += `│ 👤 واگذار شده: ${toFa(summary.tasks_assigned)} مورد\n`;
                if (summary.tasks_delegated > 0) content += `│ ↗️ ارجاع شده: ${toFa(summary.tasks_delegated)} مورد\n`;
                if (summary.notes_added > 0) content += `│ 📝 توضیحات: ${toFa(summary.notes_added)} مورد\n`;
                if (summary.sent_for_approval > 0) content += `│ ⏳ در انتظار تأیید: ${toFa(summary.sent_for_approval)} مورد\n`;
                if (overdueTasks.length > 0) content += `│ ⚠️ کارهای معوقه: ${toFa(overdueTasks.length)} مورد\n`;
                content += `└━━━━━━━━━\n\n`;

                // معوقه
                if (overdueTasks.length > 0) {
                    content += `⚠️ کارهای معوقه (${toFa(overdueTasks.length)} مورد):\n`;
                    overdueTasks.forEach((task, i) => {
                        const icon = task.priority === 'high' ? '🔴' : (task.priority === 'medium' ? '🟡' : '🟢');
                        content += `   ${toFa(i + 1)}. ${task.title} - ${icon} ${toFa(task.days_overdue || 0)} روز تأخیر\n`;
                    });
                    content += '\n';
                }

                // جزئیات
                if (todayActivities.length > 0) {
                    content += `📋 جزئیات فعالیت‌ها:\n━━━━━━━━━\n`;
                    const sections = [
                        { key: 'completed', icon: '✅', title: 'کارهای انجام شده' },
                        { key: 'approved', icon: '✔️', title: 'کارهای تأیید شده' },
                        { key: 'rejected', icon: '❌', title: 'کارهای رد شده' },
                        { key: 'created', icon: '➕', title: 'کارهای ایجاد شده' },
                        { key: 'assigned', icon: '👤', title: 'کارهای واگذار شده' },
                        { key: 'delegated', icon: '↗️', title: 'کارهای ارجاع شده' },
                        { key: 'pending_approval', icon: '⏳', title: 'در انتظار تأیید' },
                        { key: 'updated', icon: '📝', title: 'توضیحات ثبت شده' }
                    ];

                    sections.forEach(section => {
                        const items = groupedActivities[section.key] || [];
                        if (items.length === 0) return;
                        content += `${section.icon} ${section.title} (${toFa(items.length)} مورد):\n`;
                        items.forEach((item, i) => {
                            content += `   ${toFa(i + 1)}. ${item.task_title || 'بدون عنوان'}\n`;
                            content += `      ⏰ ساعت: ${toFa(getTime(item.created_at))}`;
                            if (item.to_user_name?.trim()) content += ` │ 👤 ${item.to_user_name.trim()}`;
                            content += '\n';
                            const notes = extractNotes(item.notes);
                            if (notes) content += `      💬 ${notes}\n`;
                        });
                        content += '\n';
                    });
                }
            }

            if (currentUser.report_suffix) {
                content += `━━━━━━━━━\n${currentUser.report_suffix}`;
            }

            reportContent = content;
            document.getElementById('reportContent').value = content;
            updateWordCount();
        }

        // ارسال گزارش
        async function submitReport() {
            const content = document.getElementById('reportContent').value.trim();
            if (!content || content.length < 50) {
                showAlert('گزارش باید حداقل ۵۰ کاراکتر باشد', 'warning');
                return;
            }

            const btn = event.target; // فوراً همین‌جا گرفته می‌شه، چون uiConfirm ناهمگام (async) هست
            uiConfirm('آیا از ارسال گزارش اطمینان دارید؟', async function () {
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>در حال ارسال...';

            try {
                const response = await fetch('../api/reports/submit.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + authToken
                    },
                    body: JSON.stringify({
                        activity_unit: currentUser.activity_unit || 'RS',
                        content: content,
                        report_date: getCurrentDate(),
                        statistics: {
                            ...summary,
                            overdue_count: overdueTasks.length,
                            overdue_details: overdueTasks.map(t => ({id: t.id, title: t.title, days: t.days_overdue}))
                        }
                    })
                });

                const data = await response.json();
                if (data.success) {
                    finalReportCode = data.report_code;
                    reportContent = content;
                    updateStep(3);
                    document.getElementById('reportSection').style.display = 'none';
                    document.getElementById('successSection').style.display = 'block';
                    document.getElementById('generatedCode').textContent = `کد گزارش: ${finalReportCode}`;
                    showAlert('گزارش با موفقیت ارسال شد!', 'success');
                } else {
                    showAlert(data.message || 'خطا در ارسال', 'error');
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('خطا در ارتباط با سرور', 'error');
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
            });
        }

        // توابع کمکی
        function updateStep(n) {
            for (let i = 1; i <= 3; i++) {
                const el = document.getElementById('step' + i);
                el.classList.remove('active', 'completed');
                if (i < n) el.classList.add('completed');
                if (i === n) el.classList.add('active');
            }
        }

        function goBackToActivities() {
            updateStep(1);
            document.getElementById('reportSection').style.display = 'none';
            document.getElementById('activitiesSection').style.display = 'block';
        }

        function startNewReport() {
            updateStep(1);
            document.getElementById('successSection').style.display = 'none';
            document.getElementById('activitiesSection').style.display = 'block';
            loadTodayActivities();
        }

        function copyReportToClipboard() {
            navigator.clipboard.writeText(reportContent).then(() => {
                showAlert('گزارش در کلیپبورد کپی شد', 'success');
            }).catch(() => {
                const ta = document.createElement('textarea');
                ta.value = reportContent;
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
                showAlert('گزارش کپی شد', 'success');
            });
        }

        function shareToTelegram() {
            window.open(`https://t.me/share/url?text=${encodeURIComponent(reportContent)}`, '_blank');
        }

        function updateWordCount() {
            const count = document.getElementById('reportContent').value.trim().split(/\s+/).filter(w => w).length;
            document.getElementById('wordCount').textContent = `${toFa(count)} کلمه`;
        }

        function getCurrentDate() {
            return todayLocal();
        }

        function getCurrentPersianDate() {
            return new Intl.DateTimeFormat('fa-IR', {
                year: 'numeric', month: 'long', day: 'numeric', weekday: 'long'
            }).format(new Date());
        }

        function getPersianDateFormatted() {
            return new Intl.DateTimeFormat('fa-IR', {
                weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'
            }).format(new Date());
        }

        function getTime(dt) {
            if (!dt) return '';
            try {
                return new Date(dt).toLocaleTimeString('fa-IR', { hour: '2-digit', minute: '2-digit' });
            } catch { return ''; }
        }

        function extractNotes(notes) {
            if (!notes) return '';
            const skip = ['کار ایجاد شد', 'کار تأیید شد', 'کار رد شد', 'کار انجام شد'];
            const parts = notes.split(':');
            const text = parts.length > 1 ? parts.slice(1).join(':').trim() : notes.trim();
            return skip.includes(text) ? '' : text;
        }

        function toFa(n) {
            return String(n).replace(/[0-9]/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]);
        }

        // showAlert قبلاً یک پیاده‌سازیِ جداگانه (باکسِ alert بوت‌استرپ) داشت؛
        // الان فقط یک نام‌مستعارِ نازک برایِ showToastِ مشترکه (از assets/js/alert.js)
        function showAlert(msg, type = 'info') {
            showToast(msg, type);
        }

        // showError از showInlineError مشترک (assets/js/alert.js) استفاده می‌کنه
        function showError(msg) {
            showInlineError('activitiesContainer', msg, { onRetry: loadTodayActivities });
        }
    </script>
</body>
</html>