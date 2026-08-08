<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سیستم BPM</title>
    <link href="assets/fonts/Vazirmatn-font-face.css" rel="stylesheet">
    <link href="assets/css/custom.css?v=1.3" rel="stylesheet">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #ffffff;
            /*min-height: 100vh;*/
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Vazirmatn UI', 'Vazirmatn', 'Tahoma', sans-serif;
            margin-top: 0px !important;
        }

        .page-wrapper {
            display: flex;
            width: 70%;
            min-height: 100vh;
            direction: ltr;
            position: relative;
            z-index: 1;
        }

        /* ===== LEFT SIDE — برند / تصویر ===== */
        .brand-side {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 60px 48px;
            background: transparent;
            position: relative;
        }

        /* پخش‌شده در کل صفحه (نه فقط سمت چپ) — position:fixed نسبت به body */
        #networkCanvas {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            pointer-events: none;
            opacity: 0.5;
            z-index: 0;
        }

        .brand-headline {
            font-size: 28px;
            font-weight: 800;
            color: #1a1a2e;
            text-align: center;
            line-height: 1.5;
            margin-bottom: 12px;
        }

        .brand-headline span {
            color: #6c3ff4;
        }

        .brand-sub {
            font-size: 14px;
            color: #6b7280;
            text-align: center;
            line-height: 1.8;
            margin-bottom: 20px;
            max-width: 340px;
        }

        .feature-icons {
            display: flex;
            gap: 32px;
            margin-bottom: 48px;
            justify-content: center;
        }

        .feature-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }

        .feature-icon-wrap {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f5f0ff;
        }

        .feature-icon-wrap svg {
            width: 24px;
            height: 24px;
            stroke: #6c3ff4;
        }

        .feature-label {
            font-size: 12px;
            color: #6b7280;
            font-weight: 500;
        }

        .brand-illustration {
            width: 800px;
            max-width: 100%;
            filter: drop-shadow(0 20px 40px rgba(108, 63, 244, 0.15));
        }

        /* عنوانِ نام شرکت — فقط در چیدمانِ موبایل نمایش داده می‌شود */
        .brand-company-name {
            display: none;
            font-size: 20px;
            font-weight: 800;
            color: #1a1a2e;
            text-align: center;
            margin-bottom: 6px;
        }

        /* ===== RIGHT SIDE — فرم لاگین ===== */
        .login-side {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 60px 64px;
            background: transparent;
            border-right: none;

        }

        .login-panel {
            width: 100%;
            max-width: 380px;
            direction: rtl;
        }

        /* فیلدها */
        .field-group {
            margin-bottom: 20px;
        }

        .field-label {
            display: block;
            font-size: 13px;
            color: #374151;
            font-weight: 600;
            margin-bottom: 8px;
            text-align: right;
        }

        .input-wrap {
            position: relative;
        }

        .field-input {
            width: 100%;
            height: 50px;
            border: 1.5px solid #e5e7eb;
            border-radius: 12px;
            padding: 0 48px 0 48px;
            font-size: 14px;
            color: #1f2937;
            background: #e9e9e9;
            direction: rtl;
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
        }

        .field-input:focus {
            border-color: #6c3ff4;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(108, 63, 244, 0.1);
        }

        .field-input::placeholder {
            color: #9ca3af;
            font-size: 13px;
        }

        .input-icon-right {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            width: 18px;
            height: 18px;
            color: #9ca3af;
            pointer-events: none;
        }

        .input-icon-left {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
            display: flex;
            align-items: center;
        }

        .input-icon-left svg {
            width: 18px;
            height: 18px;
            stroke: #9ca3af;
            transition: stroke 0.2s;
        }

        .input-icon-left:hover svg {
            stroke: #6c3ff4;
        }

        /* ردیف گزینه‌ها */
        .options-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 28px;
            direction: rtl;
        }

        .remember-label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-size: 13px;
            color: #374151;
        }

        .remember-label input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: #6c3ff4;
            cursor: pointer;
        }

        .forgot-link {
            font-size: 13px;
            color: #6c3ff4;
            text-decoration: none;
            font-weight: 500;
            transition: opacity 0.2s;
        }

        .forgot-link:hover {
            opacity: 0.75;
        }

        /* چیپ کاربر (مرحله ۲) */
        .user-chip {
            display: flex;
            align-items: center;
            gap: 9px;
            background: #f0edff;
            border-radius: 12px;
            padding: 12px 15px;
            margin-bottom: 18px;
            font-size: .9rem;
            color: #4b3fa0;
        }

        .user-chip svg {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
        }

        .user-chip b {
            color: #2d2d3a;
            font-weight: 700;
        }

        /* خط جداکننده بین ورود و OTP */
        .step-divider {
            height: 1px;
            background: #eee;
            margin: 18px 0;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            width: auto;
            margin: 0 0 18px 0;
            background: #f3f4f6;
            border: none;
            cursor: pointer;
            color: #6b7280;
            font-size: .85rem;
            font-weight: 600;
            padding: 7px 13px;
            border-radius: 10px;
            transition: background .12s, color .12s;
        }

        .btn-back:hover {
            background: #e5e7eb;
            color: #4b3fa0;
        }

        .btn-back svg {
            width: 16px;
            height: 16px;
            transform: scaleX(-1);
        }

        /* دکمه ورود */
        .btn-login {
            width: 100%;
            height: 52px;
            background: linear-gradient(135deg, #6c3ff4 0%, #5b32d6 100%);
            border: none;
            border-radius: 14px;
            color: #ffffff;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: transform 0.15s, box-shadow 0.2s;
            box-shadow: 0 4px 20px rgba(108, 63, 244, 0.35);
            position: relative;
            overflow: hidden;
        }

        .btn-login:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 28px rgba(108, 63, 244, 0.45);
        }

        /*.btn-login:active { transform: translateY(0); }*/

        .btn-login:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .btn-login:disabled:hover {
            transform: none;
            box-shadow: 0 4px 20px rgba(108, 63, 244, 0.35);
        }

        /* ⚠️ custom.css یک کلاسِ عمومیِ .loading (بدون ربط به این دکمه) با padding:3rem
           داره که چون این دکمه هم موقعِ لودینگ کلاسِ loading می‌گیره، بهش اعمال می‌شد
           و باعثِ بزرگ‌شدنِ دکمه می‌شد. برای اینکه custom.css دست‌نخورده بمونه، همین‌جا
           با سلکتورِ ترکیبیِ .btn-login.loading (specificity بالاتر) override می‌شود */
        .btn-login.loading {
            padding: 0;
        }

        .btn-login.loading .btn-text,
        .btn-login.loading svg {
            display: none;
        }

        .btn-login.loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 22px;
            height: 22px;
            border: 2px solid rgba(255, 255, 255, 0.4);
            border-top-color: #fff;
            border-radius: 50%;
            animation: btn-spinner-spin 0.7s linear infinite;
        }

        @keyframes btn-spinner-spin {
            from {
                transform: translate(-50%, -50%) rotate(0deg);
            }
            to {
                transform: translate(-50%, -50%) rotate(360deg);
            }
        }


        /* ===== سوییچِ روشِ ورود (رمز عبور / کد یکبارمصرف) ===== */
        .method-switch {
            position: relative;
            display: flex;
            background: #f5f0ff;
            border-radius: 14px;
            padding: 4px;
            margin-bottom: 24px;
        }

        .method-tab {
            flex: 1;
            position: relative;
            z-index: 2;
            border: none;
            background: transparent;
            padding: 11px 8px;
            font-family: inherit;
            font-size: 13.5px;
            font-weight: 600;
            color: #6b7280;
            cursor: pointer;
            border-radius: 10px;
            transition: color 0.25s;
        }

        .method-tab.active {
            color: #fff;
        }

        .method-tab-indicator {
            position: absolute;
            top: 4px;
            right: 4px;
            width: calc(50% - 4px);
            height: calc(100% - 8px);
            background: linear-gradient(135deg, #6c3ff4 0%, #5b32d6 100%);
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(108, 63, 244, 0.35);
            transition: transform 0.28s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1;
        }

        .method-tab-indicator.pos-1 {
            transform: translateX(-100%);
        }

        /* ===== مرحلهٔ کد یکبارمصرف ===== */
        .otp-phone-display {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #f9fafb;
            border: 1px solid #f0f0f0;
            border-radius: 10px;
            padding: 10px 14px;
            margin-bottom: 18px;
            font-size: 13px;
            color: #6b7280;
        }

        .otp-phone-display b {
            color: #1f2937;
            font-weight: 700;
            direction: ltr;
            display: inline-block;
        }

        .otp-edit-btn {
            background: none;
            border: none;
            color: #6c3ff4;
            font-size: 12.5px;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
            padding: 0;
        }

        .otp-edit-btn:hover {
            opacity: 0.75;
        }

        .otp-inputs {
            display: flex;
            gap: 8px;
            justify-content: space-between;
            margin-bottom: 18px;
        }

        .otp-box {
            width: 100%;
            height: 54px;
            text-align: center;
            font-size: 20px;
            font-weight: 700;
            color: #1f2937;
            border: 1.5px solid #e5e7eb;
            border-radius: 12px;
            background: #fafafa;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
        }

        .otp-box:focus {
            border-color: #6c3ff4;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(108, 63, 244, 0.1);
        }

        .otp-box.filled {
            border-color: #c4b5fd;
            background: #fff;
        }

        .otp-box.error {
            border-color: #f87171;
        }

        .otp-resend-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin-top: 16px;
            font-size: 13px;
            color: #9ca3af;
        }

        .otp-resend-btn {
            background: none;
            border: none;
            color: #6c3ff4;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            font-family: inherit;
            padding: 0;
        }

        .otp-resend-btn:disabled {
            color: #c4b5fd;
            cursor: default;
        }

        .otp-resend-btn:not(:disabled):hover {
            opacity: 0.75;
        }

        /* ===== دکمهٔ ثبت‌نامِ سازمان (ثانویه) ===== */
        .register-cta {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #f0f0f0;
        }

        .btn-secondary {
            width: 100%;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: 1.5px solid #e5e0fb;
            background: #fbf9ff;
            color: #6c3ff4;
            border-radius: 14px;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            transition: border-color 0.2s, background 0.2s, transform 0.15s;
        }

        .btn-secondary:hover {
            border-color: #6c3ff4;
            background: #f5f0ff;
            transform: translateY(-1px);
        }

        .btn-secondary svg {
            width: 17px;
            height: 17px;
            flex-shrink: 0;
        }

        /* جداکنندهٔ «یا» — فقط در چیدمانِ موبایل نمایش داده می‌شود */
        .or-divider {
            display: none;
        }

        /* نوار امنیتی */
        .security-bar {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            margin-top: 20px;
            padding: 10px 16px;
            background: #f9fafb;
            border: 1px solid #f0f0f0;
            border-radius: 10px;
            font-size: 12px;
            color: #6b7280;
        }

        .security-bar svg {
            width: 15px;
            height: 15px;
            stroke: #10b981;
            flex-shrink: 0;
        }

        /* آلرت */
        #alertContainer {
            margin-bottom: 16px;
            direction: rtl;
        }

        .alert {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 13px;
            animation: fadeIn 0.3s ease;
        }

        .alert-success {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-danger {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        .alert-warning {
            background: #fffbeb;
            color: #92400e;
            border: 1px solid #fcd34d;
        }

        .alert-close {
            margin-right: auto;
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
            line-height: 1;
            color: inherit;
            opacity: 0.6;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-6px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ریسپانسیو */
        @media (max-width: 768px) {
            .top-logo {
                display: none;
            }

            .page-wrapper {
                flex-direction: column;
                border-right: none;
                width: 100%;
            }

            .brand-side {
                padding: 48px 24px 16px;
                border-bottom: none;
            }

            .brand-company-name {
                display: block;
            }

            .brand-sub {
                display: none;
            }

            .feature-icons {
                display: none;
                margin-bottom: 0;
            }

            .brand-illustration {
                width: 240px;
                margin-top: 8px;
            }

            .brand-headline {
                font-size: 18px;
                margin-bottom: 0;
            }

            .login-side {
                padding: 8px 24px 40px;
                border-right: none;
            }
        }
    </style>
</head>

<body>
    <canvas id="networkCanvas"></canvas>
    <div class="top-logo" style="position:fixed; top:0; left:0; padding:16px 20px; z-index:100;">
        <img src="https://computeryekta.com/wp-content/uploads/2026/06/modified_logo.png" alt="لوگو" style="height:40px;">
    </div>
    <div class="page-wrapper">

        <!-- ===== چپ: برند و تصویر ===== -->
        <div class="brand-side">
            <h2 class="brand-company-name">یکتا همراهان ملک</h2>
            <h1 class="brand-headline">
                مدیریت <span>یکپارچه فرآیندها</span> در یک نگاه
            </h1>
            <p class="brand-sub">
                اتوماسیون هوشمند، تصمیم‌گیری دقیق و کنترل کامل<br>فرآیندهای کسب‌وکار شما
            </p>

            <div class="feature-icons">
                <!-- قابل اعتماد -->
                <div class="feature-item">
                    <div class="feature-icon-wrap">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                            <polyline points="9 12 11 14 15 10" />
                        </svg>
                    </div>
                    <span class="feature-label">قابل اعتماد</span>
                </div>
                <!-- سریع -->
                <div class="feature-item">
                    <div class="feature-icon-wrap">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2" />
                        </svg>
                    </div>
                    <span class="feature-label">سریع</span>
                </div>
                <!-- هوشمند -->
                <div class="feature-item">
                    <div class="feature-icon-wrap">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9.5 2A2.5 2.5 0 0 1 12 4.5v15a2.5 2.5 0 0 1-4.96-.46 2.5 2.5 0 0 1-2.96-3.08 3 3 0 0 1-.34-5.58 2.5 2.5 0 0 1 1.32-4.24 2.5 2.5 0 0 1 4.44-1.14" />
                            <path d="M14.5 2A2.5 2.5 0 0 0 12 4.5v15a2.5 2.5 0 0 0 4.96-.46 2.5 2.5 0 0 0 2.96-3.08 3 3 0 0 0 .34-5.58 2.5 2.5 0 0 0-1.32-4.24 2.5 2.5 0 0 0-4.44-1.14" />
                        </svg>
                    </div>
                    <span class="feature-label">هوشمند</span>
                </div>
                <!-- ایمن -->
                <div class="feature-item">
                    <div class="feature-icon-wrap">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                            <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                        </svg>
                    </div>
                    <span class="feature-label">ایمن</span>
                </div>
            </div>

            <!-- لوگو/تصویر برند -->
            <img src="https://computeryekta.com/wp-content/uploads/2026/06/ChatGPT-Image-Jun-27-2026-09_28_07-AM-e1782549013981.webp"
                class="brand-illustration"
                alt="تصویر برند">
        </div>

        <!-- ===== راست: فرم لاگین ===== -->
        <div class="login-side">
            <div class="login-panel">

                <div id="alertContainer"></div>

                <!-- سوییچ روش ورود -->
                <form id="loginForm" novalidate>

                    <!-- ═══════════ مرحله ۱: موبایل ═══════════ -->
                    <div class="login-step" id="step1">
                        <div class="field-group">
                            <label class="field-label" for="username">شماره موبایل</label>
                            <div class="input-wrap">
                                <input type="text" class="field-input" id="username"
                                    placeholder="شماره موبایل خود را وارد کنید"
                                    required autocomplete="username" inputmode="numeric" maxlength="11">
                                <svg class="input-icon-right" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                                    <circle cx="12" cy="7" r="4" />
                                </svg>
                            </div>
                        </div>

                        <button type="button" class="btn-login" id="continueBtn" disabled>
                            <span class="btn-text">ادامه</span>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="transform: scaleX(-1);">
                                <line x1="5" y1="12" x2="19" y2="12" />
                                <polyline points="12 5 19 12 12 19" />
                            </svg>
                        </button>

                        <div class="register-cta">
                            <a href="/pages/registerCo.php" class="btn-secondary">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10" />
                                    <line x1="12" y1="8" x2="12" y2="16" />
                                    <line x1="8" y1="12" x2="16" y2="12" />
                                </svg>
                                ثبت نام سازمان جدید
                            </a>
                        </div>
                    </div>

                    <!-- ═══════════ مرحله ۲: رمز عبور ═══════════ -->
                    <div class="login-step" id="step2" style="display:none;">
                        <button type="button" class="btn-back back-in-step" onclick="window._goBack()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="19" y1="12" x2="5" y2="12" />
                                <polyline points="12 19 5 12 12 5" />
                            </svg>
                            بازگشت
                        </button>

                        <div class="field-group">
                            <label class="field-label" for="password">رمز عبور</label>
                            <div class="input-wrap">
                                <input type="password" class="field-input" id="password"
                                    placeholder="رمز عبور خود را وارد کنید" autocomplete="current-password">
                                <svg class="input-icon-right" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                                </svg>
                                <button type="button" class="input-icon-left" id="togglePassword" aria-label="نمایش رمز عبور">
                                    <svg id="eyeIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                        <circle cx="12" cy="12" r="3" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <button type="submit" class="btn-login" id="loginBtn">
                            <span class="btn-text">ورود به سیستم</span>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="transform: scaleX(-1);">
                                <line x1="5" y1="12" x2="19" y2="12" />
                                <polyline points="12 5 19 12 12 19" />
                            </svg>
                        </button>
                        <div class="options-row">
                            <label class="remember-label">
                                <input type="checkbox" id="rememberMe">
                                <span>مرا به خاطر بسپار</span>
                            </label>
                            <a href="#" class="forgot-link" id="forgotBtn">فراموشی رمز عبور</a>
                        </div>



                        <div class="step-divider"></div>

                        <button type="button" class="btn-secondary" id="otpSendBtn">
                            <span class="btn-text">ورود با کد یکبار مصرف</span>
                        </button>
                    </div>

                    <!-- ═══════════ مرحله ۳: کد یکبارمصرف ═══════════ -->
                    <div class="login-step" id="step3" style="display:none;">
                        <button type="button" class="btn-back back-in-step" onclick="window._goBack()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="19" y1="12" x2="5" y2="12" />
                                <polyline points="12 19 5 12 12 5" />
                            </svg>
                            بازگشت
                        </button>

                        <div class="otp-phone-display">
                            <span>کد تأیید به <b id="otpPhoneShown"></b> ارسال شد</span>
                        </div>

                        <div class="field-group">
                            <label class="field-label">کد ۶ رقمی را وارد کنید</label>
                            <div class="otp-inputs" dir="ltr" id="otpInputs">
                                <input type="text" class="otp-box" maxlength="1" inputmode="numeric" data-idx="0">
                                <input type="text" class="otp-box" maxlength="1" inputmode="numeric" data-idx="1">
                                <input type="text" class="otp-box" maxlength="1" inputmode="numeric" data-idx="2">
                                <input type="text" class="otp-box" maxlength="1" inputmode="numeric" data-idx="3">
                                <input type="text" class="otp-box" maxlength="1" inputmode="numeric" data-idx="4">
                                <input type="text" class="otp-box" maxlength="1" inputmode="numeric" data-idx="5">
                            </div>
                        </div>

                        <button type="button" class="btn-login" id="otpVerifyBtn">
                            <span class="btn-text">ورود</span>
                        </button>

                        <div class="otp-resend-row">
                            <span id="otpTimerText">ارسال مجدد کد تا ۰۰:۶۰</span>
                            <button type="button" class="otp-resend-btn" id="otpResendBtn" disabled style="display:none;">ارسال مجدد کد</button>
                        </div>
                    </div>

                    <div class="security-bar">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                            <polyline points="9 12 11 14 15 10" />
                        </svg>
                        <span>ارتباط شما با رمزنگاری SSL محافظت می‌شود</span>
                    </div>

                </form>
            </div>
        </div>

    </div>
    <script src="assets/js/network-canvas.js?v=2.0"></script>
    <script>
        // ========== سوییچ روش ورود + ورود با کد یکبارمصرف (OTP) ==========
        document.addEventListener('DOMContentLoaded', function() {

            const usernameInput = document.getElementById('username');
            const loginBtn = document.getElementById('loginBtn');
            const otpSendBtn = document.getElementById('otpSendBtn');
            const otpPhoneShown = document.getElementById('otpPhoneShown');
            const otpBoxes = Array.from(document.querySelectorAll('.otp-box'));
            const otpResendBtn = document.getElementById('otpResendBtn');
            const otpTimerText = document.getElementById('otpTimerText');

            let otpMode = false;
            let resendTimerId = null;
            // ═══════════ ناوبری مراحل ═══════════
            const step1 = document.getElementById('step1');
            const step2 = document.getElementById('step2');
            const step3 = document.getElementById('step3');
            const continueBtn = document.getElementById('continueBtn');
            const backBtn = document.getElementById('backBtn');
            let currentStep = 1;

            function goStep(n) {
                currentStep = n;
                step1.style.display = (n === 1) ? 'block' : 'none';
                step2.style.display = (n === 2) ? 'block' : 'none';
                step3.style.display = (n === 3) ? 'block' : 'none';
            }
            // 🔒 اعداد فارسی/عربی (۰۹۱۲...) که ممکنه از autofill یا کیبورد فارسی بیان
            // رو به اعداد انگلیسی تبدیل می‌کنه تا الگوی /^09.../ درست تشخیصشون بده
            function toEnglishDigits(str) {
                return String(str).replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d))
                                   .replace(/[٠-٩]/g, d => '٠١٢٣٤٥٦٧٨٩'.indexOf(d));
            }

            // ✅ به‌جای پیش‌بینیِ اینکه فیلد کِی/چطور پر می‌شه (تایپ دستی، autofill مرورگر،
            // افزونهٔ پسورد منیجر، ...) — که تشخیصِ همه‌ی حالت‌هاش قابل‌اعتماد نیست —
            // دکمه همیشه قابل‌کلیک می‌مونه و اعتبارسنجی فقط لحظهٔ کلیک انجام می‌شه.
            // این‌طوری مهم نیست مقدار از کجا اومده، چون .value همیشه لحظهٔ کلیک خونده می‌شه.
            continueBtn.removeAttribute('disabled');

            function tryGoStep2() {
                const phone = toEnglishDigits(usernameInput.value.trim());
                if (!/^09[0-9]{9}$/.test(phone)) {
                    showAlert('لطفاً شماره موبایل معتبر (۱۱ رقمی، شروع با ۰۹) وارد کنید', 'danger');
                    usernameInput.focus();
                    return;
                }
                goStep(2);
                document.getElementById('password').focus();
            }

            // مرحله ۱ → ۲
            continueBtn.addEventListener('click', tryGoStep2);

            // Enter در فیلد موبایل = ادامه
            usernameInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    tryGoStep2();
                }
            });

            // بازگشت (تابع سراسری — چون دکمه در هر مرحله جداست)
            window._goBack = function() {
                if (currentStep === 3) {
                    goStep(2);
                    stopResendTimer();
                } else {
                    goStep(1);
                    usernameInput.focus();
                }
            };

            // ---------- ارسال کد ----------
            otpSendBtn.addEventListener('click', async function() {
                const phone = usernameInput.value.trim();
                if (!phone || !/^09[0-9]{9}$/.test(phone)) {
                    showAlert('لطفاً شماره موبایل معتبر وارد کنید', 'danger');
                    return;
                }
                otpSendBtn.classList.add('loading');
                try {
                    const resp = await fetch('/api/auth/login.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: 'send_otp',
                            phone
                        })
                    });
                    const result = await resp.json();
                    if (result.success) {
                        showAlert('کد تأیید به شماره شما ارسال شد', 'success');
                        otpPhoneShown.textContent = phone;
                        goStep(3);
                        otpBoxes.forEach(b => b.value = '');
                        otpBoxes[0].focus();
                        startResendTimer();
                    } else {
                        showAlert(result.message, 'danger');
                    }
                } catch (e) {
                    showAlert('خطا در ارتباط با سرور', 'danger');
                } finally {
                    otpSendBtn.classList.remove('loading');
                }
            });
            // ---------- جعبه‌های کد تأیید (ورود خودکار به خانهٔ بعد + Backspace + Paste) ----------
            otpBoxes.forEach((box, idx) => {
                box.addEventListener('input', () => {
                    box.value = box.value.replace(/[^0-9]/g, '').slice(0, 1);
                    box.classList.toggle('filled', box.value !== '');
                    box.classList.remove('error');
                    if (box.value && idx < otpBoxes.length - 1) {
                        otpBoxes[idx + 1].focus();
                    }
                    if (otpBoxes.every(b => b.value)) {
                        otpVerifyBtn.click();
                    }
                });

                box.addEventListener('keydown', (e) => {
                    if (e.key === 'Backspace' && !box.value && idx > 0) {
                        otpBoxes[idx - 1].focus();
                    }
                });

                box.addEventListener('paste', (e) => {
                    const pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/[^0-9]/g, '');
                    if (!pasted) return;
                    e.preventDefault();
                    pasted.slice(0, otpBoxes.length).split('').forEach((digit, i) => {
                        if (otpBoxes[i]) {
                            otpBoxes[i].value = digit;
                            otpBoxes[i].classList.add('filled');
                        }
                    });
                    const nextEmpty = otpBoxes.find(b => !b.value);
                    (nextEmpty || otpBoxes[otpBoxes.length - 1]).focus();
                    if (otpBoxes.every(b => b.value)) {
                        otpVerifyBtn.click();
                    }
                });
            });

            // ---------- شمارش معکوس ارسال مجدد ----------
            function startResendTimer() {
                let seconds = 60;
                otpResendBtn.style.display = 'none';
                otpTimerText.style.display = 'inline';
                updateTimerText(seconds);
                stopResendTimer();
                resendTimerId = setInterval(() => {
                    seconds -= 1;
                    if (seconds <= 0) {
                        stopResendTimer();
                        otpTimerText.style.display = 'none';
                        otpResendBtn.style.display = 'inline';
                        otpResendBtn.disabled = false;
                    } else {
                        updateTimerText(seconds);
                    }
                }, 1000);
            }

            function stopResendTimer() {
                if (resendTimerId) {
                    clearInterval(resendTimerId);
                    resendTimerId = null;
                }
            }

            function updateTimerText(seconds) {
                const m = String(Math.floor(seconds / 60)).padStart(2, '0');
                const s = String(seconds % 60).padStart(2, '0');
                otpTimerText.textContent = `ارسال مجدد کد تا ${m}:${s}`;
            }

            otpResendBtn.addEventListener('click', async function() {
                otpResendBtn.disabled = true;
                otpBoxes.forEach(b => {
                    b.value = '';
                    b.classList.remove('filled', 'error');
                });
                otpSendBtn.click(); // ارسال مجدد از همان مسیر ارسال اولیه (قابل فراخوانی حتی وقتی مخفی است)
            });

            // ---------- تأیید کد ----------
            const otpVerifyBtn = document.getElementById('otpVerifyBtn');
            otpVerifyBtn.addEventListener('click', async function() {
                const phone = usernameInput.value.trim();
                const code = otpBoxes.map(b => b.value).join('');
                if (code.length !== 6) {
                    showAlert('کد تأیید ۶ رقمی را وارد کنید', 'danger');
                    otpBoxes.forEach(b => {
                        if (!b.value) b.classList.add('error');
                    });
                    return;
                }
                this.classList.add('loading');
                try {
                    const resp = await fetch('/api/auth/login.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: 'verify_otp',
                            phone,
                            code
                        })
                    });
                    const result = await resp.json();
                    if (result.success) {
                        // ✅ ذخیرهٔ توکن و اطلاعات کاربر (مثل مسیر ورود با رمز)
                        localStorage.setItem('auth_token', result.token);
                        localStorage.setItem('user_info', JSON.stringify(result.user));

                        showAlert('ورود موفقیت‌آمیز', 'success');
                        stopResendTimer();
                        window.location.href = '/pages/dashboard.php';
                    } else {
                        showAlert(result.message, 'danger');
                        otpBoxes.forEach(b => b.classList.add('error'));
                    }
                } catch (e) {
                    showAlert('خطا در ارتباط با سرور', 'danger');
                } finally {
                    this.classList.remove('loading');
                }
            });

            // تابع نمایش پیام (همان alert قبلی)
            function showAlert(msg, type) {
                const container = document.getElementById('alertContainer');
                container.innerHTML = `<div class="alert alert-${type}">${msg}</div>`;
                setTimeout(() => container.innerHTML = '', 8000);
            }
        });
    </script>
</body>

</html>