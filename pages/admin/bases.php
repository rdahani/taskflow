<?php
/**
 * Administration — Bases géographiques (table `bases`).
 */
require_once __DIR__ . '/../../includes/layout_init.php';
require_once __DIR__ . '/../../includes/audit.php';

if (!isAdmin()) {
    flashMessage('error', 'Accès réservé aux administrateurs.');
    redirect('/index.php');
}

$pageTitle   = 'Bases géographiques';
$breadcrumbs = [
    ['label' => 'Accueil', 'url' => APP_URL . '/index.php'],
    ['label' => 'Bases géographiques', 'url' => ''],
];

$pdo       = getDB();
$errors    = [];
$editId    = (int) ($_GET['edit'] ?? 0);
$editing   = null;

if ($editId > 0) {
    $st = $pdo->prepare('SELECT * FROM bases WHERE id = ?');
    $st->execute([$editId]);
    $editing = $st->fetch();
    if (!$editing) {
        flashMessage('error', 'Base introuvable.');
        redirect('/pages/admin/bases.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['form_action'] ?? '';

    if ($action === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        $st = $pdo->prepare('SELECT id, nom, actif FROM bases WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch();
        if ($row) {
            $new = $row['actif'] ? 0 : 1;
            $pdo->prepare('UPDATE bases SET actif = ? WHERE id = ?')->execute([$new, $id]);
            logAudit((int) $currentUser['id'], 'base_toggle', 'base', $id, $row['nom'] . ' actif=' . $new);
            flashMessage('success', $new ? 'Base réactivée.' : 'Base désactivée.');
        }
        redirect('/pages/admin/bases.php');
    }

    if ($action === 'save') {
        $id     = (int) ($_POST['id'] ?? 0);
        $nom    = trim($_POST['nom'] ?? '');
        $code   = strtoupper(preg_replace('/\s+/', '', trim($_POST['code'] ?? '')));
        $region = trim($_POST['region'] ?? '');
        $actif  = isset($_POST['actif']) ? 1 : 0;

        if ($nom === '') {
            $errors[] = 'Le nom est obligatoire.';
        }
        if ($code === '' || strlen($code) > 20) {
            $errors[] = 'Le code est obligatoire (max. 20 caractères).';
        }

        if (!$errors) {
            if ($id > 0) {
                $chk = $pdo->prepare('SELECT id FROM bases WHERE code = ? AND id != ?');
                $chk->execute([$code, $id]);
                if ($chk->fetch()) {
                    $errors[] = 'Ce code est déjà utilisé par une autre base.';
                }
            } else {
                $chk = $pdo->prepare('SELECT id FROM bases WHERE code = ?');
                $chk->execute([$code]);
                if ($chk->fetch()) {
                    $errors[] = 'Ce code existe déjà.';
                }
            }
        }

        if (!$errors) {
            if ($id > 0) {
                $pdo->prepare('UPDATE bases SET nom = ?, code = ?, region = ?, actif = ? WHERE id = ?')
                    ->execute([$nom, $code, $region ?: null, $actif, $id]);
                logAudit((int) $currentUser['id'], 'base_update', 'base', $id, $nom);
                flashMessage('success', 'Base mise à jour.');
            } else {
                $pdo->prepare('INSERT INTO bases (nom, code, region, actif) VALUES (?,?,?,?)')
                    ->execute([$nom, $code, $region ?: null, $actif]);
                $newId = (int) $pdo->lastInsertId();
                logAudit((int) $currentUser['id'], 'base_create', 'base', $newId, $nom);
                flashMessage('success', 'Base créée.');
            }
            redirect('/pages/admin/bases.php');
        }
        // Reprise des champs après erreur de validation
        if ($editing) {
            $editing['nom']    = $nom;
            $editing['code']   = $_POST['code'] ?? $code;
            $editing['region'] = $region;
            $editing['actif']  = $actif;
        } else {
            $editing = ['id' => 0, 'nom' => $nom, 'code' => $_POST['code'] ?? $code, 'region' => $region, 'actif' => $actif];
        }
    }
}

$rows = $pdo->query('SELECT b.*, (SELECT COUNT(*) FROM users u WHERE u.base_id = b.id AND u.actif = 1) AS nb_users,
    (SELECT COUNT(*) FROM departements d WHERE d.base_id = b.id AND d.actif = 1) AS nb_depts
    FROM bases b ORDER BY b.actif DESC, b.nom')->fetchAll();

$form = $editing ?? ['id' => 0, 'nom' => '', 'code' => '', 'region' => '', 'actif' => 1];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div>
    <div class="page-title">Bases géographiques</div>
    <div class="page-subtitle">Lieux / antennes rattachés aux utilisateurs, départements et tâches</div>
  </div>
  <a href="<?= APP_URL ?>/pages/admin/departements.php" class="btn btn-secondary" style="text-decoration:none">Départements</a>
</div>

<?php if ($errors): ?>
<div class="card" style="margin-bottom:16px">
  <div class="card-body flash flash-error" style="padding:1rem">
    <?= implode('<br>', array_map('sanitize', $errors)) ?>
  </div>
</div>
<?php endif; ?>

<div class="card" style="margin-bottom:20px">
    <div class="card-header"><span class="card-title"><?= !empty($form['id']) ? 'Modifier la base' : 'Ajouter une base' ?></span></div>
  <div class="card-body">
    <form method="post" class="form">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <input type="hidden" name="form_action" value="save">
      <input type="hidden" name="id" value="<?= (int) ($form['id'] ?? 0) ?>">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Nom <span class="req">*</span></label>
          <input type="text" name="nom" class="form-control" required maxlength="100"
            value="<?= sanitize($form['nom'] ?? '') ?>">
        </div>
        <div class="form-group" style="max-width:140px">
          <label class="form-label">Code <span class="req">*</span></label>
          <input type="text" name="code" class="form-control" required maxlength="20" placeholder="NIM"
            value="<?= sanitize($form['code'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Région / lieu</label>
          <input type="text" name="region" class="form-control" maxlength="100"
            value="<?= sanitize($form['region'] ?? '') ?>">
        </div>
      </div>
      <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;margin-bottom:12px">
        <input type="checkbox" name="actif" value="1" <?= !empty($form['actif']) ? 'checked' : '' ?>>
        Base active (visible dans les listes)
      </label>
      <button type="submit" class="btn btn-primary"><?= !empty($form['id']) ? 'Enregistrer' : 'Ajouter' ?></button>
      <?php if (!empty($form['id'])): ?>
      <a href="<?= APP_URL ?>/pages/admin/bases.php" class="btn btn-secondary" style="margin-left:8px;text-decoration:none">Annuler</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>Nom</th>
          <th>Code</th>
          <th>Région</th>
          <th style="text-align:center">Utilisateurs</th>
          <th style="text-align:center">Départements</th>
          <th>Statut</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= sanitize($r['nom']) ?></td>
          <td><code><?= sanitize($r['code']) ?></code></td>
          <td style="color:var(--text2)"><?= sanitize($r['region'] ?? '—') ?></td>
          <td style="text-align:center"><?= (int) $r['nb_users'] ?></td>
          <td style="text-align:center"><?= (int) $r['nb_depts'] ?></td>
          <td>
            <?php if ($r['actif']): ?>
            <span class="badge" style="background:#F0FDF4;color:#166534;border:1px solid #16653440">Actif</span>
            <?php else: ?>
            <span class="badge" style="background:#F3F4F6;color:#6B7280">Inactif</span>
            <?php endif; ?>
          </td>
          <td style="white-space:nowrap">
            <a href="<?= APP_URL ?>/pages/admin/bases.php?edit=<?= (int) $r['id'] ?>" class="btn btn-sm btn-secondary" style="text-decoration:none">Modifier</a>
            <form method="post" style="display:inline;margin-left:6px" onsubmit="return confirm('Changer le statut de cette base ?');">
              <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
              <input type="hidden" name="form_action" value="toggle">
              <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <button type="submit" class="btn btn-sm <?= $r['actif'] ? 'btn-secondary' : 'btn-primary' ?>"><?= $r['actif'] ? 'Désactiver' : 'Réactiver' ?></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
