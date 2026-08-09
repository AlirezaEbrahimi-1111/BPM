<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Tehran');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/error_config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/settings_helper.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/working-days-helper.php';

try {
    $database = new Database();
    $db = $database->getConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection error']);
    exit;
}

$auth = new Auth($db);

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id)
    $user_id = $auth->getUserFromToken();
if (!$user_id && isset($_COOKIE['auth_token']))
    $user_id = $auth->validateToken($_COOKIE['auth_token']);

if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'لطفاً وارد شوید']);
    exit;
}

$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$current_user = $stmt->fetch(PDO::FETCH_ASSOC);

$json = file_get_contents('php://input');
$data = json_decode($json, true);

$request_id = $data['id'] ?? null;
$request_type = $data['type'] ?? null;
$action = $data['action'] ?? 'approve';
$notes = $data['notes'] ?? null;

// ============================================
// بررسی مهلتِ تأیید — طبقِ تنظیماتِ واقعی (approval_deadline_days)، نه هاردکد،
// و با همان تابعِ مشترکِ روزِ کاری (includes/working-days-helper.php)
// ============================================
$approval_deadline_days = (int) getSetting($db, 'approval_deadline_days', 3);

// تعیین نام جدول بر اساس نوع
$table_map = [
    'mission' => 'mission_requests',
    'leave' => 'leave_requests',
    'forget' => 'forget_requests',
    'technical' => 'technical_issues',
];
$check_table = $table_map[$request_type] ?? null;

if ($check_table) {
    $stmt = $db->prepare("SELECT created_at FROM {$check_table} WHERE id = ?");
    $stmt->execute([$request_id]);
    $check_row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($check_row) {
        $created_date = substr($check_row['created_at'], 0, 10);
        $__org_id = (int) ($current_user['organization_id'] ?? 0);
        $holidays = getHolidaySet($db, $__org_id);
        $recurringWeekdays = getRecurringHolidayWeekdays($db, $__org_id);
        $working_days = countWorkingDaysBetween(new DateTime($created_date), new DateTime(date('Y-m-d')), $holidays, $recurringWeekdays);

        if ($working_days > $approval_deadline_days) {
            error_log("Attendance request approve denied (approval_deadline_days passed) | user_id={$user_id} | request_id={$request_id} | request_type={$request_type} | working_days={$working_days} | limit={$approval_deadline_days}");
            echo json_encode([
                'success' => false,
                'message' => "مهلت تأیید/رد این درخواست ({$approval_deadline_days} روز کاری) گذشته است."
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}


if (!$request_id || !$request_type) {
    echo json_encode(['success' => false, 'message' => 'اطلاعات ناقص است']);
    exit;
}

$tables = ['mission' => 'mission_requests', 'leave' => 'leave_requests', 'pass' => 'pass_requests', 'forget' => 'forget_requests', 'technical' => 'technical_issues'];
$type_labels = ['mission' => 'مأموریت', 'leave' => 'مرخصی', 'forget' => 'فراموشی ثبت', 'technical' => 'مشکل فنی', 'pass' => 'پاس'];

if (!isset($tables[$request_type])) {
    echo json_encode(['success' => false, 'message' => 'نوع درخواست نامعتبر است']);
    exit;
}

$table = $tables[$request_type];

try {
    $stmt = $db->prepare("SELECT * FROM {$table} WHERE id = ?");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        echo json_encode(['success' => false, 'message' => 'درخواست یافت نشد']);
        exit;
    }

    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$request['user_id']]);
    $request_owner = $stmt->fetch(PDO::FETCH_ASSOC);

    // بررسی آیا مدیر کاربر همان مسئول است
    // نکته: manager_code یک کدِ نمایشیِ بی‌ربط به id است (نه شناسه‌ی مدیر)؛
    // رابطه‌ی سلسله‌مراتبیِ معتبر فقط manager_id (FK واقعی) است.
    $owner_manager_id = $request_owner['manager_id'] ?? null;
    $manager_is_supervisor = false;

    if ($owner_manager_id) {
        $stmt = $db->prepare("SELECT is_supervisor FROM users WHERE id = ?");
        $stmt->execute([$owner_manager_id]);
        $manager_info = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($manager_info && $manager_info['is_supervisor'] == 1) {
            $manager_is_supervisor = true;
        }
    }

    $can_approve = false;
    $approver_role = null;
    $approval_field = null;
    $approver_id_field = null;
    $approver_date_field = null;
    $approver_notes_field = null;
    $is_final_approval = false;

    // ===== مرخصی =====
    if ($request_type === 'leave') {
        $stmt = $db->prepare("SELECT * FROM substitutes WHERE user_id = ? AND substitute_user_id = ? AND is_active = 1");
        $stmt->execute([$request['user_id'], $user_id]);
        $is_substitute = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($is_substitute && ($request['substitute_approval'] ?? 'pending') === 'pending') {
            $can_approve = true;
            $approver_role = 'substitute';
            $approval_field = 'substitute_approval';
            $approver_id_field = 'substitute_id';
            $approver_date_field = 'substitute_date';
            $approver_notes_field = 'substitute_notes';
        } else {
            $is_manager = ((int) $request_owner['manager_id'] === (int) $user_id);

            if ($is_manager && $request['substitute_approval'] === 'approved' && ($request['manager_approval'] ?? 'pending') === 'pending') {
                $can_approve = true;
                $approver_role = 'manager';
                $approval_field = 'manager_approval';
                $approver_id_field = 'manager_id';
                $approver_date_field = 'manager_date';
                $approver_notes_field = 'manager_notes';
                if ($manager_is_supervisor || $current_user['is_supervisor'] == 1) {
                    $is_final_approval = true;
                }
            } elseif (
                $current_user['role'] === 'manager'
                && $current_user['is_supervisor'] == 1
                && (int) $current_user['organization_id'] === (int) $request_owner['organization_id']
                && $request['manager_approval'] === 'approved'
                && ($request['supervisor_approval'] ?? 'pending') === 'pending'
            ) {
                $can_approve = true;
                $approver_role = 'supervisor';
                $approval_field = 'supervisor_approval';
                $approver_id_field = 'supervisor_id';
                $approver_date_field = 'supervisor_date';
                $approver_notes_field = 'supervisor_notes';
                $is_final_approval = true;
            }
        }
    }
    // ===== مأموریت =====
    elseif ($request_type === 'mission') {
        $is_manager = ((int) $request_owner['manager_id'] === (int) $user_id);

        if ($is_manager && ($request['manager_approval'] ?? 'pending') === 'pending') {
            $can_approve = true;
            $approver_role = 'manager';
            $approval_field = 'manager_approval';
            $approver_id_field = 'manager_id';
            $approver_date_field = 'manager_date';
            $approver_notes_field = 'manager_notes';
            if ($manager_is_supervisor || $current_user['is_supervisor'] == 1) {
                $is_final_approval = true;
            }
        } elseif (
            $current_user['role'] === 'manager'
            && $current_user['is_supervisor'] == 1
            && (int) $current_user['organization_id'] === (int) $request_owner['organization_id']
            && $request['manager_approval'] === 'approved'
            && ($request['supervisor_approval'] ?? 'pending') === 'pending'
        ) {
            $can_approve = true;
            $approver_role = 'supervisor';
            $approval_field = 'supervisor_approval';
            $approver_id_field = 'supervisor_id';
            $approver_date_field = 'supervisor_date';
            $approver_notes_field = 'supervisor_notes';
            $is_final_approval = true;
        }
    }
    // ===== فراموشی =====
    elseif ($request_type === 'forget') {
        $is_manager = ((int) $request_owner['manager_id'] === (int) $user_id);

        if ($is_manager && ($request['manager_approval'] ?? 'pending') === 'pending') {
            $can_approve = true;
            $approver_role = 'manager';
            $approval_field = 'manager_approval';
            $approver_id_field = 'manager_id';
            $approver_date_field = 'manager_date';
            $approver_notes_field = 'manager_notes';
            if ($manager_is_supervisor || $current_user['is_supervisor'] == 1) {
                $is_final_approval = true;
            }
        } elseif (
            ($current_user['role'] === 'manager' || $current_user['role'] === 'supervisor')
            && $current_user['is_supervisor'] == 1
            && (int) $current_user['organization_id'] === (int) $request_owner['organization_id']
            && $request['manager_approval'] === 'approved'
            && ($request['supervisor_approval'] ?? 'pending') === 'pending'
        ) {
            $can_approve = true;
            $approver_role = 'supervisor';
            $approval_field = 'supervisor_approval';
            $approver_id_field = 'supervisor_id';
            $approver_date_field = 'supervisor_date';
            $approver_notes_field = 'supervisor_notes';
            $is_final_approval = true;
        }
    }
    // ===== مشکل فنی =====
    elseif ($request_type === 'technical') {
        if (
            ($current_user['role'] === 'admin' || $current_user['is_supervisor'] == 1)
            && (int) $current_user['organization_id'] === (int) $request_owner['organization_id']
            && $request['status'] === 'pending'
        ) {
            $can_approve = true;
            $approver_role = 'admin';
            $is_final_approval = true;
        }
    }
    // ===== پاس =====
    elseif ($request_type === 'pass') {
        echo json_encode(['success' => false, 'message' => 'درخواست پاس نیازی به تأیید ندارد']);
        exit;
    }

    if (!$can_approve) {
        error_log("Attendance request approve denied (unauthorized approver) | user_id={$user_id} | request_id={$request_id} | request_type={$request_type}");
        echo json_encode(['success' => false, 'message' => 'شما مجاز به تأیید این درخواست نیستید']);
        exit;
    }

    $approval_status = ($action === 'approve') ? 'approved' : 'rejected';

    if ($request_type === 'technical') {
        $stmt = $db->prepare("UPDATE technical_issues SET status = ?, admin_id = ?, admin_date = NOW(), admin_notes = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$approval_status, $user_id, $notes, $request_id]);
    } else {
        $sql = "UPDATE {$table} SET {$approval_field} = ?, {$approver_id_field} = ?, {$approver_date_field} = NOW(), {$approver_notes_field} = ?, current_approver_role = ?, updated_at = NOW() WHERE id = ?";

        $next_role = null;
        if ($approval_status === 'approved' && !$is_final_approval) {
            if ($approver_role === 'substitute')
                $next_role = 'manager';
            elseif ($approver_role === 'manager')
                $next_role = 'supervisor';
        }

        $stmt = $db->prepare($sql);
        $stmt->execute([$approval_status, $user_id, $notes, $next_role, $request_id]);

        if ($approval_status === 'rejected') {
            $stmt = $db->prepare("UPDATE {$table} SET status = 'rejected', can_edit = 0, can_delete = 0 WHERE id = ?");
            $stmt->execute([$request_id]);

            // ✅ مرخصیِ ردشده: سهمیه‌ای که موقعِ ثبت کسر شده بود برمی‌گرده
            // (پاس هرگز از این مسیر رد نمی‌شه — بالاتر مستقیم بلاک شده)
            if ($request_type === 'leave') {
                require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/leave-balance-helper.php';
                $ded_amount = findLeaveDeduction($db, 'leave', (int) $request_id);
                if ($ded_amount !== null) {
                    $stmt = $db->prepare("SELECT user_id FROM leave_requests WHERE id = ?");
                    $stmt->execute([$request_id]);
                    $leave_owner_id = (int) $stmt->fetchColumn();
                    if ($leave_owner_id) {
                        $stmt = $db->prepare("
                            INSERT INTO leave_balance_transactions (user_id, type, amount, related_request_id, related_request_type, note)
                            VALUES (?, 'manual_adjustment', ?, ?, 'leave', 'بازگشتِ سهمیه به‌دلیلِ ردِ درخواست')
                        ");
                        $stmt->execute([$leave_owner_id, -$ded_amount, $request_id]);
                    }
                }
            }
        } elseif ($is_final_approval && $approval_status === 'approved') {
            if ($approver_role === 'manager' && ($manager_is_supervisor || $current_user['is_supervisor'] == 1)) {
                $stmt = $db->prepare("UPDATE {$table} SET status = 'approved', supervisor_approval = 'approved', supervisor_id = ?, supervisor_date = NOW(), can_edit = 0, can_delete = 0 WHERE id = ?");
                $stmt->execute([$user_id, $request_id]);
            } else {
                $stmt = $db->prepare("UPDATE {$table} SET status = 'approved', can_edit = 0, can_delete = 0 WHERE id = ?");
                $stmt->execute([$request_id]);
            }
        }
    }

    // ===== ارسال نوتیفیکیشن و پیامک به درخواست‌دهنده =====
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/Notification.php';

    $role_labels = ['substitute' => 'جانشین', 'manager' => 'مدیر', 'supervisor' => 'مسئول', 'admin' => 'ادمین'];

    if ($approval_status === 'approved') {
        if ($is_final_approval) {
            $notif_title = 'درخواست تأیید شد ✅';
            $notif_message = "درخواست {$type_labels[$request_type]} شما تأیید نهایی شد.";
            $notif_type = 'success';
        } else {
            $notif_title = "تأیید توسط {$role_labels[$approver_role]}";
            $notif_message = "درخواست {$type_labels[$request_type]} شما توسط {$role_labels[$approver_role]} تأیید شد.";
            $notif_type = 'info';
        }
    } else {
        $notif_title = 'درخواست رد شد ❌';
        $notif_message = "درخواست {$type_labels[$request_type]} شما توسط {$role_labels[$approver_role]} رد شد.";
        if ($notes)
            $notif_message .= "\nدلیل: {$notes}";
        $notif_type = 'danger';
    }

    // ✅ ارسال نوتیف + پیامک به درخواست‌کننده (از طریق کلاس Notification که داخلش SMS هم می‌فرسته)
    $notif = new Notification($db);
    $notif->create([
        'to_user_id' => $request_owner['id'],
        'title' => $notif_title,
        'message' => $notif_message,
        'type' => $notif_type,
        'related_type' => $request_type,
        'related_id' => $request_id,
        'link' => '/attendance_system/pages/requests.php'
    ]);

    // ===== ارسال نوتیفیکیشن و پیامک به نفر بعدی در زنجیره =====
    if ($approval_status === 'approved' && !$is_final_approval) {
        $next_approver_id = null;
        $next_role_label = '';
        $requester_name = trim(($request_owner['first_name'] ?? '') . ' ' . ($request_owner['last_name'] ?? ''));

        if ($request_type === 'leave') {
            if ($approver_role === 'substitute') {
                // بعدی: مدیر
                $next_approver_id = $request_owner['manager_id'];
                $next_role_label = 'مدیر';
            } elseif ($approver_role === 'manager') {
                // بعدی: مسئول = supervisorِ همان سازمان
                $stmt = $db->prepare("
                    SELECT id FROM users
                    WHERE is_supervisor = 1
                      AND is_active = 1
                      AND organization_id = ?
                    LIMIT 1
                ");
                $stmt->execute([$request_owner['organization_id']]);
                $mosavol = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($mosavol) {
                    $next_approver_id = $mosavol['id'];
                    $next_role_label = 'مسئول';
                }
            }
        } elseif (in_array($request_type, ['mission', 'forget'])) {
            if ($approver_role === 'manager') {
                // بعدی: مسئول (در همان سازمان)
                $stmt = $db->prepare("
                    SELECT id FROM users
                    WHERE is_supervisor = 1
                      AND is_active = 1
                      AND organization_id = ?
                    LIMIT 1
                ");
                $stmt->execute([$request_owner['organization_id']]);
                $mosavol = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($mosavol) {
                    $next_approver_id = $mosavol['id'];
                    $next_role_label = 'مسئول';
                }
            }
        }
        // مشکل فنی: نفر بعدی ندارد (supervisor مستقیم تأیید نهایی می‌کند)

        if ($next_approver_id) {
            $notif->create([
                'to_user_id' => $next_approver_id,
                'title' => "درخواست {$type_labels[$request_type]} منتظر تأیید شما",
                'message' => "درخواست {$type_labels[$request_type]} از {$requester_name} توسط {$role_labels[$approver_role]} تأیید شد و منتظر تأیید شما ({$next_role_label}) است.",
                'type' => 'warning',
                'related_type' => $request_type,
                'related_id' => $request_id,
                'link' => '/attendance_system/pages/requests.php?tab=pending-approvals'
            ]);
        }
    }

    $message = ($action === 'approve') ? 'درخواست با موفقیت تأیید شد' : 'درخواست رد شد';
    echo json_encode(['success' => true, 'message' => $message, 'role' => $approver_role, 'is_final' => $is_final_approval]);

} catch (Exception $e) {
    error_log("Approve request error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطا در پردازش درخواست']);
}
?>