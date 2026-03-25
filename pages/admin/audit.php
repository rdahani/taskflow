<?php
require_once __DIR__ . '/../../includes/layout_init.php';

if (!isAdmin()) {
    flashMessage('error', 'Accès réservé aux administrateurs.');
    redirect('/index.php');
}

$pageTitle   = 'Journal d\'audit';
$breadcrumbs = [
    ['label' => 'Accueil', 'url' => APP_URL . '/index.php'],
    ['label' => 'Audit', 'url' => ''],
];

$pdo       = getDB();
$page      = max(1, (int) ($_GET['page'] ?? 1));
$auditOk   = true;
$total     = 0;
$auditError = '';

try {
    $total = (int) $pdo->query('SELECT COUNT(*) FROM audit_log')->fetchColumn();
} catch (Throwable $e) {
    $auditOk    = false;
    $auditError = $e->getMessage();
}

$perPage = 40;
$pag     = paginate($total, $page, $perPage);
$rows    = [];

if ($auditOk && $total > 0) {
    $stmt = $pdo->prepare(
        'SELECT a.*, u.prenom, u.nom
         FROM audit_log a
         JOIN users u ON u.id = a.user_id
         ORDER BY a.created_at DESC
         LIMIT :lim OFFSET :off'
    );
    $stmt->bindValue(':lim', $pag['per_page'], PDO::PARAM_INT);
    $stmt->bindValue(':off', $pag['offset'], PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div>
    <div class="page-title">Journal d’audit</div>
    <div class="page-subtitle">Actions sensibles (utilisateurs, suppressions de tâches, etc.)</div>
  </div>
</div>

<?php if (!$auditOk): ?>
<div class="card">
  <div class="card-body flash flash-error" style="padding:1.25rem">
    La table <code>audit_log</code> est introuvable. Importez
    <code>config/migrations/002_audit_reminders_indexes.sql</code>.
    <?php if (APP_DEBUG): ?>
      <pre style="margin-top:8px;font-size:11px;overflow:auto"><?= sanitize($auditError) ?></pre>
    <?php endif; ?>
  </div>
</div>
<?php elseif ($total === 0): ?>
<div class="card">
  <div class="card-body" style="color:var(--text3);padding:2rem;text-align:center">
    Aucune entrée pour le moment.
  </div>
</div>
<?php else: ?>
<div class="card">
  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>Date</th>
          <th>Utilisateur</th>
          <th>Action</th>
          <th>Entité</th>
          <th>Détails</th>
          <th>IP</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
          <td style="font-size:12px;white-space:nowrap"><?= sanitize(formatDateTime($r['created_at'])) ?></td>
          <td style="font-size:13px"><?= sanitize($r['prenom'] . ' ' . $r['nom']) ?></td>
          <td><code style="font-size:12px"><?= sanitize($r['action']) ?></code></td>
          <td style="font-size:12px"><?= sanitize($r['entity_type']) ?><?= $r['entity_id'] ? ' #' . (int) $r['entity_id'] : '' ?></td>
          <td style="font-size:12px;max-width:280px;word-break:break-word"><?= sanitize($r['details'] ?? '') ?></td>
          <td style="font-size:11px;color:var(--text3)"><?= sanitize($r['ip_address'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?= renderPagination($pag, '?page=') ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
