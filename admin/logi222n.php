<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

// اگر قبلاً لاگین کرده، ریدایرکت کن
if (isset($_COOKIE['auth_token'])) {
    header('Location: admin/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $remember_me = isset($_POST['remember_me']);
    
    $auth = new Auth();
    $result = $auth->login($username, $password, $remember_me);
    if ($result['success']) {
        resetRateLimit($ip, $db);     // ورود موفق → پاک‌کردن تلاش‌ها
        // ذخیره توکن در کوکی
        $expiry = $remember_me ? (30 * 24 * 60 * 60) : (24 * 60 * 60);
        setcookie('auth_token', $result['token'], [
            'expires'  => time() + $expiry,
            'path'     => '/',
            'secure'   => true,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        
        // ریدایرکت به داشبورد
        header('Location: admin/dashboard.php');
        exit;
    } else {
        recordFailedLogin($ip, $db);  // ورود ناموفق → ثبتِ تلاش
        $error = $result['message'];
    }
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود به سیستم</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Tahoma', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 400px;
        }
        h1 {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            border: 1px solid #f5c6cb;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        .checkbox-group input {
            margin-left: 8px;
        }
        button {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s;
        }
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>🔐 ورود به سیستم</h1>
        
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>نام کاربری یا موبایل</label>
                <input type="text" name="username" required autofocus>
            </div>
            
            <div class="form-group">
                <label>رمز عبور</label>
                <input type="password" name="password" required>
            </div>
            
            <div class="checkbox-group">
                <input type="checkbox" name="remember_me" id="remember">
                <label for="remember" style="margin: 0;">مرا به خاطر بسپار (30 روز)</label>
            </div>
            
            <button type="submit">ورود</button>
        </form>
    </div>
</body>
</html>