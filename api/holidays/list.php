<?php
ob_start();
header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';

try {
    $auth    = new Auth();
    $user_id = $auth->getUserFromToken();
    if (!$user_id) {
        ob_end_clean();
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'احراز هویت الزامی است']);
        exit;
    }

    $database = new Database();
    $db       = $database->getConnection();

    $me = loadUserForPermissions($db, (int) $user_id);
    $org_id = (int) ($me['organization_id'] ?? 0);
    // مجوزِ حذف: فقط برایِ نمایشِ دکمهٔ حذف در فرانت (تصمیمِ نهایی همیشه در delete.php دوباره چک می‌شه)
    $canDeleteOrgHolidays = isOrgWideRole($me);
    $canDeleteGlobalHolidays = ((int) $user_id === 1);

    // بازهٔ تاریخ: اگه فرانت مشخص کرده باشه همون، وگرنه پیش‌فرضِ ۱ ماهِ قبل تا ۶ ماهِ بعد
    $start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-1 month'));
    $end_date   = $_GET['end_date'] ?? date('Y-m-d', strtotime('+6 month'));

    // تعطیلاتِ یک‌روزه: سراسری (organization_id IS NULL) + مخصوصِ سازمانِ خودش
    $stmt = $db->prepare("
        SELECT id, holiday_date, title, organization_id
        FROM holidays
        WHERE type = 'date'
          AND holiday_date >= :start_date AND holiday_date <= :end_date
          AND (organization_id IS NULL OR organization_id = :org_id)
        ORDER BY holiday_date ASC
    ");
    $stmt->execute(['start_date' => $start_date, 'end_date' => $end_date, 'org_id' => $org_id]);
    $holidays = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $isGlobal = ($row['organization_id'] === null);
        $holidays[] = [
            'id'            => (int) $row['id'],
            'holiday_date'  => $row['holiday_date'],
            'title'         => $row['title'],
            'type'          => 'date',
            'is_global'     => $isGlobal,
            'can_delete'    => $isGlobal ? $canDeleteGlobalHolidays : $canDeleteOrgHolidays,
        ];
    }

    // تعطیلاتِ هفتگیِ تکرارشونده: قانون‌ها رو می‌خونیم، بعد در بازهٔ تاریخ باز می‌کنیم
    $stmt = $db->prepare("
        SELECT id, day_of_week, title, organization_id
        FROM holidays
        WHERE type = 'weekly'
          AND (organization_id IS NULL OR organization_id = :org_id)
    ");
    $stmt->execute(['org_id' => $org_id]);
    $weeklyRules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($weeklyRules)) {
        $cursor  = new DateTime($start_date);
        $endDt   = new DateTime($end_date);
        while ($cursor <= $endDt) {
            $dow = (int) $cursor->format('w');
            foreach ($weeklyRules as $rule) {
                if ((int) $rule['day_of_week'] === $dow) {
                    $isGlobal = ($rule['organization_id'] === null);
                    $holidays[] = [
                        'id'            => (int) $rule['id'],
                        'holiday_date'  => $cursor->format('Y-m-d'),
                        'title'         => $rule['title'],
                        'type'          => 'weekly',
                        'day_of_week'   => $dow,
                        'is_global'     => $isGlobal,
                        'can_delete'    => $isGlobal ? $canDeleteGlobalHolidays : $canDeleteOrgHolidays,
                    ];
                }
            }
            $cursor->modify('+1 day');
        }
        usort($holidays, fn($a, $b) => strcmp($a['holiday_date'], $b['holiday_date']));
    }

    ob_clean();
    echo json_encode(['success' => true, 'holidays' => $holidays], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    error_log("holidays/list.php failed | user_id=" . ($user_id ?? 'null') . " | " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}