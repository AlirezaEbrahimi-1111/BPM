<?php
/**
 * Middleware برای احراز هویت و دسترسی
 * مسیر: /includes/middleware.php
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';

/**
 * احراز هویت الزامی
 * اگر ناموفق بود، HTTP 401 برگردند
 */
function requireAuth() {
    $database = new Database();
    $db = $database->getConnection();
    $auth = new Auth($db);
    
    $user_id = $auth->getUserFromToken();
    
    if (!$user_id) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized - Invalid or missing token'
        ]);
        exit;
    }
    
    return $user_id;
}

/**
 * دریافت اطلاعات کاربر
 */
function getUserInfo($user_id) {
    $database = new Database();
    $db = $database->getConnection();
    
    $stmt = $db->prepare("
        SELECT 
            id,
            organization_id,
            role,
            username,
            phone,
            first_name,
            last_name,
            email,
            activity_section,
            can_create_routine,
            can_create_workflow,
            is_active,
            created_at,
            updated_at
        FROM users
        WHERE id = ? AND is_active = 1
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * بررسی دسترسی به نقش خاص
 */
function requireRole($user_id, $required_roles = []) {
    $user = getUserInfo($user_id);
    
    if (!$user) {
        return false;
    }
    
    // اگر هیچ نقش مشخص نشده، فقط active بودن الزامی است
    if (empty($required_roles)) {
        return true;
    }
    
    // بررسی activity_section
    $allowed_sections = [
        'admin' => 'management',
        'manager' => ['sales', 'purchase', 'warehouse', 'technical', 'accounting'],
        'supervisor' => ['sales', 'purchase', 'warehouse', 'technical', 'accounting'],
        'employee' => ['sales', 'purchase', 'warehouse', 'technical', 'accounting', 'public']
    ];
    
    return in_array($user['activity_section'], $required_roles);
}

/**
 * Require specific role
 */
function requireSpecificRole($user_id, $required_role) {
    $user = getUserInfo($user_id);
    
    if (!$user) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Forbidden - User not found'
        ]);
        exit;
    }
    
    // For simplicity, we'll check activity_section
    $admin_sections = ['management', 'admin'];
    if ($required_role === 'admin' && !in_array($user['activity_section'], $admin_sections)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Forbidden - Insufficient permissions'
        ]);
        exit;
    }
    
    return true;
}

/**
 * Log API call
 */
function logAPICall($user_id, $endpoint, $method, $status_code, $details = []) {
    $database = new Database();
    $db = $database->getConnection();
    
    $log_data = [
        'user_id' => $user_id,
        'endpoint' => $endpoint,
        'method' => $method,
        'status_code' => $status_code,
        'ip_address' => $_SERVER['REMOTE_ADDR'],
        'details' => json_encode($details),
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    // اگر جدول api_logs موجود باشد، log کن
    try {
        $stmt = $db->prepare("
            INSERT INTO api_logs (user_id, endpoint, method, status_code, ip_address, details, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $log_data['user_id'],
            $log_data['endpoint'],
            $log_data['method'],
            $log_data['status_code'],
            $log_data['ip_address'],
            $log_data['details'],
            $log_data['created_at']
        ]);
    } catch (Exception $e) {
        // اگر جدول موجود نیست، نادیده بگیر
        error_log("API logging error: " . $e->getMessage());
    }
}

/**
 * Validate input
 */
function validateInput($data, $rules) {
    $errors = [];
    
    foreach ($rules as $field => $rule) {
        if ($rule === 'required' && empty($data[$field])) {
            $errors[$field] = "فیلد $field الزامی است";
        }
        
        if ($rule === 'email' && !filter_var($data[$field], FILTER_VALIDATE_EMAIL)) {
            $errors[$field] = "ایمیل نامعتبر است";
        }
        
        if ($rule === 'phone' && !preg_match('/^09\d{9}$/', $data[$field])) {
            $errors[$field] = "شماره موبایل نامعتبر است";
        }
        
        if (strpos($rule, 'min:') === 0) {
            $min = (int) substr($rule, 4);
            if (strlen($data[$field]) < $min) {
                $errors[$field] = "حداقل طول $min است";
            }
        }
        
        if (strpos($rule, 'max:') === 0) {
            $max = (int) substr($rule, 4);
            if (strlen($data[$field]) > $max) {
                $errors[$field] = "حداکثر طول $max است";
            }
        }
    }
    
    return $errors;
}

/**
 * Send JSON response
 */
function sendJSON($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
?>