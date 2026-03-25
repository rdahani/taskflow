<?php
/**
 * Édition utilisateur : même formulaire que la création (create.php).
 */
if (empty($_GET['id']) || (int) $_GET['id'] < 1) {
    require_once __DIR__ . '/../../includes/layout_init.php';
    flashMessage('error', 'Utilisateur introuvable.');
    redirect('/pages/users/list.php');
}
require_once __DIR__ . '/create.php';
