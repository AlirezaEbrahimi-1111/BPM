<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  مهاجرتِ داده: تعیین پلنِ هر سازمان از روی اشتراک‌های قدیمی
 *
 *  قوانین (طبق مشخصاتِ تأییدشده):
 *   • ملاکِ انقضا همیشه end_date است، نه is_active.
 *   • اشتراکِ غیر-آزمایشی (monthly/yearly) با end_date ≥ امروز ← gold،
 *     plan_expires_at = end_date، plan_source = legacy_web_default.
 *   • بقیه ← free (بدون مهلتِ ۳۰ روزه؛ انقضا فوری).
 *   • اگر رکوردِ trial داشته باشد ← web_trial_used = 1؛ اگر trialِ آن
 *     هنوز تمام نشده، web_trial_ends_at = end_date (دسترسیِ وب تا همان روز).
 *   • plan_users برای gold از max_users: کوچک‌ترین سطحِ ۵/۱۰/۲۰/۴۰ که ≥ آن
 *     باشد؛ بیشتر از ۴۰ → ۴۰. max_users صفر/NULL → ۵ و هشدار در لاگ.
 *   • رکوردهای یتیم (organization_id بدونِ سازمان) مهاجرت نمی‌شوند؛ فقط لاگ.
 *   • idempotent: فقط سازمان‌هایی که plan_source آن‌ها هنوز NULL است پردازش
 *     می‌شوند؛ اجرای دوباره هیچ تغییری نمی‌دهد.
 *   • هر تغییر در organization_plan_audit ثبت می‌شود (قبل/بعد) تا قابل بازگشت باشد.
 * ═══════════════════════════════════════════════════════════════════
 */

return [

    'description' => 'Backfill organization plans from legacy subscriptions (idempotent, audited)',

    'up' => function (PDO $db) {
        $tehran = new DateTimeZone('Asia/Tehran');
        $now = new DateTime('now', $tehran);
        $today = $now->format('Y-m-d');
        $nowDt = $now->format('Y-m-d H:i:s');

        $ladder = function (?int $max): int {
            if ($max === null || $max <= 0) return 5;
            foreach ([5, 10, 20, 40] as $level) {
                if ($max <= $level) return $level;
            }
            return 40;
        };

        $orphans = $db->query("
            SELECT DISTINCT s.organization_id FROM subscriptions s
            WHERE s.organization_id NOT IN (SELECT id FROM organizations)
        ")->fetchAll(PDO::FETCH_COLUMN);
        if ($orphans) {
            error_log('organization_plans backfill: رکوردهای یتیمِ اشتراک (مهاجرت نشدند) برای سازمان‌های: ' . implode(',', $orphans));
        }

        $orgs = $db->query("SELECT id, max_users, plan, plan_users, plan_expires_at, web_trial_ends_at, web_trial_used, plan_source
                            FROM organizations WHERE plan_source IS NULL")->fetchAll(PDO::FETCH_ASSOC);

        $ownTx = !$db->inTransaction();
        if ($ownTx) $db->beginTransaction();
        try {
            foreach ($orgs as $org) {
                $orgId = (int) $org['id'];
                $old = [
                    'plan' => $org['plan'], 'plan_users' => (int) $org['plan_users'],
                    'plan_expires_at' => $org['plan_expires_at'], 'web_trial_ends_at' => $org['web_trial_ends_at'],
                    'web_trial_used' => (int) $org['web_trial_used'], 'plan_source' => $org['plan_source'],
                ];

                $subStmt = $db->prepare("SELECT id, plan_type, end_date FROM subscriptions WHERE organization_id = ? ORDER BY end_date DESC, id DESC");
                $subStmt->execute([$orgId]);
                $subs = $subStmt->fetchAll(PDO::FETCH_ASSOC);

                // ── تست وب (از رکوردهای trial) ─────────────────────────
                $webTrialUsed = 0;
                $webTrialEnds = null;
                foreach ($subs as $s) {
                    if ($s['plan_type'] !== 'trial') continue;
                    $webTrialUsed = 1;
                    $end = substr($s['end_date'], 0, 10);
                    if ($end >= $today && ($webTrialEnds === null || $end > $webTrialEnds)) {
                        $webTrialEnds = $end;
                    }
                }

                // ── اشتراکِ فعالِ غیر-آزمایشی ← gold ─────────────────────
                $paid = null;
                foreach ($subs as $s) {
                    if (!in_array($s['plan_type'], ['monthly', 'yearly'], true)) continue;
                    if (substr($s['end_date'], 0, 10) >= $today) { $paid = $s; break; } // ORDER BY end_date DESC
                }

                if ($paid) {
                    $plan = 'gold';
                    $planUsers = $ladder($org['max_users'] !== null ? (int) $org['max_users'] : null);
                    $planExpires = substr($paid['end_date'], 0, 10);
                    $db->prepare("UPDATE subscriptions SET plan = 'gold', plan_users = ?, duration_months = NULL WHERE id = ?")
                        ->execute([$planUsers, $paid['id']]);
                } else {
                    $plan = 'free';
                    $planUsers = 1;
                    $planExpires = null;
                }

                $new = [
                    'plan' => $plan, 'plan_users' => $planUsers,
                    'plan_expires_at' => $planExpires, 'web_trial_ends_at' => $webTrialEnds,
                    'web_trial_used' => $webTrialUsed, 'plan_source' => 'legacy_web_default',
                ];

                $db->prepare("
                    UPDATE organizations
                    SET plan = ?, plan_users = ?, plan_expires_at = ?, web_trial_ends_at = ?, web_trial_used = ?, plan_source = ?
                    WHERE id = ?
                ")->execute([
                    $new['plan'], $new['plan_users'], $new['plan_expires_at'], $new['web_trial_ends_at'],
                    $new['web_trial_used'], $new['plan_source'], $orgId,
                ]);

                $db->prepare("
                    INSERT INTO organization_plan_audit (organization_id, old_value, new_value, source, created_at)
                    VALUES (?, ?, ?, 'migration', ?)
                ")->execute([$orgId, json_encode($old), json_encode($new), $nowDt]);
            }
            if ($ownTx) $db->commit();
        } catch (Throwable $e) {
            if ($ownTx && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    },

    'down' => function (PDO $db) {
        // بازگردانیِ سازمان‌ها از روی مقدار قبلیِ ثبت‌شده در ممیزی
        $rows = $db->query("SELECT organization_id, old_value FROM organization_plan_audit WHERE source = 'migration' ORDER BY id")
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
                    $old['web_trial_ends_at'] ?? null, $old['web_trial_used'] ?? 0, $old['plan_source'] ?? null,
                    $r['organization_id'],
                ]);
            }
            $db->exec("DELETE FROM organization_plan_audit WHERE source = 'migration'");
            if ($ownTx) $db->commit();
        } catch (Throwable $e) {
            if ($ownTx && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    },

];
