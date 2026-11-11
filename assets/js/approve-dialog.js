// /assets/js/approve-dialog.js — مودال مشترک «تأیید کار» (داشبورد مدیر و کاربر)
//
// همان گزینه‌های مودال تأیید صفحهٔ جزئیات کار (pages/task-detail.php):
//   • تأیید                → api/tasks/approve.php
//   • تأیید و نگهداری کار  → api/tasks/approve-and-keep.php   (فقط کار مقطعی غیرروتین)
//   • تأیید و ارجاع        → api/tasks/approve-and-delegate.php (فقط کار غیرروتین)
// شرط نمایش هم‌راستا با task-detail.php؛ تصمیم نهایی و دسترسی همیشه با سرور است.
//
// استفاده:
//   ApproveDialog.open(task, {
//       toast(msg, type),                 // type: 'ok' | 'err'
//       loadUsers(): Promise<users[]>,    // فهرست کاربران برای ارجاع (AssigneePicker)
//       sections, sectionMap,             // اختیاری — برای AssigneePicker
//       onDone(kind)                      // بعد از موفقیت: 'approve' | 'keep' | 'delegate'
//   });
//
// 🔒 طبق قاعدهٔ فایل‌های JS مشترک: هیچ let/const سطح‌بالا — همه داخل IIFE.
(function () {
    var modalEl = null, modal = null, current = null, picker = null, busy = false;

    function isWorkflow(t) { return !!t && Number(t.is_workflow_task) === 1; }
    function canDelegate(t) { return !isWorkflow(t); }
    // periodic = کار مقطعی؛ continuous (دوره‌ای) عمدا «تأیید و نگهداری» ندارد
    function canKeep(t) { return !isWorkflow(t) && t.task_type === 'periodic'; }

    function ensureModal() {
        if (modalEl) return;
        modalEl = document.createElement('div');
        modalEl.className = 'modal fade';
        modalEl.id = 'approveDialogModal';
        modalEl.tabIndex = -1;
        modalEl.innerHTML = `
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content" style="border:none;border-radius:16px;overflow:visible;">
                    <div class="modal-header" style="padding:14px 18px;">
                        <h6 class="modal-title" style="display:flex;align-items:center;gap:9px;font-weight:700;margin:0;">
                            <i class="bi bi-check-circle" style="color:#16a34a;"></i>
                            <span id="adTitle">تأیید کار</span>
                        </h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
                    </div>
                    <div class="modal-body" style="padding:14px 18px;overflow:visible;">
                        <div id="adTaskTitle" class="text-muted" style="font-size:.82rem;margin-bottom:10px;"></div>

                        <!-- مرحلهٔ ۱: تأیید -->
                        <div id="adStepApprove">
                            <label class="form-label" for="adNotes" style="font-size:.85rem;">توضیحات تأیید (اختیاری)</label>
                            <textarea class="form-control" id="adNotes" rows="3" placeholder="توضیحات خود را وارد کنید..."></textarea>
                        </div>

                        <!-- مرحلهٔ ۲: ارجاع (بعد از «تأیید و ارجاع») -->
                        <div id="adStepDelegate" style="display:none;">
                            <label class="form-label" style="font-size:.85rem;">ارجاع به</label>
                            <div id="adAssigneePicker" style="margin-bottom:10px;"></div>
                            <label class="form-label" for="adDelegateNotes" style="font-size:.85rem;">توضیحات ارجاع (اختیاری)</label>
                            <textarea class="form-control" id="adDelegateNotes" rows="2" placeholder="توضیحات برای نفر بعدی..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer" style="padding:10px 18px;gap:6px;flex-wrap:wrap;">
                        <div id="adFooterApprove" style="display:flex;gap:6px;flex-wrap:wrap;width:100%;justify-content:flex-end;">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">انصراف</button>
                            <button type="button" class="btn btn-warning btn-sm" id="adDelegateBtn">
                                <i class="bi bi-arrow-right-circle me-1"></i>تأیید و ارجاع
                            </button>
                            <button type="button" class="btn btn-outline-primary btn-sm" id="adKeepBtn">
                                <i class="bi bi-bookmark-check me-1"></i>تأیید و نگهداری کار
                            </button>
                            <button type="button" class="btn btn-success btn-sm" id="adApproveBtn">
                                <i class="bi bi-check-circle me-1"></i>تأیید
                            </button>
                        </div>
                        <div id="adFooterDelegate" style="display:none;gap:6px;flex-wrap:wrap;width:100%;justify-content:flex-end;">
                            <button type="button" class="btn btn-secondary btn-sm" id="adBackBtn">بازگشت</button>
                            <button type="button" class="btn btn-warning btn-sm" id="adDelegateSubmitBtn">
                                <i class="bi bi-arrow-right-circle me-1"></i>تأیید و ارجاع
                            </button>
                        </div>
                    </div>
                </div>
            </div>`;
        document.body.appendChild(modalEl);
        modal = new bootstrap.Modal(modalEl);

        modalEl.addEventListener('shown.bs.modal', function () {
            var n = document.getElementById('adNotes');
            if (n) n.focus();
        });
        document.getElementById('adApproveBtn').addEventListener('click', function () { submit('approve', this); });
        document.getElementById('adKeepBtn').addEventListener('click', function () { submit('keep', this); });
        document.getElementById('adDelegateBtn').addEventListener('click', showDelegateStep);
        document.getElementById('adBackBtn').addEventListener('click', function () { showStep('approve'); });
        document.getElementById('adDelegateSubmitBtn').addEventListener('click', function () { submit('delegate', this); });
    }

    function showStep(step) {
        var isApprove = step === 'approve';
        document.getElementById('adStepApprove').style.display = isApprove ? '' : 'none';
        document.getElementById('adStepDelegate').style.display = isApprove ? 'none' : '';
        document.getElementById('adFooterApprove').style.display = isApprove ? 'flex' : 'none';
        document.getElementById('adFooterDelegate').style.display = isApprove ? 'none' : 'flex';
        document.getElementById('adTitle').textContent = isApprove ? 'تأیید کار' : 'تأیید و ارجاع کار';
    }

    function toast(msg, type) {
        var o = current && current.opts;
        if (o && typeof o.toast === 'function') o.toast(msg, type);
        else if (typeof showToast === 'function') showToast(msg, type === 'err' ? 'warning' : 'success');
    }

    async function showDelegateStep() {
        showStep('delegate');
        var box = document.getElementById('adAssigneePicker');
        box.innerHTML = '<div class="text-muted" style="font-size:.8rem;">در حال بارگذاری کاربران...</div>';
        var users = [];
        try {
            users = (current.opts.loadUsers ? await current.opts.loadUsers() : []) || [];
        } catch (e) { users = []; }
        box.innerHTML = '';
        picker = AssigneePicker.create({
            container: '#adAssigneePicker',
            users: users,
            sections: current.opts.sections || [],
            sectionMap: current.opts.sectionMap || {},
            showSections: false, // فقط کاربر (نه واحد) برای ارجاع
            allowAll: false
        });
        if (picker && typeof picker.focus === 'function') setTimeout(function () { picker.focus(); }, 50);
    }

    async function submit(kind, btn) {
        if (busy || !current) return;
        var t = current.task;
        var notes = document.getElementById('adNotes').value.trim();
        var url, payload;

        if (kind === 'approve') {
            url = '../api/tasks/approve.php';
            payload = { task_id: t.id, approve: true, notes: notes };
        } else if (kind === 'keep') {
            url = '../api/tasks/approve-and-keep.php';
            payload = { task_id: t.id, notes: notes };
        } else {
            var sel = picker ? picker.getValue() : null;
            if (!sel || sel.type !== 'user' || !sel.value || sel.value === '__all_users__') {
                toast('لطفا کاربر مقصد را انتخاب کنید', 'err');
                return;
            }
            url = '../api/tasks/approve-and-delegate.php';
            payload = {
                task_id: t.id,
                to_user_id: Number(sel.value),
                approve_notes: notes,
                delegate_notes: document.getElementById('adDelegateNotes').value.trim()
            };
        }

        busy = true;
        var original = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        try {
            var res = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + authToken },
                body: JSON.stringify(payload)
            });
            var data = await res.json();
            if (!data.success) throw new Error(data.message || 'عملیات ناموفق بود');

            var msgs = {
                approve: 'کار تأیید شد',
                keep: 'کار تأیید شد و در لیست شما نگه‌داشته شد',
                delegate: 'کار تأیید و ارجاع شد'
            };
            toast(data.message || msgs[kind], 'ok');
            var done = current.opts.onDone;
            modal.hide();
            if (typeof done === 'function') done(kind);
        } catch (err) {
            toast(err.message || 'خطا در انجام عملیات', 'err');
        } finally {
            busy = false;
            btn.disabled = false;
            btn.innerHTML = original;
        }
    }

    window.ApproveDialog = {
        open: function (task, opts) {
            if (!task) return;
            ensureModal();
            current = { task: task, opts: opts || {} };
            picker = null;

            document.getElementById('adTaskTitle').textContent = task.title || '';
            document.getElementById('adNotes').value = '';
            document.getElementById('adDelegateNotes').value = '';
            document.getElementById('adAssigneePicker').innerHTML = '';
            document.getElementById('adDelegateBtn').style.display = canDelegate(task) ? '' : 'none';
            document.getElementById('adKeepBtn').style.display = canKeep(task) ? '' : 'none';
            showStep('approve');
            modal.show();
        }
    };
})();
