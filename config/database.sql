-- ============================================================
--  TaskFlow — Script SQL complet
--  Créer la base : CREATE DATABASE taskflow CHARACTER SET utf8mb4;
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- Bases géographiques
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bases` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nom`        VARCHAR(100) NOT NULL,
  `code`       VARCHAR(20)  NOT NULL UNIQUE,
  `region`     VARCHAR(100),
  `actif`      TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `bases` (`nom`,`code`,`region`) VALUES
  ('Niamey',    'NIM', 'Niamey'),
  ('Agadez',    'AGZ', 'Agadez'),
  ('Dosso',     'DOS', 'Dosso'),
  ('Tillabéry', 'TIL', 'Tillabéry'),
  ('Zinder',    'ZND', 'Zinder'),
  ('Maradi',    'MRD', 'Maradi'),
  ('Tahoua',    'TAH', 'Tahoua');

-- ------------------------------------------------------------
-- Départements
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `departements` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nom`        VARCHAR(100) NOT NULL,
  `base_id`    INT UNSIGNED,
  `chef_id`    INT UNSIGNED DEFAULT NULL,
  `actif`      TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`base_id`) REFERENCES `bases`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `departements` (`nom`,`base_id`) VALUES
  ('Ressources Humaines', 1),
  ('Finance & Comptabilité', 1),
  ('Informatique', 1),
  ('Logistique', 1),
  ('Opérations', 1),
  ('Communication', 1),
  ('Administration', 1);

-- ------------------------------------------------------------
-- Utilisateurs
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nom`          VARCHAR(100) NOT NULL,
  `prenom`       VARCHAR(100) NOT NULL,
  `email`         VARCHAR(200) NOT NULL UNIQUE,
  `notify_email`  TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = recevoir les notifications par e-mail',
  `password`      VARCHAR(255) NOT NULL,
  `role`         ENUM('employe','superviseur','chef_dept','cheffe_mission','admin') DEFAULT 'employe',
  `departement_id` INT UNSIGNED DEFAULT NULL,
  `base_id`      INT UNSIGNED DEFAULT NULL,
  `photo`        VARCHAR(255) DEFAULT NULL,
  `telephone`    VARCHAR(30) DEFAULT NULL,
  `poste`        VARCHAR(150) DEFAULT NULL,
  `actif`        TINYINT(1) DEFAULT 1,
  `last_login`   DATETIME DEFAULT NULL,
  `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`departement_id`) REFERENCES `departements`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`base_id`) REFERENCES `bases`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Compte admin par défaut (mot de passe: Admin@2024)
INSERT INTO `users` (`nom`,`prenom`,`email`,`password`,`role`,`base_id`) VALUES
  ('Admin','Système','admin@taskflow.ne', '$2y$12$jluqTD3BMJzyz74zmzNvgerbshJlxcYv2QVn3HUzqdNZ2wLb1F3k2', 'admin', 1);

-- ------------------------------------------------------------
-- Catégories de tâches
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nom`        VARCHAR(100) NOT NULL,
  `couleur`    VARCHAR(7) DEFAULT '#6B7280',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `categories` (`nom`,`couleur`) VALUES
  ('Administratif', '#6B7280'),
  ('Technique',     '#2563EB'),
  ('Urgent',        '#DC2626'),
  ('Réunion',       '#7C3AED'),
  ('Formation',     '#16A34A'),
  ('Rapport',       '#D97706');

-- ------------------------------------------------------------
-- Tags
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tags` (
  `id`    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nom`   VARCHAR(100) NOT NULL UNIQUE,
  `couleur` VARCHAR(7) DEFAULT '#6B7280'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Tâches
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `taches` (
  `id`                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `titre`                VARCHAR(255) NOT NULL,
  `description`          TEXT,
  `statut`               ENUM('pas_fait','en_cours','en_attente','termine','annule','en_retard','rejete') DEFAULT 'pas_fait',
  `priorite`             ENUM('basse','normale','haute','urgente') DEFAULT 'normale',
  `date_creation`        DATETIME DEFAULT CURRENT_TIMESTAMP,
  `date_debut`           DATE DEFAULT NULL,
  `date_echeance`        DATE NOT NULL,
  `date_cloture`         DATETIME DEFAULT NULL,
  `createur_id`          INT UNSIGNED NOT NULL,
  `departement_id`       INT UNSIGNED DEFAULT NULL,
  `base_id`              INT UNSIGNED DEFAULT NULL,
  `categorie_id`         INT UNSIGNED DEFAULT NULL,
  `pourcentage`          TINYINT UNSIGNED DEFAULT 0,
  `tache_parente_id`     INT UNSIGNED DEFAULT NULL,
  `updated_at`           TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`createur_id`)      REFERENCES `users`(`id`),
  FOREIGN KEY (`departement_id`)   REFERENCES `departements`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`base_id`)          REFERENCES `bases`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`categorie_id`)     REFERENCES `categories`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`tache_parente_id`) REFERENCES `taches`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Assignations (many-to-many)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `taches_assignees` (
  `tache_id` INT UNSIGNED NOT NULL,
  `user_id`  INT UNSIGNED NOT NULL,
  `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tache_id`,`user_id`),
  FOREIGN KEY (`tache_id`) REFERENCES `taches`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`)  REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Tags des tâches
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `taches_tags` (
  `tache_id` INT UNSIGNED NOT NULL,
  `tag_id`   INT UNSIGNED NOT NULL,
  PRIMARY KEY (`tache_id`,`tag_id`),
  FOREIGN KEY (`tache_id`) REFERENCES `taches`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`tag_id`)   REFERENCES `tags`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Commentaires
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `commentaires` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tache_id`   INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NOT NULL,
  `contenu`    TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`tache_id`) REFERENCES `taches`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`)  REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Fichiers joints
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fichiers` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tache_id`      INT UNSIGNED NOT NULL,
  `nom_original`  VARCHAR(255) NOT NULL,
  `chemin`        VARCHAR(500) NOT NULL,
  `taille`        INT UNSIGNED,
  `mime`          VARCHAR(100),
  `uploaded_by`   INT UNSIGNED NOT NULL,
  `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`tache_id`)    REFERENCES `taches`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Historique des modifications
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `historique` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tache_id`      INT UNSIGNED NOT NULL,
  `user_id`       INT UNSIGNED NOT NULL,
  `champ`         VARCHAR(100),
  `ancienne_val`  TEXT,
  `nouvelle_val`  TEXT,
  `action`        VARCHAR(50) DEFAULT 'modification',
  `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`tache_id`) REFERENCES `taches`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`)  REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Notifications
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT UNSIGNED NOT NULL,
  `type`       VARCHAR(50) DEFAULT 'info',
  `titre`      VARCHAR(255),
  `message`    TEXT NOT NULL,
  `lien`       VARCHAR(500) DEFAULT NULL,
  `lu`         TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Audit & rappels échéance (cron)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`      INT UNSIGNED NOT NULL,
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

CREATE TABLE IF NOT EXISTS `reminder_sent` (
  `tache_id`   INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NOT NULL,
  `bucket`     VARCHAR(24)  NOT NULL,
  `sent_date`  DATE         NOT NULL,
  PRIMARY KEY (`tache_id`, `user_id`, `bucket`, `sent_date`),
  FOREIGN KEY (`tache_id`) REFERENCES `taches`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`)  REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Chat par tâche
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

-- Messagerie directe (conversations privées)
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

CREATE INDEX `idx_taches_dept_echeance` ON `taches` (`departement_id`, `date_echeance`);
CREATE INDEX `idx_taches_statut_echeance` ON `taches` (`statut`, `date_echeance`);
CREATE INDEX `idx_assignees_user` ON `taches_assignees` (`user_id`, `tache_id`);

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- Migrations correctives (à exécuter si la table existe déjà)
-- ============================================================
ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `notify_email` TINYINT(1) NOT NULL DEFAULT 1 AFTER `email`,
    ADD COLUMN IF NOT EXISTS `actif`      TINYINT(1)   DEFAULT 1    AFTER `poste`,
    ADD COLUMN IF NOT EXISTS `last_login` DATETIME     DEFAULT NULL  AFTER `actif`,
    ADD COLUMN IF NOT EXISTS `photo`      VARCHAR(255) DEFAULT NULL  AFTER `email`,
    ADD COLUMN IF NOT EXISTS `telephone`  VARCHAR(30)  DEFAULT NULL  AFTER `photo`,
    ADD COLUMN IF NOT EXISTS `poste`      VARCHAR(150) DEFAULT NULL  AFTER `telephone`;

ALTER TABLE `taches`
    ADD COLUMN IF NOT EXISTS `date_cloture`     DATETIME     DEFAULT NULL AFTER `date_echeance`,
    ADD COLUMN IF NOT EXISTS `tache_parente_id` INT UNSIGNED DEFAULT NULL AFTER `pourcentage`,
    ADD COLUMN IF NOT EXISTS `updated_at`       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `tache_parente_id`;
