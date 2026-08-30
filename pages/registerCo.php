<?php
if (!headers_sent()) {
    header("Content-Security-Policy-Report-Only: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline'; img-src 'self' data: https://computeryekta.com; font-src 'self' data:; connect-src 'self' https://api.ipify.org; frame-ancestors 'self'; object-src 'none'; base-uri 'self'; form-action 'self';");
    // بخشِ بی‌ریسکِ CSP به‌صورتِ واقعی (enforcing) — توضیح در includes/session_start.php
    header("Content-Security-Policy: object-src 'none'; base-uri 'self'; frame-ancestors 'self'; form-action 'self';");
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ثبت‌نام سازمان جدید</title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/js/cdn/bootstrap-icons.css">
    <script src="../assets/js/config.js"></script>
    <script src="../assets/js/common.js"></script>
    <script src="../assets/js/cdn/intro.min.js"></script>
    <link rel="stylesheet" href="../assets/js/cdn/introjs.min.css">
    <!-- تقویم شمسی سفارشی -->
    <link rel="stylesheet" href="../assets/css/persian-datepicker.css">
    <link rel="stylesheet" href="../../assets/css/custom.css">
    <link rel="stylesheet" href="../../assets/css/responsive/dashboard-responsive.css">
    <link rel="stylesheet" href="../assets/css/deadline-toast.css">
    <style>
        body {
            background: #f0f2f5;
        }

        .register-card {
            max-width: 520px;
            margin: 40px auto;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, .08);
        }

        .check-indicator {
            font-size: 0.85rem;
        }

        .check-indicator.available {
            color: #1b7b39;
        }

        .check-indicator.unavailable {
            color: #dc3545;
        }
    </style>
</head>

<body>
    <body class="login-page">
    <div class="register-wrapper">
        <div class="register-card">
            <div class="login-logo-section">
                 <div class="login-logo-frame">
                    <img src="../assets/images/logo.png" alt="لوگو">
                </div>
                <h1 class="login-company">ثبت نام سازمانی</h1>
                <p class="login-subtitle">ایجاد حساب کاربری جدید برای سازمان</p>
            </div>
            
            <div id="alertBox" class="alert d-none"></div>
            
            <form id="registerForm" novalidate>

                    <!-- اطلاعات سازمان -->
                    <h6 class="text-muted mb-3 border-bottom pb-2">اطلاعات سازمان</h6>

                    <div class="login-form-group" style="--i:1">
                        <label class="form-label" for="companyName">نام سازمان</label>
                        <div class="login-input-wrapper">
                            <input type="text" class="form-control" name="org_name" required>
                        </div>
                    </div>
                    <!-- اطلاعات مدیر -->
                    <h6 class="text-muted mb-3 border-bottom pb-2 mt-4">اطلاعات مدیر</h6>

                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">نام <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="first_name" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">نام خانوادگی <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="last_name" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">شماره موبایل (نام کاربری) <span class="text-danger">*</span></label>
                        <input type="tel" class="form-control" name="phone" placeholder="09XXXXXXXXX" maxlength="11"
                            required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">رمز عبور <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="password" placeholder="حداقل 6 کاراکتر"
                            required>
                    </div>

                <button type="submit" class="login-btn-submit" id="submitBtn">ثبت نام سازمانی</button>  
                <a href="../index.php" class="register-link-underline">
                ورود به سازمان</a>
                </form>
            </div>
        </div>
    </div>

    <script>
        const DOMAIN = 'bpm.computeryekta.com';  // ← دامنه اصلی خودت
        const API_BASE = '/api/organization';

        function toFa(n) {
            return String(n).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]);
        }

        // ارسال فرم
        document.getElementById('registerForm').addEventListener('submit', async function (e) {
            e.preventDefault();

            const btn = document.getElementById('submitBtn');
            const alert = document.getElementById('alertBox');
            const formData = Object.fromEntries(new FormData(this));

            btn.disabled = true;
            btn.textContent = 'در حال ثبت...';
            alert.className = 'alert d-none';

            try {
                const res = await fetch(`${API_BASE}/register.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(formData)
                });
                const data = await res.json();

                if (data.success) {
                    // ذخیره توکن
                    localStorage.setItem('authToken', data.token);

                    alert.className = 'alert alert-success';
                    alert.textContent =
                        `✅ سازمان شما ثبت شد! ${toFa(data.trial_days)} روز آزمایشی رایگان فعال است.`;

                    // ریدایرکت به داشبورد
                    setTimeout(() => {
                        window.location.href = `/pages/dashboard-manager.php`;
                    }, 2000);

                } else {
                    const msgs = data.errors ? data.errors.map(esc).join('<br>') : esc(data.message);
                    alert.className = 'alert alert-danger';
                    alert.innerHTML = msgs;
                    btn.disabled = false;
                    btn.textContent = 'ثبت‌نام و شروع رایگان';
                }
            } catch {
                alert.className = 'alert alert-danger';
                alert.textContent = 'خطا در اتصال. لطفاً دوباره تلاش کنید';
                btn.disabled = false;
                btn.textContent = 'ثبت‌نام و شروع رایگان';
            }
        });
    </script>
</body>

</html>