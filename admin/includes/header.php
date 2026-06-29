<?php
// ==================================================
// admin/includes/header.php
// ==================================================

// اگر $current_user تعریف نشده، خودمون بگیریمش
if (!isset($current_user)) {
    if (!function_exists('getUserInfo')) {
        require_once __DIR__ . '/../../includes/middleware.php';
    }
    
    // اگر $user_id هم نداریم، از requireAuth بگیریم
    if (!isset($user_id)) {
        $user_id = requireAuth();
    }
    
    $current_user = getUserInfo($user_id);
}
?>

<style>
.admin-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 15px 0;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
.admin-header-content {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.admin-nav {
    display: flex;
    gap: 5px;
}
.admin-nav a {
    color: white;
    text-decoration: none;
    padding: 8px 15px;
    border-radius: 5px;
    transition: background 0.3s;
    font-size: 14px;
}
.admin-nav a:hover {
    background: rgba(255,255,255,0.2);
}
.user-section {
    display: flex;
    align-items: center;
    gap: 20px;
}
.logout-link {
    background: rgba(255,255,255,0.2);
    padding: 8px 20px;
    border-radius: 5px;
    color: white;
    text-decoration: none;
    transition: all 0.3s;
}
.logout-link:hover {
    background: white;
    color: #667eea;
}
</style>

<div class="admin-header">
    <div class="admin-header-content">
        <div class="admin-nav">
            <a href="dashboard.php">🏠 داشبورد</a>
            <a href="sms-templates.php">📝 الگوها</a>
            <a href="sms-logs.php">📊 گزارش‌ها</a>
            <a href="sms-test-direct.php">🧪 تست</a>
        </div>
        <div class="user-section">
            <span>👤 <?= htmlspecialchars($current_user['first_name'] . ' ' . $current_user['last_name']) ?></span>
            <a href="logout.php" class="logout-link">خروج</a>
        </div>
    </div>
</div>