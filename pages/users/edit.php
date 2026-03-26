<?php
/**
 * Édition utilisateur : même formulaire que la création (create.php).
 */
$editUserId = (int) ($_GET['id'] ?? 0);
if ($editUserId < 1 && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $editUserId = (int) ($_POST['id'] ?? 0);
}
if ($editUserId < 1) {
    require_once __DIR__ . '/../../includes/layout_init.php';
    flashMessage('error', 'Utilisateur introuvable.');
    redirect('/pages/users/list.php');
}
require_once __DIR__ . '/create.php';
