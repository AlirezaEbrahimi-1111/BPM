<?php
function asset($path) {
    // مسیر فیزیکی فایل روی سرور — صرف‌نظر از تعداد «../» یا «/» ابتدای
    // آرگومان (بسته به عمق صفحه‌ای که asset() رو صدا زده)، همیشه نسبت به
    // ریشه‌ی وب حل می‌شه. بدون این نرمال‌سازی، هر «../» باعث file_exists()
    // نادرست (یک‌سطح بالاتر ریشه) و درنتیجه fallback به time() می‌شد —
    // یعنی نسخه‌ی جدید در هر درخواست و بی‌اثرشدن کامل کش مرورگر.
    $normalized = preg_replace('#^(?:\.\./|/)+#', '', $path);
    $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/' . $normalized;

    // اگر فایل وجود داشت، زمان آخرین تغییرش رو به عنوان ورژن بگیر
    $version = file_exists($fullPath) ? filemtime($fullPath) : time();

    return $path . '?v=' . $version;
}
?>