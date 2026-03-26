<?php
require_once __DIR__ . '/../../includes/layout_init.php';

$pageTitle   = 'Nouvelle tâche';
$breadcrumbs = [
  ['label'=>'Accueil','url'=>APP_URL.'/index.php'],
  ['label'=>'Tâches','url'=>APP_URL.'/pages/tasks/list.php'],
  ['label'=>'Nouvelle tâche','url'=>''],
];

if (!isSuperviseur()) {
    flashMessage('error','Accès refusé.');
    redirect('/pages/tasks/list.php');
}

$pdo    = getDB();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $titre       = trim($_POST['titre'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $statut      = $_POST['statut'] ?? 'pas_fait';
    $priorite    = $_POST['priorite'] ?? 'normale';
    $date_debut  = $_POST['date_debut'] ?? null;
    $date_ech    = $_POST['date_echeance'] ?? '';
    $dept_id     = (int)($_POST['departement_id'] ?? 0) ?: null;
    $base_id     = (int)($_POST['base_id'] ?? 0) ?: null;
    $cat_id      = (int)($_POST['categorie_id'] ?? 0) ?: null;
    $pourcent    = (int)($_POST['pourcentage'] ?? 0);
    $parent_id   = (int)($_POST['tache_parente_id'] ?? 0) ?: null;
    $assignes    = $_POST['assignes'] ?? [];

    if (empty($titre))    $errors[] = 'Le titre est obligatoire.';
    if (empty($date_ech)) $errors[] = "La date d'échéance est obligatoire.";
    if (!array_key_exists($statut, TASK_STATUSES))     $errors[] = 'Statut invalide.';
    if (!array_key_exists($priorite, TASK_PRIORITIES)) $errors[] = 'Priorité invalide.';

    $ech_ts = $date_ech !== '' ? @strtotime($date_ech) : false;
    if ($ech_ts === false) {
        $errors[] = "Format de date d'échéance invalide.";
    } else {
        if ($ech_ts < strtotime(date('Y-m-d'))) {
            $errors[] = "La date d'échéance ne peut pas être dans le passé.";
        }
        if (!empty($date_debut)) {
            $debut_ts = @strtotime($date_debut);
            if ($debut_ts !== false && $debut_ts > $ech_ts) {
                $errors[] = "La date de début ne peut pas être après la date d'échéance.";
            }
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO taches
            (titre,description,statut,priorite,date_debut,date_echeance,createur_id,departement_id,base_id,categorie_id,pourcentage,tache_parente_id)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$titre,$description,$statut,$priorite,
            $date_debut ?: null, $date_ech,
            $currentUser['id'], $dept_id, $base_id, $cat_id, $pourcent, $parent_id]);
        $taskId = (int)$pdo->lastInsertId();

        // Assignations — vérifier que l'utilisateur existe et est actif
        if (!empty($assignes)) {
            $ins = $pdo->prepare("INSERT IGNORE INTO taches_assignees (tache_id,user_id) VALUES (?,?)");
            $validUser = $pdo->prepare("SELECT id FROM users WHERE id=? AND actif=1");
            foreach ($assignes as $uid) {
                $uid = (int)$uid;
                if ($uid < 1) continue;
                $validUser->execute([$uid]);
                if (!$validUser->fetch()) continue;
                $ins->execute([$taskId, $uid]);
                // Notification
                if ($uid !== $currentUser['id']) {
                    createNotification($uid, 'task', 'Nouvelle tâche assignée',
                        sanitize($currentUser['prenom']).' vous a assigné : '.sanitize($titre),
                        APP_URL.'/pages/tasks/view.php?id='.$taskId);
                }
            }
        }

        // Fichiers
        $attachFlash = null;
        $batch       = collectUploadedFiles('fichiers');
        if (!empty($batch)) {
            $fStmt = $pdo->prepare("INSERT INTO fichiers (tache_id,nom_original,chemin,taille,mime,uploaded_by) VALUES (?,?,?,?,?,?)");
            $okFiles = 0;
            foreach ($batch as $fdata) {
                $uploaded = handleFileUpload($fdata, $taskId);
                if ($uploaded) {
                    $fStmt->execute([$taskId, $uploaded['nom_original'], $uploaded['chemin'], $uploaded['taille'], $uploaded['mime'], $currentUser['id']]);
                    $okFiles++;
                }
            }
            if ($okFiles === 0) {
                $attachFlash = ['warning', 'Tâche créée, mais aucune pièce jointe enregistrée (format, taille max 10 Mo, ou dossier uploads/ inaccessible en écriture).'];
            } elseif ($okFiles < count($batch)) {
                $attachFlash = ['warning', 'Tâche créée : ' . $okFiles . ' fichier(s) sur ' . count($batch) . ' enregistré(s).'];
            }
        }

        logChange($taskId, $currentUser['id'], 'creation', '', $titre, 'creation');
        if ($attachFlash !== null) {
            flashMessage($attachFlash[0], $attachFlash[1]);
        } else {
            flashMessage('success', 'Tâche créée avec succès.');
        }
        redirect('/pages/tasks/view.php?id='.$taskId);
    }
}

// Données formulaire
$users     = $pdo->query("SELECT id,prenom,nom,role FROM users WHERE actif=1 ORDER BY nom")->fetchAll();
$depts     = $pdo->query("SELECT id,nom FROM departements WHERE actif=1 ORDER BY nom")->fetchAll();
$bases     = $pdo->query("SELECT id,nom FROM bases WHERE actif=1 ORDER BY nom")->fetchAll();
$cats      = $pdo->query("SELECT id,nom,couleur FROM categories ORDER BY nom")->fetchAll();
$parentOpts= $pdo->query("SELECT id,titre FROM taches WHERE statut != 'annule' ORDER BY titre LIMIT 100")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div>
    <div class="page-title">Nouvelle tâche</div>
    <div class="page-subtitle">Créer et assigner une tâche</div>
  </div>
  <a href="<?= APP_URL ?>/pages/tasks/list.php" class="btn btn-secondary">← Retour</a>
</div>

<?php if (!empty($errors)): ?>
<div class="flash flash-error" style="margin-bottom:20px">
  <ul style="margin:0;padding-left:18px"><?php foreach ($errors as $e): ?><li><?= sanitize($e) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">
<input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

<div style="display:grid;grid-template-columns:1fr 320px;gap:20px">

  <!-- Colonne principale -->
  <div>
    <div class="card" style="margin-bottom:16px">
      <div class="card-header"><span class="card-title">Informations de la tâche</span></div>
      <div class="card-body">
        <div class="form-group">
          <label class="form-label">Titre <span class="req">*</span></label>
          <input type="text" name="titre" class="form-control" placeholder="Titre de la tâche" value="<?= sanitize($_POST['titre'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" rows="5" placeholder="Décrivez la tâche en détail..."><?= sanitize($_POST['description'] ?? '') ?></textarea>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Date de début</label>
            <input type="text" name="date_debut" class="form-control datepicker" placeholder="jj/mm/aaaa" value="<?= sanitize($_POST['date_debut'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Date d'échéance <span class="req">*</span></label>
            <input type="text" name="date_echeance" class="form-control datepicker-future" placeholder="jj/mm/aaaa" value="<?= sanitize($_POST['date_echeance'] ?? '') ?>" required>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Statut initial</label>
            <select name="statut" class="form-control">
              <?php foreach (TASK_STATUSES as $k => $s): ?>
                <option value="<?= $k ?>" <?= ($_POST['statut']??'pas_fait')===$k?'selected':'' ?>><?= $s['label'] ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Priorité</label>
            <select name="priorite" class="form-control">
              <?php foreach (TASK_PRIORITIES as $k => $p): ?>
                <option value="<?= $k ?>" <?= ($_POST['priorite']??'normale')===$k?'selected':'' ?>><?= $p['label'] ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Progression : <span id="pourcLabel"><?= (int)($_POST['pourcentage']??0) ?>%</span></label>
          <input type="range" name="pourcentage" min="0" max="100" value="<?= (int)($_POST['pourcentage']??0) ?>"
            oninput="document.getElementById('pourcLabel').textContent=this.value+'%'" style="width:100%">
        </div>
      </div>
    </div>

    <!-- Fichiers -->
    <div class="card">
      <div class="card-header"><span class="card-title">Pièces jointes</span></div>
      <div class="card-body">
        <div class="upload-zone" id="uploadZone">
          <div style="font-size:32px;margin-bottom:8px">📎</div>
          <div>Glissez vos fichiers ici ou <strong>cliquez pour sélectionner</strong></div>
          <div style="font-size:11px;color:var(--text3);margin-top:4px">PDF, Word, Excel, images, ZIP — max 10 Mo/fichier</div>
          <input type="file" name="fichiers[]" id="fileInput" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.zip,.rar">
        </div>
        <div class="file-list" id="fileList"></div>
      </div>
    </div>
  </div>

  <!-- Colonne droite -->
  <div>
    <div class="card" style="margin-bottom:16px">
      <div class="card-header"><span class="card-title">Organisation</span></div>
      <div class="card-body">
        <div class="form-group">
          <label class="form-label">Département</label>
          <select name="departement_id" class="form-control">
            <option value="">— Choisir —</option>
            <?php foreach ($depts as $d): ?>
              <option value="<?= $d['id'] ?>" <?= ($_POST['departement_id']??'')==$d['id']?'selected':'' ?>><?= sanitize($d['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Base géographique</label>
          <select name="base_id" class="form-control">
            <option value="">— Choisir —</option>
            <?php foreach ($bases as $b): ?>
              <option value="<?= $b['id'] ?>" <?= ($_POST['base_id']??'')==$b['id']?'selected':'' ?>><?= sanitize($b['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Catégorie</label>
          <select name="categorie_id" class="form-control">
            <option value="">— Aucune —</option>
            <?php foreach ($cats as $c): ?>
              <option value="<?= $c['id'] ?>" <?= ($_POST['categorie_id']??'')==$c['id']?'selected':'' ?>><?= sanitize($c['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Tâche parente (optionnel)</label>
          <select name="tache_parente_id" class="form-control">
            <option value="">— Aucune —</option>
            <?php foreach ($parentOpts as $p): ?>
              <option value="<?= $p['id'] ?>"><?= sanitize($p['titre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>

    <!-- Assignation -->
    <div class="card" style="margin-bottom:16px">
      <div class="card-header"><span class="card-title">Assigner à</span></div>
      <div class="card-body">
        <input type="text" id="userSearch" class="form-control" placeholder="Rechercher un employé..." style="margin-bottom:10px">
        <div id="userList" style="max-height:200px;overflow-y:auto;border:1px solid var(--border);border-radius:var(--radius-sm)">
          <?php foreach ($users as $u): ?>
          <label style="display:flex;align-items:center;gap:8px;padding:8px 12px;cursor:pointer;border-bottom:1px solid var(--border);font-size:13px">
            <input type="checkbox" name="assignes[]" value="<?= $u['id'] ?>"
              <?= in_array($u['id'], (array)($_POST['assignes']??[]))?'checked':'' ?>>
            <span style="width:28px;height:28px;border-radius:50%;background:<?= getUserColor($u['id']) ?>;color:#fff;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0">
              <?= strtoupper(substr($u['prenom'],0,1).substr($u['nom'],0,1)) ?>
            </span>
            <span><?= sanitize($u['prenom'].' '.$u['nom']) ?></span>
            <span style="font-size:10px;color:var(--text3);margin-left:auto"><?= ROLES[$u['role']] ?? '' ?></span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:12px">
      ✅ Créer la tâche
    </button>
  </div>
</div>
</form>

<script>
initUploadZone('uploadZone','fileInput','fileList');

// Filtre liste utilisateurs
document.getElementById('userSearch').addEventListener('input', function() {
  const q = this.value.toLowerCase();
  document.querySelectorAll('#userList label').forEach(lbl => {
    lbl.style.display = lbl.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
