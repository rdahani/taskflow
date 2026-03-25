<?php
require_once __DIR__ . '/../../config/config.php';
$pageTitle  = 'Gestion des utilisateurs';
$breadcrumbs = [
  ['label'=>'Accueil','url'=>APP_URL.'/index.php'],
  ['label'=>'Utilisateurs','url'=>''],
];
require_once __DIR__ . '/../../includes/header.php';

if (!isAdmin()) { flashMessage('error','Accès réservé aux administrateurs.'); redirect('/index.php'); }

$pdo = getDB();

// Filtres
$filterRole = $_GET['role'] ?? '';
$filterBase = (int)($_GET['base'] ?? 0);
$filterQ    = trim($_GET['q'] ?? '');

$where = ['1=1']; $params = [];
if ($filterRole) { $where[] = 'u.role=?';    $params[] = $filterRole; }
if ($filterBase) { $where[] = 'u.base_id=?'; $params[] = $filterBase; }
if ($filterQ)    { $where[] = '(u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ?)';
                   $params[] = "%$filterQ%"; $params[] = "%$filterQ%"; $params[] = "%$filterQ%"; }

$page  = max(1, (int)($_GET['page'] ?? 1));
$cStmt = $pdo->prepare("SELECT COUNT(*) FROM users u WHERE ".implode(' AND ',$where));
$cStmt->execute($params);
$total = (int)$cStmt->fetchColumn();
$pag   = paginate($total, $page);

$stmt = $pdo->prepare("SELECT u.*,d.nom AS dept_nom,b.nom AS base_nom,
    (SELECT COUNT(*) FROM taches_assignees ta WHERE ta.user_id=u.id) AS nb_taches
    FROM users u
    LEFT JOIN departements d ON d.id=u.departement_id
    LEFT JOIN bases b ON b.id=u.base_id
    WHERE ".implode(' AND ',$where)."
    ORDER BY u.nom ASC LIMIT ? OFFSET ?");
$stmt->execute(array_merge($params, [$pag['per_page'], $pag['offset']]));
$users = $stmt->fetchAll();

$bases = $pdo->query("SELECT id,nom FROM bases WHERE actif=1 ORDER BY nom")->fetchAll();
$filterUrl = '?role='.urlencode($filterRole).'&base='.$filterBase.'&q='.urlencode($filterQ);
?>

<div class="page-header">
  <div>
    <div class="page-title">Utilisateurs</div>
    <div class="page-subtitle"><?= $total ?> utilisateur<?= $total>1?'s':'' ?></div>
  </div>
  <a href="<?= APP_URL ?>/pages/users/create.php" class="btn btn-primary">➕ Nouvel utilisateur</a>
</div>

<!-- Filtres -->
<div class="filter-bar">
  <input type="text" id="searchInput" placeholder="🔍 Nom, email..." value="<?= sanitize($filterQ) ?>" style="min-width:200px">
  <select id="filterRole">
    <option value="">Tous les rôles</option>
    <?php foreach (ROLES as $k=>$v): ?>
      <option value="<?= $k ?>" <?= $filterRole===$k?'selected':'' ?>><?= $v ?></option>
    <?php endforeach; ?>
  </select>
  <select id="filterBase">
    <option value="">Toutes les bases</option>
    <?php foreach ($bases as $b): ?>
      <option value="<?= $b['id'] ?>" <?= $filterBase==$b['id']?'selected':'' ?>><?= sanitize($b['nom']) ?></option>
    <?php endforeach; ?>
  </select>
  <button onclick="applyFilters()" class="btn btn-primary btn-sm">Filtrer</button>
  <a href="?" class="btn btn-secondary btn-sm">Réinitialiser</a>
</div>

<div class="card">
  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>Utilisateur</th>
          <th>Email</th>
          <th>Rôle</th>
          <th>Département</th>
          <th>Base</th>
          <th>Tâches</th>
          <th>Statut</th>
          <th>Dernière connexion</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
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
          <td>
            <?php
            $roleColors = ['employe'=>'#6B7280','superviseur'=>'#2563EB','chef_dept'=>'#7C3AED','cheffe_mission'=>'#D97706','admin'=>'#DC2626'];
            $rc = $roleColors[$u['role']] ?? '#6B7280';
            ?>
            <span class="badge" style="background:<?= $rc ?>20;color:<?= $rc ?>;border:1px solid <?= $rc ?>40">
              <?= ROLES[$u['role']] ?? $u['role'] ?>
            </span>
          </td>
          <td style="font-size:12px"><?= sanitize($u['dept_nom'] ?? '—') ?></td>
          <td style="font-size:12px"><?= sanitize($u['base_nom'] ?? '—') ?></td>
          <td>
            <span style="font-weight:600;color:var(--primary)"><?= (int)$u['nb_taches'] ?></span>
          </td>
          <td>
            <?php if ($u['actif']): ?>
              <span class="badge" style="background:#F0FDF4;color:#16A34A;border:1px solid #BBF7D0">Actif</span>
            <?php else: ?>
              <span class="badge" style="background:#FEF2F2;color:#DC2626;border:1px solid #FECACA">Inactif</span>
            <?php endif; ?>
          </td>
          <td style="font-size:12px;color:var(--text3)">
            <?= !empty($u['last_login']) ? formatDateTime($u['last_login']) : 'Jamais' ?>
          </td>
          <td>
            <div style="display:flex;gap:4px">
              <a href="<?= APP_URL ?>/pages/users/edit.php?id=<?= $u['id'] ?>" class="btn-icon" title="Modifier">✏️</a>
              <a href="<?= APP_URL ?>/pages/users/profile.php?id=<?= $u['id'] ?>" class="btn-icon" title="Profil">👤</a>
              <?php if ($u['id'] !== $currentUser['id']): ?>
              <button class="btn-icon" title="<?= $u['actif']?'Désactiver':'Activer' ?>"
                onclick="toggleUser(<?= $u['id'] ?>, <?= $u['actif'] ?>)">
                <?= $u['actif'] ? '🚫' : '✅' ?>
              </button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($users)): ?>
        <tr><td colspan="9" style="text-align:center;padding:40px;color:var(--text3)">Aucun utilisateur trouvé</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?= renderPagination($pag, $filterUrl) ?>

<script>
function applyFilters() {
  const q    = document.getElementById('searchInput').value;
  const role = document.getElementById('filterRole').value;
  const base = document.getElementById('filterBase').value;
  window.location = '?q='+encodeURIComponent(q)+'&role='+role+'&base='+base;
}
document.getElementById('searchInput').addEventListener('keydown', e => { if(e.key==='Enter') applyFilters(); });

async function toggleUser(id, actif) {
  const msg = actif ? 'Désactiver cet utilisateur ?' : 'Réactiver cet utilisateur ?';
  if (!confirm(msg)) return;
  const csrf = document.querySelector('meta[name=csrf]')?.content || '';
  const res  = await fetch('<?= APP_URL ?>/api/users.php', {
    method:'POST',
    headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},
    body: JSON.stringify({action:'toggle',id,actif:actif?0:1})
  });
  const data = await res.json();
  if (data.success) { showToast('Utilisateur mis à jour','success'); setTimeout(()=>location.reload(),800); }
  else showToast('Erreur','error');
}
</script>
<meta name="csrf" content="<?= csrfToken() ?>">

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
