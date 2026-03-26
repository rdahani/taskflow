<?php
$pageTitle  = 'Tableau de bord';
$breadcrumbs = [['label' => 'Tableau de bord', 'url' => '']];
require_once __DIR__ . '/includes/header.php';

$stats = getDashboardStats($currentUser);
$pdo   = getDB();

// Tâches récentes visibles par l'utilisateur
if ($currentUser['role'] === 'employe') {
    $recentSql = "SELECT t.*, u.prenom, u.nom, b.nom AS base_nom, d.nom AS dept_nom
        FROM taches t
        JOIN taches_assignees ta ON ta.tache_id = t.id
        LEFT JOIN users u ON u.id = t.createur_id
        LEFT JOIN bases b ON b.id = t.base_id
        LEFT JOIN departements d ON d.id = t.departement_id
        WHERE ta.user_id = ?
        ORDER BY t.date_echeance ASC LIMIT 8";
    $recentStmt = $pdo->prepare($recentSql);
    $recentStmt->execute([$currentUser['id']]);
} elseif (in_array($currentUser['role'], ['superviseur','chef_dept'])) {
    if ($currentUser['departement_id']) {
        $recentSql = "SELECT t.*, u.prenom, u.nom, b.nom AS base_nom, d.nom AS dept_nom
            FROM taches t
            LEFT JOIN users u ON u.id = t.createur_id
            LEFT JOIN bases b ON b.id = t.base_id
            LEFT JOIN departements d ON d.id = t.departement_id
            WHERE t.departement_id = ?
            ORDER BY t.date_echeance ASC LIMIT 8";
        $recentStmt = $pdo->prepare($recentSql);
        $recentStmt->execute([$currentUser['departement_id']]);
    } else {
        $recentStmt = $pdo->prepare("SELECT t.*, u.prenom, u.nom, NULL AS base_nom, NULL AS dept_nom FROM taches t LEFT JOIN users u ON u.id=t.createur_id WHERE 1=0");
        $recentStmt->execute([]);
    }
} else {
    $recentSql = "SELECT t.*, u.prenom, u.nom, b.nom AS base_nom, d.nom AS dept_nom
        FROM taches t
        LEFT JOIN users u ON u.id = t.createur_id
        LEFT JOIN bases b ON b.id = t.base_id
        LEFT JOIN departements d ON d.id = t.departement_id
        ORDER BY t.date_echeance ASC LIMIT 8";
    $recentStmt = $pdo->prepare($recentSql);
    $recentStmt->execute([]);
}
$recentTasks = $recentStmt->fetchAll();

// Stats par département (admin/cheffe)
$deptStats = [];
if (isCheffe()) {
    $deptStmt = $pdo->query("SELECT d.nom, COUNT(t.id) AS total,
        SUM(t.statut='termine') AS termine,
        SUM(t.statut='en_cours') AS en_cours,
        SUM(t.date_echeance < NOW() AND t.statut NOT IN ('termine','annule')) AS retard
        FROM departements d LEFT JOIN taches t ON t.departement_id = d.id
        GROUP BY d.id ORDER BY total DESC LIMIT 6");
    $deptStats = $deptStmt->fetchAll();
}
?>

<div class="page-header">
  <div>
    <div class="page-title">Tableau de bord</div>
    <div class="page-subtitle">Bonjour <?= sanitize($currentUser['prenom']) ?> — <?= date('l d F Y') ?></div>
  </div>
  <?php if (isSuperviseur()): ?>
  <a href="<?= APP_URL ?>/pages/tasks/create.php" class="btn btn-primary"><i class="fa-solid fa-plus" style="margin-right:6px"></i>Nouvelle tâche</a>
  <?php endif; ?>
</div>

<!-- Statistiques -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon stat-icon--primary" aria-hidden="true"><i class="fa-solid fa-clipboard-list"></i></div>
    <div>
      <div class="stat-value"><?= $stats['total'] ?></div>
      <div class="stat-label">Total tâches</div>
    </div>
  </div>
  <div class="stat-card" style="border-left:3px solid #2563EB">
    <div class="stat-icon stat-icon--info" aria-hidden="true"><i class="fa-solid fa-arrows-rotate"></i></div>
    <div>
      <div class="stat-value" style="color:#2563EB"><?= $stats['en_cours'] ?? 0 ?></div>
      <div class="stat-label">En cours</div>
    </div>
  </div>
  <div class="stat-card" style="border-left:3px solid #16A34A">
    <div class="stat-icon stat-icon--success" aria-hidden="true"><i class="fa-solid fa-circle-check"></i></div>
    <div>
      <div class="stat-value" style="color:#16A34A"><?= $stats['termine'] ?? 0 ?></div>
      <div class="stat-label">Terminées</div>
    </div>
  </div>
  <div class="stat-card" style="border-left:3px solid #DC2626">
    <div class="stat-icon stat-icon--danger" aria-hidden="true"><i class="fa-solid fa-triangle-exclamation"></i></div>
    <div>
      <div class="stat-value" style="color:#DC2626"><?= $stats['en_retard'] ?? 0 ?></div>
      <div class="stat-label">En retard</div>
    </div>
  </div>
  <div class="stat-card" style="border-left:3px solid #D97706">
    <div class="stat-icon stat-icon--warning" aria-hidden="true"><i class="fa-solid fa-hourglass-half"></i></div>
    <div>
      <div class="stat-value" style="color:#D97706"><?= $stats['en_attente'] ?? 0 ?></div>
      <div class="stat-label">En attente</div>
    </div>
  </div>
</div>

<!-- Graphiques + tableau -->
<div style="display:grid;grid-template-columns:320px 1fr;gap:20px;margin-bottom:24px">

  <!-- Camembert statuts -->
  <div class="card">
    <div class="card-header"><span class="card-title">Répartition statuts</span></div>
    <div class="card-body" style="display:flex;justify-content:center">
      <div class="chart-wrap chart-wrap--dashboard-donut"><canvas id="statusChart"></canvas></div>
    </div>
  </div>

  <!-- Tâches urgentes -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">Tâches à traiter en priorité</span>
      <a href="<?= APP_URL ?>/pages/tasks/list.php" class="btn btn-secondary btn-sm">Voir tout</a>
    </div>
    <div class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th>Titre</th>
            <th>Statut</th>
            <th>Priorité</th>
            <th>Échéance</th>
            <th>Assigné à</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentTasks as $task): ?>
          <?php $realStatus = computeRealStatus($task); ?>
          <tr class="<?= getTaskRowClass($task) ?>">
            <td>
              <a href="<?= APP_URL ?>/pages/tasks/view.php?id=<?= $task['id'] ?>" style="color:var(--text);text-decoration:none;font-weight:500">
                <?= sanitize($task['titre']) ?>
              </a>
              <?php if (!empty($task['dept_nom'])): ?>
                <div style="font-size:11px;color:var(--text3)"><?= sanitize($task['dept_nom']) ?></div>
              <?php endif; ?>
            </td>
            <td><?= getTaskStatusBadge($realStatus) ?></td>
            <td><?= getPriorityBadge($task['priorite']) ?></td>
            <td>
              <?php $days = daysUntil($task['date_echeance']); ?>
              <span style="color:<?= $days < 0 ? '#DC2626' : ($days <= 3 ? '#D97706' : 'var(--text)') ?>;font-weight:<?= $days <= 3 ? '600' : '400' ?>">
                <?= formatDate($task['date_echeance']) ?>
                <?php if ($days < 0): ?><br><small style="color:#DC2626">Dépassé de <?= abs($days) ?>j</small>
                <?php elseif ($days === 0): ?><br><small style="color:#D97706">Aujourd'hui !</small>
                <?php elseif ($days <= 3): ?><br><small style="color:#D97706">Dans <?= $days ?>j</small>
                <?php endif; ?>
              </span>
            </td>
            <td style="font-size:12px;color:var(--text2)"><?= sanitize($task['prenom'].' '.$task['nom']) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($recentTasks)): ?>
          <tr><td colspan="5" style="text-align:center;color:var(--text3);padding:30px">Aucune tâche pour le moment</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Stats par département (admin) -->
<?php if (!empty($deptStats)): ?>
<div class="card">
  <div class="card-header"><span class="card-title">Activité par département</span></div>
  <div class="card-body">
    <div class="chart-wrap chart-wrap--dept"><canvas id="deptChart"></canvas></div>
  </div>
</div>
<?php endif; ?>

<script>
// Graphique statuts
const statusCtx = document.getElementById('statusChart').getContext('2d');
new Chart(statusCtx, {
  type: 'doughnut',
  data: {
    labels: ['Pas encore fait','En cours','En attente','Terminé','Annulé','En retard','Rejeté'],
    datasets: [{
      data: [
        <?= $stats['pas_fait'] ?? 0 ?>,
        <?= $stats['en_cours'] ?? 0 ?>,
        <?= $stats['en_attente'] ?? 0 ?>,
        <?= $stats['termine'] ?? 0 ?>,
        <?= $stats['annule'] ?? 0 ?>,
        <?= $stats['en_retard'] ?? 0 ?>,
        <?= $stats['rejete'] ?? 0 ?>
      ],
      backgroundColor: ['#6B7280','#2563EB','#D97706','#16A34A','#991B1B','#DC2626','#7C3AED'],
      borderWidth: 2,
      borderColor: 'var(--surface)',
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '65%',
    plugins: {
      legend: { position: 'bottom', labels: { boxWidth: 10, padding: 6, font: { size: 10 } } }
    }
  }
});

<?php if (!empty($deptStats)): ?>
const deptCtx = document.getElementById('deptChart').getContext('2d');
new Chart(deptCtx, {
  type: 'bar',
  data: {
    labels: [<?= implode(',', array_map(fn($d) => '"'.sanitize($d['nom']).'"', $deptStats)) ?>],
    datasets: [
      { label: 'Total', data: [<?= implode(',', array_column($deptStats,'total')) ?>], backgroundColor: '#EFF6FF', borderColor: '#2563EB', borderWidth: 1.5 },
      { label: 'Terminées', data: [<?= implode(',', array_column($deptStats,'termine')) ?>], backgroundColor: '#16A34A', borderRadius: 4 },
      { label: 'En cours', data: [<?= implode(',', array_column($deptStats,'en_cours')) ?>], backgroundColor: '#2563EB', borderRadius: 4 },
      { label: 'En retard', data: [<?= implode(',', array_column($deptStats,'retard')) ?>], backgroundColor: '#DC2626', borderRadius: 4 },
    ]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { position: 'top', labels: { boxWidth: 10, font: { size: 10 } } } },
    scales: {
      y: { beginAtZero: true, ticks: { font: { size: 10 }, maxTicksLimit: 6 } },
      x: { ticks: { font: { size: 10 }, maxRotation: 45 } }
    }
  }
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
