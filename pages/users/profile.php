<?php
require_once __DIR__ . '/../../includes/layout_init.php';

$profileId = isset($_GET['id']) ? (int)$_GET['id'] : $currentUser['id'];
$pdo = getDB();

$stmt = $pdo->prepare("SELECT u.*,d.nom AS dept_nom,b.nom AS base_nom
    FROM users u
    LEFT JOIN departements d ON d.id=u.departement_id
    LEFT JOIN bases b ON b.id=u.base_id
    WHERE u.id=?");
$stmt->execute([$profileId]);
$profile = $stmt->fetch();

if (!$profile) { flashMessage('error','Utilisateur introuvable.'); redirect('/index.php'); }
if (!isCheffe() && $profileId !== $currentUser['id']) { flashMessage('error','Accès refusé.'); redirect('/index.php'); }

// Statistiques de l'utilisateur
$statStmt = $pdo->prepare("SELECT t.statut, COUNT(*) AS n
    FROM taches t JOIN taches_assignees ta ON ta.tache_id=t.id
    WHERE ta.user_id=? GROUP BY t.statut");
$statStmt->execute([$profileId]);
$taskStats = [];
foreach ($statStmt->fetchAll() as $r) $taskStats[$r['statut']] = (int)$r['n'];

$totalTasks  = array_sum($taskStats);
$doneTasks   = $taskStats['termine'] ?? 0;
$completion  = $totalTasks > 0 ? round($doneTasks / $totalTasks * 100) : 0;

// Tâches récentes
$recentStmt = $pdo->prepare("SELECT t.id,t.titre,t.statut,t.priorite,t.date_echeance
    FROM taches t JOIN taches_assignees ta ON ta.tache_id=t.id
    WHERE ta.user_id=? ORDER BY t.date_echeance ASC LIMIT 6");
$recentStmt->execute([$profileId]);
$recentTasks = $recentStmt->fetchAll();

// Mise à jour du profil perso
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $profileId === $currentUser['id']) {
    verifyCsrf();
    $telephone    = trim($_POST['telephone'] ?? '');
    $poste        = trim($_POST['poste'] ?? '');
    $notifyEmail  = isset($_POST['notify_email']) ? 1 : 0;
    $password     = $_POST['password'] ?? '';
    $password2    = $_POST['password2'] ?? '';

    $sql = "UPDATE users SET telephone=?,poste=?,notify_email=? WHERE id=?";
    $params = [$telephone, $poste, $notifyEmail, $profileId];

    if (!empty($password)) {
        if ($password !== $password2) { flashMessage('error','Les mots de passe ne correspondent pas.'); redirect('/pages/users/profile.php'); }
        $sql = "UPDATE users SET telephone=?,poste=?,notify_email=?,password=? WHERE id=?";
        $params = [$telephone, $poste, $notifyEmail, password_hash($password, PASSWORD_BCRYPT), $profileId];
    }

    // Photo
    if (!empty($_FILES['photo']['name'])) {
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext,['jpg','jpeg','png','gif','webp'])) {
            $dir = UPLOAD_DIR.'photos/'; if (!is_dir($dir)) mkdir($dir,0755,true);
            $photoName = uniqid('photo_',true).'.'.$ext;
            move_uploaded_file($_FILES['photo']['tmp_name'], $dir.$photoName);
            $pdo->prepare("UPDATE users SET photo=? WHERE id=?")->execute([$photoName,$profileId]);
        }
    }

    $pdo->prepare($sql)->execute($params);
    flashMessage('success','Profil mis à jour.');
    redirect('/pages/users/profile.php');
}

$pageTitle   = $profile['prenom'] . ' ' . $profile['nom'];
$breadcrumbs = [['label' => 'Profil', 'url' => '']];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-title">Mon profil</div>
</div>

<div style="display:grid;grid-template-columns:300px 1fr;gap:20px">

  <!-- Carte profil -->
  <div>
    <div class="card" style="margin-bottom:16px;text-align:center">
      <div class="card-body">
        <div style="margin-bottom:16px">
          <?php if (!empty($profile['photo'])): ?>
          <img src="<?= APP_URL ?>/uploads/photos/<?= sanitize($profile['photo']) ?>"
               style="width:90px;height:90px;border-radius:50%;object-fit:cover;border:3px solid var(--primary)">
          <?php else: ?>
          <div class="user-avatar" style="width:90px;height:90px;font-size:32px;font-weight:800;background:<?= getUserColor($profileId) ?>;margin:0 auto">
            <?= strtoupper(substr($profile['prenom'],0,1).substr($profile['nom'],0,1)) ?>
          </div>
          <?php endif; ?>
        </div>
        <div style="font-size:20px;font-weight:700"><?= sanitize($profile['prenom'].' '.$profile['nom']) ?></div>
        <?php if (!empty($profile['poste'])): ?>
        <div style="color:var(--text3);font-size:13px;margin-top:4px"><?= sanitize($profile['poste']) ?></div>
        <?php endif; ?>
        <div style="margin-top:10px">
          <?php $rc = ['employe'=>'#6B7280','superviseur'=>'#2563EB','chef_dept'=>'#7C3AED','cheffe_mission'=>'#D97706','admin'=>'#DC2626'][$profile['role']] ?? '#6B7280'; ?>
          <span class="badge" style="background:<?= $rc ?>20;color:<?= $rc ?>;border:1px solid <?= $rc ?>40">
            <?= ROLES[$profile['role']] ?? $profile['role'] ?>
          </span>
        </div>
      </div>
    </div>

    <div class="card" style="margin-bottom:16px">
      <div class="card-body">
        <div class="detail-field"><div class="detail-label">Email</div><div class="detail-value" style="font-size:13px"><?= sanitize($profile['email']) ?></div></div>
        <?php if (!empty($profile['telephone'])): ?>
        <div class="detail-field"><div class="detail-label">Téléphone</div><div class="detail-value" style="font-size:13px"><?= sanitize($profile['telephone']) ?></div></div>
        <?php endif; ?>
        <div class="detail-field"><div class="detail-label">Département</div><div class="detail-value" style="font-size:13px"><?= sanitize($profile['dept_nom'] ?? '—') ?></div></div>
        <div class="detail-field"><div class="detail-label">Base</div><div class="detail-value" style="font-size:13px"><?= sanitize($profile['base_nom'] ?? '—') ?></div></div>
        <div class="detail-field"><div class="detail-label">Membre depuis</div><div class="detail-value" style="font-size:13px"><?= formatDate($profile['created_at']) ?></div></div>
        <div class="detail-field"><div class="detail-label">Dernière connexion</div><div class="detail-value" style="font-size:13px"><?= !empty($profile['last_login']) ? formatDateTime($profile['last_login']) : 'Jamais' ?></div></div>
      </div>
    </div>

    <!-- Stats tâches -->
    <div class="card">
      <div class="card-header"><span class="card-title">Statistiques</span></div>
      <div class="card-body">
        <div style="text-align:center;margin-bottom:16px">
          <div style="font-size:36px;font-weight:800;color:var(--primary)"><?= $totalTasks ?></div>
          <div style="font-size:12px;color:var(--text3)">Tâches assignées</div>
        </div>
        <div style="margin-bottom:10px">
          <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px">
            <span>Taux de complétion</span><span style="font-weight:600"><?= $completion ?>%</span>
          </div>
          <div class="progress-bar"><div class="progress-fill" style="width:<?= $completion ?>%;background:#16A34A"></div></div>
        </div>
        <?php foreach (TASK_STATUSES as $k=>$s): ?>
        <?php if (isset($taskStats[$k])): ?>
        <div style="display:flex;justify-content:space-between;font-size:12px;padding:3px 0">
          <span><?= getTaskStatusBadge($k) ?></span>
          <span style="font-weight:600"><?= $taskStats[$k] ?></span>
        </div>
        <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Colonne droite -->
  <div>
    <!-- Tâches récentes -->
    <div class="card" style="margin-bottom:16px">
      <div class="card-header">
        <span class="card-title">Tâches assignées</span>
        <a href="<?= APP_URL ?>/pages/tasks/list.php" class="btn btn-secondary btn-sm">Voir tout</a>
      </div>
      <div class="table-wrapper">
        <table>
          <thead><tr><th>Titre</th><th>Statut</th><th>Priorité</th><th>Échéance</th></tr></thead>
          <tbody>
            <?php foreach ($recentTasks as $t): ?>
            <tr class="<?= getTaskRowClass($t) ?>">
              <td><a href="<?= APP_URL ?>/pages/tasks/view.php?id=<?= $t['id'] ?>" style="color:var(--text);font-weight:500;text-decoration:none"><?= sanitize($t['titre']) ?></a></td>
              <td><?= getTaskStatusBadge(computeRealStatus($t)) ?></td>
              <td><?= getPriorityBadge($t['priorite']) ?></td>
              <td style="font-size:12px"><?= formatDate($t['date_echeance']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($recentTasks)): ?>
            <tr><td colspan="4" style="text-align:center;color:var(--text3);padding:30px">Aucune tâche assignée</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modifier profil (seulement le sien) -->
    <?php if ($profileId === $currentUser['id']): ?>
    <div class="card">
      <div class="card-header"><span class="card-title">Modifier mon profil</span></div>
      <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Téléphone</label>
              <input type="text" name="telephone" class="form-control" value="<?= sanitize($profile['telephone'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Poste / Fonction</label>
              <input type="text" name="poste" class="form-control" value="<?= sanitize($profile['poste'] ?? '') ?>">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Photo de profil</label>
            <input type="file" name="photo" accept="image/*" class="form-control">
          </div>
          <div class="form-group">
            <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;font-size:14px;font-weight:500">
              <input type="checkbox" name="notify_email" value="1" style="margin-top:3px"
                <?= !empty($profile['notify_email']) ? 'checked' : '' ?>>
              <span>Recevoir les notifications par e-mail (nouvelles tâches, commentaires, etc.)</span>
            </label>
            <div style="font-size:12px;color:var(--text3);margin-top:4px;margin-left:28px">
              Nécessite que l’administrateur ait configuré l’envoi des e-mails sur le serveur.
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Nouveau mot de passe</label>
              <input type="password" name="password" class="form-control" placeholder="Laisser vide = pas de changement">
            </div>
            <div class="form-group">
              <label class="form-label">Confirmer</label>
              <input type="password" name="password2" class="form-control" placeholder="••••••••">
            </div>
          </div>
          <button type="submit" class="btn btn-primary">💾 Enregistrer</button>
        </form>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
