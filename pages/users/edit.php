<?php
/**
 * Édition utilisateur : même formulaire que la création (create.php).
 *
 * En POST : certains hébergeurs / proxys ne remplissent pas $_GET (query string) ;
 * le champ caché id doit primer. create.php reçoit l’id validé via TF_EDIT_USER_ID.
 */
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'POST') {
    $editUserId = (int) ($_POST['id'] ?? 0);
    if ($editUserId < 1) {
        $editUserId = (int) ($_GET['id'] ?? 0);
    }
} else {
    $editUserId = (int) ($_GET['id'] ?? 0);
}
if ($editUserId < 1) {
    require_once __DIR__ . '/../../includes/layout_init.php';
    flashMessage('error', 'Utilisateur introuvable.');
    redirect('/pages/users/list.php');
}
define('TF_EDIT_USER_ID', $editUserId);
require_once __DIR__ . '/create.php';
