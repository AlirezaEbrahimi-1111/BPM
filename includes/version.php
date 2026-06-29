<?php
function asset($path) {
    // مسیر فیزیکی فایل روی سرور
    $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($path, '/');
    
    // اگر فایل وجود داشت، زمان آخرین تغییرش رو به عنوان ورژن بگیر
    $version = file_exists($fullPath) ? filemtime($fullPath) : time();
    
    return $path . '?v=' . $version;
}
?>