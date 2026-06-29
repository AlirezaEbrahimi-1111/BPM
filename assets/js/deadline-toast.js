/* ============================================================
   فایل: assets/js/deadline-toast.js
   هدف: نمایش toast اعلان درخواست تمدید موعد
   وابستگی‌ها (باید قبل از این فایل لود شده باشند):
     - jQuery
     - toastr.js
     - toastr.css
     - deadline-toast.css  (فایل CSS که جداگانه ساختیم)
   ============================================================ */

(function () {

    /* ────────────────────────────────────────────
       ۱. تنظیمات toastr
    ──────────────────────────────────────────── */
    var TOASTR_OPTIONS = {
        closeButton:        false,     // دکمه × خودمون رو داریم
        progressBar:        true,      // نوار شمارش معکوس
        positionClass:      'toast-bottom-left',
        rtl:                true,
        showMethod:         'fadeIn',
        hideMethod:         'fadeOut',
        showDuration:       350,
        hideDuration:       500,
        timeOut:            0,         // بی‌نهایت (تا کاربر عمل کند)
        extendedTimeOut:    0,
        tapToDismiss:       false,     // کلیک تصادفی آن را نبندد
        allowHtml:          true
    };

    /* ────────────────────────────────────────────
       ۲. متغیر نگهداری ID درخواست جاری
    ──────────────────────────────────────────── */
    var _activeRequestId  = null;
    var _activeToastEl    = null;     // ارجاع به DOM المان toast
    var _remindTimer      = null;     // تایمر "بعداً یادآوری کن"

    /* ────────────────────────────────────────────
       ۳. تابع اصلی: نمایش toast
          پارامترها:
            request = {
              id                   : شناسه درخواست در DB
              requester_name       : نام فرستنده (string)
              requested_new_deadline: تاریخ درخواستی (string, فرمت قابل تبدیل)
              reason               : دلیل (اختیاری)
            }
    ──────────────────────────────────────────── */
    window.showDeadlineExtensionToast = function (request) {

        // اگر قبلاً همین درخواست نمایش داده شده، دوباره نشان نده
        if (_activeRequestId === request.id) return;

        // بستن toast قبلی (اگر وجود دارد)
        if (_activeToastEl) {
            toastr.clear(_activeToastEl);
        }
        if (_remindTimer) {
            clearTimeout(_remindTimer);
        }

        _activeRequestId = request.id;

        // ─── ساخت تاریخ فارسی ───
        var persianDate = formatDeadlinePersian(request.requested_new_deadline);

        // ─── HTML داخل toast ───
        var html = '<div class="deadline-toast-wrapper">'

            // ردیف اول: آیکن + متن
            + '<div class="deadline-toast-from">'
            + '<i class="bi bi-hourglass-split" style="font-size:16px; flex-shrink:0;"></i>'
            + '<span>درخواست تمدید موعد از طرف <strong>' + escapeHtml(request.requester_name) + '</strong></span>'
            + '</div>'

            // ردیف دوم: تاریخ درخواستی
            + '<div class="deadline-toast-date">'
            + '<i class="bi bi-calendar-check"></i>'
            + 'موعد جدید: ' + persianDate
            + '</div>'

            // دکمه‌ها
            + '<div class="deadline-toast-actions">'
            + '<button class="deadline-toast-btn btn-approve" onclick="handleDeadlineAction(\'approve\', ' + request.id + ')">'
            + '<i class="bi bi-check-circle-fill"></i> تأیید'
            + '</button>'
            + '<button class="deadline-toast-btn btn-reject" onclick="handleDeadlineAction(\'reject\', ' + request.id + ')">'
            + '<i class="bi bi-x-circle-fill"></i> رد'
            + '</button>'
            + '<button class="deadline-toast-btn btn-later" onclick="handleDeadlineAction(\'later\', ' + request.id + ')">'
            + '<i class="bi bi-clock"></i> بعداً یادآوری کن'
            + '</button>'
            + '</div>'

            + '</div>';

        // ─── نمایش با toastr.success ───
        var toastOptions = Object.assign({}, TOASTR_OPTIONS, {
            toastClass: 'toast toast-deadline-alert'
        });

        _activeToastEl = toastr.success(html, '', toastOptions);
    };

    /* ────────────────────────────────────────────
       ۴. هندلر دکمه‌ها
          از HTML به عنوان onclick صدا زده می‌شود
    ──────────────────────────────────────────── */
    window.handleDeadlineAction = function (action, requestId) {

        var authToken = localStorage.getItem('auth_token');

        if (action === 'later') {
            // بستن toast فعلی
            if (_activeToastEl) toastr.clear(_activeToastEl);
            _activeRequestId = null;

            // بعد از ۵ دقیقه دوباره نشان بده
            // (تنها اگر داده‌ی درخواست هنوز در حافظه باشد)
            _remindTimer = setTimeout(function () {
                checkDeadlineRequests(); // صدا زده می‌شود تا دوباره بخواند
            }, 5 * 60 * 1000); // ۵ دقیقه

            showSmallInfo('در ۵ دقیقه دیگر یادآوری می‌شود');
            return;
        }

        // بستن toast برای جلوگیری از کلیک مجدد
        if (_activeToastEl) toastr.clear(_activeToastEl);

        if (action === 'approve') {
            sendDeadlineResponse('approve', requestId, null, authToken);
        } else if (action === 'reject') {
            // نمایش یک prompt ساده برای دلیل رد
            // (اگر modal rejectReasonModal در صفحه وجود دارد از آن استفاده می‌کنیم)
            openRejectReasonForToast(requestId, authToken);
        }
    };

    /* ────────────────────────────────────────────
       ۵. ارسال پاسخ به API
    ──────────────────────────────────────────── */
    function sendDeadlineResponse(action, requestId, rejectionReason, authToken) {

        var url = action === 'approve'
            ? '/api/tasks/approve-deadline.php'
            : '/api/tasks/reject-deadline.php';

        var body = { request_id: requestId };
        if (rejectionReason) body.rejection_reason = rejectionReason;

        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + authToken
            },
            body: JSON.stringify(body)
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            _activeRequestId = null;
            _activeToastEl   = null;

            if (data.success) {
                var msg = action === 'approve'
                    ? 'درخواست تمدید تأیید شد'
                    : 'درخواست تمدید رد شد';

                // نمایش toast تأیید
                toastr.success(msg, '', {
                    closeButton:  true,
                    progressBar:  true,
                    positionClass:'toast-bottom-left',
                    rtl:          true,
                    timeOut:      3500,
                    showDuration: 300,
                    hideDuration: 400
                });

                // رفرش صفحه بعد از ۱.۵ ثانیه
                setTimeout(function () { location.reload(); }, 1500);

            } else {
                toastr.error(data.message || 'خطایی رخ داد', '', {
                    closeButton:  true,
                    progressBar:  true,
                    positionClass:'toast-bottom-left',
                    rtl:          true,
                    timeOut:      4000
                });
            }
        })
        .catch(function () {
            toastr.error('خطا در ارتباط با سرور', '', {
                closeButton:  true,
                progressBar:  true,
                positionClass:'toast-bottom-left',
                rtl:          true,
                timeOut:      4000
            });
        });
    }

    /* ────────────────────────────────────────────
       ۶. باز کردن modal رد (از همان modal موجود در task-detail.php)
    ──────────────────────────────────────────── */
    function openRejectReasonForToast(requestId, authToken) {

        var modal = document.getElementById('rejectReasonModal');

        if (modal) {
            // استفاده از همان modal موجود در صفحه
            window.currentDeadlineRequestId = requestId;
            modal.style.display = 'block';

            // تنظیم دکمه تأیید رد
            var confirmBtn = document.getElementById('confirmRejectBtn');
            if (confirmBtn) {
                // حذف handler قبلی با clone
                var newBtn = confirmBtn.cloneNode(true);
                confirmBtn.parentNode.replaceChild(newBtn, confirmBtn);

                newBtn.onclick = function () {
                    var reason = document.getElementById('rejectionReasonInput').value.trim();
                    if (!reason) {
                        alert('لطفاً دلیل رد را وارد کنید');
                        return;
                    }
                    modal.style.display = 'none';
                    document.getElementById('rejectionReasonInput').value = '';
                    sendDeadlineResponse('reject', requestId, reason, authToken);
                };
            }
        } else {
            // اگر modal وجود ندارد، یک prompt ساده نشان بده
            var reason = prompt('دلیل رد درخواست را بنویسید:');
            if (reason && reason.trim()) {
                sendDeadlineResponse('reject', requestId, reason.trim(), authToken);
            }
        }
    }

    /* ────────────────────────────────────────────
       ۷. بررسی خودکار درخواست‌های منتظر
          این تابع را در task-detail.php صدا می‌زنیم
          (بعد از loadTaskDetails)
    ──────────────────────────────────────────── */
    window.checkDeadlineRequests = function (taskId) {

        var authToken = localStorage.getItem('auth_token');
        var currentUser = JSON.parse(localStorage.getItem('user_info') || '{}');

        if (!taskId || !authToken || !currentUser.id) return;

        fetch('/api/tasks/get-deadline-requests.php?task_id=' + taskId, {
            headers: { 'Authorization': 'Bearer ' + authToken }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success || !data.requests || data.requests.length === 0) return;

            var request = data.requests[0];

            // فقط اگر کاربر جاری current_approver است toast نشان بده
            if (String(request.current_approver_id) !== String(currentUser.id)) return;

            showDeadlineExtensionToast({
                id:                     request.id,
                requester_name:         (request.first_name || '') + ' ' + (request.last_name || ''),
                requested_new_deadline: request.requested_new_deadline,
                reason:                 request.reason || ''
            });
        })
        .catch(function (err) {
            console.error('deadline-toast: خطا در بررسی درخواست‌ها', err);
        });
    };

    /* ────────────────────────────────────────────
       ۸. توابع کمکی
    ──────────────────────────────────────────── */

    // تبدیل تاریخ میلادی به شمسی فارسی
    function formatDeadlinePersian(dateString) {
        if (!dateString) return 'نامشخص';
        try {
            var date = new Date(dateString);
            return date.toLocaleDateString('fa-IR', {
                year:  'numeric',
                month: 'long',
                day:   'numeric'
            });
        } catch (e) {
            return dateString;
        }
    }

    // جلوگیری از XSS
    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // نمایش پیام کوچک اطلاع‌رسانی
    function showSmallInfo(msg) {
        toastr.info(msg, '', {
            closeButton:  false,
            progressBar:  true,
            positionClass:'toast-bottom-left',
            rtl:          true,
            timeOut:      3000,
            showDuration: 250,
            hideDuration: 300
        });
    }

})();
