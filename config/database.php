<?php
$config = require __DIR__ . '/config.php';

if (!class_exists('Database')) {
    class Database {
        private $host;
        private $db_name;
        private $username;
        private $password;
        private $charset = 'utf8mb4';
        private $conn;
        
        public function __construct() {
            // بارگذاری config داخل constructor
            $config = require __DIR__ . '/config.php';
            
            $this->host = $config['db_host'];
            $this->db_name = $config['db_name'];
            $this->username = $config['db_user'];
            $this->password = $config['db_pass'];
            
            $this->connect();
        }
        
        private function connect() {
            try {
                $dsn = "mysql:host=" . $this->host . 
                       ";dbname=" . $this->db_name . 
                       ";charset=" . $this->charset;
                
                $this->conn = new PDO(
                    $dsn,
                    $this->username,
                    $this->password,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                        PDO::ATTR_STRINGIFY_FETCHES => false
                    ]
                );
                
                // Set charset
                $this->conn->exec("SET NAMES utf8mb4");
                $this->conn->exec("SET CHARACTER SET utf8mb4");
                
            } catch (PDOException $e) {
                // 🔒 قبلاً DSN/یوزرنیمِ دیتابیس + پیامِ خامِ PDOException مستقیم
                // echo می‌شد (حتی قبل از احرازِ هویت) — الان جزئیات فقط توی
                // لاگِ سرور ثبت می‌شه، پاسخِ کاربر یه پیامِ عمومیه
                error_log("Database connection error: " . $e->getMessage());
                http_response_code(500);
                header('Content-Type: application/json; charset=utf-8');
                die(json_encode(['success' => false, 'message' => 'خطا در اتصال به پایگاه داده'], JSON_UNESCAPED_UNICODE));
            }
        }
        
        public function getConnection() {
            if ($this->conn === null) {
                $this->connect();
            }
            return $this->conn;
        }
        
        public function disconnect() {
            $this->conn = null;
        }
        
        
    }
}
// ایجاد اتصال پیش‌فرض اگر $db تعریف نشده
if (!isset($db)) {
    try {
        $database = new Database();
        $db = $database->getConnection();
    } catch (PDOException $e) {
}
}

?>