<?php
require_once __DIR__ . '/../../config/config.php';
$pageTitle  = 'Vue Kanban';
$breadcrumbs = [
  ['label'=>'Accueil','url'=>APP_URL.'/index.php'],
  ['label'=>'Kanban','url'=>''],
];
require_once __DIR__ . '/../../includes/header.php';

$pdo = getDB();

// Construction requête selon rôle
if ($currentUser['role'] === 'employe') {
    $sql = "SELECT DISTINCT t.*, d.nom AS dept_nom,
        (SELECT GROUP_CONCAT(CONCAT(u2.prenom,' ',u2.nom) SEPARATOR ', ')
         FROM taches_assignees ta2 JOIN users u2 ON u2.id=ta2.user_id WHERE ta2.tache_id=t.id) AS assignes
        FROM taches t
        JOIN taches_assignees ta ON ta.tache_id=t.id
        LEFT JOIN departements d ON d.id=t.departement_id
        WHERE ta.user_id=? AND t.statut NOT IN ('annule')
        ORDER BY FIELD(t.priorite,'urgente','haute','normale','basse'), t.date_echeance ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$currentUser['id']]);
} elseif (in_array($currentUser['role'], ['superviseur','chef_dept'])) {
    $sql = "SELECT t.*,d.nom AS dept_nom,
        (SELECT GROUP_CONCAT(CONCAT(u2.prenom,' ',u2.nom) SEPARATOR ', ')
         FROM taches_assignees ta2 JOIN users u2 ON u2.id=ta2.user_id WHERE ta2.tache_id=t.id) AS assignes
        FROM taches t
        LEFT JOIN departements d ON d.id=t.departement_id
        WHERE t.departement_id=? AND t.statut NOT IN ('annule')
        ORDER BY FIELD(t.priorite,'urgente','haute','normale','basse'), t.date_echeance ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$currentUser['departement_id']]);
} else {
    $sql = "SELECT t.*,d.nom AS dept_nom,
        (SELECT GROUP_CONCAT(CONCAT(u2.prenom,' ',u2.nom) SEPARATOR ', ')
         FROM taches_assignees ta2 JOIN users u2 ON u2.id=ta2.user_id WHERE ta2.tache_id=t.id) AS assignes
        FROM taches t
        LEFT JOIN departements d ON d.id=t.departement_id
        WHERE t.statut NOT IN ('annule')
        ORDER BY FIELD(t.priorite,'urgente','haute','normale','basse'), t.date_echeance ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([]);
}
$allTasks = $stmt->fetchAll();

// Grouper par statut
$columns = [
    'pas_fait'   => ['label'=>'Pas encore fait', 'color'=>'#6B7280', 'tasks'=>[]],
    'en_cours'   => ['label'=>'En cours',         'color'=>'#2563EB', 'tasks'=>[]],
    'en_attente' => ['label'=>'En attente',        'color'=>'#D97706', 'tasks'=>[]],
    'termine'    => ['label'=>'Terminé',            'color'=>'#16A34A', 'tasks'=>[]],
    'rejete'     => ['label'=>'Rejeté',             'color'=>'#7C3AED', 'tasks'=>[]],
];

foreach ($allTasks as $task) {
    $realStatus = computeRealStatus($task);
    $key = $realStatus === 'en_retard' ? 'en_cours' : $realStatus;
    if (isset($columns[$key])) {
        $task['_real_status'] = $realStatus;
        $columns[$key]['tasks'][] = $task;
    }
}
?>

<meta name="csrf" content="<?= csrfToken() ?>">

<div class="page-header">
  <div>
    <div class="page-title">Vue Kanban</div>
    <div class="page-subtitle"><?= count($allTasks) ?> tâche<?= count($allTasks)>1?'s':'' ?></div>
  </div>
  <div style="display:flex;gap:8px">
    <a href="<?= APP_URL ?>/pages/tasks/list.php" class="btn btn-secondary"><i class="fa-solid fa-list-ul" style="margin-right:6px"></i>Liste</a>
    <?php if (isSuperviseur()): ?>
    <a href="<?= APP_URL ?>/pages/tasks/create.php" class="btn btn-primary"><i class="fa-solid fa-plus" style="margin-right:6px"></i>Nouvelle</a>
    <?php endif; ?>
  </div>
</div>

<!-- Filtre rapide -->
<div class="filter-bar" style="margin-bottom:16px">
  <input type="text" id="kanbanSearch" placeholder="🔍 Filtrer les cartes..." style="min-width:200px">
  <select id="kanbanPriority">
    <option value="">Toutes priorités</option>
    <option value="urgente">⚡ Urgente</option>
    <option value="haute">↑ Haute</option>
    <option value="normale">→ Normale</option>
    <option value="basse">↓ Basse</option>
  </select>
</div>

<div class="kanban-board">
  <?php foreach ($columns as $statusKey => $col): ?>
  <div class="kanban-col" data-status="<?= $statusKey ?>">
    <div class="kanban-col-header" style="color:<?= $col['color'] ?>">
      <span><?= $col['label'] ?></span>
      <span style="background:<?= $col['color'] ?>20;color:<?= $col['color'] ?>;font-size:12px;padding:2px 8px;border-radius:10px">
        <?= count($col['tasks']) ?>
      </span>
    </div>
    <div class="kanban-cards" id="col-<?= $statusKey ?>">
      <?php foreach ($col['tasks'] as $task): ?>
      <?php
        $rs = $task['_real_status'];
        $days = daysUntil($task['date_echeance']);
        $isOverdue = ($rs === 'en_retard');
      ?>
      <div class="kanban-card <?= $isOverdue ? 'retard' : '' ?>"
           data-id="<?= $task['id'] ?>"
           data-priority="<?= $task['priorite'] ?>"
           onclick="window.location='<?= APP_URL ?>/pages/tasks/view.php?id=<?= $task['id'] ?>'">

        <!-- Priorité indicateur -->
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
          <?= getPriorityBadge($task['priorite']) ?>
          <?php if ($isOverdue): ?>
          <span style="font-size:10px;font-weight:700;color:#DC2626">⚠ RETARD</span>
          <?php endif; ?>
        </div>

        <div class="kanban-card-title"><?= sanitize($task['titre']) ?></div>

        <?php if (!empty($task['dept_nom'])): ?>
        <div style="font-size:11px;color:var(--text3);margin-bottom:6px"><?= sanitize($task['dept_nom']) ?></div>
        <?php endif; ?>

        <!-- Progression -->
        <?php if ($task['pourcentage'] > 0): ?>
        <div class="progress-bar" style="margin-bottom:8px">
          <div class="progress-fill" style="width:<?= (int)$task['pourcentage'] ?>%"></div>
        </div>
        <?php endif; ?>

        <div class="kanban-card-meta">
          <div class="kanban-card-due <?= $days < 0 ? 'overdue' : '' ?>">
            📅 <?= formatDate($task['date_echeance']) ?>
            <?php if ($days === 0): ?> · <strong style="color:#D97706">Aujourd'hui</strong>
            <?php elseif ($days < 0): ?> · <strong><?= abs($days) ?>j dépassé</strong>
            <?php elseif ($days <= 3): ?> · <span style="color:#D97706">dans <?= $days ?>j</span>
            <?php endif; ?>
          </div>
          <?php if (!empty($task['assignes'])): ?>
          <div class="kanban-card-assignee" style="font-size:11px;color:var(--text3)">
            👤 <?= sanitize(mb_substr($task['assignes'],0,25)) ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>

      <?php if (empty($col['tasks'])): ?>
      <div style="text-align:center;color:var(--text3);font-size:12px;padding:20px;font-style:italic">
        Aucune tâche
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<script>
// Filtre cartes
document.getElementById('kanbanSearch').addEventListener('input', filterCards);
document.getElementById('kanbanPriority').addEventListener('change', filterCards);

function filterCards() {
  const q = document.getElementById('kanbanSearch').value.toLowerCase();
  const p = document.getElementById('kanbanPriority').value;
  document.querySelectorAll('.kanban-card').forEach(card => {
    const title = card.querySelector('.kanban-card-title')?.textContent.toLowerCase() || '';
    const prio  = card.dataset.priority || '';
    const show  = title.includes(q) && (p === '' || prio === p);
    card.style.display = show ? '' : 'none';
  });
  // Mise à jour compteurs
  document.querySelectorAll('.kanban-col').forEach(col => {
    const visible = col.querySelectorAll('.kanban-card:not([style*="none"])').length;
    const badge = col.querySelector('.kanban-col-header span:last-child');
    if (badge) badge.textContent = visible;
  });
}

// Drag & drop activé via SortableJS dans app.js
document.addEventListener('DOMContentLoaded', () => {
  if (typeof Sortable !== 'undefined') initKanban();
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
