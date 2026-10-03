<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  تصمیم دستیِ مالک (ثبت‌شده با plan_source = admin_manual):
 *   • سازمان ۲۸ (تست و فعال، ۵۰ کاربر): طلایی، سقف ۴۰، تا پایان تاریخ
 *     فعلیِ تست (آخرین end_date تستش).
 *   • بقیه‌ی سازمان‌های تستیِ فعال: طلایی، سقف طبق max_users (ladder)،
 *     تا پایان تاریخ فعلیِ تستشون.
 *
 *  این مهاجرت بعد از مهاجرت خودکار (…_backfill_organization_plans) اجرا می‌شود.
 *  فقط سازمان‌هایی را تغییر می‌دهد که هنوز به‌صورت legacy رایگان هستند؛
 *  اجرای دوباره هیچ تغییری نمی‌دهد. تغییرات در organization_plan_audit ثبت می‌شوند.
 * ═══════════════════════════════════════════════════════════════════
 */

return [

    'description' => 'Owner decision: gold until current trial end for test orgs; org 28 capped at 40 (admin_manual, audited)',

    'up' => function (PDO $db) {
        $tehran = new DateTimeZone('Asia/Tehran');
        $now = new DateTime('now', $tehran);
        $nowDt = $now->format('Y-m-d H:i:s');

        $ladder = function (?int $max): int {
            if ($max === null || $max <= 0) return 5;
            foreach ([5, 10, 20, 40] as $level) {
                if ($max <= $level) return $level;
            }
            return 40;
        };

        $overrides = [10, 11, 13, 25, 26, 28, 39, 44, 45, 46];
        $cap28 = 40;

        $ownTx = !$db->inTransaction();
        if ($ownTx) $db->beginTransaction();
        try {
            foreach ($overrides as $orgId) {
                $o = $db->prepare("SELECT id, max_users, plan, plan_users, plan_expires_at, web_trial_ends_at, web_trial_used, plan_source FROM organizations WHERE id = ?");
                $o->execute([$orgId]);
                $org = $o->fetch(PDO::FETCH_ASSOC);
                if (!$org) { error_log("manual_gold: سازمان {$orgId} یافت نشد"); continue; }
                if ($org['plan_source'] !== 'legacy_web_default' || $org['plan'] !== 'free') {
                    continue; // قبلاً تصمیم گرفته شده؛ idempotent
                }

                $endStmt = $db->prepare("SELECT MAX(end_date) FROM subscriptions WHERE organization_id = ? AND plan_type = 'trial'");
                $endStmt->execute([$orgId]);
                $expires = $endStmt->fetchColumn();
                if (!$expires) { error_log("manual_gold: سازمان {$orgId} تاریخ انقضای تست ندارد — رد شد"); continue; }
                $expires = substr($expires, 0, 10);
                if ($expires < $now->format('Y-m-d')) {
                    error_log("manual_gold: سازمان {$orgId} تستش از {$expires} تمام شده — طلایی نشد");
                    continue;
                }

                $planUsers = ($orgId === 28) ? $cap28 : $ladder($org['max_users'] !== null ? (int) $org['max_users'] : null);

                $old = [
                    'plan' => $org['plan'], 'plan_users' => (int) $org['plan_users'],
                    'plan_expires_at' => $org['plan_expires_at'], 'web_trial_ends_at' => $org['web_trial_ends_at'],
                    'web_trial_used' => (int) $org['web_trial_used'], 'plan_source' => $org['plan_source'],
                ];
                $new = [
                    'plan' => 'gold', 'plan_users' => $planUsers,
                    'plan_expires_at' => $expires, 'web_trial_ends_at' => $org['web_trial_ends_at'],
                    'web_trial_used' => (int) $org['web_trial_used'], 'plan_source' => 'admin_manual',
                ];

                $db->prepare("
                    UPDATE organizations
                    SET plan = 'gold', plan_users = ?, plan_expires_at = ?, plan_source = 'admin_manual'
                    WHERE id = ?
                ")->execute([$planUsers, $expires, $orgId]);

                $db->prepare("
                    INSERT INTO organization_plan_audit (organization_id, old_value, new_value, source, created_at)
                    VALUES (?, ?, ?, 'admin_manual', ?)
                ")->execute([$orgId, json_encode($old), json_encode($new), $nowDt]);
            }
            if ($ownTx) $db->commit();
        } catch (Throwable $e) {
            if ($ownTx && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    },

    'down' => function (PDO $db) {
        $rows = $db->query("SELECT organization_id, old_value FROM organization_plan_audit WHERE source = 'admin_manual' ORDER BY id")
            ->fetchAll(PDO::FETCH_ASSOC);
        $ownTx = !$db->inTransaction();
        if ($ownTx) $db->beginTransaction();
        try {
            foreach ($rows as $r) {
                $old = json_decode($r['old_value'], true) ?: [];
                $db->prepare("
                    UPDATE organizations
                    SET plan = ?, plan_users = ?, plan_expires_at = ?, web_trial_ends_at = ?, web_trial_used = ?, plan_source = ?
                    WHERE id = ?
                ")->execute([
                    $old['plan'] ?? 'free', $old['plan_users'] ?? 1, $old['plan_expires_at'] ?? null,
                    $old['web_trial_ends_at'] ?? null, $old['web_trial_used'] ?? 0, $old['plan_source'] ?? 'legacy_web_default',
                    $r['organization_id'],
                ]);
            }
            $db->exec("DELETE FROM organization_plan_audit WHERE source = 'admin_manual'");
            if ($ownTx) $db->commit();
        } catch (Throwable $e) {
            if ($ownTx && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    },

];
