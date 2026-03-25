# TaskFlow — Guide d'installation

## Prérequis
- PHP 7.4+ ou 8.x
- MySQL 5.7+ ou MariaDB 10+
- Serveur web Apache/Nginx (ou XAMPP/WAMP/MAMP)

---

## Installation en 5 étapes

### 1. Copier les fichiers
Copiez le dossier `taskflow/` dans le répertoire web de votre serveur :
- XAMPP : `C:/xampp/htdocs/taskflow/`
- WAMP  : `C:/wamp/www/taskflow/`
- Linux : `/var/www/html/taskflow/`

### 2. Créer la base de données
```sql
CREATE DATABASE taskflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 3. Importer le schéma SQL
Via phpMyAdmin ou en ligne de commande :
```bash
mysql -u root -p taskflow < taskflow/config/database.sql
```

### 4. Configurer l’application
**Recommandé (tous environnements)** : copier le fichier d’exemple et l’adapter.
```bash
cp config/config.local.example.php config/config.local.php
```
Puis éditer `config/config.local.php` : `APP_URL`, `DB_*`, et en production `APP_ENV` = `production`, `APP_DEBUG` = `false`.

Sans ce fichier, les valeurs par défaut restent celles du développement local (`http://localhost/taskflow`, MySQL `root` sans mot de passe).

**Déploiement serveur** : voir le guide détaillé [`DEPLOY.md`](DEPLOY.md).

### 5. Permissions dossier uploads
```bash
chmod 755 taskflow/uploads/
```

---

## Compte par défaut
- **Email** : `admin@taskflow.ne`
- **Mot de passe** : `Admin@2024`

⚠️ **Changez le mot de passe après la première connexion !**

---

## Structure des fichiers

```
taskflow/
├── config/
│   ├── config.php       — Configuration générale
│   ├── database.php     — Connexion PDO
│   └── database.sql     — Script SQL de création
├── includes/
│   ├── auth.php         — Authentification & permissions
│   ├── functions.php    — Fonctions utilitaires
│   ├── header.php       — En-tête HTML
│   └── footer.php       — Pied de page HTML
├── pages/
│   ├── tasks/
│   │   ├── list.php     — Liste des tâches
│   │   ├── create.php   — Créer une tâche
│   │   ├── edit.php     — Modifier une tâche
│   │   ├── view.php     — Détail d'une tâche
│   │   └── kanban.php   — Vue Kanban
│   ├── users/
│   │   ├── list.php     — Liste utilisateurs
│   │   ├── create.php   — Créer un utilisateur
│   │   ├── edit.php     — Modifier un utilisateur
│   │   └── profile.php  — Profil utilisateur
│   └── reports/
│       └── index.php    — Rapports & statistiques
├── api/
│   ├── tasks.php        — API REST tâches
│   ├── users.php        — API REST utilisateurs
│   └── notifications.php— API notifications
├── assets/
│   ├── css/
│   │   ├── style.css    — Styles principaux
│   │   └── themes.css   — Thèmes & animations
│   └── js/
│       └── app.js       — JavaScript principal
├── uploads/             — Fichiers téléversés
├── index.php            — Tableau de bord
├── login.php            — Page de connexion
└── logout.php           — Déconnexion
```

---

## Rôles utilisateurs

| Rôle | Accès |
|------|-------|
| Employé | Ses tâches uniquement |
| Superviseur | Tâches de son équipe + créer/assigner |
| Chef de département | Tout son département |
| Cheffe de mission | Vue globale tous départements |
| Admin | Accès total + gestion utilisateurs |

---

## Statuts des tâches

| Statut | Couleur | Description |
|--------|---------|-------------|
| Pas encore fait | Gris | Tâche créée non démarrée |
| En cours | Bleu | En cours de traitement |
| En attente | Orange | Bloquée / en attente |
| Terminé | Vert | Complétée et validée |
| Annulé | Rouge foncé | Annulée définitivement |
| En retard | Rouge vif | Échéance dépassée (automatique) |
| Rejeté | Violet | Refusée par le superviseur |

---

## Technologies utilisées
- **Frontend** : HTML5, CSS3, JavaScript (Vanilla)
- **Backend** : PHP 7.4+
- **Base de données** : MySQL/MariaDB
- **Librairies** :
  - Chart.js 4 (graphiques)
  - SortableJS (Kanban drag & drop)
  - Flatpickr (date pickers)
  - Google Fonts (Inter)
