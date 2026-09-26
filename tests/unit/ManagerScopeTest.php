<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  تست واحد: دامنهٔ «مدیر» (getSubordinateIds / canManageTargetUser)
 *  محل: /tests/unit/ManagerScopeTest.php
 * ───────────────────────────────────────────────────────────────────
 *  مدل سه‌نقشی:
 *    • supervisor/admin → کل سازمان
 *    • manager          → فقط خودش + زیرمجموعه‌اش (زنجیرهٔ manager_id،
 *                          با هر عمقی — نه فقط زیردست مستقیم)
 *    • employee         → فقط خودش
 *
 *  ⚠️ برخلاف بقیهٔ تست‌های این پوشه، این فایل به یک PDO (SQLite
 *     درون‌حافظه‌ای) نیاز دارد چون getSubordinateIds() کوئری واقعی
 *     می‌زند. هیچ وابستگی‌ای به دیتابیس/کانفیگ واقعی پروژه ندارد.
 * ═══════════════════════════════════════════════════════════════════
 */

require_once dirname(__DIR__, 2) . '/includes/permissions.php';

function msdb(): PDO
{
    $db = new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("CREATE TABLE users (
        id INTEGER PRIMARY KEY,
        role TEXT,
        organization_id INTEGER,
        manager_id INTEGER,
        is_deleted INTEGER DEFAULT 0
    )");
    return $db;
}

function msSeed(PDO $db, int $id, string $role, int $org, ?int $managerId = null, int $isDeleted = 0): void
{
    $db->prepare("INSERT INTO users (id, role, organization_id, manager_id, is_deleted) VALUES (?, ?, ?, ?, ?)")
       ->execute([$id, $role, $org, $managerId, $isDeleted]);
}

return [

    'name' => 'دامنهٔ مدیر (getSubordinateIds / canManageTargetUser)',

    'tests' => [

        'زیردست مستقیم' => function (Assert $a) {
            $db = msdb();
            msSeed($db, 1, 'manager', 10);
            msSeed($db, 2, 'employee', 10, 1);

            $a->equals([2], getSubordinateIds($db, 1), 'فقط کاربر ۲');
        },

        'زنجیرهٔ چندسطحی (نه فقط زیردست مستقیم)' => function (Assert $a) {
            $db = msdb();
            msSeed($db, 1, 'manager', 10);
            msSeed($db, 2, 'manager', 10, 1);
            msSeed($db, 3, 'manager', 10, 2);
            msSeed($db, 4, 'employee', 10, 3);

            $subs = getSubordinateIds($db, 1);
            sort($subs);
            $a->equals([2, 3, 4], $subs, 'باید هر سه نسل را شامل شود');
        },

        '🔒 محافظ حلقه: نباید در حلقهٔ manager_id گیر کند' => function (Assert $a) {
            $db = msdb();
            msSeed($db, 1, 'manager', 10, 2);
            msSeed($db, 2, 'manager', 10, 1); // حلقه

            $t0 = microtime(true);
            $subs = getSubordinateIds($db, 1);
            $a->true((microtime(true) - $t0) < 2.0, 'نباید کند/گیر شود');
            $a->equals([2], $subs, 'فقط ۲، نه حلقهٔ بی‌نهایت');
        },

        '🔒 مرز سازمان: حتی با manager_id درست، از سازمان دیگر رد نمی‌شود' => function (Assert $a) {
            $db = msdb();
            msSeed($db, 1, 'manager', 10);
            msSeed($db, 2, 'employee', 99, 1); // دادهٔ ناهنجار: سازمان متفاوت

            $a->equals([], getSubordinateIds($db, 1), 'نباید کاربر سازمان دیگر را برگرداند');
        },

        'کاربر حذف‌شده جزو زیردستان نیست' => function (Assert $a) {
            $db = msdb();
            msSeed($db, 1, 'manager', 10);
            msSeed($db, 2, 'employee', 10, 1, 1);

            $a->equals([], getSubordinateIds($db, 1), 'کاربر حذف‌شده نباید بیاید');
        },

        'سوپرادمین حتی در سازمان دیگر هم مجاز است' => function (Assert $a) {
            $db = msdb();
            $superIds = getSuperAdminIds();
            msSeed($db, $superIds[0], 'employee', 1);
            msSeed($db, 999, 'employee', 2);

            $me = ['id' => $superIds[0], 'role' => 'employee', 'organization_id' => 1];
            $a->true(canManageTargetUser($db, $me, 999), 'سوپرادمین مستثناست');
        },

        'هرکس می‌تواند خودش را مدیریت کند' => function (Assert $a) {
            $db = msdb();
            msSeed($db, 5, 'employee', 1);
            $me = ['id' => 5, 'role' => 'employee', 'organization_id' => 1];
            $a->true(canManageTargetUser($db, $me, 5), 'خود کاربر');
        },

        'supervisor روی کل سازمان خودش مجاز است' => function (Assert $a) {
            $db = msdb();
            msSeed($db, 10, 'supervisor', 1);
            msSeed($db, 20, 'employee', 1);
            $me = ['id' => 10, 'role' => 'supervisor', 'organization_id' => 1];
            $a->true(canManageTargetUser($db, $me, 20), 'بدون نیاز به رابطهٔ مدیریتی');
        },

        '🔒 supervisor روی سازمان دیگر مجاز نیست' => function (Assert $a) {
            $db = msdb();
            msSeed($db, 10, 'supervisor', 1);
            msSeed($db, 30, 'employee', 2);
            $me = ['id' => 10, 'role' => 'supervisor', 'organization_id' => 1];
            $a->false(canManageTargetUser($db, $me, 30), 'باید رد شود');
        },

        'admin (نقش قدیمی) دقیقا مثل supervisor رفتار می‌کند' => function (Assert $a) {
            $db = msdb();
            msSeed($db, 11, 'admin', 1);
            msSeed($db, 21, 'employee', 1);
            msSeed($db, 31, 'employee', 2);
            $me = ['id' => 11, 'role' => 'admin', 'organization_id' => 1];

            $a->true(canManageTargetUser($db, $me, 21), 'admin روی هم‌سازمانی مجاز');
            $a->false(canManageTargetUser($db, $me, 31), '🔒 admin هم روی سازمان دیگر مجاز نیست');
        },

        'manager فقط روی زیرمجموعهٔ خودش مجاز است' => function (Assert $a) {
            $db = msdb();
            msSeed($db, 40, 'manager', 1);
            msSeed($db, 41, 'employee', 1, 40);
            msSeed($db, 42, 'employee', 1, 41);
            msSeed($db, 43, 'employee', 1);

            $me = ['id' => 40, 'role' => 'manager', 'organization_id' => 1];

            $a->true(canManageTargetUser($db, $me, 41), 'زیردست مستقیم');
            $a->true(canManageTargetUser($db, $me, 42), 'زیردست غیرمستقیم (نوه)');
            $a->false(canManageTargetUser($db, $me, 43), '🔒 هم‌سازمانی بی‌ربط: مجاز نیست');
        },

        '🔒 employee نمی‌تواند غیر خودش را مدیریت کند' => function (Assert $a) {
            $db = msdb();
            msSeed($db, 50, 'employee', 1);
            msSeed($db, 51, 'employee', 1);
            $me = ['id' => 50, 'role' => 'employee', 'organization_id' => 1];
            $a->false(canManageTargetUser($db, $me, 51), 'کارمند فقط خودش');
        },

        'admin دقیقا همان اجازه‌های supervisor را دارد' => function (Assert $a) {
            $adminUser = ['id' => 900, 'role' => 'admin', 'organization_id' => 1, 'is_active' => 1, 'is_deleted' => 0];
            $a->true(hasPermission($adminUser, 'manage_users'), 'admin: manage_users');
            $a->true(hasPermission($adminUser, 'view_payroll'), 'admin: view_payroll');
            $a->true(hasPermission($adminUser, 'grant_user_permissions'), 'admin: grant_user_permissions');
        },

        'manager حالا manage_users دارد (سطح درشت — دامنه با canManageTargetUser محدود می‌شود)' => function (Assert $a) {
            $mgr = ['id' => 901, 'role' => 'manager', 'organization_id' => 1, 'is_active' => 1, 'is_deleted' => 0];
            $a->true(hasPermission($mgr, 'manage_users'), 'manager: manage_users');
        },

        '🔒 manager هنوز اجازه‌های سطح سازمانی را ندارد' => function (Assert $a) {
            $mgr = ['id' => 902, 'role' => 'manager', 'organization_id' => 1, 'is_active' => 1, 'is_deleted' => 0];
            $a->false(hasPermission($mgr, 'view_org_settings'), 'manager: نه');
            $a->false(hasPermission($mgr, 'view_payroll'), 'manager: نه');
            $a->false(hasPermission($mgr, 'grant_user_permissions'), 'manager: نه');
        },

        'isOrgWideRole(): فقط admin/supervisor/سوپرادمین، نه manager' => function (Assert $a) {
            $superIds = getSuperAdminIds();
            $a->true(isOrgWideRole(['id' => $superIds[0], 'role' => 'employee']), 'سوپرادمین');
            $a->true(isOrgWideRole(['id' => 1000, 'role' => 'supervisor']), 'supervisor');
            $a->true(isOrgWideRole(['id' => 1001, 'role' => 'admin']), 'admin (قدیمی)');
            $a->false(isOrgWideRole(['id' => 1002, 'role' => 'manager']), '🔒 manager سطح سازمانی نیست');
            $a->false(isOrgWideRole(['id' => 1003, 'role' => 'employee']), 'employee');
            $a->false(isOrgWideRole(null), 'null');
        },
    ],
];
