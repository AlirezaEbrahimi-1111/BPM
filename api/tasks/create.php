<?php

ob_start(); // شروع output buffering — جلوگیری از خروجی ناخواسته قبل از JSON

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://bpm.computeryekta.com');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/cors.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/TaskManager.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/Notification.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'متد غیرمجاز']);
    exit;
}

try {
    $user_id = requireAuth();
    $input = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'فرمت JSON نامعتبر است']);
        exit;
    }

    // ─── اعتبارسنجی فیلدهای پایه ────────────────────────────────────────────
    if (empty($input['title'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'عنوان کار الزامی است']);
        exit;
    }

    if (!in_array($input['task_type'], ['periodic', 'continuous'])) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'نوع کار نامعتبر است']);
        exit;
    }

    if ($input['task_type'] === 'periodic' && empty($input['due_date'])) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'تاریخ انجام برای کارهای مقطعی الزامی است']);
        exit;
    }

    if ($input['task_type'] === 'continuous' && empty($input['start_date'])) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'تاریخ شروع برای کارهای دوره‌ای الزامی است']);
        exit;
    }

    // ─── اعتبارسنجی فیلدهای تمدید خودکار ───────────────────────────────────
    $auto_renew = !empty($input['auto_renew']) && $input['auto_renew'] == true;

    if ($auto_renew) {
        // تمدید خودکار فقط برای کارهای دوره‌ای با تاریخ پایان معنی دارد
        if ($input['task_type'] !== 'continuous') {
            ob_end_clean();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'تمدید خودکار فقط برای کارهای دوره‌ای قابل فعال‌سازی است']);
            exit;
        }

        if (empty($input['end_date'])) {
            ob_end_clean();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'برای فعال‌سازی تمدید خودکار، تاریخ پایان الزامی است']);
            exit;
        }

        if (empty($input['renew_duration']) || (int)$input['renew_duration'] <= 0) {
            ob_end_clean();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'مدت تمدید الزامی است و باید عددی مثبت باشد']);
            exit;
        }

        if (!in_array($input['renew_duration_unit'] ?? '', ['day', 'week', 'month'])) {
            ob_end_clean();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'واحد مدت تمدید نامعتبر است (day / week / month)']);
            exit;
        }

        if (!in_array($input['renew_behavior'] ?? '', ['extend', 'new_task'])) {
            ob_end_clean();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'رفتار تمدید نامعتبر است (extend / new_task)']);
            exit;
        }
    }

    // ─── اتصال به دیتابیس ────────────────────────────────────────────────────
    $database = new Database();
    $db = $database->getConnection();
    if (!$db) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'خطا در اتصال به پایگاه داده']);
        exit;
    }

    // دریافت organization_id از توکن
    $stmt = $db->prepare("SELECT organization_id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $userRow = $stmt->fetch(PDO::FETCH_ASSOC);
    $organization_id = $userRow['organization_id'] ?? null;

    // ─── افزودن فیلدهای تمدید به input قبل از ارسال به TaskManager ──────────
    if ($auto_renew) {
        $input['auto_renew']          = 1;
    } else {
        $input['auto_renew']          = 0;
        $input['renew_duration']      = null;
        $input['renew_duration_unit'] = null;
        $input['renew_behavior']      = 'extend'; // مقدار پیش‌فرض
    }

    $taskManager = new TaskManager($db);
    $result = $taskManager->createTask($input, $user_id, $organization_id);

    if ($result['success']) {
              $newTaskId = $result['task_id'];

        // ذخیرهٔ سیاست نمایش تاریخچه (تعریف‌کننده تعیین می‌کند)
        $shareHistory = array_key_exists('share_history', $input) ? (int)(!empty($input['share_history'])) : 1;
        try {
            $shStmt = $db->prepare("UPDATE tasks SET share_history = ? WHERE id = ?");
            $shStmt->execute([$shareHistory, $newTaskId]);
        } catch (Exception $e) {
            error_log("set share_history error: " . $e->getMessage());
        }

        // ─── کپی فایل‌های پیوست (اگر درخواست شده) ──────────────────────────
        if (!empty($input['copy_attachment_ids']) && is_array($input['copy_attachment_ids'])) {
            try {
                $placeholders = implode(',', array_fill(0, count($input['copy_attachment_ids']), '?'));
                $attStmt = $db->prepare(
                    "SELECT * FROM task_attachments WHERE id IN ($placeholders)"
                );
                $attStmt->execute($input['copy_attachment_ids']);
                $attachments = $attStmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($attachments as $att) {
                    $insertAtt = $db->prepare("
                        INSERT INTO task_attachments 
                            (task_id, file_name, file_original_name, file_path, file_type, file_size, uploaded_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $insertAtt->execute([
                        $newTaskId,
                        $att['file_name'],
                        $att['file_original_name'],
                        $att['file_path'],
                        $att['file_type'],
                        $att['file_size'],
                        $user_id
                    ]);
                }
            } catch (Exception $attError) {
                error_log("Copy attachments error: " . $attError->getMessage());
                // خطا در کپی فایل‌ها کار رو متوقف نمی‌کنه
            }
        }
        // ─── ارسال نوتیفیکیشن ────────────────────────────────────────────────
        try {
            $assignee_id = $input['assignee_id'] ?? $user_id;
            $title = $input['title'] ?? 'کار جدید';

            if ($assignee_id != $user_id) {
                $creatorStmt = $db->prepare("SELECT first_name, last_name, phone FROM users WHERE id = ?");
                $creatorStmt->execute([$user_id]);
                $creator = $creatorStmt->fetch(PDO::FETCH_ASSOC);
                $creator_name = trim(($creator['first_name'] ?? '') . ' ' . ($creator['last_name'] ?? ''));
                if (empty($creator_name)) {
                    $creator_name = $creator['phone'] ?? 'کاربر';
                }

                $notif = new Notification($db);
                $notif->create([
                    'to_user_id'   => $assignee_id,
                    'title'        => 'کار جدید برای شما',
                    'message'      => "یک کار جدید با عنوان «{$title}» توسط {$creator_name} برای شما ایجاد شد",
                    'type'         => 'info',
                    'link'         => "/pages/task-detail.php?id={$result['task_id']}",
                    'related_type' => 'task',
                    'related_id'   => $result['task_id'],
                    'sms_pattern' => 'task_created',
                    'sms_args' => [$title, $creator_name]
                ]);
            }
        } catch (Exception $notifError) {
            error_log("Notification error: " . $notifError->getMessage());
        }

        ob_clean();
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'کار با موفقیت ایجاد شد',
            'task_id' => $result['task_id'] ?? null
        ]);
        exit;

    } else {
        ob_end_clean();
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $result['message'] ?? 'خطا در ایجاد کار'
        ]);
        exit;
    }

} catch (Exception $e) {
    error_log("Create task error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());

    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'خطای داخلی سرور: ' . $e->getMessage()
    ]);
    exit;
}

ob_end_clean();
http_response_code(500);
echo json_encode(['success' => false, 'message' => 'خطای نامشخص']);
exit;