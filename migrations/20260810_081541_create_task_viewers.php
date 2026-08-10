<?php
/**
 * اجازهٔ «فقط دیدنِ جزئیاتِ یک تسک» به چند نفرِ خاص — تسک‌به‌تسک، بدونِ
 * نیاز به تعریفِ کارِ جدید یا تغییرِ نقش/مسئولیت. جدولِ جدا نگه داشته
 * می‌شه تا هیچ تداخلی با زنجیرهٔ دسترسیِ موجود (سازنده/مسئول/مدیر/...)
 * در api/tasks/detail.php نداشته باشه.
 */

return [

    'description' => 'Create task_viewers table for explicit per-task view-only access grants',

    'up' => function (PDO $db) {
        $db->exec("CREATE TABLE `task_viewers` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `task_id` INT NOT NULL,
            `user_id` INT NOT NULL,
            `granted_by` INT NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uq_task_viewer` (`task_id`, `user_id`),
            CONSTRAINT `fk_tv_task` FOREIGN KEY (`task_id`) REFERENCES `tasks`(`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_tv_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_tv_granted_by` FOREIGN KEY (`granted_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    },

    'down' => function (PDO $db) {
        $db->exec("DROP TABLE IF EXISTS `task_viewers`");
    },

];
