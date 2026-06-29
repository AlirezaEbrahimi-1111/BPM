<?php
if (!function_exists('getUserInfo')) {
function getUserInfo($user_id) {
    $database = new Database();
    $db = $database->getConnection();
    
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}
}

function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $script = $_SERVER['SCRIPT_NAME'];
    $path = dirname($script);
    
    return $protocol . $host . $path . '/';
}

function getPageUrl($page) {
    return getBaseUrl() . $page;
}
function formatPersianDate($dateString) {
    if (!$dateString) return 'نامشخص';
    
    // تبدیل به تاریخ شمسی
    $date = new DateTime($dateString);
    return $date->format('Y/m/d'); // یا از کتابخانه تاریخ شمسی استفاده کنید
}

function renderNavigation($currentPage, $user) {
    $isManager = ($user['activity_section'] === 'management');
    
    $menuItems = [
        'dashboard.php' => ['icon' => 'house-door', 'title' => 'داشبورد'],
        'create-task.php' => ['icon' => 'plus-circle', 'title' => 'ایجاد کار جدید'],
        'my-tasks.php' => ['icon' => 'person-check', 'title' => 'کارهای من'],
        'tasks.php' => ['icon' => 'list-task', 'title' => 'همه کارها'],
        'routine-tracking.php' => ['icon' => 'arrow-repeat', 'title' => 'کارهای روتین'],
    ];
    
    if ($isManager) {
        $menuItems['routine-templates.php'] = ['icon' => 'diagram-3', 'title' => 'الگوهای روتین'];
    }
    
    $menuItems = array_merge($menuItems, [
        'reports.php' => ['icon' => 'file-text', 'title' => 'گزارش‌گیری'],
        'settings.php' => ['icon' => 'gear', 'title' => 'تنظیمات']
    ]);
    
    $html = '';
    foreach ($menuItems as $page => $item) {
        $isActive = ($currentPage === $page) ? 'active' : '';
        $html .= sprintf(
            '<li class="nav-item">
                <a class="nav-link %s" href="%s">
                    <i class="bi bi-%s"></i>%s
                </a>
            </li>',
            $isActive, $page, $item['icon'], $item['title']
        );
    }
    
    return $html;
}
?>