<?php
// api/requests/update.php

ob_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://bpm.computeryekta.com');
header('Access-Control-Allow-Methods: PUT, PATCH');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once '../RequestManager.php';

if (!in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'PATCH'])) {
    http_response_code(405);
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'متد غیرمجاز']);
    exit;
}

try {
    $userId = requireAuth();
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['request_type']) || empty($input['request_id'])) {
        http_response_code(400);
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'اطلاعات الزامی ارسال نشده']);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();
    $requestManager = new RequestManager($db);

    $type = $input['request_type'];
    $requestId = $input['request_id'];
    $action = $input['action'] ?? null;

    if ($action === 'cancel') {
        // لغو درخواست
        $result = $requestManager->cancelRequest($type, $requestId);
    } else {
        // به روزرسانی درخواست
        $updateData = [];
        
        if (isset($input['start_date'])) $updateData['start_date'] = $input['start_date'];
        if (isset($input['end_date'])) $updateData['end_date'] = $input['end_date'];
        if (isset($input['start_time'])) $updateData['start_time'] = $input['start_time'];
        if (isset($input['end_time'])) $updateData['end_time'] = $input['end_time'];
        if (isset($input['description'])) $updateData['description'] = $input['description'];
        if (isset($input['reason'])) $updateData['reason'] = $input['reason'];
        if (isset($input['substitute_id'])) $updateData['substitute_id'] = $input['substitute_id'];

        if (empty($updateData)) {
            http_response_code(400);
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'هیچ داده‌ای برای به روزرسانی وجود ندارد']);
            exit;
        }

        $result = $requestManager->updateRequest($type, $requestId, $updateData);
    }

    if ($result['success']) {
        http_response_code(200);
    } else {
        http_response_code(400);
    }

    ob_end_clean();
    echo json_encode($result);

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    error_log("Update request error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'خطای داخلی سرور'
    ]);
}

?>