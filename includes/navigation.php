<?php
function renderNavigation($currentPage = '') {
    $user = getUserInfo($_SESSION['user_id'] ?? null);
    $isManager = ($user['activity_section'] === 'management');
    
    $menuItems = [
        'dashboard.php' => ['icon' => 'house-door', 'title' => 'داشبورد'],
        'create-task.php' => ['icon' => 'plus-circle', 'title' => 'ایجاد کار جدید'],
        'my-tasks.php' => ['icon' => 'person-check', 'title' => 'کارهای من'],
        'tasks.php' => ['icon' => 'list-task', 'title' => 'همه کارها'],
        'routine-tracking.php' => ['icon' => 'arrow-repeat', 'title' => 'کارهای روتین'],
    ];
    
    // منوی مدیریت فقط برای مدیران
    if ($isManager) {
        $menuItems['routine-templates.php'] = ['icon' => 'diagram-3', 'title' => 'الگوهای روتین'];
    }
    
    $menuItems = array_merge($menuItems, [
        'reports.php' => ['icon' => 'file-text', 'title' => 'گزارش‌گیری'],
        'settings.php' => ['icon' => 'gear', 'title' => 'تنظیمات']
    ]);
    
    $html = '<ul class="nav flex-column py-3">';
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
    $html .= '</ul>';
    
    return $html;
}
?>