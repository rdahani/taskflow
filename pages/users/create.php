<?php
require_once __DIR__ . '/../../includes/layout_init.php';

// Édition depuis edit.php : id déjà validé (évite divergence GET/POST selon l’hébergeur).
if (defined('TF_EDIT_USER_ID')) {
    $userId = (int) TF_EDIT_USER_ID;
} else {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'POST') {
        $userId = (int) ($_POST['id'] ?? 0);
        if ($userId < 1) {
            $userId = (int) ($_GET['id'] ?? 0);
        }
    } else {
        $userId = (int) ($_GET['id'] ?? 0);
    }
}
$isEdit    = $userId > 0;
$pageTitle = $isEdit ? 'Modifier l\'utilisateur' : 'Nouvel utilisateur';
$breadcrumbs = [
  ['label'=>'Accueil','url'=>APP_URL.'/index.php'],
  ['label'=>'Utilisateurs','url'=>APP_URL.'/pages/users/list.php'],
  ['label'=>$pageTitle,'url'=>''],
];

if (!isAdmin()) { flashMessage('error','Accès réservé aux administrateurs.'); redirect('/index.php'); }

$pdo    = getDB();
$errors = [];
$user   = [];

if ($isEdit) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) { flashMessage('error','Utilisateur introuvable.'); redirect('/pages/users/list.php'); }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $nom     = trim($_POST['nom'] ?? '');
    $prenom  = trim($_POST['prenom'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $role    = $_POST['role'] ?? 'employe';
    $deptId  = (int)($_POST['departement_id'] ?? 0) ?: null;
    $baseId  = (int)($_POST['base_id'] ?? 0) ?: null;
    $poste   = trim($_POST['poste'] ?? '');
    $tel     = trim($_POST['telephone'] ?? '');
    $actif        = isset($_POST['actif']) ? 1 : 0;
    $notifyEmail  = isset($_POST['notify_email']) ? 1 : 0;
    $password     = $_POST['password'] ?? '';
    $password2= $_POST['password2'] ?? '';

    if (empty($nom))    $errors[] = 'Le nom est obligatoire.';
    if (empty($prenom)) $errors[] = 'Le prénom est obligatoire.';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalide.';
    if (!array_key_exists($role, ROLES)) $errors[] = 'Rôle invalide.';

    if (!$isEdit && empty($password)) $errors[] = 'Le mot de passe est obligatoire.';
    if (!empty($password)) {
        if (strlen($password) < 8) $errors[] = 'Le mot de passe doit faire au moins 8 caractères.';
        if ($password !== $password2) $errors[] = 'Les mots de passe ne correspondent pas.';
    }

    // Email unique
    $emailCheck = $pdo->prepare("SELECT id FROM users WHERE email=? AND id!=?");
    $emailCheck->execute([$email, $userId]);
    if ($emailCheck->fetch()) $errors[] = 'Cet email est déjà utilisé.';

    if (empty($errors)) {
        // Auto-migration : s'assurer que notify_email existe
        static $colNotifyEmail = null;
        if ($colNotifyEmail === null) {
            try {
                $pdo->query("SELECT notify_email FROM users LIMIT 0");
                $colNotifyEmail = true;
            } catch (PDOException $migEx) {
                $colNotifyEmail = false;
                try {
                    $pdo->exec("ALTER TABLE users ADD COLUMN notify_email TINYINT(1) NOT NULL DEFAULT 1 AFTER email");
                    $colNotifyEmail = true;
                } catch (PDOException $e2) { /* on continue sans */ }
            }
        }

        // Photo
        $photoName = $user['photo'] ?? null;
        if (!empty($_FILES['photo']['name'])) {
            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                $dir = UPLOAD_DIR . 'photos/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $photoName = uniqid('photo_',true).'.'.$ext;
                move_uploaded_file($_FILES['photo']['tmp_name'], $dir.$photoName);
            }
        }

        require_once __DIR__ . '/../../includes/audit.php';
        try {
            if ($isEdit) {
                $setCols = "nom=?,prenom=?,email=?,role=?,departement_id=?,base_id=?,poste=?,telephone=?,actif=?";
                $params  = [$nom,$prenom,$email,$role,$deptId,$baseId,$poste,$tel,$actif];
                if ($colNotifyEmail) { $setCols .= ',notify_email=?'; $params[] = $notifyEmail; }
                $setCols .= ',photo=?'; $params[] = $photoName;
                if (!empty($password)) { $setCols .= ',password=?'; $params[] = password_hash($password, PASSWORD_BCRYPT); }
                $params[] = $userId;
                $pdo->prepare("UPDATE users SET $setCols WHERE id=?")->execute($params);
                logAudit((int) $currentUser['id'], 'user_update', 'user', $userId, $email);
                flashMessage('success','Utilisateur modifié.');
            } else {
                $hash         = password_hash($password, PASSWORD_BCRYPT);
                $insertCols   = 'nom,prenom,email,password,role,departement_id,base_id,poste,telephone,actif,photo';
                $insertMarks  = '?,?,?,?,?,?,?,?,?,?,?';
                $insertParams = [$nom,$prenom,$email,$hash,$role,$deptId,$baseId,$poste,$tel,$actif,$photoName];
                if ($colNotifyEmail) {
                    $insertCols  .= ',notify_email';
                    $insertMarks .= ',?';
                    $insertParams[] = $notifyEmail;
                }
                $pdo->prepare("INSERT INTO users ($insertCols) VALUES ($insertMarks)")->execute($insertParams);
                $newId = (int) $pdo->lastInsertId();
                logAudit((int) $currentUser['id'], 'user_create', 'user', $newId, $email);
                flashMessage('success','Utilisateur créé. Communiquez le mot de passe à l\'utilisateur en privé.');
            }
            redirect('/pages/users/list.php');
        } catch (PDOException $e) {
            error_log('TaskFlow user save: ' . $e->getMessage());
            $errors[] = defined('APP_DEBUG') && APP_DEBUG
                ? 'Erreur BD : ' . htmlspecialchars($e->getMessage())
                : 'Erreur lors de la sauvegarde. Veuillez réessayer.';
        }
    }
}

$depts = $pdo->query("SELECT id,nom FROM departements WHERE actif=1 ORDER BY nom")->fetchAll();
$bases = $pdo->query("SELECT id,nom FROM bases WHERE actif=1 ORDER BY nom")->fetchAll();

// URL explicite : PHP_SELF est souvent faux (réécriture, index.php front) → POST perd edit.php.
$formAction = $isEdit
    ? APP_URL . '/pages/users/edit.php?id=' . (int) $userId
    : APP_URL . '/pages/users/create.php';

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div><div class="page-title"><?= $pageTitle ?></div></div>
  <a href="<?= APP_URL ?>/pages/users/list.php" class="btn btn-secondary">← Retour</a>
</div>

<?php if (!empty($errors)): ?>
<div class="flash flash-error" style="margin-bottom:20px">
  <ul style="margin:0;padding-left:18px"><?php foreach ($errors as $e): ?><li><?= sanitize($e) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<form method="POST"
      action="<?= htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8') ?>"
      enctype="multipart/form-data">
<input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
<?php if ($isEdit): ?>
<input type="hidden" name="id" value="<?= (int) $userId ?>">
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 280px;gap:20px">
  <div>
    <div class="card" style="margin-bottom:16px">
      <div class="card-header"><span class="card-title">Identité</span></div>
      <div class="card-body">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Prénom <span class="req">*</span></label>
            <input type="text" name="prenom" class="form-control" value="<?= sanitize($user['prenom'] ?? $_POST['prenom'] ?? '') ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Nom <span class="req">*</span></label>
            <input type="text" name="nom" class="form-control" value="<?= sanitize($user['nom'] ?? $_POST['nom'] ?? '') ?>" required>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Email <span class="req">*</span></label>
            <input type="email" name="email" class="form-control" value="<?= sanitize($user['email'] ?? $_POST['email'] ?? '') ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Téléphone</label>
            <input type="text" name="telephone" class="form-control" value="<?= sanitize($user['telephone'] ?? '') ?>" placeholder="+227 XX XX XX XX">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Poste / Fonction</label>
          <input type="text" name="poste" class="form-control" value="<?= sanitize($user['poste'] ?? '') ?>" placeholder="Ex: Responsable logistique">
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><span class="card-title"><?= $isEdit ? 'Changer le mot de passe' : 'Mot de passe' ?></span></div>
      <div class="card-body">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Mot de passe <?= !$isEdit?'<span class="req">*</span>':'' ?></label>
            <input type="password" name="password" class="form-control" placeholder="••••••••"
              <?= $isEdit ? '' : 'minlength="8" required' ?>>
          </div>
          <div class="form-group">
            <label class="form-label">Confirmer</label>
            <input type="password" name="password2" class="form-control" placeholder="••••••••"
              <?= $isEdit ? '' : 'minlength="8"' ?>>
          </div>
        </div>
        <div class="form-hint"><?= $isEdit ? 'Laissez vide pour ne rien changer. Sinon minimum 8 caractères (validation à l’enregistrement).' : 'Minimum 8 caractères.' ?></div>
      </div>
    </div>
  </div>

  <div>
    <!-- Photo -->
    <div class="card" style="margin-bottom:16px">
      <div class="card-header"><span class="card-title">Photo</span></div>
      <div class="card-body" style="text-align:center">
        <?php if (!empty($user['photo'])): ?>
        <img src="<?= APP_URL ?>/uploads/photos/<?= sanitize($user['photo']) ?>" style="width:80px;height:80px;border-radius:50%;object-fit:cover;margin-bottom:12px">
        <?php else: ?>
        <div class="user-avatar" style="width:80px;height:80px;font-size:28px;font-weight:700;background:<?= getUserColor($userId ?: 0) ?>;margin:0 auto 12px">
          <?= !empty($user) ? strtoupper(substr($user['prenom'],0,1).substr($user['nom'],0,1)) : '?' ?>
        </div>
        <?php endif; ?>
        <input type="file" name="photo" accept="image/*" class="form-control" style="font-size:12px">
      </div>
    </div>

    <!-- Rôle & Organisation -->
    <div class="card" style="margin-bottom:16px">
      <div class="card-header"><span class="card-title">Rôle & Organisation</span></div>
      <div class="card-body">
        <div class="form-group">
          <label class="form-label">Rôle <span class="req">*</span></label>
          <select name="role" class="form-control">
            <?php foreach (ROLES as $k=>$v): ?>
              <option value="<?= $k ?>" <?= ($user['role']??'employe')===$k?'selected':'' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Département</label>
          <select name="departement_id" class="form-control">
            <option value="">— Aucun —</option>
            <?php foreach ($depts as $d): ?>
              <option value="<?= $d['id'] ?>" <?= ($user['departement_id']??'')==$d['id']?'selected':'' ?>><?= sanitize($d['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Base géographique</label>
          <select name="base_id" class="form-control">
            <option value="">— Aucune —</option>
            <?php foreach ($bases as $b): ?>
              <option value="<?= $b['id'] ?>" <?= ($user['base_id']??'')==$b['id']?'selected':'' ?>><?= sanitize($b['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px">
            <input type="checkbox" name="actif" value="1" <?= ($user['actif']??1)?'checked':'' ?>>
            Compte actif
          </label>
        </div>
        <div class="form-group">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px">
            <input type="checkbox" name="notify_email" value="1" <?= ($user['notify_email']??1)?'checked':'' ?>>
            Notifications par e-mail
          </label>
        </div>
      </div>
    </div>

    <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:12px">
      <?= $isEdit ? '💾 Enregistrer' : '➕ Créer l\'utilisateur' ?>
    </button>
  </div>
</div>
</form>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
