<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://bpm.computeryekta.com');

// چک کردن routine_id
if (empty($_GET['routine_id'])) {
    echo json_encode(['success' => false, 'message' => 'routine_id is required']);
    exit;
}

try {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
    require_once '../../includes/RoutineManager.php';
    
    $routine_id = intval($_GET['routine_id']);
    
    $database = new Database();
    $db = $database->getConnection();
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