<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/login_throttle.php';

if (isLoggedIn()) {
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

$error = '';
$flash = ($_SERVER['REQUEST_METHOD'] !== 'POST') ? getFlash() : null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $blocked  = $email !== '' ? loginThrottleCheck($email) : null;
    if ($blocked) {
        $error = $blocked;
    } elseif (empty($email) || empty($password)) {
        $error = 'Veuillez remplir tous les champs.';
    } elseif (!login($email, $password)) {
        loginThrottleFailure($email);
        $error = 'Email ou mot de passe incorrect.';
    } else {
        loginThrottleSuccess($email);
        header('Location: ' . APP_URL . '/index.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Connexion — <?= APP_NAME ?></title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/inter.css">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/fontawesome.min.css">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
<style>
body {
  margin: 0;
  min-height: 100vh;
  display: flex;
  overflow: hidden;
}

/* Left gradient panel */
.login-left {
  flex: 0 0 42%;
  background: linear-gradient(135deg, #004F82 0%, #0086CD 55%, #009640 100%);
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  padding: 3rem;
  position: relative;
  overflow: hidden;
}
.login-left::before {
  content: '';
  position: absolute;
  top: -100px; right: -100px;
  width: 400px; height: 400px;
  border-radius: 50%;
  background: rgba(255,255,255,0.04);
}
.login-left::after {
  content: '';
  position: absolute;
  bottom: -80px; left: -80px;
  width: 300px; height: 300px;
  border-radius: 50%;
  background: rgba(255,255,255,0.05);
}

/* Right form panel */
.login-right {
  flex: 1;
  background: var(--bg);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem;
}

.login-card {
  width: 100%;
  max-width: 420px;
  background: var(--surface);
  border-radius: 20px;
  border: 1px solid var(--border);
  box-shadow: var(--shadow-lg);
  overflow: hidden;
}

.feature-item {
  display: flex;
  align-items: center;
  gap: 0.875rem;
  padding: 0.875rem 0;
  border-bottom: 1px solid rgba(255,255,255,0.08);
  position: relative;
  z-index: 1;
}
.feature-item:last-child { border-bottom: none; }

.feature-icon {
  width: 38px; height: 38px;
  border-radius: 10px;
  background: rgba(255,255,255,0.1);
  display: flex; align-items: center; justify-content: center;
  font-size: 16px; color: white; flex-shrink: 0;
}

@keyframes float {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-8px); }
}
.float-icon { animation: float 4s ease-in-out infinite; }

@media (max-width: 768px) {
  .login-left { display: none; }
  body { justify-content: center; background: var(--bg); }
}
</style>
</head>
<body>

<!-- Left panel -->
<div class="login-left">
  <div style="position:relative;z-index:1;text-align:center;margin-bottom:2.5rem">
    <div class="float-icon" style="width:72px;height:72px;border-radius:18px;background:rgba(255,255,255,0.15);border:2px solid rgba(255,255,255,0.3);display:flex;align-items:center;justify-content:center;font-size:28px;margin:0 auto 1.25rem">
      <i class="fa-solid fa-bolt" style="color:white"></i>
    </div>
    <h1 style="font-size:28px;font-weight:800;color:white;margin:0 0 0.5rem;letter-spacing:-0.5px"><?= APP_NAME ?></h1>
    <p style="font-size:14px;color:rgba(255,255,255,0.8);margin:0">Gestion collaborative de tâches</p>
  </div>

  <div style="position:relative;z-index:1;width:100%;max-width:300px">
    <div class="feature-item">
      <div class="feature-icon"><i class="fa-solid fa-list-check"></i></div>
      <div>
        <div style="font-size:14px;font-weight:600;color:white">Suivi des tâches</div>
        <div style="font-size:12px;color:rgba(255,255,255,0.7)">Organisez et priorisez votre travail</div>
      </div>
    </div>
    <div class="feature-item">
      <div class="feature-icon"><i class="fa-solid fa-table-columns"></i></div>
      <div>
        <div style="font-size:14px;font-weight:600;color:white">Vue Kanban</div>
        <div style="font-size:12px;color:rgba(255,255,255,0.7)">Visualisez l'avancement en temps réel</div>
      </div>
    </div>
    <div class="feature-item">
      <div class="feature-icon"><i class="fa-solid fa-chart-line"></i></div>
      <div>
        <div style="font-size:14px;font-weight:600;color:white">Rapports & Stats</div>
        <div style="font-size:12px;color:rgba(255,255,255,0.7)">Suivez les performances par équipe</div>
      </div>
    </div>
    <div class="feature-item">
      <div class="feature-icon"><i class="fa-solid fa-shield-halved"></i></div>
      <div>
        <div style="font-size:14px;font-weight:600;color:white">Rôles & Permissions</div>
        <div style="font-size:12px;color:rgba(255,255,255,0.7)">Accès sécurisé par département</div>
      </div>
    </div>
  </div>

  <p style="position:relative;z-index:1;margin-top:2.5rem;font-size:11px;color:rgba(255,255,255,0.4);text-align:center">
    Accès sécurisé · Données protégées
  </p>
</div>

<!-- Right panel -->
<div class="login-right">
  <div class="login-card">
    <!-- Top accent bar -->
    <div style="height:4px;background:linear-gradient(90deg,#0086CD,#009640)"></div>

    <div style="padding:2rem 2rem 1.5rem">
      <div style="margin-bottom:1.75rem">
        <h2 style="font-size:22px;font-weight:800;color:var(--text);letter-spacing:-0.3px;margin:0 0 6px">Connexion</h2>
        <p style="font-size:13.5px;color:var(--text3);margin:0">Entrez vos identifiants pour accéder à votre espace</p>
      </div>

      <?php if ($flash): ?>
      <?php
        $isErr = ($flash['type'] ?? '') === 'error';
        $fbg = $isErr ? 'var(--danger-light)' : 'var(--info-light, #E0F2FE)';
        $fbd = $isErr ? '#FCA5A5' : '#7DD3FC';
        $fco = $isErr ? 'var(--danger)' : 'var(--primary)';
        $fic = $isErr ? 'fa-circle-exclamation' : 'fa-circle-info';
      ?>
      <div style="display:flex;align-items:center;gap:10px;background:<?= $fbg ?>;color:<?= $fco ?>;border:1px solid <?= $fbd ?>;border-radius:10px;padding:10px 14px;font-size:13px;margin-bottom:1.25rem">
        <i class="fa-solid <?= $fic ?>" style="flex-shrink:0"></i>
        <span><?= sanitize($flash['message']) ?></span>
      </div>
      <?php endif; ?>
      <?php if ($error): ?>
      <div style="display:flex;align-items:center;gap:10px;background:var(--danger-light);color:var(--danger);border:1px solid #FCA5A5;border-radius:10px;padding:10px 14px;font-size:13px;margin-bottom:1.25rem">
        <i class="fa-solid fa-circle-exclamation" style="flex-shrink:0"></i>
        <span><?= sanitize($error) ?></span>
      </div>
      <?php endif; ?>

      <form method="POST" autocomplete="on">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

        <!-- Email -->
        <div class="form-group">
          <label class="form-label">Adresse email</label>
          <div style="position:relative">
            <span style="position:absolute;left:0.875rem;top:50%;transform:translateY(-50%);color:var(--text3);font-size:14px">
              <i class="fa-regular fa-envelope"></i>
            </span>
            <input type="email" name="email" class="form-control"
                   style="padding-left:2.625rem"
                   placeholder="votre@email.com"
                   value="<?= sanitize($_POST['email'] ?? '') ?>"
                   required autofocus autocomplete="email">
          </div>
        </div>

        <!-- Password -->
        <div class="form-group">
          <label class="form-label">Mot de passe</label>
          <div style="position:relative">
            <span style="position:absolute;left:0.875rem;top:50%;transform:translateY(-50%);color:var(--text3);font-size:14px">
              <i class="fa-solid fa-lock"></i>
            </span>
            <input type="password" name="password" id="pwdInput" class="form-control"
                   style="padding-left:2.625rem;padding-right:2.625rem"
                   placeholder="••••••••"
                   required autocomplete="current-password">
            <button type="button" id="pwdToggle"
                    onclick="const i=document.getElementById('pwdInput');i.type=i.type==='password'?'text':'password';document.getElementById('pwdEye').className=i.type==='password'?'fa-regular fa-eye':'fa-regular fa-eye-slash'"
                    style="position:absolute;right:0.875rem;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text3);cursor:pointer;padding:0;font-size:14px">
              <i id="pwdEye" class="fa-regular fa-eye"></i>
            </button>
          </div>
        </div>

        <button type="submit" class="btn btn-primary"
                style="width:100%;justify-content:center;padding:0.75rem;font-size:14.5px;border-radius:12px">
          <i class="fa-solid fa-right-to-bracket"></i>
          Se connecter
        </button>
      </form>
    </div>

    <div style="padding:1rem 2rem 1.5rem;border-top:1px solid var(--border);text-align:center">
      <p style="font-size:12px;color:var(--text3);margin:0">
        Problème de connexion ? Contactez votre administrateur.
      </p>
    </div>
  </div>
</div>

<script>
// Dark mode on login page
const dm = localStorage.getItem('darkMode') === '1' ||
  (!localStorage.getItem('darkMode') && window.matchMedia('(prefers-color-scheme: dark)').matches);
if (dm) document.documentElement.classList.add('dark');
</script>
</body>
</html>
