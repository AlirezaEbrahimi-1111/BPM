/*
 * task-attachments.js — قواعد مشترک «پیوست کار» سمت مرورگر
 * ------------------------------------------------------------------
 * فهرست پسوندهای مجاز و سقف حجم هر نوع فایل، یک‌جا — تا صفحهٔ ساخت کار
 * (create-task.php) و صفحهٔ جزئیات کار (task-detail.php) هرکدام نسخهٔ
 * خودشان را نداشته باشند.
 *
 * مرز واقعی سرور است: api/tasks/upload-attachment.php همین قواعد را (به‌اضافهٔ
 * بررسی محتوای فایل) دوباره اعمال می‌کند. اگر آن‌جا عوض شد، این‌جا هم عوض شود.
 *
 * فقط function/var (بدون let/const سطح بالا) — این فایل در چند صفحه لود می‌شود.
 */
(function (w) {
    'use strict';

    var MB = 1024 * 1024;
    var MAX_DEFAULT = 20 * MB;   // عکس، سند، صوت
    var MAX_VIDEO = 50 * MB;     // mp4 / mov
    var VIDEO_EXT = ['mp4', 'mov'];
    var ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'mp3', 'm4a', 'ogg'].concat(VIDEO_EXT);

    function extOf(file) {
        var name = (file && file.name) ? String(file.name) : '';
        var i = name.lastIndexOf('.');
        return i === -1 ? '' : name.slice(i + 1).toLowerCase();
    }

    /** آیا این فایل ویدیو است؟ از روی پسوند — مرورگر برای mov گاهی نوع (type) خالی می‌دهد */
    function isVideo(file) {
        return VIDEO_EXT.indexOf(extOf(file)) !== -1;
    }

    function maxSize(file) {
        return isVideo(file) ? MAX_VIDEO : MAX_DEFAULT;
    }

    /** اگر فایل از سقف حجم نوع خودش بزرگ‌تر باشد پیام فارسی برمی‌گرداند، وگرنه null */
    function tooBigMessage(file) {
        if (!file || file.size <= maxSize(file)) return null;
        return isVideo(file)
            ? 'فایل ویدیویی «' + file.name + '» بزرگ‌تر از ۵۰ مگابایت است و اضافه نشد.'
            : 'فایل «' + file.name + '» بزرگ‌تر از ۲۰ مگابایت است و اضافه نشد.';
    }

    w.TaskAttachments = {
        ACCEPT: ALLOWED_EXT.map(function (e) { return '.' + e; }).join(','),
        FORMATS_HINT: 'فرمت‌های مجاز: jpg, png, pdf, docx, xlsx, mp3, m4a, ogg, mp4, mov',
        SIZE_HINT: 'ویدیو حداکثر ۵۰ مگابایت، بقیهٔ فایل‌ها حداکثر ۲۰ مگابایت',
        extOf: extOf,
        isVideo: isVideo,
        maxSize: maxSize,
        tooBigMessage: tooBigMessage
    };
})(window);
