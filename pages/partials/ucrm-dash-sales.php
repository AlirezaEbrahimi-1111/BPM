<?php
/**
 * تب «فروش» داشبورد (مدیر و حسابداری CRM، ۱۴۰۵/۰۸/۰۳) — درست قبل از <div class="dash-wrap"> هر دو داشبورد.
 *   دکمه‌های «عملیات | فروش» بالا سمت چپ؛ «عملیات» = همان داشبورد فعلی، «فروش» = این بخش.
 *   پیش‌فرض «فروش»؛ آخرین انتخاب در همین مرورگر می‌ماند (localStorage «dash-tab»).
 * پیش‌نیاز: $db و $user_id (includes/page-bootstrap.php). رفتار: assets/js/ucrm-sales-dash.js
 */
require_once __DIR__ . '/../../includes/ucrm_access.php';
$__ucdMode = ucrmDashMode($db, (int) $user_id);
if ($__ucdMode !== 'tabs') return;
?>
<link rel="stylesheet" href="<?= asset('../assets/css/ucrm.css') ?>">
<link rel="stylesheet" href="<?= asset('../assets/css/ucrm-dash.css') ?>">
<style>
    /* کدام تب — کلاس روی <html> قبل از رسم، تا داشبورد «عملیات» یک لحظه چشمک نزند */
    html.ucd-on-sales .dash-wrap { display: none !important; }
    html:not(.ucd-on-sales) .ucd-sales { display: none !important; }
</style>
<script>
    (function () {
        var v = '';
        try { v = localStorage.getItem('dash-tab') || ''; } catch (e) { /* مرورگر اجازه نداد */ }
        if (v !== 'ops') document.documentElement.classList.add('ucd-on-sales');
    })();
</script>

<div class="ucd-bar">
    <div class="ucd-switch" role="tablist" aria-label="نوع داشبورد">
        <button type="button" role="tab" data-ucd="ops"><i class="bi bi-grid"></i>عملیات</button>
        <button type="button" role="tab" data-ucd="sales"><i class="bi bi-graph-up-arrow"></i>فروش</button>
    </div>
</div>

<div class="ucd-sales" id="ucdSales">

    <!-- فیلترهای ماتریس و نمودارها -->
    <div class="ucd-card">
        <div class="ucd-head">
            <h3><i class="bi bi-graph-up-arrow"></i>داشبورد فروش</h3>
            <div class="spacer"></div>
            <div class="uc-tg-f"><i class="bi bi-person"></i>
                <select id="ucdRep" class="form-select form-select-sm" title="کارشناس">
                    <option value="">همهٔ کارشناسان</option>
                </select>
            </div>
            <div class="uc-pf-range dropdown">
                <button type="button" class="btn uc-pf-range-btn" id="ucdRangeBtn" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                    <i class="bi bi-calendar3"></i><span id="ucdRangeLbl">…</span><i class="bi bi-chevron-down small"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-end p-3 uc-pf-range-menu">
                    <div class="uc-pf-quick" id="ucdQuick">
                        <button type="button" class="btn btn-sm" data-q="month">ماه جاری</button>
                        <button type="button" class="btn btn-sm" data-q="last">ماه قبل</button>
                        <button type="button" class="btn btn-sm" data-q="7">۷ روز اخیر</button>
                        <button type="button" class="btn btn-sm" data-q="30">۳۰ روز اخیر</button>
                    </div>
                    <label class="form-label small mt-2 mb-1">از تاریخ</label>
                    <div class="persian-datepicker-wrapper">
                        <input type="text" class="persian-datepicker-input form-control form-control-sm" id="ucdFrom" readonly>
                        <div class="persian-datepicker">
                            <div class="datepicker-header"><button type="button" class="datepicker-nav" data-action="prev">►</button><span class="datepicker-current"></span><button type="button" class="datepicker-nav" data-action="next">◄</button></div>
                            <div class="datepicker-weekdays"><div class="datepicker-weekday">ش</div><div class="datepicker-weekday">ی</div><div class="datepicker-weekday">د</div><div class="datepicker-weekday">س</div><div class="datepicker-weekday">چ</div><div class="datepicker-weekday">پ</div><div class="datepicker-weekday">ج</div></div>
                            <div class="datepicker-days"></div><button type="button" class="datepicker-today-btn">امروز</button>
                        </div>
                    </div>
                    <label class="form-label small mt-2 mb-1">تا تاریخ</label>
                    <div class="persian-datepicker-wrapper">
                        <input type="text" class="persian-datepicker-input form-control form-control-sm" id="ucdTo" readonly>
                        <div class="persian-datepicker">
                            <div class="datepicker-header"><button type="button" class="datepicker-nav" data-action="prev">►</button><span class="datepicker-current"></span><button type="button" class="datepicker-nav" data-action="next">◄</button></div>
                            <div class="datepicker-weekdays"><div class="datepicker-weekday">ش</div><div class="datepicker-weekday">ی</div><div class="datepicker-weekday">د</div><div class="datepicker-weekday">س</div><div class="datepicker-weekday">چ</div><div class="datepicker-weekday">پ</div><div class="datepicker-weekday">ج</div></div>
                            <div class="datepicker-days"></div><button type="button" class="datepicker-today-btn">امروز</button>
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm w-100 mt-3" id="ucdRangeApply">اعمال بازه</button>
                </div>
            </div>
        </div>
    </div>

    <div class="ucd-row2">
        <!-- مشتریان نیازمند پیگیری (بازهٔ تاریخ روی این بخش اثر ندارد) -->
        <div class="ucd-card">
            <div class="ucd-head">
                <h3><i class="bi bi-person-exclamation"></i>مشتریان نیازمند پیگیری</h3>
                <div class="spacer"></div>
                <input type="search" class="form-control ucd-search" id="ucdSearch" placeholder="جستجوی مشتری یا کارشناس" autocomplete="off">
                <button type="button" class="btn btn-primary ucd-xl" id="ucdExcel"><i class="bi bi-download ms-1"></i>خروجی اکسل</button>
            </div>
            <div class="ucd-scroll">
                <table class="ucd-table">
                    <thead><tr><th>نام مشتری</th><th>کارشناس مسئول</th><th>آخرین پیگیری</th><th class="c">مدت عدم پیگیری</th><th class="c">اطلاع‌رسانی</th></tr></thead>
                    <tbody id="ucdNeeds"><tr><td colspan="5" class="empty">در حال بارگذاری…</td></tr></tbody>
                </table>
            </div>
            <div class="ucd-note" id="ucdNeedsNote"></div>
        </div>

        <!-- ماتریس تارگت و فروش -->
        <div class="ucd-card">
            <div class="ucd-head">
                <h3><i class="bi bi-table"></i>ماتریس تارگت و فروش</h3>
            </div>
            <div class="ucd-scroll">
                <table class="ucd-table">
                    <thead><tr><th>کارشناس</th><th>تارگت فروش</th><th>فروش</th><th>درصد تحقق</th><th>تفاوت</th></tr></thead>
                    <tbody id="ucdMatrix"><tr><td colspan="5" class="empty">در حال بارگذاری…</td></tr></tbody>
                </table>
            </div>
            <div class="ucd-note"><i class="bi bi-info-circle"></i>مبلغ‌ها به تومان است. فروش از فاکتورهای CRM حساب می‌شود؛ تا ساخته شدن آن بخش، فروش صفر نشان داده می‌شود.</div>
        </div>
    </div>

    <!-- سه نمودار، از راست به چپ -->
    <div class="ucd-row3">
        <div class="ucd-card">
            <div class="ucd-head"><h3><i class="bi bi-bar-chart"></i>مقایسه تیم فروش</h3></div>
            <div class="ucd-chart"><canvas id="ucdChTeam" aria-label="فروش هر کارشناس در بازهٔ انتخاب‌شده"></canvas><div class="ucd-chart-empty" id="ucdTeamEmpty" hidden>هنوز فروشی در این بازه ثبت نشده است.</div></div>
        </div>
        <div class="ucd-card">
            <div class="ucd-head"><h3><i class="bi bi-speedometer2"></i>شاخص‌های کلیدی</h3></div>
            <div class="ucd-donut">
                <canvas id="ucdChKpi" aria-label="درصد تحقق تارگت کل تیم"></canvas>
                <div class="center"><b id="ucdKpiPct">۰٪</b><small>تحقق تارگت تیم</small></div>
            </div>
            <div class="ucd-kpis" id="ucdKpis"></div>
        </div>
        <div class="ucd-card">
            <div class="ucd-head"><h3><i class="bi bi-bar-chart-steps"></i>مقایسه هدف و فروش کارشناس‌ها</h3></div>
            <div class="ucd-chart"><canvas id="ucdChCmp" aria-label="تارگت و فروش هر کارشناس"></canvas></div>
        </div>
    </div>
</div>
