<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  مهاجرت: پلن رایگان ۱۴ روزه (تصمیم ۱۴۰۵/۰۸/۱۳)
 * ───────────────────────────────────────────────────────────────────
 *  دوره‌ی رایگان = همان تست وب (organizations.web_trial_ends_at). سازمانِ بدون
 *  پلن پولیِ فعال که هیچ تاریخی ندارد، از «تاریخ ثبت‌نام + ۱۴ روز» می‌گیرد
 *  (طبق تصمیم: بیشترشان همین الان منقضی می‌شوند → وب plan-required.php،
 *  API کد PLAN_EXPIRED — includes/plan-access.php::planEnforceActive).
 *  سازمانی که تاریخِ تست دارد دست نمی‌خورد. هر تغییر در organization_plan_audit.
 * ═══════════════════════════════════════════════════════════════════
 */

return [

    'description' => 'Free plan = 14 days: backfill web_trial_ends_at = created_at + 14 days for free orgs without a date',

    'up' => function (PDO $db) {
        $rows = $db->query("
            SELECT id, DATE_FORMAT(DATE_ADD(DATE(created_at), INTERVAL 14 DAY), '%Y-%m-%d') AS ends
            FROM organizations
            WHERE web_trial_ends_at IS NULL
              AND NOT (plan IN ('silver', 'gold') AND (plan_expires_at IS NULL OR plan_expires_at > CURDATE()))
        ")->fetchAll(PDO::FETCH_ASSOC);

        $now = (new DateTime('now', new DateTimeZone('Asia/Tehran')))->format('Y-m-d H:i:s');
        $upd = $db->prepare("UPDATE organizations SET web_trial_ends_at = ? WHERE id = ? AND web_trial_ends_at IS NULL");
        $aud = $db->prepare("
            INSERT INTO organization_plan_audit (organization_id, old_value, new_value, source, created_at)
            VALUES (?, ?, ?, 'migration', ?)
        ");
        foreach ($rows as $r) {
            $upd->execute([$r['ends'], $r['id']]);
            $aud->execute([
                $r['id'],
                json_encode(['web_trial_ends_at' => null]),
                json_encode(['web_trial_ends_at' => $r['ends'], 'reason' => 'free_plan_14_days']),
                $now,
            ]);
        }
    },

    'down' => function (PDO $db) {
        // فقط همان‌هایی که این مهاجرت پر کرد (طبق audit) و هنوز دست نخورده‌اند
        $db->exec("
            UPDATE organizations o
            JOIN organization_plan_audit a
              ON a.organization_id = o.id AND a.source = 'migration'
             AND JSON_UNQUOTE(JSON_EXTRACT(a.new_value, '$.reason')) = 'free_plan_14_days'
            SET o.web_trial_ends_at = NULL
            WHERE o.web_trial_ends_at = JSON_UNQUOTE(JSON_EXTRACT(a.new_value, '$.web_trial_ends_at'))
        ");
    },

];
