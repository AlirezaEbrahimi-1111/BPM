<?php
/**
 * تشخیصِ mime_type واقعیِ یک پیوستِ تیکت — از رویِ خودِ فایل (یا در نبودِ
 * fileinfo، از رویِ پسوندِ ازقبل‌تأییدشده)، نه از رویِ Content-Type ای که
 * مرورگر توی درخواستِ آپلود فرستاده. آن مقدار قابلِ‌اعتماد نیست — مثلاً
 * Samsung Internet برایِ عکسِ چسبانده‌شده (Ctrl+V) گاهی نوعِ خالی یا نادرست
 * می‌فرستد، و همین باعث می‌شد بعداً در پیشخوانِ تیکت به‌جایِ تصویر، فقط
 * آیکنِ فایلِ عمومی نشان داده شود.
 */
function detectTicketAttachmentMime(string $filePath, string $ext): string
{
    if (function_exists('mime_content_type')) {
        $detected = @mime_content_type($filePath);
        if ($detected) {
            return $detected;
        }
    }

    $byExt = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'pdf'  => 'application/pdf',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls'  => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'mp3'  => 'audio/mpeg',
        'm4a'  => 'audio/mp4',
        'ogg'  => 'audio/ogg',
        'txt'  => 'text/plain',
    ];

    return $byExt[strtolower($ext)] ?? 'application/octet-stream';
}
