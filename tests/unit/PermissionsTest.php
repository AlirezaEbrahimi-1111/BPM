<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  تست واحد: سامانهٔ کنترل دسترسی (RBAC سه‌لایه)
 *  محل: /tests/unit/PermissionsTest.php
 * ───────────────────────────────────────────────────────────────────
 *  چرا این تست حیاتی است؟
 *    بازبینی اخیر نشان داد یک حفرهٔ دسترسی وجود داشته که ۹ کارمند
 *    عادی را به عملیات مدیریتی مجاز می‌کرد. این تست‌ها تضمین می‌کنند
 *    که چنین حفره‌ای دوباره باز نشود — حتی اگر کسی در آینده کد را
 *    تغییر دهد.
 *
 *  سه لایهٔ تصمیم:
 *    ۱) سوپرادمین    → دسترسی کامل
 *    ۲) اختیار فردی  → اجازهٔ صریحِ اعطاشده توسط مدیر سازمان
 *    ۳) نقش          → اجازه‌های پایه
 * ═══════════════════════════════════════════════════════════════════
 */

require_once dirname(__DIR__, 2) . '/includes/permissions.php';

/**
 * ساخت یک کاربر آزمایشی — بدون نیاز به پایگاه داده.
 * دقیقاً همان ساختاری که loadUserForPermissions برمی‌گرداند.
 */
function makeUser(array $overrides = []): array
{
    return array_merge([
        'id'                  => 100,
        'role'                => 'employee',
        'organization_id'     => 1,
        'activity_section'    => 'sales',
        'can_create_routine'  => 0,
        'can_create_workflow' => 0,
        'is_active'           => 1,
        'is_deleted'          => 0,
    ], $overrides);
}

return [

    'name' => 'سامانهٔ کنترل دسترسی (RBAC سه‌لایه)',

    'tests' => [

        // ═══════════════════════════════════════════════
        //  لایهٔ ۱ — سوپرادمین
        // ═══════════════════════════════════════════════

        'سوپرادمین به همه‌چیز دسترسی دارد' => function (Assert $a) {
            $superIds = getSuperAdminIds();
            $super = makeUser(['id' => $superIds[0], 'role' => 'employee']);

            $a->true(isSuperAdmin($super), 'شناسه در فهرست سوپرادمین است');
            $a->true(hasPermission($super, 'manage_users'), 'مدیریت کاربران');
            $a->true(hasPermission($super, 'view_payroll'), 'مشاهدهٔ فیش حقوقی');
            $a->true(hasPermission($super, 'send_org_announcement'), 'ارسال اطلاعیه');
        },

        'سوپرادمین حتی با نقش کارمند، دسترسی کامل دارد' => function (Assert $a) {
            // ⚠️ این مورد مهم است: در دیتابیس واقعی، سوپرادمین ممکن است
            //    نقش employee داشته باشد. نباید قفل شود.
            $superIds = getSuperAdminIds();
            $super = makeUser(['id' => $superIds[0], 'role' => 'employee']);

            $a->true(
                hasPermission($super, 'manage_users'),
                'نقش کارمند نباید سوپرادمین را محدود کند'
            );
        },

        'سوپرادمین از قید سازمان مستثناست' => function (Assert $a) {
            $superIds = getSuperAdminIds();
            $super = makeUser(['id' => $superIds[0], 'organization_id' => 1]);

            $a->true(
                isSameOrganization($super, 99),
                'سوپرادمین به همهٔ سازمان‌ها دسترسی دارد'
            );
        },


        // ═══════════════════════════════════════════════
        //  لایهٔ ۳ — نقش
        // ═══════════════════════════════════════════════

        'سرپرست به عملیات مدیریتی دسترسی دارد' => function (Assert $a) {
            $sup = makeUser(['id' => 200, 'role' => 'supervisor']);

            $a->true(hasPermission($sup, 'manage_users'), 'مدیریت کاربران');
            $a->true(hasPermission($sup, 'view_all_org_tasks'), 'مشاهدهٔ همهٔ کارها');
            $a->true(hasPermission($sup, 'manage_task_groups'), 'مدیریت گروه‌ها');
            $a->true(hasPermission($sup, 'send_org_announcement'), 'ارسال اطلاعیه');
        },

        'کارمند عادی به عملیات مدیریتی دسترسی ندارد' => function (Assert $a) {
            $emp = makeUser(['id' => 300, 'role' => 'employee']);

            $a->false(hasPermission($emp, 'manage_users'), 'نباید کاربر بسازد');
            $a->false(hasPermission($emp, 'view_all_org_tasks'), 'نباید همهٔ کارها را ببیند');
            $a->false(hasPermission($emp, 'view_payroll'), 'نباید فیش حقوقی ببیند');
            $a->false(hasPermission($emp, 'send_org_announcement'), 'نباید اطلاعیه بفرستد');
        },

        'کارمند عادی می‌تواند کار بسازد' => function (Assert $a) {
            $emp = makeUser(['role' => 'employee']);

            $a->true(hasPermission($emp, 'create_task'), 'ساخت کار، اجازهٔ پایه است');
        },

        // 🔒 این تست، دقیقاً همان حفره‌ای را می‌بندد که پیدا شد
        'واحد سازمانی نباید دسترسی بدهد' => function (Assert $a) {
            // کارمندی در واحد «مدیریت» — که قبلاً به‌اشتباه
            // دسترسی مدیریتی می‌گرفت
            $emp = makeUser([
                'id'               => 400,
                'role'             => 'employee',
                'activity_section' => 'management',
            ]);

            $a->false(
                hasPermission($emp, 'manage_users'),
                '🔒 عضویت در واحد مدیریت نباید اختیار مدیریتی بدهد'
            );
            $a->false(
                hasPermission($emp, 'create_routine_template'),
                '🔒 عضویت در واحد مدیریت نباید اجازهٔ ساخت روتین بدهد'
            );
        },

        'سرپرست در هر واحدی، دسترسی مدیریتی دارد' => function (Assert $a) {
            // سرپرستِ واحد شبکه‌های اجتماعی — که قبلاً به‌اشتباه
            // از دسترسی مدیریتی محروم بود
            $sup = makeUser([
                'id'               => 500,
                'role'             => 'supervisor',
                'activity_section' => 'socialmedia',
            ]);

            $a->true(
                hasPermission($sup, 'manage_users'),
                '🔓 واحد نباید مانع اختیارِ نقش شود'
            );
        },


        // ═══════════════════════════════════════════════
        //  لایهٔ ۲ — اختیار فردی
        // ═══════════════════════════════════════════════

        'اختیار فردی، نقش را تکمیل می‌کند' => function (Assert $a) {
            // کارمندی که مدیر سازمان به او اجازهٔ ساخت روتین داده
            $emp = makeUser([
                'id'                  => 131,
                'role'                => 'employee',
                'can_create_routine'  => 1,
                'can_create_workflow' => 1,
            ]);

            $a->true(
                hasPermission($emp, 'create_routine_template'),
                'اختیار فردی: ساخت الگوی روتین'
            );
            $a->true(
                hasPermission($emp, 'create_workflow'),
                'اختیار فردی: ساخت فرآیند'
            );
        },

        'اختیار فردی فقط همان اجازه را می‌دهد، نه بیشتر' => function (Assert $a) {
            $emp = makeUser([
                'id'                  => 131,
                'role'                => 'employee',
                'can_create_routine'  => 1,
                'can_create_workflow' => 1,
            ]);

            $a->false(
                hasPermission($emp, 'manage_users'),
                'اجازهٔ ساخت روتین، اجازهٔ مدیریت کاربران نمی‌آورد'
            );
            $a->false(
                hasPermission($emp, 'view_payroll'),
                'اجازهٔ ساخت روتین، اجازهٔ فیش حقوقی نمی‌آورد'
            );
        },

        'اختیار فردیِ جزئی، فقط همان یک اجازه را می‌دهد' => function (Assert $a) {
            // کاربری که فقط اجازهٔ فرآیند دارد، نه روتین
            $emp = makeUser([
                'id'                  => 139,
                'role'                => 'employee',
                'can_create_routine'  => 0,
                'can_create_workflow' => 1,
            ]);

            $a->true(
                hasPermission($emp, 'create_workflow'),
                'اجازهٔ فرآیند: دارد'
            );
            $a->false(
                hasPermission($emp, 'create_routine_template'),
                'اجازهٔ روتین: ندارد'
            );
        },


        // ═══════════════════════════════════════════════
        //  اصل احتیاط (fail-closed)
        // ═══════════════════════════════════════════════

        'نقش ناشناخته هیچ اجازه‌ای نمی‌گیرد' => function (Assert $a) {
            $ghost = makeUser(['id' => 600, 'role' => 'ghost_role']);

            $a->false(
                hasPermission($ghost, 'create_task'),
                'در برابر نقش ناشناخته، سامانه بسته می‌ماند'
            );
        },

        'کاربر تهی، هیچ اجازه‌ای ندارد' => function (Assert $a) {
            $a->false(hasPermission(null, 'create_task'), 'کاربر null');
            $a->false(isSuperAdmin(null), 'سوپرادمین بودنِ null');
            $a->false(isSameOrganization(null, 1), 'هم‌سازمانیِ null');
        },


        // ═══════════════════════════════════════════════
        //  جداسازی سازمان‌ها (چندمستأجری)
        // ═══════════════════════════════════════════════

        'کاربر به سازمان دیگر دسترسی ندارد' => function (Assert $a) {
            $sup = makeUser([
                'id'              => 700,
                'role'            => 'supervisor',
                'organization_id' => 1,
            ]);

            $a->true(
                isSameOrganization($sup, 1),
                'به سازمان خودش دسترسی دارد'
            );
            $a->false(
                isSameOrganization($sup, 2),
                '🔒 حتی سرپرست، به سازمان دیگر دسترسی ندارد'
            );
        },


        // ═══════════════════════════════════════════════
        //  یکپارچگی جدول اجازه‌ها
        // ═══════════════════════════════════════════════

        'فهرست اجازه‌های کاربر درست ساخته می‌شود' => function (Assert $a) {
            $emp = makeUser([
                'role'               => 'employee',
                'can_create_routine' => 1,
            ]);

            $perms = getUserPermissions($emp);

            $a->contains('create_task', $perms, 'اجازهٔ پایهٔ نقش');
            $a->contains('create_routine_template', $perms, 'اختیار فردی');
            $a->false(
                in_array('manage_users', $perms, true),
                'اجازه‌ای که ندارد، در فهرست نیست'
            );
        },

    ],
];
