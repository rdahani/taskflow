-- ============================================================
--  TaskFlow — Données de démonstration
--  Mot de passe de tous les employés : Employe@2024
--  Mot de passe admin : Admin@2024
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- BASES géographiques supplémentaires
-- ============================================================
INSERT IGNORE INTO `bases` (`nom`,`code`,`region`) VALUES
  ('Diffa',   'DIF', 'Diffa'),
  ('Birni',   'BNK', 'Dosso');

-- ============================================================
-- DÉPARTEMENTS supplémentaires (toutes les bases)
-- ============================================================
INSERT IGNORE INTO `departements` (`nom`,`base_id`) VALUES
  ('Ressources Humaines',    2),  -- Agadez
  ('Logistique',             2),
  ('Opérations',             2),
  ('Ressources Humaines',    3),  -- Dosso
  ('Finance & Comptabilité', 3),
  ('Administration',         4),  -- Tillabéry
  ('Opérations',             4),
  ('Informatique',           5),  -- Zinder
  ('Logistique',             5),
  ('Ressources Humaines',    6),  -- Maradi
  ('Communication',          6),
  ('Finance & Comptabilité', 7);  -- Tahoua

-- ============================================================
-- UTILISATEURS (30 comptes réalistes)
-- Mot de passe : Employe@2024  →  $2y$10$Bt5JYL9VhkHEw7aUWwB3IeGHkUOv0JX04OkiUvQqgMVvwA9U2llCm
-- ============================================================
INSERT IGNORE INTO `users` (`nom`,`prenom`,`email`,`password`,`role`,`departement_id`,`base_id`,`poste`,`telephone`,`actif`) VALUES

-- Chefs de département (1 par département principal)
('Mahamane','Issaka',   'i.mahamane@taskflow.ne',   '$2y$10$Bt5JYL9VhkHEw7aUWwB3IeGHkUOv0JX04OkiUvQqgMVvwA9U2llCm','chef_dept',1,1,'Directeur RH',              '+227 90 11 22 33',1),
('Oumarou', 'Aïchatou', 'a.oumarou@taskflow.ne',    '$2y$10$Bt5JYL9VhkHEw7aUWwB3IeGHkUOv0JX04OkiUvQqgMVvwA9U2llCm','chef_dept',2,1,'Directrice Finance',        '+227 90 22 33 44',1),
('Soumana', 'Moussa',   'm.soumana@taskflow.ne',    '$2y$10$Bt5JYL9VhkHEw7aUWwB3IeGHkUOv0JX04OkiUvQqgMVvwA9U2llCm','chef_dept',3,1,'Directeur Informatique',    '+227 90 33 44 55',1),
('Adamou',  'Ramatou',  'r.adamou@taskflow.ne',     '$2y$10$Bt5JYL9VhkHEw7aUWwB3IeGHkUOv0JX04OkiUvQqgMVvwA9U2llCm','chef_dept',4,1,'Directrice Logistique',     '+227 90 44 55 66',1),
('Hamidou', 'Sani',     's.hamidou@taskflow.ne',    '$2y$10$Bt5JYL9VhkHEw7aUWwB3IeGHkUOv0JX04OkiUvQqgMVvwA9U2llCm','chef_dept',5,1,'Directeur Opérations',      '+227 90 55 66 77',1),

-- Cheffe de mission
('Hassane', 'Fatouma',  'f.hassane@taskflow.ne',    '$2y$10$Bt5JYL9VhkHEw7aUWwB3IeGHkUOv0JX04OkiUvQqgMVvwA9U2llCm','cheffe_mission',NULL,1,'Cheffe de Mission',         '+227 90 00 11 00',1),

-- Superviseurs
('Daouda',  'Ibrahim',  'i.daouda@taskflow.ne',     '$2y$10$Bt5JYL9VhkHEw7aUWwB3IeGHkUOv0JX04OkiUvQqgMVvwA9U2llCm','superviseur',1,1,'Responsable RH',            '+227 91 10 20 30',1),
('Amadou',  'Mariama',  'm.amadou@taskflow.ne',     '$2y$10$Bt5JYL9VhkHEw7aUWwB3IeGHkUOv0JX04OkiUvQqgMVvwA9U2llCm','superviseur',3,1,'Chef Projet SI',            '+227 91 20 30 40',1),
('Boureima','Hadiza',   'h.boureima@taskflow.ne',   '$2y$10$Bt5JYL9VhkHEw7aUWwB3IeGHkUOv0JX04OkiUvQqgMVvwA9U2llCm','superviseur',4,2,'Superviseur Logistique',    '+227 91 30 40 50',1),
('Chaibou', 'Zeinabou', 'z.chaibou@taskflow.ne',    '$2y$10$Bt5JYL9VhkHEw7aUWwB3IeGHkUOv0JX04OkiUvQqgMVvwA9U2llCm','superviseur',5,1,'Superviseur Opérations',    '+227 91 40 50 60',1),

-- Employés — RH (dept 1)
('Illo',    'Amina',    'a.illo@taskflow.ne',       '$2y$10$Bt5JYL9VhkHEw7aUWwB3IeGHkUOv0JX04OkiUvQqgMVvwA9U2llCm','employe',1,1,'Chargée RH',                '+227 96 11 11 11',1),
('Laouali', 'Balkissa', 'b.laouali@taskflow.ne',    '$2y$10$Bt5JYL9VhkHEw7aUWwB3IeGHkUOv0JX04OkiUvQqgMVvwA9U2llCm','employe',1,1,'Assistante RH',             '+227 96 22 22 22',1),
('Mounkaila','Rahila',  'r.mounkaila@taskflow.ne',  '$2y$10$Bt5JYL9VhkHEw7aUWwB3IeGHkUOv0JX04OkiUvQqgMVvwA9U2llCm','employe',1,1,'Gestionnaire Paie',         '+227 96 33 33 33',1),

-- Employés — Finance (dept 2)
('Seydou',  'Fatouma',  'f.seydou@taskflow.ne',     '$2y$10$Bt5JYL9VhkHEw7aUWwB3IeGHkUOv0JX04OkiUvQqgMVvwA9U2llCm','employe',2,1,'Comptable',                 '+227 96 44 44 44',1),
('Garba',   'Raichatou','r.garba@taskflow.ne',      '$2y$10$Bt5JYL9VhkHEw7aUWwB3IeGHkUOv0JX04OkiUvQqgMVvwA9U2llCm','employe',2,1,'Aide-Comptable',            '+227 96 55 55 55',1),
('Wada',    'Hassane',  'h.wada@taskflow.ne',       '$2y$10$Bt5JYL9VhkHEw7aUWwB3IeGHkUOv0JX04OkiUvQqgMVvwA9U2llCm','employe',2,1,'Analyste Budget',           '+227 96 66 66 66',1),

-- Employés — Informatique (dept 3)
('Niandou', 'Abdou',    'a.niandou@taskflow.ne',    '$2y$10$Bt5JYL9VhkHEw7aUWwB3IeGHkUOv0JX04OkiUvQqgMVvwA9U2llCm','employe',3,1,'Développeur',               '+227 96 77 77 77',1),
('Saidou',  'Youssouf', 'y.saidou@taskflow.ne',     '$2y$10$Bt5JYL9VhkHEw7aUWwB3IeGHkUOv0JX04OkiUvQqgMVvwA9U2llCm','employe',3,1,'Administrateur Réseau',     '+227 96 88 88 88',1),
('Tahirou', 'Aicha',    'a.tahirou@taskflow.ne',    '$2y$10$Bt5JYL9VhkHEw7aUWwB3IeGHkUOv0JX04OkiUvQqgMVvwA9U2llCm','employe',3,1,'Technicien Support',        '+227 96 99 99 99',1),
('Djibo',   'Safiatou', 's.djibo@taskflow.ne',      '$2y$10$Bt5JYL9VhkHEw7aUWwB3IeGHkUOv0JX04OkiUvQqgMVvwA9U2llCm','employe',3,1,'Analyste Données',          '+227 97 11 11 11',1),

-- Employés — Logistique (dept 4)
('Idrissa', 'Mariama',  'm.idrissa@taskflow.ne',    '$2y$10$Bt5JYL9VhkHEw7aUWwB3IeGHkUOv0JX04OkiUvQqgMVvwA9U2llCm','employe',4,1,'Responsable Stock',         '+227 97 22 22 22',1),
('Kimba',   'Aminatou', 'a.kimba@taskflow.ne',      '$2y$10$Bt5JYL9VhkHEw7aUWwB3IeGHkUOv0JX04OkiUvQqgMVvwA9U2llCm','employe',4,1,'Chauffeur Convoyeur',        '+227 97 33 33 33',1),
('Malam',   'Harouna',  'h.malam@taskflow.ne',      '$2y$10$Bt5JYL9VhkHEw7aUWwB3IeGHkUOv0JX04OkiUvQqgMVvwA9U2llCm','employe',4,2,'Agent Logistique',           '+227 97 44 44 44',1),

-- Employés — Opérations (dept 5)
('Bello',   'Aissatou', 'a.bello@taskflow.ne',      '$2y$10$Bt5JYL9VhkHEw7aUWwB3IeGHkUOv0JX04OkiUvQqgMVvwA9U2llCm','employe',5,1,'Coordonnateur Terrain',     '+227 97 55 55 55',1),
('Hamani',  'Roukayatou','r.hamani@taskflow.ne',    '$2y$10$Bt5JYL9VhkHEw7aUWwB3IeGHkUOv0JX04OkiUvQqgMVvwA9U2llCm','employe',5,1,'Moniteur Activités',        '+227 97 66 66 66',1),
('Moussa',  'Abdoulaye','a.moussa@taskflow.ne',     '$2y$10$Bt5JYL9VhkHEw7aUWwB3IeGHkUOv0JX04OkiUvQqgMVvwA9U2llCm','employe',5,3,'Agent de Terrain',          '+227 97 77 77 77',1),

-- Employés — Communication (dept 6)
('Souleymane','Khadija','k.souleymane@taskflow.ne', '$2y$10$Bt5JYL9VhkHEw7aUWwB3IeGHkUOv0JX04OkiUvQqgMVvwA9U2llCm','employe',6,1,'Chargée Communication',     '+227 97 88 88 88',1),
('Abarchi',  'Sadissou','s.abarchi@taskflow.ne',    '$2y$10$Bt5JYL9VhkHEw7aUWwB3IeGHkUOv0JX04OkiUvQqgMVvwA9U2llCm','employe',6,1,'Graphiste',                 '+227 97 99 99 99',1),

-- Employés — Admin (dept 7)
('Maiga',   'Zara',     'z.maiga@taskflow.ne',      '$2y$10$Bt5JYL9VhkHEw7aUWwB3IeGHkUOv0JX04OkiUvQqgMVvwA9U2llCm','employe',7,1,'Secrétaire Exécutive',      '+227 98 11 11 11',1),
('Ouali',   'Modibo',   'm.ouali@taskflow.ne',      '$2y$10$Bt5JYL9VhkHEw7aUWwB3IeGHkUOv0JX04OkiUvQqgMVvwA9U2llCm','employe',7,1,'Agent Administration',      '+227 98 22 22 22',1);

-- ============================================================
-- TAGS
-- ============================================================
INSERT IGNORE INTO `tags` (`nom`,`couleur`) VALUES
  ('prioritaire',  '#DC2626'),
  ('suivi',        '#D97706'),
  ('terrain',      '#059669'),
  ('digital',      '#2563EB'),
  ('formation',    '#7C3AED'),
  ('recrutement',  '#0891B2'),
  ('budget',       '#D97706'),
  ('reporting',    '#6B7280');

-- ============================================================
-- TÂCHES (60 tâches réalistes)
-- IDs utilisateurs (après insertion ci-dessus) :
--   1=Admin, 2=Mahamane(chef RH), 3=Oumarou(chef Finance),
--   4=Soumana(chef Info), 5=Adamou(chef Logistique),
--   6=Hamidou(chef Ops), 7=Hassane(cheffe mission),
--   8=Daouda(sup RH), 9=Amadou(sup Info),
--   10=Boureima(sup Log), 11=Chaibou(sup Ops),
--   12=Illo, 13=Laouali, 14=Mounkaila, ...
-- ============================================================

-- RH — département 1
INSERT INTO `taches` (`titre`,`description`,`statut`,`priorite`,`date_creation`,`date_debut`,`date_echeance`,`createur_id`,`departement_id`,`base_id`,`categorie_id`,`pourcentage`) VALUES
('Révision du manuel des procédures RH','Mettre à jour le manuel interne avec les nouvelles politiques 2024','en_cours','haute',DATE_SUB(NOW(),INTERVAL 20 DAY),DATE_SUB(CURDATE(),INTERVAL 18 DAY),DATE_ADD(CURDATE(),INTERVAL 10 DAY),2,1,1,1,60),
('Campagne de recrutement Q2 2024','Ouvrir 5 postes : 2 développeurs, 1 comptable, 2 agents terrain','en_cours','urgente',DATE_SUB(NOW(),INTERVAL 15 DAY),DATE_SUB(CURDATE(),INTERVAL 14 DAY),DATE_ADD(CURDATE(),INTERVAL 5 DAY),2,1,1,1,45),
('Évaluation annuelle du personnel','Coordonner les entretiens d\'évaluation de performance pour tout le personnel','pas_fait','normale',DATE_SUB(NOW(),INTERVAL 5 DAY),DATE_ADD(CURDATE(),INTERVAL 5 DAY),DATE_ADD(CURDATE(),INTERVAL 30 DAY),8,1,1,1,0),
('Formation sécurité au travail','Organiser 3 sessions de formation HSE pour 80 agents','termine','normale',DATE_SUB(NOW(),INTERVAL 45 DAY),DATE_SUB(CURDATE(),INTERVAL 40 DAY),DATE_SUB(CURDATE(),INTERVAL 5 DAY),8,1,1,5,100),
('Mise à jour des contrats de travail','Renouveler les contrats arrivant à échéance en juin','en_attente','haute',DATE_SUB(NOW(),INTERVAL 10 DAY),DATE_ADD(CURDATE(),INTERVAL 2 DAY),DATE_ADD(CURDATE(),INTERVAL 20 DAY),2,1,1,1,20),
('Gestion des congés annuels','Planifier et valider les congés du personnel pour la période juillet-septembre','pas_fait','basse',DATE_SUB(NOW(),INTERVAL 3 DAY),DATE_ADD(CURDATE(),INTERVAL 10 DAY),DATE_ADD(CURDATE(),INTERVAL 45 DAY),8,1,1,1,0),
('Audit des dossiers du personnel','Vérifier et mettre à jour tous les dossiers administratifs individuels','en_cours','normale',DATE_SUB(NOW(),INTERVAL 8 DAY),DATE_SUB(CURDATE(),INTERVAL 7 DAY),DATE_ADD(CURDATE(),INTERVAL 7 DAY),2,1,1,1,35),
('Enquête satisfaction employés','Concevoir et déployer le questionnaire de satisfaction interne','termine','basse',DATE_SUB(NOW(),INTERVAL 60 DAY),DATE_SUB(CURDATE(),INTERVAL 55 DAY),DATE_SUB(CURDATE(),INTERVAL 20 DAY),8,1,1,4,100),

-- Finance — département 2
('Clôture comptable du trimestre','Préparer les états financiers du T1 2024','en_cours','urgente',DATE_SUB(NOW(),INTERVAL 12 DAY),DATE_SUB(CURDATE(),INTERVAL 10 DAY),DATE_ADD(CURDATE(),INTERVAL 3 DAY),3,2,1,2,75),
('Audit interne des dépenses','Contrôler les justificatifs de dépenses des 6 derniers mois','en_cours','haute',DATE_SUB(NOW(),INTERVAL 20 DAY),DATE_SUB(CURDATE(),INTERVAL 18 DAY),DATE_ADD(CURDATE(),INTERVAL 8 DAY),3,2,1,6,50),
('Élaboration du budget 2025','Collecter les besoins de chaque département pour le budget prévisionnel','pas_fait','haute',DATE_SUB(NOW(),INTERVAL 2 DAY),DATE_ADD(CURDATE(),INTERVAL 15 DAY),DATE_ADD(CURDATE(),INTERVAL 60 DAY),3,2,1,6,0),
('Rapport de trésorerie mensuel','Produire le rapport cash-flow du mois en cours','termine','normale',DATE_SUB(NOW(),INTERVAL 35 DAY),DATE_SUB(CURDATE(),INTERVAL 34 DAY),DATE_SUB(CURDATE(),INTERVAL 10 DAY),3,2,1,6,100),
('Réconciliation bancaire','Rapprocher les relevés bancaires avec la comptabilité interne','termine','normale',DATE_SUB(NOW(),INTERVAL 40 DAY),DATE_SUB(CURDATE(),INTERVAL 38 DAY),DATE_SUB(CURDATE(),INTERVAL 15 DAY),3,2,1,2,100),
('Formation outil comptable Sage','Former 4 agents sur le nouveau module Sage Comptabilité','en_attente','normale',DATE_SUB(NOW(),INTERVAL 7 DAY),DATE_ADD(CURDATE(),INTERVAL 5 DAY),DATE_ADD(CURDATE(),INTERVAL 25 DAY),3,2,1,5,10),
('Analyse des écarts budgétaires','Comparer réalisations vs prévisions et produire rapport d\'écart','pas_fait','basse',DATE_SUB(NOW(),INTERVAL 1 DAY),DATE_ADD(CURDATE(),INTERVAL 20 DAY),DATE_ADD(CURDATE(),INTERVAL 50 DAY),3,2,1,6,0),

-- Informatique — département 3
('Migration vers le nouveau serveur','Transférer les données et applications sur le serveur Ubuntu 22.04','en_cours','urgente',DATE_SUB(NOW(),INTERVAL 25 DAY),DATE_SUB(CURDATE(),INTERVAL 22 DAY),DATE_ADD(CURDATE(),INTERVAL 2 DAY),4,3,1,2,80),
('Développement module reporting','Créer un module de rapports automatisés pour la direction','en_cours','haute',DATE_SUB(NOW(),INTERVAL 30 DAY),DATE_SUB(CURDATE(),INTERVAL 28 DAY),DATE_ADD(CURDATE(),INTERVAL 15 DAY),9,3,1,2,55),
('Mise à jour antivirus et pare-feu','Renouveler les licences et mettre à jour les signatures','termine','normale',DATE_SUB(NOW(),INTERVAL 50 DAY),DATE_SUB(CURDATE(),INTERVAL 48 DAY),DATE_SUB(CURDATE(),INTERVAL 10 DAY),4,3,1,2,100),
('Déploiement VPN pour télétravail','Configurer et tester la solution VPN pour 30 utilisateurs distants','en_cours','haute',DATE_SUB(NOW(),INTERVAL 18 DAY),DATE_SUB(CURDATE(),INTERVAL 15 DAY),DATE_ADD(CURDATE(),INTERVAL 6 DAY),9,3,1,2,70),
('Formation utilisateurs Microsoft 365','Animer 2 jours de formation sur Teams, SharePoint et OneDrive','pas_fait','normale',DATE_SUB(NOW(),INTERVAL 4 DAY),DATE_ADD(CURDATE(),INTERVAL 10 DAY),DATE_ADD(CURDATE(),INTERVAL 20 DAY),4,3,1,5,0),
('Sauvegarde et plan de reprise','Rédiger et tester le plan de reprise d\'activité (PRA)','en_attente','haute',DATE_SUB(NOW(),INTERVAL 14 DAY),DATE_ADD(CURDATE(),INTERVAL 3 DAY),DATE_ADD(CURDATE(),INTERVAL 30 DAY),9,3,1,2,15),
('Audit sécurité du SI','Réaliser un test de pénétration interne et corriger les vulnérabilités','pas_fait','haute',DATE_SUB(NOW(),INTERVAL 6 DAY),DATE_ADD(CURDATE(),INTERVAL 14 DAY),DATE_ADD(CURDATE(),INTERVAL 45 DAY),4,3,1,2,0),
('Maintenance préventive des équipements','Vérifier et nettoyer 120 postes de travail dans les 3 sites','termine','basse',DATE_SUB(NOW(),INTERVAL 65 DAY),DATE_SUB(CURDATE(),INTERVAL 60 DAY),DATE_SUB(CURDATE(),INTERVAL 25 DAY),9,3,1,2,100),

-- Logistique — département 4
('Inventaire général du matériel','Recenser tout le matériel dans les dépôts de Niamey et Agadez','en_cours','urgente',DATE_SUB(NOW(),INTERVAL 10 DAY),DATE_SUB(CURDATE(),INTERVAL 9 DAY),DATE_ADD(CURDATE(),INTERVAL 1 DAY),5,4,1,1,88),
('Organisation du convoi Agadez','Planifier et exécuter la livraison de 15 tonnes de matériel','termine','urgente',DATE_SUB(NOW(),INTERVAL 55 DAY),DATE_SUB(CURDATE(),INTERVAL 50 DAY),DATE_SUB(CURDATE(),INTERVAL 20 DAY),10,4,1,3,100),
('Révision du parc automobile','Faire réviser les 8 véhicules du parc et mettre à jour les cartes grises','en_cours','normale',DATE_SUB(NOW(),INTERVAL 22 DAY),DATE_SUB(CURDATE(),INTERVAL 20 DAY),DATE_ADD(CURDATE(),INTERVAL 8 DAY),5,4,1,1,40),
('Appel d\'offres fournisseurs','Lancer et analyser les offres pour le contrat de fourniture 2024','en_attente','haute',DATE_SUB(NOW(),INTERVAL 9 DAY),DATE_ADD(CURDATE(),INTERVAL 3 DAY),DATE_ADD(CURDATE(),INTERVAL 35 DAY),10,4,1,1,5),
('Formation logistique humanitaire','Organiser une formation Sphère pour 20 agents logistiques','pas_fait','normale',DATE_SUB(NOW(),INTERVAL 2 DAY),DATE_ADD(CURDATE(),INTERVAL 20 DAY),DATE_ADD(CURDATE(),INTERVAL 40 DAY),5,4,1,5,0),
('Gestion des stocks médicaux','Vérifier les dates de péremption et renouveler les stocks critiques','en_cours','urgente',DATE_SUB(NOW(),INTERVAL 16 DAY),DATE_SUB(CURDATE(),INTERVAL 14 DAY),DATE_ADD(CURDATE(),INTERVAL 4 DAY),10,4,2,3,60),
('Rapport mensuel logistique','Compiler les données mouvement de stocks pour rapport direction','termine','basse',DATE_SUB(NOW(),INTERVAL 38 DAY),DATE_SUB(CURDATE(),INTERVAL 36 DAY),DATE_SUB(CURDATE(),INTERVAL 8 DAY),5,4,1,6,100),

-- Opérations — département 5
('Évaluation des activités terrain Q1','Analyser les indicateurs de performance des équipes terrain','en_cours','haute',DATE_SUB(NOW(),INTERVAL 17 DAY),DATE_SUB(CURDATE(),INTERVAL 15 DAY),DATE_ADD(CURDATE(),INTERVAL 5 DAY),6,5,1,6,65),
('Coordination réunion inter-agences','Préparer l\'ordre du jour et les présentations pour la réunion mensuelle','termine','normale',DATE_SUB(NOW(),INTERVAL 42 DAY),DATE_SUB(CURDATE(),INTERVAL 40 DAY),DATE_SUB(CURDATE(),INTERVAL 12 DAY),11,5,1,4,100),
('Déploiement équipes zone Tillabéry','Planifier la rotation des équipes terrain dans la région','en_cours','urgente',DATE_SUB(NOW(),INTERVAL 13 DAY),DATE_SUB(CURDATE(),INTERVAL 11 DAY),DATE_ADD(CURDATE(),INTERVAL 2 DAY),6,5,1,3,72),
('Mise en place système de monitoring','Installer et configurer le tableau de bord de suivi des opérations','en_attente','haute',DATE_SUB(NOW(),INTERVAL 11 DAY),DATE_ADD(CURDATE(),INTERVAL 7 DAY),DATE_ADD(CURDATE(),INTERVAL 28 DAY),11,5,1,2,0),
('Formation premiers secours','Certifier 30 agents en premiers secours (PSC1)','pas_fait','normale',DATE_SUB(NOW(),INTERVAL 1 DAY),DATE_ADD(CURDATE(),INTERVAL 25 DAY),DATE_ADD(CURDATE(),INTERVAL 35 DAY),6,5,1,5,0),
('Rapport opérationnel mensuel','Compiler les données d\'activité pour le rapport mensuel bailleur','termine','normale',DATE_SUB(NOW(),INTERVAL 33 DAY),DATE_SUB(CURDATE(),INTERVAL 31 DAY),DATE_SUB(CURDATE(),INTERVAL 6 DAY),11,5,1,6,100),
('Visite terrain zone Diffa','Effectuer une mission d\'évaluation de 5 jours à Diffa','en_cours','haute',DATE_SUB(NOW(),INTERVAL 8 DAY),DATE_SUB(CURDATE(),INTERVAL 6 DAY),DATE_ADD(CURDATE(),INTERVAL 9 DAY),6,5,4,3,50),
('Mise à jour du plan de sécurité','Réviser le protocole de sécurité pour les équipes terrain','en_attente','urgente',DATE_SUB(NOW(),INTERVAL 5 DAY),DATE_ADD(CURDATE(),INTERVAL 1 DAY),DATE_ADD(CURDATE(),INTERVAL 12 DAY),11,5,1,1,0),

-- Communication — département 6
('Refonte site web institutionnel','Concevoir et développer le nouveau site en WordPress','en_cours','haute',DATE_SUB(NOW(),INTERVAL 28 DAY),DATE_SUB(CURDATE(),INTERVAL 25 DAY),DATE_ADD(CURDATE(),INTERVAL 20 DAY),1,6,1,2,42),
('Campagne réseaux sociaux Ramadan','Créer et programmer 30 publications pour la période de Ramadan','termine','normale',DATE_SUB(NOW(),INTERVAL 70 DAY),DATE_SUB(CURDATE(),INTERVAL 68 DAY),DATE_SUB(CURDATE(),INTERVAL 30 DAY),1,6,1,1,100),
('Production vidéo témoignages','Filmer et monter 5 vidéos de témoignages bénéficiaires','en_attente','normale',DATE_SUB(NOW(),INTERVAL 6 DAY),DATE_ADD(CURDATE(),INTERVAL 8 DAY),DATE_ADD(CURDATE(),INTERVAL 30 DAY),1,6,1,1,0),
('Rapport annuel 2023','Rédiger, illustrer et diffuser le rapport annuel d\'activités','pas_fait','haute',DATE_SUB(NOW(),INTERVAL 3 DAY),DATE_ADD(CURDATE(),INTERVAL 30 DAY),DATE_ADD(CURDATE(),INTERVAL 75 DAY),1,6,1,6,0),
('Newsletter mensuelle mai','Rédiger et envoyer la newsletter aux 500 abonnés','termine','basse',DATE_SUB(NOW(),INTERVAL 35 DAY),DATE_SUB(CURDATE(),INTERVAL 33 DAY),DATE_SUB(CURDATE(),INTERVAL 15 DAY),1,6,1,1,100),
('Charte graphique 2024','Actualiser les couleurs et templates de la charte graphique','en_cours','normale',DATE_SUB(NOW(),INTERVAL 19 DAY),DATE_SUB(CURDATE(),INTERVAL 17 DAY),DATE_ADD(CURDATE(),INTERVAL 11 DAY),1,6,1,1,30),

-- Administration — département 7
('Organisation réunion direction mensuelle','Préparer la salle, l\'ordre du jour et le procès-verbal','termine','normale',DATE_SUB(NOW(),INTERVAL 32 DAY),DATE_SUB(CURDATE(),INTERVAL 30 DAY),DATE_SUB(CURDATE(),INTERVAL 20 DAY),7,7,1,4,100),
('Classement et archivage des documents','Numériser et classer 800 documents administratifs','en_cours','basse',DATE_SUB(NOW(),INTERVAL 21 DAY),DATE_SUB(CURDATE(),INTERVAL 20 DAY),DATE_ADD(CURDATE(),INTERVAL 15 DAY),1,7,1,1,25),
('Renouvellement des assurances','Négocier et renouveler les contrats d\'assurance véhicules et biens','en_attente','haute',DATE_SUB(NOW(),INTERVAL 8 DAY),DATE_ADD(CURDATE(),INTERVAL 5 DAY),DATE_ADD(CURDATE(),INTERVAL 22 DAY),1,7,1,1,5),
('Inventaire des fournitures de bureau','Recenser les stocks et passer commande pour le prochain trimestre','pas_fait','basse',DATE_SUB(NOW(),INTERVAL 2 DAY),DATE_ADD(CURDATE(),INTERVAL 12 DAY),DATE_ADD(CURDATE(),INTERVAL 25 DAY),1,7,1,1,0),
('Compte rendu réunion partenaires','Rédiger et diffuser le compte rendu de la réunion bailleurs','termine','normale',DATE_SUB(NOW(),INTERVAL 28 DAY),DATE_SUB(CURDATE(),INTERVAL 27 DAY),DATE_SUB(CURDATE(),INTERVAL 18 DAY),7,7,1,1,100),
('Gestion des visites institutionnelles','Coordonner les visites de terrain avec les délégations partenaires','en_cours','haute',DATE_SUB(NOW(),INTERVAL 9 DAY),DATE_SUB(CURDATE(),INTERVAL 7 DAY),DATE_ADD(CURDATE(),INTERVAL 4 DAY),1,7,1,4,55),

-- Tâches en retard (date_echeance dépassée, statut pas_fait ou en_cours)
('Validation du rapport d\'évaluation externe','Analyser et transmettre les recommandations du rapport','en_cours','urgente',DATE_SUB(NOW(),INTERVAL 35 DAY),DATE_SUB(CURDATE(),INTERVAL 30 DAY),DATE_SUB(CURDATE(),INTERVAL 5 DAY),7,5,1,6,40),
('Mise à jour du registre de risques','Documenter les risques opérationnels et les mesures de mitigation','pas_fait','haute',DATE_SUB(NOW(),INTERVAL 25 DAY),DATE_SUB(CURDATE(),INTERVAL 20 DAY),DATE_SUB(CURDATE(),INTERVAL 2 DAY),6,5,1,1,0),
('Récupération des avances non justifiées','Contacter les agents et récupérer les justificatifs manquants','en_cours','urgente',DATE_SUB(NOW(),INTERVAL 30 DAY),DATE_SUB(CURDATE(),INTERVAL 28 DAY),DATE_SUB(CURDATE(),INTERVAL 7 DAY),3,2,1,6,15),
('Formation gestion de projet','Organiser 2 jours de formation PM pour les chefs d\'équipe','pas_fait','normale',DATE_SUB(NOW(),INTERVAL 40 DAY),DATE_SUB(CURDATE(),INTERVAL 35 DAY),DATE_SUB(CURDATE(),INTERVAL 8 DAY),8,1,1,5,0),
('Renouvellement permis de conduire','Renouveler les permis des 5 chauffeurs arrivés à expiration','en_cours','haute',DATE_SUB(NOW(),INTERVAL 45 DAY),DATE_SUB(CURDATE(),INTERVAL 40 DAY),DATE_SUB(CURDATE(),INTERVAL 3 DAY),5,4,1,1,70);

-- ============================================================
-- ASSIGNATIONS (taches_assignees)
-- Assigner les tâches aux utilisateurs concernés
-- ============================================================
INSERT IGNORE INTO `taches_assignees` (`tache_id`,`user_id`) VALUES
-- Tâches RH (taches 1-8)
(1,8),(1,12),     -- révision manuel : sup RH + Illo
(2,2),(2,8),(2,12),(2,13), -- recrutement : chef + sup + 2 employés
(3,8),(3,13),(3,14),       -- évaluation : sup + 2 employés RH
(4,8),(4,12),              -- formation HSE
(5,2),(5,14),              -- contrats
(6,8),(6,12),(6,13),       -- congés
(7,8),(7,14),              -- audit dossiers
(8,8),(8,13),              -- enquête satisfaction

-- Tâches Finance (taches 9-15)
(9,3),(9,15),(9,16),       -- clôture comptable
(10,3),(10,16),(10,17),    -- audit dépenses
(11,3),(11,15),(11,17),    -- budget 2025
(12,15),(12,16),           -- rapport trésorerie
(13,15),(13,16),           -- réconciliation
(14,3),(14,15),            -- formation Sage
(15,16),(15,17),           -- analyse écarts

-- Tâches Info (taches 16-23)
(16,4),(16,18),(16,19),    -- migration serveur
(17,9),(17,18),(17,21),    -- module reporting
(18,9),(18,20),            -- antivirus
(19,9),(19,19),(19,20),    -- VPN
(20,4),(20,18),(20,19),(20,20), -- formation M365
(21,9),(21,21),            -- sauvegarde PRA
(22,4),(22,18),            -- audit sécu
(23,9),(23,20),(23,21),    -- maintenance

-- Tâches Logistique (taches 24-30)
(24,5),(24,10),(24,22),    -- inventaire matériel
(25,10),(25,22),(25,23),   -- convoi Agadez
(26,5),(26,22),            -- révision véhicules
(27,5),(27,10),            -- appel offres
(28,5),(28,22),(28,23),    -- formation log
(29,10),(29,22),(29,23),   -- stocks médicaux
(30,5),(30,10),            -- rapport mensuel log

-- Tâches Opérations (taches 31-38)
(31,6),(31,24),(31,25),    -- évaluation Q1
(32,11),(32,25),           -- réunion inter-agences
(33,6),(33,11),(33,24),(33,25),(33,26), -- déploiement Tillabéry
(34,11),(34,18),           -- monitoring
(35,6),(35,24),(35,25),(35,26), -- formation secours
(36,11),(36,24),           -- rapport mensuel ops
(37,6),(37,24),(37,25),    -- visite Diffa
(38,11),(38,6),            -- plan sécurité

-- Tâches Communication (taches 39-44)
(39,1),(39,27),(39,28),    -- site web
(40,27),(40,28),           -- campagne RS
(41,27),(41,28),           -- vidéo témoignages
(42,1),(42,27),            -- rapport annuel
(43,28),                   -- newsletter
(44,27),(44,28),           -- charte graphique

-- Tâches Admin (taches 45-50)
(45,1),(45,29),(45,30),    -- réunion direction
(46,29),(46,30),           -- archivage
(47,1),(47,29),            -- assurances
(48,29),(48,30),           -- fournitures
(49,1),(49,29),            -- CR partenaires
(50,1),(50,29),(50,30),    -- visites institutionnelles

-- Tâches en retard (taches 51-55)
(51,7),(51,6),(51,11),     -- rapport évaluation externe
(52,6),(52,11),            -- registre risques
(53,3),(53,15),(53,16),    -- avances non justifiées
(54,8),(54,2),             -- formation gestion projet
(55,5),(55,22),(55,23);    -- permis de conduire

-- ============================================================
-- COMMENTAIRES
-- ============================================================
INSERT INTO `commentaires` (`tache_id`,`user_id`,`contenu`,`created_at`) VALUES
(1, 8,  'J\'ai commencé la révision de la section recrutement. Quelques points à clarifier avec la direction.', DATE_SUB(NOW(),INTERVAL 16 DAY)),
(1, 2,  'Bien, continuez. N\'oubliez pas d\'inclure le nouveau cadre réglementaire du travail de 2023.', DATE_SUB(NOW(),INTERVAL 15 DAY)),
(1, 12, 'Section congés mise à jour. Je passe à la gestion des sanctions.', DATE_SUB(NOW(),INTERVAL 10 DAY)),
(2, 2,  'Les offres d\'emploi ont été publiées sur LinkedIn et Indeed. Déjà 45 candidatures reçues.', DATE_SUB(NOW(),INTERVAL 12 DAY)),
(2, 8,  'Présélection en cours. 12 candidats retenus pour les entretiens téléphoniques.', DATE_SUB(NOW(),INTERVAL 8 DAY)),
(2, 12, 'Entretiens planifiés pour la semaine prochaine. Salles réservées.', DATE_SUB(NOW(),INTERVAL 5 DAY)),
(9, 15, 'Toutes les pièces comptables Q1 sont centralisées. Démarrage de la saisie.', DATE_SUB(NOW(),INTERVAL 9 DAY)),
(9, 3,  'Assurez-vous que les provisions pour risques sont bien comptabilisées cette fois.', DATE_SUB(NOW(),INTERVAL 8 DAY)),
(9, 16, 'États financiers à 75%. Il reste les notes annexes à rédiger.', DATE_SUB(NOW(),INTERVAL 3 DAY)),
(16,4,  'Tests de migration effectués en environnement de recette. Pas d\'anomalie détectée.', DATE_SUB(NOW(),INTERVAL 20 DAY)),
(16,18, 'Configuration Apache et PHP 8.2 terminée. Base de données migrée à 90%.', DATE_SUB(NOW(),INTERVAL 12 DAY)),
(16,4,  'Migration planifiée ce week-end pour limiter l\'impact. Équipe en alerte.', DATE_SUB(NOW(),INTERVAL 5 DAY)),
(17,9,  'Maquettes des tableaux de bord validées par la direction. Développement en cours.', DATE_SUB(NOW(),INTERVAL 22 DAY)),
(17,18, 'Module export PDF intégré. Reste les graphiques dynamiques.', DATE_SUB(NOW(),INTERVAL 8 DAY)),
(24,10, 'Inventaire site Niamey terminé : 342 articles recensés dont 28 en mauvais état.', DATE_SUB(NOW(),INTERVAL 7 DAY)),
(24,22, 'Inventaire Agadez en cours. Dépôt principal fait, reste le dépôt secondaire.', DATE_SUB(NOW(),INTERVAL 4 DAY)),
(24,5,  'Rapport d\'inventaire à produire pour lundi. Mettre en évidence les articles obsolètes.', DATE_SUB(NOW(),INTERVAL 2 DAY)),
(33,6,  'Briefing sécurité effectué avec toute l\'équipe avant départ. Go pour le déploiement.', DATE_SUB(NOW(),INTERVAL 10 DAY)),
(33,24, 'Installation à Tillabéry terminée. Équipe opérationnelle depuis hier.', DATE_SUB(NOW(),INTERVAL 4 DAY)),
(37,6,  'Mission à Diffa lancée. Rencontres avec les autorités locales positives.', DATE_SUB(NOW(),INTERVAL 5 DAY)),
(37,24, 'Visite des sites de distribution effectuée. Rédaction du rapport en cours.', DATE_SUB(NOW(),INTERVAL 2 DAY)),
(51,7,  'Le rapport externe a été reçu il y a 2 semaines. La synthèse est en attente de validation.', DATE_SUB(NOW(),INTERVAL 10 DAY)),
(51,6,  'J\'ai transmis mes commentaires. En attente de retour de la cheffe de mission.', DATE_SUB(NOW(),INTERVAL 6 DAY)),
(53,3,  'Relances envoyées à 8 agents. 3 ont régularisé. Il en reste 5.', DATE_SUB(NOW(),INTERVAL 12 DAY)),
(53,15, 'Mise en demeure préparée pour les 5 agents récalcitrants.', DATE_SUB(NOW(),INTERVAL 5 DAY)),
(29,10, 'Vérification des stocks médicaux : 3 références expirées identifiées et mises de côté.', DATE_SUB(NOW(),INTERVAL 10 DAY)),
(29,22, 'Commande de réapprovisionnement passée. Livraison prévue dans 5 jours.', DATE_SUB(NOW(),INTERVAL 5 DAY)),
(39,27, 'Structure du site validée. Développement des pages principales en cours.', DATE_SUB(NOW(),INTERVAL 15 DAY)),
(39,28, 'Charte graphique intégrée. Il reste les pages projets et témoignages.', DATE_SUB(NOW(),INTERVAL 7 DAY));

-- ============================================================
-- HISTORIQUE
-- ============================================================
INSERT INTO `historique` (`tache_id`,`user_id`,`champ`,`ancienne_val`,`nouvelle_val`,`action`,`created_at`) VALUES
(1, 2,  'statut',     'pas_fait',   'en_cours',   'modification', DATE_SUB(NOW(),INTERVAL 18 DAY)),
(1, 8,  'pourcentage','0',          '30',         'modification', DATE_SUB(NOW(),INTERVAL 14 DAY)),
(1, 12, 'pourcentage','30',         '60',         'modification', DATE_SUB(NOW(),INTERVAL 10 DAY)),
(2, 2,  'statut',     'pas_fait',   'en_cours',   'modification', DATE_SUB(NOW(),INTERVAL 14 DAY)),
(2, 2,  'priorite',   'normale',    'urgente',    'modification', DATE_SUB(NOW(),INTERVAL 13 DAY)),
(4, 8,  'statut',     'en_cours',   'termine',    'modification', DATE_SUB(NOW(),INTERVAL 5 DAY)),
(4, 8,  'pourcentage','90',         '100',        'modification', DATE_SUB(NOW(),INTERVAL 5 DAY)),
(8, 8,  'statut',     'en_cours',   'termine',    'modification', DATE_SUB(NOW(),INTERVAL 20 DAY)),
(9, 15, 'statut',     'pas_fait',   'en_cours',   'modification', DATE_SUB(NOW(),INTERVAL 10 DAY)),
(9, 3,  'pourcentage','50',         '75',         'modification', DATE_SUB(NOW(),INTERVAL 3 DAY)),
(12,15, 'statut',     'en_cours',   'termine',    'modification', DATE_SUB(NOW(),INTERVAL 10 DAY)),
(13,15, 'statut',     'en_cours',   'termine',    'modification', DATE_SUB(NOW(),INTERVAL 15 DAY)),
(16,4,  'statut',     'pas_fait',   'en_cours',   'modification', DATE_SUB(NOW(),INTERVAL 22 DAY)),
(16,18, 'pourcentage','50',         '80',         'modification', DATE_SUB(NOW(),INTERVAL 5 DAY)),
(18,9,  'statut',     'en_cours',   'termine',    'modification', DATE_SUB(NOW(),INTERVAL 10 DAY)),
(23,9,  'statut',     'en_cours',   'termine',    'modification', DATE_SUB(NOW(),INTERVAL 25 DAY)),
(24,5,  'statut',     'pas_fait',   'en_cours',   'modification', DATE_SUB(NOW(),INTERVAL 9 DAY)),
(24,10, 'pourcentage','50',         '88',         'modification', DATE_SUB(NOW(),INTERVAL 2 DAY)),
(25,10, 'statut',     'en_cours',   'termine',    'modification', DATE_SUB(NOW(),INTERVAL 20 DAY)),
(30,5,  'statut',     'en_cours',   'termine',    'modification', DATE_SUB(NOW(),INTERVAL 8 DAY)),
(32,11, 'statut',     'en_cours',   'termine',    'modification', DATE_SUB(NOW(),INTERVAL 12 DAY)),
(33,6,  'statut',     'pas_fait',   'en_cours',   'modification', DATE_SUB(NOW(),INTERVAL 11 DAY)),
(33,11, 'pourcentage','40',         '72',         'modification', DATE_SUB(NOW(),INTERVAL 3 DAY)),
(36,11, 'statut',     'en_cours',   'termine',    'modification', DATE_SUB(NOW(),INTERVAL 6 DAY)),
(40,27, 'statut',     'en_cours',   'termine',    'modification', DATE_SUB(NOW(),INTERVAL 30 DAY)),
(43,28, 'statut',     'en_cours',   'termine',    'modification', DATE_SUB(NOW(),INTERVAL 15 DAY)),
(45,29, 'statut',     'en_cours',   'termine',    'modification', DATE_SUB(NOW(),INTERVAL 20 DAY)),
(49,1,  'statut',     'en_cours',   'termine',    'modification', DATE_SUB(NOW(),INTERVAL 18 DAY));

-- ============================================================
-- NOTIFICATIONS
-- ============================================================
INSERT INTO `notifications` (`user_id`,`type`,`titre`,`message`,`lien`,`lu`,`created_at`) VALUES
(2, 'info',    'Nouvelle tâche assignée',    'La tâche "Campagne de recrutement Q2 2024" vous a été assignée.',  '/taskflow/pages/tasks/view.php?id=2',  0, DATE_SUB(NOW(),INTERVAL 15 DAY)),
(8, 'info',    'Nouvelle tâche assignée',    'La tâche "Révision du manuel RH" vous a été assignée.',            '/taskflow/pages/tasks/view.php?id=1',  1, DATE_SUB(NOW(),INTERVAL 18 DAY)),
(8, 'warning', 'Tâche bientôt échue',        'La tâche "Mise à jour des contrats" est due dans 3 jours.',        '/taskflow/pages/tasks/view.php?id=5',  0, DATE_SUB(NOW(),INTERVAL 2 DAY)),
(3, 'warning', 'Tâche bientôt échue',        'La tâche "Clôture comptable Q1" est due dans 3 jours.',            '/taskflow/pages/tasks/view.php?id=9',  0, DATE_SUB(NOW(),INTERVAL 1 DAY)),
(3, 'danger',  'Tâche en retard',            'La tâche "Récupération des avances" est en retard de 7 jours.',    '/taskflow/pages/tasks/view.php?id=53', 0, DATE_SUB(NOW(),INTERVAL 1 DAY)),
(4, 'danger',  'Migration critique',         'La tâche "Migration vers nouveau serveur" est due demain.',         '/taskflow/pages/tasks/view.php?id=16', 0, DATE_SUB(NOW(),INTERVAL 1 DAY)),
(5, 'warning', 'Inventaire en cours',        'L\'inventaire général doit être finalisé demain.',                  '/taskflow/pages/tasks/view.php?id=24', 0, DATE_SUB(NOW(),INTERVAL 1 DAY)),
(6, 'danger',  'Tâche en retard',            'La tâche "Validation rapport évaluation" est en retard de 5 jours.','/taskflow/pages/tasks/view.php?id=51', 0, DATE_SUB(NOW(),INTERVAL 1 DAY)),
(7, 'info',    'Rapport en attente',         'Le rapport d\'évaluation externe attend votre validation.',         '/taskflow/pages/tasks/view.php?id=51', 0, DATE_SUB(NOW(),INTERVAL 3 DAY)),
(9, 'success', 'Tâche terminée',             'La tâche "Maintenance préventive" a été marquée comme terminée.',  '/taskflow/pages/tasks/view.php?id=23', 1, DATE_SUB(NOW(),INTERVAL 25 DAY)),
(10,'success', 'Convoi terminé',             'Le convoi Agadez a été livré avec succès.',                        '/taskflow/pages/tasks/view.php?id=25', 1, DATE_SUB(NOW(),INTERVAL 20 DAY)),
(11,'info',    'Nouvelle tâche assignée',    'La tâche "Mise en place système monitoring" vous a été assignée.', '/taskflow/pages/tasks/view.php?id=34', 0, DATE_SUB(NOW(),INTERVAL 11 DAY)),
(15,'info',    'Commentaire sur votre tâche','Un nouveau commentaire a été ajouté à "Clôture comptable Q1".',    '/taskflow/pages/tasks/view.php?id=9',  0, DATE_SUB(NOW(),INTERVAL 3 DAY)),
(18,'info',    'Nouvelle tâche assignée',    'La tâche "Développement module reporting" vous a été assignée.',   '/taskflow/pages/tasks/view.php?id=17', 1, DATE_SUB(NOW(),INTERVAL 28 DAY)),
(24,'warning', 'Déploiement terrain',        'Le déploiement zone Tillabéry est dû dans 2 jours.',               '/taskflow/pages/tasks/view.php?id=33', 0, DATE_SUB(NOW(),INTERVAL 1 DAY)),
(27,'info',    'Site web en cours',          'La tâche "Refonte site web" avance bien selon le planning.',        '/taskflow/pages/tasks/view.php?id=39', 1, DATE_SUB(NOW(),INTERVAL 10 DAY)),
(1, 'info',    'Nouveau utilisateur',        '30 nouveaux comptes utilisateurs ont été créés avec succès.',       '/taskflow/pages/users/list.php',       1, DATE_SUB(NOW(),INTERVAL 20 DAY)),
(1, 'warning', 'Tâches en retard',           '5 tâches sont actuellement en retard dans le système.',             '/taskflow/pages/tasks/list.php',       0, DATE_SUB(NOW(),INTERVAL 1 DAY));

SET FOREIGN_KEY_CHECKS = 1;
