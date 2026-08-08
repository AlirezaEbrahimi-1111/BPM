<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://bpm.computeryekta.com');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';

// این endpoint قبلاً کاملاً بدونِ احرازِ هویت بود — هر کسی با حدس‌زدنِ
// routine_id می‌تونست مراحلِ روتینِ هر سازمانی رو ببینه. هم‌راستا با
// routines-all.php (که همین داده رو لیست می‌کنه)، همون سطحِ دسترسی اعمال می‌شه
$user_id = requireAuth();
$database = new Database();
$db = $database->getConnection();
$currentUser = loadUserForPermissions($db, $user_id);
requirePermission($currentUser, 'monitor_all_workflows');

// چک کردن routine_id
if (empty($_GET['routine_id'])) {
    echo json_encode(['success' => false, 'message' => 'routine_id is required']);
    exit;
}

try {
    require_once '../../includes/RoutineManager.php';

    $routine_id = intval($_GET['routine_id']);

    $routineManager = new RoutineManager($db);

    $steps = $routineManager->getRoutineSteps($routine_id);
    
    echo json_encode([
        'success' => true, 
        'steps' => $steps,
        'count' => count($steps)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage(),
        'file' => __FILE__
    ]);
}
?>