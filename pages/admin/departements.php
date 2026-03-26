<?php
/**
 * Administration — Départements (table `departements`).
 */
require_once __DIR__ . '/../../includes/layout_init.php';
require_once __DIR__ . '/../../includes/audit.php';

if (!isAdmin()) {
    flashMessage('error', 'Accès réservé aux administrateurs.');
    redirect('/index.php');
}

$pageTitle   = 'Départements';
$breadcrumbs = [
    ['label' => 'Accueil', 'url' => APP_URL . '/index.php'],
    ['label' => 'Départements', 'url' => ''],
];

$pdo     = getDB();
$errors  = [];
$editId  = (int) ($_GET['edit'] ?? 0);
$editing = null;

if ($editId > 0) {
    $st = $pdo->prepare('SELECT * FROM departements WHERE id = ?');
    $st->execute([$editId]);
    $editing = $st->fetch();
    if (!$editing) {
        flashMessage('error', 'Département introuvable.');
        redirect('/pages/admin/departements.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['form_action'] ?? '';

    if ($action === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        $st = $pdo->prepare('SELECT id, nom, actif FROM departements WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch();
        if ($row) {
            $new = $row['actif'] ? 0 : 1;
            $pdo->prepare('UPDATE departements SET actif = ? WHERE id = ?')->execute([$new, $id]);
            logAudit((int) $currentUser['id'], 'dept_toggle', 'departement', $id, $row['nom'] . ' actif=' . $new);
            flashMessage('success', $new ? 'Département réactivé.' : 'Département désactivé.');
        }
        redirect('/pages/admin/departements.php');
    }

    if ($action === 'save') {
        $id      = (int) ($_POST['id'] ?? 0);
        $nom     = trim($_POST['nom'] ?? '');
        $baseId  = (int) ($_POST['base_id'] ?? 0) ?: null;
        $actif   = isset($_POST['actif']) ? 1 : 0;

        if ($nom === '') {
            $errors[] = 'Le nom est obligatoire.';
        }

        if ($baseId !== null) {
            $chk = $pdo->prepare('SELECT id FROM bases WHERE id = ?');
            $chk->execute([$baseId]);
            if (!$chk->fetch()) {
                $errors[] = 'Base géographique invalide.';
                $baseId = null;
            }
        }

        if (!$errors) {
            if ($id > 0) {
                $pdo->prepare('UPDATE departements SET nom = ?, base_id = ?, actif = ? WHERE id = ?')
                    ->execute([$nom, $baseId, $actif, $id]);
                logAudit((int) $currentUser['id'], 'dept_update', 'departement', $id, $nom);
                flashMessage('success', 'Département mis à jour.');
            } else {
                $pdo->prepare('INSERT INTO departements (nom, base_id, actif) VALUES (?,?,?)')
                    ->execute([$nom, $baseId, $actif]);
                $newId = (int) $pdo->lastInsertId();
                logAudit((int) $currentUser['id'], 'dept_create', 'departement', $newId, $nom);
                flashMessage('success', 'Département créé.');
            }
            redirect('/pages/admin/departements.php');
        }

        if ($editing) {
            $editing['nom']      = $nom;
            $editing['base_id']  = $baseId;
            $editing['actif']   = $actif;
        } else {
            $editing = ['id' => 0, 'nom' => $nom, 'base_id' => $baseId, 'actif' => $actif];
        }
    }
}

$basesList = $pdo->query('SELECT id, nom, actif FROM bases ORDER BY actif DESC, nom')->fetchAll();

$rows = $pdo->query('SELECT d.*, b.nom AS base_nom,
    (SELECT COUNT(*) FROM users u WHERE u.departement_id = d.id AND u.actif = 1) AS nb_users
    FROM departements d
    LEFT JOIN bases b ON b.id = d.base_id
    ORDER BY d.actif DESC, d.nom')->fetchAll();

$form = $editing ?? ['id' => 0, 'nom' => '', 'base_id' => null, 'actif' => 1];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div>
    <div class="page-title">Départements</div>
    <div class="page-subtitle">Services rattachés à une base géographique</div>
  </div>
  <a href="<?= APP_URL ?>/pages/admin/bases.php" class="btn btn-secondary" style="text-decoration:none">Bases géographiques</a>
</div>

<?php if ($errors): ?>
<div class="card" style="margin-bottom:16px">
  <div class="card-body flash flash-error" style="padding:1rem">
    <?= implode('<br>', array_map('sanitize', $errors)) ?>
  </div>
</div>
<?php endif; ?>

<div class="card" style="margin-bottom:20px">
  <div class="card-header"><span class="card-title"><?= !empty($form['id']) ? 'Modifier le département' : 'Ajouter un département' ?></span></div>
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
        <div class="form-group">
          <label class="form-label">Base géographique</label>
          <select name="base_id" class="form-control">
            <option value="">— Aucune —</option>
            <?php foreach ($basesList as $b): ?>
            <option value="<?= (int) $b['id'] ?>" <?= (int) ($form['base_id'] ?? 0) === (int) $b['id'] ? 'selected' : '' ?>>
              <?= sanitize($b['nom']) ?><?= $b['actif'] ? '' : ' (base inactive)' ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;margin-bottom:12px">
        <input type="checkbox" name="actif" value="1" <?= !empty($form['actif']) ? 'checked' : '' ?>>
        Département actif
      </label>
      <button type="submit" class="btn btn-primary"><?= !empty($form['id']) ? 'Enregistrer' : 'Ajouter' ?></button>
      <?php if (!empty($form['id'])): ?>
      <a href="<?= APP_URL ?>/pages/admin/departements.php" class="btn btn-secondary" style="margin-left:8px;text-decoration:none">Annuler</a>
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
          <th>Base</th>
          <th style="text-align:center">Utilisateurs actifs</th>
          <th>Statut</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= sanitize($r['nom']) ?></td>
          <td style="color:var(--text2)"><?= sanitize($r['base_nom'] ?? '—') ?></td>
          <td style="text-align:center"><?= (int) $r['nb_users'] ?></td>
          <td>
            <?php if ($r['actif']): ?>
            <span class="badge" style="background:#F0FDF4;color:#166534;border:1px solid #16653440">Actif</span>
            <?php else: ?>
            <span class="badge" style="background:#F3F4F6;color:#6B7280">Inactif</span>
            <?php endif; ?>
          </td>
          <td style="white-space:nowrap">
            <a href="<?= APP_URL ?>/pages/admin/departements.php?edit=<?= (int) $r['id'] ?>" class="btn btn-sm btn-secondary" style="text-decoration:none">Modifier</a>
            <form method="post" style="display:inline;margin-left:6px" onsubmit="return confirm('Changer le statut ?');">
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
