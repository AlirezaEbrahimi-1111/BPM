<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  تاریخچه‌ی مانیتورینگِ سرور — برایِ نمودارهایِ ۱ساعت/۲۴ساعت/۷روزِ
 *  صفحه‌ی pages/server-monitor.php. هر ردیف یک نمونه‌ی خلاصه‌ست که
 *  go-api هر ۲ دقیقه خودش می‌سازه (نگاه کن go-api/internal/sysmon).
 *  کاملا ایزوله، بدونِ FK به بقیه‌ی اسکیما.
 * ═══════════════════════════════════════════════════════════════════
 */

return [

    'description' => 'Create system_metrics_history table for the server-monitor page charts',

    'up' => function (PDO $db) {

        $db->exec("
            CREATE TABLE IF NOT EXISTS `system_metrics_history` (
                `id`           INT AUTO_INCREMENT PRIMARY KEY,
                `recorded_at`  DATETIME NOT NULL,
                `cpu_percent`  DECIMAL(5,2) NOT NULL,
                `ram_percent`  DECIMAL(5,2) NOT NULL,
                `disk_percent` DECIMAL(5,2) NOT NULL,
                `net_rx_kbps`  DECIMAL(12,2) NOT NULL,
                `net_tx_kbps`  DECIMAL(12,2) NOT NULL,
                `load1`        DECIMAL(6,2) NOT NULL,
                INDEX `idx_smh_recorded_at` (`recorded_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci
        ");

    },

    'down' => function (PDO $db) {
        $db->exec("DROP TABLE IF EXISTS `system_metrics_history`");
    },

];
