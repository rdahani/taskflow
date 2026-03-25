<?php
/**
 * TaskFlow — configuration serveur (production / préproduction)
 *
 * 1. Copier ce fichier :  config.local.php
 * 2. Renseigner APP_URL (sans slash final) et les accès MySQL
 * 3. Ne jamais commiter config.local.php (voir .gitignore)
 *
 * Les clés peuvent aussi être définies via variables d'environnement
 * du même nom (ex. APP_URL, DB_PASS) si votre hébergeur les supporte.
 */
return [
    // production | development
    'APP_ENV'   => 'production',
    'APP_DEBUG' => false,

    // URL publique exacte de l'application (HTTPS recommandé)
    'APP_URL'   => 'https://votre-domaine.tld/chemin/taskflow',

    'DB_HOST'   => 'localhost',
    'DB_NAME'   => 'taskflow',
    'DB_USER'   => 'taskflow_user',
    'DB_PASS'   => 'changez_moi',

    // Optionnel : forcer le cookie de session en Secure (sinon : auto si APP_URL est en https)
    // 'SESSION_COOKIE_SECURE' => true,

    // Optionnel : durée d’inactivité session (secondes), défaut 1800
    // 'SESSION_TIMEOUT' => 1800,

    // --- Notifications par e-mail (même contenu que dans l’app) ---
    // 'MAIL_NOTIFICATIONS' => true,
    // 'MAIL_FROM'          => 'noreply@votre-domaine.tld',
    // 'MAIL_FROM_NAME'     => 'TaskFlow',
    // SMTP (recommandé en production) :
    // 'MAIL_USE_SMTP'      => true,
    // 'MAIL_SMTP_HOST'     => 'smtp.votre-hebergeur.com',
    // 'MAIL_SMTP_PORT'     => 587,
    // 'MAIL_SMTP_USER'     => 'votre_compte_smtp',
    // 'MAIL_SMTP_PASS'     => 'mot_de_passe_smtp',
    // 'MAIL_SMTP_ENCRYPTION' => 'tls', // ou 'ssl' (port 465) ou '' (sans TLS, ex. port 25 local)
    // Sans SMTP : laisser MAIL_USE_SMTP à false et configurer sendmail / mail() sur le serveur
];
