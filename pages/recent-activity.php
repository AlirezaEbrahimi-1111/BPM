<?php
require_once __DIR__ . '/../includes/page-bootstrap.php';

// 🔒 همان شرط api/dashboard/bootstrap.php و api/dashboard/recent-activity.php:
// «کل سازمان» فقط برای مدیر/سرپرست/سوپرادمین؛ بقیه فقط فعالیت خودشان را می‌بینند
$raCanSeeOrg = in_array($__me['role'] ?? '', ['manager', 'supervisor'], true) || isSuperAdmin($__me);
$raScope = ($raCanSeeOrg && ($_GET['scope'] ?? 'org') !== 'personal') ? 'org' : 'personal';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>همه‌ی فعالیت‌ها - سیستم مدیریت کار</title>

    <link href="<?= asset('../assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">
    <script src="<?= asset('../assets/js/config.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('../../assets/css/custom.css') ?>">
    <script src="<?= asset('../../assets/js/ag-grid-community.min.js') ?>"></script>

    <style>
        /* گرید تا پایین صفحه کش می‌آید (فوتر چسبیده، بدون اسکرول صفحه) — مثل بقیهٔ صفحات فهرست */
        .grid-fill { height: calc(100vh - 250px); min-height: 260px; padding-top: 1rem; }

        .ra-controls {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .ra-controls .search-box { flex: 1; }

        /* سوییچر «شخصی / کل سازمان» — همان ظاهر چیپ‌های تب فعالیت‌های اخیر داشبورد */
        .ra-scope { display: flex; gap: 6px; flex-shrink: 0; }

        .ra-scope-chip {
            border: 1px solid var(--border-soft);
            background: #f8f9fa;
            border-radius: var(--radius-btn);
            padding: 5px 14px;
            font-size: .72rem;
            color: var(--text-strong);
            font-weight: 700;
            cursor: pointer;
            transition: all .15s;
        }

        .ra-scope-chip:hover { background: rgba(142, 87, 254, .12); }

        :root[data-theme="dark"] .ra-scope-chip { background: #232a3a; }

        :root[data-theme="dark"] .ra-scope-chip:hover { background: rgba(142, 87, 254, .18); }

        .ra-scope-chip.active,
        :root[data-theme="dark"] .ra-scope-chip.active {
            background: #8e57fe;
            border-color: #8e57fe;
            color: #fff;
            font-weight: 600;
        }

        #raGrid .ag-row { cursor: pointer; }

        /* نشان فعالیت وسط ردیف بنشیند (خود .ml-action-badge برای تاریخچهٔ کار طراحی شده) */
        .ra-action { display: flex; align-items: center; height: 100%; }

        #raGrid .ml-action-badge { margin-top: 0; line-height: 1.4; white-space: nowrap; }

        /* اندازهٔ صفحه ثابت است (هر صفحه یک درخواست به سرور) — برچسب خالی «تعداد در صفحه» دیده نشود */
        #raGrid .ag-paging-page-size { display: none !important; }

        .ra-title { display: flex; flex-direction: column; justify-content: center; line-height: 1.5; height: 100%; }

        .ra-title span { overflow: hidden; text-overflow: ellipsis; }

        .ra-title .ra-sub { font-size: .7rem; color: var(--text-muted, #6b7280); }

        .dash-empty {
            text-align: center;
            color: var(--text-muted, #6b7280);
            font-size: .8rem;
            padding: 40px 14px;
        }

        .dash-empty i { display: block; font-size: 1.6rem; margin-bottom: 8px; opacity: .5; }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="overview-container">

        <div class="filters-wrapper two-col">
            <div class="filters-title-col">
                <h1><i class="bi bi-clock-history"></i> همه‌ی فعالیت‌ها</h1>
                <p id="raSubtitle"></p>
            </div>
            <div class="ra-controls">
                <div class="search-box">
                    <i class="bi bi-search"></i>
                    <input type="text" id="searchInput" placeholder="جستجو در عنوان کار یا نام کاربر...">
                </div>
                <?php if ($raCanSeeOrg): ?>
                    <div class="ra-scope" id="raScope">
                        <button class="ra-scope-chip<?= $raScope === 'personal' ? ' active' : '' ?>" data-scope="personal">شخصی</button>
                        <button class="ra-scope-chip<?= $raScope === 'org' ? ' active' : '' ?>" data-scope="org">کل سازمان</button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div id="raGrid" class="ag-theme-alpine grid-fill" style="width: 100%;"></div>

    </div>

    <?php include 'footer.php'; ?>

    <script src="<?= asset('/assets/js/cdn/bootstrap.bundle.min.js') ?>"></script>
    <script src="<?= asset('/assets/js/task-filters.js') ?>"></script>
    <script>
        'use strict';

        const RA_PAGE_SIZE = 50;
        let raScope = <?= json_encode($raScope) ?>;
        let raSearch = '';
        let raGridApi = null;

        /* تاریخ شمسی + ساعت — از ساعت سرور (TimeSync)، نه ساعت دستگاه */
        function raDateTime(str) {
            if (!str || !window.TimeSync) return '';
            const dt = TimeSync.formatJalali(str), tm = TimeSync.formatTimeOnly(str);
            return dt ? (tm ? `${dt} - ${tm}` : dt) : '';
        }

        function raUpdateSubtitle() {
            document.getElementById('raSubtitle').textContent = (raScope === 'org')
                ? 'فعالیت‌های ثبت‌شده‌ی همه‌ی کاربران سازمان، از جدید به قدیم'
                : 'فعالیت‌هایی که خود شما انجام داده‌اید، از جدید به قدیم';
        }

        const raColumnDefs = [
            {
                headerName: 'زمان',
                field: 'timestamp',
                width: 170,
                cellRenderer: p => p.data ? `<span class="date-display">${raDateTime(p.value) || 'نامشخص'}</span>` : ''
            },
            {
                headerName: 'کاربر',
                field: 'actor_name',
                flex: 1,
                minWidth: 130,
                cellRenderer: p => p.data ? esc(p.value || 'نامشخص') : ''
            },
            {
                headerName: 'فعالیت',
                field: 'action',
                flex: 1,
                minWidth: 150,
                // برچسب و رنگ هر فعالیت — تنها مرجع: TF.actionCfg (assets/js/task-filters.js)
                cellRenderer: p => p.data
                    ? `<div class="ra-action"><span class="ml-action-badge ${TF.actionClass(p.value)}">${esc(p.value ? TF.actionLabel(p.value) : 'نامشخص')}</span></div>`
                    : ''
            },
            {
                headerName: 'عنوان کار',
                field: 'title',
                flex: 2.4,
                minWidth: 220,
                cellRenderer: p => {
                    if (!p.data) return '';
                    const title = esc(p.data.title || 'بدون عنوان');
                    // فعالیت‌های چک‌لیست: عنوان آیتم بالا، نام کار زیرش
                    if (p.data.item_title) {
                        return `<div class="ra-title"><span>${esc(p.data.item_title)}</span><span class="ra-sub">در کار: ${title}</span></div>`;
                    }
                    return `<div class="ra-title"><span>${title}</span></div>`;
                }
            }
        ];

        /* هر صفحه‌ی گرید = یک درخواست به سرور (صفحه‌بندی سمت سرور) */
        function raDatasource() {
            return {
                getRows: async params => {
                    const page = Math.floor(params.startRow / RA_PAGE_SIZE) + 1;
                    const url = '/api/dashboard/recent-activity.php?page=' + page + '&per=' + RA_PAGE_SIZE +
                        '&scope=' + encodeURIComponent(raScope) + '&q=' + encodeURIComponent(raSearch);
                    try {
                        const res = await fetch(url, { headers: { 'Authorization': 'Bearer ' + authToken } });
                        const data = await res.json();
                        if (!data.success) throw new Error(data.message || 'خطا در دریافت اطلاعات');
                        params.successCallback(data.activities || [], data.total || 0);
                        if (data.total) raGridApi.hideOverlay();
                        else raGridApi.showNoRowsOverlay();
                    } catch (e) {
                        console.error('recent-activity:', e);
                        params.failCallback();
                        showToast('دریافت فهرست فعالیت‌ها با خطا روبه‌رو شد. لطفا دوباره تلاش کنید.', 'error');
                    }
                }
            };
        }

        function raReload() {
            raUpdateSubtitle();
            raGridApi.setGridOption('datasource', raDatasource());
            raGridApi.paginationGoToFirstPage();
        }

        const raGridOptions = {
            columnDefs: raColumnDefs,
            defaultColDef: { resizable: true, sortable: false },
            enableRtl: true,
            rowHeight: 50,
            rowModelType: 'infinite',
            cacheBlockSize: RA_PAGE_SIZE,
            maxBlocksInCache: 4,
            pagination: true,
            paginationPageSize: RA_PAGE_SIZE,
            paginationPageSizeSelector: false,
            onPaginationChanged: () => AgGridFa.persianizePaging(),
            onRowClicked: e => { if (e.data) window.location.href = 'task-detail.php?id=' + e.data.task_id; },
            overlayNoRowsTemplate: '<div class="dash-empty"><i class="bi bi-clock-history"></i>فعالیتی برای نمایش یافت نشد</div>'
        };

        document.addEventListener('DOMContentLoaded', function () {
            if (!authToken) {
                window.location.href = '../index.php';
                return;
            }
            raGridApi = agGrid.createGrid(document.getElementById('raGrid'), raGridOptions);
            raReload();

            let searchTimeout;
            document.getElementById('searchInput').addEventListener('input', function () {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    raSearch = this.value.trim();
                    raReload();
                }, 350);
            });

            document.querySelectorAll('.ra-scope-chip').forEach(chip => {
                chip.addEventListener('click', () => {
                    if (chip.classList.contains('active')) return;
                    raScope = chip.dataset.scope;
                    document.querySelectorAll('.ra-scope-chip').forEach(c => c.classList.remove('active'));
                    chip.classList.add('active');
                    raReload();
                });
            });
        });
    </script>
</body>

</html>
