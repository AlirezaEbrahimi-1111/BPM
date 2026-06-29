<!-- views/view.php -->
<?php

echo $_SERVER['DOCUMENT_ROOT'];

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', __DIR__ . '/../../../');
}

require_once __DIR__ . '/../../../pages/header.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/error_config.php';


if (!defined('BASEPATH')) {
    define('BASEPATH', true);
}
require_once __DIR__ . '/../../../includes/version.php';

$ticket = $ticket ?? [];
$messages = $messages ?? [];
$attachments = $attachments ?? [];
$history = $history ?? [];
function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function badgeClass($type, $value)
{
    $map = [
        'status' => [
            'open' => 'success',
            'pending' => 'warning',
            'answered' => 'info',
            'closed' => 'secondary',
        ],
        'priority' => [
            'low' => 'success',
            'medium' => 'primary',
            'high' => 'warning',
            'urgent' => 'danger',
        ]
    ];

    return $map[$type][$value] ?? 'secondary';
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>جزئیات تیکت</title>

<link rel="stylesheet" href="<?= asset('/assets/css/bootstrap.min.css') ?>">
<link rel="stylesheet" href="<?= asset('/assets/js/cdn/bootstrap-icons.css') ?>">
<link rel="stylesheet" href="<?= asset('/modules/ticketing/assets/ticket.css') ?>">
    
</head>
<body>

<div class="ticket-page">

    <div class="ticket-page-header">
        <div>
            <h1>جزئیات تیکت</h1>
            <p class="ticket-number">
                <?php echo e($ticket['ticket_number'] ?? '-'); ?>
            </p>
        </div>

        <div class="header-actions">
            <a href="routes.php?action=index" class="btn btn-secondary">
                <i class="bi bi-arrow-right"></i>
                بازگشت
            </a>
        </div>
    </div>

    <?php if (!empty($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <?php
            echo e($_SESSION['success']);
            unset($_SESSION['success']);
            ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <?php
            echo e($_SESSION['error']);
            unset($_SESSION['error']);
            ?>
        </div>
    <?php endif; ?>

    <!-- اطلاعات تیکت -->
    <div class="ticket-card">

        <div class="ticket-card-header">
            <h2>
                <?php echo e($ticket['subject'] ?? '-'); ?>
            </h2>
        </div>

        <div class="ticket-meta-grid">

            <div class="meta-item">
                <span class="meta-label">وضعیت</span>

                <span class="badge badge-<?php echo badgeClass('status', $ticket['status_key'] ?? 'open'); ?>">
                    <?php echo e($ticket['status_name'] ?? '-'); ?>
                </span>
            </div>

            <div class="meta-item">
                <span class="meta-label">اولویت</span>

                <span class="badge badge-<?php echo badgeClass('priority', $ticket['priority_key'] ?? 'medium'); ?>">
                    <?php echo e($ticket['priority_name'] ?? '-'); ?>
                </span>
            </div>

            <div class="meta-item">
                <span class="meta-label">دسته‌بندی</span>
                <span><?php echo e($ticket['category_name'] ?? '-'); ?></span>
            </div>

            <div class="meta-item">
                <span class="meta-label">ایجاد کننده</span>
                <span><?php echo e($ticket['creator_name'] ?? '-'); ?></span>
            </div>

            <div class="meta-item">
                <span class="meta-label">تاریخ ایجاد</span>
                <span><?php echo e($ticket['created_at'] ?? '-'); ?></span>
            </div>

            <div class="meta-item">
                <span class="meta-label">آخرین بروزرسانی</span>
                <span><?php echo e($ticket['updated_at'] ?? '-'); ?></span>
            </div>

        </div>

    </div>

    <!-- مکالمات -->
    <div class="ticket-card">

        <div class="ticket-card-header">
            <h3>مکالمه</h3>
        </div>

        <div class="conversation-wrapper">

            <?php if (empty($messages)): ?>

                <div class="empty-state">
                    هنوز پیامی ثبت نشده است.
                </div>

            <?php else: ?>

                <?php foreach ($messages as $message): ?>

                    <div class="message-item <?php echo !empty($message['is_internal']) ? 'internal-note' : ''; ?>">

                        <div class="message-header">

                            <div class="message-author">
                                <i class="bi bi-person-circle"></i>

                                <?php echo e($message['user_name'] ?? 'کاربر'); ?>

                                <?php if (!empty($message['is_internal'])): ?>
                                    <span class="internal-badge">
                                        یادداشت داخلی
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="message-date">
                                <?php echo e($message['created_at'] ?? '-'); ?>
                            </div>

                        </div>

                        <div class="message-content">
                            <?php echo nl2br(e($message['message'] ?? '')); ?>
                        </div>

                        <?php if (!empty($message['attachments'])): ?>

                            <div class="message-attachments">

                                <?php foreach ($message['attachments'] as $file): ?>

                                    <a
                                        href="<?php echo e($file['file_path']); ?>"
                                        class="attachment-item"
                                        target="_blank"
                                    >
                                        <i class="bi bi-paperclip"></i>

                                        <?php echo e($file['original_name']); ?>
                                    </a>

                                <?php endforeach; ?>

                            </div>

                        <?php endif; ?>

                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

    </div>

    <!-- پیوست‌های کلی -->
    <div class="ticket-card">

        <div class="ticket-card-header">
            <h3>پیوست‌ها</h3>
        </div>

        <?php if (empty($attachments)): ?>

            <div class="empty-state">
                فایلی پیوست نشده است.
            </div>

        <?php else: ?>

            <div class="attachments-grid">

                <?php foreach ($attachments as $file): ?>

                    <a
                        href="<?php echo e($file['file_path']); ?>"
                        target="_blank"
                        class="attachment-item"
                    >
                        <i class="bi bi-file-earmark-arrow-down"></i>

                        <span>
                            <?php echo e($file['original_name']); ?>
                        </span>
                    </a>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </div>

    <!-- تاریخچه -->
    <div class="ticket-card">

        <div class="ticket-card-header">
            <h3>تاریخچه تغییرات</h3>
        </div>

        <?php if (empty($history)): ?>

            <div class="empty-state">
                تاریخچه‌ای ثبت نشده است.
            </div>

        <?php else: ?>

            <div class="history-list">

                <?php foreach ($history as $item): ?>

                    <div class="history-item">

                        <div class="history-icon">
                            <i class="bi bi-clock-history"></i>
                        </div>

                        <div class="history-content">

                            <div class="history-text">
                                <?php echo e($item['action_text'] ?? '-'); ?>
                            </div>

                            <div class="history-meta">
                                <?php echo e($item['user_name'] ?? '-'); ?>
                                -
                                <?php echo e($item['created_at'] ?? '-'); ?>
                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </div>

    <!-- فرم پاسخ -->
    <div class="ticket-card">

        <div class="ticket-card-header">
            <h3>پاسخ به تیکت</h3>
        </div>

        <form
            action="routes.php?action=reply&id=<?php echo (int)$ticket['id']; ?>"
            method="POST"
            enctype="multipart/form-data"
            id="replyForm"
        >

            <input
                type="hidden"
                name="_token"
                value="<?php echo e($_SESSION['_token'] ?? ''); ?>"
            >

            <div class="form-group">

                <label for="message">
                    متن پاسخ
                </label>

                <textarea
                    name="message"
                    id="message"
                    class="form-control"
                    rows="6"
                    required
                ></textarea>

            </div>

            <div class="form-group">

                <label class="checkbox-wrapper">

                    <input
                        type="checkbox"
                        name="is_internal"
                        value="1"
                    >

                    <span>
                        یادداشت داخلی
                    </span>

                </label>

            </div>

            <div class="form-group">

                <label for="attachments">
                    پیوست فایل
                </label>

                <input
                    type="file"
                    name="attachments[]"
                    id="attachments"
                    class="form-control"
                    multiple
                >

                <small class="form-hint">
                    فرمت‌های مجاز:
                    jpg, jpeg, png, pdf, zip, rar, doc, docx
                </small>

                <div id="selectedFiles"></div>

            </div>

            <div class="form-actions">

                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-send"></i>
                    ثبت پاسخ
                </button>

            </div>

        </form>

    </div>

</div>

<script src="<?= asset('/assets/js/cdn/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= asset('/modules/ticketing/assets/ticket.js') ?>"></script>
</body>
</html>
