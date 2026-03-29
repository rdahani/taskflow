<?php
// includes/header.php — Layout HTML (après traitements POST / redirect éventuels)
if (!defined('TASKFLOW_BOOTSTRAPPED')) {
    require_once __DIR__ . '/layout_init.php';
}

$flash     = getFlash();
$pageTitle = $pageTitle ?? APP_NAME;
$initials  = strtoupper(substr($currentUser['prenom'], 0, 1) . substr($currentUser['nom'], 0, 1));
$path      = $_SERVER['PHP_SELF'] ?? '';
// Préfixe URL pour les appels fetch (vide si APP_URL est à la racine du domaine, ex. https://task.example.org)
$tfBasePath = '';
if (defined('APP_URL')) {
    $parts = parse_url(APP_URL);
    if (!empty($parts['path']) && $parts['path'] !== '/') {
        $tfBasePath = rtrim($parts['path'], '/');
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#004F82">
<title><?= sanitize($pageTitle) ?> — <?= APP_NAME ?></title>
<link rel="manifest" href="<?= APP_URL ?>/manifest.json">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/inter.css">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/fontawesome.min.css">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/fr.js"></script>
<script>window.TF_BASE=<?= json_encode($tfBasePath, JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</head>
<body>

<div class="app-layout">

<!-- ── Sidebar ── -->
<aside class="app-sidebar" id="appSidebar">

  <!-- Brand -->
  <div class="sidebar-brand">
    <div class="brand-icon">
      <i class="fa-solid fa-bolt"></i>
    </div>
    <div class="brand-text">
      <div class="brand-name"><?= APP_NAME ?></div>
      <div class="brand-tagline">Gestion des tâches</div>
    </div>
  </div>

  <!-- User -->
  <div class="sidebar-user">
    <div class="user-avatar" style="background:<?= getUserColor($currentUser['id']) ?>">
      <?php if (!empty($currentUser['photo'])): ?>
        <img src="<?= APP_URL.'/uploads/photos/'.sanitize($currentUser['photo']) ?>" alt="">
      <?php else: ?>
        <?= $initials ?>
      <?php endif; ?>
    </div>
    <div class="user-info">
      <span class="user-name"><?= sanitize($currentUser['prenom'].' '.$currentUser['nom']) ?></span>
      <span class="user-role"><?= ROLES[$currentUser['role']] ?? $currentUser['role'] ?></span>
    </div>
  </div>

  <!-- Navigation -->
  <nav class="sidebar-nav">

    <div class="nav-section">Principal</div>

    <a href="<?= APP_URL ?>/index.php"
       class="nav-link <?= basename($path)==='index.php' ? 'active' : '' ?>">
      <span class="nav-icon"><i class="fa-solid fa-gauge-high"></i></span>
      <span class="nav-label">Tableau de bord</span>
    </a>

    <a href="<?= APP_URL ?>/pages/tasks/list.php"
       class="nav-link <?= strpos($path,'tasks/list')!==false ? 'active' : '' ?>">
      <span class="nav-icon"><i class="fa-solid fa-list-check"></i></span>
      <span class="nav-label">Mes tâches</span>
    </a>

    <a href="<?= APP_URL ?>/pages/tasks/kanban.php"
       class="nav-link <?= strpos($path,'tasks/kanban')!==false ? 'active' : '' ?>">
      <span class="nav-icon"><i class="fa-solid fa-table-columns"></i></span>
      <span class="nav-label">Kanban</span>
    </a>

    <a href="<?= APP_URL ?>/pages/messages/index.php"
       class="nav-link <?= strpos($path,'messages/')!==false ? 'active' : '' ?>">
      <span class="nav-icon"><i class="fa-solid fa-comments"></i></span>
      <span class="nav-label">Messages</span>
      <?php if (!empty($dmUnreadCount) && $dmUnreadCount > 0): ?>
      <span class="nav-badge" id="dmNavBadge"><?= (int) $dmUnreadCount ?></span>
      <?php else: ?>
      <span class="nav-badge" id="dmNavBadge" style="display:none">0</span>
      <?php endif; ?>
    </a>

    <?php if (isSuperviseur()): ?>
    <a href="<?= APP_URL ?>/pages/tasks/create.php"
       class="nav-link <?= strpos($path,'tasks/create')!==false ? 'active' : '' ?>">
      <span class="nav-icon"><i class="fa-solid fa-circle-plus"></i></span>
      <span class="nav-label">Nouvelle tâche</span>
    </a>
    <?php endif; ?>

    <?php if (isChefDept()): ?>
    <a href="<?= APP_URL ?>/pages/reports/index.php"
       class="nav-link <?= strpos($path,'reports')!==false ? 'active' : '' ?>">
      <span class="nav-icon"><i class="fa-solid fa-chart-line"></i></span>
      <span class="nav-label">Rapports</span>
    </a>
    <?php endif; ?>

    <?php if (isAdmin()): ?>
    <div class="nav-section" style="margin-top:0.5rem">Administration</div>

    <a href="<?= APP_URL ?>/pages/users/list.php"
       class="nav-link <?= strpos($path,'users/list')!==false ? 'active' : '' ?>">
      <span class="nav-icon"><i class="fa-solid fa-users-gear"></i></span>
      <span class="nav-label">Utilisateurs</span>
    </a>

    <a href="<?= APP_URL ?>/pages/users/create.php"
       class="nav-link <?= strpos($path,'users/create')!==false ? 'active' : '' ?>">
      <span class="nav-icon"><i class="fa-solid fa-user-plus"></i></span>
      <span class="nav-label">Nouvel utilisateur</span>
    </a>

    <a href="<?= APP_URL ?>/pages/admin/bases.php"
       class="nav-link <?= strpos($path,'admin/bases')!==false ? 'active' : '' ?>">
      <span class="nav-icon"><i class="fa-solid fa-location-dot"></i></span>
      <span class="nav-label">Bases géographiques</span>
    </a>

    <a href="<?= APP_URL ?>/pages/admin/departements.php"
       class="nav-link <?= strpos($path,'admin/departements')!==false ? 'active' : '' ?>">
      <span class="nav-icon"><i class="fa-solid fa-building"></i></span>
      <span class="nav-label">Départements</span>
    </a>

    <a href="<?= APP_URL ?>/pages/admin/droits.php"
       class="nav-link <?= strpos($path,'admin/droits')!==false ? 'active' : '' ?>">
      <span class="nav-icon"><i class="fa-solid fa-shield-halved"></i></span>
      <span class="nav-label">Droits d'accès</span>
    </a>

    <a href="<?= APP_URL ?>/pages/admin/roles.php"
       class="nav-link <?= strpos($path,'admin/roles')!==false ? 'active' : '' ?>">
      <span class="nav-icon"><i class="fa-solid fa-user-shield"></i></span>
      <span class="nav-label">Rôles</span>
    </a>

    <a href="<?= APP_URL ?>/pages/admin/audit.php"
       class="nav-link <?= strpos($path,'admin/audit')!==false ? 'active' : '' ?>">
      <span class="nav-icon"><i class="fa-solid fa-clipboard-list"></i></span>
      <span class="nav-label">Journal d’audit</span>
    </a>

    <a href="<?= APP_URL ?>/pages/admin/logs.php"
       class="nav-link <?= strpos($path,'admin/logs')!==false ? 'active' : '' ?>">
      <span class="nav-icon"><i class="fa-solid fa-bug"></i></span>
      <span class="nav-label">Journal technique</span>
    </a>
    <?php endif; ?>

  </nav>

  <!-- Footer -->
  <div class="sidebar-footer">
    <a href="<?= APP_URL ?>/pages/users/profile.php"
       class="nav-link <?= strpos($path,'users/profile')!==false ? 'active' : '' ?>"
       style="border-radius:var(--radius);margin-bottom:0">
      <span class="nav-icon"><i class="fa-solid fa-circle-user"></i></span>
      <span>Mon profil</span>
    </a>
    <a href="<?= APP_URL ?>/logout.php"
       class="nav-link danger"
       style="border-radius:var(--radius)">
      <span class="nav-icon"><i class="fa-solid fa-right-from-bracket"></i></span>
      <span>Déconnexion</span>
    </a>
  </div>

</aside>

<!-- ── Main area ── -->
<div class="app-main">

  <!-- Topbar -->
  <header class="app-topbar">

    <!-- Sidebar toggle -->
    <button type="button" class="topbar-toggle" onclick="Sidebar.toggle()" title="Réduire le menu" aria-label="Ouvrir ou réduire le menu latéral">
      <i class="fa-solid fa-bars-staggered" aria-hidden="true"></i>
    </button>

    <!-- Breadcrumb -->
    <nav class="breadcrumb">
      <?php if (!empty($breadcrumbs)): ?>
        <?php foreach ($breadcrumbs as $i => $bc): ?>
          <?php if ($i < count($breadcrumbs)-1): ?>
            <a href="<?= sanitize($bc['url']) ?>"><?= sanitize($bc['label']) ?></a>
            <span class="bc-sep"><i class="fa-solid fa-chevron-right" style="font-size:9px"></i></span>
          <?php else: ?>
            <span class="bc-current"><?= sanitize($bc['label']) ?></span>
          <?php endif; ?>
        <?php endforeach; ?>
      <?php else: ?>
        <span class="bc-current"><?= sanitize($pageTitle) ?></span>
      <?php endif; ?>
    </nav>

    <!-- Search -->
    <div class="topbar-search" style="max-width:300px">
      <i class="fa-solid fa-magnifying-glass search-icon"></i>
      <input type="search" id="globalSearch" placeholder="Rechercher... (Ctrl+K)" autocomplete="off" aria-label="Recherche globale de tâches">
      <div class="search-results" id="searchResults"></div>
    </div>

    <!-- Right actions -->
    <div class="topbar-actions">

      <!-- Notifications -->
      <div style="position:relative" id="notifWrap">
        <button type="button" class="topbar-btn" onclick="toggleNotifs()" title="Notifications" aria-expanded="false" aria-controls="notifPanel" aria-label="Notifications">
          <i class="fa-regular fa-bell" aria-hidden="true"></i>
          <?php if ($notifCount > 0): ?>
          <span class="notif-badge"><?= $notifCount ?></span>
          <?php endif; ?>
        </button>
        <div class="notif-panel" id="notifPanel">
          <div class="notif-header">
            Notifications
            <a href="#" onclick="markAllRead();return false">Tout lire</a>
          </div>
          <div id="notifList"><div style="padding:20px;text-align:center;color:var(--text3);font-size:13px">Chargement…</div></div>
        </div>
      </div>

      <!-- Dark mode -->
      <button type="button" class="topbar-btn" onclick="DarkMode.toggle()" title="Mode sombre/clair" id="darkBtn" aria-label="Basculer le mode clair ou sombre">
        <i class="fa-regular fa-moon" id="darkIcon" aria-hidden="true"></i>
      </button>

      <!-- User -->
      <a href="<?= APP_URL ?>/pages/users/profile.php" class="topbar-user">
        <div class="avatar" style="background:<?= getUserColor($currentUser['id']) ?>">
          <?php if (!empty($currentUser['photo'])): ?>
            <img src="<?= APP_URL.'/uploads/photos/'.sanitize($currentUser['photo']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%">
          <?php else: ?>
            <?= $initials ?>
          <?php endif; ?>
        </div>
        <span class="user-label"><?= sanitize($currentUser['prenom']) ?></span>
        <i class="fa-solid fa-chevron-down" style="font-size:9px;color:var(--text3)"></i>
      </a>

    </div>
  </header>

  <!-- Flash -->
  <?php if ($flash): ?>
  <div style="padding:0 2rem">
    <div class="flash flash-<?= sanitize($flash['type']) ?>" id="flashMsg">
      <span><?= sanitize($flash['message']) ?></span>
      <button onclick="document.getElementById('flashMsg').remove()"><i class="fa-solid fa-xmark"></i></button>
    </div>
  </div>
  <?php endif; ?>

  <!-- Content -->
  <main class="app-content">

<?php // toast container & mobile overlay ?>
<div id="toast-container" role="status" aria-live="polite"></div>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="Sidebar.closeMobile()"></div>
