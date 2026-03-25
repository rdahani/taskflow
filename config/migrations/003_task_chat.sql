-- Discussion (chat) liée aux tâches
CREATE TABLE IF NOT EXISTS `tache_chat_messages` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tache_id`   INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NOT NULL,
  `message`    TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_chat_tache_id` (`tache_id`, `id`),
  FOREIGN KEY (`tache_id`) REFERENCES `taches`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`)  REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
