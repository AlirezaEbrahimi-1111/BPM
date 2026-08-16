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

$__me = loadUserForPermissions($db, (int) $user_id);
if (!$__me || !hasPermission($__me, 'view_org_settings')) {
    header('Location: dashboard-manager.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>گزارش پیامک‌ها</title>
    <link href="<?= asset('../assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">
    <link href="<?= asset('../assets/js/cdn/fonts/bootstrap-icons.woff2?30af91bf14e37666a085fb8a161ff36d') ?>" rel="stylesheet">
    <script src="<?= asset('../assets/js/config.js') ?>"></script>
    
    <style>
        * { font-family: 'Vazir', sans-serif !important; }
        body {
            background: linear-gradient(135deg, #f5f7ff 0%, #fafbff 100%);
            padding-top: 80px;
            min-height: 100vh;
        }
        .main-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .stat-card h3 {
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
        }
        .stat-card p {
            margin: 0.5rem 0 0 0;
            color: #5f6368;
        }
        .card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .table { margin: 0; }
        .badge {
            padding: 6px 12px;
            border-radius: 8px;
        }
        .loading {
            text-align: center;
            padding: 3rem;
        }
    </style>
</head>
<body>
    <?php include '../pages/header.php'; ?>

    <div class="main-content">
        <h1 class="mb-4"><i class="bi bi-graph-up me-2"></i>گزارش پیامک‌ها</h1>

        <div class="stats-grid">
            <div class="stat-card">
                <h3 id="totalSMS">0</h3>
                <p>کل پیامک‌ها</p>
            </div>
            <div class="stat-card">
                <h3 id="sentSMS" style="color: #28a745;">0</h3>
                <p>ارسال شده</p>
            </div>
            <div class="stat-card">
                <h3 id="failedSMS" style="color: #dc3545;">0</h3>
                <p>ناموفق</p>
            </div>
            <div class="stat-card">
                <h3 id="pendingSMS" style="color: #ffc107;">0</h3>
                <p>در انتظار</p>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div id="logsContainer" class="loading">
                    <div class="spinner-border text-primary"></div>
                    <p class="mt-2">در حال بارگذاری...</p>
                </div>
            </div>
        </div>
    </div>

    <script src="<?= asset('../assets/js/cdn/bootstrap.bundle.min.js') ?>"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (!authToken) {
                window.location.href = '../index.php';
                return;
            }
            loadLogs();
        });

        async function loadLogs() {
            try {
                const response = await fetch('../api/sms/get-logs.php', {
                    headers: { 'Authorization': 'Bearer ' + authToken }
                });

                const data = await response.json();

                if (data.success) {
                    updateStats(data.stats);
                    renderLogs(data.logs || []);
                } else {
                    showError(data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                showError('خطا در بارگذاری');
            }
        }

        function updateStats(stats) {
            document.getElementById('totalSMS').textContent = toFa(stats.total || 0);
            document.getElementById('sentSMS').textContent = toFa(stats.sent || 0);
            document.getElementById('failedSMS').textContent = toFa(stats.failed || 0);
            document.getElementById('pendingSMS').textContent = toFa(stats.pending || 0);
        }

        function renderLogs(logs) {
            const container = document.getElementById('logsContainer');

            if (logs.length === 0) {
                container.innerHTML = '<div class="text-center text-muted p-5">هیچ لاگی وجود ندارد</div>';
                return;
            }

            let html = `
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>شماره</th>
                            <th>متن</th>
                            <th>الگو</th>
                            <th>وضعیت</th>
                            <th>خطا</th>
                            <th>تاریخ</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            logs.forEach(log => {
                const statusClass = log.status === 'sent' ? 'success' : log.status === 'failed' ? 'danger' : 'warning';
                const statusText = log.status === 'sent' ? 'ارسال شد' : log.status === 'failed' ? 'ناموفق' : 'در انتظار';

                html += `
                    <tr>
                        <td>${esc(log.phone)}</td>
                        <td style="max-width: 300px;">${esc(log.message)}</td>
                        <td>${esc(log.template_name) || '-'}</td>
                        <td><span class="badge bg-${statusClass}">${statusText}</span></td>
                        <td style="color: #dc3545; font-size: 12px;">${esc(log.error_message) || '-'}</td>
                        <td>${log.created_at}</td>
                    </tr>
                `;
            });

            html += '</tbody></table>';
            container.innerHTML = html;
        }

        // showError از showInlineError مشترک (assets/js/alert.js) استفاده می‌کنه
        function showError(message) {
            showInlineError('logsContainer', message, { onRetry: loadLogs });
        }
    </script>
    <?php include 'footer.php'; ?>
</body>
</html>