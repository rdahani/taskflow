-- Journal d’audit (admin / utilisateurs)
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`      INT UNSIGNED NOT NULL COMMENT 'Acteur',
  `action`       VARCHAR(80)  NOT NULL,
  `entity_type`  VARCHAR(50)  NOT NULL,
  `entity_id`    INT UNSIGNED DEFAULT NULL,
  `details`      VARCHAR(2000) DEFAULT NULL,
  `ip_address`   VARCHAR(45)   DEFAULT NULL,
  `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_audit_created` (`created_at`),
  INDEX `idx_audit_entity` (`entity_type`, `entity_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Anti-doublon rappels échéance (cron quotidien)
CREATE TABLE IF NOT EXISTS `reminder_sent` (
  `tache_id`   INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NOT NULL,
  `bucket`     VARCHAR(24)  NOT NULL COMMENT 'due_today|due_tomorrow|due_in_3d',
  `sent_date`  DATE         NOT NULL,
  PRIMARY KEY (`tache_id`, `user_id`, `bucket`, `sent_date`),
  FOREIGN KEY (`tache_id`) REFERENCES `taches`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`)  REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Index listes / rapports (ignorer erreur « duplicate key » si déjà appliqué)
CREATE INDEX `idx_taches_dept_echeance` ON `taches` (`departement_id`, `date_echeance`);
CREATE INDEX `idx_taches_statut_echeance` ON `taches` (`statut`, `date_echeance`);
CREATE INDEX `idx_assignees_user` ON `taches_assignees` (`user_id`, `tache_id`);
