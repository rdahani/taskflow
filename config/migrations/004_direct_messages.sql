-- Messagerie directe entre utilisateurs (hors tâches)
CREATE TABLE IF NOT EXISTS `dm_threads` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_low_id`     INT UNSIGNED NOT NULL,
  `user_high_id`    INT UNSIGNED NOT NULL,
  `last_message_at` DATETIME DEFAULT NULL,
  UNIQUE KEY `uniq_dm_pair` (`user_low_id`, `user_high_id`),
  FOREIGN KEY (`user_low_id`)  REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_high_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `dm_messages` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `thread_id`  INT UNSIGNED NOT NULL,
  `sender_id`  INT UNSIGNED NOT NULL,
  `body`       TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_dm_thread_id` (`thread_id`, `id`),
  FOREIGN KEY (`thread_id`) REFERENCES `dm_threads`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `dm_thread_reads` (
  `thread_id`            INT UNSIGNED NOT NULL,
  `user_id`              INT UNSIGNED NOT NULL,
  `last_read_message_id` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`thread_id`, `user_id`),
  FOREIGN KEY (`thread_id`) REFERENCES `dm_threads`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`)   REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
