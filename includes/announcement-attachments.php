<?php
/**
 * منطق مشترک فایل‌های پیوست اطلاعیه‌ها: دسترسی، اعتبارسنجی و حذف فایل.
 *
 * فهرست پسوندها و سقف حجم عمدا همان api/tasks/upload-attachment.php است.
 * ⚠️ سقف‌ها باید از upload_max_filesize / post_max_size در ‎.htaccess‎ ریشه کمتر بمانند.
 */

require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/user-sections.php';

const ANN_ATT_MAX_SIZE       = 20 * 1024 * 1024;
const ANN_ATT_MAX_SIZE_VIDEO = 50 * 1024 * 1024;
const ANN_ATT_MAX_COUNT      = 10;
const ANN_ATT_EXTENSIONS     = ['jpg', 'jpeg', 'png', 'pdf', 'docx', 'doc', 'xls', 'xlsx', 'mp3', 'm4a', 'ogg', 'mp4', 'mov'];
const ANN_ATT_MIME_TYPES     = [
    'image/jpeg', 'image/png', 'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'audio/mpeg', 'audio/mp4', 'audio/ogg', 'audio/x-m4a',
    'video/mp4', 'video/quicktime',
];

function annAttDir(): string
{
    return $_SERVER['DOCUMENT_ROOT'] . '/uploads/announcements/';
}

function annAttLoad(PDO $db, int $announcementId): ?array
{
    $stmt = $db->prepare("SELECT id, organization_id, target_section, target_user_id, is_active, publish_at, expire_at FROM announcements WHERE id = ?");
    $stmt->execute([$announcementId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function annAttLoadUser(PDO $db, int $userId): ?array
{
    $stmt = $db->prepare("SELECT id, role, organization_id FROM users WHERE id = ? AND is_active = 1");
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** هم‌تعریف با بررسی مالکیت در api/announcements/update.php */
function annAttCanManage(array $user, array $ann): bool
{
    if (in_array((int) $user['id'], getSuperAdminIds(), true)) return true;
    return in_array($user['role'], ['management', 'supervisor'], true)
        && $ann['organization_id'] !== null
        && (int) $ann['organization_id'] === (int) $user['organization_id'];
}

/** هم‌تعریف با دامنهٔ دید api/announcements/list.php (حالت عادی و حالت all=1 مدیران) */
function annAttCanView(PDO $db, array $user, array $ann): bool
{
    $isSuper = in_array((int) $user['id'], getSuperAdminIds(), true);
    if ($isSuper) return true;

    $sameOrg = (int) $ann['organization_id'] === (int) $user['organization_id'];
    if (in_array($user['role'], ['management', 'supervisor'], true)
        && ($ann['organization_id'] === null || $sameOrg)) {
        return true;
    }

    if (!$ann['is_active']) return false;
    // زمان از خود پایگاه داده، مثل NOW() در list
    $now = $db->query("SELECT NOW()")->fetchColumn();
    if ($ann['publish_at'] > $now) return false;
    if ($ann['expire_at'] !== null && $ann['expire_at'] <= $now) return false;

    if ($ann['target_user_id'] !== null) return (int) $ann['target_user_id'] === (int) $user['id'];
    if ($ann['organization_id'] === null) return true;
    if (!$sameOrg) return false;
    if ($ann['target_section'] === null || $ann['target_section'] === '') return true;
    return in_array($ann['target_section'], us_getUserSections($db, (int) $user['id']), true);
}

/**
 * اعتبارسنجی یک فایل بارگذاری‌شده.
 * @return array{error: ?string, extension: string, mime: string}
 */
function annAttValidate(string $name, string $tmpPath, int $size, int $uploadError): array
{
    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $isVideo = in_array($extension, ['mp4', 'mov'], true);
    $tooBig = $isVideo ? 'حجم فایل ویدیویی نباید بیشتر از ۵۰ مگابایت باشد' : 'حجم فایل نباید بیشتر از ۲۰ مگابایت باشد';
    $fail = fn(string $error, string $mime = '') => ['error' => $error, 'extension' => $extension, 'mime' => $mime];

    if ($uploadError !== UPLOAD_ERR_OK) {
        return $fail(in_array($uploadError, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
            ? $tooBig
            : 'بارگذاری فایل کامل نشد. لطفا دوباره تلاش کنید.');
    }
    if ($size > ($isVideo ? ANN_ATT_MAX_SIZE_VIDEO : ANN_ATT_MAX_SIZE)) return $fail($tooBig);
    if (preg_match('/\.(php|phtml|phar|cgi|pl|py|sh|exe|js|html?)(\.|$)/i', $name)) return $fail('نوع فایل مجاز نیست');
    if (!in_array($extension, ANN_ATT_EXTENSIONS, true)) return $fail('فرمت فایل مجاز نیست');

    $mime = (string) mime_content_type($tmpPath);
    if (!in_array($mime, ANN_ATT_MIME_TYPES, true)) return $fail('نوع فایل مجاز نیست', $mime);

    // پسوند ویدیویی سقف بزرگ‌تری دارد، پس محتوا هم باید واقعا ویدیو باشد (و برعکس).
    // m4a ضبط‌شده در کانتینر MP4 را finfo به‌صورت video/mp4 تشخیص می‌دهد.
    $mimeIsVideo = in_array($mime, ['video/mp4', 'video/quicktime'], true);
    $m4aInMp4 = $extension === 'm4a' && $mime === 'video/mp4';
    if (($isVideo && !$mimeIsVideo && $mime !== 'audio/mp4') || (!$isVideo && $mimeIsVideo && !$m4aInMp4)) {
        return $fail('پسوند فایل با محتوای آن هم‌خوانی ندارد', $mime);
    }
    return ['error' => null, 'extension' => $extension, 'mime' => $mime];
}

/** حذف فایل‌های روی دیسک یک اطلاعیه. ردیف‌ها با حذف اطلاعیه (ON DELETE CASCADE) می‌روند */
function annAttStoredNames(PDO $db, int $announcementId): array
{
    $stmt = $db->prepare("SELECT stored_name FROM announcement_attachments WHERE announcement_id = ?");
    $stmt->execute([$announcementId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function annAttUnlink(string $storedName): void
{
    $path = annAttDir() . basename($storedName);
    if (is_file($path) && !unlink($path)) {
        error_log("announcement attachment unlink failed | file={$storedName}");
    }
}
