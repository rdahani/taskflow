<?php
// ============================================================
//  TaskFlow — Configuration générale
// ============================================================

$GLOBALS['tf_local'] = [];
if (is_readable(__DIR__ . '/config.local.php')) {
    $loaded = require __DIR__ . '/config.local.php';
    if (is_array($loaded)) {
        $GLOBALS['tf_local'] = $loaded;
    }
}

/**
 * @param mixed $default
 * @return mixed
 */
function tf_cfg(string $key, $default = null) {
    $tf_local = $GLOBALS['tf_local'] ?? [];
    if (!is_array($tf_local)) {
        return $default;
    }
    if (array_key_exists($key, $tf_local)) {
        return $tf_local[$key];
    }
    $v = getenv($key);
    if ($v !== false && $v !== '') {
        return $v;
    }
    return $default;
}

function tf_cfg_bool(string $key, bool $default): bool {
    $v = tf_cfg($key, null);
    if ($v === null) {
        return $default;
    }
    if (is_bool($v)) {
        return $v;
    }
    $s = strtolower((string) $v);
    return in_array($s, ['1', 'true', 'yes', 'on'], true);
}

// --- Environnement & affichage des erreurs ---
$appEnv = tf_cfg('APP_ENV', 'development');
if (!is_string($appEnv) || $appEnv === '') {
    $appEnv = 'development';
}
define('APP_ENV', $appEnv);
define('APP_DEBUG', tf_cfg_bool('APP_DEBUG', $appEnv !== 'production'));

if (!APP_DEBUG) {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

define('APP_NAME', (string) tf_cfg('APP_NAME', 'TaskFlow'));
define('APP_VERSION', (string) tf_cfg('APP_VERSION', '1.0.0'));
define('APP_URL', rtrim((string) tf_cfg('APP_URL', 'http://localhost/taskflow'), '/'));
define('APP_LANG', (string) tf_cfg('APP_LANG', 'fr'));
define('SESSION_TIMEOUT', (int) tf_cfg('SESSION_TIMEOUT', 1800));

// Base de données (utilisé par config/database.php)
define('DB_HOST', (string) tf_cfg('DB_HOST', 'localhost'));
define('DB_NAME', (string) tf_cfg('DB_NAME', 'taskflow'));
define('DB_USER', (string) tf_cfg('DB_USER', 'root'));
define('DB_PASS', (string) tf_cfg('DB_PASS', ''));
define('DB_CHARSET', (string) tf_cfg('DB_CHARSET', 'utf8mb4'));

// Notifications par e-mail (désactivé par défaut — activer dans config.local.php)
define('MAIL_NOTIFICATIONS', tf_cfg_bool('MAIL_NOTIFICATIONS', false));
define('MAIL_FROM', (string) tf_cfg('MAIL_FROM', 'noreply@localhost'));
define('MAIL_FROM_NAME', (string) tf_cfg('MAIL_FROM_NAME', APP_NAME));
define('MAIL_USE_SMTP', tf_cfg_bool('MAIL_USE_SMTP', false));
define('MAIL_SMTP_HOST', (string) tf_cfg('MAIL_SMTP_HOST', ''));
define('MAIL_SMTP_PORT', (int) tf_cfg('MAIL_SMTP_PORT', 587));
define('MAIL_SMTP_USER', (string) tf_cfg('MAIL_SMTP_USER', ''));
define('MAIL_SMTP_PASS', (string) tf_cfg('MAIL_SMTP_PASS', ''));
/** tls (STARTTLS, port 587) | ssl (SMTPS, port 465) | chaîne vide (sans chiffrement, ex. port 25 local) */
define('MAIL_SMTP_ENCRYPTION', strtolower((string) tf_cfg('MAIL_SMTP_ENCRYPTION', 'tls')));

// Upload
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10 Mo
define('UPLOAD_ALLOWED', ['pdf','doc','docx','xls','xlsx','jpg','jpeg','png','gif','zip','rar']);

// Pagination
define('ITEMS_PER_PAGE', 20);

// Statuts tâches
define('TASK_STATUSES', [
    'pas_fait'   => ['label' => 'Pas encore fait', 'color' => '#6B7280', 'bg' => '#F9FAFB'],
    'en_cours'   => ['label' => 'En cours',        'color' => '#2563EB', 'bg' => '#EFF6FF'],
    'en_attente' => ['label' => 'En attente',      'color' => '#D97706', 'bg' => '#FFFBEB'],
    'termine'    => ['label' => 'Terminé',          'color' => '#16A34A', 'bg' => '#F0FDF4'],
    'annule'     => ['label' => 'Annulé',           'color' => '#991B1B', 'bg' => '#FEF2F2'],
    'en_retard'  => ['label' => 'En retard',        'color' => '#DC2626', 'bg' => '#FEE2E2'],
    'rejete'     => ['label' => 'Rejeté',           'color' => '#7C3AED', 'bg' => '#F5F3FF'],
]);

// Priorités
define('TASK_PRIORITIES', [
    'basse'    => ['label' => 'Basse',    'color' => '#6B7280'],
    'normale'  => ['label' => 'Normale',  'color' => '#2563EB'],
    'haute'    => ['label' => 'Haute',    'color' => '#D97706'],
    'urgente'  => ['label' => 'Urgente',  'color' => '#DC2626'],
]);

// Rôles
define('ROLES', [
    'employe'         => 'Employé',
    'superviseur'     => 'Superviseur',
    'chef_dept'       => 'Chef de département',
    'cheffe_mission'  => 'Cheffe de mission',
    'admin'           => 'Administrateur',
]);

// Bases géographiques
define('BASES', [
    'niamey'    => 'Niamey',
    'agadez'    => 'Agadez',
    'dosso'     => 'Dosso',
    'tillabery' => 'Tillabéry',
    'zinder'    => 'Zinder',
    'maradi'    => 'Maradi',
    'tahoua'    => 'Tahoua',
]);

// Timezone
date_default_timezone_set('Africa/Niamey');

// Cookie de session : Secure automatique si APP_URL est en https
$scheme = parse_url(APP_URL, PHP_URL_SCHEME);
$secureCookie = tf_cfg('SESSION_COOKIE_SECURE', null);
if ($secureCookie === null || $secureCookie === '') {
    $sessionSecure = ($scheme === 'https');
} else {
    $sessionSecure = tf_cfg_bool('SESSION_COOKIE_SECURE', false);
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_TIMEOUT,
        'path'     => '/',
        'secure'   => $sessionSecure,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

require_once __DIR__ . '/../includes/security_headers.php';
sendSecurityHeaders();

unset($GLOBALS['tf_local']);
