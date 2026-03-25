<?php
require_once __DIR__ . '/../../includes/layout_init.php';

$pageTitle  = 'Rapports & Statistiques';
$breadcrumbs = [
  ['label'=>'Accueil','url'=>APP_URL.'/index.php'],
  ['label'=>'Rapports','url'=>''],
];

if (!isChefDept()) { flashMessage('error','Accès réservé aux chefs de département.'); redirect('/index.php'); }

$pdo = getDB();

// Période filtre
$periode = $_GET['periode'] ?? '30';
$dateFrom = date('Y-m-d', strtotime("-$periode days"));

// Stats globales
$deptId = (int)($currentUser['departement_id'] ?? 0);

if (isCheffe()) {
    $globalStmt = $pdo->prepare("SELECT
        COUNT(*) AS total,
        SUM(statut='termine') AS termine,
        SUM(statut='en_cours') AS en_cours,
        SUM(statut='annule') AS annule,
        SUM(statut='rejete') AS rejete,
        SUM(statut='en_attente') AS en_attente,
        SUM(statut='pas_fait') AS pas_fait,
        SUM(date_echeance < CURDATE() AND statut NOT IN ('termine','annule','rejete')) AS en_retard,
        ROUND(AVG(pourcentage)) AS avg_progress,
        SUM(date_creation >= ?) AS recent
        FROM taches t");
    $globalStmt->execute([$dateFrom]);
} else {
    $globalStmt = $pdo->prepare("SELECT
        COUNT(*) AS total,
        SUM(statut='termine') AS termine,
        SUM(statut='en_cours') AS en_cours,
        SUM(statut='annule') AS annule,
        SUM(statut='rejete') AS rejete,
        SUM(statut='en_attente') AS en_attente,
        SUM(statut='pas_fait') AS pas_fait,
        SUM(date_echeance < CURDATE() AND statut NOT IN ('termine','annule','rejete')) AS en_retard,
        ROUND(AVG(pourcentage)) AS avg_progress,
        SUM(date_creation >= ?) AS recent
        FROM taches t WHERE t.departement_id = ?");
    $globalStmt->execute([$dateFrom, $deptId]);
}
$globalStats = $globalStmt->fetch();

// Par département
if (isCheffe()) {
    $deptStmt = $pdo->prepare("SELECT d.nom,
        COUNT(t.id) AS total,
        SUM(t.statut='termine') AS termine,
        SUM(t.statut='en_cours') AS en_cours,
        SUM(t.date_echeance < CURDATE() AND t.statut NOT IN ('termine','annule','rejete')) AS retard,
        ROUND(AVG(t.pourcentage)) AS avg_progress
        FROM departements d
        LEFT JOIN taches t ON t.departement_id=d.id
        WHERE d.actif=1
        GROUP BY d.id ORDER BY total DESC");
    $deptStmt->execute([]);
} else {
    $deptStmt = $pdo->prepare("SELECT d.nom,
        COUNT(t.id) AS total,
        SUM(t.statut='termine') AS termine,
        SUM(t.statut='en_cours') AS en_cours,
        SUM(t.date_echeance < CURDATE() AND t.statut NOT IN ('termine','annule','rejete')) AS retard,
        ROUND(AVG(t.pourcentage)) AS avg_progress
        FROM departements d
        LEFT JOIN taches t ON t.departement_id=d.id
        WHERE d.actif=1 AND d.id=?
        GROUP BY d.id ORDER BY total DESC");
    $deptStmt->execute([$deptId]);
}
$deptStats = $deptStmt->fetchAll();

// Par base
$baseStats = [];
if (isCheffe()) {
    $baseStats = $pdo->query("SELECT b.nom,
        COUNT(t.id) AS total,
        SUM(t.statut='termine') AS termine,
        SUM(t.date_echeance < CURDATE() AND t.statut NOT IN ('termine','annule','rejete')) AS retard
        FROM bases b
        LEFT JOIN taches t ON t.base_id=b.id
        WHERE b.actif=1 GROUP BY b.id ORDER BY total DESC")->fetchAll();
}

// Top employés par charge
if (isCheffe()) {
    $topStmt = $pdo->prepare("SELECT u.prenom,u.nom,u.id,
        COUNT(ta.tache_id) AS total,
        SUM(t.statut='termine') AS termine,
        SUM(t.date_echeance < CURDATE() AND t.statut NOT IN ('termine','annule','rejete')) AS retard
        FROM users u
        JOIN taches_assignees ta ON ta.user_id=u.id
        JOIN taches t ON t.id=ta.tache_id
        WHERE u.actif=1
        GROUP BY u.id ORDER BY total DESC LIMIT 10");
    $topStmt->execute([]);
} else {
    $topStmt = $pdo->prepare("SELECT u.prenom,u.nom,u.id,
        COUNT(ta.tache_id) AS total,
        SUM(t.statut='termine') AS termine,
        SUM(t.date_echeance < CURDATE() AND t.statut NOT IN ('termine','annule','rejete')) AS retard
        FROM users u
        JOIN taches_assignees ta ON ta.user_id=u.id
        JOIN taches t ON t.id=ta.tache_id
        WHERE u.actif=1 AND t.departement_id=?
        GROUP BY u.id ORDER BY total DESC LIMIT 10");
    $topStmt->execute([$deptId]);
}
$topUsers = $topStmt->fetchAll();

// Évolution mensuelle
if (isCheffe()) {
    $monthlyStmt = $pdo->prepare("SELECT
        DATE_FORMAT(date_creation,'%Y-%m') AS mois,
        COUNT(*) AS cree,
        SUM(statut='termine') AS termine
        FROM taches t
        WHERE date_creation >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(date_creation,'%Y-%m')
        ORDER BY mois");
    $monthlyStmt->execute([]);
} else {
    $monthlyStmt = $pdo->prepare("SELECT
        DATE_FORMAT(date_creation,'%Y-%m') AS mois,
        COUNT(*) AS cree,
        SUM(statut='termine') AS termine
        FROM taches t
        WHERE t.departement_id=?
        AND date_creation >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(date_creation,'%Y-%m')
        ORDER BY mois");
    $monthlyStmt->execute([$deptId]);
}
$monthlyData = $monthlyStmt->fetchAll();

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="rapport_taskflow_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['TaskFlow — export rapports', 'Période (jours)', $periode, 'Depuis', $dateFrom], ';');
    fputcsv($out, [], ';');
    fputcsv($out, ['Vue', 'Indicateur', 'Valeur'], ';');
    fputcsv($out, ['Global', 'Total tâches', (string) ($globalStats['total'] ?? 0)], ';');
    fputcsv($out, ['Global', 'Terminées', (string) ($globalStats['termine'] ?? 0)], ';');
    fputcsv($out, ['Global', 'En cours', (string) ($globalStats['en_cours'] ?? 0)], ';');
    fputcsv($out, ['Global', 'En retard', (string) ($globalStats['en_retard'] ?? 0)], ';');
    fputcsv($out, ['Global', 'Progression moy. %', (string) ($globalStats['avg_progress'] ?? 0)], ';');
    fputcsv($out, ['Global', 'Créées sur la période', (string) ($globalStats['recent'] ?? 0)], ';');
    fputcsv($out, [], ';');
    fputcsv($out, ['Département', 'Total', 'Terminées', 'En cours', 'En retard', 'Progression moy.%'], ';');
    foreach ($deptStats as $d) {
        fputcsv($out, [
            $d['nom'],
            (string) $d['total'],
            (string) $d['termine'],
            (string) $d['en_cours'],
            (string) $d['retard'],
            (string) round((float) ($d['avg_progress'] ?? 0)),
        ], ';');
    }
    fputcsv($out, [], ';');
    fputcsv($out, ['Employé', 'Total assignées', 'Terminées', 'En retard'], ';');
    foreach ($topUsers as $u) {
        fputcsv($out, [
            $u['prenom'] . ' ' . $u['nom'],
            (string) $u['total'],
            (string) $u['termine'],
            (string) $u['retard'],
        ], ';');
    }
    if (!empty($baseStats)) {
        fputcsv($out, [], ';');
        fputcsv($out, ['Base', 'Total', 'Terminées', 'En retard'], ';');
        foreach ($baseStats as $b) {
            fputcsv($out, [$b['nom'], (string) $b['total'], (string) $b['termine'], (string) $b['retard']], ';');
        }
    }
    fclose($out);
    exit;
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div>
    <div class="page-title">Rapports & Statistiques</div>
    <div class="page-subtitle">Vue d'ensemble des performances</div>
  </div>
  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <a href="?export=csv&amp;periode=<?= urlencode((string) $periode) ?>" class="btn btn-secondary btn-sm" download>Télécharger CSV</a>
    <select id="periodeSelect" class="form-control" style="width:auto" onchange="window.location='?periode='+this.value" aria-label="Période des statistiques">
      <?php foreach (['7'=>'7 derniers jours','30'=>'30 derniers jours','90'=>'3 derniers mois','365'=>'1 an'] as $v=>$l): ?>
        <option value="<?= $v ?>" <?= $periode==$v?'selected':'' ?>><?= $l ?></option>
      <?php endforeach; ?>
    </select>
  </div>
</div>

<!-- Stats globales -->
<div class="stats-grid" style="margin-bottom:24px">
  <div class="stat-card" style="border-left:3px solid #2563EB">
    <div class="stat-icon stat-icon--info" aria-hidden="true"><i class="fa-solid fa-clipboard-list"></i></div>
    <div><div class="stat-value" style="color:#2563EB"><?= $globalStats['total'] ?></div><div class="stat-label">Total tâches</div></div>
  </div>
  <div class="stat-card" style="border-left:3px solid #16A34A">
    <div class="stat-icon stat-icon--success" aria-hidden="true"><i class="fa-solid fa-circle-check"></i></div>
    <div><div class="stat-value" style="color:#16A34A"><?= $globalStats['termine'] ?></div><div class="stat-label">Terminées</div></div>
  </div>
  <div class="stat-card" style="border-left:3px solid #DC2626">
    <div class="stat-icon stat-icon--danger" aria-hidden="true"><i class="fa-solid fa-triangle-exclamation"></i></div>
    <div><div class="stat-value" style="color:#DC2626"><?= $globalStats['en_retard'] ?></div><div class="stat-label">En retard</div></div>
  </div>
  <div class="stat-card" style="border-left:3px solid #D97706">
    <div class="stat-icon stat-icon--warning" aria-hidden="true"><i class="fa-solid fa-chart-line"></i></div>
    <div><div class="stat-value" style="color:#D97706"><?= $globalStats['avg_progress'] ?? 0 ?>%</div><div class="stat-label">Progression moy.</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon stat-icon--primary" aria-hidden="true"><i class="fa-solid fa-calendar-plus"></i></div>
    <div><div class="stat-value"><?= $globalStats['recent'] ?></div><div class="stat-label">Créées (<?= $periode ?>j)</div></div>
  </div>
</div>

<!-- Graphiques -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;margin-bottom:24px">
  <div class="card">
    <div class="card-header"><span class="card-title">Répartition par statut</span></div>
    <div class="card-body">
      <div class="chart-wrap"><canvas id="donutChart"></canvas></div>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><span class="card-title">Évolution mensuelle</span></div>
    <div class="card-body">
      <div class="chart-wrap"><canvas id="lineChart"></canvas></div>
    </div>
  </div>
</div>

<!-- Par département -->
<div class="card" style="margin-bottom:20px">
  <div class="card-header"><span class="card-title">Performance par département</span></div>
  <div class="card-body">
    <div class="chart-wrap chart-wrap--dept"><canvas id="deptBar"></canvas></div>
  </div>
</div>

<!-- Top utilisateurs -->
<div style="display:grid;grid-template-columns:<?= !empty($baseStats)?'1fr 1fr':'1fr' ?>;gap:20px">
  <div class="card">
    <div class="card-header"><span class="card-title">Charge par employé</span></div>
    <div class="table-wrapper">
      <table>
        <thead><tr><th>Employé</th><th>Total</th><th>Terminées</th><th>En retard</th><th>Taux</th></tr></thead>
        <tbody>
          <?php foreach ($topUsers as $u): ?>
          <?php $rate = $u['total']>0?round($u['termine']/$u['total']*100):0; ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:8px">
                <div class="user-avatar" style="width:28px;height:28px;font-size:11px;background:<?= getUserColor($u['id']) ?>">
                  <?= strtoupper(substr($u['prenom'],0,1).substr($u['nom'],0,1)) ?>
                </div>
                <a href="<?= APP_URL ?>/pages/users/profile.php?id=<?= $u['id'] ?>" style="color:var(--text);font-size:13px">
                  <?= sanitize($u['prenom'].' '.$u['nom']) ?>
                </a>
              </div>
            </td>
            <td style="font-weight:600"><?= $u['total'] ?></td>
            <td style="color:#16A34A;font-weight:600"><?= $u['termine'] ?></td>
            <td style="color:<?= $u['retard']>0?'#DC2626':'var(--text3)' ?>;font-weight:<?= $u['retard']>0?'600':'400' ?>"><?= $u['retard'] ?></td>
            <td>
              <div style="display:flex;align-items:center;gap:8px">
                <div class="progress-bar" style="width:60px"><div class="progress-fill" style="width:<?= $rate ?>%;background:<?= $rate>=80?'#16A34A':($rate>=50?'#D97706':'#DC2626') ?>"></div></div>
                <span style="font-size:12px"><?= $rate ?>%</span>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if (!empty($baseStats)): ?>
  <div class="card">
    <div class="card-header"><span class="card-title">Par base géographique</span></div>
    <div class="table-wrapper">
      <table>
        <thead><tr><th>Base</th><th>Total</th><th>Terminées</th><th>En retard</th></tr></thead>
        <tbody>
          <?php foreach ($baseStats as $b): ?>
          <tr>
            <td style="font-weight:500"><?= sanitize($b['nom']) ?></td>
            <td><?= $b['total'] ?></td>
            <td style="color:#16A34A"><?= $b['termine'] ?></td>
            <td style="color:<?= $b['retard']>0?'#DC2626':'var(--text3)' ?>"><?= $b['retard'] ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
</div>

<script>
// Camembert statuts
new Chart(document.getElementById('donutChart').getContext('2d'), {
  type: 'doughnut',
  data: {
    labels: ['Pas fait','En cours','En attente','Terminé','Annulé','En retard','Rejeté'],
    datasets: [{
      data: [<?= implode(',', [$globalStats['pas_fait']??0,$globalStats['en_cours']??0,$globalStats['en_attente']??0,$globalStats['termine']??0,$globalStats['annule']??0,$globalStats['en_retard']??0,$globalStats['rejete']??0]) ?>],
      backgroundColor: ['#6B7280','#2563EB','#D97706','#16A34A','#991B1B','#DC2626','#7C3AED'],
      borderWidth: 2, borderColor: 'var(--surface)'
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    cutout:'60%',
    plugins:{ legend:{ position:'right', labels:{ boxWidth:10, padding:8, font:{size:10} } } }
  }
});

// Évolution
const months = [<?= implode(',', array_map(fn($m)=>'"'.substr($m['mois'],0,7).'"', $monthlyData)) ?>];
new Chart(document.getElementById('lineChart').getContext('2d'), {
  type: 'line',
  data: {
    labels: months,
    datasets: [
      { label:'Créées', data:[<?= implode(',', array_column($monthlyData,'cree')) ?>], borderColor:'#2563EB', backgroundColor:'#2563EB20', fill:true, tension:.3 },
      { label:'Terminées', data:[<?= implode(',', array_column($monthlyData,'termine')) ?>], borderColor:'#16A34A', backgroundColor:'#16A34A20', fill:true, tension:.3 }
    ]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    scales:{ y:{beginAtZero:true,ticks:{font:{size:10},maxTicksLimit:6}}, x:{ticks:{font:{size:10},maxRotation:45}} },
    plugins:{ legend:{ labels:{ boxWidth:10, font:{size:10} } } }
  }
});

// Barres département
const depts = [<?= implode(',', array_map(fn($d)=>'"'.sanitize($d['nom']).'"', $deptStats)) ?>];
new Chart(document.getElementById('deptBar').getContext('2d'), {
  type: 'bar',
  data: {
    labels: depts,
    datasets: [
      { label:'Total',    data:[<?= implode(',', array_column($deptStats,'total')) ?>],    backgroundColor:'#EFF6FF', borderColor:'#2563EB', borderWidth:1 },
      { label:'Terminées',data:[<?= implode(',', array_column($deptStats,'termine')) ?>],  backgroundColor:'#16A34A', borderRadius:3 },
      { label:'En cours', data:[<?= implode(',', array_column($deptStats,'en_cours')) ?>], backgroundColor:'#2563EB', borderRadius:3 },
      { label:'En retard',data:[<?= implode(',', array_column($deptStats,'retard')) ?>],   backgroundColor:'#DC2626', borderRadius:3 },
    ]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    scales:{ y:{beginAtZero:true, ticks:{font:{size:10},maxTicksLimit:6} }, x:{ticks:{font:{size:10},maxRotation:45}} },
    plugins:{ legend:{ labels:{ boxWidth:10, font:{size:10} } } }
  }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
