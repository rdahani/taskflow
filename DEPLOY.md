# Déploiement TaskFlow en production

## Avant la mise en ligne

1. **PHP** 7.4+ ou 8.x avec extensions : `pdo_mysql`, `json`, `mbstring`, `session`.
2. **MySQL / MariaDB** : base `utf8mb4_unicode_ci`.
3. **HTTPS** : fortement recommandé ; le cookie de session passera en `Secure` automatiquement si `APP_URL` commence par `https://`.

## Étapes

### 1. Transférer les fichiers

Envoyer tout le projet sur le serveur (FTP, Git, CI/CD), **sauf** les fichiers listés dans `.gitignore` (pas de `config.local.php` du poste de dev si non adapté).

### 2. Base de données

```bash
mysql -u UTILISATEUR -p -e "CREATE DATABASE taskflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u UTILISATEUR -p taskflow < config/database.sql
```

Importer éventuellement `config/seeds.sql` si vous l’utilisez.

Si la base **existait déjà** avant l’ajout des notifications par e-mail, exécuter aussi :

```bash
mysql -u UTILISATEUR -p taskflow < config/migrations/001_users_notify_email.sql
```

Pour le **journal d’audit**, les **rappels d’échéance** (cron) et les **index** de performance :

```bash
mysql -u UTILISATEUR -p taskflow < config/migrations/002_audit_reminders_indexes.sql
```

*(Si des index existent déjà, MySQL peut signaler une erreur « duplicate key name » : ignorer ou supprimer les lignes `CREATE INDEX` concernées.)*

Pour le **chat sur les tâches** :

```bash
mysql -u UTILISATEUR -p taskflow < config/migrations/003_task_chat.sql
```

Le chat utilise du **long polling** (connexion ouverte jusqu’à ~30–35 s ou arrivée d’un message) pour une livraison quasi instantanée, et des fichiers dans le répertoire temporaire système (`tf_chat_typing/`) pour l’indicateur « en train d’écrire » — le compte PHP doit pouvoir y écrire (cas standard).

Pour la **messagerie privée** (page Messages, hors tâches) :

```bash
mysql -u UTILISATEUR -p taskflow < config/migrations/004_direct_messages.sql
```

Même principe de long polling via `api/dm_chat.php` et fichiers `tf_dm_typing/` pour « en train d’écrire ».

### 3. Configuration

```bash
cp config/config.local.example.php config/config.local.php
```

Éditer `config/config.local.php` :

- `APP_ENV` → `production`
- `APP_DEBUG` → `false`
- `APP_URL` → URL publique exacte, **sans slash final** (ex. `https://domaine.tld/taskflow`)
- `DB_*` → identifiants MySQL dédiés (compte avec droits **uniquement** sur la base `taskflow`)

#### Notifications par e-mail (optionnel)

Les utilisateurs reçoivent les mêmes alertes que dans l’app (nouvelle tâche, commentaire, etc.) **si** :

1. Dans `config.local.php` : `MAIL_NOTIFICATIONS` → `true`.
2. Soit **SMTP** (`MAIL_USE_SMTP` + hôte, port, identifiants, chiffrement `tls` ou `ssl`), soit **sendmail / `mail()`** correctement configuré sur le serveur (`MAIL_USE_SMTP` → `false`).
3. Adresse **`MAIL_FROM`** autorisée par votre fournisseur (souvent alignée sur le compte SMTP).

Voir les clés commentées dans `config/config.local.example.php`. Les utilisateurs peuvent désactiver les e-mails dans **Mon profil** (ou l’admin dans la fiche utilisateur).

### 4. Droits fichiers

```bash
chmod 755 uploads
chmod 640 config/config.local.php
```

Le serveur web doit pouvoir écrire dans `uploads/` pour les pièces jointes.

### 5. Apache

- Activer `mod_rewrite` si besoin.
- Le `.htaccess` à la racine désactive le listing et bloque l’accès direct à certains types de fichiers.
- Le dossier `config/` est protégé par son propre `.htaccess`.

### 6. Nginx (sans .htaccess)

Refuser l’accès à `config/` et aux fichiers `.sql` via la configuration du `server` (exemple) :

```nginx
location ^~ /taskflow/config/ { deny all; return 404; }
location ~* \.(sql|local\.php)$ { deny all; return 404; }
```

Adapter le préfixe `/taskflow/` selon votre installation.

### 7. Cron — rappels d’échéance

Une fois par jour (ex. 8 h), lancer le script PHP en ligne de commande (adapter le chemin PHP et le projet) :

```bash
/usr/bin/php /var/www/taskflow/scripts/cron_reminders.php
```

Exemple crontab : `0 8 * * * /usr/bin/php /var/www/taskflow/scripts/cron_reminders.php >> /var/log/taskflow-cron.log 2>&1`

Les utilisateurs assignés reçoivent une notification (et un e-mail si les notifications mail sont activées) pour les tâches dont l’échéance est **aujourd’hui**, **demain** ou **dans 3 jours** (une fois par jour et par tâche grâce à la table `reminder_sent`).

### 8. Sauvegardes (exemple)

```bash
mysqldump -u UTILISATEUR -p taskflow | gzip -c > taskflow_$(date +%Y%m%d).sql.gz
tar czf taskflow_uploads_$(date +%Y%m%d).tar.gz uploads/
```

### 9. Tests PHPUnit (optionnel)

```bash
composer install
vendor/bin/phpunit
```

### 10. Sécurité après installation

- Changer le mot de passe du compte administrateur par défaut.
- Vérifier les journaux PHP / serveur en cas d’erreur 503 (connexion BD).
- Sauvegardes régulières de la base et du dossier `uploads/`.

## Hébergement **LWS** (mutualisé)

L’objectif est le même que ci-dessus ; chez **LWS** les points usuels sont les suivants.

1. **FTP / fichiers**  
   Dans l’espace client → votre hébergement → **Compte FTP** (hôte, identifiant, mot de passe).  
   Transférer tout le projet (FileZilla, WinSCP, etc.) dans le dossier racine du site (**`www/`** ou **`public_html/`**), ou dans un sous-dossier (ex. `www/taskflow/`) si l’application ne doit pas occuper la racine du domaine.

2. **Version PHP**  
   Panel LWS → **Bases de données & PHP** → **Configuration PHP** : choisir **PHP 8.1** ou **8.2** (ou 8.3 si disponible) pour le dossier où se trouve TaskFlow. Vérifier que les extensions **`pdo_mysql`**, **`mbstring`**, **`json`**, **`session`** sont actives (souvent déjà le cas).

3. **Base MySQL**  
   Créer une base et un utilisateur MySQL depuis le panel, puis importer via **phpMyAdmin** :  
   - Fichier `config/database.sql`, puis les migrations `001` → `004` si vous n’utilisez pas uniquement le script complet à jour.

4. **Configuration `config.local.php` sur le serveur**  
   Créer **sur le serveur uniquement** (ne pas commiter) :  
   `cp config/config.local.example.php config/config.local.php` puis éditer avec :  
   - `APP_ENV` = `production`, `APP_DEBUG` = `false`  
   - `APP_URL` = URL publique **exacte** (ex. `https://mondomaine.fr` ou `https://mondomaine.fr/taskflow` **sans** slash final)  
   - `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` = identifiants fournis par LWS (hôte souvent du type `mysqlXX.lwspanel.com` ou `localhost` — voir le mail / le panel)

5. **Droits**  
   Le dossier **`uploads/`** doit être **inscriptible** par PHP (chmod adapté selon les recommandations LWS, souvent `755` sur le dossier).

6. **Cron (rappels d’échéance)**  
   Si le panel propose des **tâches planifiées**, exécuter une fois par jour :  
   `/usr/bin/php /home/USERNAME/www/chemin/vers/taskflow/scripts/cron_reminders.php`  
   (adapter **USERNAME** et le chemin absolu ; le support LWS peut confirmer le binaire `php` CLI).

7. **Long polling (chat / messages)**  
   Les scripts restent ouverts jusqu’à ~30–45 s. Si LWS coupe la requête trop tôt, réduire `timeout` côté API ou contacter le support pour les limites **max_execution_time** / pare-feu.

---

## Publier le code sur **GitHub**

1. Sur [github.com](https://github.com) : **New repository** → nom du projet (ex. `taskflow`) → **sans** README/license si vous poussez un dépôt déjà rempli localement.

2. Sur votre machine (dans le dossier du projet) :

```bash
git init
git add .
git commit -m "Import initial TaskFlow"
git branch -M main
git remote add origin https://github.com/VOTRE_COMPTE/VOTRE_REPO.git
git push -u origin main
```

3. **Secrets** : ne jamais commiter `config/config.local.php`, mots de passe, ou clés. Ils sont listés dans `.gitignore`.

4. **Sur le serveur LWS**, vous pouvez ensuite déployer par **FTP**, ou en **clone Git** si LWS / un VPS vous fournit SSH + git (selon l’offre).

### Déploiement automatique **GitHub Actions → LWS** (FTP)

Le dépôt inclut `.github/workflows/deploy-lws.yml` : à chaque **push sur `main`** (ou lancement manuel dans l’onglet **Actions**), les fichiers sont synchronisés vers votre hébergement LWS par FTP.

**Ce qu’il faut savoir**

- Personne ne peut « se connecter à votre GitHub » à votre place sans **une authentification sur votre PC** (recommandé : **GitHub CLI** `gh auth login`, une fois). Ensuite vous pouvez lancer le script `scripts/setup-github-repo.ps1` pour créer le dépôt public et pousser `main`.
- Si le `git push` est **refusé** pour un fichier sous `.github/workflows/`, exécutez **`gh auth refresh -h github.com -s workflow`** puis validez dans le navigateur ; le jeton doit inclure la permission **workflow** pour publier les Actions.
- Les identifiants LWS ne doivent **pas** être dans le code : ils sont stockés dans les **secrets** du dépôt GitHub.

**Secrets à créer** (dépôt → **Settings** → **Secrets and variables** → **Actions** → **New repository secret**) :

| Nom | Rôle |
|-----|------|
| `FTP_SERVER` | Hôte FTP indiqué par LWS (ex. `ftp.clusterXXX.hosting.ovh.net` ou équivalent) |
| `FTP_USERNAME` | Identifiant FTP |
| `FTP_PASSWORD` | Mot de passe FTP |
| `CONFIG_LOCAL_PHP` | Texte **complet** du fichier `config/config.local.php` de production (copier-coller **une fois** depuis un éditeur ; même contenu que sur le serveur, avec `APP_URL`, `DB_*`, etc.) |
| `FTP_SERVER_DIR` | **Recommandé** si le site n’est pas à la racine FTP : chemin du **répertoire racine web** avec slash final. Si vide (`./`), tout est déposé à la connexion FTP — souvent **le mauvais dossier** pour un sous-domaine. |

#### Sous-domaine (ex. `task.coopiconge.org`)

Le domaine principal et chaque sous-domaine ont en général un **dossier dédié** sur l’hébergement (LWS Panel → domaines / sous-domaines : colonne **répertoire** ou **racine**).

1. Notez ce chemin tel qu’il apparaît dans le panel et dans le FTP (ex. `task.coopiconge.org/`, `www/task.coopiconge.org/`, `htdocs/...` — LWS varie).
2. Créez ou mettez à jour le secret GitHub **`FTP_SERVER_DIR`** avec ce chemin et un **slash final** (ex. `task.coopiconge.org/`).
3. Gardez dans **`CONFIG_LOCAL_PHP`** (secret GitHub) : `'APP_URL' => 'https://task.coopiconge.org'` **sans** sous-chemin (`…/taskflow`), si l’app est servie à la racine du sous-domaine.
4. Si une précédente synchro a envoyé les fichiers **à la racine FTP** par erreur : déplacez-les dans le dossier du sous-domaine (gestionnaire de fichiers ou FileZilla), ou supprimez-les à la racine puis **relancez Deploy LWS** après avoir renseigné `FTP_SERVER_DIR`.

La synchronisation **exclut** le dossier `uploads/` pour ne pas écraser les fichiers déjà déposés par les utilisateurs sur le serveur. Vérifiez une première fois que le dossier `uploads/` existe côté LWS et est inscriptible par PHP.

Le workflow utilise par défaut **FTPS explicite** sur le port `21` (souvent requis par LWS). Si votre offre n’utilise que le FTP « clair », remplacez dans `deploy-lws.yml` : retirez `protocol: ftps`, `port` et `security: loose`, ou mettez `protocol: ftp`.

#### GitHub Actions : `FTPError: 530 Login authentication failed`

Le serveur refuse l’identifiant ou le mot de passe FTP (ce n’est pas une erreur PHP / TaskFlow).

1. Dans **l’espace client LWS**, ouvrez **Compte FTP** : vérifiez le **nom d’utilisateur exact** (parfois différent du préfixe de la base MySQL) et **réinitialisez le mot de passe FTP** si besoin.
2. Testez la même combinaison avec **FileZilla** (ou équivalent) depuis votre PC : si la connexion échoue aussi, le mot de passe ou l’identifiant est incorrect côté LWS.
3. Si FileZilla fonctionne mais pas GitHub Actions, vérifiez une éventuelle **restriction d’accès FTP par IP** dans le panel : les runners GitHub ont des IP publiques variables ; il faudrait autoriser tout le monde pour le FTP ou utiliser un autre mode de déploiement (ZIP manuel, autre hébergeur avec SSH, etc.).
4. Mettez à jour les secrets **`FTP_USERNAME`** et **`FTP_PASSWORD`** sur GitHub, sans espace ni retour à la ligne en trop (rééditer le secret après copier-coller).

**Base de données** : le workflow ne lance pas les migrations MySQL ; importez `config/database.sql` et les migrations `001` → `004` une fois via phpMyAdmin (ou équivalent), comme décrit plus haut.

## Développement local

Sans `config.local.php`, l’application utilise les valeurs par défaut (`http://localhost/taskflow`, MySQL `root` sans mot de passe), comme auparavant.
