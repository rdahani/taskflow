<?php
require_once __DIR__ . '/../../includes/layout_init.php';

$taskId = (int)($_GET['id'] ?? 0);
if (!$taskId) { redirect('/pages/tasks/list.php'); }

$pdo  = getDB();
$stmt = $pdo->prepare("SELECT * FROM taches WHERE id=?");
$stmt->execute([$taskId]);
$task = $stmt->fetch();
if (!$task || !canEditTask($task)) {
    flashMessage('error','Accès refusé ou tâche introuvable.');
    redirect('/pages/tasks/list.php');
}

$pageTitle = 'Modifier : '.$task['titre'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    if (isset($_POST['delete_file_id'])) {
        $fid = (int) $_POST['delete_file_id'];
        $removed = $fid > 0 ? deleteTaskFile($fid, $taskId) : null;
        if ($removed !== null) {
            logChange($taskId, $currentUser['id'], 'pièce jointe', $removed, '(supprimé)', 'modification');
            flashMessage('success', 'Fichier supprimé.');
        } else {
            flashMessage('error', 'Impossible de supprimer ce fichier.');
        }
        redirect('/pages/tasks/edit.php?id=' . $taskId);
    }

    $titre       = trim($_POST['titre'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $statut      = $_POST['statut'] ?? $task['statut'];
    $priorite    = $_POST['priorite'] ?? $task['priorite'];
    $date_debut  = $_POST['date_debut'] ?: null;
    $date_ech    = $_POST['date_echeance'] ?? '';
    $dept_id     = (int)($_POST['departement_id'] ?? 0) ?: null;
    $base_id     = (int)($_POST['base_id'] ?? 0) ?: null;
    $cat_id      = (int)($_POST['categorie_id'] ?? 0) ?: null;
    $pourcent    = (int)($_POST['pourcentage'] ?? 0);
    $assignes    = $_POST['assignes'] ?? [];

    if (empty($titre))    $errors[] = 'Le titre est obligatoire.';
    if (empty($date_ech)) $errors[] = "La date d'échéance est obligatoire.";

    $ech_ts = strtotime($date_ech);
    if ($ech_ts < strtotime(date('Y-m-d'))) $errors[] = "La date d'échéance ne peut pas être dans le passé.";
    if (!empty($date_debut) && strtotime($date_debut) > $ech_ts) $errors[] = "La date de début ne peut pas être après la date d'échéance.";

    if (empty($errors)) {
        // Log changes
        $fields = [
            'titre'          => [$task['titre'],      $titre],
            'statut'         => [$task['statut'],     $statut],
            'priorite'       => [$task['priorite'],   $priorite],
            'date_echeance'  => [$task['date_echeance'], $date_ech],
            'pourcentage'    => [$task['pourcentage'], $pourcent],
        ];
        foreach ($fields as $field => [$old, $new]) {
            if ((string)$old !== (string)$new) logChange($taskId, $currentUser['id'], $field, $old, $new);
        }

        $closedAt = ($statut === 'termine' && $task['statut'] !== 'termine') ? date('Y-m-d H:i:s') : ($task['date_cloture'] ?? null);

        $pdo->prepare("UPDATE taches SET titre=?,description=?,statut=?,priorite=?,date_debut=?,date_echeance=?,
            departement_id=?,base_id=?,categorie_id=?,pourcentage=?,date_cloture=? WHERE id=?")
            ->execute([$titre,$description,$statut,$priorite,$date_debut,$date_ech,$dept_id,$base_id,$cat_id,$pourcent,$closedAt,$taskId]);

        // Mettre à jour les assignations
        $pdo->prepare("DELETE FROM taches_assignees WHERE tache_id=?")->execute([$taskId]);
        if (!empty($assignes)) {
            $ins = $pdo->prepare("INSERT INTO taches_assignees (tache_id,user_id) VALUES (?,?)");
            foreach ($assignes as $uid) { $ins->execute([$taskId,(int)$uid]); }
        }

        $batch = collectUploadedFiles('fichiers');
        $uploadFlash = null;
        if (!empty($batch)) {
            $fStmt = $pdo->prepare("INSERT INTO fichiers (tache_id,nom_original,chemin,taille,mime,uploaded_by) VALUES (?,?,?,?,?,?)");
            $okNew = 0;
            foreach ($batch as $fdata) {
                $uploaded = handleFileUpload($fdata, $taskId);
                if ($uploaded) {
                    $fStmt->execute([$taskId, $uploaded['nom_original'], $uploaded['chemin'], $uploaded['taille'], $uploaded['mime'], $currentUser['id']]);
                    $okNew++;
                    logChange($taskId, $currentUser['id'], 'pièce jointe', '', $uploaded['nom_original'], 'modification');
                }
            }
            if ($okNew === 0) {
                $uploadFlash = ['warning', 'Tâche enregistrée, mais aucune nouvelle pièce jointe n’a pu être ajoutée (format, taille max 10 Mo ou dossier uploads/).'];
            } elseif ($okNew < count($batch)) {
                $uploadFlash = ['warning', 'Tâche enregistrée : ' . $okNew . ' nouveau(x) fichier(s) sur ' . count($batch) . ' importé(s).'];
            }
        }

        if ($uploadFlash !== null) {
            flashMessage($uploadFlash[0], $uploadFlash[1]);
        } else {
            flashMessage('success', 'Tâche modifiée avec succès.');
        }
        redirect('/pages/tasks/view.php?id='.$taskId);
    }

    // En cas d'erreur, utiliser les valeurs POST
    $task = array_merge($task, [
        'titre'=>$titre,'description'=>$description,'statut'=>$statut,
        'priorite'=>$priorite,'date_debut'=>$date_debut,'date_echeance'=>$date_ech,
        'departement_id'=>$dept_id,'base_id'=>$base_id,'categorie_id'=>$cat_id,'pourcentage'=>$pourcent
    ]);
}

// Assignés actuels
$currentAssignes = $pdo->prepare("SELECT user_id FROM taches_assignees WHERE tache_id=?");
$currentAssignes->execute([$taskId]);
$assignedIds = array_column($currentAssignes->fetchAll(), 'user_id');

$users  = $pdo->query("SELECT id,prenom,nom,role FROM users WHERE actif=1 ORDER BY nom")->fetchAll();
$depts  = $pdo->query("SELECT id,nom FROM departements WHERE actif=1 ORDER BY nom")->fetchAll();
$bases  = $pdo->query("SELECT id,nom FROM bases WHERE actif=1 ORDER BY nom")->fetchAll();
$cats   = $pdo->query("SELECT id,nom FROM categories ORDER BY nom")->fetchAll();

$taskFilesStmt = $pdo->prepare("SELECT f.*, u.prenom, u.nom FROM fichiers f JOIN users u ON u.id = f.uploaded_by WHERE f.tache_id = ? ORDER BY f.created_at DESC");
$taskFilesStmt->execute([$taskId]);
$taskFiles = $taskFilesStmt->fetchAll();

$breadcrumbs = [
  ['label'=>'Accueil','url'=>APP_URL.'/index.php'],
  ['label'=>'Tâches','url'=>APP_URL.'/pages/tasks/list.php'],
  ['label'=>'Modifier','url'=>''],
];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div>
    <div class="page-title">Modifier la tâche</div>
    <div class="page-subtitle">#<?= $taskId ?> — <?= sanitize(mb_substr($task['titre'],0,60)) ?></div>
  </div>
  <div style="display:flex;gap:8px">
    <a href="<?= APP_URL ?>/pages/tasks/view.php?id=<?= $taskId ?>" class="btn btn-secondary">← Annuler</a>
  </div>
</div>

<?php if (!empty($errors)): ?>
<div class="flash flash-error" style="margin-bottom:20px">
  <ul style="margin:0;padding-left:18px"><?php foreach ($errors as $e): ?><li><?= sanitize($e) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">
<input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

<div style="display:grid;grid-template-columns:1fr 320px;gap:20px">
  <div>
    <div class="card" style="margin-bottom:16px">
      <div class="card-header"><span class="card-title">Informations</span></div>
      <div class="card-body">
        <div class="form-group">
          <label class="form-label">Titre <span class="req">*</span></label>
          <input type="text" name="titre" class="form-control" value="<?= sanitize($task['titre']) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" rows="5"><?= sanitize($task['description'] ?? '') ?></textarea>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Date de début</label>
            <input type="text" name="date_debut" class="form-control datepicker" value="<?= sanitize($task['date_debut'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Date d'échéance <span class="req">*</span></label>
            <input type="text" name="date_echeance" class="form-control datepicker-future" value="<?= sanitize($task['date_echeance']) ?>" required>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Statut</label>
            <select name="statut" class="form-control">
              <?php foreach (TASK_STATUSES as $k => $s): ?>
                <?php if ($k==='en_retard') continue; ?>
                <option value="<?= $k ?>" <?= $task['statut']===$k?'selected':'' ?>><?= $s['label'] ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Priorité</label>
            <select name="priorite" class="form-control">
              <?php foreach (TASK_PRIORITIES as $k => $p): ?>
                <option value="<?= $k ?>" <?= $task['priorite']===$k?'selected':'' ?>><?= $p['label'] ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Progression : <span id="pourcLabel"><?= (int)$task['pourcentage'] ?>%</span></label>
          <input type="range" name="pourcentage" min="0" max="100" value="<?= (int)$task['pourcentage'] ?>"
            oninput="document.getElementById('pourcLabel').textContent=this.value+'%'" style="width:100%">
        </div>
      </div>
    </div>

    <div class="card" style="margin-bottom:16px">
      <div class="card-header"><span class="card-title">Pièces jointes</span></div>
      <div class="card-body">
        <?php if (!empty($taskFiles)): ?>
        <div class="file-list" style="margin-bottom:14px">
          <?php foreach ($taskFiles as $f): ?>
          <div class="file-item" style="flex-wrap:wrap;align-items:center">
            <span class="file-icon"><i class="fa-solid fa-paperclip" style="color:var(--text3)"></i></span>
            <div style="flex:1;min-width:140px;min-height:0">
              <div class="file-name" style="font-weight:500;word-break:break-word"><?= sanitize($f['nom_original']) ?></div>
              <div style="font-size:10px;color:var(--text3)"><?= formatSize((int) $f['taille']) ?> — <?= sanitize($f['prenom'] . ' ' . $f['nom']) ?></div>
            </div>
            <div style="display:flex;gap:6px;flex-shrink:0">
              <a href="<?= APP_URL ?>/uploads/<?= sanitize($f['chemin']) ?>" download class="btn btn-secondary btn-sm">Télécharger</a>
              <button type="submit" name="delete_file_id" value="<?= (int) $f['id'] ?>" class="btn btn-secondary btn-sm" style="color:var(--danger);border-color:var(--danger-light)" onclick="return confirm('Supprimer ce fichier définitivement ?')">Supprimer</button>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p style="font-size:13px;color:var(--text3);margin:0 0 14px">Aucun fichier joint pour l’instant.</p>
        <?php endif; ?>

        <label class="form-label" style="margin-bottom:8px">Ajouter des fichiers</label>
        <div class="upload-zone" id="uploadZoneEdit">
          <div style="font-size:28px;margin-bottom:6px">📎</div>
          <div style="font-size:13px">Glisser-déposer ou <strong>cliquer pour sélectionner</strong></div>
          <div style="font-size:11px;color:var(--text3);margin-top:4px">PDF, Word, Excel, images, ZIP — max 10 Mo/fichier</div>
          <input type="file" name="fichiers[]" id="fileInputEdit" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.zip,.rar">
        </div>
        <div class="file-list" id="fileListEdit"></div>
      </div>
    </div>
  </div>

  <div>
    <div class="card" style="margin-bottom:16px">
      <div class="card-header"><span class="card-title">Organisation</span></div>
      <div class="card-body">
        <div class="form-group">
          <label class="form-label">Département</label>
          <select name="departement_id" class="form-control">
            <option value="">— Choisir —</option>
            <?php foreach ($depts as $d): ?>
              <option value="<?= $d['id'] ?>" <?= $task['departement_id']==$d['id']?'selected':'' ?>><?= sanitize($d['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Base</label>
          <select name="base_id" class="form-control">
            <option value="">— Choisir —</option>
            <?php foreach ($bases as $b): ?>
              <option value="<?= $b['id'] ?>" <?= $task['base_id']==$b['id']?'selected':'' ?>><?= sanitize($b['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Catégorie</label>
          <select name="categorie_id" class="form-control">
            <option value="">— Aucune —</option>
            <?php foreach ($cats as $c): ?>
              <option value="<?= $c['id'] ?>" <?= $task['categorie_id']==$c['id']?'selected':'' ?>><?= sanitize($c['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>

    <div class="card" style="margin-bottom:16px">
      <div class="card-header"><span class="card-title">Assigner à</span></div>
      <div class="card-body">
        <input type="text" id="userSearch" class="form-control" placeholder="Rechercher..." style="margin-bottom:10px">
        <div id="userList" style="max-height:200px;overflow-y:auto;border:1px solid var(--border);border-radius:var(--radius-sm)">
          <?php foreach ($users as $u): ?>
          <label style="display:flex;align-items:center;gap:8px;padding:8px 12px;cursor:pointer;border-bottom:1px solid var(--border);font-size:13px">
            <input type="checkbox" name="assignes[]" value="<?= $u['id'] ?>"
              <?= in_array($u['id'], $assignedIds)?'checked':'' ?>>
            <span style="width:28px;height:28px;border-radius:50%;background:<?= getUserColor($u['id']) ?>;color:#fff;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0">
              <?= strtoupper(substr($u['prenom'],0,1).substr($u['nom'],0,1)) ?>
            </span>
            <span><?= sanitize($u['prenom'].' '.$u['nom']) ?></span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div style="display:flex;gap:10px">
      <button type="submit" class="btn btn-primary" style="flex:1;justify-content:center;padding:12px">
        💾 Enregistrer
      </button>
      <a href="<?= APP_URL ?>/pages/tasks/view.php?id=<?= $taskId ?>" class="btn btn-secondary">Annuler</a>
    </div>
  </div>
</div>
</form>

<script>
initUploadZone('uploadZoneEdit', 'fileInputEdit', 'fileListEdit');
document.getElementById('userSearch')?.addEventListener('input', function() {
  const q = this.value.toLowerCase();
  document.querySelectorAll('#userList label').forEach(lbl => {
    lbl.style.display = lbl.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
