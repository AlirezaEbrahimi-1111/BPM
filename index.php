<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سیستم BPM</title>
<link href="assets/fonts/Vazirmatn-font-face.css" rel="stylesheet">
<link href="assets/css/custom.css?v=1.3" rel="stylesheet">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background: #ffffff;
            /*min-height: 100vh;*/
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Vazirmatn UI', 'Vazirmatn', 'Tahoma', sans-serif;
            margin-top:0px !important;
        }

        .page-wrapper {
            display: flex;
            width: 70%;
            min-height: 100vh;
            direction: ltr;
        }

        /* ===== LEFT SIDE — برند / تصویر ===== */
        .brand-side {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 60px 48px;
            background: #ffffff;
            position: relative;
                background: #ffffff;
    position: relative;
        }
        .brand-side canvas {
    position: absolute;
    top: 0; left: 0;
    width: 100%; height: 100%;
    pointer-events: none;
    opacity: 0.5;
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
            background: #ffffff;
                border-right: none;

        }

        .login-panel {
            width: 100%;
            max-width: 380px;
            direction:rtl;
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
            background: #fafafa;
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

        .input-icon-left:hover svg { stroke: #6c3ff4; }

        /* ردیف گزینه‌ها */
        .options-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 28px;
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

        .forgot-link:hover { opacity: 0.75; }

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

        .btn-login.loading .btn-text { opacity: 0; }
        .btn-login.loading::after {
            content: '';
            position: absolute;
            width: 22px; height: 22px;
            border: 2px solid rgba(255,255,255,0.4);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        /* لینک ثبت‌نام */
        .register-link {
            display: block;
            text-align: center;
            margin-top: 22px;
            font-size: 13px;
            color: #6c3ff4;
            text-decoration: none;
            font-weight: 600;
            position: relative;
        }

        .register-link::before,
        .register-link::after {
            content: '';
            position: absolute;
            top: 50%;
            width: 60px;
            height: 1px;
            background: #e5e7eb;
        }

        .register-link::before { right: 0; }
        .register-link::after { left: 0; }

        /* جداکنندهٔ «یا» — فقط در چیدمانِ موبایل نمایش داده می‌شود */
        .or-divider { display: none; }

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
        #alertContainer { margin-bottom: 16px;direction:rtl; }

        .alert {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 13px;
            animation: fadeIn 0.3s ease;
        }

        .alert-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-danger  { background: #fef2f2; color: #991b1b; border: 1px solid #fca5a5; }
        .alert-warning { background: #fffbeb; color: #92400e; border: 1px solid #fcd34d; }

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

        @keyframes fadeIn { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: translateY(0); } }

        /* ریسپانسیو */
        @media (max-width: 768px) {
            .top-logo { display: none; }

            .page-wrapper { flex-direction: column; border-right: none; width: 100%; }

            .brand-side { padding: 48px 24px 16px; border-bottom: none; }
            .brand-company-name { display: block; }
            .brand-sub { display: none; }
            .feature-icons { display: none; margin-bottom: 0; }
            .brand-illustration { width: 240px; margin-top: 8px; }
            .brand-headline { font-size: 18px; margin-bottom: 0; }

            .login-side { padding: 8px 24px 40px; border-right: none; }

            .or-divider {
                display: flex;
                align-items: center;
                gap: 12px;
                margin-top: 22px;
                color: #9ca3af;
                font-size: 13px;
            }
            .or-divider::before,
            .or-divider::after {
                content: '';
                flex: 1;
                height: 1px;
                background: #e5e7eb;
            }
            .register-link {
                margin-top: 14px;
                padding: 14px;
                border: 1.5px solid #6c3ff4;
                border-radius: 14px;
                font-weight: 700;
                font-size: 14px;
            }
            .register-link::before,
            .register-link::after { display: none; }
        }
    </style>
</head>
<body>
<div class="top-logo" style="position:fixed; top:0; left:0; padding:16px 20px; z-index:100;">
    <img src="https://computeryekta.com/wp-content/uploads/2026/06/modified_logo.png" alt="لوگو" style="height:40px;">
</div>
<div class="page-wrapper">

    <!-- ===== چپ: برند و تصویر ===== -->
    <div class="brand-side">
    <canvas id="networkCanvas"></canvas>
    <h2 class="brand-company-name">یکتا همراهان ملک</h2>
    <h1 class="brand-headline">
            مدیریت <span>یکپارچه فرایندها</span> در یک نگاه
        </h1>
        <p class="brand-sub">
            اتوماسیون هوشمند، تصمیم‌گیری دقیق و کنترل کامل<br>فرایندهای کسب‌وکار شما
        </p>

        <div class="feature-icons">
            <!-- قابل اعتماد -->
            <div class="feature-item">
                <div class="feature-icon-wrap">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        <polyline points="9 12 11 14 15 10"/>
                    </svg>
                </div>
                <span class="feature-label">قابل اعتماد</span>
            </div>
            <!-- سریع -->
            <div class="feature-item">
                <div class="feature-icon-wrap">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                    </svg>
                </div>
                <span class="feature-label">سریع</span>
            </div>
            <!-- هوشمند -->
            <div class="feature-item">
                <div class="feature-icon-wrap">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9.5 2A2.5 2.5 0 0 1 12 4.5v15a2.5 2.5 0 0 1-4.96-.46 2.5 2.5 0 0 1-2.96-3.08 3 3 0 0 1-.34-5.58 2.5 2.5 0 0 1 1.32-4.24 2.5 2.5 0 0 1 4.44-1.14"/>
                        <path d="M14.5 2A2.5 2.5 0 0 0 12 4.5v15a2.5 2.5 0 0 0 4.96-.46 2.5 2.5 0 0 0 2.96-3.08 3 3 0 0 0 .34-5.58 2.5 2.5 0 0 0-1.32-4.24 2.5 2.5 0 0 0-4.44-1.14"/>
                    </svg>
                </div>
                <span class="feature-label">هوشمند</span>
            </div>
            <!-- ایمن -->
            <div class="feature-item">
                <div class="feature-icon-wrap">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
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

            <form id="loginForm" novalidate>

                <div class="field-group">
                    <label class="field-label" for="username">شماره موبایل</label>
                    <div class="input-wrap">
                        <input type="tel" class="field-input" id="username"
                               placeholder="شماره موبایل خود را وارد کنید"
                               required autocomplete="tel" inputmode="numeric">
                        <svg class="input-icon-right" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                    </div>
                </div>

                <div class="field-group">
                    <label class="field-label" for="password">رمز عبور</label>
                    <div class="input-wrap">
                        <input type="password" class="field-input" id="password"
                               placeholder="رمز عبور خود را وارد کنید"
                               required autocomplete="current-password">
                        <svg class="input-icon-right" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                        <button type="button" class="input-icon-left" id="togglePassword" aria-label="نمایش رمز عبور">
                            <svg id="eyeIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="options-row">
                    <label class="remember-label">
                        <input type="checkbox" id="rememberMe">
                        <span>مرا به خاطر بسپار</span>
                    </label>
                    <a href="#" class="forgot-link" id="forgotBtn">فراموشی رمز عبور</a>
                </div>

                <button type="submit" class="btn-login" id="loginBtn">
                    <span class="btn-text">ورود به سیستم</span>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="transform: scaleX(-1);">
                        <line x1="5" y1="12" x2="19" y2="12"/>
                        <polyline points="12 5 19 12 12 19"/>
                    </svg>
                </button>

                <div class="or-divider"><span>یا</span></div>
                <a href="/pages/registerCo.php" class="register-link">ثبت نام سازمان جدید</a>

                <div class="security-bar">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        <polyline points="9 12 11 14 15 10"/>
                    </svg>
                    <span>ارتباط شما با رمزنگاری SSL محافظت می‌شود</span>
                </div>

            </form>
        </div>
    </div>

</div>
<script src="assets/js/network-canvas.js?v=2.0"></script>
</body>
</html>