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

        die(
            "<pre>" .
            $e->getMessage() .
            "\n\nDSN = " . $dsn .
            "\nUSER = " . $this->username .
            "</pre>"
        );

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