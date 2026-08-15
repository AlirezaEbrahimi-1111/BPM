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
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>جزئیات کار - سیستم مدیریت کار</title>

    <!-- Bootstrap 5 RTL -->
    <link href="<?= asset('../assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">

    <!-- فونت فارسی -->
    <link href="<?= asset('../assets/js/cdn/fonts/bootstrap-icons.woff2?30af91bf14e37666a085fb8a161ff36d') ?>" rel="stylesheet">
    <!-- تقویم شمسی -->
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/persian-datepicker.min.css') ?>">
    <script src="<?= asset('../assets/js/jalali.js') ?>"></script>
    <!-- Moment.js -->
    <script src="<?= asset('../assets/js/moment.min.js') ?>"></script>
    <!-- Moment Jalaali -->
    <script src="<?= asset('../assets/js/cdn/moment-jalaali.js') ?>"></script>
    <script src="<?= asset('../assets/js/config.js') ?>"></script>
    <script src="<?= asset('../assets/js/cdn/persian-date.min.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('../assets/css/persian-datepicker.css') ?>">
    <!-- Intro.js CSS -->
    <link rel="stylesheet" href="<?= asset('../assets/css/custom.css') ?>">
    <script src="<?= asset('../assets/js/cdn/jquery.min.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('../assets/css/deadline-toast.css') ?>">
    <style>
        /* دو ستون اطلاعات تسک */
        #taskInfo .row {
            margin: 0;
        }

        #taskInfo .col-6 {
            padding-top: 4px;
        }

        #taskInfo .border-end {
            border-color: #e9ecef !important;
        }

        :root[data-theme="dark"] #taskInfo .border-end {
            border-color: var(--border-soft) !important;
        }

        .checklist-detail-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 4px;
        }

        .viewer-name {
            transition: color .15s ease;
        }

        .viewer-name:hover {
            color: var(--icon-accent, #744CA4);
        }

        /* در پیکرِ افزودنِ دسترسی، hintِ زیرِ فیلد هیچ‌وقت پر نمی‌شه (فقط تویِ حالتِ
           واگذاریِ تک‌نفره پر می‌شه) — فضایِ خالیِ رزروشده‌اش رو جمع می‌کنیم */
        #addViewerPicker .ap-hint {
            display: none;
        }

        .checklist-detail-item:hover {
            background: #f8f9fa;
            border-radius: 6px;
        }

        :root[data-theme="dark"] .checklist-detail-item:hover {
            background: #232a3a;
        }

        .checklist-detail-item.done .chk-title {
            text-decoration: line-through;
            color: #9ca3af;
        }

        .chk-title {
            flex: 1;
            font-size: 0.88rem;
        }

        .chk-meta {
            font-size: 0.72rem;
            color: #9ca3af;
        }

        .chk-desc-inline {
            font-size: 0.78rem;
            color: #9ca3af;
            font-weight: normal;
        }

        .chk-desc-icon {
            font-size: 0.85rem;
            color: #6366f1;
            margin-inline-start: 4px;
        }

        .chk-desc-icon:hover {
            color: #4338ca;
        }

        .chk-desc-zone {
            display: inline;
        }

        .checklist-detail-item-wrap {
            border-bottom: 1px solid #f0f0f0;
            padding: 4px 0;
        }

        .checklist-detail-item-wrap:hover {
            background: #f8f9fa;
            border-radius: 6px;
        }

        :root[data-theme="dark"] .checklist-detail-item-wrap:hover {
            background: #232a3a;
        }

        /* ── کشوی یادداشت انجام (مدل درجا) ── */
        .chk-note-drawer {
            display: none;
            margin: 8px 0 4px 26px;
            padding: 11px 13px;
            background: #faf9ff;
            border: 1px solid #e5e0ff;
            border-radius: 10px;
            animation: chkSlide .18s ease;
        }

        :root[data-theme="dark"] .chk-note-drawer {
            background: #232032;
            border-color: #3a2f5c;
        }

        .checklist-detail-item-wrap.noting .chk-note-drawer {
            display: block;
        }

        @keyframes chkSlide {
            from {
                opacity: 0;
                transform: translateY(-4px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .chk-note-drawer label {
            display: block;
            font-size: .78rem;
            color: #4b5563;
            margin-bottom: 6px;
        }

        :root[data-theme="dark"] .chk-note-drawer label {
            color: var(--text-muted);
        }

        .chk-note-drawer textarea {
            width: 100%;
            font-family: inherit;
            font-size: .84rem;
            border: 1.5px solid #e5e7eb;
            border-radius: 8px;
            padding: 8px 10px;
            resize: vertical;
        }

        :root[data-theme="dark"] .chk-note-drawer textarea {
            background: var(--surface);
            border-color: var(--border-soft);
            color: var(--text-strong);
        }

        .chk-note-drawer textarea:focus {
            outline: none;
            border-color: #6c3ff4;
        }

        .chk-note-actions {
            display: flex;
            gap: 8px;
            margin-top: 9px;
        }

        /* یادداشت ثبت‌شده زیر آیتم */
        .chk-done-note {
            margin: 6px 0 4px 26px;
            padding: 7px 11px;
            background: #f3f4f6;
            border-right: 3px solid #ddd6fe;
            border-radius: 8px;
            font-size: .8rem;
            color: #4b5563;
        }

        :root[data-theme="dark"] .chk-done-note {
            background: #232a3a;
            color: var(--text-muted);
        }

        .chk-done-note b {
            color: #5b32d6;
            font-weight: 600;
        }

        @media (prefers-reduced-motion: reduce) {
            .chk-note-drawer {
                animation: none;
            }
        }

        @media (max-width: 576px) {
            #taskInfo .col-6 {
                flex: 0 0 100%;
                max-width: 100%;
                border-right: none !important;
                border-bottom: 1px solid #e9ecef;
                padding-bottom: 8px;
                margin-bottom: 8px;
            }

            :root[data-theme="dark"] #taskInfo .col-6 {
                border-bottom-color: var(--border-soft);
            }
        }
    </style>

</head>

<body>
    <?php include 'header.php'; ?>


    <div class="task-detail-container">
        <!-- هدر کار -->
        <div class="taskDetail-header">
            <div class="taskDetail-title" id="taskTitle"></div>
            <div class="taskDetail-title" id="taskDescription"
                style="font-size: .8rem; font-weight:400;margin-top:5px;color:#d8cbf7 ;"></div>
            <div class="taskDetail-meta" id="taskMeta"></div>
        </div>

        <!-- بدنه کار -->
        <div class="task-body">
            <!-- پیغام وضعیت -->
            <div id="statusMessage"></div>

            <!-- شمارنده تکمیل برای کارهای دوره‌ای -->
            <div id="completionCounter" style="display: none;"></div>

            <!-- اطلاعات اصلی -->
            <div class="info-section">
                <h5><i class="bi bi-info-circle ms-2"></i>اطلاعات کار</h5>
                <div id="taskInfo"></div>
            </div>



            <!-- توضیحات ارجاع -->
            <!--<div class="info-section" id="delegationSection" style="display: none;">-->
            <!--    <h5><i class="bi bi-arrow-right-circle ms-2"></i>توضیحات روند کار</h5>-->
            <!--    <div id="delegationNotes"></div>-->
            <!--</div>-->

            <!-- بخش فایل‌های پیوست -->
            <div class="attachments-section" id="attachmentsSection">
                <h5 class="accordion-toggle" id="attachmentsToggle" onclick="toggleAttachments()"
                    style="cursor:pointer; user-select:none; display:flex; align-items:center; justify-content:space-between;">
                    <span>
                        <i class="bi bi-paperclip ms-2"></i>فایل‌های پیوست
                        <span id="attachmentsCountBadge"
                            style="display:none; background:#744ca4; color:#fff; border-radius:50px; padding:1px 9px; font-size:0.75rem; margin-right:6px; font-weight:600;">
                        </span>
                    </span>
                    <i class="bi bi-chevron-down" id="attachmentsChevron" style="transition: transform 0.3s;"></i>
                </h5>

                <div id="attachmentsBody" style="display:none;">
                    <!-- منطقه آپلود — فقط برای assignee نمایش داده می‌شود -->
                    <div class="upload-area" id="uploadArea" style="display:none;">
                        <input type="file" id="fileInput" class="file-input-hidden"
                            accept=".jpg,.jpeg,.png,.pdf,.docx,.doc,.xls,.xlsx,.mp3,.m4a,.ogg" multiple>
                        <i class="bi bi-cloud-upload"></i>
                        <p><strong>فایل خود را اینجا رها کنید یا کلیک کنید</strong></p>
                        <small>فرمت‌های مجاز: jpg, png, pdf, docx, xlsx, mp3, m4a, ogg</small>
                        <P>(حداکثر 20MB)</P>
                        <div class="upload-progress" id="uploadProgress">
                            <div class="upload-progress-bar" id="uploadProgressBar"></div>
                        </div>
                    </div>

                    <!-- لیست فایل‌ها -->
                    <div id="attachmentsList" style="margin-top: 1rem;">
                        <div class="no-attachments">
                            <i class="bi bi-inbox"></i>
                            <p>هیچ فایلی پیوست نشده است</p>
                        </div>
                    </div>
                </div>
            </div>
            <!-- 🆕 چک‌لیست (فقط کار عادی) -->
            <div class="info-section" id="checklistDetailSection" style="display:none;">
                <div style="display:flex; align-items:center; gap:8px; margin-bottom:12px;">
                    <h5 style="margin:0;"><i class="bi bi-check2-square ms-2"></i>چک‌لیست</h5>
                    <div style="flex:1; margin:0 12px;">
                        <div class="progress" style="height:8px;">
                            <div class="progress-bar bg-success" id="checklistProgress" style="width:0%"></div>
                        </div>
                    </div>
                    <small class="text-muted" id="checklistProgressText">۰ از ۰</small>
                </div>

                <div id="checklistDetailItems"></div>

                <!-- افزودن آیتم - فقط تعریف‌کننده -->
                <div id="checklistLockNote" style="display:none; margin-top:8px; padding:8px 12px; background:var(--warning-box-bg); border:1px solid #fcd34d; border-radius:8px; font-size:0.8rem; color:var(--warning-box-text);">
                    <i class="bi bi-lock-fill me-1"></i>
                    این کار به پایان رسیده و چک‌لیست آن قفل شده است. امکان افزودن، ویرایش، حذف یا تغییر آیتم‌ها وجود ندارد.
                </div>
                <div id="addChecklistItemRow" style="display:none; margin-top:8px;">
                    <div style="display:flex; gap:8px;">
                        <input type="text" id="newDetailChecklistItem" class="form-control form-control-sm"
                            placeholder="افزودن آیتم..."
                            onkeydown="if(event.key==='Enter'){event.preventDefault();addDetailChecklistItem();}">
                        <button class="btn btn-outline-primary btn-sm" onclick="addDetailChecklistItem()" title="افزودن آیتم">
                            <i class="bi bi-plus"></i>
                        </button>
                    </div>
                </div>
            </div>
            <!-- تاریخچه -->
            <div class="info-section" id="historySection">
                <h5><i class="bi bi-clock-history ms-2"></i>تاریخچه فعالیت‌ها</h5>
                <div class="history-timeline" id="taskHistory"></div>
            </div>


            <!-- دکمه‌های عمل -->
            <div class="TDaction-buttons">
                <button type="button" class="btn btn-outline-secondary" onclick="goBackSmart();">
                    <i class="bi bi-arrow-right ms-2"></i>بازگشت
                </button>
                <button type="button" class="btn btn-primary" id="startBtn" onclick="startThisTask()"
                    style="display: none;">
                    <i class="bi bi-play-fill ms-2"></i>شروع کار
                </button>
                <button type="button" class="btn btn-primary" id="addDiscBtn" onclick="showAddDiscModal()"
                    style="display: none;">
                    <i class="bi bi-chat-left-text ms-2"></i>درج توضیح
                </button>
                <button type="button" class="btn btn-success" id="completeBtn" onclick="showCompleteDiscModal()"
                    style="display: none;">
                    <i class="bi bi-check-circle ms-2"></i>تکمیل کار
                </button>
                <button type="button" class="btn btn-success" id="approveBtn" style="display: none;"
                    onclick="showApproveModal()">
                    <i class="bi bi-check-circle-fill ms-2"></i>تأیید کار
                </button>

                <button type="button" class="btn btn-danger" id="rejectBtn" style="display: none;"
                    onclick="showRejectModal()">
                    <i class="bi bi-x-circle-fill ms-2"></i>رد کار
                </button>
                <button type="button" class="btn btn-warning" id="delegateBtn" onclick="showDelegateModal()"
                    style="display: none;">
                    <i class="bi bi-arrow-right-circle ms-2"></i>ارجاع کار
                </button>
                <button type="button" class="btn btn-warning" id="terminationRequestBtn"
                    onclick="showTerminationRequestModal()" style="display:none;">
                    <i class="bi bi-flag-fill ms-2"></i>درخواست اتمام کار
                </button>
                <button type="button" class="btn btn-danger" id="terminatePeriodBtn" onclick="showTerminatePeriodConfirm()"
                    style="display: none;">
                    <i class="bi bi-stop-circle ms-2"></i>اتمام دوره
                </button>
                <button type="button" class="btn btn-primary" id="clearOverdueBtn" onclick="showClearOverdueModal()"
                    style="display: none;">
                    <i class="bi bi-eraser ms-2"></i>رفع دوره‌های معوقه
                </button>
                <button type="button" class="btn btn-primary" id="editBtn" onclick="editTask()" style="display: none;">
                    <i class="bi bi-pencil ms-2"></i>ویرایش
                </button>
                <button type="button" class="btn btn-danger" id="deleteBtn" onclick="deleteTask()"
                    style="display: none;">
                    <i class="bi bi-trash ms-2"></i>حذف کار
                </button>
                <button type="button" class="btn btn-primary" id="redefineBtn" onclick="showRedefineModal()"
                    style="display: none;">
                    <i class="bi bi-arrow-repeat ms-2"></i>بازتعریف کار
                </button>

                <!-- ✅ تمدید دوره -->
                <button type="button" class="btn btn-info" id="renewalActionBtn" style="display: none;">
                    <i class="bi bi-arrow-repeat ms-2"></i><span id="renewalActionBtnText">تمدید دوره</span>
                </button>
                <button type="button" class="btn btn-outline-secondary" id="renewalPendingBadge"
                    style="display: none;" disabled>
                    <i class="bi bi-hourglass-split ms-2"></i>درخواست تمدید دوره در انتظار بررسی
                </button>
                <button type="button" class="btn btn-warning" id="reviewRenewalBtn" style="display: none;">
                    <i class="bi bi-eye ms-2"></i>بررسی درخواست تمدید دوره
                </button>
            </div>
        </div>

        <div class="quick-actions" style="margin-bottom: 3.4rem;">
            <!-- window.location.href='create-task.php' -->
            <button class="fab" onclick="showNewTaskModal()" title="کار جدید">
                <i class="bi bi-plus"></i>
            </button>

        </div>
        <!-- Modal ارجاع کار -->
        <div class="modal fade" id="delegateModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">ارجاع کار</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">ارجاع به کاربر</label>
                            <div id="delegatePicker"></div>
                        </div>
                        <!-- ✅ این کار موعد نداره (خودی بوده) — برایِ ارجاع باید موعد تعیین بشه -->
                        <div class="mb-3" id="delegateDueDateContainer" style="display:none;">
                            <label class="form-label required">موعد انجام</label>
                            <div class="persian-datepicker-wrapper" data-restrict-past="0">
                                <input type="text" id="delegateDueDate" class="persian-datepicker-input form-control"
                                    placeholder="انتخاب تاریخ..." readonly>
                                <div class="persian-datepicker">
                                    <div class="datepicker-header">
                                        <button type="button" class="datepicker-nav" data-action="prev">►</button>
                                        <span class="datepicker-current">-</span>
                                        <button type="button" class="datepicker-nav" data-action="next">◄</button>
                                    </div>
                                    <div class="datepicker-weekdays">
                                        <div class="datepicker-weekday">ش</div>
                                        <div class="datepicker-weekday">ی</div>
                                        <div class="datepicker-weekday">د</div>
                                        <div class="datepicker-weekday">س</div>
                                        <div class="datepicker-weekday">چ</div>
                                        <div class="datepicker-weekday">پ</div>
                                        <div class="datepicker-weekday">ج</div>
                                    </div>
                                    <div class="datepicker-days"></div>
                                    <button type="button" class="datepicker-today-btn">امروز</button>
                                </div>
                            </div>
                            <small class="form-text text-muted d-block">این کار تا الان موعد نداشته — چون به کسِ دیگه‌ای ارجاع می‌شه، تعیینِ موعد الزامیه.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">توضیحات ارجاع</label>
                            <textarea class="form-control" id="delegateNotes" rows="3"
                                placeholder="توضیحات خود را وارد کنید..."></textarea>
                        </div>
                        <div class="form-check mb-3" id="delegateShareHistoryWrap" style="display:none;">
                            <input class="form-check-input" type="checkbox" id="delegateShareHistory" checked>
                            <label class="form-check-label" for="delegateShareHistory">
                                تاریخچهٔ کار برای کاربران ارجاع‌شونده قابل نمایش باشد
                            </label>
                            <small class="form-text text-muted d-block">
                                فقط تعریف‌کنندهٔ کار می‌تواند این سیاست را تعیین کند. اگر غیرفعال شود، هر ارجاع‌شونده فقط از زمان ورود خودش به بعد را می‌بیند.
                            </small>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="button" class="btn btn-warning" onclick="submitDelegate()">ارجاع</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal fade" id="addDiscModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">درج توضیح کار</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">توضیحات تکمیلی</label>
                            <textarea class="form-control" id="addDiscNotes" rows="3"
                                placeholder="توضیحات خود را وارد کنید..."></textarea>
                        </div>

                        <!-- بخش آپلود فایل -->
                        <div class="mb-3">
                            <label class="form-label">پیوست فایل (اختیاری)</label>
                            <div class="upload-area-modal" id="uploadAreaModal">
                                <input type="file" id="fileInputModal" class="file-input-hidden"
                                    accept=".jpg,.jpeg,.png,.pdf,.docx,.doc,.xls,.xlsx,.mp3,.m4a,.ogg" multiple>
                                <i class="bi bi-paperclip"></i>
                                <p><small>کلیک کنید یا فایل را بکشید</small></p>
                                <small class="text-muted">حداکثر 20MB</small>
                            </div>
                            <div id="selectedFilesModal" class="mt-2"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="button" class="btn btn-primary" onclick="submitAddDiscWithFiles()">درج
                            توضیح</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal تأیید کار -->
        <div class="modal fade" id="approveModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title">تأیید کار</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            با تأیید این کار، به مرحله بعد یا تأیید نهایی ارسال می‌شود.
                        </div>
                        <div class="mb-3">
                            <label class="form-label">توضیحات تأیید (اختیاری)</label>
                            <textarea class="form-control" id="approveNotes" rows="3"
                                placeholder="توضیحات خود را وارد کنید..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="button" class="btn btn-warning" id="approveAndDelegateBtn"
                            onclick="showApproveAndDelegateFlow()" style="display:none;">
                            <i class="bi bi-arrow-right-circle me-2"></i>تأیید و ارجاع
                        </button>
                        <button type="button" class="btn btn-outline-primary" id="approveAndKeepBtn"
                            onclick="submitApproveAndKeep()" style="display:none;">
                            <i class="bi bi-bookmark-check me-2"></i>تأیید و نگهداری کار
                        </button>
                        <button type="button" class="btn btn-success" onclick="submitApprove()">
                            <i class="bi bi-check-circle me-2"></i>تأیید
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal رد کار -->
        <div class="modal fade" id="rejectModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title">رد کار</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            با رد این کار، به انجام‌دهنده قبلی برای اصلاح بازگردانده می‌شود.
                        </div>
                        <div class="mb-3">
                            <label class="form-label">دلیل رد کار *</label>
                            <textarea class="form-control" id="rejectNotes" rows="3"
                                placeholder="لطفاً دلیل رد کار را شرح دهید..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="button" class="btn btn-danger" onclick="submitReject()">
                            <i class="bi bi-x-circle me-2"></i>رد کار
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal تکمیل کار -->
        <div class="modal fade" id="completeDiscModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">تکمیل کار</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">توضیحات تکمیلی</label>
                            <textarea class="form-control" id="completeDiscNotes" rows="3"
                                placeholder="توضیحات خود را وارد کنید..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="button" class="btn btn-success" onclick="submitCompleteDisc()">
                            <i class="bi bi-check me-2"></i>تکمیل
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <!-- Modal بازتعریف کار -->
        <div class="modal fade" id="redefineModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">بازتعریف کار</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            با بازتعریف، یک کار جدید با همین مشخصات برای کاربر دیگری ایجاد می‌شود.
                        </div>

                        <div class="mb-3">
                            <label class="form-label">عنوان کار *</label>
                            <input type="text" class="form-control" id="redefineTitle" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">توضیحات</label>
                            <textarea class="form-control" id="redefineDescription" rows="3"></textarea>
                        </div>
                        <!-- ✅ فیلد تاریخ با ساختار کامل -->
                        <div class="mb-3" id="redefineDueDateContainer">
                            <label class="form-label">موعد انجام</label>
                            <div class="persian-datepicker-wrapper" data-restrict-past="0">
                                <input type="text" id="redefineDueDate" class="persian-datepicker-input form-control"
                                    placeholder="انتخاب تاریخ..." readonly>
                                <div class="persian-datepicker">
                                    <div class="datepicker-header">
                                        <button type="button" class="datepicker-nav" data-action="prev">►</button>
                                        <span class="datepicker-current">-</span>
                                        <button type="button" class="datepicker-nav" data-action="next">◄</button>
                                    </div>
                                    <div class="datepicker-weekdays">
                                        <div class="datepicker-weekday">ش</div>
                                        <div class="datepicker-weekday">ی</div>
                                        <div class="datepicker-weekday">د</div>
                                        <div class="datepicker-weekday">س</div>
                                        <div class="datepicker-weekday">چ</div>
                                        <div class="datepicker-weekday">پ</div>
                                        <div class="datepicker-weekday">ج</div>
                                    </div>
                                    <div class="datepicker-days"></div>
                                    <button type="button" class="datepicker-today-btn">امروز</button>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">واگذار به کاربر *</label>
                            <div id="redefinePicker"></div>
                        </div>

                        <div class="mb-3" id="redefineAttachmentsOption" style="display:none;">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="redefineIncludeAttachments" checked>
                                <label class="form-check-label" for="redefineIncludeAttachments">
                                    فایل‌های پیوست را به کار جدید منتقل کن
                                </label>
                            </div>
                            <small class="text-muted d-block mt-1" id="redefineAttachmentsCount"></small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="button" class="btn btn-primary" onclick="submitRedefine()">
                            <i class="bi bi-check-circle me-2"></i>ایجاد کار جدید
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div id="requestDeadlineModal" class="modal" style="display:none;">
            <div class="modal-content">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h5 style="margin: 0; color: var(--text-strong); font-weight: 600;">درخواست تمدید موعد انجام</h5>
                    <button onclick="closeModal('requestDeadlineModal')"
                        style="background: none; border: none; font-size: 24px; cursor: pointer; color: var(--text-muted); padding: 0; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">
                        ✕
                    </button>
                </div>

                <!-- موعد فعلی -->
                <div
                    style="margin-bottom: 15px; padding: 12px; background: var(--info-box-bg); border-radius: 5px; border-right: 4px solid #667eea;">
                    <strong style="color: var(--text-strong);" id="currentDeadlineLabel">موعد فعلی: -</strong>
                </div>

                <!-- انتخاب موعد جدید -->
                <div style="margin-bottom: 15px;">
                    <label class="form-label">موعد جدید را انتخاب کنید:</label>
                    <div class="persian-datepicker-wrapper">
                        <input type="text" id="newDeadline" class="persian-datepicker-input form-control"
                            placeholder="انتخاب تاریخ جدید..." readonly>
                        <div class="persian-datepicker">
                            <div class="datepicker-header">
                                <button type="button" class="datepicker-nav" data-action="prev">►</button>
                                <span class="datepicker-current">-</span>
                                <button type="button" class="datepicker-nav" data-action="next">◄</button>
                            </div>
                            <div class="datepicker-weekdays">
                                <div class="datepicker-weekday">ش</div>
                                <div class="datepicker-weekday">ی</div>
                                <div class="datepicker-weekday">د</div>
                                <div class="datepicker-weekday">س</div>
                                <div class="datepicker-weekday">چ</div>
                                <div class="datepicker-weekday">پ</div>
                                <div class="datepicker-weekday">ج</div>
                            </div>
                            <div class="datepicker-days"></div>
                            <button type="button" class="datepicker-today-btn">امروز</button>
                        </div>
                    </div>
                </div>

                <!-- دلیل درخواست -->
                <div style="margin-bottom: 15px;">
                    <label class="form-label">دلیل درخواست:</label>
                    <textarea id="extensionReason" rows="4"
                        placeholder="مثلاً: نیاز به وقت بیشتر برای تکمیل کار یا..."></textarea>
                </div>

                <!-- دکمه‌های عمل -->
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button onclick="submitDeadlineRequest()"
                        style="flex: 1; padding: 10px; border: none; border-radius: 5px; cursor: pointer; background: #667eea; color: white; font-weight: 600; transition: background 0.2s;">
                        ارسال درخواست
                    </button>
                    <button onclick="closeModal('requestDeadlineModal')"
                        style="flex: 1; padding: 10px; border: 1px solid var(--border-soft); background: var(--surface); border-radius: 5px; cursor: pointer; font-weight: 600; color: var(--text-muted); transition: all 0.2s;">
                        انصراف
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal تمدید ساعتی کار روتین -->
        <div id="workflowDeadlineModal" class="modal" style="display:none;">
            <div class="modal-content">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                    <h5 style="margin:0; color:var(--text-strong); font-weight:600;">تمدید موعد کار روتین</h5>
                    <button onclick="closeModal('workflowDeadlineModal')"
                        style="background:none; border:none; font-size:24px; cursor:pointer; color:var(--text-muted);">✕</button>
                </div>
                <div style="margin-bottom:15px; padding:12px; background:var(--info-box-bg); border-radius:5px; border-right:4px solid #667eea;">
                    <strong style="color:var(--text-strong);">موعد فعلی: </strong><span id="wfCurrentDeadline">-</span>
                </div>
                <div style="margin-bottom:15px;">
                    <label class="form-label">چند ساعت به موعد اضافه شود؟</label>
                    <input type="number" id="wfExtendHours" min="1" step="1" class="form-control"
                        placeholder="مثلاً: ۲۴" oninput="updateWfDeadlinePreview()">
                    <small class="text-muted">محدودیتی برای تعداد ساعت وجود ندارد.</small>
                </div>
                <div style="margin-bottom:15px; padding:12px; background:var(--warning-box-bg); border-radius:5px; border-right:4px solid #ffc107;">
                    <strong style="color:var(--warning-box-text);">موعد جدید: </strong><span id="wfNewDeadlinePreview">-</span>
                </div>
                <div style="margin-bottom:15px;">
                    <label class="form-label">دلیل تمدید:</label>
                    <textarea id="wfExtensionReason" rows="4" placeholder="دلیل نیاز به زمان بیشتر..."></textarea>
                </div>
                <div style="display:flex; gap:10px; margin-top:20px;">
                    <button onclick="submitWorkflowDeadlineRequest()"
                        style="flex:1; padding:10px; border:none; border-radius:5px; cursor:pointer; background:#667eea; color:white; font-weight:600;">
                        ارسال درخواست
                    </button>
                    <button onclick="closeModal('workflowDeadlineModal')"
                        style="flex:1; padding:10px; border:1px solid var(--border-soft); background:var(--surface); border-radius:5px; cursor:pointer; font-weight:600; color:var(--text-muted);">
                        انصراف
                    </button>
                </div>
            </div>
        </div>

        <!-- 2️⃣ Modal بررسی درخواست -->
        <div id="reviewDeadlineModal" class="modal" style="display:none;">
            <div class="modal-content">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h5 style="margin: 0; color: var(--text-strong); font-weight: 600;">بررسی درخواست تمدید موعد</h5>
                    <button onclick="closeModal('reviewDeadlineModal')"
                        style="background: none; border: none; font-size: 24px; cursor: pointer; color: var(--text-muted); padding: 0; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">
                        ✕
                    </button>
                </div>

                <!-- جزئیات -->
                <div style="margin-bottom: 15px;">
                    <strong style="color: #667eea;">درخواست‌کننده:</strong>
                    <span id="requesterName" style="margin-right: 8px; color: var(--text-strong);">-</span>
                </div>

                <!-- موعد فعلی -->
                <div
                    style="margin-bottom: 15px; padding: 12px; background: var(--info-box-bg); border-radius: 5px; border-right: 4px solid #667eea;">
                    <strong style="color: var(--text-strong);" id="currentDeadlineDisplay">موعد فعلی: -</strong>
                </div>

                <!-- موعد درخواستی -->
                <div
                    style="margin-bottom: 15px; padding: 12px; background: var(--warning-box-bg); border-radius: 5px; border-right: 4px solid #ffc107;">
                    <strong style="color: var(--warning-box-text);" id="requestedDeadlineDisplay">موعد درخواستی: -</strong>
                </div>

                <!-- دلیل -->
                <div style="margin-bottom: 15px;">
                    <strong style="color: #667eea;">دلیل درخواست:</strong>
                    <p id="extensionReasonDisplay"
                        style="margin: 8px 0 0 0; color: var(--text-muted); white-space: pre-wrap; line-height: 1.6;">-</p>
                </div>

                <!-- دکمه‌های عمل -->
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button onclick="approveDeadlineRequest()"
                        style="flex: 1; padding: 10px; border: none; border-radius: 5px; cursor: pointer; background: #28a745; color: white; font-weight: 600; transition: background 0.2s;">
                        ✓ تأیید درخواست
                    </button>
                    <button onclick="rejectDeadlineRequest()"
                        style="flex: 1; padding: 10px; border: none; border-radius: 5px; cursor: pointer; background: #dc3545; color: white; font-weight: 600; transition: background 0.2s;">
                        ✗ رد درخواست
                    </button>
                </div>

                <button onclick="closeModal('reviewDeadlineModal')"
                    style="width: 100%; padding: 10px; margin-top: 10px; border: 1px solid var(--border-soft); background: var(--surface); border-radius: 5px; cursor: pointer; font-weight: 600; color: var(--text-muted);">
                    بستن
                </button>
            </div>
        </div>
        <!-- Modal درخواست/اعمال تمدید دوره -->
        <div id="renewalModal" class="modal" style="display:none;">
            <div class="modal-content">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h5 style="margin: 0; color: var(--text-strong); font-weight: 600;" id="renewalModalTitle">تمدید دوره</h5>
                    <button onclick="closeModal('renewalModal')"
                        style="background: none; border: none; font-size: 24px; cursor: pointer; color: var(--text-muted); padding: 0; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">
                        ✕
                    </button>
                </div>

                <div style="margin-bottom: 15px; padding: 12px; background: var(--info-box-bg); border-radius: 5px; border-right: 4px solid #667eea;">
                    <strong style="color: var(--text-strong);">دوره: <span id="renewalPeriodLabel">-</span></strong>
                </div>

                <div style="margin-bottom: 15px;">
                    <label class="form-label">تاریخ شروع مجدد:</label>
                    <div class="persian-datepicker-wrapper">
                        <input type="text" id="renewalNewStartDate" class="persian-datepicker-input form-control"
                            placeholder="انتخاب تاریخ شروع..." readonly>
                        <div class="persian-datepicker">
                            <div class="datepicker-header">
                                <button type="button" class="datepicker-nav" data-action="prev">►</button>
                                <span class="datepicker-current">-</span>
                                <button type="button" class="datepicker-nav" data-action="next">◄</button>
                            </div>
                            <div class="datepicker-weekdays">
                                <div class="datepicker-weekday">ش</div>
                                <div class="datepicker-weekday">ی</div>
                                <div class="datepicker-weekday">د</div>
                                <div class="datepicker-weekday">س</div>
                                <div class="datepicker-weekday">چ</div>
                                <div class="datepicker-weekday">پ</div>
                                <div class="datepicker-weekday">ج</div>
                            </div>
                            <div class="datepicker-days"></div>
                            <button type="button" class="datepicker-today-btn">امروز</button>
                        </div>
                    </div>
                </div>

                <div style="margin-bottom: 15px;">
                    <label class="form-label">تاریخ پایان جدید (اختیاری):</label>
                    <div class="persian-datepicker-wrapper">
                        <input type="text" id="renewalNewEndDate" class="persian-datepicker-input form-control"
                            placeholder="در صورت نیاز انتخاب کنید..." readonly>
                        <div class="persian-datepicker">
                            <div class="datepicker-header">
                                <button type="button" class="datepicker-nav" data-action="prev">►</button>
                                <span class="datepicker-current">-</span>
                                <button type="button" class="datepicker-nav" data-action="next">◄</button>
                            </div>
                            <div class="datepicker-weekdays">
                                <div class="datepicker-weekday">ش</div>
                                <div class="datepicker-weekday">ی</div>
                                <div class="datepicker-weekday">د</div>
                                <div class="datepicker-weekday">س</div>
                                <div class="datepicker-weekday">چ</div>
                                <div class="datepicker-weekday">پ</div>
                                <div class="datepicker-weekday">ج</div>
                            </div>
                            <div class="datepicker-days"></div>
                            <button type="button" class="datepicker-today-btn">امروز</button>
                        </div>
                    </div>
                    <small class="text-muted">خالی بگذارید برای نامحدود (تا تمدیدِ بعدی)</small>
                </div>

                <div style="margin-bottom: 15px; padding: 10px; background: var(--info-box-bg); border-radius: 5px;">
                    <strong style="color: var(--text-strong);">سررسیدِ دورهٔ بعدی: <span id="renewalNextPeriodPreview">-</span></strong>
                </div>

                <div style="margin-bottom: 15px;">
                    <label class="form-label">دلیل (<span id="renewalReasonRequiredHint">اجباری</span>):</label>
                    <textarea id="renewalReason" rows="3" placeholder="مثلاً: ادامهٔ انجام دورهٔ کار..."></textarea>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button onclick="submitRenewal()" id="renewalSubmitBtn"
                        style="flex: 1; padding: 10px; border: none; border-radius: 5px; cursor: pointer; background: #667eea; color: white; font-weight: 600;">
                        ارسالِ درخواست
                    </button>
                    <button onclick="closeModal('renewalModal')"
                        style="flex: 1; padding: 10px; border: 1px solid var(--border-soft); background: var(--surface); border-radius: 5px; cursor: pointer; font-weight: 600; color: var(--text-muted);">
                        انصراف
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal بررسیِ درخواستِ تمدیدِ دوره -->
        <div id="reviewRenewalModal" class="modal" style="display:none;">
            <div class="modal-content">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h5 style="margin: 0; color: var(--text-strong); font-weight: 600;">بررسی درخواست تمدید دوره</h5>
                    <button onclick="closeModal('reviewRenewalModal')"
                        style="background: none; border: none; font-size: 24px; cursor: pointer; color: var(--text-muted); padding: 0; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">
                        ✕
                    </button>
                </div>

                <div style="margin-bottom: 15px;">
                    <strong style="color: #667eea;">درخواست‌کننده:</strong>
                    <span id="renewalRequesterName" style="margin-right: 8px; color: var(--text-strong);">-</span>
                </div>

                <div style="margin-bottom: 15px; padding: 12px; background: var(--warning-box-bg); border-radius: 5px; border-right: 4px solid #ffc107;">
                    <strong style="color: var(--warning-box-text);">شروعِ پیشنهادی: <span id="renewalReqStartDisplay">-</span></strong><br>
                    <strong style="color: var(--warning-box-text);">پایانِ پیشنهادی: <span id="renewalReqEndDisplay">-</span></strong>
                </div>

                <div style="margin-bottom: 15px;">
                    <strong style="color: #667eea;">دلیل درخواست:</strong>
                    <p id="renewalReasonDisplay" style="margin: 8px 0 0 0; color: var(--text-muted); white-space: pre-wrap; line-height: 1.6;">-</p>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button onclick="approveRenewalRequest()"
                        style="flex: 1; padding: 10px; border: none; border-radius: 5px; cursor: pointer; background: #28a745; color: white; font-weight: 600;">
                        ✓ تأیید درخواست
                    </button>
                    <button onclick="showRejectRenewalReason()"
                        style="flex: 1; padding: 10px; border: none; border-radius: 5px; cursor: pointer; background: #dc3545; color: white; font-weight: 600;">
                        ✗ رد درخواست
                    </button>
                </div>

                <button onclick="closeModal('reviewRenewalModal')"
                    style="width: 100%; padding: 10px; margin-top: 10px; border: 1px solid var(--border-soft); background: var(--surface); border-radius: 5px; cursor: pointer; font-weight: 600; color: var(--text-muted);">
                    بستن
                </button>
            </div>
        </div>
        <!-- 3️⃣ Modal دلیل رد درخواست -->
        <div id="rejectReasonModal" class="modal" style="display:none;">
            <div class="modal-content">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h5 style="margin: 0; color: var(--text-strong); font-weight: 600;">دلیل رد درخواست</h5>
                    <button onclick="closeModal('rejectReasonModal')"
                        style="background: none; border: none; font-size: 24px; cursor: pointer; color: var(--text-muted); padding: 0; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">
                        ✕
                    </button>
                </div>

                <div style="margin-bottom: 15px;">
                    <label class="form-label">دلیل رد را بنویسید:</label>
                    <textarea id="rejectionReasonInput" rows="4"
                        placeholder="مثلاً: کار در زمان مقرر تکمیل نشد یا..."></textarea>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button id="confirmRejectBtn"
                        style="flex: 1; padding: 10px; border: none; border-radius: 5px; cursor: pointer; background: #dc3545; color: white; font-weight: 600; transition: background 0.2s;">
                        تأیید رد
                    </button>
                    <button onclick="closeModal('rejectReasonModal')"
                        style="flex: 1; padding: 10px; border: 1px solid var(--border-soft); background: var(--surface); border-radius: 5px; cursor: pointer; font-weight: 600; color: var(--text-muted);">
                        انصراف
                    </button>
                </div>
            </div>
        </div>
        <!-- Modal درخواست اتمام کار (assignee) -->
        <div class="modal fade" id="terminationRequestModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title">
                            <i class="bi bi-flag-fill me-2"></i>درخواست اتمام کار
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            با ارسال این درخواست، تعریف‌کننده کار مطلع می‌شود و پس از تأیید، کار به وضعیت تکمیل‌شده
                            تغییر می‌یابد.
                        </div>
                        <div class="mb-3">
                            <label class="form-label">دلیل درخواست اتمام *</label>
                            <textarea class="form-control" id="terminationReason" rows="4"
                                placeholder="توضیح دهید چرا کار باید پایان یابد..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="button" class="btn btn-warning" onclick="submitTerminationRequest()">
                            <i class="bi bi-send me-2"></i>ارسال درخواست
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal بررسی درخواست اتمام (creator) -->
        <div class="modal fade" id="terminationReviewModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">
                            <i class="bi bi-flag-fill me-2"></i>بررسی درخواست اتمام کار
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning mb-3">
                            <strong><i class="bi bi-person me-1"></i>درخواست‌کننده:</strong>
                            <span id="terminationRequesterName" class="me-2">-</span>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">دلیل درخواست:</label>
                            <p id="terminationReasonDisplay" class="p-3 bg-light rounded border"
                                style="white-space: pre-wrap; line-height: 1.8;">-</p>
                        </div>
                        <hr>
                        <div class="mb-3" id="terminationRejectionReasonWrapper" style="display:none;">
                            <label class="form-label">دلیل رد *</label>
                            <textarea class="form-control" id="terminationRejectionReason" rows="3"
                                placeholder="لطفاً دلیل رد درخواست را بنویسید..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer justify-content-between">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-danger" id="terminationRejectToggleBtn"
                                onclick="toggleTerminationRejectReason()">
                                <i class="bi bi-x-circle me-1"></i>رد
                            </button>
                            <button type="button" class="btn btn-danger d-none" id="terminationRejectConfirmBtn"
                                onclick="rejectTerminationRequest()">
                                <i class="bi bi-check me-1"></i>تأیید رد
                            </button>
                            <button type="button" class="btn btn-success" id="terminationApproveBtn"
                                onclick="approveTerminationRequest()">
                                <i class="bi bi-check-circle me-1"></i>تأیید اتمام
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <script src="<?= asset('../assets/js/cdn/bootstrap.bundle.min.js') ?>"></script>
        <script src="<?= asset('../../assets/js/table-utils.js') ?>"></script>
        <script src="<?= asset('/assets/js/undo-toast.js') ?>"></script>
        <script src="<?= asset('../assets/js/assignee-picker.js') ?>"></script>
        <script>
            let currentUser = null;
            let currentUserId;
            let taskData = null;
            let taskId = null;
            let allTasks = [];
            let viewingArchive = false; // آیا الان بایگانی نمایش داده می‌شود؟
            let currentDeadlineRequestId = null;
            let currentDeadlineTaskId = null;
            let isAssignee;
            let isCreator;
            let justSubmittedRequest = false; // ✅ اضافه کنید
            let taskHistory = []; // ← اضافه کن
            let currentTerminationRequestId = null; // ← اضافه کن
            function toggleAttachments() {
                const body = document.getElementById('attachmentsBody');
                const chevron = document.getElementById('attachmentsChevron');
                const isOpen = body.style.display !== 'none';

                body.style.display = isOpen ? 'none' : 'block';
                chevron.style.transform = isOpen ? '' : 'rotate(180deg)';
            }
            /* ─── حفظ آدرس صفحه‌ی مبدأ (مقاوم در برابر رفرش) ─── */
            (function rememberBackUrl() {
                const ref = document.referrer;
                // فقط اگر مبدأ خودِ task-detail نیست، ذخیره کن
                if (ref && !ref.includes('task-detail.php')) {
                    sessionStorage.setItem('taskDetailBackUrl', ref);
                }
            })();

            function goBackSmart() {
                const back = sessionStorage.getItem('taskDetailBackUrl') || 'tasks.php';
                sessionStorage.removeItem('taskDetailBackUrl');
                window.location.href = back;
            }

            function initPersianDatepickerForModal(inputId, defaultDateString) {
                const input = document.getElementById(inputId);
                if (!input) {
                    console.error('❌ Input not found:', inputId);
                    return;
                }

                const wrapper = input.closest('.persian-datepicker-wrapper');
                if (!wrapper) {
                    console.error('❌ Wrapper not found');
                    return;
                }

                const datepicker = wrapper.querySelector('.persian-datepicker');
                const daysContainer = datepicker.querySelector('.datepicker-days');
                const monthDisplay = datepicker.querySelector('.datepicker-current');
                const todayBtn = datepicker.querySelector('.datepicker-today-btn');
                const navButtons = datepicker.querySelectorAll('.datepicker-nav');

                let currentJalaliYear = 0;
                let currentJalaliMonth = 0;
                let holidays = []; // تاریخ‌های تعطیل (فرمت: 'YYYY-MM-DD')
                let holidayTitles = {}; // نگاشت تاریخ → عنوان تعطیل
                const jalaliMonths = [
                    'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
                    'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'
                ];

                // ✅ بعد — این تابع رو داخل initPersianDatepickerForModal اضافه کن
                async function loadHolidays() {
                    try {
                        const token = window.authToken || localStorage.getItem('auth_token') || '';
                        const res = await fetch('/api/holidays/list.php', {
                            headers: {
                                'Authorization': 'Bearer ' + token
                            }
                        });
                        if (!res.ok) return;
                        const data = await res.json();
                        if (data.success && Array.isArray(data.holidays)) {
                            holidays = data.holidays.map(h => h.holiday_date);
                            data.holidays.forEach(h => {
                                holidayTitles[h.holiday_date] = h.title;
                            });
                        }
                    } catch (e) {
                        // بدون تعطیلات ادامه می‌دیم
                    }
                }

                function toPersian(num) {
                    const persianNumbers = '۰۱۲۳۴۵۶۷۸۹';
                    return String(num).replace(/\d/g, d => persianNumbers[d]);
                }

                function toEnglish(str) {
                    const pNumbers = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
                    const eNumbers = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
                    let result = String(str);
                    for (let i = 0; i < 10; i++) {
                        result = result.replace(new RegExp(pNumbers[i], 'g'), eNumbers[i]);
                    }
                    return result;
                }

                // ✅ استفاده از API مرورگر برای تبدیل میلادی به شمسی
                function gregorianToJalali(gDate) {
                    try {
                        if (!gDate || !(gDate instanceof Date) || isNaN(gDate.getTime())) {
                            gDate = new Date();
                        }

                        // استفاده از API مرورگر برای تبدیل
                        const shamsiStr = gDate.toLocaleDateString('fa-IR');
                        const parts = shamsiStr.split('/').map(p => parseInt(toEnglish(p)));

                        return {
                            year: parts[0],
                            month: parts[1],
                            day: parts[2]
                        };
                    } catch (e) {
                        console.error('❌ Error in gregorianToJalali:', e);
                        const today = new Date();
                        const jToday = today.toLocaleDateString('fa-IR').split('/');
                        return {
                            year: parseInt(toEnglish(jToday[0])),
                            month: parseInt(toEnglish(jToday[1])),
                            day: parseInt(toEnglish(jToday[2]))
                        };
                    }
                }

                function jalaliToGregorian(jy, jm, jd) {
                    // الگوریتم دقیق
                    let jy2 = jy - 979;
                    let jm2 = jm - 1;
                    let jd2 = jd - 1;
                    let j_day_no = 365 * jy2 + Math.floor(jy2 / 33) * 8 + Math.floor((jy2 % 33 + 3) / 4);
                    for (let i = 0; i < jm2; ++i) j_day_no += (i < 6) ? 31 : 30;
                    j_day_no += jd2;
                    let g_day_no = j_day_no + 79;
                    let gy2 = 1600 + 400 * Math.floor(g_day_no / 146097);
                    g_day_no = g_day_no % 146097;
                    let leap = true;
                    if (g_day_no >= 36525) {
                        g_day_no--;
                        gy2 += 100 * Math.floor(g_day_no / 36524);
                        g_day_no = g_day_no % 36524;
                        if (g_day_no >= 365) g_day_no++;
                        else leap = false;
                    }
                    gy2 += 4 * Math.floor(g_day_no / 1461);
                    g_day_no %= 1461;
                    if (g_day_no >= 366) {
                        leap = false;
                        g_day_no--;
                        gy2 += Math.floor(g_day_no / 365);
                        g_day_no = g_day_no % 365;
                    }
                    const g_days_in_month = [31, (leap ? 29 : 28), 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
                    let gm2 = 0;
                    for (gm2 = 0; gm2 < 12 && g_day_no >= g_days_in_month[gm2]; gm2++)
                        g_day_no -= g_days_in_month[gm2];

                    // ✅ مشکل timezone — به جای new Date(y,m,d) از noon استفاده می‌کنیم
                    // تا getDay() در همه timezone‌ها درست باشه
                    return new Date(gy2, gm2, g_day_no + 1, 12, 0, 0);
                }

                function getDaysInJalaliMonth(year, month) {
                    if (month <= 6) return 31;
                    if (month <= 11) return 30;
                    // اسفند: سال کبیسه 30 روز، غیر کبیسه 29 روز
                    return isJalaliLeapYear(year) ? 30 : 29;
                }

                function isJalaliLeapYear(year) {
                    const breaks = [1, 5, 9, 13, 17, 22, 26, 30];
                    const cycle = year % 33;
                    return breaks.includes(cycle);
                }

                function updateCalendar() {
                    try {
                        daysContainer.innerHTML = '';
                        const year = currentJalaliYear;
                        const month = currentJalaliMonth;

                        if (monthDisplay) {
                            monthDisplay.textContent = jalaliMonths[month - 1] + ' ' + toPersian(year);
                        }

                        const daysInMonth = getDaysInJalaliMonth(year, month);

                        const firstGregorian = jalaliToGregorian(year, month, 1);
                        // ✅ بعد — تبدیل به سیستم شنبه=0
                        const firstDay = (firstGregorian.getDay() + 1) % 7;

                        // روزهای خالی ابتدای ماه
                        for (let i = 0; i < firstDay; i++) {
                            const emptyDay = document.createElement('div');
                            emptyDay.className = 'datepicker-day other-month';
                            daysContainer.appendChild(emptyDay);
                        }

                        // روزهای ماه
                        const todayJalali = gregorianToJalali(new Date());

                        for (let day = 1; day <= daysInMonth; day++) {
                            const dayEl = document.createElement('div');
                            dayEl.className = 'datepicker-day';
                            dayEl.textContent = toPersian(day);

                            // ── تشخیص تعطیل بودن روز ──
                            const gregDate = jalaliToGregorian(year, month, day);
                            const gregStr = gregDate.toISOString().split('T')[0];
                            const isFriday = gregDate.getDay() === 5;
                            const isHoliday = holidays.includes(gregStr);

                            if (isFriday) dayEl.classList.add('datepicker-friday');
                            if (isHoliday) {
                                dayEl.classList.add('datepicker-holiday');
                                dayEl.title = holidayTitles[gregStr] || 'تعطیل رسمی';
                            }
                            // ──────────────────────────

                            const currentDayNum = year * 10000 + month * 100 + day;
                            const todayNum = todayJalali.year * 10000 + todayJalali.month * 100 + todayJalali.day;

                            if (year === todayJalali.year && month === todayJalali.month && day === todayJalali.day) {
                                dayEl.classList.add('today');
                            }

                            if (currentDayNum < todayNum) {
                                dayEl.classList.add('disabled');
                                dayEl.title = 'انتخاب روز گذشته مجاز نیست';
                            } else {
                                dayEl.addEventListener('click', () => {
                                    // ── هشدار هنگام انتخاب روز تعطیل ──
                                    if (isFriday || isHoliday) {
                                        const label = isHoliday ? (holidayTitles[gregStr] || 'تعطیل رسمی') : 'جمعه';
                                        showToast(`روز انتخابی «${label}» است. آیا مطمئن هستید؟`, 'warning', {
                                            duration: 1500000,
                                            buttons: [{
                                                    label: 'بله، انتخاب کن',
                                                    style: 'primary',
                                                    onClick: function() {
                                                        selectDate(year, month, day);
                                                    }
                                                },
                                                {
                                                    label: 'خیر',
                                                    style: 'ghost',
                                                    onClick: function() {
                                                        return;
                                                    }
                                                }
                                            ]
                                        });
                                    } else {
                                        selectDate(year, month, day);
                                    }
                                });
                                dayEl.style.cursor = 'pointer';
                            }

                            daysContainer.appendChild(dayEl);
                        }
                    } catch (e) {
                        console.error('❌ Error in updateCalendar:', e);
                    }
                }

                function selectDate(year, month, day) {
                    try {
                        const gregorianDate = jalaliToGregorian(year, month, day);

                        if (isNaN(gregorianDate.getTime())) {
                            throw new Error('Invalid date');
                        }

                        const dateStr = gregorianDate.toISOString().split('T')[0];

                        input.setAttribute('data-date', dateStr);
                        input.value = toPersian(day) + ' ' + jalaliMonths[month - 1] + ' ' + toPersian(year);

                        datepicker.style.display = 'none';

                    } catch (e) {
                        console.error('❌ Error in selectDate:', e);
                    }
                }

                // دکمه‌های ناوبری
                navButtons.forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();

                        if (btn.dataset.action === 'prev') {
                            if (currentJalaliMonth === 1) {
                                currentJalaliMonth = 12;
                                currentJalaliYear--;
                            } else {
                                currentJalaliMonth--;
                            }
                        } else {
                            if (currentJalaliMonth === 12) {
                                currentJalaliMonth = 1;
                                currentJalaliYear++;
                            } else {
                                currentJalaliMonth++;
                            }
                        }
                        updateCalendar();
                    });
                });

                // کلیک روی input
                input.addEventListener('click', (e) => {
                    e.stopPropagation();
                    datepicker.style.display = datepicker.style.display === 'block' ? 'none' : 'block';
                });

                // دکمه امروز
                if (todayBtn) {
                    todayBtn.addEventListener('click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();

                        const today = new Date();
                        const todayJalali = gregorianToJalali(today);

                        currentJalaliYear = todayJalali.year;
                        currentJalaliMonth = todayJalali.month;

                        updateCalendar();
                        selectDate(todayJalali.year, todayJalali.month, todayJalali.day);
                    });
                }

                // بستن با کلیک خارج
                document.addEventListener('click', (e) => {
                    if (!wrapper.contains(e.target)) {
                        datepicker.style.display = 'none';
                    }
                });

                // ✅ تنظیم تاریخ پیش‌فرض
                try {
                    let defaultDate;

                    // استفاده از پارامتر ارسالی
                    if (defaultDateString) {
                        defaultDate = new Date(defaultDateString);

                    } else {
                        defaultDate = new Date();
                    }

                    // اطمینان از معتبر بودن تاریخ
                    if (isNaN(defaultDate.getTime())) {
                        defaultDate = new Date();
                    }

                    // ✅ بعد — اول تعطیلات رو بارگذاری کن، بعد تقویم رو رندر کن
                    const defaultJalali = gregorianToJalali(defaultDate);
                    currentJalaliYear = defaultJalali.year;
                    currentJalaliMonth = defaultJalali.month;

                    loadHolidays().then(() => {
                        updateCalendar();
                        selectDate(defaultJalali.year, defaultJalali.month, defaultJalali.day);
                    });
                } catch (e) {
                    console.error('❌ Error initializing datepicker:', e);
                }
            }
            document.addEventListener('DOMContentLoaded', function(event) {
                authToken = localStorage.getItem('auth_token');

                if (taskId) {
                    loadPendingDeadlineRequests();
                }

                try {
                    const su = JSON.parse(localStorage.getItem('user_info'));
                    myUserId = su ? parseInt(su.id) : null;
                } catch {
                    myUserId = null;
                }

                const reviewModal = document.getElementById('reviewDeadlineModal');
                const rejectModal = document.getElementById('rejectReasonModal');
                const requestModal = document.getElementById('requestDeadlineModal');

                if (event.target === reviewModal) {
                    reviewModal.style.display = 'none';
                }
                if (event.target === rejectModal) {
                    rejectModal.style.display = 'none';
                }
                if (event.target === requestModal) {
                    requestModal.style.display = 'none';
                }

                if (!authToken) {
                    window.location.href = '../index.php';
                    return;
                }

                const urlParams = new URLSearchParams(window.location.search);
                taskId = urlParams.get('id');

                if (!taskId) {
                    const t = showToast('هیچ کاری با این شماره پیدا نشد', 'info');

                    goBackSmart();
                    return;
                }

                loadSections().then(() => {
                    loadTaskDetails(); // 🆕 بعد از آماده‌شدن برچسب‌های فارسی
                });
                loadUsers();

                // ✅ نوتیفیکیشن‌های این تسک را خوانده‌شده کن
                markTaskNotificationsRead(taskId);

                // ✅ Event Listener برای دکمه درخواست مهلت
                const requestBtn = document.getElementById('requestDeadlineBtn');
                if (requestBtn) {
                    requestBtn.addEventListener('click', function(e) {
                        // چک کردن disabled بودن (هم کلاس هم attribute)
                        if (this.classList.contains('disabled') || this.getAttribute('data-disabled') === 'true') {
                            e.preventDefault();
                            e.stopPropagation();
                            e.stopImmediatePropagation();
                            return false;
                        }

                        // اگر فعال است، modal را باز کن
                        showRequestDeadlineModal();
                    });
                }

                const modals = document.querySelectorAll('.modal');
                modals.forEach(modal => {
                    modal.addEventListener('show.bs.modal', function() {
                        // حذف backdrop های قبلی
                        document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
                            if (backdrop.style.display === 'none') {
                                backdrop.remove();
                            }
                        });
                    });
                });
            });

            function checkDeadlineRequests(taskId) {
                loadPendingDeadlineRequests();
            }
            // ── واحدهای فعالیت از API ──────────────────────────────────
            let orgSections = [];
            // ============== چک‌لیست ==============
            let checklistCanEdit = false;
            let checklistUsers = []; // لیست کاربران برای منوی ارجاع چک‌لیست
            let currentChecklistItems = []; // آخرین آیتم‌های لودشده (برای مودال ویرایش)
            // 🔒 قفلِ چک‌لیست: تا زمانی که همهٔ آیتم‌ها تیک نخورده باشند، دکمهٔ «تکمیل کار»
            // (چه برای کار معمولی، چه برای مرحلهٔ روتین که از همین دکمه استفاده می‌کند) مخفی می‌ماند
            let checklistGateState = { total: 0, done: 0 };
            // آیا منطقِ کسب‌وکارِ setupActionButtons (بدون درنظرگرفتنِ چک‌لیست) تصمیم گرفته
            // completeBtn نشان داده شود؟ applyChecklistGate روی همین پرچم (نه روی style.display
            // فعلیِ دکمه) تصمیم نهایی را می‌گیرد تا به ترتیبِ اجرای توابعِ async وابسته نباشد
            let completeBtnEligible = false;
            let myUserId = null;
            let delegateTargetId = '';
            async function loadChecklist() {
                // 🆕 قبلاً چک‌لیست فقط برای کار عادی نمایش داده می‌شد؛ حالا مراحل روتین هم
                // می‌توانند چک‌لیستِ الگو داشته باشند (کپی‌شده هنگام شروع روتین) و باید همینجا دیده شوند
                if (!taskData) {
                    document.getElementById('checklistDetailSection').style.display = 'none';
                    checklistGateState = { total: 0, done: 0 };
                    return;
                }
                // 🔒 بیننده‌ای که دسترسیِ چک‌لیست براش خاموش شده: کل بخش پنهان
                if (window._isViewerOnly && !window._viewerCanViewChecklist) {
                    const section = document.getElementById('checklistDetailSection');
                    if (section) section.style.display = 'none';
                    checklistGateState = { total: 0, done: 0 };
                    return;
                }
                try {
                    const res = await fetch(`../api/checklist/get.php?task_id=${taskId}`, {
                        headers: {
                            'Authorization': 'Bearer ' + authToken
                        }
                    });
                    const data = await res.json();
                    if (!data.success) {
                        // شکستِ API را باز (بدون قفل) در نظر می‌گیریم؛ قفلِ واقعی سمتِ سرور است
                        checklistGateState = { total: 0, done: 0 };
                        applyChecklistGate();
                        return;
                    }

                    checklistCanEdit = data.can_edit;
                    checklistGateState = { total: data.total, done: data.done };
                    const section = document.getElementById('checklistDetailSection');

                    // اگر آیتمی نیست و کاربر تعریف‌کننده هم نیست، بخش را نشان نده
                    if (data.total === 0 && !checklistCanEdit) {
                        section.style.display = 'none';
                        applyChecklistGate();
                        return;
                    }
                    section.style.display = 'block';

                    renderDetailChecklist(data.items, data.percent, data.done, data.total);

                    // ردیف افزودن فقط برای تعریف‌کننده (و وقتی کار قفل نیست)
                    document.getElementById('addChecklistItemRow').style.display =
                        checklistCanEdit ? 'block' : 'none';

                    // 🔒 اگر کار به پایان رسیده، پیام قفل نمایش بده
                    const lockNote = document.getElementById('checklistLockNote');
                    if (data.is_locked) {
                        if (lockNote) lockNote.style.display = 'block';
                    } else {
                        if (lockNote) lockNote.style.display = 'none';
                    }

                    applyChecklistGate();
                } catch (e) {
                    console.error('loadChecklist error:', e);
                }
            }

            // 🔒 اجرای واقعیِ قفل: نمایشِ نهاییِ completeBtn را همیشه از رویِ دو منبع
            // مستقل دوباره محاسبه می‌کند — completeBtnEligible (تصمیمِ setupActionButtons،
            // بدون درنظرگرفتنِ چک‌لیست) و checklistGateState (وضعیتِ چک‌لیست). چون نتیجه
            // هربار از صفر ساخته می‌شود (نه بر مبنایِ display فعلیِ دکمه)، به ترتیبِ اجرای
            // توابعِ async وابسته نیست: هم از setupActionButtons و هم از loadChecklist (بعد
            // از هر تیک) صدا زده می‌شود؛ هرکدام دیرتر اجرا شود، تصمیمِ نهایی را می‌گیرد.
            function applyChecklistGate() {
                const completeBtn = document.getElementById('completeBtn');
                if (!completeBtn) return;

                const incomplete = checklistGateState.total > 0 && checklistGateState.done < checklistGateState.total;
                completeBtn.style.display = (completeBtnEligible && !incomplete) ? 'inline-block' : 'none';
            }
            // تازه‌سازی فقط بخش تاریخچه (بدون رفرش کل صفحه)
            async function refreshHistory() {
                try {
                    const response = await fetch(`../api/tasks/detail.php?id=${taskId}`, {
                        headers: {
                            'Authorization': 'Bearer ' + authToken
                        }
                    });
                    const data = await response.json();
                    if (data.success) {
                        taskHistory = data.history || [];
                        displayHistory(taskHistory); // همان تابع نمایش تاریخچه
                    }
                } catch (e) {
                    console.error('refreshHistory error:', e);
                }
            }
            // تولید برچسب ارجاعِ یک آیتم چک‌لیست برای نمایش (برای همه کاربران)
            function renderChecklistAssigneeBadge(item) {
                if (!item.assignee_type) return ''; // بدون ارجاع → چیزی نشان نده

                let label = '',
                    icon = '';

                if (item.assignee_type === 'user') {
                    icon = 'bi-person';
                    label = (item.assignee_user_name && item.assignee_user_name.trim()) ?
                        item.assignee_user_name.trim() :
                        'کاربر حذف‌شده';
                } else if (item.assignee_type === 'section') {
                    icon = 'bi-people-fill';
                    label = item.assignee_section_name ||
                        item.assignee_value ||
                        'واحد حذف‌شده';
                } else {
                    return '';
                }

                return `<span class="badge bg-light text-dark border ms-1" style="font-weight:normal;">
                        <i class="bi ${icon} me-1"></i>${label}
                    </span>`;
            }
            // ساخت گزینه‌های منوی ارجاع (کاربران + واحدها)
            // selectedRaw مثل "user:۱۲۳" یا "section:management" یا "" است
            function buildChecklistAssigneeOptions(selectedRaw) {
                let html = `<option value="">بدون ارجاع</option>`;

                if (checklistUsers.length) {
                    html += `<optgroup label="کاربران">`;
                    checklistUsers.forEach(u => {
                        const name = u.full_name ||
                            `${u.first_name || ''} ${u.last_name || ''}`.trim() ||
                            u.phone;
                        const val = `user:${u.id}`;
                        html += `<option value="${val}" ${selectedRaw === val ? 'selected' : ''}>${name}</option>`;
                    });
                    html += `</optgroup>`;
                }

                if (orgSections.length) {
                    html += `<optgroup label="واحدها">`;
                    orgSections.forEach(s => {
                        const val = `section:${s.section_key}`;
                        html += `<option value="${val}" ${selectedRaw === val ? 'selected' : ''}>${s.section_label}</option>`;
                    });
                    html += `</optgroup>`;
                }

                return html;
            }


            function renderDetailChecklist(items, percent, done, total) {
                currentChecklistItems = items || []; // نگه‌داری برای مودال

                document.getElementById('checklistProgress').style.width = percent + '%';
                document.getElementById('checklistProgressText').textContent =
                    `${enTofaNumber(done)} از ${enTofaNumber(total)}`;

                const c = document.getElementById('checklistDetailItems');
                if (!items || items.length === 0) {
                    c.innerHTML = '<p class="text-muted" style="font-size:.85rem;">هنوز آیتمی اضافه نشده.</p>';
                    return;
                }

                c.innerHTML = items.map(item => {
                    // آیا این آیتم به کاربر فعلی ارجاع شده؟
                    const mine = item.assignee_type === 'user' &&
                        myUserId != null &&
                        parseInt(item.assignee_value) === myUserId;

                    const MAX_DESC_LEN = 50;

                    const safeDesc = item.description ?
                        String(item.description).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;') :
                        '';
                    const titleDesc = item.description ?
                        String(item.description).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;') :
                        '';

                    const isLongDesc = safeDesc.length > MAX_DESC_LEN;
                    const shortDesc = isLongDesc ? safeDesc.slice(0, MAX_DESC_LEN) + '…' : safeDesc;

                    // ناحیه‌ی توضیحات آیتم
                    const isAssigned = !!(item.assignee_type && item.assignee_value);
                    let descZoneHTML = '';
                    if (!checklistCanEdit || isAssigned) {
                        descZoneHTML = safeDesc ?
                            `<span class="chk-desc-inline" title="${titleDesc}"> — ${shortDesc}</span>` :
                            '';
                    } else {
                        descZoneHTML = safeDesc ?
                            `<span class="chk-desc-inline" title="${titleDesc}" onclick="startEditDesc(${item.id})" style="cursor:pointer;"> — ${shortDesc}</span>` :
                            `<i class="bi bi-chat-left-text chk-desc-icon" title="افزودن توضیحات" onclick="startEditDesc(${item.id})" style="cursor:pointer;"></i>`;
                    }

                    // دکمه‌های سمت راست (ویرایش/حذف/قفل)
                    let actionsHTML = '';
                    if (checklistCanEdit) {
                        if (item.is_done == 1) {
                            actionsHTML = `<i class="bi bi-lock-fill text-muted" style="cursor:help;" onclick="showLockReason()" title="این آیتم تیک خورده و قفل شده است؛ دیگر قابل تغییر یا حذف نیست."></i>`;
                        } else if (item.assignee_type && item.assignee_value) {
                            actionsHTML = `<i class="bi bi-arrow-right-circle text-muted" style="cursor:help;" onclick="showAssignedLockReason()" title="این آیتم ارجاع داده شده است. برای تغییر، ابتدا آن را حذف کنید."></i>
                    <button class="btn btn-link btn-sm text-danger p-0" onclick="deleteChecklistItem(${item.id})"><i class="bi bi-trash"></i></button>`;
                        } else {
                            const safeTitle = (item.title || '').replace(/'/g, "\\'");
                            actionsHTML = `<button class="btn btn-link btn-sm p-0" onclick="editChecklistItem(${item.id}, '${safeTitle}')"><i class="bi bi-pencil"></i></button>
                    <button class="btn btn-link btn-sm text-danger p-0" onclick="deleteChecklistItem(${item.id})"><i class="bi bi-trash"></i></button>`;
                        }
                    }

                    const doneMetaHTML = (item.is_done == 1 && item.done_by_name) ? ('✓ ' + item.done_by_name.trim()) : '';
                    const mineHTML = mine ? '<span class="badge bg-warning text-dark ms-1">به شما ارجاع شده</span>' : '';
                    const itemStyle = `${mine ? 'background:var(--warning-box-bg); border-right:3px solid #ffc107; padding-right:6px; border-radius:6px;' : ''}${item.can_toggle_this === false ? 'opacity:0.65;' : ''}`;
                    const checkboxTitle = item.is_done == 1 ?
                        'title="این آیتم تیک خورده و قابل برداشتن نیست"' :
                        (item.can_toggle_this === false ? 'title="این آیتم به شما ارجاع نشده"' : '');

                    return `
          <div class="checklist-detail-item-wrap">
            <div class="checklist-detail-item ${item.is_done == 1 ? 'done' : ''}"
                 id="chk-${item.id}"
                 style="${itemStyle}">
              <input type="checkbox" ${item.is_done == 1 ? 'checked' : ''}
                     ${(item.can_toggle_this === false || item.is_done == 1) ? 'disabled' : ''}
                     onchange="openDoneNote(${item.id}, this)"
                     ${checkboxTitle}>
              <span class="chk-title">${item.title}</span>
              <span class="chk-desc-zone" id="chk-desc-zone-${item.id}">${descZoneHTML}</span>
              ${renderAssigneeBadge(item)}
              ${mineHTML}
              <span class="chk-meta">${doneMetaHTML}</span>
              ${actionsHTML}
            </div>

                    ${item.is_done == 1 && item.done_note
                        ? `<div class="chk-done-note"><b>یادداشت:</b> ${String(item.done_note).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')}</div>`
                        : ''}

                    ${item.is_done != 1 && item.can_toggle_this !== false ? `
                    <div class="chk-note-drawer">
                        <label for="chk-note-${item.id}">چه چیزی را ثبت می‌کنید؟ (اختیاری)</label>
                        <textarea id="chk-note-${item.id}" rows="2"
                                  placeholder="مثلاً: فاکتور با شماره ۴۸۲۱ صادر شد"></textarea>
                        <div class="chk-note-actions">
                            <button class="btn btn-primary btn-sm" onclick="saveDoneNote(${item.id}, true)">ثبت و انجام شد</button>
                            <button class="btn btn-light btn-sm" onclick="saveDoneNote(${item.id}, false)">بدون یادداشت</button>
                            <button class="btn btn-link btn-sm text-muted" onclick="cancelDoneNote(${item.id})">انصراف</button>
                        </div>
                    </div>` : ''}
                  </div>`;
                }).join('');
            }
            // شروع ویرایش درجای توضیحات یک آیتم

            function startEditDesc(itemId) {
                if (!checklistCanEdit) {
                    showToast('این کار به پایان رسیده و قابل ویرایش نیست', 'warning');
                    return;
                }

                const zone = document.getElementById('chk-desc-zone-' + itemId);
                if (!zone) return;

                const item = currentChecklistItems.find(i => i.id == itemId);
                const currentDesc = (item && item.description) ? item.description : '';
                const safeCurrentDesc = currentDesc.replace(/</g, '&lt;').replace(/>/g, '&gt;');

                zone.innerHTML = `<span class="chk-desc-edit" style="display:inline-flex; align-items:center; gap:4px; vertical-align:middle;">
        <textarea id="chk-desc-input-${itemId}" class="form-control form-control-sm" rows="1"
            style="min-width:180px; font-size:0.8rem; display:inline-block; width:350px;"
            placeholder="توضیحات...">${safeCurrentDesc}</textarea>
        <button class="btn btn-link btn-sm text-success p-0" title="ثبت توضیحات" onclick="saveEditDesc(${itemId})">
            <i class="bi bi-check-lg"></i>
        </button>
        <button class="btn btn-link btn-sm text-secondary p-0" title="انصراف" onclick="cancelEditDesc(${itemId})">
            <i class="bi bi-x-lg"></i>
        </button>
    </span>`;

                const ta = document.getElementById('chk-desc-input-' + itemId);
                if (ta) {
                    ta.focus();
                    ta.setSelectionRange(ta.value.length, ta.value.length);
                }
            }

            // ذخیره‌ی توضیحات ویرایش‌شده به سرور
            async function saveEditDesc(itemId) {
                const ta = document.getElementById('chk-desc-input-' + itemId);
                if (!ta) return;
                const newDesc = ta.value.trim();

                // پیدا کردن آیتم از حافظه (برای حفظ عنوان و ارجاع فعلی)
                const item = currentChecklistItems.find(i => i.id == itemId);
                if (!item) return;

                // حفظ ارجاع فعلی (تا پاک نشود)
                let assignee_type = item.assignee_type || null;
                let assignee_value = item.assignee_value ? String(item.assignee_value) : null;

                try {
                    const res = await fetch('../api/checklist/update-item.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': 'Bearer ' + authToken
                        },
                        body: JSON.stringify({
                            item_id: parseInt(itemId),
                            title: item.title, // عنوان فعلی حفظ می‌شود
                            description: newDesc,
                            assignee_type: assignee_type,
                            assignee_value: assignee_value
                        })
                    });
                    const data = await res.json();
                    if (data.success) {
                        // به‌روزرسانی توضیحات در حافظه
                        item.description = newDesc;
                        // نمایش دوباره‌ی همان ناحیه (بدون رندر کل لیست)
                        refreshDescZone(itemId);
                        showToast('توضیحات ذخیره شد', 'success');
                    } else {
                        showToast(data.message || 'خطا در ذخیره توضیحات', 'warning');
                    }
                } catch (e) {
                    showToast('خطا در ارتباط با سرور', 'warning');
                }
            }
            // انصراف از ویرایش توضیحات (بدون ذخیره)
            function cancelEditDesc(itemId) {
                refreshDescZone(itemId);
            }

            // بازسازی ناحیه‌ی توضیحاتِ یک آیتم (بدون رندر کل لیست)

            function refreshDescZone(itemId) {
                const zone = document.getElementById('chk-desc-zone-' + itemId);
                if (!zone) return;

                const item = currentChecklistItems.find(i => i.id == itemId);
                const desc = (item && item.description) ? item.description : '';

                const MAX_DESC_LEN = 50;
                const safeDesc = desc ?
                    String(desc).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;') :
                    '';
                const titleDesc = desc ?
                    String(desc).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;') :
                    '';
                const shortDesc = safeDesc.length > MAX_DESC_LEN ? safeDesc.slice(0, MAX_DESC_LEN) + '…' : safeDesc;

                zone.innerHTML = safeDesc ?
                    `<span class="chk-desc-inline" title="${titleDesc}" onclick="startEditDesc(${itemId})" style="cursor:pointer;"> — ${shortDesc}</span>` :
                    `<i class="bi bi-chat-left-text chk-desc-icon" title="افزودن توضیحات" onclick="startEditDesc(${itemId})" style="cursor:pointer;"></i>`;
            }
            // برچسب ارجاع — برای همه‌ی کاربران نمایش داده می‌شود

            function renderAssigneeBadge(item) {
                if (!item.assignee_type) return '';
                let label = '',
                    icon = '';
                if (item.assignee_type === 'user') {
                    icon = 'bi-person';
                    label = (item.assignee_user_name && item.assignee_user_name.trim()) ?
                        item.assignee_user_name.trim() :
                        'کاربر حذف‌شده';
                } else if (item.assignee_type === 'section') {
                    icon = 'bi-people-fill';
                    label = item.assignee_section_name ||
                        getSectionLabel(item.assignee_value) ||
                        'واحد حذف‌شده';
                } else {
                    return '';
                }
                return `<span class="badge bg-light text-dark border ms-1" style="font-weight:normal;">
        <i class="bi ${icon} me-1"></i>${label}
    </span>`;
            }
            /* باز کردن کشوی یادداشت — تیک هنوز ثبت نشده */
            function openDoneNote(itemId, cb) {
                if (cb) cb.checked = false; // تا تأیید نشود، تیک نمی‌خورد

                document.querySelectorAll('.checklist-detail-item-wrap.noting')
                    .forEach(w => w.classList.remove('noting'));

                const wrap = document.getElementById('chk-' + itemId)?.closest('.checklist-detail-item-wrap');
                if (!wrap) return;
                wrap.classList.add('noting');
                setTimeout(() => document.getElementById('chk-note-' + itemId)?.focus(), 50);
            }

            function cancelDoneNote(itemId) {
                document.getElementById('chk-' + itemId)
                    ?.closest('.checklist-detail-item-wrap')
                    ?.classList.remove('noting');
            }

            /* ثبت نهایی — با یادداشت یا بدون آن */
            function saveDoneNote(itemId, withNote) {
                const box = document.getElementById('chk-note-' + itemId);
                const note = (withNote && box) ? box.value.trim() : '';
                toggleChecklistItem(itemId, true, note);
            }
            async function toggleChecklistItem(itemId, isDone, note = '') {
                try {
                    const res = await fetch('../api/checklist/toggle.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': 'Bearer ' + authToken
                        },
                        body: JSON.stringify({
                            item_id: itemId,
                            is_done: isDone ? 1 : 0,
                            note: note
                        })
                    });
                    const data = await res.json();
                    if (!data.success) {
                        showToast(data.message || 'خطا در ثبت', 'warning');
                        loadChecklist();
                        return;
                    }
                    if (data.auto_completed) {
                        showToast('همه آیتم‌ها تکمیل شدند. کار طبق روال ادامه یافت.', 'success');
                        setTimeout(() => location.reload(), 1200);
                    } else {
                        // 🆕 اولین تیک، کار را (سمتِ سرور) به in_progress می‌برد — همین‌جا هم
                        // taskData را هماهنگ کن و دکمه‌ها را دوباره بساز تا «شروع کار» فوراً
                        // مخفی شود، بدون نیاز به رفرشِ صفحه
                        if (isDone && taskData && ['not_started', 'delegated', 'rejected'].includes(taskData.status)) {
                            taskData.status = 'in_progress';
                            setupActionButtons(taskData);
                        }
                        loadChecklist(); // تازه‌سازی درصد و آیتم‌ها
                        refreshHistory(); // 🆕 تازه‌سازی تاریخچه تا تغییر وضعیت دیده شود
                    }
                } catch (e) {
                    showToast('خطا در ارتباط با سرور', 'warning');
                }
            }

            async function addDetailChecklistItem() {
                const input = document.getElementById('newDetailChecklistItem');
                const title = input.value.trim();
                if (!title) return;
                try {
                    const res = await fetch('../api/checklist/add-item.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': 'Bearer ' + authToken
                        },
                        body: JSON.stringify({
                            task_id: taskId,
                            title,
                            description: ''
                        })
                    });
                    const data = await res.json();
                    if (data.success) {
                        input.value = '';
                        loadChecklist();
                        refreshHistory();
                    } else showToast(data.message || 'خطا در افزودن', 'warning');
                } catch (e) {
                    showToast('خطا در ارتباط با سرور', 'warning');
                }
            }

            function showAssignedLockReason() {
                showToast('این آیتم به فرد/واحد دیگری ارجاع داده شده است. برای ویرایش یا افزودن توضیحات، ابتدا آن را حذف کنید و دوباره بسازید.', 'info');
            }

            function showLockReason() {
                showToast(
                    'آیتم‌های انجام‌شده قفل هستند. اگر می‌خواهید این آیتم را ویرایش یا حذف کنید، ابتدا تیک آن را بردارید تا قفل باز شود.',
                    'info'
                );
            }
            // باز کردن مودال ویرایش (به‌جای prompt قدیمی)
            let clTarget = null,
                clTouched = false; // انتخابِ پیکرِ ارجاع چک‌لیست
            // باز کردن مودال در حالت «افزودن آیتم جدید»
            function openAddChecklistModal() {
                // خالی کردن فیلدها
                document.getElementById('editChecklistItemId').value = ''; // خالی = حالت افزودن
                document.getElementById('editChecklistTitle').value = '';
                document.getElementById('editChecklistDesc').value = '';

                // ریست پیکر ارجاع
                clTarget = null;
                clTouched = false;
                const _secMap = {};
                (orgSections || []).forEach(s => {
                    _secMap[s.section_key] = s.section_label;
                });

                document.getElementById('editChecklistAssigneePicker').innerHTML = '';
                AssigneePicker.create({
                    container: '#editChecklistAssigneePicker',
                    users: checklistUsers || [],
                    sections: orgSections || [],
                    sectionMap: _secMap,
                    showSections: true,
                    placeholder: 'ارجاع به کاربر یا واحد (اختیاری)...',
                    onSelect: (type, value) => {
                        clTouched = true;
                        if (!type || value === '__all__' || value === '__all_users__') {
                            clTarget = null;
                            return;
                        }
                        clTarget = {
                            type: type,
                            value: value
                        };
                    }
                });

                // تغییر عنوان مودال به «افزودن»
                const modalTitle = document.querySelector('#editChecklistModal .modal-title');
                if (modalTitle) modalTitle.innerHTML = '<i class="bi bi-plus-circle ms-2"></i>افزودن آیتم';
                // در حالت افزودن، بخش ارجاع مخفی باشد
                const _assigneeWrap = document.getElementById('editChecklistAssigneeWrap');
                if (_assigneeWrap) _assigneeWrap.style.display = 'none';
                const modal = new bootstrap.Modal(document.getElementById('editChecklistModal'));
                modal.show();
            }

            function editChecklistItem(itemId, currentTitle) {
                // پیدا کردن آیتم برای دانستن ارجاع فعلی‌اش
                const item = currentChecklistItems.find(i => i.id == itemId);
                const currentRaw = (item && item.assignee_type && item.assignee_value) ?
                    `${item.assignee_type}:${item.assignee_value}` :
                    '';

                document.getElementById('editChecklistItemId').value = itemId;
                document.getElementById('editChecklistTitle').value = currentTitle;
                document.getElementById('editChecklistDesc').value = (item && item.description) ? item.description : '';
                // اطمینان از عنوان درست مودال در حالت ویرایش
                const _modalTitle = document.querySelector('#editChecklistModal .modal-title');
                if (_modalTitle) _modalTitle.innerHTML = '<i class="bi bi-pencil-square ms-2"></i>ویرایش آیتم';
                // در حالت ویرایش، بخش ارجاع نمایان باشد
                const _assigneeWrap = document.getElementById('editChecklistAssigneeWrap');
                if (_assigneeWrap) _assigneeWrap.style.display = 'block';
                // پیکر سرچ‌دار (کاربر + واحد) با نام واحد فارسی
                clTarget = null;
                clTouched = false;
                const _secMap = {};
                (orgSections || []).forEach(s => {
                    _secMap[s.section_key] = s.section_label;
                });

                // برچسب ارجاع فعلی برای نمایش در placeholder
                let _curLabel = 'بدون ارجاع';
                if (item && item.assignee_type === 'user') {
                    const _u = (checklistUsers || []).find(x => String(x.id) === String(item.assignee_value));
                    _curLabel = _u ? (_u.full_name || `${_u.first_name || ''} ${_u.last_name || ''}`.trim() || _u.phone) : 'کاربر';
                } else if (item && item.assignee_type === 'section') {
                    _curLabel = _secMap[item.assignee_value] || item.assignee_value;
                }

                document.getElementById('editChecklistAssigneePicker').innerHTML = '';
                AssigneePicker.create({
                    container: '#editChecklistAssigneePicker',
                    users: checklistUsers || [],
                    sections: orgSections || [],
                    sectionMap: _secMap,
                    showSections: true,
                    placeholder: 'ارجاع فعلی: ' + _curLabel + ' — برای تغییر جستجو کنید...',
                    onSelect: (type, value) => {
                        clTouched = true;
                        // گزینه‌های گروهیِ «همه...» برای آیتم چک‌لیست نامعتبرند
                        if (!type || value === '__all__' || value === '__all_users__') {
                            clTarget = null;
                            return;
                        }
                        clTarget = {
                            type: type,
                            value: value
                        };
                    }
                });

                const modal = new bootstrap.Modal(document.getElementById('editChecklistModal'));
                modal.show();
            }

            // ذخیره تغییرات مودال
            async function submitEditChecklist() {
                const itemId = document.getElementById('editChecklistItemId').value;
                const title = document.getElementById('editChecklistTitle').value.trim();
                const description = document.getElementById('editChecklistDesc').value.trim();
                if (!title) {
                    showToast('عنوان نمی‌تواند خالی باشد', 'warning');
                    return;
                }

                // تعیین ارجاع
                let assignee_type = null,
                    assignee_value = null;
                if (clTouched) {
                    if (clTarget) {
                        assignee_type = clTarget.type;
                        assignee_value = String(clTarget.value);
                    }
                } else if (itemId) {
                    // فقط در حالت ویرایش: بدون تغییر → حفظ ارجاع فعلی آیتم
                    const _it = currentChecklistItems.find(i => i.id == itemId);
                    if (_it && _it.assignee_type && _it.assignee_value) {
                        assignee_type = _it.assignee_type;
                        assignee_value = String(_it.assignee_value);
                    }
                }

                // تشخیص حالت: اگر itemId خالی باشد → افزودن، وگرنه ویرایش
                const isAdd = !itemId;
                const url = isAdd ? '../api/checklist/add-item.php' : '../api/checklist/update-item.php';
                const payload = isAdd ? {
                    task_id: taskId,
                    title: title,
                    description: description,
                    assignee_type: assignee_type,
                    assignee_value: assignee_value
                } : {
                    item_id: parseInt(itemId),
                    title: title,
                    description: description,
                    assignee_type: assignee_type,
                    assignee_value: assignee_value
                };

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
                        bootstrap.Modal.getInstance(document.getElementById('editChecklistModal')).hide();
                        showToast(isAdd ? 'آیتم اضافه شد' : 'آیتم به‌روزرسانی شد', 'success');
                        loadChecklist();
                        refreshHistory();
                    } else {
                        showToast(data.message || 'خطا در ثبت', 'warning');
                    }
                } catch (e) {
                    showToast('خطا در ارتباط با سرور', 'warning');
                }
            }

            async function deleteChecklistItem(itemId) {
                showToast('آیا مطمئن هستید که می‌خواهید این آیتم را حذف کنید؟', 'warning', {
                    duration: 1500000,
                    buttons: [{
                            label: 'بله، حذف شود',
                            style: 'primary',
                            onClick: async function() {
                                try {
                                    const res = await fetch('../api/checklist/delete-item.php', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'Authorization': 'Bearer ' + authToken
                                        },
                                        body: JSON.stringify({
                                            item_id: itemId
                                        })
                                    });
                                    const data = await res.json();
                                    if (data.success) {
                                        if (data.auto_completed) {
                                            showToast('با حذف آیتم، چک‌لیست کامل شد. کار ادامه یافت.', 'success');
                                            setTimeout(() => location.reload(), 1200);
                                        } else {
                                            loadChecklist();
                                            refreshHistory();
                                        }
                                    } else showToast(data.message || 'خطا در حذف', 'warning');
                                } catch (e) {
                                    showToast('خطا در ارتباط با سرور', 'warning');
                                }
                            }
                        },
                        {
                            label: 'خیر، منصرف شدم',
                            style: 'ghost',
                            onClick: function() {
                                return;
                            }
                        }
                    ]
                });
            }





            async function loadSections() {
                try {
                    const res = await fetch('/api/organization/activity-sections.php', {
                        headers: {
                            'Authorization': 'Bearer ' + authToken
                        }
                    });
                    const data = await res.json();
                    if (data.success) orgSections = data.sections;
                } catch {}
            }

            function getSectionLabel(value) {
                if (!value) return 'نامشخص';
                const found = orgSections.find(s => s.section_key === value); // 🆕
                return found ? found.section_label : value; // 🆕
            }

            // برای سازگاری با کدهای قدیمی که sectionToFarsi صدا می‌زنند
            function sectionToFarsi(section) {
                return getSectionLabel(section);
            }
            async function markTaskNotificationsRead(taskId) {
                try {
                    await fetch('../api/notifications/mark-read.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': 'Bearer ' + authToken
                        },
                        body: JSON.stringify({
                            task_id: parseInt(taskId)
                        })
                    });
                } catch (e) {
                    // خطا مهم نیست، فقط لاگ کن
                    console.error('خطا در mark-read نوتیفیکیشن:', e);
                }
            }
            async function loadTaskDetails() {
                try {
                    const response = await fetch(`../api/tasks/detail.php?id=${taskId}`, {
                        headers: {
                            'Authorization': 'Bearer ' + authToken
                        }
                    });

                    const data = await response.json();
                    if (data.success) {

                        taskData = data.task;
                        window._currentTask = data.task;
                        taskHistory = data.history || [];
                        window.taskData = taskData;

                        currentUser = JSON.parse(localStorage.getItem('user_info'));
                        window.userId = currentUser.id;

                        // تاریخچه از سمت سرور فیلتر شده است
                        window._isChecklistOnly = (data.is_checklist_only === true);
                        window._isViewerOnly = (data.is_viewer_only === true); // 🆕
                        window._viewerCanViewAttachments = (data.viewer_can_view_attachments !== false); // 🆕
                        window._viewerCanViewHistory = (data.viewer_can_view_history !== false); // 🆕
                        window._viewerCanViewChecklist = (data.viewer_can_view_checklist !== false); // 🆕

                        displayTaskDetails(taskData, data.history);
                        renderTaskGroup(taskData); // 🆕
                        renderTaskViewers(taskData); // 🆕
                        setupActionButtons(taskData); // ← isAssignee اینجا مقدار می‌گیرد
                        updateDeadlineDisplay(taskData);
                        checkDeadlineRequests(taskId);
                        loadTerminationRequest();

                        // ← اینجا اضافه کنید (بعد از setupActionButtons):
                        setupUploadListeners();
                        setupModalUploadListeners();
                        loadAttachments();
                        loadChecklist();
                    } else {
                        showToast(data.message || 'به این کار دسترسی ندارید', 'warning');
                        setTimeout(() => window.location.replace('dashboard-manager.php'), 1200);
                    }
                } catch (error) {
                    const t = showToast('خطا در ارتباط با سرور', 'warning');

                }
            }
            // 🆕 نمایش گروه کار + امکان تغییر برای تعریف‌کننده
            async function renderTaskGroup(task) {
                const cell = document.getElementById('taskGroupCell');
                if (!cell) return;

                const isOwner = (currentUser && currentUser.id == task.creator_id);
                // حذف‌شده/کنسل‌شده/متوقف‌شده/تکمیل‌شده → دیگه قابلِ تغییر نیست
                const isTerminal = task.is_deleted == 1 ||
                    ['completed', 'approved', 'stopped', 'rejected'].includes(task.status);

                // نمایش badge فعلی (یا «بدون گروه»)
                function badgeHtml() {
                    if (task.group_id && task.group_name) {
                        const color = task.group_color || '#6366f1';
                        return `<span class="badge" style="background:${color}20;color:${color};border:1px solid ${color}40;">
                                <i class="${task.group_icon || 'bi-tag'} me-1"></i>${task.group_name}</span>`;
                    }
                    return '<span class="text-muted">بدون گروه</span>';
                }

                // اگر تعریف‌کننده نیست یا کار در وضعیتِ پایانی/غیرفعاله، فقط نمایش
                if (!isOwner || isTerminal) {
                    cell.innerHTML = badgeHtml();
                    return;
                }

                // تعریف‌کننده: badge + دکمه تغییر
                cell.innerHTML = `${badgeHtml()}
                <button class="btn btn-link btn-sm p-0 ms-2" id="changeGroupBtn" title="تغییر گروه">
                    <i class="bi bi-pencil"></i>
                </button>
                <span id="groupSelectWrap" style="display:none;">
                    <select id="taskGroupSelect" class="form-select form-select-sm d-inline-block" style="width:auto;"></select>
                </span>`;

                // راه‌اندازی گروه‌ها
                const isOrgAdmin = (currentUser.activity_section === 'management' && currentUser.role === 'supervisor');
                await TaskGroups.init({
                    isOrgAdmin
                });

                document.getElementById('changeGroupBtn').addEventListener('click', () => {
                    const wrap = document.getElementById('groupSelectWrap');
                    const sel = document.getElementById('taskGroupSelect');
                    TaskGroups.fill(sel, task.group_id || '');
                    wrap.style.display = 'inline-block';
                    document.getElementById('changeGroupBtn').style.display = 'none';

                    sel.addEventListener('change', async () => {
                        await saveTaskGroup(task.id, sel.value);
                    }, {
                        once: true
                    });
                });
            }

            // 🆕 ذخیره تغییر گروه
            async function saveTaskGroup(taskId, groupId) {
                try {
                    const res = await fetch('../api/tasks/update-group.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': 'Bearer ' + authToken
                        },
                        body: JSON.stringify({
                            task_id: taskId,
                            group_id: groupId || null
                        })
                    });
                    const data = await res.json();
                    if (data.success) {
                        showToast('گروه کار به‌روزرسانی شد', 'success');
                        setTimeout(() => location.reload(), 800);
                    } else {
                        showToast(data.message || 'خطا در تغییر گروه', 'warning');
                    }
                } catch (e) {
                    showToast('خطا در ارتباط با سرور', 'warning');
                }
            }

            // ─────────────── بیننده‌هایِ کار (task_viewers) ───────────────
            let addViewerPickerInst = null;

            async function renderTaskViewers(task) {
                const cell = document.getElementById('taskViewersCell');
                const item = document.getElementById('taskViewersItem');
                if (!cell) return;

                let data;
                try {
                    const res = await fetch(`../api/tasks/list-viewers.php?task_id=${task.id}`, {
                        headers: { 'Authorization': 'Bearer ' + authToken }
                    });
                    data = await res.json();
                } catch {
                    return;
                }
                // این بخش صرفاً یک ابزارِ مدیریتیه (اضافه/حذف/ویرایشِ دسترسیِ
                // دیگران)؛ خودِ بینندگان نباید فهرستِ سایرِ بینندگان و دسترسی‌شون
                // رو ببینن، حتی به‌صورتِ غیرفعال — پس برایِ غیرِ مدیر، کلاً مخفی می‌مونه
                if (!data.success || !data.can_manage) {
                    if (item) item.style.display = 'none';
                    return;
                }
                if (item) item.style.display = '';
                renderViewersCell(task.id, data.viewers, !!data.can_manage);
            }

            // یک سوییچِ کوچکِ دسترسی برایِ ردیفِ یک بیننده — سه‌بار (پیوست/تاریخچه/چک‌لیست) صدا زده می‌شه
            function viewerSwitchHtml(inputId, checked, labelText, disabled) {
                return `<div class="form-check form-switch mb-0" style="display:flex; align-items:center; gap:5px; padding:0; margin:0;">
                    <input class="form-check-input" type="checkbox" role="switch" id="${inputId}" ${checked ? 'checked' : ''} ${disabled ? 'disabled' : ''} style="margin:0; flex-shrink:0;">
                    <label class="form-check-label small" for="${inputId}" style="margin:0; white-space:nowrap;">${labelText}</label>
                </div>`;
            }

            // بازکردن/بستنِ ردیفِ یک بیننده — پیش‌فرض فقط نام دیده می‌شه؛ با
            // کلیک، جزئیاتِ دسترسی (سوییچ‌ها/اعمال/حذف) باز می‌شه — برایِ
            // نگه‌داشتنِ طراحیِ خلوت به‌جایِ نمایشِ همیشگیِ همه‌چیز
            function toggleViewerRow(userId) {
                const detail = document.getElementById(`viewerDetail-${userId}`);
                const chevron = document.getElementById(`viewerChevron-${userId}`);
                if (!detail) return;
                const isOpen = detail.style.display !== 'none';
                detail.style.display = isOpen ? 'none' : 'flex';
                if (chevron) {
                    // فلشِ افقی — باید با حالتِ نهاییِ ردیف هماهنگ باشه (هم بعدِ کلیک،
                    // هم در رندرِ اولیه‌یِ پیش‌فرض که پایین‌تر «بسته» ساخته می‌شه):
                    // باز → سمتِ راست، بسته → سمتِ چپ (همون آیکنِ پیش‌فرض)
                    chevron.classList.toggle('bi-chevron-left', isOpen);
                    chevron.classList.toggle('bi-chevron-right', !isOpen);
                }
            }

            function renderViewersCell(taskId, viewers, canManage) {
                const cell = document.getElementById('taskViewersCell');
                if (!cell) return;

                let html = '';

                if (canManage) {
                    html += `<button class="btn btn-link btn-sm p-0 mb-1" id="addViewerBtn" title="افزودنِ دسترسی" style="display:flex; align-items:center; gap:4px;">
                        <i class="bi bi-person-plus"></i> افزودنِ دسترسی
                    </button>`;
                }

                // فرمِ افزودنِ دسترسیِ جدید: بالایِ فهرستِ کاربرانِ دسترسی‌داده‌شده
                if (canManage) {
                    // align-items:flex-start (نه center) چون وقتی کاربری از پیکر
                    // انتخاب می‌شه، یک ردیفِ چیپ زیرِ فیلدِ جستجو اضافه می‌شه و
                    // ارتفاعِ اون بلوک بیشتر از دکمه/سوییچ‌ها می‌شه؛ با center
                    // دکمه‌یِ افزودن و سوییچ‌ها به‌جایِ هم‌ردیف‌بودن با فیلدِ جستجو،
                    // به وسطِ ارتفاعِ کلی می‌رفتن و توازنِ ردیف به‌هم می‌خورد
                    html += `<div id="addViewerWrap" style="display:none; align-items:flex-start; flex-wrap:wrap; gap:16px; margin-bottom:8px;">
                        <div style="display:flex; align-items:flex-start; gap:6px; flex:1; min-width:220px;">
                            <div id="addViewerPicker" style="flex:1; min-width:0;"></div>
                            <button class="btn btn-primary btn-sm" id="submitAddViewerBtn" style="flex-shrink:0;">افزودن</button>
                        </div>
                        <div style="display:flex; align-items:center; gap:16px; flex-wrap:wrap;">
                            ${viewerSwitchHtml('viewerCanAttachments', true, 'پیوست‌ها')}
                            ${viewerSwitchHtml('viewerCanHistory', true, 'تاریخچه')}
                            ${viewerSwitchHtml('viewerCanChecklist', true, 'چک‌لیست')}
                        </div>
                    </div>`;
                }

                if (!viewers.length) {
                    html += '<div><span class="text-muted">کسی اضافه نشده</span></div>';
                } else {
                    html += '<div style="display:flex; flex-direction:column; gap:4px;">';
                    html += viewers.map(v => `
                        <div class="viewer-row" data-user-id="${v.id}" style="display:flex; align-items:center; flex-wrap:wrap; gap:10px; padding:5px 8px; border-radius:8px; background:var(--bg-page);">
                            <span class="viewer-name" style="font-weight:600; display:flex; align-items:center; gap:4px; flex-shrink:0; cursor:pointer;" onclick="toggleViewerRow(${v.id})">
                                ${escapeHtml(v.full_name)}
                                <i class="bi bi-chevron-left" id="viewerChevron-${v.id}" style="font-size:.75em;"></i>
                            </span>
                            <div id="viewerDetail-${v.id}" style="display:none; align-items:center; flex-wrap:wrap; gap:12px;">
                                ${viewerSwitchHtml(`viewerRowAtt-${v.id}`, v.can_view_attachments, 'پیوست‌ها')}
                                ${viewerSwitchHtml(`viewerRowHist-${v.id}`, v.can_view_history, 'تاریخچه')}
                                ${viewerSwitchHtml(`viewerRowChk-${v.id}`, v.can_view_checklist, 'چک‌لیست')}
                                <button class="btn btn-outline-primary btn-sm py-0" onclick="applyViewerPermission(${taskId}, ${v.id})">اعمال</button>
                                <i class="bi bi-x-circle" style="cursor:pointer" onclick="removeTaskViewer(${taskId}, ${v.id})" title="حذف"></i>
                            </div>
                        </div>
                    `).join('');
                    html += '</div>';
                }

                cell.innerHTML = html;

                if (canManage) {
                    document.getElementById('addViewerBtn').addEventListener('click', () => openAddViewerPicker(taskId));
                    document.getElementById('submitAddViewerBtn').addEventListener('click', () => submitAddViewers(taskId));
                }
            }

            async function openAddViewerPicker(taskId) {
                const wrap = document.getElementById('addViewerWrap');
                wrap.style.display = wrap.style.display === 'none' ? 'flex' : 'none';
                if (wrap.style.display === 'none') return;

                if (!addViewerPickerInst) {
                    let viewerPickerUsers = [];
                    try {
                        const res = await fetch('../api/users/list.php', {
                            headers: { 'Authorization': 'Bearer ' + authToken }
                        });
                        const data = await res.json();
                        if (data.success) viewerPickerUsers = data.users;
                    } catch {}

                    addViewerPickerInst = AssigneePicker.create({
                        container: '#addViewerPicker',
                        users: viewerPickerUsers,
                        multiSelect: true,
                        onSelect: () => {}
                    });
                }
                addViewerPickerInst.focus();
            }

            async function submitAddViewers(taskId) {
                const val = addViewerPickerInst ? addViewerPickerInst.getValue() : null;
                const userIds = val ? val.value : [];
                if (!userIds.length) {
                    showToast('حداقل یک نفر را انتخاب کنید', 'warning');
                    return;
                }
                const canAttachments = document.getElementById('viewerCanAttachments').checked;
                const canHistory = document.getElementById('viewerCanHistory').checked;
                const canChecklist = document.getElementById('viewerCanChecklist').checked;
                try {
                    const res = await fetch('../api/tasks/add-viewers.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + authToken },
                        body: JSON.stringify({
                            task_id: taskId, user_ids: userIds,
                            can_view_attachments: canAttachments, can_view_history: canHistory,
                            can_view_checklist: canChecklist
                        })
                    });
                    const data = await res.json();
                    if (data.success) {
                        showToast('دسترسی اضافه شد', 'success');
                        addViewerPickerInst = null; // نمونهٔ بعدی از نو با داده‌یِ تازه ساخته بشه
                        renderTaskViewers(taskData);
                    } else {
                        showToast(data.message || 'خطا در افزودنِ دسترسی', 'error');
                    }
                } catch {
                    showToast('خطا در ارتباط با سرور', 'error');
                }
            }

            // ویرایشِ دسترسیِ یک بیننده‌ی از قبل‌موجود — دقیقاً همون اندپوینتِ
            // افزودن رو با یک‌نفره و پرچم‌هایِ به‌روزشده دوباره صدا می‌زنه
            // (ON DUPLICATE KEY UPDATE سمتِ سرور، سطرِ موجود رو جای‌گزین می‌کنه)
            async function applyViewerPermission(taskId, userId) {
                const canAttachments = document.getElementById(`viewerRowAtt-${userId}`).checked;
                const canHistory = document.getElementById(`viewerRowHist-${userId}`).checked;
                const canChecklist = document.getElementById(`viewerRowChk-${userId}`).checked;
                try {
                    const res = await fetch('../api/tasks/add-viewers.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + authToken },
                        body: JSON.stringify({
                            task_id: taskId, user_ids: [userId],
                            can_view_attachments: canAttachments, can_view_history: canHistory,
                            can_view_checklist: canChecklist
                        })
                    });
                    const data = await res.json();
                    if (data.success) {
                        showToast('تغییرات اعمال شد', 'success');
                        renderTaskViewers(taskData);
                    } else {
                        showToast(data.message || 'خطا در اعمالِ تغییرات', 'error');
                    }
                } catch {
                    showToast('خطا در ارتباط با سرور', 'error');
                }
            }

            async function removeTaskViewer(taskId, userId) {
                try {
                    const res = await fetch('../api/tasks/remove-viewer.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + authToken },
                        body: JSON.stringify({ task_id: taskId, user_id: userId })
                    });
                    const data = await res.json();
                    if (data.success) {
                        renderTaskViewers(taskData);
                    } else {
                        showToast(data.message || 'خطا در حذفِ دسترسی', 'error');
                    }
                } catch {
                    showToast('خطا در ارتباط با سرور', 'error');
                }
            }

            function displayTaskDetails(task, history) {

                document.getElementById('taskTitle').textContent = task.title;
                document.getElementById('taskDescription').textContent = task.description || '';

                const metaHTML = `
                <div class="task-meta-item">

                    <span class="badge type-${task.task_type}">
                        ${task.task_type === 'periodic' ? 'مقطعی' : 'دوره‌ای'}
                    </span>
                </div>
                <div class="task-meta-item">

                    <span class="badge priority-${task.priority}">
                        ${getPriorityLabel(task.priority)}
                    </span>
                </div>
                <div class="task-meta-item">

                    <span class="badge status-${task.status}">
                        ${getStatusLabel(task.status, task.assignee_id)}
                    </span>
                </div>
            `;
                document.getElementById('taskMeta').innerHTML = metaHTML;

                // دوره بعدی — از سرور (period-engine) که موعد پایان و لنگرِ روزِ ماه را درست لحاظ می‌کند
                const nextDueDate = task.next_due_date;

                // ستون اول: اطلاعات عمومی
                let col1HTML = `
                <div class="info-item">
                    <div class="info-label">شماره:</div>
                    <div class="info-value">${enTofaNumber(taskId) || 'نامشخص'}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">ایجادکننده:</div>
                    <div class="info-value">${task.creator_name || 'نامشخص'}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">مسئول انجام:</div>
                    <div class="info-value">${task.is_workflow_task == 1
                        ? (task.assignee_id ? (task.assignee_name || 'نامشخص') : (sectionToFarsi(task.current_step_section) || 'نامشخص'))
                        : (task.assignee_name || 'نامشخص')
                    }</div>
                </div>
                <div class="info-item">
                    <div class="info-label">گروه:</div>
                    <div class="info-value" id="taskGroupCell"></div>
                </div>
                <div class="info-item" id="taskViewersItem" style="display:none;">
                    <div class="info-label">دسترسی به:</div>
                    <div class="info-value" id="taskViewersCell">در حال بارگذاری...</div>
                </div>

                <div class="info-item">
                    <div class="info-label">تاریخ ایجاد:</div>
                    <div class="info-value">${formatDateTime(task.created_at)}</div>
                </div>
            `;

                if (task.task_type === 'continuous') {
                    col1HTML += `
                <div class="info-item">
                    <div class="info-label">آخرین دوره تأیید:</div>
                    <div class="info-value">${task.last_approved_date ? formatPersianDate(task.last_approved_date) : 'هنوز تأییدی ثبت نشده'}</div>
                </div>
                `;
                }

                // ستون دوم: اطلاعات تاریخ/دوره
                let col2HTML = '';

                if (task.task_type === 'periodic') {
                    col2HTML += `
                <div class="info-item">
                    <div class="info-label">موعد انجام:</div>
                    <div class="info-value d-flex align-items-center gap-2">
                        <span id="deadlineValue">-</span>
                        <span id="requestDeadlineBtn"
                            class="deadline-request-icon"
                            data-bs-toggle="tooltip"
                            data-bs-placement="top"
                            title="درخواست تمدید موعد انجام"
                            style="display:none;">
                            <i class="bi bi-hourglass-split"></i>
                        </span>
                        <span id="pendingRequestBadge"
                            class="badge badge-info"
                            style="display:none; cursor:pointer; margin-left: 10px;">
                            درخواست: 17 دی 1404
                        </span>
                    </div>
                </div>
                ${task.working_days_delayed > 0 ? `
                <div class="info-item">
                    <div class="info-label">تأخیر:</div>
                    <div class="info-value"><span class="badge bg-danger"><i class="bi bi-clock-history me-1"></i>${enTofaNumber(task.working_days_delayed)} روز کاری تأخیر</span></div>
                </div>` : ''}
                `;
                }

                if (task.task_type === 'continuous') {
                    col2HTML += `
<div class="info-item">
    <div class="info-label">تاریخ شروع:</div>
    <div class="info-value">${formatPersianDate(task.start_date)}</div>
</div>
<div class="info-item">
    <div class="info-label">تاریخ پایان:</div>
    <div class="info-value">${task.end_date ? formatPersianDate(task.end_date) : '<span class="text-muted">تعریف نشده</span>'}</div>
</div>
<div class="info-item">
    <div class="info-label">دوره تکرار:</div>
    <div class="info-value">${getPeriodLabel(task.period_type)}</div>
</div>
<div class="info-item">
    <div class="info-label">دوره بعدی:</div>
    <div class="info-value">${nextDueDate ? '<span class="badge bg-info text-dark" style="color: white !important;">' + formatPersianDate(nextDueDate) + '</span>' : '<span class="text-muted">-</span>'}</div>
</div>
${task.overdue_periods > 0 ? `
<div class="info-item">
    <div class="info-label">دوره‌های معوقه:</div>
    <div class="info-value"><span class="badge bg-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i>${enTofaNumber(task.overdue_periods)} دوره معوقه</span></div>
</div>` : ''}`;
                }

                // ترکیب دو ستون - جایگزین کن
                let infoHTML = '';

                if (task.task_type === 'periodic') {
                    // یک ستون برای مقطعی
                    infoHTML = `<div class="row g-0"><div class="col-12">${col1HTML}${col2HTML}</div></div>`;
                } else {
                    // دو ستون برای دوره‌ای
                    infoHTML = `
    <div class="row g-0">
        <div class="col-6 border-end pe-3">${col1HTML}</div>
        <div class="col-6 ps-3">${col2HTML}</div>
    </div>`;
                }


                if (task.is_pending_approval == 1 && task.status === 'pending_approval') {
                    const statusMessage = document.getElementById('statusMessage');
                    if (isAssignee) {
                        statusMessage.innerHTML = `
                <div class="alert alert-warning">
                    <h5 class="alert-heading"><i class="bi bi-clock-history me-2"></i>در انتظار تأیید شما</h5>
                    <p class="mb-0">این کار توسط نفر قبلی تکمیل شده و منتظر تأیید شماست. لطفاً کار را بررسی کرده و تأیید یا رد کنید.</p>
                </div>
            `;
                    } else {
                        statusMessage.innerHTML = `
                <div class="alert alert-info">
                    <h5 class="alert-heading"><i class="bi bi-hourglass-split me-2"></i>در انتظار تأیید</h5>
                    <p class="mb-0">این کار تکمیل شده و در حال حاضر منتظر تأیید ارجاع‌دهنده قبلی است.</p>
                </div>
            `;
                    }
                }



                if (task.task_type === 'continuous' && task.pending_approval_count > 0) {
                    const approvalInfo = document.createElement('div');
                    approvalInfo.className = 'alert alert-warning';
                    approvalInfo.style.marginTop = '1rem';
                    approvalInfo.innerHTML = `
        <h5 class="alert-heading">
            <i class="bi bi-hourglass-split me-2"></i>دوره‌های منتظر تأیید
        </h5>
        <p class="mb-1">
            <strong>${task.pending_approval_count}</strong> دوره منتظر تأیید است
        </p>
        ${task.last_approved_date ? `
            <p class="mb-0">
                <i class="bi bi-check-circle me-1"></i>
                آخرین دوره تأیید شده: ${formatPersianDate(task.last_approved_date)}
            </p>
        ` : ''}
    `;

                    const statusMessage = document.getElementById('statusMessage');
                    if (statusMessage) {
                        statusMessage.appendChild(approvalInfo);
                    }
                }



                document.getElementById('taskInfo').innerHTML = infoHTML;

                const stepDescEl = document.getElementById('taskDescription');
                if (task.is_workflow_task == 1 && task.current_step_description) {
                    stepDescEl.innerHTML = task.current_step_description
                        .replace(/\r\n/g, '\n')
                        .replace(/\n{2,}/g, '\n')
                        .replace(/\n/g, '<br>');
                    stepDescEl.style.display = '';
                } else {
                    stepDescEl.innerHTML = '';
                    stepDescEl.style.display = 'none'; // کارهای عادی: زیر عنوان خالی و پنهان
                }

                // if (task.delegation_notes) {
                //     document.getElementById('delegationSection').style.display = 'block';
                //     document.getElementById('delegationNotes').innerHTML = task.delegation_notes.replace(/\n/g, '<br>');
                // }

                if (task.task_type === 'continuous') {
                    const completedCount = task.completed_count || 0;
                    const forgiven = task.overdue_forgiven_credit || 0;
                    const overdueCount = calculateOverduePeriods(task);
                    const remaining = Math.max(0, overdueCount - completedCount - forgiven);

                    document.getElementById('completionCounter').style.display = 'block';
                }

                displayHistory(history);
            }

            function showNewTaskModal() {
                window.location.href = 'create-task.php';
            }

            function showDailyReportModal() {
                window.location.href = 'daily-report.php';
            }

            function updateDeadlineDisplay(task) {
                // تعیین نقش کاربر
                isCreator = (task.creator_id === userId);
                isAssignee = (task.assignee_id === userId);

                const deadlineElement = document.getElementById('deadlineValue');
                if (task.deadline) {
                    deadlineElement.textContent = (task.is_workflow_task == 1) ?
                        formatDateTime(task.deadline) :
                        formatDateTime(task.deadline).split(' - ')[0];
                }

                // ✅ کار روتین: آیکن تمدید ساعتی برای مسئولِ مرحله (کاربرِ مشخص یا اعضای واحد)
                if (task.is_workflow_task == 1) {
                    const inSection = currentUser && (task.assignee_id ?
                        currentUser.id == task.assignee_id :
                        currentUser.activity_section === task.current_step_section);
                    const isFinished = ['completed', 'approved', 'rejected'].includes(task.status);
                    const wfBtn = document.getElementById('requestDeadlineBtn');
                    if (wfBtn) {
                        if (inSection && !isFinished) {
                            wfBtn.classList.remove('force-hidden', 'disabled');
                            wfBtn.removeAttribute('data-disabled');
                            wfBtn.style.display = 'inline-block';
                            wfBtn.setAttribute('title', 'تمدید موعد (ساعتی)');
                            wfBtn.onclick = function(e) {
                                e.preventDefault();
                                showWorkflowDeadlineModal();
                            };
                        } else {
                            wfBtn.style.setProperty('display', 'none', 'important');
                        }
                    }
                    loadPendingDeadlineRequests();
                    return;
                }

                // ✅ نمایش دکمه درخواست فقط برای assignee

                // ✅ نمایش دکمه درخواست فقط برای assignee
                const requestBtn = document.getElementById('requestDeadlineBtn');
                if (requestBtn) {
                    const isFinished = task.task_type === 'periodic' && ['completed', 'approved'].includes(task.status);

                    if (isAssignee && !isFinished) {
                        requestBtn.classList.remove('force-hidden');
                        requestBtn.style.display = 'inline-block';
                    } else {
                        requestBtn.classList.add('force-hidden');
                        requestBtn.style.setProperty('display', 'none', 'important');
                    }
                }

                // بارگذاری درخواست‌های منتظر
                loadPendingDeadlineRequests();
                loadPendingOverdueClearRequests();
            }

            /**
             * بارگذاری درخواست‌های منتظر
             */
            function loadPendingDeadlineRequests() {

                fetch(`../api/tasks/get-deadline-requests.php?task_id=${taskId}`, {
                        headers: {
                            'Authorization': 'Bearer ' + authToken
                        }
                    })
                    .then(response => response.json())
                    .then(data => {

                        if (data.success && data.requests && data.requests.length > 0) {
                            const request = data.requests[0];

                            // ✅ دریافت user ID از localStorage
                            const currentUser = JSON.parse(localStorage.getItem('user_info'));
                            currentUserId = currentUser ? currentUser.id : null;

                            // ✅ چک کردن: آیا این کاربر current approver است؟
                            const isCurrentApprover = (request.current_approver_id == currentUserId);

                            // نمایش badge برای current approver
                            const badge = document.getElementById('pendingRequestBadge');
                            if (badge && isCurrentApprover) {
                                // ✅ تبدیل تاریخ برای نمایش
                                let displayDate = request.requested_new_deadline;

                                // اگر تابع formatDeadlineDisplay وجود دارد استفاده کن
                                if (typeof formatDeadlineDisplay === 'function') {
                                    displayDate = formatDeadlineDisplay(request.requested_new_deadline).split(' - ')[0];
                                }

                                badge.textContent = `درخواست: ${displayDate}`;
                                badge.style.display = 'inline-block';
                                badge.onclick = function() {
                                    showDeadlineReviewModal(request);
                                };
                                // ✅ نمایش toast با دکمه مشاهده
                                showToast('یک درخواست تمدید موعد در انتظار بررسی شماست', 'info', {
                                    duration: 150000,
                                    buttons: [{
                                            label: 'مشاهده درخواست',
                                            style: 'primary',
                                            onClick: function() {
                                                showDeadlineReviewModal(request);
                                            }
                                        },
                                        {
                                            label: 'بعداً',
                                            style: 'ghost'
                                        }
                                    ]
                                });
                            } else {
                                if (badge) {
                                    badge.style.display = 'none';
                                }
                            }

                            // غیرفعال کردن دکمه درخواست برای assignee
                            if (isAssignee) {
                                disableRequestButton();
                                justSubmittedRequest = false;
                            }
                        } else {

                            // مخفی کردن badge
                            const badge = document.getElementById('pendingRequestBadge');
                            if (badge) {
                                badge.style.display = 'none';
                            }

                            justSubmittedRequest = false;

                            // فعال کردن دکمه برای assignee
                            if (isAssignee) {
                                enableRequestButton();
                            }
                        }
                    })
                    .catch(error => {
                        console.error('❌ Error loading requests:', error);
                    });
            }

            /**
             * نمایش Modal درخواست تمدید مهلت
             */
            function showRequestDeadlineModal() {
                const currentDeadlineLabel = document.getElementById('currentDeadlineLabel');

                // ✅ نمایش موعد فعلی (فقط تاریخ، بدون ساعت)
                if (taskData && taskData.deadline) {
                    const formatted = formatDeadlineDisplay(taskData.deadline);
                    const dateOnly = formatted.split(' - ')[0]; // فقط قسمت تاریخ
                    currentDeadlineLabel.textContent = 'موعد فعلی: ' + dateOnly;
                } else {
                    currentDeadlineLabel.textContent = 'موعد فعلی: نامشخص';
                }

                const modal = document.getElementById('requestDeadlineModal');
                modal.style.display = 'block';

                // ✅ پاک کردن datepicker قبلی (اگر وجود دارد)
                const input = document.getElementById('newDeadline');
                input.value = '';
                input.removeAttribute('data-date');

                setTimeout(() => {
                    // ✅ ارسال مستقیم deadline
                    const deadlineToUse = taskData.deadline;
                    initPersianDatepickerForModal('newDeadline', deadlineToUse);
                }, 150);
            }

            // تابع غیرفعال کردن دکمه درخواست مهلت
            function disableRequestButton() {
                const requestBtn = document.getElementById('requestDeadlineBtn');

                if (!requestBtn) {

                    return;
                }

                // ✅ اول: tooltip را dispose کنیم
                const tooltipInstance = bootstrap.Tooltip.getInstance(requestBtn);
                if (tooltipInstance) {
                    tooltipInstance.dispose();
                }

                // اضافه کردن کلاس disabled
                requestBtn.classList.add('disabled');
                requestBtn.setAttribute('data-disabled', 'true');

                // تغییر آیکن
                const icon = requestBtn.querySelector('i');
                if (icon) {
                    icon.className = 'bi bi-hourglass';
                }

                // ✅ بعد: tooltip جدید را initialize کنیم (فقط اگر visible است)
                requestBtn.setAttribute('title', 'درخواست در انتظار بررسی...');

                if (requestBtn.offsetParent !== null) {
                    new bootstrap.Tooltip(requestBtn, {
                        title: 'درخواست در انتظار بررسی...'
                    });
                }
            }
            // تابع فعال کردن دکمه درخواست مهلت
            function enableRequestButton() {
                const requestBtn = document.getElementById('requestDeadlineBtn');

                if (!requestBtn) return;
                // ✅ اگر تسک تکمیل/تأیید شده، دکمه را نشان نده
                if (taskData && taskData.task_type === 'periodic' && ['completed', 'approved'].includes(taskData.status)) {
                    return;
                }

                // حذف کلاس force-hidden
                requestBtn.classList.remove('force-hidden');
                requestBtn.classList.remove('disabled');
                requestBtn.removeAttribute('data-disabled');

                // element را visible کنیم
                requestBtn.style.display = 'inline-block';

                // برگرداندن آیکن
                const icon = requestBtn.querySelector('i');
                if (icon) {
                    icon.className = 'bi bi-plus';
                }

                // tooltip را dispose و دوباره initialize کنیم
                const tooltipInstance = bootstrap.Tooltip.getInstance(requestBtn);
                if (tooltipInstance) {
                    tooltipInstance.dispose();
                }

                requestBtn.setAttribute('title', 'درخواست تمدید موعد انجام');

                if (requestBtn.offsetParent !== null) {
                    new bootstrap.Tooltip(requestBtn, {
                        title: 'درخواست تمدید موعد انجام'
                    });
                }

                // ✅ اضافه کردن: Re-attach event listener
                // حذف event listener قبلی با clone
                const newBtn = requestBtn.cloneNode(true);
                requestBtn.parentNode.replaceChild(newBtn, requestBtn);

                // اضافه کردن event listener جدید
                const freshBtn = document.getElementById('requestDeadlineBtn');
                freshBtn.addEventListener('click', function(e) {
                    if (this.classList.contains('disabled') || this.getAttribute('data-disabled') === 'true') {
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation();
                        return false;
                    }

                    showRequestDeadlineModal();
                });

            }
            // ===== تمدید ساعتی کار روتین =====
            function showWorkflowDeadlineModal() {
                document.getElementById('wfCurrentDeadline').textContent =
                    (taskData && taskData.deadline) ? formatDeadlineDisplay(taskData.deadline) : 'نامشخص';
                document.getElementById('wfExtendHours').value = '';
                document.getElementById('wfExtensionReason').value = '';
                document.getElementById('wfNewDeadlinePreview').textContent = '-';
                document.getElementById('workflowDeadlineModal').style.display = 'block';
            }

            function wfToMysqlDatetime(d) {
                const p = n => String(n).padStart(2, '0');
                return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())} ${p(d.getHours())}:${p(d.getMinutes())}:${p(d.getSeconds())}`;
            }

            function updateWfDeadlinePreview() {
                const hours = parseInt(document.getElementById('wfExtendHours').value, 10);
                const preview = document.getElementById('wfNewDeadlinePreview');
                if (!hours || hours < 1 || !taskData || !taskData.deadline) {
                    preview.textContent = '-';
                    return;
                }
                const nd = new Date(new Date(taskData.deadline).getTime() + hours * 3600 * 1000);
                preview.textContent = formatDeadlineDisplay(wfToMysqlDatetime(nd));
            }

            async function submitWorkflowDeadlineRequest() {
                const hours = parseInt(document.getElementById('wfExtendHours').value, 10);
                const reason = document.getElementById('wfExtensionReason').value.trim();
                if (!hours || hours < 1) {
                    showToast('تعداد ساعت معتبر وارد کنید', 'info');
                    return;
                }
                if (!reason) {
                    showToast('لطفاً دلیل را وارد کنید', 'info');
                    return;
                }
                if (!taskData || !taskData.deadline) {
                    showToast('موعد فعلی نامشخص است', 'warning');
                    return;
                }

                const nd = new Date(new Date(taskData.deadline).getTime() + hours * 3600 * 1000);
                const newDeadline = wfToMysqlDatetime(nd);

                try {
                    const response = await fetch('../api/tasks/request-deadline.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': 'Bearer ' + authToken
                        },
                        body: JSON.stringify({
                            task_id: taskId,
                            new_deadline: newDeadline,
                            reason: reason,
                            extend_hours: hours
                        })
                    });
                    const data = await response.json();
                    if (data.success) {
                        closeModal('workflowDeadlineModal');
                        if (data.auto_approved) {
                            showToast('موعد کار روتین تمدید شد', 'success');
                            location.reload();
                        } else {
                            showToast('درخواست تمدید ارسال شد', 'success');
                        }
                    } else {
                        showToast(data.message || 'خطا در ارسال درخواست', 'warning');
                    }
                } catch (e) {
                    showToast('خطا در ارتباط با سرور', 'warning');
                }
            }
            async function submitDeadlineRequest() {
                const newDeadlineInput = document.getElementById('newDeadline');
                const newDeadline = newDeadlineInput.getAttribute('data-date');
                const reason = document.getElementById('extensionReason').value.trim();

                if (!newDeadline) {
                    showToast('لطفاً تاریخ جدید را انتخاب کنید', 'info');
                    return;
                }

                if (!reason) {
                    showToast('لطفاً دلیل درخواست را وارد کنید', 'info');
                    // await doHeavyWork();

                    return;
                }

                try {
                    const response = await fetch('../api/tasks/request-deadline.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': 'Bearer ' + authToken
                        },
                        body: JSON.stringify({
                            task_id: taskId,
                            new_deadline: newDeadline,
                            reason: reason
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        closeModal('requestDeadlineModal');

                        // ✅ چک: آیا تأیید خودکار شده؟
                        if (data.auto_approved) {
                            showToast('موعد انجام با موفقیت تغییر یافت', 'success');
                            // ✅ رفرش صفحه
                            location.reload();
                        } else {
                            showToast('درخواست ارسال شد', 'success');
                            // ✅ غیرفعال کردن دکمه
                            disableRequestButton();
                        }
                    } else {
                        const t = showToast(data.message || 'خطا در ارتباط با سرور', 'warning');
                    }
                } catch (error) {
                    const t = showToast('خطا در ارتباط با سرور', 'warning');

                }
            }

            // ✅ اصلاح 4: نمایش modal بررسی درخواست با تاریخ شمسی
            function showDeadlineReviewModal(request) {
                const modal = document.getElementById('reviewDeadlineModal');
                window.currentDeadlineRequestId = request.id;

                document.getElementById('requesterName').textContent = request.first_name + " " + request.last_name || 'نامشخص';

                // موعد فعلی (برای کار روتین با ساعت)
                const currentDeadlineElement = document.getElementById('currentDeadlineDisplay');
                if (taskData && taskData.deadline) {
                    const isWfCur = taskData.is_workflow_task == 1;
                    const formatted = isWfCur ?
                        formatDeadlineDisplay(taskData.deadline) :
                        formatDeadlineDisplay(taskData.deadline).split(' - ')[0];
                    currentDeadlineElement.textContent = 'موعد فعلی: ' + formatted;
                }

                // موعد درخواستی (برای کار روتین با ساعت)
                const requestedDeadlineElement = document.getElementById('requestedDeadlineDisplay');
                const isWf = taskData && taskData.is_workflow_task == 1;
                const requestedFormatted = isWf ?
                    formatDeadlineDisplay(request.requested_new_deadline) :
                    formatDeadlineDisplay(request.requested_new_deadline).split(' - ')[0];
                requestedDeadlineElement.textContent = 'موعد درخواستی: ' + requestedFormatted;

                document.getElementById('extensionReasonDisplay').textContent = request.reason || 'دلیلی ذکر نشده';

                modal.style.display = 'block';
            }

            function formatDeadlineDisplay(dateString) {
                if (!dateString) return 'نامشخص';
                try {
                    const date = new Date(dateString);
                    const options = {
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                    };
                    const persianDate = date.toLocaleDateString('fa-IR', options);
                    const timeOptions = {
                        hour: '2-digit',
                        minute: '2-digit'
                    };
                    const persianTime = date.toLocaleTimeString('fa-IR', timeOptions);
                    return `${persianDate} - ${persianTime}`;
                } catch (e) {
                    return dateString;
                }
            }
            // ✅ اصلاح 1: تأیید درخواست تمدید موعد
            function approveDeadlineRequest() {
                const requestId = window.currentDeadlineRequestId;
                if (!requestId) {
                    showToast('درخواست یافت نشد.', 'info');
                    return;
                }

                showToast('آیا می‌خواهید تأیید کنید؟', 'warning', {
                    duration: 1500000,
                    buttons: [{
                            label: 'بله، تأیید',
                            style: 'primary',
                            onClick: function() {
                                fetch('../api/tasks/approve-deadline.php', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'Authorization': 'Bearer ' + authToken
                                        },
                                        body: JSON.stringify({
                                            request_id: requestId
                                        })
                                    })
                                    .then(response => {
                                        return response.text().then(text => {
                                            try {
                                                return JSON.parse(text);
                                            } catch (e) {
                                                console.error('❌ JSON parse error:', e);
                                                console.error('❌ Response was:', text);
                                                throw new Error('خطای سرور: ' + text.substring(0, 200));
                                            }
                                        });
                                    })
                                    .then(data => {
                                        if (data.success) {
                                            showToast('تأیید شد', 'success');
                                            const badge = document.getElementById('pendingRequestBadge');
                                            if (badge) badge.style.display = 'none';
                                            closeModal('reviewDeadlineModal');
                                            if (data.new_deadline) {
                                                const deadlineElement = document.getElementById('deadlineValue');
                                                if (deadlineElement) {
                                                    deadlineElement.textContent = formatDateTime(data.new_deadline).split(' - ')[0];
                                                }
                                            }
                                            setTimeout(() => location.reload(), 1000);
                                        } else {
                                            showToast('خطا در ارتباط با سرور', 'warning');
                                        }
                                    })
                                    .catch(error => {
                                        showToast('خطا در ارتباط با سرور', 'warning');
                                    });
                            }
                        },
                        {
                            label: 'خیر',
                            style: 'ghost',
                            onClick: function() {
                                return;
                            }
                        }
                    ]
                });
            }
            /**
             * نمایش Modal رد درخواست
             */
            function showRejectDeadlineReason() {
                // بستن Modal بررسی
                const reviewModal = bootstrap.Modal.getInstance(document.getElementById('reviewDeadlineModal'));
                if (reviewModal) reviewModal.hide();

                // بازنشانی textarea
                const reasonInput = document.getElementById('rejectReason');
                if (reasonInput) {
                    reasonInput.value = '';
                }

                // نمایش Modal رد
                const rejectModal = document.getElementById('rejectReasonModal');
                if (rejectModal) {
                    new bootstrap.Modal(rejectModal).show();
                }
            }

            /**
             * ارسال رد درخواست
             */
            // ✅ اصلاح 2: رد درخواست تمدید موعد
            function rejectDeadlineRequest() {
                const requestId = window.currentDeadlineRequestId;
                if (!requestId) {
                    const t = showToast('درخواست یافت نشد', 'warning');


                    return;
                }

                const modal = document.getElementById('rejectReasonModal');
                modal.style.display = 'block';

                const confirmBtn = document.getElementById('confirmRejectBtn');
                confirmBtn.onclick = function() {
                    const reason = document.getElementById('rejectionReasonInput').value.trim();

                    if (!reason) {
                        const t = showToast('لطفا دلیل را وارد کنید', 'warning');

                        return;
                    }

                    fetch('../api/tasks/reject-deadline.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Authorization': 'Bearer ' + authToken // ✅ اضافه کردن
                            },
                            body: JSON.stringify({
                                request_id: requestId,
                                rejection_reason: reason
                            })
                        })
                        .then(response => {
                            // ✅ اضافه کردن: نمایش response قبل از parse
                            return response.text().then(text => {
                                try {
                                    return JSON.parse(text);
                                } catch (e) {
                                    console.error('❌ JSON parse error:', e);
                                    console.error('❌ Response was:', text);
                                    throw new Error('خطای سرور: ' + text.substring(0, 200));
                                }
                            });
                        })
                        .then(data => {
                            if (data.success) {
                                const t = showToast('رد درخواست با موفقیت انجام شد', 'info');


                                const badge = document.getElementById('pendingRequestBadge');
                                if (badge) badge.style.display = 'none';

                                closeModal('reviewDeadlineModal');
                                closeModal('rejectReasonModal');

                                setTimeout(() => location.reload(), 1000);
                            } else {
                                const t = showToast('خطا در ارتباط با سرور', 'warning');


                            }
                        })
                        .catch(error => {
                            const t = showToast('خطا در ارتباط با سرور', 'warning');

                        });
                };
            }

            // تابع کمکی برای ارسال رد
            function performReject(requestId, reason) {
                fetch('../api/tasks/reject-deadline.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            request_id: requestId,
                            rejection_reason: reason
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const t = showToast('رد درخواست با موفقیت انجام شد', 'info');

                            // ✅ اصلاح: مخفی کردن badge درخواست منتظر
                            const badge = document.getElementById('pendingRequestBadge');
                            if (badge) {
                                badge.style.display = 'none';
                            }

                            // بستن modal‌ها
                            const reviewModal = document.getElementById('reviewDeadlineModal');
                            const rejectModal = document.getElementById('rejectReasonModal');
                            if (reviewModal) reviewModal.style.display = 'none';
                            if (rejectModal) rejectModal.style.display = 'none';

                            // بارگذاری مجدد کار
                            setTimeout(() => {
                                location.reload();
                            }, 1000);
                        } else {
                            const t = showToast('خطا در ارتباط با سرور', 'warning');

                        }
                    })
                    .catch(error => {
                        const t = showToast('خطا در ارتباط با سرور', 'warning');

                    });
            }
            async function submitRejectDeadline() {
                const reason = document.getElementById('rejectReason')?.value?.trim();

                if (!reason) {
                    const t = showToast('لطفا دلیل را وارد کنید', 'warning');

                    return;
                }

                if (!currentDeadlineRequestId) {
                    const t = showToast('درخواستی یافت نشد', 'warning');

                    return;
                }

                try {
                    const response = await fetch('../api/tasks/reject-deadline.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': 'Bearer ' + (localStorage.getItem('auth_token') || authToken)
                        },
                        body: JSON.stringify({
                            request_id: currentDeadlineRequestId,
                            rejection_reason: reason
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        showAlert('درخواست مهلت با موفقیت رد شد', 'success');

                        // بستن Modal
                        const modal = bootstrap.Modal.getInstance(document.getElementById('rejectReasonModal'));
                        if (modal) modal.hide();

                        // بارگذاری مجدد صفحه
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        showAlert('❌ ' + (data.message || 'خطا در رد درخواست'), 'danger');
                    }
                } catch (error) {
                    console.error('خطا:', error);
                    showAlert('❌ خطا در ارتباط با سرور', 'danger');
                }
            }

            function setupActionButtons(task) {
                const startBtn = document.getElementById('startBtn');
                const completeBtn = document.getElementById('completeBtn');
                const addDiscBtn = document.getElementById('addDiscBtn');
                const delegateBtn = document.getElementById('delegateBtn');
                const editBtn = document.getElementById('editBtn');
                const deleteBtn = document.getElementById('deleteBtn');
                const redefineBtn = document.getElementById('redefineBtn');
                const approveBtn = document.getElementById('approveBtn');
                const rejectBtn = document.getElementById('rejectBtn');
                const terminationBtn = document.getElementById('terminationRequestBtn');

                // ✅ مورد ۲: اگر تسک حذف شده، همه دکمه‌ها مخفی و آپلود غیرفعال
                if (task.is_deleted == 1) {
                    [startBtn, completeBtn, addDiscBtn, delegateBtn, editBtn, deleteBtn, redefineBtn, approveBtn, rejectBtn].forEach(btn => {
                        if (btn) btn.style.display = 'none';
                    });
                    if (terminationBtn) terminationBtn.style.display = 'none';

                    // غیرفعال کردن آپلود
                    const uploadArea = document.getElementById('uploadArea');
                    if (uploadArea) uploadArea.style.display = 'none';

                    // نمایش پیام حذف
                    const statusMessage = document.getElementById('statusMessage');
                    if (statusMessage) {
                        statusMessage.innerHTML = `
                <div class="alert alert-danger">
                    <h5 class="alert-heading"><i class="bi bi-trash me-2"></i>کار حذف شده</h5>
                    <p class="mb-0">این کار حذف شده است و امکان انجام عملیات روی آن وجود ندارد.</p>
                </div>
            `;
                    }
                    return; // خروج از تابع — نیازی به ادامه نیست
                }

                // 🆕 بیننده‌یِ صرف (فقط از راهِ task_viewers دسترسی داره) — هیچ دکمهٔ
                // اقدامی نباید ببینه، فقط جزئیات رو مشاهده می‌کنه
                if (window._isViewerOnly) {
                    [startBtn, completeBtn, addDiscBtn, delegateBtn, editBtn, deleteBtn, redefineBtn, approveBtn, rejectBtn].forEach(btn => {
                        if (btn) btn.style.display = 'none';
                    });
                    if (terminationBtn) terminationBtn.style.display = 'none';
                    const uploadArea = document.getElementById('uploadArea');
                    if (uploadArea) uploadArea.style.display = 'none';
                    return;
                }

                isCreator = currentUser && currentUser.id == task.creator_id;
                isAssignee = currentUser && currentUser.id == task.assignee_id;
                const isPendingApproval = task.status === 'pending_approval' && task.is_pending_approval == 1;
                const isWorkflow = task.is_workflow_task == 1;

                const lastApprovedDateToday = isLastApprovedDateToday(task);

                let currentDeadlineRequestId = null; // شناسه درخواست منتظر
                let currentDeadlineTaskId = null; // شناسه کار

                [startBtn, completeBtn, addDiscBtn, delegateBtn, editBtn, deleteBtn].forEach(btn => btn.style.display = 'none');
                approveBtn.style.display = 'none';
                rejectBtn.style.display = 'none';
                completeBtnEligible = false;

                if (isPendingApproval) {
                    // هر کسی که باید تأیید کند، دکمه‌های تأیید/رد را می‌بیند
                    if (isCreator || isAssignee) {
                        approveBtn.style.display = 'inline-block';
                        rejectBtn.style.display = 'inline-block';
                    }

                    // دکمه "تأیید و ارجاع" → فقط برای assignee‌ای که creator نیست
                    if (isAssignee && !isCreator) {
                        const approveAndDelegateBtn = document.getElementById('approveAndDelegateBtn');
                        if (approveAndDelegateBtn) {
                            approveAndDelegateBtn.style.display = 'inline-block';
                        }
                    }

                    // دکمه "تأیید و نگهداری" → فقط برای کارهای مقطعیِ غیرروتین، و فقط برای creator
                    if (isCreator && task.task_type === 'periodic' && !isWorkflow) {
                        const approveAndKeepBtn = document.getElementById('approveAndKeepBtn');
                        if (approveAndKeepBtn) {
                            approveAndKeepBtn.style.display = 'inline-block';
                        }
                    }

                    return;
                }

                // ✅ اصلاح: workflow tasks
                // ✅ منطق جدید workflow tasks
                if (isWorkflow) {

                    // ✅ چک 1: مسئولِ این مرحله (کاربرِ مشخص یا عضوِ واحد) باشد
                    // اگر تسک صراحتاً assignee دارد (تعریف/ارجاع/claim شده)، همان کافی است —
                    // به current_step_status گره نمی‌زنیم چون ممکن است از وضعیتِ واقعیِ تسک
                    // عقب بماند (مثلاً بعد از ارجاع) و دکمه‌های عملیات را برای مسئولِ واقعی مخفی کند.
                    // فقط برای تسکِ هنوز تخصیص‌نیافته (سراسرِ واحد) به «فعال بودنِ مرحله» نیاز داریم.
                    const stepActive = (task.current_step_status === 'active');
                    const inSection = currentUser && (task.assignee_id ?
                        isAssignee :
                        (stepActive && currentUser.activity_section === task.current_step_section));

                    // ✅ چک 2: آیا این task در مرحله فعلی workflow است؟
                    // const isCurrentStage = task.current_stage_id === task.current_workflow_step;
                    const canAct = inSection; // فقط بررسی بخش کافیه

                    // پنهان کردن همه دکمه‌ها به صورت پیش‌فرض
                    startBtn.style.display = 'none';
                    completeBtn.style.display = 'none';
                    addDiscBtn.style.display = 'none';
                    // ✅ مخفی کردن آیکن تمدید موعد
                    const deadlineIcon = document.querySelector('.deadline-request-icon');
                    if (deadlineIcon) {
                        deadlineIcon.style.display = 'none';
                    }
                    // ✅ نمایش دکمه‌ها فقط اگر هر دو شرط برقرار باشد
                    if (inSection) {

                        if (task.status === 'not_started' || task.status === 'delegated' || task.status === 'rejected') {
                            // کار شروع نشده، می‌توان شروع کرد
                            startBtn.style.display = 'inline-block';
                        } else if (task.status === 'in_progress') {
                            if (!isAssignee) {
                                // کار در حال انجام اما کاربر assignee نیست
                                startBtn.style.display = 'inline-block';
                            } else {
                                // کاربر assignee است
                                completeBtn.style.display = 'inline-block';
                                completeBtnEligible = true;
                                addDiscBtn.style.display = 'inline-block';
                            }
                        }

                    }

                    // دکمه ارجاع
                    if (task.status === 'approved' || isPendingApproval) {
                        delegateBtn.style.display = 'none';
                    } else if (isAssignee && task.status !== 'approved' && task.status !== 'completed' && task.status !== 'rejected') {
                        delegateBtn.style.display = 'inline-block';
                    } else {
                        delegateBtn.style.display = 'none';
                    }

                    // کارهای روتین از این صفحه قابل حذف نیستند؛
                    // حذف فقط توسط مدیریت و از صفحهٔ مانیتورینگ انجام می‌شود
                    deleteBtn.style.display = 'none';

                    applyChecklistGate();
                    return;
                }

                const completedCount = task.completed_count || 0;
                // ✅ نمایش دکمه بازتعریف فقط برای creator، و فقط وقتی کار در
                // وضعیتِ پایانی (تکمیل/کنسل/متوقف) نیست
                const isTaskTerminal = ['completed', 'approved', 'stopped', 'rejected'].includes(task.status);
                if (isCreator && !isTaskTerminal) {
                    redefineBtn.style.display = 'inline-block';
                } else {
                    redefineBtn.style.display = 'none';
                }

                if (task.task_type === 'periodic') {
                    if (task.status === 'completed' || task.status === 'approved') {
                        completeBtn.style.display = 'none';
                        addDiscBtn.style.display = 'none';
                        startBtn.style.display = 'none';
                    } else if (isAssignee && (task.status === 'not_started' || task.status === 'delegated')) {
                        startBtn.style.display = 'inline-block';
                        completeBtn.style.display = 'none';
                        addDiscBtn.style.display = 'none';
                    } else if (isAssignee) {
                        completeBtn.style.display = 'inline-block';
                        completeBtnEligible = true;
                        addDiscBtn.style.display = 'inline-block';
                    }
                } else if (task.task_type === 'continuous') {
                    if (lastApprovedDateToday) {
                        completeBtn.style.display = 'none';
                        addDiscBtn.style.display = 'none';
                    } else {
                        const canComplete = canCompleteNow(task);
                        if (!canComplete) {
                            completeBtn.style.display = 'none';
                            addDiscBtn.style.display = 'none';
                        } else if (isAssignee && task.status != 'rejected') {
                            completeBtn.style.display = 'inline-block';
                            completeBtnEligible = true;
                            addDiscBtn.style.display = 'inline-block';
                        }
                    }
                }

                if (task.status === 'not_started' && isAssignee && task.task_type !== 'continuous') {
                    startBtn.style.display = 'inline-block';
                    completeBtn.style.display = 'none';
                    completeBtnEligible = false;
                    addDiscBtn.style.display = 'none';
                }

                if (task.status === 'completed' || task.status === 'approved' || isPendingApproval || task.status === 'rejected') {
                    delegateBtn.style.display = 'none';
                } else if (isAssignee) {
                    delegateBtn.style.display = 'inline-block';
                } else {
                    delegateBtn.style.display = 'none';
                }

                if (isCreator && task.status === 'not_started') {
                    editBtn.style.display = 'inline-block';
                    deleteBtn.style.display = 'inline-block';
                } else {
                    editBtn.style.display = 'none';
                    // حذف: اگر کاربر جاری هم تعریف‌کننده هم مسئول انجام باشد و کار شروع شده باشد
                    if (isCreator && isAssignee && task.status === 'in_progress') {
                        deleteBtn.style.display = 'inline-block';
                    } else {
                        deleteBtn.style.display = 'none';
                    }
                }
                // ── دکمه اتمام دوره: فقط creator، فقط کارهای دوره‌ای، فقط وقتی تکمیل/تأیید نشده
                const terminatePeriodBtn = document.getElementById('terminatePeriodBtn');
                if (terminatePeriodBtn) {
                    const canTerminate = isCreator && ['continuous'].includes(task.task_type) &&
                        !['completed', 'approved', 'rejected'].includes(task.status);
                    terminatePeriodBtn.style.display = canTerminate ? 'inline-block' : 'none';
                }

                // ── دکمه درخواست رفع دوره‌های معوقه
                const clearOverdueBtn = document.getElementById('clearOverdueBtn');
                if (clearOverdueBtn) {
                    // ✅ از مقدار محاسبه‌شده سرور استفاده می‌کنیم (با احتساب تعطیلات و جمعه‌ها)
                    // به جای calculateStrictlyOverduePeriods که تعطیلات رو نمی‌شناسه.
                    // task.overdue_periods (پیرو period-engine.php::pe_state) از قبل
                    // هم دوره‌های تکمیل‌شده هم overdue_forgiven_credit رو کسر کرده —
                    // کسرِ دوباره‌ی completed_count/forgiven این‌جا باعث می‌شد عدد
                    // منفی بشه و دکمه برایِ هر تسکی که حداقل یک‌بار تکمیل شده
                    // (حتی با معوقه‌ی واقعی) همیشه مخفی بمونه
                    const _overdue = task.overdue_periods || 0;
                    const _remaining = Math.max(0, _overdue);
                    const canRequest = (task.task_type === 'continuous') &&
                        _remaining > 0 &&
                        (isAssignee || isCreator) &&
                        !['completed', 'approved', 'rejected'].includes(task.status);
                    if (canRequest && task.has_pending_overdue_request == 1) {
                        clearOverdueBtn.style.display = 'inline-block';
                        clearOverdueBtn.disabled = true;
                        clearOverdueBtn.innerHTML = '<i class="bi bi-hourglass-split ms-2"></i>درخواست رفع معوقه در انتظار تأیید';
                    } else if (canRequest) {
                        clearOverdueBtn.style.display = 'inline-block';
                        clearOverdueBtn.disabled = false;
                        clearOverdueBtn.innerHTML = '<i class="bi bi-eraser ms-2"></i>رفع دوره‌های معوقه';
                    } else {
                        clearOverdueBtn.style.display = 'none';
                    }
                }
                editBtn.style.display = 'none';
                setupRenewalButton(task);
                applyChecklistGate();
            }

            // ✅ منطق نمایشِ دکمه/بجِ تمدید دوره
            function setupRenewalButton(task) {
                const actionBtn = document.getElementById('renewalActionBtn');
                const actionBtnText = document.getElementById('renewalActionBtnText');
                const pendingBadge = document.getElementById('renewalPendingBadge');
                const reviewBtn = document.getElementById('reviewRenewalBtn');
                if (!actionBtn || !pendingBadge || !reviewBtn) return;

                actionBtn.style.display = 'none';
                pendingBadge.style.display = 'none';
                reviewBtn.style.display = 'none';

                if (task.task_type !== 'continuous') return;

                const today = new Date().toISOString().split('T')[0];
                const isReady = task.end_date &&
                    task.end_date <= today &&
                    task.is_pending_approval != 1;

                // درخواستی در جریان است؟
                if (task.has_pending_renewal_request == 1) {
                    fetchPendingRenewalRequest(task.id);
                    return;
                }

                if (!isReady) return;

                if (isCreator) {
                    actionBtn.style.display = 'inline-block';
                    actionBtnText.textContent = 'تمدید دوره';
                    actionBtn.onclick = () => openRenewalModal('apply');
                } else if (isAssignee) {
                    actionBtn.style.display = 'inline-block';
                    actionBtnText.textContent = 'درخواست تمدید دوره';
                    actionBtn.onclick = () => openRenewalModal('request');
                }
            }

            function isLastApprovedDateToday(task) {
                if (!task.last_approved_date) {
                    return false;
                }

                const today = new Date();
                today.setHours(0, 0, 0, 0);

                const lastApprovedDate = new Date(task.last_approved_date);
                lastApprovedDate.setHours(0, 0, 0, 0);

                return today.getTime() === lastApprovedDate.getTime();
            }

            function canCompleteNow(task) {
                if (task.task_type !== 'continuous') return true;

                const today = new Date();
                today.setHours(0, 0, 0, 0);

                const startDate = new Date(task.start_date);
                startDate.setHours(0, 0, 0, 0);

                if (today < startDate) {
                    return false;
                }

                const completedCount = task.completed_count || 0;
                const forgiven = task.overdue_forgiven_credit || 0;
                const overdueCount = calculateOverduePeriods(task);
                return completedCount + forgiven < overdueCount;
            }

            function calculateOverduePeriods(task) {
                if (task.task_type !== 'continuous') return 0;

                const today = new Date();
                today.setHours(0, 0, 0, 0);

                const startDate = new Date(task.start_date);
                startDate.setHours(0, 0, 0, 0);

                if (today < startDate) {
                    return 0;
                }

                const diffTime = today - startDate;
                const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));

                let periods = 0;
                switch (task.period_type) {
                    case 'daily':
                        periods = diffDays;
                        break;
                    case 'weekly':
                        periods = Math.floor(diffDays / 7);
                        break;
                    case 'monthly':
                        const monthsDiff = (today.getFullYear() - startDate.getFullYear()) * 12 +
                            (today.getMonth() - startDate.getMonth());
                        periods = monthsDiff;
                        break;
                    default:
                        periods = 0;
                }

                return Math.max(periods, today >= startDate ? 1 : 0);
            }
            // ✅ برخلاف calculateOverduePeriods، این تابع دوره‌ی جاری (هنوز بازِ) را
            // به‌عنوان معوقه حساب نمی‌کند؛ فقط دوره‌هایی که واقعاً سررسیدشان گذشته است.
            function calculateStrictlyOverduePeriods(task) {
                if (task.task_type !== 'continuous') return 0;

                const today = new Date();
                today.setHours(0, 0, 0, 0);

                const startDate = new Date(task.start_date);
                startDate.setHours(0, 0, 0, 0);

                if (today < startDate) return 0;

                const diffTime = today - startDate;
                const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));

                switch (task.period_type) {
                    case 'daily':
                        return diffDays;
                    case 'weekly':
                        return Math.floor(diffDays / 7);
                    case 'monthly':
                        return (today.getFullYear() - startDate.getFullYear()) * 12 +
                            (today.getMonth() - startDate.getMonth());
                    default:
                        return 0;
                }
            }

            function displayHistory(history) {
                // 🔒 کاربری که فقط آیتم چک‌لیست به او ارجاع شده، یا بیننده‌ای که
                // دسترسیِ تاریخچه براش خاموش شده: کل بخش تاریخچه پنهان
                if (window._isChecklistOnly || (window._isViewerOnly && !window._viewerCanViewHistory)) {
                    const hs = document.getElementById('historySection');
                    if (hs) hs.style.display = 'none';
                    return;
                }
                if (!history || history.length === 0) {
                    document.getElementById('taskHistory').innerHTML = '<p class="text-muted">بدون تاریخچه</p>';
                    return;
                }

                const actionBadgeClass = {
                    'created': 'ab-created',
                    'assigned': 'ab-assigned',
                    'completed': 'ab-completed',
                    'pending_approval': 'ab-pending',
                    'approved': 'ab-approved',
                    'rejected': 'ab-rejected',
                    'stopped': 'ab-stopped',
                    'delegated': 'ab-delegated',
                    'updated': 'ab-updated',
                    'deadline_extended': 'ab-deadline',
                    'deadline_rejected': 'ab-rejected',
                    'checklist_sync': 'ab-updated',
                    'checklist_assigned': 'ab-delegated',
                    'checklist_done': 'ab-completed',
                    'workflow_prev_note': 'ab-completed'
                };

                let html = '';
                history.forEach(item => {
                    const actionLabel = getActionLabel(item.action);
                    const badgeClass = actionBadgeClass[item.action] || 'ab-updated';
                    const userName = item.from_user_first_name || item.from_user_last_name ?
                        `${item.from_user_first_name || ''} ${item.from_user_last_name || ''}`.trim() :
                        'نامشخص';

                    const dateOnly = item.created_at ?
                        new Date(item.created_at).toLocaleDateString('fa-IR', {
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric'
                        }) :
                        '';
                    const timeOnly = item.created_at ?
                        new Date(item.created_at).toLocaleTimeString('fa-IR', {
                            hour: '2-digit',
                            minute: '2-digit'
                        }) :
                        '';

                    // build notes section
                    let notesHTML = '';
                    if (item.action === 'deadline_extended' && item.notes) {
                        try {
                            const n = JSON.parse(item.notes);
                            const oldD = n.old_deadline ?
                                new Date(n.old_deadline).toLocaleDateString('fa-IR', {
                                    year: 'numeric',
                                    month: 'long',
                                    day: 'numeric'
                                }) :
                                'نامشخص';
                            const newD = n.new_deadline ?
                                new Date(n.new_deadline).toLocaleDateString('fa-IR', {
                                    year: 'numeric',
                                    month: 'long',
                                    day: 'numeric'
                                }) :
                                'نامشخص';
                            notesHTML = `
                            <div class="ml-deadline-inline">
                                <span class="ml-cross">${oldD}</span>
                                <i class="bi bi-arrow-left ml-arrow-icon"></i>
                                <span class="ml-bold-new">${newD}</span>
                            </div>
                            ${n.reason ? `<div class="ml-reason-mini">دلیل: ${n.reason}</div>` : ''}`;
                        } catch (e) {
                            notesHTML = `<div class="ml-notes">${item.notes}</div>`;
                        }
                    } else if (item.notes) {
                        const txt = item.notes.replace(/\r\n/g, '<br>').replace(/\n/g, '<br>');
                        notesHTML = `<div class="ml-notes">${txt}</div>`;
                    }
                    // ✅ ساخت متن توضیحات خاص برای هر action
                    let customNotesHTML = notesHTML;

                    // برای ایجاد: نمایش نام assignee
                    if (item.action === 'created') {
                        if (item.to_user_id && item.to_user_first_name) {
                            const toName = `${item.to_user_first_name || ''} ${item.to_user_last_name || ''}`.trim();
                            if (toName && toName !== userName) {
                                customNotesHTML = `<div class="ml-notes">واگذار به: ${toName}</div>` + customNotesHTML;
                            }
                        }
                        // ✅ نمایش توضیحات کار در تاریخچه ایجاد
                        if (taskData && taskData.description) {
                            const descTxt = taskData.description.replace(/\r\n/g, '<br>').replace(/\n/g, '<br>');
                            customNotesHTML += `<div class="ml-notes" style="color:var(--text-muted);">توضیحات: ${descTxt}</div>`;
                        }
                    }

                    // برای ارجاع: نمایش نام مقصد
                    if (item.action === 'delegated' && item.to_user_id && item.to_user_first_name) {
                        const toName = `${item.to_user_first_name || ''} ${item.to_user_last_name || ''}`.trim();
                        if (toName && !notesHTML.includes(toName)) {
                            customNotesHTML = `<div class="ml-notes">ارجاع به ${toName}</div>` + (notesHTML || '');
                        }
                    }
                    // ✅ جدید: برای در انتظار تأیید: نمایش نام تأییدکننده
                    if (item.action === 'pending_approval' && item.to_user_id && item.to_user_first_name) {
                        const toName = `${item.to_user_first_name || ''} ${item.to_user_last_name || ''}`.trim();
                        if (toName) {
                            customNotesHTML = (notesHTML || '') + `<div class="ml-notes">در انتظار تأیید: ${toName}</div>`;
                        }
                    }
                    // ✅ برای رد درخواست تمدید موعد: نمایش نام درخواست‌دهنده (to_user = کسی که درخواست داده بود)
                    if (item.action === 'deadline_rejected' && item.to_user_id && item.to_user_first_name) {
                        const toName = `${item.to_user_first_name || ''} ${item.to_user_last_name || ''}`.trim();
                        const reasonHTML = item.notes ?
                            `<div class="ml-reason-mini">توضیح: ${item.notes.replace(/\r\n/g, '<br>').replace(/\n/g, '<br>')}</div>` :
                            '';
                        if (toName) {
                            customNotesHTML = `<div class="ml-notes">درخواست تمدید موعد از ${toName} رد شد</div>` + reasonHTML;
                        }
                    }

                    html += `
        <div class="ml-item">
            <div class="ml-left">
                <div class="ml-time">${dateOnly}<br>${timeOnly}</div>
                <span class="ml-action-badge ${badgeClass}">${actionLabel}</span>
            </div>
            <div class="ml-right">
                <div class="ml-user"><i class="bi bi-person"></i> ${userName}</div>
                ${customNotesHTML}
            </div>
        </div>`;

                });

                document.getElementById('taskHistory').innerHTML =
                    `<div class="minimal-list">${html}</div>`;
            }

            async function loadUsers() {
                try {
                    if (!orgSections || !orgSections.length) {
                        await loadSections();
                    }
                    const response = await fetch('../api/users/list.php', {
                        headers: {
                            'Authorization': 'Bearer ' + authToken
                        }
                    });

                    const data = await response.json();

                    if (data.success) {
                        checklistUsers = data.users;
                        const secMap = {};
                        (orgSections || []).forEach(s => {
                            secMap[s.section_key] = s.section_label;
                        });
                        AssigneePicker.create({
                            container: '#delegatePicker',
                            users: data.users,
                            sectionMap: secMap,
                            showSections: false,
                            onSelect: (_, v) => {
                                delegateTargetId = v || '';
                            }
                        });
                    }
                } catch (error) {
                    console.error('Error loading users:', error);
                }
            }

            function showAddDiscModal() {

                selectedFilesForModal = [];
                displaySelectedFilesInModal();
                document.getElementById('addDiscNotes').value = '';


                new bootstrap.Modal(document.getElementById('addDiscModal')).show();
            }

            async function submitAddDisc() {
                currentUser = JSON.parse(localStorage.getItem('user_info'));
                const notes = currentUser.first_name + ' ' + currentUser.last_name + ": " + document.getElementById('addDiscNotes').value;

                try {
                    const response = await fetch('../api/tasks/update-status.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': 'Bearer ' + authToken
                        },
                        body: JSON.stringify({
                            task_id: taskId,
                            status: taskData.status,
                            notes: notes
                        })
                    });
                    const data = await response.json();
                    const t = showToast(data.message, 'info');



                    if (data.success) {
                        bootstrap.Modal.getInstance(document.getElementById('addDiscModal')).hide();
                        location.reload();
                    }
                } catch (error) {
                    const t = showToast('خطا در درج توضیح', 'warning');

                }
            }

            function showCompleteDiscModal() {
                new bootstrap.Modal(document.getElementById('completeDiscModal')).show();
            }

            async function submitCompleteDisc() {
                currentUser = JSON.parse(localStorage.getItem('user_info'));
                const userName = currentUser.first_name + ' ' + currentUser.last_name;
                const userNotes = document.getElementById('completeDiscNotes').value.trim();
                let notes;
                if (userNotes) {
                    notes = ' کار تکمیل شد. توضیحات: ' + userNotes;
                } else {
                    notes = ' کار تکمیل شد';
                }
                let newStatus = 'completed';

                if (taskData.is_workflow_task == 1) {
                    newStatus = 'approved';
                }

                try {
                    const response = await fetch('../api/tasks/update-status.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': 'Bearer ' + authToken
                        },
                        body: JSON.stringify({
                            task_id: taskId,
                            status: newStatus,
                            notes: notes
                        })
                    });
                    const data = await response.json();
                    const t = showToast(data.message, 'info');


                    bootstrap.Modal.getInstance(document.getElementById('completeDiscModal')).hide();
                    location.reload();

                } catch (error) {
                    const t = showToast('خطا در تکمیل کار', 'info');


                }
            }
            async function doApproveAndDelegate(toUserId, delegateNotes) {
                try {
                    const response = await fetch('../api/tasks/approve-and-delegate.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': 'Bearer ' + authToken
                        },
                        body: JSON.stringify({
                            task_id: taskId,
                            to_user_id: toUserId,
                            approve_notes: window._approveNotesForDelegate || '',
                            delegate_notes: delegateNotes
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        showToast(data.message, 'success');
                        bootstrap.Modal.getInstance(document.getElementById('delegateModal')).hide();
                        goBackSmart();
                    } else {
                        showToast(data.message || 'خطا در تأیید و ارجاع', 'warning');
                    }
                } catch (error) {
                    showToast('خطا در ارتباط با سرور', 'warning');
                }
            }

            function showDelegateModal() {
                // منبع امن: از window._currentTask استفاده کن (در loadTaskDetails ست می‌شود)
                const t = window._currentTask || null;
                const wrap = document.getElementById('delegateShareHistoryWrap');
                const chk = document.getElementById('delegateShareHistory');
                const isCreator = (currentUser && t && currentUser.id == t.creator_id);
                if (wrap) wrap.style.display = isCreator ? 'block' : 'none';
                if (chk && t) chk.checked = (parseInt(t.share_history) !== 0);

                // ✅ اگه کارِ مقطعیِ خودی (بدونِ موعد) داره ارجاع می‌شه، تعیینِ موعد الزامیه
                const dueContainer = document.getElementById('delegateDueDateContainer');
                const needsDueDate = !!(t && t.task_type === 'periodic' && !t.due_date);
                if (dueContainer) dueContainer.style.display = needsDueDate ? 'block' : 'none';
                if (needsDueDate) {
                    document.getElementById('delegateDueDate').removeAttribute('data-date');
                    document.getElementById('delegateDueDate').value = '';
                    initPersianDatepickerForModal('delegateDueDate', null);
                }

                new bootstrap.Modal(document.getElementById('delegateModal')).show();
            }

            async function submitDelegate() {
                const toUserId = delegateTargetId;
                const notes = currentUser.first_name + ' ' + currentUser.last_name + ": " + document.getElementById('delegateNotes').value;
                const shareHistory = document.getElementById('delegateShareHistory').checked;

                if (!toUserId) {
                    const t = showToast('لطفا کاربر مقصد را انتخاب کنید', 'info');

                    return;
                }

                // ✅ اگه این کار موعد نداشت، انتخابِ موعد الزامیه
                const dueContainer = document.getElementById('delegateDueDateContainer');
                let delegateDueDate = null;
                if (dueContainer && dueContainer.style.display !== 'none') {
                    delegateDueDate = document.getElementById('delegateDueDate').getAttribute('data-date');
                    if (!delegateDueDate) {
                        showToast('لطفاً موعدِ انجام را انتخاب کنید', 'warning');
                        return;
                    }
                }

                if (window._approveNotesForDelegate !== undefined) {
                    await doApproveAndDelegate(toUserId, notes);
                    window._approveNotesForDelegate = undefined;
                    return;
                }

                try {
                    const response = await fetch('../api/tasks/delegate.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': 'Bearer ' + authToken
                        },
                        body: JSON.stringify({
                            task_id: taskId,
                            to_user_id: toUserId,
                            notes: notes,
                            share_history: shareHistory,
                            due_date: delegateDueDate
                        })
                    });

                    const data = await response.json();
                    const t = showToast(data.message, 'info');


                    if (data.success) {
                        bootstrap.Modal.getInstance(document.getElementById('delegateModal')).hide();
                        goBackSmart();
                    }
                } catch (error) {
                    const t = showToast('خطا در ارجاع کار', 'info');

                }
            }

            function editTask() {}

            async function restoreTask(id) {
                try {
                    const res = await fetch('../api/tasks/restore.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': 'Bearer ' + authToken
                        },
                        body: JSON.stringify({
                            task_id: id
                        })
                    });
                    const d = await res.json();
                    if (d.success) location.reload();
                    else showToast(d.message || 'بازگرداندن انجام نشد', 'warning');
                } catch (e) {
                    showToast('خطا در ارتباط با سرور', 'warning');
                }
            }

            async function deleteTask() {
                showToast('آیا مطمئن هستید که می‌خواهید این کار را حذف کنید؟', 'warning', {
                    duration: 1500000,
                    buttons: [{
                            label: 'بله، حذف شود',
                            style: 'primary',
                            onClick: async function() {
                                try {
                                    const response = await fetch('../api/tasks/delete.php', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'Authorization': 'Bearer ' + authToken
                                        },
                                        body: JSON.stringify({
                                            task_id: taskId
                                        })
                                    });

                                    const data = await response.json();

                                    if (data.success) {
                                        let undone = false;
                                        const back = document.referrer || 'tasks.php';
                                        showUndoToast({
                                            title: 'حذف کار',
                                            message: 'کار حذف شد',
                                            duration: 3000,
                                            onUndo: async () => {
                                                undone = true;
                                                await restoreTask(taskId);
                                            }
                                        });
                                        // اگر تا پایان مهلت، بازگردانی نشد → برگشت به صفحه قبل
                                        setTimeout(() => {
                                            if (!undone) window.location.href = back;
                                        }, 3200);
                                    } else {
                                        const t = showToast('خطا در حذف کار', 'warning');

                                    }
                                } catch (error) {
                                    const t = showToast('خطا در ارتباط با سرور', 'warning');


                                }
                            }
                        },
                        {
                            label: 'خیر، منصرف شدم',
                            style: 'ghost',
                            onClick: function() {
                                return;
                            }
                        }
                    ]
                });


            }

            function getPriorityLabel(priority) {
                const labels = {
                    'high': 'بالا',
                    'medium': 'متوسط',
                    'low': 'پایین'
                };
                return labels[priority] || priority;
            }

            function getStatusLabel(status, assigneeId) {
                // status='delegated' وقتی از دیدِ خودِ assigneeِ جدید (کاربرِ
                // فعلی) دیده بشه، دیگه «ارجاع شد» معنی نداره — نوبتِ خودشه که
                // شروعش کنه، دقیقاً هم‌ردیفِ not_started (مطابقِ همون منطقی که
                // برایِ بجِ داشبورد در assets/js/task-filters.js اضافه شد)
                if (status === 'delegated' && currentUser && Number(assigneeId) === Number(currentUser.id)) {
                    return 'شروع نشده';
                }
                const labels = {
                    'in_progress': 'در حال انجام',
                    'completed': 'انجام شد',
                    'approved': 'تأیید و انجام شد',
                    'pending_approval': 'در انتظار تأیید',
                    'delegated': 'ارجاع شد',
                    'not_started': 'شروع نشده',
                    'termination_requested': 'درخواست اتمام',
                    'rejected': 'متوقف شده',
                    'period_done': 'دوره انجام شد'
                };
                return labels[status] || status;
            }

            function getPeriodLabel(period) {
                const labels = {
                    'daily': 'روزانه',
                    'weekly': 'هفتگی',
                    'monthly': 'ماهانه'
                };
                return labels[period] || period;
            }

            function getActionLabel(action) {
                const labels = {
                    'created': 'ایجاد',
                    'assigned': 'واگذاری',
                    'completed': 'تکمیل',
                    'pending_approval': 'در انتظار تأیید',
                    'approved': 'تأیید',
                    'rejected': 'رد',
                    'stopped': 'توقف',
                    'delegated': 'ارجاع',
                    'updated': 'یادآوری',
                    'deadline_extended': 'تمدید موعد',
                    'deadline_rejected': 'رد درخواست تمدید موعد',
                    'termination_requested': 'درخواست اتمام',
                    'checklist_sync': 'به‌روزرسانی چک‌لیست',
                    'checklist_assigned': 'ارجاع آیتم چک‌لیست',
                    'checklist_done': 'انجام آیتم چک‌لیست',
                    'period_done': 'دوره انجام شد',
                    'workflow_prev_note': 'توضیحات مرحلهٔ قبل',
                };
                return labels[action] || action;
            }

            function formatPersianDate(dateString) {
                if (!dateString) return 'نامشخص';
                try {
                    const date = new Date(dateString);

                    const options = {
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                    };
                    return date.toLocaleDateString('fa-IR', options);
                } catch (e) {
                    return dateString;
                }
            }

            function formatDateTime(dateString) {
                if (!dateString) return 'نامشخص';
                try {
                    const date = new Date(dateString);
                    date.setDate(date.getDate());
                    const dateOptions = {
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                    };
                    const timeOptions = {
                        hour: '2-digit',
                        minute: '2-digit'
                    };
                    const persianDate = date.toLocaleDateString('fa-IR', dateOptions);
                    const persianTime = date.toLocaleTimeString('fa-IR', timeOptions);
                    return `${persianDate} - ${persianTime}`;
                } catch (e) {
                    return dateString;
                }
            }
            // ✅ هندلر بستن modal‌ها
            function closeModal(modalId) {
                const modal = document.getElementById(modalId);
                if (modal) {
                    modal.style.display = 'none';
                }
            }
            async function startThisTask() {
                const response = await fetch('../api/tasks/update-status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + localStorage.getItem('auth_token')
                    },
                    body: JSON.stringify({
                        task_id: taskId,
                        status: 'in_progress',
                        notes: 'کار شروع شد'
                    })
                });

                const data = await response.json();
                if (data.success) {
                    const t = showToast('کار شروع شد', 'warning');

                    location.reload();
                } else {
                    const t = showToast('خطا در شروع کار: ' + (data.message || 'نامشخص'), 'warning');

                }
            }

            function enTofaNumber(numb) {
                const persianNumbers = "۰۱۲۳۴۵۶۷۸۹";
                const englishNumbers = "0123456789";
                return String(numb).replace(/[0-9]/g, d => persianNumbers[englishNumbers.indexOf(d)]);
            }

            function showAlert(message, type = 'info') {
                const map = {
                    danger: 'warning',
                    error: 'warning'
                };
                showToast(message, map[type] || type);
            }

            function showApproveModal() {
                new bootstrap.Modal(document.getElementById('approveModal')).show();
            }

            function showRejectModal() {
                new bootstrap.Modal(document.getElementById('rejectModal')).show();
            }

            async function submitApprove() {
                const notes = document.getElementById('approveNotes').value;

                try {
                    const response = await fetch('../api/tasks/approve.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': 'Bearer ' + authToken
                        },
                        body: JSON.stringify({
                            task_id: taskId,
                            approve: true,
                            notes: notes
                        })
                    });

                    const data = await response.json();
                    const t = showToast(data.message, 'info');

                    if (data.success) {
                        bootstrap.Modal.getInstance(document.getElementById('approveModal')).hide();
                        location.reload();
                    }
                } catch (error) {
                    const t = showToast('خطا در تأیید کار', 'warning');

                }
            }

            // ===== تأیید و نگهداری کار =====
            async function submitApproveAndKeep() {
                const notes = document.getElementById('approveNotes').value.trim();

                try {
                    const response = await fetch('../api/tasks/approve-and-keep.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': 'Bearer ' + authToken
                        },
                        body: JSON.stringify({
                            task_id: taskId,
                            notes: notes
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        bootstrap.Modal.getInstance(document.getElementById('approveModal')).hide();
                        showToast(data.message || 'کار تأیید شد و در لیست شما نگه‌داشته شد', 'success');
                        setTimeout(() => location.reload(), 1200);
                    } else {
                        showToast(data.message || 'خطا در تأیید و نگهداری', 'warning');
                    }
                } catch (error) {
                    showToast('خطا در ارتباط با سرور', 'warning');
                }
            }

            // ===== تأیید و ارجاع =====
            function showApproveAndDelegateFlow() {
                // بستن مودال تأیید
                const approveModal = bootstrap.Modal.getInstance(document.getElementById('approveModal'));
                if (approveModal) approveModal.hide();

                // ذخیره توضیحات تأیید
                window._approveNotesForDelegate = document.getElementById('approveNotes').value || '';

                // باز کردن مودال ارجاع
                setTimeout(() => {
                    showDelegateModal();
                }, 500);
            }

            async function submitApproveAndDelegate(toUserId, delegateNotes) {
                try {
                    const response = await fetch('../api/tasks/approve-and-delegate.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': 'Bearer ' + authToken
                        },
                        body: JSON.stringify({
                            task_id: taskId,
                            to_user_id: toUserId,
                            approve_notes: window._approveNotesForDelegate || '',
                            delegate_notes: delegateNotes
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        showToast(data.message, 'success');
                        bootstrap.Modal.getInstance(document.getElementById('delegateModal')).hide();
                    } else {
                        showToast(data.message || 'خطا در تأیید و ارجاع', 'warning');
                    }
                } catch (error) {
                    showToast('خطا در ارتباط با سرور', 'warning');
                }
            }

            async function submitReject() {
                const notes = document.getElementById('rejectNotes').value.trim();

                if (!notes) {
                    const t = showToast('لطفا دلیل کار را وارد کنید', 'info');

                    return;
                }

                try {
                    const response = await fetch('../api/tasks/approve.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': 'Bearer ' + authToken
                        },
                        body: JSON.stringify({
                            task_id: taskId,
                            approve: false,
                            notes: notes
                        })
                    });

                    const data = await response.json();
                    const t = showToast(data.message, 'info');


                    if (data.success) {
                        bootstrap.Modal.getInstance(document.getElementById('rejectModal')).hide();
                        location.reload();
                    }
                } catch (error) {
                    const t = showToast('خطا در رد کار', 'warning');

                }
            }
            // نمایش modal بازتعریف
            let redefineTargetId = '';

            function showRedefineModal() {
                // پر کردن فیلدها با اطلاعات فعلی
                document.getElementById('redefineTitle').value = taskData.title;
                document.getElementById('redefineDescription').value = taskData.description || '';

                // ✅ تنظیم تاریخ هوشمند با پارس دستی
                const today = new Date();
                today.setHours(12, 0, 0, 0); // noon برای جلوگیری از مشکل timezone

                let defaultDate = today;

                if (taskData.due_date) {
                    // ✅ پارس دستی تاریخ (بدون timezone)
                    const [year, month, day] = taskData.due_date.split('-').map(Number);
                    const taskDueDate = new Date(year, month - 1, day, 12, 0, 0);



                    // اگر تاریخ تسک در آینده است، همان را استفاده کن
                    if (taskDueDate >= today) {
                        defaultDate = taskDueDate;
                    }
                    // اگر در گذشته است، امروز + 7 روز
                    else {
                        defaultDate = new Date(today);
                        defaultDate.setDate(today.getDate() + 7);
                    }
                }

                // نمایش/مخفی کردن فیلد تاریخ بر اساس نوع تسک
                const dueDateContainer = document.getElementById('redefineDueDateContainer');
                if (taskData.task_type === 'periodic') {
                    dueDateContainer.style.display = 'block';

                    // ✅ تبدیل به فرمت YYYY-MM-DD
                    const year = defaultDate.getFullYear();
                    const month = String(defaultDate.getMonth() + 1).padStart(2, '0');
                    const day = String(defaultDate.getDate()).padStart(2, '0');
                    const dateString = `${year}-${month}-${day}`;


                    // راه‌اندازی datepicker با تاریخ پیش‌فرض
                    setTimeout(() => {
                        initPersianDatepickerForModal('redefineDueDate', dateString);
                    }, 100);
                } else {
                    dueDateContainer.style.display = 'none';
                }

                // بارگذاری لیست کاربران (پیکر سرچ‌دار)
                redefineTargetId = '';
                loadUsersForRedefine();

                // نمایش modal
                // نمایش checkbox فایل‌ها اگر پیوست دارد
                const attachmentsOption = document.getElementById('redefineAttachmentsOption');
                const attachmentsCount = document.getElementById('redefineAttachmentsCount');
                const attachmentItems = document.querySelectorAll('#attachmentsList [data-attachment-id]');
                if (attachmentItems.length > 0) {
                    attachmentsOption.style.display = 'block';
                    attachmentsCount.textContent = enTofaNumber(attachmentItems.length) + ' فایل پیوست موجود است';
                } else {
                    attachmentsOption.style.display = 'none';
                }

                // نمایش modal
                new bootstrap.Modal(document.getElementById('redefineModal')).show();
            }
            // راه‌اندازی datepicker برای modal بازتعریف
            function initPersianDatepickerForRedefine(inputId, defaultDateString) {
                const input = document.getElementById(inputId);
                if (!input) return;

                // پاک کردن datepicker قبلی
                input.value = '';
                input.removeAttribute('data-date');

                // استفاده از همان تابع موجود
                setTimeout(() => {
                    initPersianDatepickerForModal(inputId, defaultDateString);
                }, 100);
            }
            // بارگذاری لیست کاربران
            async function loadUsersForRedefine() {
                try {
                    if (!orgSections || !orgSections.length) {
                        await loadSections();
                    }
                    const response = await fetch('../api/users/list.php', {
                        headers: {
                            'Authorization': 'Bearer ' + authToken
                        }
                    });
                    const data = await response.json();
                    if (data.success) {
                        const usersForPicker = data.users.slice();

                        // نگاشت کلید واحد → نام فارسی (طبق استاندارد بقیهٔ بخش‌ها)
                        const secMap = {};
                        (orgSections || []).forEach(s => {
                            secMap[s.section_key] = s.section_label;
                        });

                        // پیش‌فرض: خودم (id کاربر جاری)
                        const me = JSON.parse(localStorage.getItem('user_info'));
                        redefineTargetId = me ? String(me.id) : '';

                        AssigneePicker.create({
                            container: '#redefinePicker',
                            users: usersForPicker,
                            sectionMap: secMap,
                            showSections: false,
                            onSelect: (_, v) => {
                                if (v === '' || v === null) {
                                    // «خودم» انتخاب شده
                                    redefineTargetId = me ? String(me.id) : '';
                                } else {
                                    redefineTargetId = v || '';
                                }
                            }
                        });
                    }
                } catch (error) {
                    console.error('Error loading users:', error);
                    showAlert('خطا در بارگذاری کاربران', 'danger');
                }
            }

            // ارسال درخواست بازتعریف
            // ارسال درخواست بازتعریف
            async function submitRedefine() {
                const title = document.getElementById('redefineTitle').value.trim();
                const description = document.getElementById('redefineDescription').value.trim();
                const assigneeId = redefineTargetId;

                if (!title) {
                    showAlert('عنوان کار الزامی است', 'warning');
                    return;
                }

                if (!assigneeId) {
                    showAlert('لطفاً کاربر مقصد را انتخاب کنید', 'warning');
                    return;
                }

                try {
                    // جمع‌آوری آیدی فایل‌های پیوست در صورت انتخاب کاربر
                    // جمع‌آوری آیدی فایل‌ها در صورت انتخاب کاربر
                    let copyAttachmentIds = [];
                    const includeAttachments = document.getElementById('redefineIncludeAttachments');
                    if (includeAttachments && includeAttachments.checked) {
                        document.querySelectorAll('#attachmentsList [data-attachment-id]').forEach(el => {
                            copyAttachmentIds.push(parseInt(el.getAttribute('data-attachment-id')));
                        });
                    }

                    const requestBody = {
                        title: title,
                        description: description,
                        assignee_id: assigneeId,
                        priority: taskData.priority,
                        task_type: taskData.task_type,
                        period_type: taskData.period_type,
                        copy_attachment_ids: copyAttachmentIds
                    };

                    // ✅ برای periodic: due_date از datepicker
                    if (taskData.task_type === 'periodic') {
                        const dueDateInput = document.getElementById('redefineDueDate');
                        const dueDate = dueDateInput.getAttribute('data-date');

                        if (!dueDate) {
                            showAlert('لطفاً موعد انجام را انتخاب کنید', 'warning');
                            return;
                        }

                        requestBody.due_date = dueDate;
                    }

                    // ✅ برای continuous: محاسبه start_date (اول دوره بعدی بعد از امروز)
                    // ✅ برای continuous: محاسبه start_date (اول دوره بعدی بعد از امروز)
                    // ✅ برای continuous: محاسبه start_date (اول دوره بعدی بعد از امروز)
                    if (taskData.task_type === 'continuous' && taskData.period_type && taskData.start_date) {
                        // ✅ پارس کردن تاریخ با timezone محلی
                        const [year, month, day] = taskData.start_date.split('-').map(Number);
                        const originalStart = new Date(year, month - 1, day, 12, 0, 0); // noon برای جلوگیری از مشکل timezone

                        const today = new Date();
                        today.setHours(12, 0, 0, 0); // noon

                        // محاسبه تفاوت روزها
                        const diffTime = today - originalStart;
                        const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));

                        let nextPeriodDate;

                        switch (taskData.period_type) {
                            case 'daily':
                                // دوره بعدی = فردا
                                nextPeriodDate = new Date(year, month - 1, day + diffDays + 1, 12, 0, 0);
                                break;

                            case 'weekly':
                                // تعداد هفته‌های کامل گذشته
                                const weeksPassed = Math.floor(diffDays / 7);
                                const daysToAdd = (weeksPassed + 1) * 7;

                                // دوره بعدی
                                nextPeriodDate = new Date(year, month - 1, day + daysToAdd, 12, 0, 0);
                                break;

                            case 'monthly':
                                // تعداد ماه‌های گذشته
                                const todayYear = today.getFullYear();
                                const todayMonth = today.getMonth();
                                const monthsPassed = (todayYear - year) * 12 + (todayMonth - (month - 1));

                                // دوره بعدی
                                nextPeriodDate = new Date(year, month - 1 + monthsPassed + 1, day, 12, 0, 0);
                                break;
                        }

                        // ✅ تبدیل به فرمت YYYY-MM-DD
                        const nextYear = nextPeriodDate.getFullYear();
                        const nextMonth = String(nextPeriodDate.getMonth() + 1).padStart(2, '0');
                        const nextDay = String(nextPeriodDate.getDate()).padStart(2, '0');

                        requestBody.start_date = `${nextYear}-${nextMonth}-${nextDay}`;

                    }

                    const response = await fetch('../api/tasks/create.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': 'Bearer ' + authToken
                        },
                        body: JSON.stringify(requestBody)
                    });

                    const data = await response.json();

                    if (data.success) {
                        showAlert('کار جدید با موفقیت ایجاد شد', 'success');
                        bootstrap.Modal.getInstance(document.getElementById('redefineModal')).hide();
                        // پاک کردن فرم
                        document.getElementById('redefineTitle').value = '';
                        document.getElementById('redefineDescription').value = '';
                        redefineTargetId = '';
                        document.getElementById('redefineDueDate').value = '';
                    } else {
                        showAlert(data.message || 'خطا در ایجاد کار', 'danger');
                    }
                } catch (error) {
                    console.error('Error redefining task:', error);
                    showAlert('خطا در ارتباط با سرور', 'danger');
                }
            }

            // ============== مدیریت آپلود فایل ==============

            let currentTaskId = null;

            // بارگذاری فایل‌های پیوست
            async function loadAttachments() {
                try {
                    if (!taskId) return;

                    // 🔒 بیننده‌ای که دسترسیِ پیوست براش خاموش شده: کل بخش پنهان
                    if (window._isViewerOnly && !window._viewerCanViewAttachments) {
                        const section = document.getElementById('attachmentsSection');
                        if (section) section.style.display = 'none';
                        return;
                    }

                    try {
                        const response = await fetch(`../api/tasks/get-attachments.php?task_id=${taskId}`, {
                            headers: {
                                'Authorization': 'Bearer ' + authToken
                            }
                        });

                        const data = await response.json();

                        if (data.success) {
                            displayAttachments(data.attachments);
                            // اگر فایل وجود داره، آکاردئون رو باز کن
                            if (data.attachments && data.attachments.length > 0) {
                                const attachmentsBody = document.getElementById('attachmentsBody');
                                const attachmentsChevron = document.getElementById('attachmentsChevron');
                                if (attachmentsBody && attachmentsBody.style.display === 'none') {
                                    attachmentsBody.style.display = 'block';
                                    if (attachmentsChevron) {
                                        attachmentsChevron.style.transform = 'rotate(180deg)';
                                    }
                                }
                            }
                        }
                    } catch (error) {
                        console.error('Error loading attachments:', error);
                    }
                } catch (e) {
                    console.warn('attachments load failed:', e);
                }
            }

            // نمایش فایل‌ها - نسخه بروز شده با قابلیت ویرایش نام
            // نمایش فایل‌ها
            function displayAttachments(attachments) {
                const container = document.getElementById('attachmentsList');

                // ─── بروزرسانی badge تعداد فایل‌ها ───────────────────────────
                const countBadge = document.getElementById('attachmentsCountBadge');
                if (countBadge) {
                    if (attachments && attachments.length > 0) {
                        countBadge.textContent = enTofaNumber(attachments.length);
                        countBadge.style.display = 'inline-block';
                    } else {
                        countBadge.style.display = 'none';
                    }
                }

                // ─── بررسی دسترسی آپلود ───────────────────────────────────────
                const uploadArea = document.getElementById('uploadArea');
                if (uploadArea) {
                    uploadArea.style.display = isAssignee ? 'block' : 'none';
                }

                if (!attachments || attachments.length === 0) {
                    container.innerHTML = `
            <div class="no-attachments">
                <i class="bi bi-inbox"></i>
                <p>هیچ فایلی پیوست نشده است</p>
            </div>
        `;
                    return;
                }

                let html = '';
                attachments.forEach(attachment => {
                    const icon = getFileIcon(attachment.file_type);
                    const preview = attachment.is_image ?
                        `<img src="${attachment.file_path}" alt="${attachment.file_original_name}">` :
                        `<i class="bi ${icon}"></i>`;

                    const canDelete = attachment.can_delete === true;

                    const deleteBtn = canDelete ?
                        `<button class="btn-icon btn-delete" onclick="deleteAttachment(${attachment.id})" title="حذف">
           <i class="bi bi-trash" style="line-height: 0"></i>
       </button>` :
                        '';

                    html += `
            <div class="attachment-item" data-id="${attachment.id}" data-attachment-id="${attachment.id}">

                <div class="attachment-preview">
                    ${preview}
                </div>
                <div class="attachment-info">
                    <div class="attachment-name" title="${attachment.file_original_name}">
                        ${attachment.file_original_name}
                    </div>
                    <div class="attachment-meta">
                        <span><i class="bi bi-hdd ms-1"></i>${attachment.file_size_formatted}</span>
                        <span><i class="bi bi-person ms-1"></i>${attachment.uploader_name || 'نامشخص'}</span>
                        <span><i class="bi bi-clock ms-1"></i>${formatDateTime(attachment.created_at)}</span>
                    </div>
                </div>
                <div class="attachment-actions">
                    <button class="btn-icon btn-download" onclick="downloadAttachment('${attachment.file_path}', '${attachment.file_original_name}')" title="دانلود">
                        <i class="bi bi-download" style="line-height: 0"></i>
                    </button>
                    ${deleteBtn}
                </div>
            </div>
        `;
                });

                container.innerHTML = html;
            }

            // تابع کمکی برای escape کردن HTML
            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
            }

            // آیکون بر اساس نوع فایل
            function getFileIcon(fileType) {
                const icons = {
                    'pdf': 'bi-file-earmark-pdf file-icon-pdf',
                    'doc': 'bi-file-earmark-word file-icon-doc',
                    'docx': 'bi-file-earmark-word file-icon-doc',
                    'xls': 'bi-file-earmark-excel file-icon-excel',
                    'xlsx': 'bi-file-earmark-excel file-icon-excel',
                    'mp3': 'bi-file-earmark-music file-icon-audio',
                    'm4a': 'bi-file-earmark-music file-icon-audio',
                    'ogg': 'bi-file-earmark-music file-icon-audio',
                    'jpg': 'bi-file-earmark-image file-icon-image',
                    'jpeg': 'bi-file-earmark-image file-icon-image',
                    'png': 'bi-file-earmark-image file-icon-image'
                };

                return icons[fileType] || 'bi-file-earmark';
            }

            // تنظیم event listeners برای آپلود
            function setupUploadListeners() {
                const uploadArea = document.getElementById('uploadArea');
                const fileInput = document.getElementById('fileInput');

                if (!uploadArea || !fileInput) return;

                // کلیک روی منطقه آپلود
                uploadArea.addEventListener('click', (e) => {
                    if (e.target.id !== 'fileInput') {
                        fileInput.click();
                    }
                });

                // انتخاب فایل
                fileInput.addEventListener('change', (e) => {
                    handleFiles(e.target.files);
                });

                // Drag & Drop
                uploadArea.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    uploadArea.classList.add('dragover');
                });

                uploadArea.addEventListener('dragleave', () => {
                    uploadArea.classList.remove('dragover');
                });

                uploadArea.addEventListener('drop', (e) => {
                    e.preventDefault();
                    uploadArea.classList.remove('dragover');
                    handleFiles(e.dataTransfer.files);
                });
                // نمایش منطقه آپلود فقط برای مسئول انجام
                if (uploadArea) {
                    uploadArea.style.display = isAssignee ? 'block' : 'none';
                }
            }

            // مدیریت فایل‌های انتخاب شده
            async function handleFiles(files) {
                if (!files || files.length === 0) return;

                const maxSize = 20 * 1024 * 1024; // 20MB
                const allowedTypes = ['image/jpeg', 'image/png', 'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'audio/mpeg', 'audio/mp4', 'audio/ogg', 'audio/x-m4a'
                ];

                for (let file of files) {
                    // چک حجم
                    if (file.size > maxSize) {
                        showAlert(`فایل "${file.name}" بزرگتر از 20 مگابایت است`, 'warning');
                        continue;
                    }

                    // چک نوع
                    if (!allowedTypes.includes(file.type)) {
                        showAlert(`فرمت فایل "${file.name}" مجاز نیست`, 'warning');
                        continue;
                    }

                    // آپلود
                    await uploadFile(file);
                }
            }

            // آپلود فایل
            // ⚠️ قبلاً این تابع async بود ولی درونش فقط XMLHttpRequest با addEventListener
            // صدا می‌زد، بدون اینکه یک Promise واقعی به اتمامِ آپلود گره بخوره — یعنی
            // await uploadFile(file) عملاً تقریباً فوری resolve می‌شد، نه بعدِ تمومِ آپلودِ
            // واقعی. جایی مثلِ submitAddDiscWithFiles() که بعدِ حلقهٔ await، با فاصلهٔ کوتاه
            // (۱.۵ ثانیه) صفحه رو reload می‌کرد، اگه آپلودِ واقعی (شبکه/سرور) از اون فاصله
            // بیشتر طول می‌کشید، reload درخواستِ نیمه‌تمام رو قطع می‌کرد و فایل هیچ‌وقت
            // واقعاً ذخیره نمی‌شد — با اینکه تویِ UI انتخاب‌شده به‌نظر می‌رسید
            function uploadFile(file) {
                return new Promise((resolve, reject) => {
                    const formData = new FormData();
                    formData.append('file', file);
                    formData.append('task_id', taskId);

                    const progressBar = document.getElementById('uploadProgressBar');
                    const progressContainer = document.getElementById('uploadProgress');

                    progressContainer.style.display = 'block';
                    progressBar.style.width = '0%';

                    try {
                        const xhr = new XMLHttpRequest();

                        // پیشرفت آپلود
                        xhr.upload.addEventListener('progress', (e) => {
                            if (e.lengthComputable) {
                                const percent = (e.loaded / e.total) * 100;
                                progressBar.style.width = percent + '%';
                            }
                        });

                        xhr.addEventListener('load', () => {
                            progressContainer.style.display = 'none';

                            if (xhr.status === 200) {
                                const data = JSON.parse(xhr.responseText);
                                if (data.success) {
                                    showAlert('فایل با موفقیت آپلود شد', 'success');
                                    loadAttachments(); // بارگذاری مجدد لیست
                                    // باز کردن آکاردئون پیوست‌ها بعد از آپلود
                                    const attachmentsBody = document.getElementById('attachmentsBody');
                                    const attachmentsChevron = document.getElementById('attachmentsChevron');
                                    if (attachmentsBody) {
                                        attachmentsBody.style.display = 'block';
                                    }
                                    if (attachmentsChevron) {
                                        attachmentsChevron.style.transform = 'rotate(180deg)';
                                    }
                                    document.getElementById('fileInput').value = ''; // پاک کردن input
                                    resolve(data);
                                } else {
                                    showAlert(data.message || 'خطا در آپلود فایل', 'danger');
                                    reject(new Error(data.message || 'خطا در آپلود فایل'));
                                }
                            } else {
                                showAlert('خطا در آپلود فایل', 'danger');
                                reject(new Error('خطا در آپلود فایل'));
                            }
                        });

                        xhr.addEventListener('error', () => {
                            progressContainer.style.display = 'none';
                            showAlert('خطا در ارتباط با سرور', 'danger');
                            reject(new Error('خطا در ارتباط با سرور'));
                        });

                        xhr.open('POST', '../api/tasks/upload-attachment.php');
                        xhr.setRequestHeader('Authorization', 'Bearer ' + authToken);
                        xhr.send(formData);

                    } catch (error) {
                        progressContainer.style.display = 'none';
                        console.error('Upload error:', error);
                        showAlert('خطا در آپلود فایل', 'danger');
                        reject(error);
                    }
                });
            }

            // دانلود فایل
            function downloadAttachment(filePath, fileName) {
                const link = document.createElement('a');
                link.href = filePath;
                link.download = fileName;
                link.target = '_blank';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }

            // حذف فایل
            function deleteAttachment(attachmentId) {
                showToast('آیا مطمئن هستید که می‌خواهید این فایل را حذف کنید؟', 'warning', {
                    duration: 1500000,
                    buttons: [{
                            label: 'بله، حذف',
                            style: 'primary',
                            onClick: async function() {
                                try {
                                    const response = await fetch('../api/tasks/delete-attachment.php', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'Authorization': 'Bearer ' + authToken
                                        },
                                        body: JSON.stringify({
                                            attachment_id: attachmentId
                                        })
                                    });

                                    const data = await response.json();

                                    if (data.success) {
                                        showAlert('فایل با موفقیت حذف شد', 'success');
                                        loadAttachments();
                                    } else {
                                        showAlert(data.message || 'خطا در حذف فایل', 'danger');
                                    }
                                } catch (error) {
                                    console.error('Delete error:', error);
                                    showAlert('خطا در حذف فایل', 'danger');
                                }
                            }
                        },
                        {
                            label: 'خیر',
                            style: 'ghost',
                            onClick: function() {
                                return;
                            }
                        }
                    ]
                });
            }

            // تابع showAlert (اگر قبلاً وجود نداره)
            function showAlert(message, type = 'info') {
                const map = {
                    danger: 'warning',
                    error: 'warning'
                };
                showToast(message, map[type] || type);
            }

            // ============== مدیریت آپلود در Modal ==============

            let selectedFilesForModal = [];

            // تنظیم event listeners برای modal
            function setupModalUploadListeners() {
                const uploadAreaModal = document.getElementById('uploadAreaModal');
                const fileInputModal = document.getElementById('fileInputModal');

                if (!uploadAreaModal || !fileInputModal) return;

                // کلیک روی منطقه آپلود
                uploadAreaModal.addEventListener('click', (e) => {
                    if (e.target.id !== 'fileInputModal') {
                        fileInputModal.click();
                    }
                });

                // انتخاب فایل
                fileInputModal.addEventListener('change', (e) => {
                    addFilesToModal(e.target.files);
                });

                // Drag & Drop
                uploadAreaModal.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    uploadAreaModal.style.borderColor = '#744ca4';
                });

                uploadAreaModal.addEventListener('dragleave', () => {
                    uploadAreaModal.style.borderColor = 'rgba(116, 76, 164, 0.3)';
                });

                uploadAreaModal.addEventListener('drop', (e) => {
                    e.preventDefault();
                    uploadAreaModal.style.borderColor = 'rgba(116, 76, 164, 0.3)';
                    addFilesToModal(e.dataTransfer.files);
                });
            }

            // اضافه کردن فایل‌ها به لیست انتخاب شده
            function addFilesToModal(files) {
                const maxSize = 20 * 1024 * 1024; // 20MB
                const allowedTypes = ['image/jpeg', 'image/png', 'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'audio/mpeg', 'audio/mp4', 'audio/ogg', 'audio/x-m4a'
                ];

                for (let file of files) {
                    // چک حجم
                    if (file.size > maxSize) {
                        showAlert(`فایل "${file.name}" بزرگتر از 20 مگابایت است`, 'warning');
                        continue;
                    }

                    // چک نوع
                    if (!allowedTypes.includes(file.type)) {
                        showAlert(`فرمت فایل "${file.name}" مجاز نیست`, 'warning');
                        continue;
                    }

                    selectedFilesForModal.push(file);
                }

                displaySelectedFilesInModal();
            }

            // نمایش فایل‌های انتخاب شده در modal
            function displaySelectedFilesInModal() {
                const container = document.getElementById('selectedFilesModal');

                if (selectedFilesForModal.length === 0) {
                    container.innerHTML = '';
                    return;
                }

                let html = '';
                selectedFilesForModal.forEach((file, index) => {
                    const fileSize = formatBytes(file.size);
                    const icon = getFileIconByType(file.type);

                    html += `
            <div class="selected-file-item">
                <div class="selected-file-info">
                    <i class="bi ${icon}"></i>
                    <span class="selected-file-name" title="${file.name}">${file.name}</span>
<small>فرمت‌های مجاز: jpg, png, pdf, docx, xlsx, mp3, m4a, ogg (حداکثر 20MB)</small>
                <button type="button" class="btn-remove-file" onclick="removeFileFromModal(${index})">
                    <i class="bi bi-x-circle"></i>
                </button>
            </div>
        `;
                });

                container.innerHTML = html;
            }

            // حذف فایل از لیست
            function removeFileFromModal(index) {
                selectedFilesForModal.splice(index, 1);
                displaySelectedFilesInModal();
            }

            // تبدیل بایت به واحد قابل خواندن
            function formatBytes(bytes) {
                if (bytes === 0) return '0 Bytes';
                const k = 1024;
                const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
            }

            // آیکون بر اساس mime type
            function getFileIconByType(mimeType) {
                if (mimeType.startsWith('image/')) return 'bi-file-earmark-image';
                if (mimeType.startsWith('audio/')) return 'bi-file-earmark-music';
                if (mimeType.includes('pdf')) return 'bi-file-earmark-pdf';
                if (mimeType.includes('word') || mimeType.includes('document')) return 'bi-file-earmark-word';
                if (mimeType.includes('excel') || mimeType.includes('spreadsheet')) return 'bi-file-earmark-excel';
                return 'bi-file-earmark';
            }

            // ارسال توضیحات همراه با فایل‌ها
            async function submitAddDiscWithFiles() {
                const notes = document.getElementById('addDiscNotes').value.trim();

                if (!notes) {
                    showAlert('لطفاً توضیحات را وارد کنید', 'warning');
                    return;
                }

                try {
                    // ابتدا توضیحات را ثبت کنیم
                    currentUser = JSON.parse(localStorage.getItem('user_info'));
                    const fullNotes = currentUser.first_name + ' ' + currentUser.last_name + ": " + notes;

                    const response = await fetch('../api/tasks/update-status.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': 'Bearer ' + authToken
                        },
                        body: JSON.stringify({
                            task_id: taskId,
                            status: taskData.status,
                            notes: fullNotes
                        })
                    });

                    const data = await response.json();

                    if (!data.success) {
                        showAlert(data.message || 'خطا در درج توضیحات', 'danger');
                        return;
                    }

                    // حالا فایل‌ها را آپلود کنیم
                    if (selectedFilesForModal.length > 0) {
                        for (let file of selectedFilesForModal) {
                            await uploadFile(file);
                        }
                    }

                    // پاک کردن و بستن modal
                    document.getElementById('addDiscNotes').value = '';
                    selectedFilesForModal = [];
                    displaySelectedFilesInModal();

                    const modal = bootstrap.Modal.getInstance(document.getElementById('addDiscModal'));
                    if (modal) modal.hide();

                    showAlert('توضیحات با موفقیت ثبت شد', 'success');

                    // رفرش صفحه
                    setTimeout(() => {
                        location.reload();
                    }, 1500);

                } catch (error) {
                    console.error('Error:', error);
                    showAlert('خطا در درج توضیح', 'danger');
                }
            }

            function canRequestTermination(task) {
                if (task.task_type !== 'continuous') return false;
                if (!isAssignee) return false;
                if (!['not_started', 'in_progress'].includes(task.status)) return false;

                // بررسی: کار مستقیماً از تعریف‌کننده دریافت شده (نه از طریق ارجاع)
                const receivedViaDelegation = taskHistory.some(h =>
                    h.action === 'delegated' &&
                    parseInt(h.to_user_id) === currentUser.id
                );
                return !receivedViaDelegation;
            }

            // ─── بارگذاری درخواست اتمام در حال انتظار ────────────────────
            async function loadTerminationRequest() {
                if (!taskId) return;
                try {
                    const response = await fetch(`../api/tasks/get-termination-request.php?task_id=${taskId}`, {
                        headers: {
                            'Authorization': 'Bearer ' + authToken
                        }
                    });
                    const data = await response.json();

                    if (!data.success) return;

                    const request = data.request;

                    // اگر assignee هستیم و درخواست دادیم → مخفی کردن دکمه
                    if (isAssignee && request) {
                        const btn = document.getElementById('terminationRequestBtn');
                        if (btn) btn.style.display = 'none';
                    }

                    // اگر creator هستیم و درخواست در انتظار داریم → نمایش toast
                    if (isCreator && request) {
                        currentTerminationRequestId = request.id;

                        showToast(
                            `درخواست اتمام کار از ${request.requester_name} دریافت شد`,
                            'info', {
                                duration: 150000,
                                buttons: [{
                                        label: 'مشاهده درخواست',
                                        style: 'primary',
                                        onClick: () => showTerminationReviewModal(request)
                                    },
                                    {
                                        label: 'بعداً',
                                        style: 'ghost'
                                    }
                                ]
                            }
                        );
                    }
                } catch (err) {
                    console.error('loadTerminationRequest error:', err);
                }
            }

            // ─── نمایش modal درخواست (assignee) ──────────────────────────
            function showTerminationRequestModal() {
                document.getElementById('terminationReason').value = '';
                new bootstrap.Modal(document.getElementById('terminationRequestModal')).show();
            }

            // ─── ارسال درخواست اتمام ─────────────────────────────────────
            async function submitTerminationRequest() {
                const reason = document.getElementById('terminationReason').value.trim();
                if (!reason) {
                    showToast('لطفاً دلیل درخواست را وارد کنید', 'info');
                    return;
                }

                try {
                    const response = await fetch('../api/tasks/request-termination.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': 'Bearer ' + authToken
                        },
                        body: JSON.stringify({
                            task_id: taskId,
                            reason
                        })
                    });
                    const data = await response.json();

                    if (data.success) {
                        bootstrap.Modal.getInstance(document.getElementById('terminationRequestModal')).hide();
                        showToast(data.message, 'success');

                        // مخفی کردن دکمه بعد از ارسال
                        const btn = document.getElementById('terminationRequestBtn');
                        if (btn) btn.style.display = 'none';

                        setTimeout(() => location.reload(), 1200);
                    } else {
                        showToast(data.message || 'خطا در ارسال درخواست', 'warning');
                    }
                } catch (err) {
                    showToast('خطا در ارتباط با سرور', 'warning');
                }
            }

            // ─── نمایش modal بررسی (creator) ──────────────────────────────
            function showTerminationReviewModal(request) {
                currentTerminationRequestId = request.id;

                document.getElementById('terminationRequesterName').textContent = request.requester_name || '-';
                document.getElementById('terminationReasonDisplay').textContent = request.reason || 'دلیلی ذکر نشده';

                // reset حالت رد
                document.getElementById('terminationRejectionReasonWrapper').style.display = 'none';
                document.getElementById('terminationRejectionReason').value = '';
                document.getElementById('terminationRejectToggleBtn').classList.remove('d-none');
                document.getElementById('terminationRejectConfirmBtn').classList.add('d-none');

                new bootstrap.Modal(document.getElementById('terminationReviewModal')).show();
            }

            // ─── toggle بخش دلیل رد ───────────────────────────────────────
            function toggleTerminationRejectReason() {
                document.getElementById('terminationRejectionReasonWrapper').style.display = 'block';
                document.getElementById('terminationRejectToggleBtn').classList.add('d-none');
                document.getElementById('terminationRejectConfirmBtn').classList.remove('d-none');
                document.getElementById('terminationApproveBtn').style.display = 'none';
            }

            // ─── تأیید اتمام (creator) ────────────────────────────────────
            async function approveTerminationRequest() {
                if (!currentTerminationRequestId) return;

                try {
                    const response = await fetch('../api/tasks/review-termination.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': 'Bearer ' + authToken
                        },
                        body: JSON.stringify({
                            request_id: currentTerminationRequestId,
                            action: 'approve'
                        })
                    });
                    const data = await response.json();

                    if (data.success) {
                        bootstrap.Modal.getInstance(document.getElementById('terminationReviewModal')).hide();
                        showToast(data.message, 'success');
                        setTimeout(() => location.reload(), 1200);
                    } else {
                        showToast(data.message || 'خطا در تأیید', 'warning');
                    }
                } catch (err) {
                    showToast('خطا در ارتباط با سرور', 'warning');
                }
            }
            // ============ رفع دوره‌های معوقه (Overdue Clear) ============
            function showClearOverdueModal() {
                const old = document.getElementById('clearOverdueModalWrap');
                if (old) old.remove();
                const wrap = document.createElement('div');
                wrap.id = 'clearOverdueModalWrap';
                wrap.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99999;display:flex;align-items:center;justify-content:center;';
                wrap.innerHTML = `
                    <div style="background:var(--surface);border-radius:12px;max-width:420px;width:92%;padding:20px;direction:rtl;">
                        <h5 style="margin-bottom:12px;">درخواست رفع دوره‌های معوقه</h5>
                        <p style="font-size:.9rem;color:var(--text-muted);">پس از تأیید تعریف‌کنندهٔ کار، همهٔ دوره‌های معوقهٔ فعلی برداشته می‌شوند.</p>
                        <textarea id="clearOverdueReason" rows="3" style="width:100%;border:1px solid var(--border-soft);border-radius:8px;padding:8px;" placeholder="دلیل (اختیاری)"></textarea>
                        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:14px;">
                            <button class="btn btn-secondary" onclick="document.getElementById('clearOverdueModalWrap').remove()">انصراف</button>
                            <button class="btn btn-warning" id="clearOverdueConfirmBtn" onclick="submitClearOverdue()">ثبت درخواست</button>
                        </div>
                    </div>`;
                document.body.appendChild(wrap);
            }

            function submitClearOverdue() {
                const btn = document.getElementById('clearOverdueConfirmBtn');
                if (btn) {
                    btn.disabled = true;
                    btn.textContent = 'در حال ارسال...';
                }
                const reason = (document.getElementById('clearOverdueReason') || {}).value || '';
                fetch('../api/tasks/request-overdue-clear.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': 'Bearer ' + authToken
                        },
                        body: JSON.stringify({
                            task_id: taskId,
                            reason: reason
                        })
                    })
                    .then(r => r.json())
                    .then(data => {
                        const w = document.getElementById('clearOverdueModalWrap');
                        if (w) w.remove();
                        showToast(data.message || (data.success ? 'انجام شد' : 'خطا'), data.success ? 'success' : 'error');
                        if (data.success) setTimeout(() => location.reload(), 1200);
                    })
                    .catch(() => showToast('خطا در ارتباط با سرور', 'error'));
            }

            function loadPendingOverdueClearRequests() {
                fetch(`../api/tasks/get-overdue-clear-requests.php?task_id=${taskId}`, {
                        headers: {
                            'Authorization': 'Bearer ' + authToken
                        }
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (!(data.success && data.requests && data.requests.length > 0)) return;
                        const request = data.requests[0];
                        const currentUser = JSON.parse(localStorage.getItem('user_info'));
                        const uid = currentUser ? currentUser.id : null;
                        if (request.current_approver_id != uid) return; // فقط تأییدکننده
                        showToast('یک درخواست رفع دوره‌های معوقه در انتظار بررسی شماست', 'info', {
                            duration: 150000,
                            buttons: [{
                                    label: 'مشاهده درخواست',
                                    style: 'primary',
                                    onClick: function() {
                                        showOverdueClearReviewModal(request);
                                    }
                                },
                                {
                                    label: 'بعداً',
                                    style: 'ghost'
                                }
                            ]
                        });
                    })
                    .catch(() => {});
            }

            function showOverdueClearReviewModal(request) {
                const old = document.getElementById('ocReviewWrap');
                if (old) old.remove();
                const wrap = document.createElement('div');
                wrap.id = 'ocReviewWrap';
                wrap.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99999;display:flex;align-items:center;justify-content:center;';
                wrap.innerHTML = `
                    <div style="background:var(--surface);border-radius:12px;max-width:440px;width:92%;padding:20px;direction:rtl;">
                        <h5 style="margin-bottom:12px;">بررسی درخواست رفع دوره‌های معوقه</h5>
                        <p style="margin:6px 0;"><strong>درخواست‌دهنده:</strong> ${request.requester_name || '-'}</p>
                        <p style="margin:6px 0;"><strong>تعداد دورهٔ معوقه:</strong> ${request.periods_count || 0}</p>
                        ${request.reason ? `<p style="margin:6px 0;"><strong>دلیل:</strong> ${request.reason}</p>` : ''}
                        <textarea id="ocRejectReason" rows="2" style="width:100%;border:1px solid var(--border-soft);border-radius:8px;padding:8px;margin-top:8px;" placeholder="دلیل رد (در صورت رد)"></textarea>
                        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:14px;">
                            <button class="btn btn-secondary" onclick="document.getElementById('ocReviewWrap').remove()">بستن</button>
                            <button class="btn btn-danger" onclick="rejectOverdueClear(${request.id})">رد</button>
                            <button class="btn btn-success" onclick="approveOverdueClear(${request.id})">تأیید</button>
                        </div>
                    </div>`;
                document.body.appendChild(wrap);
            }

            function approveOverdueClear(requestId) {
                fetch('../api/tasks/approve-overdue-clear.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': 'Bearer ' + authToken
                        },
                        body: JSON.stringify({
                            request_id: requestId
                        })
                    })
                    .then(r => r.json())
                    .then(data => {
                        const w = document.getElementById('ocReviewWrap');
                        if (w) w.remove();
                        showToast(data.message, data.success ? 'success' : 'error');
                        if (data.success) setTimeout(() => location.reload(), 1200);
                    })
                    .catch(() => showToast('خطا در ارتباط با سرور', 'error'));
            }

            function rejectOverdueClear(requestId) {
                const reason = (document.getElementById('ocRejectReason') || {}).value || '';
                fetch('../api/tasks/reject-overdue-clear.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': 'Bearer ' + authToken
                        },
                        body: JSON.stringify({
                            request_id: requestId,
                            rejection_reason: reason
                        })
                    })
                    .then(r => r.json())
                    .then(data => {
                        const w = document.getElementById('ocReviewWrap');
                        if (w) w.remove();
                        showToast(data.message, data.success ? 'success' : 'error');
                        if (data.success) setTimeout(() => location.reload(), 1200);
                    })
                    .catch(() => showToast('خطا در ارتباط با سرور', 'error'));
            }
            // ─── رد درخواست اتمام (creator) ──────────────────────────────
            async function rejectTerminationRequest() {
                if (!currentTerminationRequestId) return;

                const reason = document.getElementById('terminationRejectionReason').value.trim();
                if (!reason) {
                    showToast('لطفاً دلیل رد را وارد کنید', 'info');
                    return;
                }

                try {
                    const response = await fetch('../api/tasks/review-termination.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': 'Bearer ' + authToken
                        },
                        body: JSON.stringify({
                            request_id: currentTerminationRequestId,
                            action: 'reject',
                            rejection_reason: reason
                        })
                    });
                    const data = await response.json();

                    if (data.success) {
                        bootstrap.Modal.getInstance(document.getElementById('terminationReviewModal')).hide();
                        showToast(data.message, 'success');
                        setTimeout(() => location.reload(), 1200);
                    } else {
                        showToast(data.message || 'خطا در رد درخواست', 'warning');
                    }
                } catch (err) {
                    showToast('خطا در ارتباط با سرور', 'warning');
                }
            }
            async function showTerminatePeriodConfirm() {
                showToast('آیا مطمئن هستید که می‌خواهید این کار را اتمام دهید؟', 'warning', {
                    duration: 1500000,
                    buttons: [{
                            label: 'بله، اتمام داده شود',
                            style: 'primary',
                            onClick: async function() {
                                try {
                                    const response = await fetch('../api/tasks/terminate-period.php', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'Authorization': 'Bearer ' + authToken
                                        },
                                        body: JSON.stringify({
                                            task_id: taskId
                                        })
                                    });
                                    const data = await response.json();
                                    if (data.success) {
                                        showToast(data.message, 'success');
                                        setTimeout(() => location.reload(), 1200);
                                    } else {
                                        showToast(data.message || 'خطا در اتمام کار', 'warning');
                                    }
                                } catch (error) {
                                    showToast('خطا در ارتباط با سرور', 'warning');
                                }
                            }
                        },
                        {
                            label: 'خیر، منصرف شدم',
                            style: 'ghost',
                            onClick: function() {
                                return;
                            }
                        }
                    ]
                });
            }

            let renewalMode = 'request'; // یا 'apply'
            let currentRenewalRequest = null;

            function openRenewalModal(mode) {
                renewalMode = mode;
                document.getElementById('renewalModalTitle').textContent =
                    mode === 'apply' ? 'تمدید دوره' : 'درخواست تمدید دوره';
                document.getElementById('renewalSubmitBtn').textContent =
                    mode === 'apply' ? 'اعمال تمدید' : 'ارسالِ درخواست';
                document.getElementById('renewalReasonRequiredHint').textContent =
                    mode === 'apply' ? 'اختیاری' : 'اجباری';
                document.getElementById('renewalPeriodLabel').textContent = getPeriodLabel(taskData.period_type);
                document.getElementById('renewalNewStartDate').value = '';
                document.getElementById('renewalNewStartDate').removeAttribute('data-date');
                document.getElementById('renewalNewEndDate').value = '';
                document.getElementById('renewalNewEndDate').removeAttribute('data-date');
                document.getElementById('renewalReason').value = '';
                document.getElementById('renewalNextPeriodPreview').textContent = '-';

                document.getElementById('renewalModal').style.display = 'block';

                setTimeout(() => {
                    initPersianDatepickerForModal('renewalNewStartDate', null);
                    initPersianDatepickerForModal('renewalNewEndDate', null);

                    const startInput = document.getElementById('renewalNewStartDate');
                    const obs = new MutationObserver(() => updateRenewalPreview());
                    obs.observe(startInput, {
                        attributes: true,
                        attributeFilter: ['data-date']
                    });
                }, 150);
            }

            function updateRenewalPreview() {
                const startVal = document.getElementById('renewalNewStartDate').getAttribute('data-date');
                if (!startVal) {
                    document.getElementById('renewalNextPeriodPreview').textContent = '-';
                    return;
                }
                document.getElementById('renewalNextPeriodPreview').textContent = formatPersianDate(startVal);
            }
            // next ? formatPersianDate(next) : '-';

            function submitRenewal() {
                const startVal = document.getElementById('renewalNewStartDate').getAttribute('data-date');
                const endVal = document.getElementById('renewalNewEndDate').getAttribute('data-date');
                const reason = document.getElementById('renewalReason').value.trim();

                if (!startVal) {
                    const t = showToast('تاریخ شروع مجدد الزامی است', 'warning');
                    return;
                }
                if (renewalMode === 'request' && !reason) {
                    const t = showToast('دلیل درخواست الزامی است', 'warning');
                    return;
                }

                const url = renewalMode === 'apply' ?
                    '../api/tasks/apply-renewal.php' :
                    '../api/tasks/request-renewal.php';

                fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': 'Bearer ' + authToken
                        },
                        body: JSON.stringify({
                            task_id: taskData.id,
                            new_start_date: startVal,
                            new_end_date: endVal || null,
                            reason: reason
                        })
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            const t = showToast(data.message || 'انجام شد', 'success');
                            closeModal('renewalModal');
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            const t = showToast(data.message || 'خطا', 'warning');
                        }
                    })
                    .catch(() => {
                        const t = showToast('خطا در ارتباط با سرور', 'warning');
                    });
            }

            // بررسی اینکه آیا درخواستِ تمدیدِ دورهٔ در‌جریان مربوط به کاربر جاری است
            function fetchPendingRenewalRequest(taskId) {
                fetch('../api/tasks/get-pending-renewal.php?task_id=' + taskId, {
                        headers: {
                            'Authorization': 'Bearer ' + authToken
                        }
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (!data.success || !data.request) return;
                        currentRenewalRequest = data.request;

                        const pendingBadge = document.getElementById('renewalPendingBadge');
                        const reviewBtn = document.getElementById('reviewRenewalBtn');

                        if (currentUser && currentUser.id == data.request.current_approver_id) {
                            reviewBtn.style.display = 'inline-block';
                            reviewBtn.onclick = () => openReviewRenewalModal(data.request);
                        } else {
                            pendingBadge.style.display = 'inline-block';
                        }
                    })
                    .catch(() => {});
            }

            function openReviewRenewalModal(req) {
                document.getElementById('renewalRequesterName').textContent = req.requester_name || '-';
                document.getElementById('renewalReqStartDisplay').textContent = formatPersianDate(req.new_start_date);
                document.getElementById('renewalReqEndDisplay').textContent = req.new_end_date ? formatPersianDate(req.new_end_date) : 'نامحدود';
                document.getElementById('renewalReasonDisplay').textContent = req.reason || '-';
                document.getElementById('reviewRenewalModal').style.display = 'block';
            }

            function approveRenewalRequest() {
                if (!currentRenewalRequest) return;

                showToast('آیا می‌خواهید تأیید کنید؟', 'warning', {
                    duration: 1500000,
                    buttons: [{
                            label: 'بله، تأیید',
                            style: 'primary',
                            onClick: function() {
                                fetch('../api/tasks/approve-renewal.php', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'Authorization': 'Bearer ' + authToken
                                        },
                                        body: JSON.stringify({
                                            request_id: currentRenewalRequest.id
                                        })
                                    })
                                    .then(r => r.json())
                                    .then(data => {
                                        if (data.success) {
                                            showToast(data.message || 'تأیید شد', 'success');
                                            closeModal('reviewRenewalModal');
                                            setTimeout(() => location.reload(), 1000);
                                        } else {
                                            showToast(data.message || 'خطا', 'warning');
                                        }
                                    })
                                    .catch(() => {
                                        showToast('خطا در ارتباط با سرور', 'warning');
                                    });
                            }
                        },
                        {
                            label: 'خیر',
                            style: 'ghost',
                            onClick: function() {
                                return;
                            }
                        }
                    ]
                });
            }

            function showRejectRenewalReason() {
                closeModal('reviewRenewalModal');
                document.getElementById('rejectionReasonInput').value = '';
                const rejectModal = document.getElementById('rejectReasonModal');
                rejectModal.style.display = 'block';

                const confirmBtn = document.getElementById('confirmRejectBtn');
                confirmBtn.onclick = function() {
                    const reason = document.getElementById('rejectionReasonInput').value.trim();
                    if (!reason) {
                        const t = showToast('لطفاً دلیل را وارد کنید', 'warning');
                        return;
                    }
                    fetch('../api/tasks/reject-renewal.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Authorization': 'Bearer ' + authToken
                            },
                            body: JSON.stringify({
                                request_id: currentRenewalRequest.id,
                                rejection_reason: reason
                            })
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                const t = showToast('رد درخواست با موفقیت انجام شد', 'info');
                                closeModal('rejectReasonModal');
                                setTimeout(() => location.reload(), 1000);
                            } else {
                                const t = showToast(data.message || 'خطا', 'warning');
                            }
                        })
                        .catch(() => {
                            const t = showToast('خطا در ارتباط با سرور', 'warning');
                        });
                };
            }
        </script>
        <script src="<?= asset('../assets/js/task-groups.js') ?>"></script>
        <script src="<?= asset('../assets/js/cdn/intro.min.js') ?>"></script>
        <!--<script src="<?= asset('../assets/js/deadline-toast.js') ?>"></script>-->

        <!-- مودال ویرایش آیتم چک‌لیست -->
        <div class="modal fade" id="editChecklistModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-pencil-square ms-2"></i>ویرایش آیتم</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="editChecklistItemId">
                        <div class="mb-3">
                            <label class="form-label">عنوان آیتم</label>
                            <input type="text" class="form-control" id="editChecklistTitle" placeholder="عنوان آیتم...">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">توضیحات (اختیاری)</label>
                            <textarea class="form-control" id="editChecklistDesc" rows="3" placeholder="توضیحات آیتم..."></textarea>
                        </div>
                        <div class="mb-2" id="editChecklistAssigneeWrap">
                            <label class="form-label">ارجاع به</label>
                            <div id="editChecklistAssigneePicker"></div>
                            <small class="text-muted">می‌توانید این آیتم را به یک کاربر یا واحد ارجاع دهید (اختیاری).</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="button" class="btn btn-primary" onclick="submitEditChecklist()">
                            <i class="bi bi-check-circle ms-2"></i>ذخیره
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php include 'footer.php'; ?>
</body>

</html>