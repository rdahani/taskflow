<?php
/**
 * Surcharge développement sur la même copie du projet que la prod (config.local.php).
 *
 * 1. Copier vers config.local.dev.php (non versionné)
 * 2. Lancer Apache/MySQL XAMPP, ouvrir http://localhost/taskflow/pages/users/list.php
 *
 * Les clés absentes gardent les valeurs de config.local.php.
 */
return [
    'APP_ENV'   => 'development',
    'APP_DEBUG' => true,

    'APP_URL'   => 'http://localhost/taskflow',

    'DB_HOST'   => '127.0.0.1',
    'DB_NAME'   => 'taskflow',
    'DB_USER'   => 'root',
    'DB_PASS'   => '',

    'SESSION_COOKIE_SECURE' => false,
];
