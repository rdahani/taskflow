-- Préférence notifications e-mail (à exécuter si la base existait avant cette fonctionnalité)
ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `notify_email` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = recevoir les notifications par e-mail' AFTER `email`;
