<?php
/**
 * داشبورد کارشناس فروش (۱۴۰۵/۰۸/۰۳): کارت «کارهای کارشناس فروش» کنار کارت «کارها»
 * (داخل .tasks-row در pages/dashboard-user.php). همهٔ پیگیری‌های انجام‌نشدهٔ مشتری‌های
 * خود کارشناس — اول عقب‌افتاده‌ها. $__ucdMode را pages/partials/ucrm-dash-sales.php می‌سازد.
 * رفتار: assets/js/ucrm-sales-dash.js (UcrmDash.initRep)
 */
if (($__ucdMode ?? '') !== 'rep') return;
?>
<link rel="stylesheet" href="<?= asset('../assets/css/ucrm-dash.css') ?>">
<div class="dash-card">
    <div class="dash-card-head">
        <div class="dash-card-title">
            <i class="bi bi-telephone" style="color:#2563eb;width:32px;height:32px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size: .9rem;flex-shrink:0;"></i><span>کارهای کارشناس فروش</span>
        </div>
        <a href="ucrm-customers.php" class="dash-see-all">
            مشاهده همه <i class="bi bi-chevron-left"></i>
        </a>
    </div>
    <div class="dash-card-body">
        <table class="task-table ucd-fu-table">
            <thead>
                <tr>
                    <th>مشتری</th>
                    <th>موضوع تماس</th>
                    <th>تاریخ پیگیری</th>
                    <th class="col-status">وضعیت</th>
                    <th class="col-ops">عملیات</th>
                </tr>
            </thead>
            <tbody id="ucdFuBody">
                <tr>
                    <td colspan="5" class="dash-loading"><span class="spinner-border spinner-border-sm" role="status"></span>در حال بارگذاری…</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
