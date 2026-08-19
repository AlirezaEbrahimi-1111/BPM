<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/middleware.php';

// ✅ دریافت user_id از middleware
$user_id = requireAuth();

// ✅ دریافت اطلاعات کاربر
$current_user = getUserInfo($user_id);

// ✅ اگه اطلاعات کاربر نیومد، ریدایرکت
if (!$current_user) {
    header('Location: ../index.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>داشبورد</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Tahoma', sans-serif;
            background: #f5f5f5;
        }

        .header {
            background: #8e57fe;
            color: white;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logout-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 8px 20px;
            border: 1px solid white;
            border-radius: 9px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
        }

        .logout-btn:hover {
            background: white;
            color: #8e57fe;
        }

        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }

        .card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: transform 0.3s;
            text-decoration: none;
            color: #333;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
        }

        .card-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }

        .card h3 {
            margin-bottom: 10px;
            color: #8e57fe;
        }

        .card p {
            color: #666;
            font-size: 14px;
        }
    </style>
</head>

<body>
    <?php include '../pages/header.php'; ?>

    <div class="header">
        <div class="header-content">
            <h1>سیستم مدیریت CRM</h1>
            <div class="user-info">
                <span>👤 <?= htmlspecialchars($current_user['first_name'] . ' ' . $current_user['last_name']) ?></span>
                <a href="logout.php" class="logout-btn">خروج</a>
            </div>
        </div>
    </div>

    <div class="container">
        <h2>📱 مدیریت پیامک</h2>

        <div class="dashboard-grid">
            <a href="sms-templates.php" class="card">
                <div class="card-icon">📝</div>
                <h3>مدیریت الگوها</h3>
                <p>ایجاد و ویرایش الگوهای پیامکی</p>
            </a>

            <a href="sms-logs.php" class="card">
                <div class="card-icon">📊</div>
                <h3>گزارش ارسال</h3>
                <p>مشاهده لاگ و آمار پیامک‌ها</p>
            </a>

            <a href="sms-test-direct.php" class="card">
                <div class="card-icon">🧪</div>
                <h3>تست ارسال</h3>
                <p>ارسال پیامک تستی</p>
            </a>
        </div>
    </div>
</body>

</html>