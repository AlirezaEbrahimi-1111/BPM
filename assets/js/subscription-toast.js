/* ============================================================
   فایل: assets/js/subscription-toast.js
   هدف: نمایش toast هشدار انقضای اشتراک سازمان
   
   کاملاً مستقل — نیازی به alert.js یا هیچ فایل دیگری ندارد
   
   منطق:
     - هر بار صفحه لود میشه، API رو چک می‌کنه
     - اگر days_remaining <= 3 باشه، toast نمایش میده
     - کاربر "متوجه شدم" بزنه → localStorage ذخیره میشه
       و تا 24 ساعت دیگه نمایش داده نمیشه
     - بعد 24 ساعت دوباره نمایش داده میشه
     - اگه اشتراک تمدید بشه → خودکار پاک میشه
   ============================================================ */

(function () {

    /* ثابت‌ها */
    var STORAGE_KEY      = 'subscription_toast_dismissed_at';
    var DISMISS_DURATION = 24 * 60 * 60 * 1000;   // 24 ساعت
    var WARNING_DAYS     = 3;
    var TOAST_DURATION   = 60000;                  // 60 ثانیه نمایش
    var API_URL          = '/api/organization/check-subscription.php';

    /* ──────────────────────────────────────────── */
    /*  بررسی اصلی                                  */
    /* ──────────────────────────────────────────── */
    function checkSubscriptionExpiry() {

        var authToken = localStorage.getItem('auth_token');
        if (!authToken) return;

        // آیا قبلاً dismiss شده و هنوز 24 ساعت نگذشته؟
        var dismissedAt = localStorage.getItem(STORAGE_KEY);
        if (dismissedAt) {
            var elapsed = Date.now() - parseInt(dismissedAt, 10);
            if (elapsed < DISMISS_DURATION) {
                return;
            }
        }

        fetch(API_URL, {
            method: 'GET',
            headers: { 'Authorization': 'Bearer ' + authToken }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {

            if (!data.success) return;

            if (!data.has_subscription) {
                showSubscriptionToast(-1, 'no_subscription');
                return;
            }

            if (data.status === 'expired') {
                showSubscriptionToast(data.days_remaining, 'expired');
                return;
            }

            if (data.days_remaining <= WARNING_DAYS) {
                showSubscriptionToast(data.days_remaining, 'expiring_soon');
                return;
            }

            // اشتراک خوبه → پاک کردن dismiss قبلی
            localStorage.removeItem(STORAGE_KEY);
        })
        .catch(function (err) {
            console.error('subscription-toast: خطا', err);
        });
    }

    /* ──────────────────────────────────────────── */
    /*  نمایش toast (کاملاً مستقل)                   */
    /* ──────────────────────────────────────────── */
    function showSubscriptionToast(daysRemaining, status) {

        // حذف toast قبلی
        var existing = document.getElementById('subscription-expiry-toast');
        if (existing) existing.remove();

        // تعیین متن و رنگ
        var message, icon, bgColor, borderColor, iconColor;

        if (status === 'expired' || status === 'no_subscription') {
            message     = 'اشتراک سازمان شما منقضی شده است. لطفاً برای تمدید اشتراک با پشتیبانی تماس بگیرید.';
            icon        = 'bi-exclamation-octagon-fill';
            bgColor     = '#fef2f2';
            borderColor = '#ef4444';
            iconColor   = '#ef4444';
        } else if (daysRemaining <= 0) {
            message     = 'اشتراک سازمان شما امروز منقضی می‌شود! لطفاً برای تمدید با پشتیبانی تماس بگیرید.';
            icon        = 'bi-exclamation-octagon-fill';
            bgColor     = '#fef2f2';
            borderColor = '#ef4444';
            iconColor   = '#ef4444';
        } else if (daysRemaining === 1) {
            message     = 'اشتراک سازمان شما فردا منقضی می‌شود. لطفاً برای خرید یا تمدید اشتراک با پشتیبانی ارتباط بگیرید.';
            icon        = 'bi-exclamation-triangle-fill';
            bgColor     = '#fffbeb';
            borderColor = '#f59e0b';
            iconColor   = '#f59e0b';
        } else {
            message     = 'اشتراک سازمان شما ' + daysRemaining + ' روز دیگر منقضی می‌شود. لطفاً برای خرید یا تمدید اشتراک با پشتیبانی ارتباط بگیرید.';
            icon        = 'bi-exclamation-triangle-fill';
            bgColor     = '#fffbeb';
            borderColor = '#f59e0b';
            iconColor   = '#f59e0b';
        }

        // ساخت المان toast
        var toast = document.createElement('div');
        toast.id = 'subscription-expiry-toast';
        toast.style.cssText =
            'position:fixed;' +
            'top:80px;' +
            'left:20px;' +
            'right:auto;' +
            'min-width:320px;' +
            'max-width:440px;' +
            'background:' + bgColor + ';' +
            'border-radius:14px;' +
            'padding:16px 20px;' +
            'box-shadow:0 8px 32px rgba(0,0,0,0.15);' +
            'z-index:10000;' +
            'border-right:4px solid ' + borderColor + ';' +
            'border-left:none;' +
            'transform:translateX(-120%);' +
            'transition:transform 0.4s ease, opacity 0.4s ease;' +
            'opacity:0;' +
            'direction:rtl;' +
            'font-family:Vazir,sans-serif;' +
            'overflow:hidden;';

        // ردیف آیکن + متن
        var header = document.createElement('div');
        header.style.cssText = 'display:flex;align-items:flex-start;gap:10px;';
        header.innerHTML =
            '<i class="bi ' + icon + '" style="color:' + iconColor + ';font-size:1.4rem;flex-shrink:0;margin-top:2px;"></i>' +
            '<span style="font-weight:600;color:#1e293b;font-size:0.88rem;line-height:1.7;">' + message + '</span>';
        toast.appendChild(header);

        // ردیف دکمه‌ها
        var btnRow = document.createElement('div');
        btnRow.style.cssText = 'display:flex;gap:8px;margin-top:14px;justify-content:flex-end;';

        // دکمه "متوجه شدم"
        var dismissBtn = document.createElement('button');
        dismissBtn.textContent = 'متوجه شدم';
        dismissBtn.style.cssText =
            'padding:7px 20px;' +
            'border-radius:8px;' +
            'border:none;' +
            'background:' + borderColor + ';' +
            'color:white;' +
            'cursor:pointer;' +
            'font-size:0.85rem;' +
            'font-weight:600;' +
            'font-family:Vazir,sans-serif;' +
            'transition:opacity 0.2s;';
        dismissBtn.onmouseenter = function () { this.style.opacity = '0.85'; };
        dismissBtn.onmouseleave = function () { this.style.opacity = '1'; };
        dismissBtn.onclick = function () {
            localStorage.setItem(STORAGE_KEY, String(Date.now()));
            closeToast();
        };

        // دکمه بستن
        var closeXBtn = document.createElement('button');
        closeXBtn.innerHTML = '<i class="bi bi-x-lg"></i>';
        closeXBtn.title = 'بستن';
        closeXBtn.style.cssText =
            'padding:7px 12px;' +
            'border-radius:8px;' +
            'border:1.5px solid ' + borderColor + ';' +
            'background:transparent;' +
            'color:' + borderColor + ';' +
            'cursor:pointer;' +
            'font-size:0.85rem;' +
            'font-family:Vazir,sans-serif;' +
            'transition:opacity 0.2s;';
        closeXBtn.onmouseenter = function () { this.style.opacity = '0.7'; };
        closeXBtn.onmouseleave = function () { this.style.opacity = '1'; };
        closeXBtn.onclick = function () {
            closeToast();   // فقط بستن، بدون ذخیره — دفعه بعد باز نشون میده
        };

        btnRow.appendChild(dismissBtn);
        btnRow.appendChild(closeXBtn);
        toast.appendChild(btnRow);

        // نوار پیشرفت
        var progressBar = document.createElement('div');
        progressBar.style.cssText =
            'position:absolute;' +
            'bottom:0;' +
            'right:0;' +
            'height:3px;' +
            'width:100%;' +
            'background:' + borderColor + ';' +
            'border-radius:0 0 14px 14px;' +
            'transform-origin:right;' +
            'transition:transform ' + TOAST_DURATION + 'ms linear;';
        toast.appendChild(progressBar);

        document.body.appendChild(toast);

        // انیمیشن ورود
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                toast.style.transform = 'translateX(0)';
                toast.style.opacity   = '1';
                progressBar.style.transform = 'scaleX(0)';
            });
        });

        // بسته شدن خودکار
        var autoCloseTimer = setTimeout(closeToast, TOAST_DURATION);

        // hover: متوقف کردن تایمر
        toast.onmouseenter = function () {
            clearTimeout(autoCloseTimer);
            progressBar.style.transition = 'none';
        };
        toast.onmouseleave = function () {
            var remaining = TOAST_DURATION * 0.3;
            progressBar.style.transition = 'transform ' + remaining + 'ms linear';
            progressBar.style.transform  = 'scaleX(0)';
            autoCloseTimer = setTimeout(closeToast, remaining);
        };

        function closeToast() {
            clearTimeout(autoCloseTimer);
            toast.style.transform = 'translateX(-120%)';
            toast.style.opacity   = '0';
            setTimeout(function () {
                if (toast.parentNode) toast.remove();
            }, 400);
        }
    }

    /* ──────────────────────────────────────────── */
    /*  اجرای خودکار                                */
    /* ──────────────────────────────────────────── */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            setTimeout(checkSubscriptionExpiry, 2000);
        });
    } else {
        setTimeout(checkSubscriptionExpiry, 2000);
    }

    window.checkSubscriptionExpiry = checkSubscriptionExpiry;

})();