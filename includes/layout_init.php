<?php
/**
 * Bootstrap session / auth / BDD sans envoyer de HTML.
 * À inclure en premier sur les pages qui appellent redirect() avant le layout.
 */
if (defined('TASKFLOW_BOOTSTRAPPED')) {
    return;
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/dm.php';

requireLogin();
$currentUser    = currentUser();
$notifCount     = getUnreadNotificationsCount($currentUser['id']);
$dmUnreadCount  = 0;
try {
    $dmUnreadCount = dmGetTotalUnread(getDB(), (int) $currentUser['id']);
} catch (Throwable $e) {
    // Table dm absente (migration non jouée) — on ignore silencieusement
}

define('TASKFLOW_BOOTSTRAPPED', true);
