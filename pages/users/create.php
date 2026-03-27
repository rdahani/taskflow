<?php
/**
 * Création d'utilisateur — page autonome.
 * L'édition est gérée par edit.php.
 */
require_once __DIR__ . '/../../includes/layout_init.php';

if (!isAdmin()) {
    flashMessage('error', 'Accès réservé aux administrateurs.');
    redirect('/index.php');
}

$pdo         = getDB();
$pageTitle   = 'Nouvel utilisateur';
$breadcrumbs = [
    ['label' => 'Accueil',       'url' => APP_URL . '/index.php'],
    ['label' => 'Utilisateurs',  'url' => APP_URL . '/pages/users/list.php'],
    ['label' => 'Nouvel utilisateur', 'url' => ''],
];

$errors = [];
$form   = [
    'prenom' => '', 'nom' => '', 'email' => '', 'role' => 'employe',
    'departement_id' => '', 'base_id' => '', 'poste' => '', 'telephone' => '',
    'actif' => 1, 'notify_email' => 1,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $form['prenom']         = trim($_POST['prenom']         ?? '');
    $form['nom']            = trim($_POST['nom']            ?? '');
    $form['email']          = trim($_POST['email']          ?? '');
    $form['role']           = $_POST['role']                ?? 'employe';
    $form['departement_id'] = (int) ($_POST['departement_id'] ?? 0) ?: null;
    $form['base_id']        = (int) ($_POST['base_id']       ?? 0) ?: null;
    $form['poste']          = trim($_POST['poste']          ?? '');
    $form['telephone']      = trim($_POST['telephone']      ?? '');
    $form['actif']          = isset($_POST['actif'])        ? 1 : 0;
    $form['notify_email']   = isset($_POST['notify_email']) ? 1 : 0;
    $password               = $_POST['password']            ?? '';
    $password2              = $_POST['password2']           ?? '';

    if ($form['prenom'] === '') $errors[] = 'Le prénom est obligatoire.';
    if ($form['nom']    === '') $errors[] = 'Le nom est obligatoire.';
    if ($form['email']  === '' || !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email invalide.';
    }
    if (!array_key_exists($form['role'], ROLES)) {
        $errors[] = 'Rôle invalide.';
    }
    if ($password === '') {
        $errors[] = 'Le mot de passe est obligatoire.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Le mot de passe doit faire au moins 8 caractères.';
    } elseif ($password !== $password2) {
        $errors[] = 'Les mots de passe ne correspondent pas.';
    }

    // Email unique
    $ck = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $ck->execute([$form['email']]);
    if ($ck->fetch()) {
        $errors[] = 'Cet email est déjà utilisé.';
    }

    if (empty($errors)) {
        // Photo
        $photoName = null;
        if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                $dir = UPLOAD_DIR . 'photos/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $photoName = uniqid('photo_', true) . '.' . $ext;
                move_uploaded_file($_FILES['photo']['tmp_name'], $dir . $photoName);
            }
        }

        // Auto-migration notify_email
        $hasNotify = true;
        try {
            $pdo->query("SELECT notify_email FROM users LIMIT 0");
        } catch (PDOException $e) {
            $hasNotify = false;
            try {
                $pdo->exec("ALTER TABLE users ADD COLUMN notify_email TINYINT(1) NOT NULL DEFAULT 1 AFTER email");
                $hasNotify = true;
            } catch (PDOException $e2) { /* continue sans */ }
        }

        $hash        = password_hash($password, PASSWORD_BCRYPT);
        $insertCols  = 'nom, prenom, email, password, role, departement_id, base_id, poste, telephone, actif, photo';
        $insertMarks = '?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?';
        $params      = [
            $form['nom'], $form['prenom'], $form['email'], $hash, $form['role'],
            $form['departement_id'], $form['base_id'], $form['poste'], $form['telephone'],
            $form['actif'], $photoName,
        ];
        if ($hasNotify) {
            $insertCols  .= ', notify_email';
            $insertMarks .= ', ?';
            $params[]     = $form['notify_email'];
        }

        try {
            $pdo->prepare("INSERT INTO users ($insertCols) VALUES ($insertMarks)")->execute($params);
            $newId = (int) $pdo->lastInsertId();

            // Valider que les assignés sont actifs avant insertion (si des tâches existent)
            require_once __DIR__ . '/../../includes/audit.php';
            logAudit((int) $currentUser['id'], 'user_create', 'user', $newId, $form['email']);

            flashMessage('success', 'Utilisateur créé. Communiquez le mot de passe à l\'utilisateur en privé.');
            redirect('/pages/users/list.php');
        } catch (PDOException $e) {
            error_log('TaskFlow create user: ' . $e->getMessage());
            $errors[] = defined('APP_DEBUG') && APP_DEBUG
                ? 'Erreur BD : ' . htmlspecialchars($e->getMessage())
                : 'Erreur lors de la création. Veuillez réessayer.';
        }
    }
}

$depts = $pdo->query("SELECT id, nom FROM departements WHERE actif = 1 ORDER BY nom")->fetchAll();
$bases = $pdo->query("SELECT id, nom FROM bases       WHERE actif = 1 ORDER BY nom")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div><div class="page-title"><?= $pageTitle ?></div></div>
  <a href="<?= APP_URL ?>/pages/users/list.php" class="btn btn-secondary">← Retour</a>
</div>

<?php if (!empty($errors)): ?>
<div class="flash flash-error" style="margin-bottom:20px">
  <ul style="margin:0;padding-left:18px">
    <?php foreach ($errors as $err): ?>
      <li><?= sanitize($err) ?></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<form method="POST" action="<?= APP_URL ?>/pages/users/create.php"
      enctype="multipart/form-data">
  <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

  <div style="display:grid;grid-template-columns:1fr 280px;gap:20px">

    <!-- Colonne gauche -->
    <div>
      <div class="card" style="margin-bottom:16px">
        <div class="card-header"><span class="card-title">Identité</span></div>
        <div class="card-body">
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Prénom <span class="req">*</span></label>
              <input type="text" name="prenom" class="form-control"
                     value="<?= sanitize($form['prenom']) ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label">Nom <span class="req">*</span></label>
              <input type="text" name="nom" class="form-control"
                     value="<?= sanitize($form['nom']) ?>" required>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Email <span class="req">*</span></label>
              <input type="email" name="email" class="form-control"
                     value="<?= sanitize($form['email']) ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label">Téléphone</label>
              <input type="text" name="telephone" class="form-control"
                     value="<?= sanitize($form['telephone']) ?>"
                     placeholder="+227 XX XX XX XX">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Poste / Fonction</label>
            <input type="text" name="poste" class="form-control"
                   value="<?= sanitize($form['poste']) ?>"
                   placeholder="Ex: Responsable logistique">
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><span class="card-title">Mot de passe</span></div>
        <div class="card-body">
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Mot de passe <span class="req">*</span></label>
              <input type="password" name="password" class="form-control"
                     placeholder="••••••••" minlength="8" required autocomplete="new-password">
            </div>
            <div class="form-group">
              <label class="form-label">Confirmer <span class="req">*</span></label>
              <input type="password" name="password2" class="form-control"
                     placeholder="••••••••" minlength="8" autocomplete="new-password">
            </div>
          </div>
          <div class="form-hint">Minimum 8 caractères.</div>
        </div>
      </div>
    </div>

    <!-- Colonne droite -->
    <div>
      <div class="card" style="margin-bottom:16px">
        <div class="card-header"><span class="card-title">Photo</span></div>
        <div class="card-body" style="text-align:center">
          <div class="user-avatar"
               style="width:80px;height:80px;font-size:28px;font-weight:700;
                      background:#6B7280;margin:0 auto 12px">?</div>
          <input type="file" name="photo" accept="image/*" class="form-control" style="font-size:12px">
        </div>
      </div>

      <div class="card" style="margin-bottom:16px">
        <div class="card-header"><span class="card-title">Rôle & Organisation</span></div>
        <div class="card-body">
          <div class="form-group">
            <label class="form-label">Rôle <span class="req">*</span></label>
            <select name="role" class="form-control">
              <?php foreach (ROLES as $k => $v): ?>
                <option value="<?= $k ?>" <?= $form['role'] === $k ? 'selected' : '' ?>>
                  <?= $v ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Département</label>
            <select name="departement_id" class="form-control">
              <option value="">— Aucun —</option>
              <?php foreach ($depts as $d): ?>
                <option value="<?= $d['id'] ?>"
                  <?= (string)($form['departement_id'] ?? '') === (string)$d['id'] ? 'selected' : '' ?>>
                  <?= sanitize($d['nom']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Base géographique</label>
            <select name="base_id" class="form-control">
              <option value="">— Aucune —</option>
              <?php foreach ($bases as $b): ?>
                <option value="<?= $b['id'] ?>"
                  <?= (string)($form['base_id'] ?? '') === (string)$b['id'] ? 'selected' : '' ?>>
                  <?= sanitize($b['nom']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px">
              <input type="checkbox" name="actif" value="1"
                     <?= $form['actif'] ? 'checked' : '' ?>>
              Compte actif
            </label>
          </div>
          <div class="form-group">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px">
              <input type="checkbox" name="notify_email" value="1"
                     <?= $form['notify_email'] ? 'checked' : '' ?>>
              Notifications par e-mail
            </label>
          </div>
        </div>
      </div>

      <button type="submit" class="btn btn-primary"
              style="width:100%;justify-content:center;padding:12px">
        ➕ Créer l'utilisateur
      </button>
    </div>

  </div>
</form>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
