<?php
require_once __DIR__ . '/../../includes/layout_init.php';

if (!isAdmin()) {
    flashMessage('error', 'Accès réservé aux administrateurs.');
    redirect('/index.php');
}

$pageTitle   = 'Gestion des droits d\'accès';
$breadcrumbs = [
    ['label' => 'Accueil',         'url' => APP_URL . '/index.php'],
    ['label' => 'Administration',  'url' => ''],
    ['label' => 'Droits d\'accès', 'url' => ''],
];

$pdo = getDB();

$users = $pdo->query(
    "SELECT u.*, d.nom AS dept_nom
     FROM users u
     LEFT JOIN departements d ON d.id = u.departement_id
     ORDER BY u.actif DESC, u.nom ASC"
)->fetchAll();

$apiUrl = rtrim(APP_URL, '/') . '/api/users.php';

require_once __DIR__ . '/../../includes/header.php';
?>
<meta name="csrf" content="<?= csrfToken() ?>">

<div class="page-header">
  <div>
    <div class="page-title">Droits d'accès</div>
    <div class="page-subtitle"><?= count($users) ?> utilisateur<?= count($users) > 1 ? 's' : '' ?></div>
  </div>
  <a href="<?= APP_URL ?>/pages/users/create.php" class="btn btn-primary">
    <i class="fa-solid fa-user-plus"></i> Nouvel utilisateur
  </a>
</div>

<!-- ── Tableau des utilisateurs ── -->
<div class="card" style="margin-bottom:24px">
  <div class="card-header" style="display:flex;align-items:center;gap:10px">
    <i class="fa-solid fa-users-gear" style="color:var(--primary)"></i>
    <span class="card-title">Utilisateurs &amp; rôles</span>
    <span style="margin-left:auto;font-size:12px;color:var(--text3)">Les changements de rôle sont immédiats et journalisés.</span>
  </div>
  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>Utilisateur</th>
          <th>Email</th>
          <th>Département</th>
          <th>Rôle</th>
          <th>Statut</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
        <?php
          $roleColors = [
            'employe'        => '#6B7280',
            'superviseur'    => '#2563EB',
            'chef_dept'      => '#7C3AED',
            'cheffe_mission' => '#D97706',
            'admin'          => '#DC2626',
          ];
          $rc = $roleColors[$u['role']] ?? '#6B7280';
          $isSelf = ($u['id'] == $currentUser['id']);
        ?>
        <tr id="row-<?= $u['id'] ?>" style="<?= !$u['actif'] ? 'opacity:.6' : '' ?>">
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <div class="user-avatar" style="background:<?= getUserColor($u['id']) ?>;width:32px;height:32px;font-size:11px;flex-shrink:0">
                <?php if (!empty($u['photo'])): ?>
                  <img src="<?= APP_URL ?>/uploads/photos/<?= sanitize($u['photo']) ?>" alt="">
                <?php else: ?>
                  <?= strtoupper(substr($u['prenom'],0,1).substr($u['nom'],0,1)) ?>
                <?php endif; ?>
              </div>
              <div>
                <div style="font-weight:600;font-size:13px"><?= sanitize($u['prenom'].' '.$u['nom']) ?></div>
                <?php if (!empty($u['poste'])): ?>
                <div style="font-size:11px;color:var(--text3)"><?= sanitize($u['poste']) ?></div>
                <?php endif; ?>
              </div>
            </div>
          </td>
          <td style="font-size:12px"><?= sanitize($u['email']) ?></td>
          <td style="font-size:12px"><?= sanitize($u['dept_nom'] ?? '—') ?></td>
          <td>
            <?php if ($isSelf): ?>
              <span class="badge" style="background:<?= $rc ?>20;color:<?= $rc ?>;border:1px solid <?= $rc ?>40">
                <?= ROLES[$u['role']] ?? $u['role'] ?>
              </span>
              <div style="font-size:10px;color:var(--text3);margin-top:2px">Votre compte</div>
            <?php else: ?>
            <select
              class="form-control"
              style="padding:4px 8px;font-size:12px;min-width:160px;border-color:<?= $rc ?>60"
              onchange="updateRole(<?= $u['id'] ?>, this)"
              data-current="<?= sanitize($u['role']) ?>">
              <?php foreach (ROLES as $k => $v): ?>
              <option value="<?= $k ?>" <?= $u['role'] === $k ? 'selected' : '' ?>><?= $v ?></option>
              <?php endforeach; ?>
            </select>
            <?php endif; ?>
          </td>
          <td>
            <span id="badge-<?= $u['id'] ?>" class="badge"
              style="background:<?= $u['actif'] ? '#F0FDF4' : '#FEF2F2' ?>;
                     color:<?= $u['actif'] ? '#16A34A' : '#DC2626' ?>;
                     border:1px solid <?= $u['actif'] ? '#BBF7D0' : '#FECACA' ?>">
              <?= $u['actif'] ? 'Actif' : 'Inactif' ?>
            </span>
          </td>
          <td>
            <div style="display:flex;gap:4px;align-items:center">
              <a href="<?= APP_URL ?>/pages/users/edit.php?id=<?= $u['id'] ?>"
                 class="btn btn-sm btn-secondary" title="Modifier le profil" style="padding:4px 8px;font-size:11px">
                <i class="fa-solid fa-pen"></i> Modifier
              </a>
              <?php if (!$isSelf): ?>
              <button
                class="btn btn-sm btn-secondary"
                style="padding:4px 8px;font-size:11px;color:<?= $u['actif'] ? 'var(--danger,#DC2626)' : 'var(--success,#16A34A)' ?>;border-color:<?= $u['actif'] ? 'var(--danger,#DC2626)' : 'var(--success,#16A34A)' ?>"
                id="toggle-<?= $u['id'] ?>"
                title="<?= $u['actif'] ? 'Désactiver ce compte' : 'Réactiver ce compte' ?>"
                onclick="toggleUser(<?= $u['id'] ?>, <?= (int)$u['actif'] ?>)">
                <i class="fa-solid <?= $u['actif'] ? 'fa-user-slash' : 'fa-user-check' ?>"></i>
                <?= $u['actif'] ? 'Désactiver' : 'Réactiver' ?>
              </button>
              <?php else: ?>
              <span style="font-size:11px;color:var(--text3);padding:4px 8px">—</span>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ── Matrice des permissions ── -->
<div class="card">
  <div class="card-header" style="display:flex;align-items:center;gap:10px">
    <i class="fa-solid fa-shield-halved" style="color:var(--primary)"></i>
    <span class="card-title">Matrice des permissions par rôle</span>
    <span style="margin-left:auto;font-size:12px;color:var(--text3)">Lecture seule — défini dans le code</span>
  </div>
  <div class="table-wrapper">
    <?php
    $matrix = [
      ['group' => 'Tâches', 'label' => 'Voir ses propres tâches',        'perms' => [1,1,1,1,1]],
      ['group' => 'Tâches', 'label' => 'Voir les tâches du département',  'perms' => [0,1,1,1,1]],
      ['group' => 'Tâches', 'label' => 'Voir toutes les tâches',          'perms' => [0,0,0,1,1]],
      ['group' => 'Tâches', 'label' => 'Créer des tâches',                'perms' => [0,1,1,0,1]],
      ['group' => 'Tâches', 'label' => 'Modifier les tâches (département)','perms' => [0,1,1,0,1]],
      ['group' => 'Tâches', 'label' => 'Modifier toutes les tâches',      'perms' => [0,0,0,0,1]],
      ['group' => 'Tâches', 'label' => 'Changer le statut de ses tâches', 'perms' => [1,1,1,0,1]],
      ['group' => 'Rapports','label' => 'Accès aux rapports',             'perms' => [0,0,1,1,1]],
      ['group' => 'Admin',  'label' => 'Gérer les utilisateurs',          'perms' => [0,0,0,0,1]],
      ['group' => 'Admin',  'label' => 'Gérer les droits d\'accès',       'perms' => [0,0,0,0,1]],
      ['group' => 'Admin',  'label' => 'Gérer les bases géographiques',   'perms' => [0,0,0,0,1]],
      ['group' => 'Admin',  'label' => 'Gérer les départements',          'perms' => [0,0,0,0,1]],
      ['group' => 'Admin',  'label' => 'Consulter le journal d\'audit',   'perms' => [0,0,0,0,1]],
    ];
    $roleLabels  = ['Employé','Superviseur','Chef dept','Cheffe mission','Admin'];
    $roleKeys    = ['employe','superviseur','chef_dept','cheffe_mission','admin'];
    $roleColFg   = ['#6B7280','#2563EB','#7C3AED','#D97706','#DC2626'];
    $lastGroup   = '';
    ?>
    <table>
      <thead>
        <tr>
          <th style="min-width:220px">Permission</th>
          <?php foreach ($roleLabels as $i => $rl): ?>
          <th style="text-align:center;width:110px">
            <span style="color:<?= $roleColFg[$i] ?>;font-size:12px;font-weight:600"><?= $rl ?></span>
          </th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($matrix as $row): ?>
        <?php if ($row['group'] !== $lastGroup): $lastGroup = $row['group']; ?>
        <tr>
          <td colspan="6" style="background:var(--bg2);font-size:11px;font-weight:700;
              text-transform:uppercase;color:var(--text3);padding:6px 16px;letter-spacing:.05em">
            <?= sanitize($row['group']) ?>
          </td>
        </tr>
        <?php endif; ?>
        <tr>
          <td style="font-size:13px;padding-left:20px"><?= sanitize($row['label']) ?></td>
          <?php foreach ($row['perms'] as $p): ?>
          <td style="text-align:center">
            <?php if ($p): ?>
              <i class="fa-solid fa-circle-check" style="color:#16A34A;font-size:16px"></i>
            <?php else: ?>
              <i class="fa-solid fa-circle-xmark" style="color:#D1D5DB;font-size:16px"></i>
            <?php endif; ?>
          </td>
          <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
const _tfUrl = (typeof window.tfUrl === 'function')
  ? window.tfUrl
  : (p => (window.TF_BASE||'') + p);

async function updateRole(userId, selectEl) {
  const newRole = selectEl.value;
  const prev    = selectEl.dataset.current;
  const csrf    = document.querySelector('meta[name=csrf]')?.content || '';
  const res = await fetch(_tfUrl('/api/users.php'), {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
    body: JSON.stringify({ action: 'update_role', id: userId, role: newRole, csrf_token: csrf })
  });
  let data;
  try { data = await res.json(); } catch(e) { showToast('Réponse serveur invalide', 'error'); selectEl.value = prev; return; }
  if (data.success) {
    selectEl.dataset.current = newRole;
    const colors = {
      employe:'#6B7280', superviseur:'#2563EB', chef_dept:'#7C3AED',
      cheffe_mission:'#D97706', admin:'#DC2626'
    };
    selectEl.style.borderColor = (colors[newRole] || '#6B7280') + '60';
    showToast('Rôle mis à jour', 'success');
  } else {
    showToast(data.error || 'Erreur lors du changement de rôle', 'error');
    selectEl.value = prev;
  }
}

async function toggleUser(id, actif) {
  const msg = actif ? 'Désactiver ce compte utilisateur ?' : 'Réactiver ce compte utilisateur ?';
  if (!confirm(msg)) return;
  const csrf = document.querySelector('meta[name=csrf]')?.content || '';
  const res  = await fetch(_tfUrl('/api/users.php'), {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
    body: JSON.stringify({ action: 'toggle', id, actif: actif ? 0 : 1, csrf_token: csrf })
  });
  let data;
  try { data = await res.json(); } catch(e) { showToast('Réponse serveur invalide', 'error'); return; }
  if (data.success) {
    showToast('Compte ' + (actif ? 'désactivé' : 'réactivé'), 'success');
    setTimeout(() => location.reload(), 700);
  } else {
    showToast(data.error || 'Action refusée', 'error');
  }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
