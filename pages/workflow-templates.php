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

// این صفحه هم روتین‌ها و هم روتینِ گردشِ‌کار رو پوشش می‌ده — با هرکدوم از دو
// اجازه (که ممکنه جدا/فردی هم اعطا شده باشن) قابلِ‌دسترسیه
$__me = loadUserForPermissions($db, (int) $user_id);
if (!$__me || (!hasPermission($__me, 'create_routine_template') && !hasPermission($__me, 'create_workflow'))) {
    header('Location: dashboard-manager.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت کارهای روتین</title>

    <link href="<?= asset('../assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">
    <link rel="stylesheet" href="<?= asset('../../assets/css/custom.css') ?>">
    <link rel="stylesheet" href="<?= asset('../../assets/css/drawflow.min.css') ?>">
    <script src="<?= asset('../../assets/js/sections-helper.js') ?>"></script>
    <script src="<?= asset('../assets/js/assignee-picker.js') ?>"></script>
    <script src="<?= asset('../../assets/js/cdn/drawflow.min.js') ?>"></script>
    <style>
        :root {
            --primary: #8e57fe;
            --primary-light: #8e57fe;
            --primary-dark: #8e57fe;
            --surface: #ffffff;
            --surface-2: #f8fafc;
            --surface-3: #f1f5f9;
            --border: #e9e9e9;
            --border-hover: #8e57fe;
            --text-1: #0f172a;
            --text-2: #475569;
            --text-3: #94a3b8;
            --success: #1b7b39;
            --danger: #ef4444;
            --warning: #f59e0b;
            --radius: 12px;
            --radius-sm: 8px;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.06), 0 1px 2px rgba(0, 0, 0, 0.04);
            --shadow: 0 4px 16px rgba(0, 0, 0, 0.07), 0 2px 6px rgba(0, 0, 0, 0.04);
            --shadow-lg: 0 12px 40px rgba(0, 0, 0, 0.1), 0 4px 16px rgba(0, 0, 0, 0.06);
        }

        * {
            font-family: 'Vazirmatn', Tahoma, sans-serif !important;
            box-sizing: border-box;
        }

        body {
            background: var(--surface-2);
            color: var(--text-1);
            min-height: 100vh;
        }


        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--border);
        }

        .page-header-left h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-1);
            margin: 0 0 4px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-header-left h1 i {
            width: 38px;
            height: 38px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1rem;
        }

        .page-header-left p {
            color: var(--text-3);
            font-size: 0.875rem;
            margin: 0 48px;
        }

        /* ─── Btn Primary ─── */
        .btn-create {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            border-radius: 9px;
            padding: 10px 20px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 2px 8px rgba(99, 102, 241, 0.35);
        }

        .btn-create:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 16px rgba(99, 102, 241, 0.45);
        }

        /* ─── Template Grid ─── */
        .templates-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.25rem;
        }

        .template-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.25rem;
            cursor: pointer;
            transition: all 0.22s ease;
            position: relative;
            overflow: hidden;
        }

        .template-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(180deg, var(--primary), var(--primary-light));
            opacity: 0;
            transition: opacity 0.2s;
        }

        .template-card:hover {
            border-color: var(--border-hover);
            box-shadow: var(--shadow);
            transform: translateY(-2px);
        }

        .template-card:hover::before {
            opacity: 1;
        }

        .card-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }

        .card-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-1);
            margin: 0;
            flex: 1;
            padding-left: 12px;
        }

        .badge-status {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .badge-active {
            background: rgba(27, 123, 57, 0.12);
            color: #1b7b39;
        }

        .badge-inactive {
            background: #e9e9e9;
            color: var(--text-3);
        }

        .card-desc {
            font-size: 0.825rem;
            color: var(--text-2);
            margin: 0 0 14px;
            line-height: 1.6;
            min-height: 2.4em;
        }

        .card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 12px;
            border-top: 1px solid var(--border);
        }

        .card-meta {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.8rem;
            color: var(--text-3);
        }

        .card-meta i {
            font-size: 0.9rem;
        }

        .card-actions {
            display: flex;
            gap: 6px;
        }

        .btn-icon {
            width: 32px;
            height: 32px;
            border-radius: 9px;
            border: 1px solid var(--border);
            background: var(--surface);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.18s;
            color: var(--text-2);
        }

        .btn-icon:hover {
            background: var(--surface-3);
            border-color: var(--border-hover);
        }

        .btn-icon.edit:hover {
            color: var(--primary);
            border-color: var(--primary-light);
            background: rgba(142, 87, 254, 0.12);
        }

        .btn-icon.del:hover {
            color: var(--danger);
            border-color: #fca5a5;
            background: #fef2f2;
        }

        /* ─── Empty State ─── */
        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 5rem 2rem;
            background: var(--surface);
            border-radius: var(--radius);
            border: 1px dashed var(--border);
        }

        .empty-state .empty-icon {
            width: 72px;
            height: 72px;
            background: var(--surface-3);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.25rem;
            font-size: 2rem;
            color: var(--text-3);
        }

        .empty-state h5 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-2);
            margin-bottom: 8px;
        }

        .empty-state p {
            font-size: 0.875rem;
            color: var(--text-3);
        }

        /* ─── Modal ─── */
        .modal-content {
            border: none;
            border-radius: 16px;
            box-shadow: var(--shadow-lg);
            max-width: 1200px !important;
        }

        .modal-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border);
            background: var(--surface);
            border-radius: 16px 16px 0 0;
        }

        .modal-title {
            font-weight: 700;
            font-size: 1.05rem;
            color: var(--text-1);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .modal-title i {
            color: var(--primary);
        }

        .modal-body {
            padding: 1.5rem;
            background: var(--surface);
        }

        .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border);
            background: var(--surface-2);
            border-radius: 0 0 16px 16px;
            gap: 8px;
        }

        /* حالتِ مشاهده: عناصرِ ویرایش/جابه‌جایی پنهان شوند */
        #templateModal.view-mode .btn-remove-step,
        #templateModal.view-mode .drag-hint,
        #templateModal.view-mode .btn-add-step,
        #templateModal.view-mode .exec-quick {
            display: none !important;
        }

        #templateModal.view-mode .step-item {
            cursor: default;
        }

        #templateModal.view-mode .step-mode-toggle {
            pointer-events: none;
            opacity: .85;
        }

        /* فاز ۴: پنلِ انشعابِ فاز ۳ به‌عنوانِ لایهٔ دادهٔ مخفی نگه داشته می‌شود؛
           ویرایشِ دیداری روی بومِ Drawflow انجام می‌شود */
        .sr-decision-toggle,
        .step-decision-body,
        #execPreview {
            display: none !important;
        }

        /* ─── انشعابِ شرطیِ مرحله (فاز ۳) ─── */
        .sr-decision-toggle {
            background: none;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 4px 8px;
            font-size: .72rem;
            color: var(--text-2);
            cursor: pointer;
            white-space: nowrap;
        }

        .sr-decision-toggle.has-branch {
            border-color: #8e57fe;
            color: #8e57fe;
        }

        .step-decision-body {
            flex-basis: 100%;
            margin-top: 8px;
            padding: 10px 12px;
            background: var(--surface-2);
            border: 1px dashed var(--border);
            border-radius: 10px;
            font-size: .8rem;
        }

        .step-decision-body .dec-row {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 8px;
        }

        .step-decision-body select {
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 5px 10px;
            font-size: .8rem;
            font-family: inherit;
            background: var(--surface);
            color: var(--text);
        }

        .step-decision-body .dec-config[hidden],
        .step-decision-body .dec-reject-target[hidden] {
            display: none;
        }

        .step-decision-body .dec-hint {
            color: var(--text-2);
            font-size: .72rem;
            margin-top: 4px;
        }

        #templateModal.view-mode .step-decision-body select,
        #templateModal.view-mode .step-decision-toggle-input {
            pointer-events: none;
            opacity: .85;
        }

        /* ─── بومِ مسیر و انشعاب (فاز ۴ — Drawflow) ─── */
        .wf-canvas-wrap {
            margin-top: 14px;
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            background: var(--surface-2);
        }

        .wf-canvas-toolbar {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            padding: 8px 12px;
            border-bottom: 1px solid var(--border);
            font-size: .78rem;
        }

        .wf-canvas-toolbar button {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 4px 10px;
            font-size: .78rem;
            font-family: inherit;
            color: var(--text);
            cursor: pointer;
        }

        .wf-canvas-toolbar .wf-legend {
            margin-inline-start: auto;
            display: flex;
            gap: 12px;
            color: var(--text-2);
        }

        .wf-canvas-toolbar .wf-legend i {
            font-style: normal;
            font-weight: 700;
        }

        #wfCanvas {
            height: 340px;
            width: 100%;
            direction: ltr;
            background:
                radial-gradient(circle, rgba(142, 87, 254, .12) 1px, transparent 1px) 0 0 / 22px 22px;
        }

        #wfCanvas .drawflow-node {
            background: var(--surface);
            border: 1.5px solid var(--border);
            border-radius: 12px;
            padding: 0;
            width: 190px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .06);
            color: var(--text);
        }

        #wfCanvas .drawflow-node.wf-decision {
            border-color: #8e57fe;
        }

        #wfCanvas .drawflow-node.wf-fixed {
            background: #eef;
            border-style: dashed;
        }

        .wf-node-body {
            padding: 8px 10px;
            direction: rtl;
            font-size: .8rem;
        }

        .wf-node-title {
            font-weight: 700;
            margin-bottom: 4px;
        }

        .wf-node-body label {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: .72rem;
            color: var(--text-2);
            cursor: pointer;
        }

        #wfCanvas .drawflow .connection .main-path {
            stroke: #1b7b39;
            stroke-width: 2.5px;
        }

        #wfCanvas .drawflow .connection.output_2 .main-path {
            stroke: #d33;
            stroke-dasharray: 6 4;
        }

        #wfCanvas .drawflow-node .output,
        #wfCanvas .drawflow-node .input {
            background: #8e57fe;
        }

        #templateModal.view-mode #wfCanvas {
            pointer-events: none;
            opacity: .9;
        }

        /* ─── Form Elements ─── */
        .form-label {
            font-size: 0.825rem;
            font-weight: 600;
            color: var(--text-2);
            margin-bottom: 6px;
        }

        .form-control,
        .form-select {
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 9px 12px;
            font-size: 0.875rem;
            color: var(--text-1);
            background: var(--surface);
            transition: border-color 0.18s, box-shadow 0.18s;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
            outline: none;
        }

        .form-switch .form-check-input:checked {
            background-color: var(--primary);
            border-color: var(--primary);
        }

        .sr-assignee-wrap {
            display: flex;
            flex-direction: column;
            gap: 4px;
            flex: 1;
            min-width: 0;
        }

        .sr-creator-check {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: .72rem;
            color: #6b7280;
            cursor: pointer;
            user-select: none;
            white-space: nowrap;
        }

        .sr-creator-check input {
            cursor: pointer;
            margin: 0;
        }

        .sr-creator-check:hover {
            color: #7c5cff;
        }

        /* ─── Section Divider ─── */
        .section-label {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 1.25rem 0 1rem;
        }

        .section-label span {
            font-size: 0.825rem;
            font-weight: 600;
            color: var(--text-2);
            white-space: nowrap;
        }

        .section-label::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        /* ─── Steps ─── */
        .steps-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .steps-header h6 {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-2);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-add-step {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(142, 87, 254, 0.12);
            color: var(--primary);
            border: 1px solid rgba(142, 87, 254, 0.3);
            border-radius: 9px;
            padding: 6px 14px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.18s;
        }

        .btn-add-step:hover {
            background: rgba(142, 87, 254, 0.2);
        }

        .step-item {
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 14px;
            margin-bottom: 10px;
            position: relative;
            transition: border-color 0.18s, box-shadow 0.18s;
            cursor: grab;
        }

        .step-item:active {
            cursor: grabbing;
        }

        .step-item.dragging {
            opacity: 0.45;
            border-style: dashed;
        }

        .step-item:hover {
            border-color: var(--border-hover);
            box-shadow: var(--shadow-sm);
        }

        .step-item-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .step-num {
            width: 24px;
            height: 24px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .drag-hint {
            color: var(--text-3);
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .template-card.locked {
            opacity: .9;
            cursor: not-allowed;
        }

        .badge-status.badge-locked {
            background: #FEF3C7;
            color: #B54708;
        }

        .btn-icon:disabled {
            opacity: .45;
            cursor: not-allowed;
            pointer-events: none;
        }

        .btn-remove-step {
            width: 28px;
            height: 28px;
            border-radius: 9px;
            border: 1px solid #fca5a5;
            background: #fef2f2;
            color: var(--danger);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 0.8rem;
            transition: all 0.18s;
        }

        .btn-remove-step:hover {
            background: #fee2e2;
        }

        .steps-hint {
            font-size: 0.775rem;
            color: var(--text-3);
            background: var(--surface-3);
            border-radius: var(--radius-sm);
            padding: 10px 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 10px;
        }

        /* ─── چیدمانِ یک‌خطیِ هر مرحله ─── */
        .step-item.step-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            padding: 10px 12px;
        }

        .step-row .step-num {
            flex: 0 0 auto;
        }

        .step-row .drag-hint {
            flex: 0 0 auto;
            cursor: grab;
            margin: 0;
        }

        .step-row .sr-name {
            flex: 1 1 30%;
            min-width: 0;
        }

        .step-row .sr-assignee {
            flex: 1 1 35%;
            min-width: 0;
        }

        .step-row .sr-time {
            flex: 0 0 80px;
            text-align: center;
        }

        .step-row .sr-mode {
            flex: 0 0 auto;
            margin: 0;
            display: flex;
            gap: 4px;
        }

        .step-row .sr-mode .sm-btn {
            padding: 5px 9px;
            font-size: 0.95rem;
            line-height: 1;
        }

        .step-row .sr-remove {
            flex: 0 0 auto;
        }

        /* جمع‌وجور کردنِ پیکر مسئول در ردیف */
        .step-row .sr-assignee .form-control,
        .step-row .sr-assignee input {
            font-size: 0.8rem;
            padding: 7px 10px;
        }

        /* ── چک‌لیستِ مرحله ── */
        .sr-checklist-toggle {
            flex: 0 0 auto;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--text-muted, #6b7280);
            border-radius: var(--radius-btn);
            padding: 5px 10px;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.15s;
        }

        .sr-checklist-toggle:hover,
        .sr-checklist-toggle.has-items {
            border-color: #8e57fe;
            color: #8e57fe;
        }

        .step-checklist-body {
            flex: 0 0 100%;
            width: 100%;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px dashed var(--border);
        }

        /* آیتم‌های چک‌لیست — کپی از الگوی create-task.php برای یکدستیِ ظاهری */
        .cl-item-wrap { margin-bottom: 8px; }
        .cl-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 10px;
            border: 1px solid #e9e9e9;
            border-radius: 8px;
            background: #fff;
            transition: border-color .15s, background .15s;
        }
        .cl-item:hover { border-color: #8e57fe; background: rgba(142, 87, 254, 0.04); }
        .cl-index { color: #9ca3af; font-size: 0.85rem; flex: 0 0 auto; }
        .cl-title-input { flex: 1 1 auto; min-width: 0; border: none; background: transparent; box-shadow: none !important; }
        .cl-title-input:focus { background: #e9e9e9; border-radius: 4px; }
        .cl-actions { display: flex; align-items: center; gap: 2px; flex: 0 0 auto; margin-inline-start: auto; }
        .cl-icon-btn {
            display: inline-flex; align-items: center; justify-content: center;
            width: 28px; height: 28px; border: none; background: transparent;
            border-radius: 9px; color: #9ca3af; cursor: pointer; transition: background .15s, color .15s;
            padding: 0; font-size: 0.9rem;
        }
        .cl-icon-btn:hover { background: #e9e9e9; }
        .cl-desc-btn:hover { color: #8e57fe; }
        .cl-desc-btn.has-desc { color: #8e57fe; }
        .cl-delete-btn:hover { color: #dc2626; background: #fee2e2; }
        .cl-desc-zone:empty { display: none; }
        .cl-desc-zone.open { margin-top: 6px; }
        .cl-desc-edit {
            display: flex; flex-direction: column; gap: 6px;
            padding: 8px 10px; background: #e9e9e9; border: 1px solid #e9e9e9; border-radius: 8px;
        }
        .cl-desc-edit textarea { font-size: 0.82rem; resize: vertical; }
        .cl-desc-edit-actions { display: flex; gap: 6px; justify-content: flex-end; }

        :root[data-theme="dark"] .cl-item {
            border-color: var(--border-soft);
            background: var(--surface);
        }
        :root[data-theme="dark"] .cl-item:hover {
            border-color: var(--icon-accent);
            background: #232a3a;
        }
        :root[data-theme="dark"] .cl-title-input:focus {
            background: #232a3a;
        }
        :root[data-theme="dark"] .cl-icon-btn:hover {
            background: #2b3242;
        }
        :root[data-theme="dark"] .cl-desc-edit {
            background: #161b27;
            border-color: var(--border-soft);
        }

        /* ── موبایل: برگشت به حالت عمودی ── */
        @media (max-width: 768px) {
            .step-item.step-row {
                flex-wrap: wrap;
            }

            .step-row .sr-name,
            .step-row .sr-assignee {
                flex: 1 1 100%;
            }

            .step-row .sr-time {
                flex: 0 0 80px;
            }
        }

        /* ─── Btn Save / Cancel ─── */
        .btn-save {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            border-radius: 9px;
            padding: 10px 22px;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 2px 8px rgba(99, 102, 241, 0.3);
        }

        .btn-save:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(99, 102, 241, 0.4);
        }

        .btn-cancel {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: var(--surface);
            color: var(--text-2);
            border: 1px solid var(--border);
            border-radius: 9px;
            padding: 10px 20px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.18s;
        }

        .btn-cancel:hover {
            background: var(--surface-3);
        }

        /* ─── Skeleton loader ─── */
        .skeleton {
            background: linear-gradient(90deg, var(--surface-3) 25%, var(--border) 50%, var(--surface-3) 75%);
            background-size: 200% 100%;
            animation: shimmer 1.4s infinite;
            border-radius: 6px;
        }

        .template-card.inactive {
            opacity: .65;
        }

        .btn-icon.view {
            color: #475467;
        }

        .btn-icon.redefine {
            color: #175CD3;
        }

        .btn-icon.toggle-active {
            color: #B54708;
        }

        .btn-icon:hover {
            background: #F2F4F7;
        }

        @keyframes shimmer {
            to {
                background-position: -200% 0;
            }
        }

        .skel-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.25rem;
        }

        .step-mode-toggle {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 10px;
            flex-wrap: wrap;
        }

        .step-mode-toggle .sm-label {
            font-size: .8rem;
            color: var(--text-2);
        }

        .sm-btn {
            border: 1px solid #d7dce3;
            background: #fff;
            color: #667085;
            border-radius: 9px;
            padding: 5px 12px;
            font-size: .8rem;
            cursor: pointer;
            font-family: inherit;
            transition: all .15s;
        }

        .sm-btn:hover {
            border-color: #8e57fe;
            color: #8e57fe;
        }

        :root[data-theme="dark"] .sm-btn {
            background: var(--surface);
            border-color: var(--border-soft);
            color: var(--text-muted);
        }

        .sm-btn.active.sm-parallel {
            background: #ECFDF3;
            border-color: #12B76A;
            color: #027A48;
            font-weight: 700;
        }

        .sm-btn.active.sm-cascade {
            background: #EFF8FF;
            border-color: #2E90FA;
            color: #175CD3;
            font-weight: 700;
        }

        .exec-quick {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin: 6px 0 12px;
        }

        .exec-quick .eq-label {
            font-size: .82rem;
            color: var(--text-2);
        }

        .exec-preview {
            background: #F9FAFB;
            border: 1px solid #EAECF0;
            border-radius: 10px;
            padding: 10px 14px;
            margin-top: 10px;
            font-size: .83rem;
            color: #344054;
        }

        .exec-preview .ep-line {
            margin: 2px 0;
        }

        .exec-preview b {
            color: #101828;
        }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="overview-container">
        <!-- Header -->
        <div class="page-header">
            <div class="page-header-left">
                <h1><i class="bi bi-diagram-3"></i> کارهای روتین</h1>
                <p>مشاهده و مدیریت فرآیندهای تکرارشونده</p>
            </div>
            <button class="btn-create" onclick="showAddTemplateModal()">
                <i class="bi bi-plus-lg"></i>
                روتین جدید
            </button>
        </div>

        <!-- Grid -->
        <div class="templates-grid" id="templatesList">
            <!-- Skeletons while loading -->
            <div class="skel-card">
                <div class="skeleton" style="height:20px;width:60%;margin-bottom:10px;"></div>
                <div class="skeleton" style="height:14px;width:90%;margin-bottom:6px;"></div>
                <div class="skeleton" style="height:14px;width:70%;"></div>
            </div>
            <div class="skel-card">
                <div class="skeleton" style="height:20px;width:50%;margin-bottom:10px;"></div>
                <div class="skeleton" style="height:14px;width:85%;margin-bottom:6px;"></div>
                <div class="skeleton" style="height:14px;width:60%;"></div>
            </div>
            <div class="skel-card">
                <div class="skeleton" style="height:20px;width:65%;margin-bottom:10px;"></div>
                <div class="skeleton" style="height:14px;width:80%;margin-bottom:6px;"></div>
                <div class="skeleton" style="height:14px;width:55%;"></div>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="templateModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">
                        <i class="bi bi-plus-circle"></i>
                        افزودن کار روتین جدید
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <form id="templateForm" onsubmit="return false;">
                        <input type="hidden" id="templateId">

                        <!-- نام و توضیحات در یک ردیف -->
                        <div class="row g-2 mb-3">
                            <div class="col-md-5">
                                <label class="form-label">نام کار روتین <span style="color:var(--danger)">*</span></label>
                                <input type="text" class="form-control" id="templateName"
                                    placeholder="مثال: فروش به مصرف‌کننده" required>
                            </div>
                            <div class="col-md-7">
                                <label class="form-label">توضیحات</label>
                                <textarea class="form-control" id="templateDescription" rows="2"
                                    placeholder="توضیح مختصری درباره این فرآیند..."></textarea>
                            </div>
                        </div>

                        <!-- فعال -->
                        <div class="mb-2">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="templateActive" checked>
                                <label class="form-check-label" for="templateActive" style="font-size:0.875rem;color:var(--text-2);">فعال</label>
                            </div>
                        </div>

                        <!-- مراحل -->
                        <div class="section-label"><span><i class="bi bi-list-ol me-1"></i>مراحل فرآیند</span></div>

                        <div class="steps-header">
                            <h6><i class="bi bi-grip-vertical"></i> ترتیب مراحل</h6>
                            <button type="button" class="btn-add-step" onclick="addStep()">
                                <i class="bi bi-plus"></i> افزودن مرحله
                            </button>
                        </div>

                        <div class="exec-quick">
                            <span class="eq-label"><i class="bi bi-lightning-charge"></i> تنظیم سریعِ همهٔ مراحل:</span>
                            <button type="button" class="sm-btn" onclick="setAllModes('cascade')">⛓ همه آبشاری</button>
                            <button type="button" class="sm-btn" onclick="setAllModes('parallel')">⚡ همه موازی</button>
                        </div>

                        <div id="stepsList"></div>

                        <div id="execPreview" class="exec-preview"></div>

                        <!-- بومِ مسیر و انشعاب (فاز ۴) -->
                        <div class="wf-canvas-wrap" id="wfCanvasWrap">
                            <div class="wf-canvas-toolbar">
                                <b><i class="bi bi-diagram-2"></i> مسیر و انشعابِ روتین</b>
                                <button type="button" onclick="wfSyncFromForm()" title="بازچینش از روی فهرستِ مراحل"><i class="bi bi-arrow-repeat"></i> همگام‌سازی</button>
                                <button type="button" onclick="wfZoom(0.1)"><i class="bi bi-zoom-in"></i></button>
                                <button type="button" onclick="wfZoom(-0.1)"><i class="bi bi-zoom-out"></i></button>
                                <button type="button" onclick="wfZoomReset()">۱:۱</button>
                                <span class="wf-legend">
                                    <span><i style="color:#1b7b39">──</i> تأیید / بعدی</span>
                                    <span><i style="color:#d33">╌╌</i> رد</span>
                                </span>
                            </div>
                            <div id="wfCanvas"></div>
                        </div>

                        <div class="steps-hint">
                            <i class="bi bi-info-circle"></i>
                            جزئیاتِ هر مرحله (نام، مسئول، مهلت، چک‌لیست) در فهرستِ بالا؛ <b>ترتیب و انشعاب</b> را روی بومِ پایین مشخص کنید:
                            گرهِ «نقطهٔ تصمیم» را تیک بزنید تا دو خروجیِ «تأیید» و «رد» بگیرد، بعد آن‌ها را به گرهِ مقصد (یا گرهِ «تعریف‌کننده») وصل کنید.
                            جای عمودیِ گره‌ها ترتیبِ اجرا را تعیین می‌کند.
                        </div>
                    </form>
                </div>

                <div id="deactivateOldWrap" style="display:none; padding:0 1rem 0.5rem;">
                    <label style="display:flex; align-items:center; gap:8px; font-size:0.9rem; cursor:pointer;">
                        <input type="checkbox" id="deactivateOldChk">
                        قالبِ قبلی غیرفعال شود (دیگر در ساخت کار جدید نمایش داده نشود)
                    </label>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-cancel" data-bs-dismiss="modal">
                        <i class="bi bi-x"></i> انصراف
                    </button>
                    <button type="button" class="btn-save" onclick="saveTemplate()">
                        <i class="bi bi-check2"></i> ذخیره
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="<?= asset('../assets/js/cdn/bootstrap.bundle.min.js') ?>"></script>
    <script>
        let currentTemplateId = null;
        let stepCounter = 0;

        function toFa(n) {
            return String(n).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]);
        }

        const UNITS = [{
                value: 'sales',
                label: 'فروش'
            },
            {
                value: 'purchase',
                label: 'خرید'
            },
            {
                value: 'warehouse',
                label: 'انبار'
            },
            {
                value: 'technical',
                label: 'فنی'
            },
            {
                value: 'accounting',
                label: 'حسابداری'
            },
            {
                value: 'colleague',
                label: 'همکار'
            },
            {
                value: 'offices',
                label: 'ادارات'
            },
            {
                value: 'virtual',
                label: 'فضای مجازی'
            },
            {
                value: 'public',
                label: 'عمومی'
            },
            {
                value: 'management',
                label: 'مدیریت'
            },
        ];

        // ─── بارگذاری لیست ───────────────────────────────
        async function loadTemplates() {
            try {
                const response = await fetch('../api/workflows/list-all-templates.php', {
                    headers: {
                        'Authorization': 'Bearer ' + authToken
                    }
                });
                const data = await response.json();
                if (data.success) renderTemplates(data.templates);
                else showToast(data.message, 'error');
            } catch (e) {
                console.error(e);
                showToast('خطا در بارگذاری لیست', 'error');
            }
        }

        // ─── رندر کارت‌ها ────────────────────────────────
        function renderTemplates(templates) {
            const container = document.getElementById('templatesList');

            if (!templates || templates.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-icon"><i class="bi bi-diagram-3"></i></div>
                        <h5>هنوز روتینی تعریف نشده</h5>
                        <p>اولین فرآیند روتین خود را اضافه کنید</p>
                    </div>`;
                return;
            }

            container.innerHTML = templates.map(t => {
                const isActive = (t.is_active == 1);
                return `
                <div class="template-card ${isActive ? '' : 'inactive'}"
                     onclick="viewTemplate(${t.id})" style="cursor:pointer;">
                    <div class="card-top">
                        <h3 class="card-title">${escHtml(t.name)}</h3>
                        ${isActive
                            ? '<span class="badge-status badge-active"><i class="bi bi-circle-fill" style="font-size:6px;"></i> فعال</span>'
                            : '<span class="badge-status badge-inactive"><i class="bi bi-circle" style="font-size:6px;"></i> غیرفعال</span>'}
                    </div>
                    <p class="card-desc">${escHtml(t.description || 'بدون توضیحات')}</p>
                    <div class="card-footer">
                        <div class="card-meta">
                            <i class="bi bi-list-check"></i>
                            <span>${toFa(t.steps_count || 0)} مرحله</span>
                        </div>
                        <div class="card-actions">
                            <button class="btn-icon view" title="مشاهده"
                                onclick="event.stopPropagation(); viewTemplate(${t.id})">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn-icon redefine" title="بازتعریف (ساخت نسخهٔ جدید)"
                                onclick="event.stopPropagation(); redefineTemplate(${t.id})">
                                <i class="bi bi-arrow-repeat"></i>
                            </button>
                            <button class="btn-icon toggle-active" title="${isActive ? 'غیرفعال‌کردن' : 'فعال‌کردن'}"
                                onclick="event.stopPropagation(); toggleTemplateActive(${t.id}, ${isActive ? 0 : 1})">
                                <i class="bi bi-${isActive ? 'pause-circle' : 'play-circle'}"></i>
                            </button>
                        </div>
                    </div>
                </div>`;
            }).join('');
        }
        // ─── فعال/غیرفعال‌کردن قالب ───────────────────────
        function toggleTemplateActive(templateId, newActive) {
            const makeActive = (newActive == 1);
            const msg = makeActive ?
                'این قالب دوباره فعال شود و در ساخت کار جدید نمایش داده شود؟' :
                'این قالب غیرفعال شود؟ دیگر در ساخت کار جدید نمایش داده نمی‌شود (کارهای در حال اجرا ادامه می‌یابند).';
            uiConfirm(msg, async function() {
                try {
                    const res = await fetch('../api/workflows/toggle-template-active.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': 'Bearer ' + authToken
                        },
                        body: JSON.stringify({
                            template_id: templateId,
                            is_active: makeActive ? 1 : 0
                        })
                    });
                    const data = await res.json();
                    if (data.success) {
                        showToast(data.message, 'success');
                        loadTemplates();
                    } else {
                        showToast(data.message || 'تغییر وضعیت انجام نشد', 'error');
                    }
                } catch (e) {
                    console.error(e);
                    showToast('خطا در ارتباط با سرور', 'error');
                }
            });
        }
        // ─── مودال افزودن ────────────────────────────────
        function showAddTemplateModal() {
            currentTemplateId = null;
            redefineSourceId = null;
            setTemplateModalReadonly(false);
            document.getElementById('modalTitle').innerHTML = '<i class="bi bi-plus-circle"></i> افزودن کار روتین جدید';
            document.getElementById('templateForm').reset();
            document.getElementById('templateId').value = '';
            document.getElementById('templateActive').checked = true;
            const wrap = document.getElementById('deactivateOldWrap');
            if (wrap) wrap.style.display = 'none'; // تیک فقط در بازتعریف
            document.getElementById('stepsList').innerHTML = '';
            stepCounter = 0;
            addStep();
            new bootstrap.Modal(document.getElementById('templateModal')).show();
        }

        function notifyLocked() {
            showToast('این قالب یک کار روتینِ در حال اجرا دارد و تا تکمیل‌شدنِ آن قابل ویرایش یا حذف نیست.', 'warning');
        }
        // ─── مشاهدهٔ فقط‌خواندنی ──────────────────────────
        async function viewTemplate(templateId) {
            try {
                const response = await fetch(`../api/workflows/get-template.php?id=${templateId}`, {
                    headers: {
                        'Authorization': 'Bearer ' + authToken
                    }
                });
                const data = await response.json();
                if (!data.success) {
                    showToast(data.message, 'error');
                    return;
                }

                const t = data.template;
                currentTemplateId = null;
                redefineSourceId = null;

                document.getElementById('modalTitle').innerHTML = '<i class="bi bi-eye"></i> مشاهدهٔ کار روتین';
                document.getElementById('templateId').value = '';
                document.getElementById('templateName').value = t.name || '';
                document.getElementById('templateDescription').value = t.description || '';
                document.getElementById('templateActive').checked = t.is_active == 1;

                const wrap = document.getElementById('deactivateOldWrap');
                if (wrap) wrap.style.display = 'none';

                document.getElementById('stepsList').innerHTML = '';
                stepCounter = 0;
                if (t.steps && t.steps.length > 0) {
                    t.steps.forEach(step => addStep(step));
                } else {
                    addStep();
                }

                // فقط‌خواندنی کردن کلِ فرم + پنهان‌کردن دکمهٔ ذخیره
                setTemplateModalReadonly(true);

                new bootstrap.Modal(document.getElementById('templateModal')).show();
            } catch (e) {
                console.error(e);
                showToast('خطا در بارگذاری اطلاعات', 'error');
            }
        }

        // فعال/غیرفعال‌کردنِ حالتِ فقط‌خواندنیِ مودال
        function setTemplateModalReadonly(readonly) {
            const modal = document.getElementById('templateModal');

            // کلاسِ view-mode برای کنترلِ نمایش با CSS (حذف مرحله، جابه‌جایی، افزودن مرحله، ذخیره)
            modal.classList.toggle('view-mode', !!readonly);

            // ورودی‌ها و دکمه‌های داخلِ فرم را غیرفعال کن
            modal.querySelectorAll('input, select, textarea, button').forEach(el => {
                if (el.classList.contains('btn-close') ||
                    el.classList.contains('btn-cancel') ||
                    el.getAttribute('data-bs-dismiss') === 'modal') return;
                el.disabled = readonly;
            });

            // غیرفعال‌کردنِ کشیدن (drag) روی مراحل در حالت مشاهده
            modal.querySelectorAll('.step-item').forEach(el => {
                if (readonly) el.removeAttribute('draggable');
                else el.setAttribute('draggable', 'true');
            });

            // دکمهٔ ذخیره
            const saveBtn = modal.querySelector('.btn-save');
            if (saveBtn) saveBtn.style.display = readonly ? 'none' : '';
        }
        // ─── ویرایش الگو ─────────────────────────────────
        async function editTemplate(templateId) {
            try {
                const response = await fetch(`../api/workflows/get-template.php?id=${templateId}`, {
                    headers: {
                        'Authorization': 'Bearer ' + authToken
                    }
                });
                const data = await response.json();

                if (!data.success) {
                    showToast(data.message, 'error');
                    return;
                }

                const t = data.template;
                currentTemplateId = templateId;

                document.getElementById('modalTitle').innerHTML = '<i class="bi bi-pencil"></i> ویرایش کار روتین';
                document.getElementById('templateId').value = templateId;
                document.getElementById('templateName').value = t.name || '';
                document.getElementById('templateDescription').value = t.description || '';
                document.getElementById('templateActive').checked = t.is_active == 1;

                document.getElementById('stepsList').innerHTML = '';
                stepCounter = 0;

                if (t.steps && t.steps.length > 0) {
                    t.steps.forEach(step => addStep(step));
                } else {
                    addStep();
                }

                new bootstrap.Modal(document.getElementById('templateModal')).show();
            } catch (e) {
                console.error(e);
                showToast('خطا در بارگذاری اطلاعات', 'error');
            }
        }
        // ─── بازتعریف (ساخت نسخهٔ جدید از روی قالب) ──────────
        let redefineSourceId = null; // آیدیِ قالبِ مبدأ برای غیرفعال‌سازی پس از ذخیره

        async function redefineTemplate(templateId) {
            try {
                const response = await fetch(`../api/workflows/get-template.php?id=${templateId}`, {
                    headers: {
                        'Authorization': 'Bearer ' + authToken
                    }
                });
                const data = await response.json();
                if (!data.success) {
                    showToast(data.message, 'error');
                    return;
                }

                const t = data.template;
                currentTemplateId = null;
                redefineSourceId = templateId; // مبدأ را نگه می‌داریم
                setTemplateModalReadonly(false);

                document.getElementById('modalTitle').innerHTML = '<i class="bi bi-arrow-repeat"></i> بازتعریف کار روتین';
                document.getElementById('templateId').value = ''; // خالی → ساختِ نسخهٔ جدید
                document.getElementById('templateName').value = (t.name || '') + ' (نسخهٔ جدید)';
                document.getElementById('templateDescription').value = t.description || '';
                document.getElementById('templateActive').checked = true;

                // تیکِ «قالب قبلی غیرفعال شود» را نمایش بده و پیش‌فرض خاموش
                const wrap = document.getElementById('deactivateOldWrap');
                if (wrap) wrap.style.display = 'block';
                const chk = document.getElementById('deactivateOldChk');
                if (chk) chk.checked = false;

                document.getElementById('stepsList').innerHTML = '';
                stepCounter = 0;
                if (t.steps && t.steps.length > 0) {
                    t.steps.forEach(step => addStep(step));
                } else {
                    addStep();
                }

                new bootstrap.Modal(document.getElementById('templateModal')).show();
            } catch (e) {
                console.error(e);
                showToast('خطا در بارگذاری اطلاعات', 'error');
            }
        }
        // ─── افزودن مرحله ────────────────────────────────
        let stepPickers = {}; // stepId -> picker instance
        let stepOriginalData = {}; // stepId -> { type, value } (برای حالت ویرایش‌نشده)
        let stepChecklists = {}; // stepId -> [{tempId, title, description}]
        /* 🆕 تیک «به ایجادکننده» → غیرفعال‌کردن AssigneePicker */
        function toggleCreatorMode(stepId, checkbox) {
            const wrap = document.getElementById('step_assignee_' + stepId);
            if (!wrap) return;

            if (checkbox.checked) {
                wrap.style.opacity = '.4';
                wrap.style.pointerEvents = 'none';
                // علامت‌گذاری این مرحله
                checkbox.closest('.step-item').dataset.creatorMode = '1';
            } else {
                wrap.style.opacity = '';
                wrap.style.pointerEvents = '';
                delete checkbox.closest('.step-item').dataset.creatorMode;
            }
        }

        function addStep(stepData = null) {
            stepCounter++;
            const stepId = stepData ? stepData.id : `new_${stepCounter}`;
            const stepMode = (stepData && stepData.execution_mode === 'parallel') ? 'parallel' : 'cascade';
            stepOriginalData[stepId] = {
                type: stepData ? (stepData.assignee_type || 'section') : 'section',
                value: stepData ?
                    (stepData.assignee_type === 'user' ? stepData.assignee_user_id : stepData.activity_section) : ''
            };

            let assigneePlaceholder = 'جستجوی کاربر/واحد...';
            if (stepData) {
                if (stepData.assignee_type === 'user') {
                    assigneePlaceholder = 'مسئول فعلی: ' + (stepData.assignee_user_name || 'کاربر') + ' — برای تغییر جستجو کنید...';
                } else if (stepData.activity_section) {
                    assigneePlaceholder = 'واحد فعلی: ' + (wfSectionMap[stepData.activity_section] || stepData.activity_section) + ' — برای تغییر جستجو کنید...';
                }
            }

            const html = `
                <div class="step-item step-row" data-step-id="${stepId}" draggable="true">
                    <span class="step-num">${toFa(stepCounter)}</span>
                    <span class="drag-hint" title="بکشید"><i class="bi bi-grip-vertical"></i></span>

                    <input type="text" class="form-control form-control-sm step-name sr-name"
                           value="${escAttr(stepData ? stepData.step_name : '')}"
                           placeholder="نام مرحله" oninput="updateExecPreview()" required>

                    <div class="sr-assignee-wrap">
                        <div class="sr-assignee" id="step_assignee_${stepId}"></div>
                        <label class="sr-creator-check" title="این مرحله به کسی که روتین را شروع می‌کند سپرده شود">
                            <input type="checkbox" class="step-creator-toggle"
                                   onchange="toggleCreatorMode('${stepId}', this)"
                                   ${stepData && stepData.assignee_type === 'creator' ? 'checked' : ''}>
                            <span>به ایجادکننده</span>
                        </label>
                    </div>

                    <input type="number" class="form-control form-control-sm step-time sr-time"
                           value="${stepData ? stepData.time_limit_hours : 24}"
                           min="1" placeholder="ساعت" title="مهلت (ساعت)" required>

                    <div class="step-mode-toggle sr-mode" data-mode="${stepMode}">
                        <button type="button" class="sm-btn sm-cascade ${stepMode==='cascade'?'active':''}" onclick="setStepMode(this,'cascade')" title="آبشاری">⛓</button>
                        <button type="button" class="sm-btn sm-parallel ${stepMode==='parallel'?'active':''}" onclick="setStepMode(this,'parallel')" title="موازی">⚡</button>
                    </div>

                    <button type="button" class="sr-checklist-toggle" id="scl-toggle-${stepId}" onclick="toggleStepChecklist('${stepId}')" title="چک‌لیستِ این مرحله">
                        <i class="bi bi-check2-square"></i> <span id="scl-count-${stepId}">چک‌لیست</span>
                    </button>

                    <button type="button" class="sr-decision-toggle" id="dec-toggle-${stepId}" onclick="toggleStepDecision('${stepId}')" title="انشعابِ شرطی (تأیید/رد)">
                        <i class="bi bi-signpost-split"></i> <span id="dec-label-${stepId}">انشعاب</span>
                    </button>

                    <button type="button" class="btn-remove-step sr-remove" onclick="removeStep(this)" title="حذف مرحله">
                        <i class="bi bi-x"></i>
                    </button>

                    <div class="step-checklist-body" id="scl-body-${stepId}" style="display:none;">
                        <div id="scl-items-${stepId}"></div>
                        <div style="display:flex; gap:8px; margin-top:8px;">
                            <input type="text" id="scl-new-${stepId}" class="form-control form-control-sm"
                                placeholder="افزودن آیتم چک‌لیست..."
                                onkeydown="if(event.key==='Enter'){event.preventDefault();addStepChecklistItem('${stepId}');}">
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="addStepChecklistItem('${stepId}')">
                                <i class="bi bi-plus"></i>
                            </button>
                        </div>
                    </div>

                    <div class="step-decision-body" id="dec-body-${stepId}" style="display:none;">
                        <label style="display:flex; align-items:center; gap:6px; cursor:pointer;">
                            <input type="checkbox" class="step-decision-toggle-input" id="dec-on-${stepId}"
                                   onchange="onDecisionToggle('${stepId}', this)">
                            <span>این مرحله «نقطهٔ تصمیم» است (تعریف‌کنندهٔ روتین تأیید/رد می‌کند)</span>
                        </label>
                        <div class="dec-config" id="dec-config-${stepId}" hidden>
                            <div class="dec-row">
                                <span>در صورت <b>تأیید</b> → برو به مرحلهٔ</span>
                                <select class="dec-approve-target" id="dec-approve-${stepId}">
                                    <option value="">مرحلهٔ بعدی (پیش‌فرض)</option>
                                </select>
                            </div>
                            <div class="dec-row">
                                <span>در صورت <b>رد</b> →</span>
                                <select class="dec-reject-kind" id="dec-rkind-${stepId}" onchange="onRejectKindChange('${stepId}', this)">
                                    <option value="step">برگرد به مرحلهٔ…</option>
                                    <option value="creator">برگشت به تعریف‌کنندهٔ روتین</option>
                                </select>
                                <select class="dec-reject-target" id="dec-reject-${stepId}"></select>
                            </div>
                            <div class="dec-hint">مقصدِ رد می‌تواند هر مرحله‌ای باشد؛ حتی مراحلِ بعد از این مرحله.</div>
                        </div>
                        <div class="dec-hint" id="dec-parallel-hint-${stepId}" hidden>
                            انشعاب فقط برای مراحلِ «آبشاری» است. این مرحله موازی است.
                        </div>
                    </div>
                </div>`;

            document.getElementById('stepsList').insertAdjacentHTML('beforeend', html);

            // 🆕 چک‌لیستِ این مرحله (عنوان + توضیحات، هر دو اختیاری در ورودی اما عنوان الزامی برای ثبت)
            stepChecklists[stepId] = (stepData && Array.isArray(stepData.checklist_items)) ?
                stepData.checklist_items.map(it => ({
                    tempId: Date.now() + Math.random(),
                    title: it.title || '',
                    description: it.description || ''
                })) : [];
            renderStepChecklist(stepId);

            stepPickers[stepId] = AssigneePicker.create({
                container: '#step_assignee_' + stepId,
                users: wfUsers,
                sections: wfSections,
                sectionMap: wfSectionMap,
                showSections: true,
                placeholder: assigneePlaceholder
            });

            // 🆕 اگر این مرحله از نوع «ایجادکننده» است، picker را غیرفعال کن
            if (stepData && stepData.assignee_type === 'creator') {
                const cb = document.querySelector(`.step-item[data-step-id="${stepId}"] .step-creator-toggle`);
                if (cb) toggleCreatorMode(stepId, cb);
            }

            // 🆕 فاز ۳: تنظیماتِ انشعاب (اگر از قبل داشت) — روی المان ذخیره می‌شود و
            // در refreshDecisionTargets() اعمال می‌شود (چون گزینه‌های select هنوز ساخته نشده‌اند)
            const stepEl = document.querySelector(`.step-item[data-step-id="${stepId}"]`);
            if (stepEl && stepData && Number(stepData.is_decision) === 1) {
                stepEl._pendingBranch = {
                    on_approve_step_order: stepData.on_approve_step_order ?? '',
                    on_reject_mode: stepData.on_reject_mode || 'creator',
                    on_reject_step_order: stepData.on_reject_step_order ?? ''
                };
                const onCb = document.getElementById('dec-on-' + stepId);
                if (onCb) { onCb.checked = true; onDecisionToggle(stepId, onCb); }
                const decBody = document.getElementById('dec-body-' + stepId);
                if (decBody) decBody.style.display = 'block'; // باز نشان بده چون انشعاب دارد
            }

            updateStepNumbers();   // ← refreshDecisionTargets() هم از این‌جا صدا زده می‌شود
            initDragAndDrop();

            // فاز ۴: افزودنِ تعاملیِ یک مرحله → بومِ Drawflow را همگام کن
            // (حالتِ bulk/restore با رویدادِ shown.bs.modal همگام می‌شود)
            if (!stepData && typeof wfSyncFromForm === 'function') wfSyncFromForm();
        }

        // ─── انشعابِ شرطیِ مرحله (فاز ۳) ─────────────────────────────
        function toggleStepDecision(stepId) {
            const body = document.getElementById('dec-body-' + stepId);
            if (!body) return;
            body.style.display = (body.style.display === 'none') ? 'block' : 'none';
        }

        function onDecisionToggle(stepId, cb) {
            const stepEl = document.querySelector(`.step-item[data-step-id="${stepId}"]`);
            const config = document.getElementById('dec-config-' + stepId);
            const pHint = document.getElementById('dec-parallel-hint-' + stepId);
            const isParallel = stepEl && stepEl.querySelector('.step-mode-toggle')?.dataset.mode === 'parallel';

            if (cb.checked && isParallel) {
                // انشعاب فقط برای آبشاری
                cb.checked = false;
                if (config) config.hidden = true;
                if (pHint) pHint.hidden = false;
            } else {
                if (config) config.hidden = !cb.checked;
                if (pHint) pHint.hidden = true;
            }
            markBranchToggle(stepId);
            refreshDecisionTargets();
        }

        function onRejectKindChange(stepId, sel) {
            const tgt = document.getElementById('dec-reject-' + stepId);
            if (tgt) tgt.hidden = (sel.value !== 'step');
        }

        function markBranchToggle(stepId) {
            const btn = document.getElementById('dec-toggle-' + stepId);
            const on = document.getElementById('dec-on-' + stepId);
            const lbl = document.getElementById('dec-label-' + stepId);
            if (!btn || !on) return;
            btn.classList.toggle('has-branch', on.checked);
            if (lbl) lbl.textContent = on.checked ? 'انشعابِ فعال' : 'انشعاب';
        }

        // گزینه‌های dropdownِ همهٔ مراحلِ تصمیم را از ترتیبِ فعلیِ مراحل بازمی‌سازد
        function refreshDecisionTargets() {
            const items = [...document.querySelectorAll('.step-item')];
            const opts = items.map((el, i) => {
                const nm = (el.querySelector('.step-name')?.value || '').trim() || 'بی‌نام';
                return { pos: i + 1, name: nm };
            });

            items.forEach((el, idx) => {
                const stepId = el.dataset.stepId;
                const selfPos = idx + 1;
                const approveSel = document.getElementById('dec-approve-' + stepId);
                const rejectSel = document.getElementById('dec-reject-' + stepId);
                if (!approveSel || !rejectSel) return;

                // اعمالِ pending (فقط یک‌بار، بعد از ساختِ گزینه‌ها)
                const pending = el._pendingBranch;

                const prevApprove = pending ? String(pending.on_approve_step_order ?? '') : approveSel.value;
                const prevRejectKind = pending ? (pending.on_reject_mode || 'creator')
                    : (document.getElementById('dec-rkind-' + stepId)?.value || 'step');
                const prevRejectStep = pending ? String(pending.on_reject_step_order ?? '') : rejectSel.value;

                const buildOptions = (includeDefault) => {
                    let h = includeDefault ? '<option value="">مرحلهٔ بعدی (پیش‌فرض)</option>' : '<option value="">— انتخاب مرحله —</option>';
                    opts.forEach(o => {
                        if (o.pos === selfPos) return; // خودِ مرحله را نگذار
                        h += `<option value="${o.pos}">${toFa(o.pos)} - ${escHtml(o.name)}</option>`;
                    });
                    return h;
                };

                approveSel.innerHTML = buildOptions(true);
                rejectSel.innerHTML = buildOptions(false);

                if ([...approveSel.options].some(o => o.value === prevApprove)) approveSel.value = prevApprove;
                const rkind = document.getElementById('dec-rkind-' + stepId);
                if (rkind && ['step', 'creator'].includes(prevRejectKind)) rkind.value = prevRejectKind;
                if ([...rejectSel.options].some(o => o.value === prevRejectStep)) rejectSel.value = prevRejectStep;
                if (rkind) onRejectKindChange(stepId, rkind);

                if (pending) delete el._pendingBranch;
            });
        }

        /* ═══════════════════════════════════════════════════════════════
           فاز ۴ — بومِ تعاملیِ «مسیر و انشعاب» با Drawflow.
           مدلِ داده همان عناصرِ مخفیِ فاز ۳ است (dec-on/dec-approve/dec-rkind/
           dec-reject در هر ردیفِ مرحله). بوم آن‌ها را می‌خواند و می‌نویسد؛
           saveTemplate بدون تغییر می‌ماند.
           ─ گرهِ مرحله: ورودی ۱، خروجی ۱ (یا ۲ اگر «نقطهٔ تصمیم» تیک باشد:
             output_1 = تأیید/بعدی، output_2 = رد).
           ─ گرهِ ثابتِ «شروع» و «تعریف‌کننده».
           ─ جای عمودیِ گره‌ها → ترتیبِ اجرا (step_order).
           ═══════════════════════════════════════════════════════════════ */
        let wfEditor = null;
        let wfStartId = null, wfCreatorId = null;
        let wfSyncing = false;        // جلوگیری از حلقهٔ رویدادها هنگام بازسازی
        let wfCanvasToFormTimer = null;

        function wfInit() {
            const el = document.getElementById('wfCanvas');
            if (!el || typeof Drawflow === 'undefined' || wfEditor) return;
            wfEditor = new Drawflow(el);
            wfEditor.reroute = true;
            wfEditor.reroute_fix_curvature = true;
            wfEditor.force_first_input = false;
            wfEditor.start();

            wfEditor.on('connectionCreated', () => { if (!wfSyncing) wfScheduleCanvasToForm(); });
            wfEditor.on('connectionRemoved', () => { if (!wfSyncing) wfScheduleCanvasToForm(); });
            wfEditor.on('nodeMoved', () => { if (!wfSyncing) wfScheduleCanvasToForm(); });

            // تیکِ «نقطهٔ تصمیم» داخلِ گره‌ها (delegation)
            el.addEventListener('change', function (e) {
                if (wfSyncing || !e.target.classList.contains('wf-dec-chk')) return;
                const nodeEl = e.target.closest('.drawflow-node');
                if (!nodeEl) return;
                const nid = parseInt(String(nodeEl.id).replace('node-', ''), 10);
                wfSetNodeDecision(nid, e.target.checked);
                wfScheduleCanvasToForm();
            });
        }

        function wfNodeHtml(order, name, isDecision) {
            return `<div class="wf-node-body">
                <div class="wf-node-title">${toFa(order)}. <span class="wf-node-name">${escHtml(name || 'بی‌نام')}</span></div>
                <label><input type="checkbox" class="wf-dec-chk" ${isDecision ? 'checked' : ''}> نقطهٔ تصمیم (تأیید/رد)</label>
            </div>`;
        }

        // فهرستِ مراحلِ فرم به‌ترتیبِ فعلی
        function wfFormSteps() {
            return [...document.querySelectorAll('.step-item')].map((el, i) => {
                const stepId = el.dataset.stepId;
                const mode = el.querySelector('.step-mode-toggle')?.dataset.mode === 'parallel' ? 'parallel' : 'cascade';
                const decOn = document.getElementById('dec-on-' + stepId);
                return {
                    el, stepId, order: i + 1,
                    name: (el.querySelector('.step-name')?.value || '').trim(),
                    mode,
                    isDecision: mode === 'cascade' && decOn && decOn.checked,
                    onApprove: document.getElementById('dec-approve-' + stepId)?.value || '',
                    rkind: document.getElementById('dec-rkind-' + stepId)?.value || 'creator',
                    onReject: document.getElementById('dec-reject-' + stepId)?.value || ''
                };
            });
        }

        // بازسازیِ کاملِ بوم از روی فهرستِ مراحل
        function wfSyncFromForm() {
            wfInit();
            if (!wfEditor) return;
            wfSyncing = true;
            try {
                wfEditor.clear();
                const steps = wfFormSteps();
                wfStartId = wfEditor.addNode('start', 0, 1, 30, 20, 'wf-fixed', {}, '<div class="wf-node-body"><b>شروع</b></div>');

                const idByOrder = {};
                steps.forEach((s, i) => {
                    const nid = wfEditor.addNode('step', 1, s.isDecision ? 2 : 1, 30, 110 + i * 95,
                        'wf-step' + (s.isDecision ? ' wf-decision' : ''),
                        { stepId: s.stepId }, wfNodeHtml(s.order, s.name, s.isDecision));
                    idByOrder[s.order] = nid;
                });

                wfCreatorId = wfEditor.addNode('creator', 1, 0, 260, 110 + steps.length * 95, 'wf-fixed', {},
                    '<div class="wf-node-body"><b>تعریف‌کنندهٔ روتین</b></div>');

                // یال‌ها
                const cascadeOrders = steps.filter(s => s.mode === 'cascade').map(s => s.order);
                if (cascadeOrders.length) {
                    wfEditor.addConnection(wfStartId, idByOrder[cascadeOrders[0]], 'output_1', 'input_1');
                }
                steps.forEach(s => {
                    if (s.mode === 'parallel') {
                        wfEditor.addConnection(wfStartId, idByOrder[s.order], 'output_1', 'input_1');
                        return;
                    }
                    const ci = cascadeOrders.indexOf(s.order);
                    const nextCascadeOrder = (ci >= 0 && ci + 1 < cascadeOrders.length) ? cascadeOrders[ci + 1] : null;
                    if (s.isDecision) {
                        const appr = s.onApprove ? parseInt(s.onApprove, 10) : nextCascadeOrder;
                        if (appr && idByOrder[appr]) wfEditor.addConnection(idByOrder[s.order], idByOrder[appr], 'output_1', 'input_1');
                        if (s.rkind === 'creator') {
                            wfEditor.addConnection(idByOrder[s.order], wfCreatorId, 'output_2', 'input_1');
                        } else if (s.onReject && idByOrder[parseInt(s.onReject, 10)]) {
                            wfEditor.addConnection(idByOrder[s.order], idByOrder[parseInt(s.onReject, 10)], 'output_2', 'input_1');
                        } else {
                            wfEditor.addConnection(idByOrder[s.order], wfCreatorId, 'output_2', 'input_1');
                        }
                    } else if (nextCascadeOrder && idByOrder[nextCascadeOrder]) {
                        wfEditor.addConnection(idByOrder[s.order], idByOrder[nextCascadeOrder], 'output_1', 'input_1');
                    }
                });
            } catch (e) {
                console.error('wfSyncFromForm', e);
            } finally {
                wfSyncing = false;
            }
        }

        function wfSetNodeDecision(nodeId, on) {
            if (!wfEditor) return;
            const node = wfEditor.getNodeFromId(nodeId);
            if (!node) return;
            const outCount = Object.keys(node.outputs || {}).length;
            wfSyncing = true;
            try {
                if (on && outCount < 2) wfEditor.addNodeOutput(nodeId);
                if (!on && outCount > 1) wfEditor.removeNodeOutput(nodeId, 'output_' + outCount);
                const dom = document.getElementById('node-' + nodeId);
                if (dom) dom.classList.toggle('wf-decision', !!on);
            } catch (e) { console.error('wfSetNodeDecision', e); }
            finally { wfSyncing = false; }
        }

        function wfScheduleCanvasToForm() {
            clearTimeout(wfCanvasToFormTimer);
            wfCanvasToFormTimer = setTimeout(wfCanvasToForm, 200);
        }

        // نوشتنِ وضعیتِ بوم به عناصرِ مخفیِ فرم + بازچینشِ ردیف‌ها بر اساسِ جای عمودی
        function wfCanvasToForm() {
            if (!wfEditor) return;
            let data;
            try { data = wfEditor.export().drawflow.Home.data; } catch (e) { return; }

            const nodes = Object.values(data).filter(n => n.data && n.data.stepId);
            if (!nodes.length) return;
            nodes.sort((a, b) => (a.pos_y - b.pos_y));

            const orderByNodeId = {};
            nodes.forEach((n, i) => { orderByNodeId[n.id] = i + 1; });

            // بازچینشِ DOMِ فهرستِ مراحل
            const list = document.getElementById('stepsList');
            nodes.forEach(n => {
                const row = document.querySelector(`.step-item[data-step-id="${n.data.stepId}"]`);
                if (row) list.appendChild(row);
            });
            updateStepNumbers(); // شماره‌ها + refreshDecisionTargets

            // انشعابِ هر مرحله از روی یال‌ها
            nodes.forEach(n => {
                const stepId = n.data.stepId;
                const decOn = document.getElementById('dec-on-' + stepId);
                const outs = n.outputs || {};
                const isDecision = Object.keys(outs).length >= 2;

                if (decOn) {
                    if (decOn.checked !== isDecision) {
                        decOn.checked = isDecision;
                        if (typeof onDecisionToggle === 'function') onDecisionToggle(stepId, decOn);
                    }
                }
                if (!isDecision) return;

                const targetOf = (outKey) => {
                    const c = outs[outKey] && outs[outKey].connections && outs[outKey].connections[0];
                    return c ? parseInt(c.node, 10) : null;
                };
                const apprNode = targetOf('output_1');
                const rejNode = targetOf('output_2');

                const apprSel = document.getElementById('dec-approve-' + stepId);
                const rkind = document.getElementById('dec-rkind-' + stepId);
                const rejSel = document.getElementById('dec-reject-' + stepId);

                if (apprSel) {
                    const p = apprNode && orderByNodeId[apprNode] ? String(orderByNodeId[apprNode]) : '';
                    if ([...apprSel.options].some(o => o.value === p)) apprSel.value = p; else apprSel.value = '';
                }
                if (rkind && rejSel) {
                    if (rejNode === wfCreatorId || rejNode === null) {
                        rkind.value = 'creator';
                    } else {
                        rkind.value = 'step';
                        const p = orderByNodeId[rejNode] ? String(orderByNodeId[rejNode]) : '';
                        if ([...rejSel.options].some(o => o.value === p)) rejSel.value = p;
                    }
                    if (typeof onRejectKindChange === 'function') onRejectKindChange(stepId, rkind);
                }
            });
        }

        function wfZoom(delta) { if (wfEditor) { delta > 0 ? wfEditor.zoom_in() : wfEditor.zoom_out(); } }
        function wfZoomReset() { if (wfEditor) wfEditor.zoom_reset(); }

        // ─── حذف مرحله ───────────────────────────────────
        function removeStep(btn) {
            const stepEl = btn.closest('.step-item');
            const stepId = stepEl.dataset.stepId;
            delete stepPickers[stepId];
            delete stepOriginalData[stepId];
            delete stepChecklists[stepId];
            stepEl.remove();
            updateStepNumbers();
            if (typeof wfSyncFromForm === 'function') wfSyncFromForm();
        }

        // ─── چک‌لیستِ مرحله ────────────────────────────────
        function toggleStepChecklist(stepId) {
            const body = document.getElementById('scl-body-' + stepId);
            if (!body) return;
            body.style.display = (body.style.display === 'none') ? 'block' : 'none';
        }

        function addStepChecklistItem(stepId) {
            const input = document.getElementById('scl-new-' + stepId);
            const title = input.value.trim();
            if (!title) return;
            stepChecklists[stepId] = stepChecklists[stepId] || [];
            stepChecklists[stepId].push({
                tempId: Date.now() + Math.random(),
                title,
                description: ''
            });
            input.value = '';
            input.focus();
            renderStepChecklist(stepId);
        }

        function removeStepChecklistItem(stepId, tempId) {
            stepChecklists[stepId] = (stepChecklists[stepId] || []).filter(i => i.tempId !== tempId);
            renderStepChecklist(stepId);
        }

        function updateStepChecklistTitle(stepId, tempId, val) {
            const it = (stepChecklists[stepId] || []).find(i => i.tempId === tempId);
            if (it) it.title = val.trim();
        }

        function startEditStepChecklistDesc(stepId, tempId) {
            const zone = document.getElementById('scl-desc-zone-' + stepId + '-' + tempId);
            if (!zone) return;
            const it = (stepChecklists[stepId] || []).find(i => i.tempId === tempId);
            const currentDesc = (it && it.description) ? it.description : '';

            zone.classList.add('open');
            zone.innerHTML = `
                <div class="cl-desc-edit">
                    <textarea id="scl-desc-input-${stepId}-${tempId}" class="form-control form-control-sm" rows="2"
                        placeholder="توضیحات..."
                        onkeydown="if(event.key==='Escape'){cancelEditStepChecklistDesc('${stepId}',${tempId});} else if(event.ctrlKey && event.key==='Enter'){saveEditStepChecklistDesc('${stepId}',${tempId});}"
                    >${escHtml(currentDesc)}</textarea>
                    <div class="cl-desc-edit-actions">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="cancelEditStepChecklistDesc('${stepId}',${tempId})">
                            <i class="bi bi-x-lg"></i> انصراف
                        </button>
                        <button type="button" class="btn btn-sm btn-success" onclick="saveEditStepChecklistDesc('${stepId}',${tempId})">
                            <i class="bi bi-check-lg"></i> ثبت توضیحات
                        </button>
                    </div>
                </div>`;

            const ta = document.getElementById('scl-desc-input-' + stepId + '-' + tempId);
            if (ta) {
                ta.focus();
                ta.setSelectionRange(ta.value.length, ta.value.length);
            }
        }

        function saveEditStepChecklistDesc(stepId, tempId) {
            const ta = document.getElementById('scl-desc-input-' + stepId + '-' + tempId);
            if (!ta) return;
            const it = (stepChecklists[stepId] || []).find(i => i.tempId === tempId);
            if (it) it.description = ta.value.trim();
            renderStepChecklist(stepId);
        }

        function cancelEditStepChecklistDesc(stepId, tempId) {
            renderStepChecklist(stepId);
        }

        function renderStepChecklist(stepId) {
            const c = document.getElementById('scl-items-' + stepId);
            const toggle = document.getElementById('scl-toggle-' + stepId);
            const countLabel = document.getElementById('scl-count-' + stepId);
            const items = stepChecklists[stepId] || [];
            if (!c) return;

            c.innerHTML = items.map((item, idx) => {
                const hasDesc = !!(item.description && item.description.trim());
                const descTooltip = hasDesc ? escAttr(item.description) : 'افزودن توضیحات';
                return `
                <div class="cl-item-wrap">
                    <div class="cl-item">
                        <span class="cl-index">${idx + 1}.</span>
                        <input type="text" class="form-control form-control-sm cl-title-input" value="${escAttr(item.title)}"
                            placeholder="عنوان آیتم..."
                            onchange="updateStepChecklistTitle('${stepId}', ${item.tempId}, this.value)">
                        <div class="cl-actions">
                            <button type="button" class="cl-icon-btn cl-desc-btn ${hasDesc ? 'has-desc' : ''}"
                                title="${descTooltip}"
                                onclick="startEditStepChecklistDesc('${stepId}', ${item.tempId})">
                                <i class="bi ${hasDesc ? 'bi-chat-left-text-fill' : 'bi-chat-left-text'}"></i>
                            </button>
                            <button type="button" class="cl-icon-btn cl-delete-btn" title="حذف آیتم"
                                onclick="removeStepChecklistItem('${stepId}', ${item.tempId})">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                    <div class="cl-desc-zone" id="scl-desc-zone-${stepId}-${item.tempId}"></div>
                </div>`;
            }).join('');

            if (toggle && countLabel) {
                countLabel.textContent = items.length ? `چک‌لیست (${toFa(items.length)})` : 'چک‌لیست';
                toggle.classList.toggle('has-items', items.length > 0);
            }
        }

        function updateStepNumbers() {
            document.querySelectorAll('.step-item').forEach((el, i) => {
                el.querySelector('.step-num').textContent = toFa(i + 1);
            });
            updateExecPreview();
            if (typeof refreshDecisionTargets === 'function') refreshDecisionTargets();
        }

        // ── انتخاب حالتِ یک مرحله ──
        function setStepMode(btn, mode) {
            const wrap = btn.closest('.step-mode-toggle');
            wrap.dataset.mode = mode;
            wrap.querySelectorAll('.sm-btn').forEach(b => b.classList.remove('active'));
            wrap.querySelector(mode === 'parallel' ? '.sm-parallel' : '.sm-cascade').classList.add('active');
            // 🆕 انشعاب فقط برای آبشاری — با تبدیل به موازی، تصمیم خاموش می‌شود
            if (mode === 'parallel') {
                const stepEl = wrap.closest('.step-item');
                const stepId = stepEl?.dataset.stepId;
                const onCb = stepId && document.getElementById('dec-on-' + stepId);
                if (onCb && onCb.checked) { onCb.checked = false; onDecisionToggle(stepId, onCb); }
            }
            updateExecPreview();
        }

        // ── ست‌کردنِ همهٔ مراحل با یک کلیک ──
        function setAllModes(mode) {
            document.querySelectorAll('.step-mode-toggle').forEach(wrap => {
                wrap.dataset.mode = mode;
                wrap.querySelectorAll('.sm-btn').forEach(b => b.classList.remove('active'));
                wrap.querySelector(mode === 'parallel' ? '.sm-parallel' : '.sm-cascade').classList.add('active');
            });
            updateExecPreview();
        }

        // ── پیش‌نمایشِ زنده ──
        function updateExecPreview() {
            const box = document.getElementById('execPreview');
            if (!box) return;
            const faNum = s => String(s).replace(/[0-9]/g, d => '۰۱۲۳۴۵۶۷۸۹' [d]);
            const items = [...document.querySelectorAll('.step-item')];
            if (!items.length) {
                box.innerHTML = '';
                return;
            }

            const modes = items.map(it => it.querySelector('.step-mode-toggle')?.dataset.mode || 'cascade');
            const names = items.map((it, i) => (it.querySelector('.step-name')?.value.trim() || ('مرحله ' + faNum(i + 1))));
            const firstCascadeIdx = modes.findIndex(m => m === 'cascade');

            const activeNow = [],
                waiting = [];
            items.forEach((it, i) => {
                if (modes[i] === 'parallel' || i === firstCascadeIdx) {
                    activeNow.push(names[i]);
                } else {
                    let p = -1;
                    for (let k = i - 1; k >= 0; k--) {
                        if (modes[k] === 'cascade') {
                            p = k;
                            break;
                        }
                    }
                    waiting.push(names[i] + (p >= 0 ? ' (بعد از: ' + names[p] + ')' : ''));
                }
            });

            box.innerHTML =
                '<div class="ep-line"><b>از ابتدا فعال:</b> ' + (activeNow.length ? activeNow.map(escHtml).join('، ') : '—') + '</div>' +
                '<div class="ep-line"><b>منتظر:</b> ' + (waiting.length ? waiting.map(escHtml).join('، ') : '—') + '</div>';
        }

        // ─── Drag & Drop ──────────────────────────────────
        let draggedEl = null;

        function initDragAndDrop() {
            document.querySelectorAll('.step-item').forEach(el => {
                el.ondragstart = function() {
                    draggedEl = this;
                    this.classList.add('dragging');
                };
                el.ondragover = e => e.preventDefault();
                el.ondrop = function(e) {
                    e.stopPropagation();
                    if (draggedEl && draggedEl !== this) {
                        const all = [...document.querySelectorAll('.step-item')];
                        const di = all.indexOf(draggedEl),
                            ti = all.indexOf(this);
                        if (di < ti) this.after(draggedEl);
                        else this.before(draggedEl);
                        updateStepNumbers();
                    }
                };
                el.ondragend = function() {
                    this.classList.remove('dragging');
                    draggedEl = null;
                };
            });
        }

        // ─── ذخیره الگو ──────────────────────────────────
        async function saveTemplate() {
            const name = document.getElementById('templateName').value.trim();
            if (!name) {
                showToast('نام کار روتین الزامی است', 'warning');
                return;
            }

            // فاز ۴: آخرین وضعیتِ بوم را (بدون منتظرِ debounce) در فرم بنویس
            clearTimeout(wfCanvasToFormTimer);
            if (typeof wfCanvasToForm === 'function' && wfEditor) wfCanvasToForm();

            const stepItems = document.querySelectorAll('.step-item');
            if (stepItems.length === 0) {
                showToast('حداقل یک مرحله تعریف کنید', 'warning');
                return;
            }

            const steps = [];
            let valid = true;

            stepItems.forEach((item, i) => {
                const stepId = item.dataset.stepId;
                const sName = item.querySelector('.step-name').value.trim();
                const sTime = item.querySelector('.step-time').value;

                const sMode = item.querySelector('.step-mode-toggle')?.dataset.mode === 'parallel' ? 'parallel' : 'cascade';

                // 🆕 چک‌لیستِ این مرحله — فقط آیتم‌هایی که عنوان دارند
                const stepChecklistItems = (stepChecklists[stepId] || [])
                    .filter(it => it.title && it.title.trim())
                    .map(it => ({
                        title: it.title.trim(),
                        description: (it.description || '').trim()
                    }));

                // 🆕 فاز ۳: تنظیماتِ انشعابِ شرطی (فقط برای آبشاری)
                const decOn = document.getElementById('dec-on-' + stepId);
                const branch = { is_decision: 0, on_approve_step_order: null, on_reject_mode: null, on_reject_step_order: null };
                if (sMode === 'cascade' && decOn && decOn.checked) {
                    branch.is_decision = 1;
                    branch.on_approve_step_order = document.getElementById('dec-approve-' + stepId)?.value || null;
                    const rkind = document.getElementById('dec-rkind-' + stepId)?.value || 'creator';
                    branch.on_reject_mode = rkind;
                    if (rkind === 'step') {
                        const rt = document.getElementById('dec-reject-' + stepId)?.value || '';
                        if (!rt) { valid = false; }
                        branch.on_reject_step_order = rt || null;
                    }
                }

                // 🆕 حالت «به ایجادکننده»
                if (item.dataset.creatorMode === '1') {
                    if (!sName || !sTime) {
                        valid = false;
                        return;
                    }
                    steps.push({
                        step_order: i + 1,
                        step_name: sName,
                        time_limit_hours: parseInt(sTime),
                        assignee_type: 'creator',
                        assignee_value: 'creator', // مقدار نمادین (بک‌اند نادیده می‌گیرد)
                        execution_mode: sMode,
                        checklist_items: stepChecklistItems,
                        ...branch
                    });
                    return;
                }

                const picked = stepPickers[stepId] ? stepPickers[stepId].getValue() : null;
                let assignee = (picked && picked.value !== '__all__' && picked.value !== '__all_users__') ? {
                        type: picked.type,
                        value: picked.value
                    } :
                    (stepOriginalData[stepId] || {
                        type: 'section',
                        value: ''
                    });

                if (!sName || !assignee.value || !sTime) {
                    valid = false;
                    return;
                }
                steps.push({
                    step_order: i + 1,
                    step_name: sName,
                    time_limit_hours: parseInt(sTime),
                    assignee_type: assignee.type,
                    assignee_value: assignee.value,
                    execution_mode: sMode,
                    checklist_items: stepChecklistItems,
                    ...branch
                });
            });

            if (!valid) {
                showToast('تمام فیلدها را تکمیل کنید — و برای مراحلِ «رد → برگرد به مرحلهٔ…»، مقصدِ رد را انتخاب کنید', 'warning');
                return;
            }

            const payload = {
                name,
                description: document.getElementById('templateDescription').value.trim(),
                is_active: document.getElementById('templateActive').checked ? 1 : 0,
                steps
            };

            const templateId = document.getElementById('templateId').value;
            if (templateId) payload.template_id = templateId;

            const url = templateId ?
                '../api/workflows/update-template.php' :
                '../api/workflows/create-template.php';

            const saveBtn = document.querySelector('.btn-save');
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> در حال ذخیره...';

            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + authToken
                    },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();

                if (data.success) {
                    // اگر بازتعریف بود و تیکِ غیرفعال‌سازیِ قالب قبلی زده شده بود
                    const deact = document.getElementById('deactivateOldChk');
                    if (redefineSourceId && deact && deact.checked) {
                        try {
                            await fetch('../api/workflows/toggle-template-active.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Authorization': 'Bearer ' + authToken
                                },
                                body: JSON.stringify({
                                    template_id: redefineSourceId,
                                    is_active: 0
                                })
                            });
                        } catch (e) {
                            console.error('deactivate old template:', e);
                        }
                    }
                    redefineSourceId = null; // پاک‌سازی
                    showToast(data.message, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('templateModal')).hide();
                    loadTemplates();
                } else {
                    showToast(data.message, 'error');
                }
            } catch (e) {
                showToast('خطا در ارتباط با سرور', 'error');
            } finally {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="bi bi-check2"></i> ذخیره';
            }
        }

        // ─── حذف الگو ────────────────────────────────────
        function deleteTemplate(templateId) {
            uiConfirm('آیا از حذف این روتین اطمینان دارید؟', async function() {
                try {
                    const res = await fetch('../api/workflows/delete-template.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': 'Bearer ' + authToken
                        },
                        body: JSON.stringify({
                            template_id: templateId
                        })
                    });
                    const data = await res.json();
                    if (data.success) {
                        showToast(data.message, 'success');
                        loadTemplates();
                    } else showToast(data.message, 'error');
                } catch (e) {
                    showToast('خطا در حذف', 'error');
                }
            }, {
                danger: true,
                yesText: 'بله، حذف',
                noText: 'انصراف'
            });
        }

        // ─── Toast ────────────────────────────────────────
        // showToast از assets/js/alert.js (لودشده در header.php) استفاده می‌شود —
        // قبلاً اینجا یک نسخهٔ محلیِ جداگانه بازتعریف می‌شد که آن را می‌پوشاند

        // ─── Utils ────────────────────────────────────────
        function escHtml(str) {
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        function escAttr(str) {
            return String(str || '').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        }

        // ─── بارگذاریِ مستقلِ کاربران و واحدها برای Pickerِ مسئولِ هر مرحله ───
        let wfUsers = [];
        let wfSections = [];
        let wfSectionMap = {};
        let stepAssigneeState = {}; // stepId -> { touched, type, value }

        async function loadUsersAndSectionsForPicker() {
            try {
                const [usersRes, sectionsRes] = await Promise.all([
                    fetch('../api/users/list.php', {
                        headers: {
                            'Authorization': 'Bearer ' + authToken
                        }
                    }),
                    fetch('../api/organization/activity-sections.php', {
                        headers: {
                            'Authorization': 'Bearer ' + authToken
                        }
                    })
                ]);
                const usersData = await usersRes.json();
                const sectionsData = await sectionsRes.json();
                if (usersData.success) wfUsers = usersData.users;
                if (sectionsData.success) {
                    wfSections = sectionsData.sections;
                    sectionsData.sections.forEach(s => {
                        wfSectionMap[s.section_key] = s.section_label;
                    });
                }
            } catch (e) {
                console.error('loadUsersAndSectionsForPicker error:', e);
            }
        }

        // ─── Init ─────────────────────────────────────────
        document.addEventListener('DOMContentLoaded', async function() {
            if (!authToken) {
                window.location.href = '../index.php';
                return;
            }
            loadTemplates();
            await loadSectionMap(); // در init صفحه
            await loadUsersAndSectionsForPicker();

            // فاز ۴: هر بار مودالِ قالب کامل نمایش داده شد، بومِ مسیر را از فهرستِ مراحل بساز
            const tm = document.getElementById('templateModal');
            if (tm) tm.addEventListener('shown.bs.modal', function () {
                setTimeout(function () { if (typeof wfSyncFromForm === 'function') wfSyncFromForm(); }, 60);
            });
        });
    </script>
    <?php include 'footer.php'; ?>
</body>

</html>