<?php
require_once __DIR__ . '/../includes/page-bootstrap.php';
if ((!hasPermission($__me, 'create_routine_template') && !hasPermission($__me, 'create_workflow'))) {
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
            font-family: 'Vazirmatn', sans-serif !important;
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
            padding: 7px 10px;
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

        /* حالت مشاهده: عناصر ویرایش/جابه‌جایی پنهان شوند */
        #templateModal.view-mode .btn-remove-step,
        #templateModal.view-mode .drag-hint,
        #templateModal.view-mode .btn-add-step {
            display: none !important;
        }

        #templateModal.view-mode .step-item {
            cursor: default;
        }

        /* ─── بوم مسیر و انشعاب (فاز ۴ — Drawflow) ─── */
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

        .wf-narrative {
            margin-top: 10px;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: var(--surface);
            font-size: .8rem;
            line-height: 1.9;
            color: var(--text);
        }

        .wf-narrative div {
            padding: 1px 0;
        }

        /* تمام‌صفحه برای بوم انشعاب */
        .wf-canvas-wrap.wf-canvas-fs {
            position: fixed;
            inset: 0;
            z-index: 3000;
            margin: 0;
            border-radius: 0;
            background: var(--surface);
            display: flex;
            flex-direction: column;
        }

        .wf-canvas-wrap.wf-canvas-fs #wfCanvas {
            flex: 1;
            height: auto;
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

        .wf-node-assignee {
            margin-top: 4px;
            padding-top: 4px;
            border-top: 1px dashed var(--border);
            font-size: .66rem;
            color: var(--text-2);
            direction: rtl;
            text-align: left;
        }

        /* فاز ۴: پنل فاز ۳ + چک‌باکس «به ایجادکننده» مخفی (ادیتور روی بوم است) */
        .sr-creator-check {
            display: none !important;
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

        /* دایره‌های خروجی یک «نقطهٔ تصمیم»: بالا سبز (تأیید)، پایین قرمز (رد) */
        #wfCanvas .drawflow-node.wf-decision .outputs .output_1 {
            background: #16a34a;
        }

        #wfCanvas .drawflow-node.wf-decision .outputs .output_2 {
            background: #dc2626;
        }

        #wfCanvas .drawflow-node.wf-decision .outputs .output_1::after,
        #wfCanvas .drawflow-node.wf-decision .outputs .output_2::after {
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            font-size: .6rem;
            font-weight: 700;
            white-space: nowrap;
            pointer-events: none;
        }

        #wfCanvas .drawflow-node.wf-decision .outputs .output_1::after {
            content: 'تأیید';
            color: #16a34a;
            bottom: 130%;
        }

        #wfCanvas .drawflow-node.wf-decision .outputs .output_2::after {
            content: 'رد';
            color: #dc2626;
            top: 130%;
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

        /* ─── چیدمان یک‌خطی هر مرحله ─── */
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

        .step-row .sr-remove {
            flex: 0 0 auto;
        }

        /* جمع‌وجور کردن پیکر مسئول در ردیف */
        .step-row .sr-assignee .form-control,
        .step-row .sr-assignee input {
            font-size: 0.8rem;
            padding: 7px 10px;
            padding-right: 30px !important;
        }

        /* ── چک‌لیست مرحله ── */
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

        /* آیتم‌های چک‌لیست — کپی از الگوی create-task.php برای یکدستی ظاهری */
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

                        <div id="stepsList"></div>

                        <!-- بوم مسیر و انشعاب (فاز ۶ — گراف‌محور) -->
                        <div class="wf-canvas-wrap" id="wfCanvasWrap">
                            <div class="wf-canvas-toolbar">
                                <b><i class="bi bi-diagram-2"></i>مسیردهی روتین</b>
                                <button type="button" onclick="wfAutoArrange()" title="چیدمانِ گره‌ها را به یک ستون مرتب تنظیم کن (یال‌ها حفظ می‌شوند)"><i class="bi bi-arrow-repeat"></i> چیدمان خودکار</button>
                                <button type="button" onclick="wfZoom(0.1)" title="بزرگ‌نمایی"><i class="bi bi-zoom-in"></i></button>
                                <button type="button" onclick="wfZoom(-0.1)" title="کوچک‌نمایی"><i class="bi bi-zoom-out"></i></button>
                                <button type="button" onclick="wfZoomReset()" title="بازنشانی بزرگ‌نمایی">۱:۱</button>
                                <button type="button" id="wfFsBtn" onclick="wfToggleFullscreen()" title="تمام‌صفحه"><i class="bi bi-arrows-fullscreen"></i></button>
                                <span class="wf-legend">
                                    <span><i style="color:#1b7b39">──</i> تأیید / بعدی</span>
                                    <span><i style="color:#d33">╌╌</i> رد</span>
                                    <span>کلیک راست روی خط = حذف · دوبار کلیک = نقطهٔ خم</span>
                                </span>
                            </div>
                            <div id="wfCanvas"></div>
                        </div>

                        <div class="wf-narrative" id="wfNarrative"></div>

                        <div class="steps-hint">
                            <i class="bi bi-info-circle"></i>
                            جزئیات هر مرحله (نام، مسئول، مهلت، چک‌لیست) در فهرست بالا؛ <b>زمانِ اجرا و انشعاب</b> را فقط با یال‌هایِ روی بوم پایین مشخص کنید:
                            گرهٔ «شروع» را به هر مرحله‌ای که باید از همان ابتدا فعال شود وصل کنید؛ یک گره را می‌توان هم‌زمان به چند مرحله وصل کرد (موازی/فورک)، و یک مرحله می‌تواند از چند مبدأ هم‌زمان خط بگیرد (هم‌گرایی/join — تا همه تمام نشوند، آن مرحله فعال نمی‌شود).
                            برای «نقطهٔ تصمیم»، تیکِ گره را بزنید تا دو خروجیِ «تأیید» (سبز) و «رد» (قرمز) بگیرد و هرکدام را به مقصدِ دلخواه وصل کنید. جابه‌جاکردنِ گره‌ها فقط ظاهر است و چیزی را تغییر نمی‌دهد. <b>کلیک راست</b> روی یک خط، آن را حذف می‌کند.
                        </div>
                    </form>
                </div>

                <div id="deactivateOldWrap" style="display:none; padding:0 1rem 0.5rem;">
                    <label style="display:flex; align-items:center; gap:8px; font-size:0.9rem; cursor:pointer;">
                        <input type="checkbox" id="deactivateOldChk">
                        قالب قبلی غیرفعال شود (دیگر در ساخت کار جدید نمایش داده نشود)
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
        // فاز ۶: تبدیلِ ساختارِ آبشاری/موازی/انشعابِ تک‌مقصدیِ قالب‌هایِ قدیمی
        // (engine_version=1) به آرایهٔ یال‌هایِ گراف — فقط برایِ «نمایش» یا
        // «بازتعریف»؛ خودِ قالبِ قدیمی دست‌نخورده می‌ماند. برمی‌گرداند:
        // { transitions, creatorRejectSteps } — دومی مراحلی‌ست که «رد →
        // بازگشت به ایجادکننده» داشتند و معادلِ گراف‌محور ندارند (باید
        // دستی روی بوم وصل شوند).
        function wfTransitionsFromTemplateData(t) {
            if (Number(t.engine_version) === 2) {
                return { transitions: t.transitions || [], creatorRejectSteps: [] };
            }
            const steps = t.steps || [];
            const cascadeOrders = steps.filter(s => s.execution_mode !== 'parallel').map(s => s.step_order);
            const transitions = [];
            const creatorRejectSteps = [];
            steps.forEach(s => {
                if (s.execution_mode === 'parallel') return; // بدون پیش‌نیاز؛ خودش مستقل شروع می‌شود
                const ci = cascadeOrders.indexOf(s.step_order);
                const nextCascade = (ci >= 0 && ci + 1 < cascadeOrders.length) ? cascadeOrders[ci + 1] : null;
                if (Number(s.is_decision) === 1) {
                    const appr = s.on_approve_step_order ? Number(s.on_approve_step_order) : nextCascade;
                    if (appr) transitions.push({ from_step_order: s.step_order, to_step_order: appr, condition: 'approve' });
                    if (s.on_reject_mode === 'step' && s.on_reject_step_order) {
                        transitions.push({ from_step_order: s.step_order, to_step_order: Number(s.on_reject_step_order), condition: 'reject' });
                    } else {
                        creatorRejectSteps.push(s.step_order);
                    }
                } else if (nextCascade) {
                    transitions.push({ from_step_order: s.step_order, to_step_order: nextCascade, condition: 'always' });
                }
            });
            return { transitions, creatorRejectSteps };
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
            stepCanvasPos = {};
            wfCurrentTransitions = [];
            wfLoadedTransitions = [];
            addStep();
            new bootstrap.Modal(document.getElementById('templateModal')).show();
        }

        function notifyLocked() {
            showToast('این قالب یک کار روتین در حال اجرا دارد و تا تکمیل‌شدن آن قابل ویرایش یا حذف نیست.', 'warning');
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

                document.getElementById('modalTitle').innerHTML = '<i class="bi bi-eye"></i> مشاهدهٔ کار روتین #' + templateId;
                document.getElementById('templateId').value = '';
                document.getElementById('templateName').value = t.name || '';
                document.getElementById('templateDescription').value = t.description || '';
                document.getElementById('templateActive').checked = t.is_active == 1;

                const wrap = document.getElementById('deactivateOldWrap');
                if (wrap) wrap.style.display = 'none';

                document.getElementById('stepsList').innerHTML = '';
                stepCounter = 0;
                stepCanvasPos = {};
                wfCurrentTransitions = [];
                const conv = wfTransitionsFromTemplateData(t);
                wfLoadedTransitions = conv.transitions;
                if (t.steps && t.steps.length > 0) {
                    t.steps.forEach(step => addStep(step));
                } else {
                    addStep();
                }

                // فقط‌خواندنی کردن کل فرم + پنهان‌کردن دکمهٔ ذخیره
                setTemplateModalReadonly(true);

                new bootstrap.Modal(document.getElementById('templateModal')).show();
            } catch (e) {
                console.error(e);
                showToast('خطا در بارگذاری اطلاعات', 'error');
            }
        }

        // فعال/غیرفعال‌کردن حالت فقط‌خواندنی مودال
        function setTemplateModalReadonly(readonly) {
            const modal = document.getElementById('templateModal');

            // کلاس view-mode برای کنترل نمایش با CSS (حذف مرحله، جابه‌جایی، افزودن مرحله، ذخیره)
            modal.classList.toggle('view-mode', !!readonly);

            // ورودی‌ها و دکمه‌های داخل فرم را غیرفعال کن
            modal.querySelectorAll('input, select, textarea, button').forEach(el => {
                if (el.classList.contains('btn-close') ||
                    el.classList.contains('btn-cancel') ||
                    el.getAttribute('data-bs-dismiss') === 'modal') return;
                el.disabled = readonly;
            });

            // غیرفعال‌کردن کشیدن (drag) روی مراحل در حالت مشاهده
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
                stepCanvasPos = {};
                wfCurrentTransitions = [];
                wfLoadedTransitions = wfTransitionsFromTemplateData(t).transitions;

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
        let redefineSourceId = null; // آیدی قالب مبدأ برای غیرفعال‌سازی پس از ذخیره

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
                document.getElementById('templateId').value = ''; // خالی → ساخت نسخهٔ جدید
                document.getElementById('templateName').value = (t.name || '') + ' (نسخهٔ جدید)';
                document.getElementById('templateDescription').value = t.description || '';
                document.getElementById('templateActive').checked = true;

                // تیک «قالب قبلی غیرفعال شود» را نمایش بده و پیش‌فرض خاموش
                const wrap = document.getElementById('deactivateOldWrap');
                if (wrap) wrap.style.display = 'block';
                const chk = document.getElementById('deactivateOldChk');
                if (chk) chk.checked = false;

                document.getElementById('stepsList').innerHTML = '';
                stepCounter = 0;
                stepCanvasPos = {};
                wfCurrentTransitions = [];
                const conv = wfTransitionsFromTemplateData(t);
                wfLoadedTransitions = conv.transitions;

                if (t.steps && t.steps.length > 0) {
                    t.steps.forEach(step => addStep(step));
                } else {
                    addStep();
                }

                new bootstrap.Modal(document.getElementById('templateModal')).show();

                // 🆕 فاز ۶: قالبِ قدیمی اگر مرحلهٔ «رد → بازگشت به ایجادکننده» داشت،
                // این حالت معادلِ مستقیم در گراف ندارد — کاربر باید آن یال را
                // دستی روی بوم بکشد (مثلاً به مرحله‌ای با مسئولِ «ایجادکننده»)
                if (conv.creatorRejectSteps.length) {
                    showToast('توجه: مرحلهٔ ' + conv.creatorRejectSteps.map(o => toFa(o)).join('، ') +
                        ' در قالبِ قبلی «در صورتِ رد → بازگشت به ایجادکننده» بود؛ این حالت دیگر خودکار نیست — روی بوم، یالِ رد را دستی به مرحلهٔ دلخواه وصل کنید.', 'warning');
                }
            } catch (e) {
                console.error(e);
                showToast('خطا در بارگذاری اطلاعات', 'error');
            }
        }
        // ─── افزودن مرحله ────────────────────────────────
        let stepPickers = {}; // stepId -> picker instance
        let stepOriginalData = {}; // stepId -> { type, value } (برای حالت ویرایش‌نشده)
        let stepChecklists = {}; // stepId -> [{tempId, title, description}]
        let stepCanvasPos = {}; // stepId -> {x,y} یا null (فاز ۶ — جایِ گره رویِ بوم)
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
            // 🆕 فاز ۶: جایِ گرهٔ این مرحله رویِ بوم (اگر از قبل ذخیره شده بود) —
            // برایِ ساختِ اولیهٔ بوم در wfSyncFromForm استفاده می‌شود
            stepCanvasPos[stepId] = (stepData && stepData.canvas_x != null && stepData.canvas_y != null)
                ? { x: parseInt(stepData.canvas_x, 10), y: parseInt(stepData.canvas_y, 10) } : null;
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
                           placeholder="نام مرحله"
                           oninput="if(typeof wfPatchNodeDebounced==='function') wfPatchNodeDebounced('${stepId}')" required>

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

                    <button type="button" class="sr-checklist-toggle" id="scl-toggle-${stepId}" onclick="toggleStepChecklist('${stepId}')" title="چک‌لیست این مرحله">
                        <i class="bi bi-check2-square"></i> <span id="scl-count-${stepId}">چک‌لیست</span>
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
                </div>`;

            document.getElementById('stepsList').insertAdjacentHTML('beforeend', html);

            // 🆕 چک‌لیست این مرحله (عنوان + توضیحات، هر دو اختیاری در ورودی اما عنوان الزامی برای ثبت)
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
                placeholder: assigneePlaceholder,
                onSelect: () => { if (typeof wfPatchNode === 'function') wfPatchNode(stepId); }
            });

            // 🆕 اگر این مرحله از نوع «ایجادکننده» است، picker را غیرفعال کن
            if (stepData && stepData.assignee_type === 'creator') {
                const cb = document.querySelector(`.step-item[data-step-id="${stepId}"] .step-creator-toggle`);
                if (cb) toggleCreatorMode(stepId, cb);
            }

            updateStepNumbers();
            initDragAndDrop();

            // فاز ۶: افزودنِ تعاملیِ یک مرحله (توسط خودِ کاربر، نه بارگذاریِ
            // قالبِ ذخیره‌شده) → فقط همین یک گره را به بوم اضافه کن، بدونِ
            // دست‌زدن به جای گره‌هایِ دیگر (حالتِ بارگذاریِ کامل با رویدادِ
            // shown.bs.modal و wfSyncFromForm انجام می‌شود)
            if (!stepData && typeof wfAddNodeForStep === 'function') wfAddNodeForStep(stepId);
        }
        /* توابعِ آبشاری/موازی/انشعابِ تک‌مقصدیِ قدیمی (setStepMode, setAllModes,
           toggleStepDecision, onDecisionToggle, onRejectKindChange,
           markBranchToggle, refreshDecisionTargets, updateExecPreview) طبقِ
           فازِ ۶ کاملاً حذف شدند — ترتیب/هم‌زمانی/انشعاب از این به بعد فقط
           با یال‌هایِ رسم‌شده رویِ بومِ Drawflow تعیین می‌شود. */
        /* ═══════════════════════════════════════════════════════════════
           فاز ۶ — بوم «مسیر و انشعاب» با Drawflow، این‌بار به‌عنوانِ تنها
           منبعِ حقیقتِ ترتیب/هم‌زمانی/انشعاب (نه یک لایهٔ نمایشیِ روی یک
           مدلِ فرمیِ جدا). جدولِ workflow_step_transitions مستقیماً از
           یال‌هایِ این بوم ساخته می‌شود؛ saveTemplate آن را می‌خواند.
           ─ گره «شروع»: بدونِ ورودی، یک خروجی که می‌تواند هم‌زمان به چند
             مرحلهٔ «بدونِ پیش‌نیاز» وصل شود (یعنی آن مرحله‌ها همان اولِ
             روتین فعال می‌شوند).
           ─ گره مرحله: یک ورودی (که می‌تواند از چند مبدأ هم‌زمان خط بگیرد
             → یعنی «هم‌گرایی/join»: آن مرحله تا تکمیلِ همهٔ مبدأها منتظر
             می‌ماند)، و یک خروجی («همیشه») یا دو خروجی اگر تیکِ «نقطهٔ
             تصمیم» رویِ گره زده شود (خروجیِ ۱ = تأیید، خروجیِ ۲ = رد). از
             یک خروجی می‌توان هم‌زمان چند خط به چند مقصد کشید → یعنی
             «فورک/موازی‌سازی».
           ─ شمارهٔ مرحله (step_order) از رویِ ترتیبِ ردیف‌ها در فهرستِ
             مراحل (بالا) تعیین می‌شود، نه از رویِ جایِ گره — پس جابه‌جا
             کردنِ گره‌ها رویِ بوم هیچ‌وقت شماره/ترتیبِ مراحل را به‌هم
             نمی‌ریزد؛ فقط x/y (ظاهر) ذخیره می‌شود.
           ═══════════════════════════════════════════════════════════════ */
        let wfEditor = null;
        let wfStartId = null;
        let wfSyncing = false;        // جلوگیری از حلقهٔ رویدادها هنگام بازسازی
        let wfCanvasToFormTimer = null;
        let wfNodeIdByStepId = {};    // stepId → drawflow node id
        let wfPatchTimers = {};
        let wfCurrentTransitions = []; // [{from_step_order|null, to_step_order, condition}] — خروجیِ wfCanvasToForm
        let wfLoadedTransitions = null; // یال‌هایِ بارگذاری‌شده از سرور (view/edit/redefine) — فقط برایِ ساختِ اولیهٔ بوم

        // برچسب مسئول یک مرحله برای نمایش در گوشهٔ گره
        function wfStepAssigneeLabel(stepId) {
            const row = document.querySelector(`.step-item[data-step-id="${stepId}"]`);
            if (!row) return '—';
            if (row.dataset.creatorMode === '1') return '↩ ایجادکننده';
            const picked = stepPickers[stepId] ? stepPickers[stepId].getValue() : null;
            let type, value;
            if (picked && picked.value && picked.value !== '__all__' && picked.value !== '__all_users__') {
                type = picked.type; value = picked.value;
            } else {
                const orig = stepOriginalData[stepId] || {};
                type = orig.type; value = orig.value;
            }
            if (!value) return '—';
            if (type === 'user') {
                const u = (wfUsers || []).find(x => String(x.id) === String(value));
                if (!u) return 'فرد';
                const nm = (u.name || `${u.first_name || ''} ${u.last_name || ''}`).trim();
                return 'فرد: ' + (nm || ('کاربر ' + value));
            }
            if (type === 'section') return 'واحد: ' + (wfSectionMap[value] || value);
            return '—';
        }

        // به‌روزرسانی درجای نام/مسئول یک گره (بدون بازسازی کل بوم)
        function wfPatchNode(stepId) {
            const nid = wfNodeIdByStepId[stepId];
            if (!nid) return;
            const row = document.querySelector(`.step-item[data-step-id="${stepId}"]`);
            if (!row) return;
            const order = [...document.querySelectorAll('.step-item')].indexOf(row) + 1;
            const nameEl = document.querySelector('#node-' + nid + ' .wf-node-name');
            const asgEl = document.querySelector('#node-' + nid + ' .wf-node-assignee');
            if (nameEl) {
                const nm = (row.querySelector('.step-name')?.value || '').trim();
                nameEl.textContent = nm || ('مرحلهٔ ' + toFa(order));
            }
            if (asgEl) asgEl.textContent = wfStepAssigneeLabel(stepId);
            if (typeof wfBuildNarrative === 'function') wfBuildNarrative();
        }

        function wfPatchNodeDebounced(stepId) {
            clearTimeout(wfPatchTimers[stepId]);
            wfPatchTimers[stepId] = setTimeout(() => wfPatchNode(stepId), 150);
        }

        function wfInit() {
            const el = document.getElementById('wfCanvas');
            if (!el || typeof Drawflow === 'undefined' || wfEditor) return;
            wfEditor = new Drawflow(el);
            wfEditor.reroute = true;             // دوبار کلیک روی خط → افزودن نقطهٔ خم
            wfEditor.reroute_fix_curvature = true;
            wfEditor.force_first_input = false;
            wfEditor.start();

            wfEditor.on('connectionCreated', () => { if (!wfSyncing) wfScheduleCanvasToForm(); });
            wfEditor.on('connectionRemoved', () => { if (!wfSyncing) wfScheduleCanvasToForm(); });
            wfEditor.on('nodeMoved', () => { if (!wfSyncing) wfScheduleCanvasToForm(); });

            // تیک «نقطهٔ تصمیم» داخل گره‌ها (delegation)
            el.addEventListener('change', function (e) {
                if (wfSyncing || !e.target.classList.contains('wf-dec-chk')) return;
                const nodeEl = e.target.closest('.drawflow-node');
                if (!nodeEl) return;
                const nid = parseInt(String(nodeEl.id).replace('node-', ''), 10);
                wfSetNodeDecision(nid, e.target.checked);
                wfScheduleCanvasToForm();
            });

            // کلیک راست روی یک خط → حذف آن خط
            el.addEventListener('contextmenu', function (e) {
                const conn = e.target.closest('.connection');
                if (!conn) return;
                e.preventDefault();
                const cls = conn.getAttribute('class') || '';
                const mo = cls.match(/node_out_node-(\d+)/), mi = cls.match(/node_in_node-(\d+)/);
                const oc = cls.match(/output_\d+/), ic = cls.match(/input_\d+/);
                if (mo && mi && oc && ic) {
                    wfEditor.removeSingleConnection(mo[1], mi[1], oc[0], ic[0]);
                    wfScheduleCanvasToForm();
                }
            });

            // Escape → فقط خروج از تمام‌صفحه‌ی نمودار، نه بستن کل مودال
            // «تعریف روتین جدید» (که #templateModal یه مودال Bootstrapه و
            // خود Bootstrap هم یه listener Escape داره که با هر Escape کل
            // مودال رو می‌بنده و اطلاعات واردشده رو می‌پرونه). این‌جا با
            // capture:true زودتر از listener Bootstrap اجرا می‌شه و با
            // stopPropagation جلوی رسیدن رویداد به اون رو می‌گیره — پس
            // Escape وقتی نمودار تمام‌صفحه‌ست، فقط از تمام‌صفحه خارج می‌کنه.
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && document.getElementById('wfCanvasWrap')?.classList.contains('wf-canvas-fs')) {
                    e.stopPropagation();
                    e.preventDefault();
                    wfToggleFullscreen(false);
                }
            }, true);

            // 🆕 زوم با چرخ وسط موس — خود Drawflow از قبل فقط با
            // Ctrl+چرخ زوم می‌کنه (zoom_enter داخل کتابخونه)؛ این‌جا حالت
            // بدون Ctrl رو هم اضافه می‌کنیم تا اسکرول سادهٔ چرخ موس هم
            // زوم/بک‌زوم کنه، هم توی حالت عادی هم تمام‌صفحه (چون این
            // لیسنر روی خود #wfCanvas هست، نه روی wrapper تمام‌صفحه).
            // passive:false لازمه وگرنه مرورگر e.preventDefault() رو
            // نادیده می‌گیره و صفحه هم اسکرول می‌کنه.
            el.addEventListener('wheel', function (e) {
                if (e.ctrlKey || !wfEditor) return; // حالت Ctrl رو خود Drawflow هندل می‌کنه
                e.preventDefault();
                if (e.deltaY > 0) wfEditor.zoom_out(); else wfEditor.zoom_in();
            }, { passive: false });
        }

        function wfToggleFullscreen(force) {
            const wrap = document.getElementById('wfCanvasWrap');
            const btn = document.getElementById('wfFsBtn');
            if (!wrap) return;
            const on = (typeof force === 'boolean') ? force : !wrap.classList.contains('wf-canvas-fs');
            wrap.classList.toggle('wf-canvas-fs', on);
            document.body.style.overflow = on ? 'hidden' : '';
            if (btn) btn.innerHTML = on
                ? '<i class="bi bi-fullscreen-exit"></i>'
                : '<i class="bi bi-arrows-fullscreen"></i>';
            btn?.setAttribute('title', on ? 'خروج از تمام‌صفحه' : 'تمام‌صفحه');
        }

        function wfNodeHtml(order, name, isDecision, assigneeLabel) {
            return `<div class="wf-node-body">
                <div class="wf-node-title">${toFa(order)}. <span class="wf-node-name">${escHtml(name || ('مرحلهٔ ' + toFa(order)))}</span></div>
                <label><input type="checkbox" class="wf-dec-chk" ${isDecision ? 'checked' : ''}> شرط مسیر (تأیید/رد)</label>
                <div class="wf-node-assignee">${escHtml(assigneeLabel || '—')}</div>
            </div>`;
        }

        // فهرست مراحل فرم به‌ترتیب فعلی (شمارهٔ مرحله = جایِ ردیف در این فهرست)
        function wfFormSteps() {
            return [...document.querySelectorAll('.step-item')].map((el, i) => ({
                el, stepId: el.dataset.stepId, order: i + 1,
                name: (el.querySelector('.step-name')?.value || '').trim()
            }));
        }

        function wfStepOrderOf(stepId) {
            const items = [...document.querySelectorAll('.step-item')];
            const idx = items.findIndex(el => el.dataset.stepId === stepId);
            return idx >= 0 ? idx + 1 : null;
        }

        // توضیح متنی چگونگی اجرای روتین — مستقیماً از روی یال‌های بوم (wfCurrentTransitions) ساخته می‌شود
        function wfBuildNarrative() {
            const box = document.getElementById('wfNarrative');
            if (!box) return;
            const steps = wfFormSteps();
            if (!steps.length) { box.innerHTML = '<div>هنوز مرحله‌ای تعریف نشده است.</div>'; return; }

            const nameByOrder = {};
            steps.forEach(s => { nameByOrder[s.order] = s.name || ('بی‌نام'); });
            const nm = o => 'مرحلهٔ ' + toFa(o) + (nameByOrder[o] ? ' «' + nameByOrder[o] + '»' : '');

            const lines = [];
            const starts = steps.filter(s => !wfCurrentTransitions.some(t => t.to_step_order === s.order));
            lines.push(starts.length
                ? 'شروع روتین: ' + starts.map(s => nm(s.order)).join(' و ') + (starts.length > 1 ? ' هم‌زمان فعال می‌شوند.' : ' فعال می‌شود.')
                : 'هشدار: هیچ مرحله‌ای بدون پیش‌نیاز نیست — این روتین هیچ‌وقت شروع نمی‌شود!');

            steps.forEach(s => {
                const outs = wfCurrentTransitions.filter(t => t.from_step_order === s.order);
                if (!outs.length) { lines.push(nm(s.order) + ': پایانِ یک مسیر (بعد از آن مرحلهٔ دیگری فعال نمی‌شود).'); return; }
                const approve = outs.filter(t => t.condition === 'approve');
                const reject = outs.filter(t => t.condition === 'reject');
                const always = outs.filter(t => t.condition === 'always');
                if (approve.length || reject.length) {
                    const a = approve.length ? approve.map(t => nm(t.to_step_order)).join(' و ') : 'پایان روتین';
                    const r = reject.length ? reject.map(t => nm(t.to_step_order)).join(' و ') : 'پایان روتین';
                    lines.push(nm(s.order) + ' یک «نقطهٔ تصمیم» است؛ در صورت تأیید → ' + a + '، در صورت رد → ' + r + '.');
                } else if (always.length) {
                    lines.push('پس از تکمیل ' + nm(s.order) + ' → ' + always.map(t => nm(t.to_step_order)).join(' و ') +
                        (always.length > 1 ? ' هم‌زمان فعال می‌شوند.' : ' فعال می‌شود.'));
                }
            });

            // هم‌گرایی‌ها (join): مرحله‌ای که چند مبدأ هم‌زمان دارد
            steps.forEach(s => {
                const ins = wfCurrentTransitions.filter(t => t.to_step_order === s.order && t.condition === 'always');
                if (ins.length > 1) {
                    lines.push(nm(s.order) + ' تا تکمیلِ همهٔ این مراحل صبر می‌کند: ' + ins.map(t => nm(t.from_step_order)).join('، ') + '.');
                }
            });

            lines.push('پایان روتین: وقتی هیچ مرحلهٔ در انتظار/فعالی باقی نماند.');
            box.innerHTML = lines.map((l, i) => `<div><b>${toFa(i + 1)}.</b> ${escHtml(l)}</div>`).join('');
        }

        // ساختِ یک گرهِ تازه رویِ بوم برایِ یک مرحله — بدونِ دست‌زدن به گره‌هایِ دیگر
        function wfAddNodeForStep(stepId) {
            wfInit();
            if (!wfEditor || wfNodeIdByStepId[stepId]) return;
            const order = wfStepOrderOf(stepId);
            const saved = stepCanvasPos[stepId];
            let x, y;
            if (saved) {
                x = saved.x; y = saved.y;
            } else {
                // زیرِ پایین‌ترین گرهٔ موجود قرار بگیرد تا هیچ گرهٔ دیگری جابه‌جا نشود
                let maxY = 20;
                try {
                    const data = wfEditor.export().drawflow.Home.data;
                    Object.values(data).forEach(n => { if (n.data && n.data.stepId) maxY = Math.max(maxY, n.pos_y); });
                } catch (e) { /* بومِ خالی */ }
                x = 30; y = maxY + 120;
            }
            wfSyncing = true;
            try {
                const nid = wfEditor.addNode('step', 1, 1, x, y, 'wf-step',
                    { stepId }, wfNodeHtml(order, '', false, wfStepAssigneeLabel(stepId)));
                wfNodeIdByStepId[stepId] = nid;
            } catch (e) { console.error('wfAddNodeForStep', e); }
            finally { wfSyncing = false; }
            wfCanvasToForm();
        }

        // حذفِ گرهِ یک مرحله از بوم — بدونِ دست‌زدن به گره‌هایِ دیگر
        function wfRemoveNodeForStep(stepId) {
            const nid = wfNodeIdByStepId[stepId];
            if (!wfEditor || !nid) return;
            wfSyncing = true;
            try { wfEditor.removeNodeId('node-' + nid); } catch (e) { console.error('wfRemoveNodeForStep', e); }
            finally { wfSyncing = false; }
            delete wfNodeIdByStepId[stepId];
            wfCanvasToForm();
        }

        // بازسازیِ کاملِ بوم — فقط برایِ ساختِ اولیه (بازِشدنِ مودال) یا دکمهٔ
        // دستیِ «بازچینش»؛ هیچ‌وقت از addStep/removeStep صدا زده نمی‌شود چون
        // چینشِ دستیِ کاربر را از بین می‌برد.
        function wfSyncFromForm() {
            wfInit();
            if (!wfEditor) return;
            wfSyncing = true;
            try {
                wfEditor.clear();
                wfNodeIdByStepId = {};
                const steps = wfFormSteps();
                wfStartId = wfEditor.addNode('start', 0, 1, 30, 20, 'wf-fixed', {}, '<div class="wf-node-body"><b>شروع</b></div>');

                // یال‌ها: اگر از قبل روی خودِ بوم چیزی ساخته/ویرایش شده (wfCurrentTransitions)
                // همان حفظ شود؛ وگرنه (اولین‌بار) از دادهٔ بارگذاری‌شدهٔ سرور استفاده کن
                const trans = (wfCurrentTransitions && wfCurrentTransitions.length) ? wfCurrentTransitions : (wfLoadedTransitions || []);
                const outByOrder = {}; // order → [{to,cond}]
                trans.forEach(t => {
                    const key = (t.from_step_order === null || t.from_step_order === undefined) ? 'start' : t.from_step_order;
                    (outByOrder[key] = outByOrder[key] || []).push(t);
                });
                const isDecisionOrder = o => (outByOrder[o] || []).some(t => t.condition === 'approve' || t.condition === 'reject');

                const idByOrder = {};
                steps.forEach((s, i) => {
                    const dec = isDecisionOrder(s.order);
                    const pos = stepCanvasPos[s.stepId];
                    const x = pos ? pos.x : 30;
                    const y = pos ? pos.y : (110 + i * 120);
                    const nid = wfEditor.addNode('step', 1, dec ? 2 : 1, x, y,
                        'wf-step' + (dec ? ' wf-decision' : ''),
                        { stepId: s.stepId }, wfNodeHtml(s.order, s.name, dec, wfStepAssigneeLabel(s.stepId)));
                    idByOrder[s.order] = nid;
                    wfNodeIdByStepId[s.stepId] = nid;
                });

                // رسمِ یال‌ها از رویِ داده‌یِ بارگذاری‌شده
                trans.forEach(t => {
                    const toNid = idByOrder[t.to_step_order];
                    if (!toNid) return;
                    const fromNid = (t.from_step_order === null || t.from_step_order === undefined) ? wfStartId : idByOrder[t.from_step_order];
                    if (!fromNid) return;
                    const outKey = (t.condition === 'reject') ? 'output_2' : 'output_1';
                    try { wfEditor.addConnection(fromNid, toNid, outKey, 'input_1'); } catch (e) { /* یالِ تکراری/نامعتبر */ }
                });

                // اگر هیچ دادهٔ بارگذاری‌شده‌ای نبود (قالبِ تازه) و فقط یک مرحله
                // هست، آن را خودکار به «شروع» وصل کن تا بومِ خالی سردرگم‌کننده نباشد
                if (!trans.length && steps.length === 1) {
                    try { wfEditor.addConnection(wfStartId, idByOrder[steps[0].order], 'output_1', 'input_1'); } catch (e) {}
                }
            } catch (e) {
                console.error('wfSyncFromForm', e);
            } finally {
                wfSyncing = false;
            }
            wfCanvasToForm();
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

        // خواندنِ وضعیتِ کاملِ بوم → wfCurrentTransitions (یال‌ها) + stepCanvasPos (جایِ گره‌ها).
        // دیگر هیچ فیلدِ فرمیِ جداگانه‌ای نمی‌نویسد و ترتیبِ ردیف‌هایِ فهرستِ
        // مراحل را هم جابه‌جا نمی‌کند — شماره‌گذاری همیشه از رویِ فهرست است.
        function wfCanvasToForm() {
            if (!wfEditor) return;
            let data;
            try { data = wfEditor.export().drawflow.Home.data; } catch (e) { return; }

            const stepNodes = Object.values(data).filter(n => n.data && n.data.stepId);
            const orderOfNode = {}; // nodeId → step_order
            stepNodes.forEach(n => {
                const ord = wfStepOrderOf(n.data.stepId);
                if (ord) orderOfNode[n.id] = ord;
                // جایِ گره را هم ذخیره کن تا هنگامِ ذخیرهٔ قالب فرستاده شود
                stepCanvasPos[n.data.stepId] = { x: Math.round(n.pos_x), y: Math.round(n.pos_y) };
            });

            const transitions = [];

            // یال‌ها را از سمتِ «خروجیِ» هر گرهِ مبدأ می‌سازیم:
            // برایِ هر گرهِ مبدأ (مرحله یا «شروع»)، هر خروجی‌اش را می‌خوانیم.
            const allNodes = Object.values(data);
            allNodes.forEach(n => {
                const isStart = n.id === wfStartId;
                const fromOrder = isStart ? null : orderOfNode[n.id];
                if (!isStart && !fromOrder) return;
                const outs = n.outputs || {};
                const outKeys = Object.keys(outs).sort(); // output_1, output_2, ...
                const isDecision = !isStart && outKeys.length >= 2;
                outKeys.forEach((key, idx) => {
                    const cond = isDecision ? (idx === 0 ? 'approve' : 'reject') : 'always';
                    (outs[key].connections || []).forEach(c => {
                        const toOrder = orderOfNode[c.node];
                        if (!toOrder) return;
                        transitions.push({ from_step_order: fromOrder, to_step_order: toOrder, condition: cond });
                    });
                });
            });
            wfCurrentTransitions = transitions;

            if (typeof wfBuildNarrative === 'function') wfBuildNarrative();
        }

        // اعتبارسنجیِ سمتِ کاربر، همتایِ validateStepTransitions در بک‌اند —
        // پیامِ خطا را برمی‌گرداند یا null اگر معتبر بود
        function wfValidateGraph() {
            const steps = wfFormSteps();
            if (!steps.length) return null;
            const incoming = {};
            wfCurrentTransitions.forEach(t => {
                (incoming[t.to_step_order] = incoming[t.to_step_order] || []).push(t.condition);
            });
            for (const ord in incoming) {
                const conds = incoming[ord];
                const hasConditional = conds.some(c => c !== 'always');
                if (hasConditional && conds.length > 1) {
                    const nm = steps.find(s => s.order === Number(ord));
                    return `مرحلهٔ ${toFa(ord)}${nm && nm.name ? ' «' + nm.name + '»' : ''} هم مقصدِ یک تصمیم (تأیید/رد) است هم مقصدِ یالِ دیگری — این حالت مجاز نیست؛ روی بوم یکی از یال‌های ورودی آن را حذف کنید.`;
                }
            }
            const hasStart = steps.some(s => !incoming[s.order]);
            if (!hasStart) {
                return 'هیچ مرحله‌ای بدون پیش‌نیاز (نقطهٔ شروع) وجود ندارد — حداقل یک مرحله باید بدون یالِ ورودی باشد تا روتین بتواند شروع شود.';
            }
            return null;
        }

        function wfZoom(delta) { if (wfEditor) { delta > 0 ? wfEditor.zoom_in() : wfEditor.zoom_out(); } }
        function wfZoomReset() { if (wfEditor) wfEditor.zoom_reset(); }

        // چیدمانِ گره‌ها را به یک ستونِ مرتب برمی‌گرداند؛ یال‌ها/داده‌ها حفظ می‌شوند
        function wfAutoArrange() {
            Object.keys(stepCanvasPos).forEach(stepId => { stepCanvasPos[stepId] = null; });
            wfSyncFromForm();
        }

        // ─── حذف مرحله ───────────────────────────────────
        function removeStep(btn) {
            const stepEl = btn.closest('.step-item');
            const stepId = stepEl.dataset.stepId;
            delete stepPickers[stepId];
            delete stepOriginalData[stepId];
            delete stepChecklists[stepId];
            delete stepCanvasPos[stepId];
            stepEl.remove();
            updateStepNumbers();
            // فاز ۶: فقط همین یک گره از بوم حذف شود — بقیهٔ گره‌ها/جایشان دست‌نخورده می‌مانند
            if (typeof wfRemoveNodeForStep === 'function') wfRemoveNodeForStep(stepId);
        }

        // ─── چک‌لیست مرحله ────────────────────────────────
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
            // جابه‌جاییِ ردیف‌ها (درگ‌ودراپ) شمارهٔ مرحله‌ها را عوض می‌کند —
            // نودهای بوم سرِ جایشان می‌مانند، فقط باید نگاشتِ شماره↔یال دوباره
            // محاسبه و متنِ گره‌ها (شماره) به‌روزرسانی شود
            document.querySelectorAll('.step-item').forEach((el, i) => {
                const stepId = el.dataset.stepId;
                const nid = wfNodeIdByStepId[stepId];
                if (nid) {
                    const t = document.querySelector('#node-' + nid + ' .wf-node-title');
                    if (t) t.innerHTML = toFa(i + 1) + '. <span class="wf-node-name">' + escHtml(el.querySelector('.step-name')?.value.trim() || ('مرحلهٔ ' + toFa(i + 1))) + '</span>';
                }
            });
            if (typeof wfCanvasToForm === 'function') wfCanvasToForm();
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

            // فاز ۶: آخرین وضعیت بوم را (بدون منتظر debounce) در transitions/جای‌گره‌ها بنویس
            clearTimeout(wfCanvasToFormTimer);
            if (typeof wfCanvasToForm === 'function' && wfEditor) wfCanvasToForm();

            const stepItems = document.querySelectorAll('.step-item');
            if (stepItems.length === 0) {
                showToast('حداقل یک مرحله تعریف کنید', 'warning');
                return;
            }

            const graphError = wfValidateGraph();
            if (graphError) {
                showToast(graphError, 'warning');
                return;
            }

            const steps = [];
            let valid = true;

            stepItems.forEach((item, i) => {
                const stepId = item.dataset.stepId;
                const sName = item.querySelector('.step-name').value.trim();
                const sTime = item.querySelector('.step-time').value;

                // 🆕 چک‌لیست این مرحله — فقط آیتم‌هایی که عنوان دارند
                const stepChecklistItems = (stepChecklists[stepId] || [])
                    .filter(it => it.title && it.title.trim())
                    .map(it => ({
                        title: it.title.trim(),
                        description: (it.description || '').trim()
                    }));

                // فاز ۶: جایِ گرهٔ این مرحله رویِ بوم (برایِ باز نشدنِ چینش در دفعهٔ بعد)
                const pos = stepCanvasPos[stepId];

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
                        checklist_items: stepChecklistItems,
                        canvas_x: pos ? pos.x : null,
                        canvas_y: pos ? pos.y : null
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
                    checklist_items: stepChecklistItems,
                    canvas_x: pos ? pos.x : null,
                    canvas_y: pos ? pos.y : null
                });
            });

            if (!valid) {
                showToast('تمام فیلدها را تکمیل کنید', 'warning');
                return;
            }

            const payload = {
                name,
                description: document.getElementById('templateDescription').value.trim(),
                is_active: document.getElementById('templateActive').checked ? 1 : 0,
                engine_version: 2,
                transitions: wfCurrentTransitions,
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
                    // اگر بازتعریف بود و تیک غیرفعال‌سازی قالب قبلی زده شده بود
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
        // قبلا اینجا یک نسخهٔ محلی جداگانه بازتعریف می‌شد که آن را می‌پوشاند

        // ─── Utils ────────────────────────────────────────
        function escHtml(str) {
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        function escAttr(str) {
            return String(str || '').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        }

        // ─── بارگذاری مستقل کاربران و واحدها برای Picker مسئول هر مرحله ───
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

            // فاز ۴: هر بار مودال قالب کامل نمایش داده شد، بوم مسیر را از فهرست مراحل بساز
            const tm = document.getElementById('templateModal');
            if (tm) tm.addEventListener('shown.bs.modal', function () {
                setTimeout(function () { if (typeof wfSyncFromForm === 'function') wfSyncFromForm(); }, 60);
            });
        });
    </script>
    <?php include 'footer.php'; ?>
</body>

</html>