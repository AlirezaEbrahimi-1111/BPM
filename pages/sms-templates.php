<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php'; 
require_once '../includes/version.php';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت الگوهای پیامک</title>
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        .page-header {
            margin-bottom: 2rem;
            background: white;
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(116, 76, 164, 0.08);
        }
        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: #1a1a1a;
            margin: 0;
        }
        .card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 16px 16px 0 0 !important;
            padding: 1.5rem;
        }
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #e8eaed;
            padding: 10px 14px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 8px;
            padding: 10px 24px;
        }
        .btn-success {
            background: #28a745;
            border: none;
            border-radius: 8px;
        }
        .btn-danger {
            background: #dc3545;
            border: none;
            border-radius: 8px;
        }
        .table {
            margin: 0;
        }
        .badge {
            padding: 6px 12px;
            border-radius: 8px;
            font-weight: 500;
        }
        .loading {
            text-align: center;
            padding: 3rem;
        }
        .alert {
            border-radius: 8px;
            border: none;
        }
    </style>
</head>
<body>
    <?php include '../pages/header.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1><i class="bi bi-chat-square-text me-2"></i>مدیریت الگوهای پیامک</h1>
        </div>

        <div id="alertContainer"></div>

        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">الگوهای پیامکی</h5>
                    <button class="btn btn-light btn-sm" onclick="showTemplateModal()">
                        <i class="bi bi-plus-circle me-1"></i>الگوی جدید
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div id="templatesContainer" class="loading">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2">در حال بارگذاری...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal افزودن/ویرایش -->
    <div class="modal fade" id="templateModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">الگوی جدید</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="templateForm">
                        <input type="hidden" id="templateId">
                        
                        <div class="mb-3">
                            <label class="form-label">نام الگو (انگلیسی) *</label>
                            <input type="text" class="form-control" id="templateName" required>
                            <small class="text-muted">مثال: task_assigned</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">عنوان فارسی *</label>
                            <input type="text" class="form-control" id="templateTitle" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">متن پیامک *</label>
                            <textarea class="form-control" id="templateMessage" rows="5" required></textarea>
                            <small class="text-muted">متغیرها: {user_name}, {title}, {message}</small>
                        </div>

                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="templateActive" checked>
                            <label class="form-check-label" for="templateActive">فعال</label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="button" class="btn btn-primary" onclick="saveTemplate()">ذخیره</button>
                </div>
            </div>
        </div>
    </div>

    <script src="<?= asset('../assets/js/cdn/bootstrap.bundle.min.js') ?>"></script>
    <script>
        let templates = [];
        let editingId = null;

        document.addEventListener('DOMContentLoaded', function() {
            if (!authToken) {
                window.location.href = '../index.php';
                return;
            }
            loadTemplates();
        });

        async function loadTemplates() {
            try {
                const response = await fetch('../api/sms/list-templates.php', {
                    headers: { 'Authorization': 'Bearer ' + authToken }
                });

                const data = await response.json();

                if (data.success) {
                    templates = data.templates || [];
                    renderTemplates();
                } else {
                    showError(data.message || 'خطا در بارگذاری');
                }
            } catch (error) {
                console.error('Error:', error);
                showError('خطا در ارتباط با سرور');
            }
        }

        function renderTemplates() {
            const container = document.getElementById('templatesContainer');

            if (templates.length === 0) {
                container.innerHTML = '<div class="text-center text-muted p-5">هیچ الگویی وجود ندارد</div>';
                return;
            }

            let html = `
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>نام الگو</th>
                            <th>عنوان</th>
                            <th>متن پیامک</th>
                            <th>وضعیت</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            templates.forEach(t => {
                html += `
                    <tr>
                        <td><code>${t.name}</code></td>
                        <td>${t.title}</td>
                        <td style="max-width: 300px; white-space: pre-wrap;">${t.message}</td>
                        <td>
                            ${t.is_active == 1 
                                ? '<span class="badge bg-success">فعال</span>' 
                                : '<span class="badge bg-secondary">غیرفعال</span>'}
                        </td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="editTemplate(${t.id})">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="deleteTemplate(${t.id})">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });

            html += '</tbody></table>';
            container.innerHTML = html;
        }

        function showTemplateModal(id = null) {
            editingId = id;
            const modal = new bootstrap.Modal(document.getElementById('templateModal'));
            
            if (id) {
                const template = templates.find(t => t.id == id);
                document.getElementById('modalTitle').textContent = 'ویرایش الگو';
                document.getElementById('templateId').value = template.id;
                document.getElementById('templateName').value = template.name;
                document.getElementById('templateName').readOnly = true;
                document.getElementById('templateTitle').value = template.title;
                document.getElementById('templateMessage').value = template.message;
                document.getElementById('templateActive').checked = template.is_active == 1;
            } else {
                document.getElementById('modalTitle').textContent = 'الگوی جدید';
                document.getElementById('templateForm').reset();
                document.getElementById('templateName').readOnly = false;
            }

            modal.show();
        }

        function editTemplate(id) {
            showTemplateModal(id);
        }

        async function saveTemplate() {
            const id = document.getElementById('templateId').value;
            const name = document.getElementById('templateName').value.trim();
            const title = document.getElementById('templateTitle').value.trim();
            const message = document.getElementById('templateMessage').value.trim();
            const is_active = document.getElementById('templateActive').checked ? 1 : 0;

            if (!name || !title || !message) {
                showAlert('لطفاً همه فیلدها را پر کنید', 'warning');
                return;
            }

            try {
                const url = id ? '../api/sms/update-template.php' : '../api/sms/create-template.php';
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + authToken
                    },
                    body: JSON.stringify({ id, name, title, message, is_active })
                });

                const data = await response.json();

                if (data.success) {
                    showAlert(data.message || 'ذخیره شد', 'success');
                    bootstrap.Modal.getInstance(document.getElementById('templateModal')).hide();
                    loadTemplates();
                } else {
                    showAlert(data.message || 'خطا در ذخیره', 'danger');
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('خطا در ارتباط با سرور', 'danger');
            }
        }

        function deleteTemplate(id) {
            uiConfirm('آیا از حذف این الگو اطمینان دارید؟', async function () {
                try {
                    const response = await fetch('../api/sms/delete-template.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': 'Bearer ' + authToken
                        },
                        body: JSON.stringify({ id })
                    });

                    const data = await response.json();

                    if (data.success) {
                        showAlert('حذف شد', 'success');
                        loadTemplates();
                    } else {
                        showAlert(data.message || 'خطا در حذف', 'danger');
                    }
                } catch (error) {
                    showAlert('خطا در ارتباط با سرور', 'danger');
                }
            }, { danger: true, yesText: 'بله، حذف', noText: 'انصراف' });
        }

        function showAlert(message, type = 'info') {
            const map = { danger: 'warning', error: 'warning' };
            showToast(message, map[type] || type);
        }

        function showError(message) {
            document.getElementById('templatesContainer').innerHTML = 
                `<div class="text-center text-danger p-5">${message}</div>`;
        }
    </script>
</body>
</html>