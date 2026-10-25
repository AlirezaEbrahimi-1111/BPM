<?php
/**
 * اسکریپت‌های داشبورد فروش CRM (۱۴۰۵/۰۸/۰۳) — آخر <body> هر دو داشبورد، قبل از footer.
 * $__ucdMode را pages/partials/ucrm-dash-sales.php می‌سازد.
 */
if (!in_array($__ucdMode ?? '', ['tabs', 'rep'], true)) return;
?>
<script>window.UCRM_ME = <?= (int) $user_id ?>;</script>
<script src="<?= asset('../assets/js/ucrm-common.js') ?>"></script>
<?php if ($__ucdMode === 'tabs'): ?>
    <script src="<?= asset('../assets/js/cdn/chart.js') ?>"></script>
    <script src="<?= asset('../assets/js/cdn/xlsx.full.min.js') ?>"></script>
    <script src="<?= asset('../assets/js/persian-date-utils.js') ?>"></script>
<?php endif; ?>
<script src="<?= asset('../assets/js/ucrm-sales-dash.js') ?>"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        UcrmDash.<?= $__ucdMode === 'tabs' ? 'initTabs' : 'initRep' ?>();
    });
</script>
