<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ایجاد تیکت</title>

    <link rel="stylesheet" href="modules/ticketing/assets/ticket.css">
</head>
<body>

<div class="ticket-container">

    <div class="page-header">
        <h1>ایجاد تیکت جدید</h1>
    </div>

    <?php if (!empty($_SESSION['error'])): ?>
        <div class="alert alert-error">
            <?= htmlspecialchars($_SESSION['error']) ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <form
        method="POST"
        action="?module=ticketing&action=store"
        enctype="multipart/form-data"
        class="ticket-form"
    >

        <input
            type="hidden"
            name="_token"
            value="<?= $_SESSION['_token'] ?>"
        >

        <!-- Subject -->
        <div class="form-group">
            <label>عنوان تیکت</label>

            <input
                type="text"
                name="subject"
                required
                maxlength="255"
                class="form-control"
                placeholder="عنوان مشکل یا درخواست..."
            >
        </div>

        <!-- Category -->
        <div class="form-group">
            <label>دسته‌بندی</label>

            <select name="category_id" class="form-control">

                <option value="">انتخاب دسته‌بندی</option>

                <?php foreach ($categories as $category): ?>

                    <option value="<?= (int)$category['id'] ?>">
                        <?= htmlspecialchars($category['name']) ?>
                    </option>

                <?php endforeach; ?>

            </select>
        </div>

        <!-- Priority -->
        <div class="form-group">
            <label>اولویت</label>

            <select name="priority_id" class="form-control">

                <?php foreach ($priorities as $priority): ?>

                    <option
                        value="<?= (int)$priority['id'] ?>"
                        <?= (int)$priority['id'] === 2 ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars($priority['name']) ?>
                    </option>

                <?php endforeach; ?>

            </select>
        </div>

        <!-- Description -->
        <div class="form-group">
            <label>توضیحات</label>

            <textarea
                name="description"
                rows="8"
                required
                class="form-control"
                placeholder="شرح کامل مشکل یا درخواست..."
            ></textarea>
        </div>

        <!-- Attachments -->
        <div class="form-group">

            <label>پیوست فایل</label>

            <div class="upload-box">

                <input
                    type="file"
                    name="attachments[]"
                    multiple
                    id="attachments"
                    class="file-input"
                    accept=".jpg,.jpeg,.png,.pdf,.txt,.zip"
                >

                <label for="attachments" class="upload-label">
                    فایل‌ها را انتخاب کنید
                </label>

                <div id="file-list"></div>

            </div>

            <small class="help-text">
                فرمت‌های مجاز:
                JPG, PNG, PDF, TXT, ZIP
            </small>

        </div>

        <!-- Actions -->
        <div class="form-actions">

            <button type="submit" class="btn btn-primary">
                ثبت تیکت
            </button>

            <a
                href="?module=ticketing"
                class="btn btn-ghost"
            >
                بازگشت
            </a>

        </div>

    </form>

</div>

<script src="modules/ticketing/assets/ticket.js"></script>
</body>
</html>
