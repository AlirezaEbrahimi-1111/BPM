<?php
if (!isset($_SESSION['_token'])) {
    $_SESSION['_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لیست تیکت‌ها</title>
    <link rel="stylesheet" href="modules/ticketing/assets/ticket.css">
</head>
<body>

<div class="ticket-container">

    <!-- Header -->
    <div class="ticket-header">
        <h1>تیکت‌های پشتیبانی</h1>
        <a href="?module=ticketing&action=create" class="btn btn-primary">
            ایجاد تیکت جدید
        </a>
    </div>

    <?php if (!empty($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($_SESSION['success']) ?>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['error'])): ?>
        <div class="alert alert-error">
            <?= htmlspecialchars($_SESSION['error']) ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Filters -->
    <div class="ticket-filters">
        <form method="get" class="filter-form">
            <input type="hidden" name="module" value="ticketing">
            <input type="hidden" name="action" value="index">

            <input 
                type="text" 
                name="search" 
                placeholder="جستجو در موضوع یا شماره تیکت..."
                value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
                class="filter-input"
            >

            <select name="status_id" class="filter-select">
                <option value="">همه وضعیت‌ها</option>
                <option value="1" <?= ($_GET['status_id'] ?? '') == '1' ? 'selected' : '' ?>>جدید</option>
                <option value="2" <?= ($_GET['status_id'] ?? '') == '2' ? 'selected' : '' ?>>در حال بررسی</option>
                <option value="3" <?= ($_GET['status_id'] ?? '') == '3' ? 'selected' : '' ?>>در انتظار پاسخ</option>
                <option value="4" <?= ($_GET['status_id'] ?? '') == '4' ? 'selected' : '' ?>>حل شده</option>
                <option value="5" <?= ($_GET['status_id'] ?? '') == '5' ? 'selected' : '' ?>>بسته شده</option>
            </select>

            <select name="priority_id" class="filter-select">
                <option value="">همه اولویت‌ها</option>
                <option value="1" <?= ($_GET['priority_id'] ?? '') == '1' ? 'selected' : '' ?>>کم</option>
                <option value="2" <?= ($_GET['priority_id'] ?? '') == '2' ? 'selected' : '' ?>>متوسط</option>
                <option value="3" <?= ($_GET['priority_id'] ?? '') == '3' ? 'selected' : '' ?>>بالا</option>
                <option value="4" <?= ($_GET['priority_id'] ?? '') == '4' ? 'selected' : '' ?>>فوری</option>
            </select>

            <button type="submit" class="btn btn-secondary">فیلتر</button>
            <a href="?module=ticketing&action=index" class="btn btn-ghost">پاک کردن</a>
        </form>
    </div>

    <!-- Tickets Table -->
    <div class="ticket-table-wrapper">
        <?php if (empty($tickets)): ?>
            <div class="empty-state">
                <p>هیچ تیکتی یافت نشد</p>
            </div>
        <?php else: ?>
            <table class="ticket-table">
                <thead>
                    <tr>
                        <th>شماره تیکت</th>
                        <th>موضوع</th>
                        <th>وضعیت</th>
                        <th>اولویت</th>
                        <th>تاریخ ایجاد</th>
                        <th>آخرین بروزرسانی</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $ticket): ?>
                        <tr class="ticket-row" onclick="window.location='?module=ticketing&action=view&id=<?= $ticket['id'] ?>'">
                            <td>
                                <span class="ticket-number"><?= htmlspecialchars($ticket['ticket_number']) ?></span>
                            </td>

                            <td>
                                <div class="ticket-subject">
                                    <?= htmlspecialchars($ticket['subject']) ?>
                                </div>
                            </td>

                            <td>
                                <span class="status-badge status-<?= (int)$ticket['status_id'] ?>">
                                    <?= htmlspecialchars($ticket['status_name']) ?>
                                </span>
                            </td>

                            <td>
                                <span class="priority priority-<?= (int)$ticket['priority_id'] ?>">
                                    <?= htmlspecialchars($ticket['priority_name']) ?>
                                </span>
                            </td>

                            <td>
                                <?= htmlspecialchars($ticket['created_at']) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($ticket['updated_at']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php
    $totalPages = ceil($total / $limit);
    if ($totalPages > 1):
    ?>
        <div class="pagination">

            <?php for ($i = 1; $i <= $totalPages; $i++): ?>

                <a
                    href="?module=ticketing&action=index&page=<?= $i ?>"
                    class="pagination-link <?= $page === $i ? 'active' : '' ?>"
                >
                    <?= $i ?>
                </a>

            <?php endfor; ?>

        </div>
    <?php endif; ?>

</div>

<script src="modules/ticketing/assets/ticket.js"></script>
</body>
</html>
