<?php
/**
 * Référence des rôles (ENUM côté base — libellés dans config.php).
 */
require_once __DIR__ . '/../../includes/layout_init.php';

if (!isAdmin()) {
    flashMessage('error', 'Accès réservé aux administrateurs.');
    redirect('/index.php');
}

$pageTitle   = 'Rôles & permissions';
$breadcrumbs = [
    ['label' => 'Accueil', 'url' => APP_URL . '/index.php'],
    ['label' => 'Rôles', 'url' => ''],
];

$roleHelp = [
    'employe' => [
        'Voir les tâches qui lui sont assignées (ou qu’il a créées).',
        'Peut mettre à jour le statut des tâches qui lui sont assignées.',
    ],
    'superviseur' => [
        'Voit les tâches de son département.',
        'Peut créer et modifier les tâches de son département.',
    ],
    'chef_dept' => [
        'Comme le superviseur sur les tâches du département.',
        'Accès à la page Rapports pour son département.',
    ],
    'cheffe_mission' => [
        'Voit l’ensemble des tâches (vue large, pilotage).',
        'Ne peut pas modifier les tâches (rôle majoritairement en lecture).',
    ],
    'admin' => [
        'Accès complet : utilisateurs, audit, bases, départements, toutes les tâches.',
        'Peut tout créer, modifier et supprimer selon les écrans prévus.',
    ],
];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div>
    <div class="page-title">Rôles & permissions</div>
    <div class="page-subtitle">Les profils sont fixés dans l’application ; le libellé affiché vient du fichier de configuration</div>
  </div>
</div>

<div class="card" style="margin-bottom:16px">
  <div class="card-body" style="font-size:13px;color:var(--text2);line-height:1.5">
    Les rôles sont stockés en base sur chaque utilisateur (valeur technique, ex. <code>chef_dept</code>).
    Pour ajouter un nouveau type de profil, il faut modifier le schéma SQL de la table <code>users</code> et le code (constante <code>ROLES</code> dans <code>config/config.php</code>).
  </div>
</div>

<div class="card">
  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>Identifiant</th>
          <th>Libellé</th>
          <th>Permissions principales</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach (ROLES as $key => $label):
            $bullets = $roleHelp[$key] ?? ['—'];
        ?>
        <tr>
          <td><code style="font-size:12px"><?= sanitize($key) ?></code></td>
          <td style="font-weight:600"><?= sanitize($label) ?></td>
          <td style="font-size:13px;color:var(--text2);line-height:1.45">
            <ul style="margin:0;padding-left:1.2rem">
              <?php foreach ($bullets as $line): ?>
              <li><?= sanitize($line) ?></li>
              <?php endforeach; ?>
            </ul>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
