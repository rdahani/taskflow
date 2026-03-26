<?php
/**
 * Consultation des journaux applicatifs (admin).
 */
require_once __DIR__ . '/../../includes/layout_init.php';

if (!isAdmin()) {
    flashMessage('error', 'Accès réservé aux administrateurs.');
    redirect('/index.php');
}

$pageTitle   = 'Journal technique';
$breadcrumbs = [
    ['label' => 'Accueil', 'url' => APP_URL . '/index.php'],
    ['label' => 'Journal technique', 'url' => ''],
];

$logDir = TF_LOG_DIR;
$files  = [];
if (is_dir($logDir)) {
    foreach (glob($logDir . '/taskflow-*.log') ?: [] as $path) {
        $base = basename($path);
        if (preg_match('/^taskflow-\d{4}-\d{2}-\d{2}\.log$/', $base)) {
            $files[] = $base;
        }
    }
    rsort($files);
}

$selected = basename((string) ($_GET['f'] ?? ''));
if ($selected === '.' || $selected === '') {
    $selected = $files[0] ?? '';
}
if ($selected !== '' && !preg_match('/^taskflow-\d{4}-\d{2}-\d{2}\.log$/', $selected)) {
    $selected = '';
}

$content = '';
$bytes   = 0;
if ($selected !== '' && is_file($logDir . '/' . $selected)) {
    $full = $logDir . '/' . $selected;
    $bytes = (int) filesize($full);
    if ($bytes > 2 * 1024 * 1024) {
        $content = '(Fichier trop volumineux — plus de 2 Mo — ouvrez-le par FTP ou en SSH.)';
    } else {
        $lines = file($full, FILE_IGNORE_NEW_LINES);
        if (is_array($lines)) {
            $tail  = array_slice($lines, -400);
            $content = implode("\n", $tail);
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div>
    <div class="page-title">Journal technique</div>
    <div class="page-subtitle">Erreurs PHP, avertissements et exceptions enregistrés localement</div>
  </div>
  <a href="<?= APP_URL ?>/pages/admin/audit.php" class="btn btn-secondary" style="text-decoration:none">Journal d’audit</a>
</div>

<div class="card" style="margin-bottom:16px">
  <div class="card-body" style="font-size:13px;color:var(--text2)">
    Fichiers dans <code>storage/logs/</code> (un fichier par jour). En production, limitez l’accès au dossier. Optionnel dans
    <code>config.local.php</code> : <code>LOG_ENABLED</code>, <code>LOG_LEVEL</code> (<code>debug</code>, <code>info</code>, <code>warning</code>, <code>error</code>).
  </div>
</div>

<?php if (empty($files)): ?>
<div class="card">
  <div class="card-body" style="padding:2rem;color:var(--text3);text-align:center">
    Aucun fichier de log pour le moment. Les entrées apparaissent lors d’erreurs ou d’avertissements PHP.
  </div>
</div>
<?php else: ?>
<div class="card" style="margin-bottom:16px">
  <div class="card-body" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
    <label class="form-label" style="margin:0">Fichier</label>
    <select class="form-control" style="max-width:260px" onchange="location='?f='+encodeURIComponent(this.value)">
      <?php foreach ($files as $f): ?>
      <option value="<?= sanitize($f) ?>" <?= $f === $selected ? 'selected' : '' ?>><?= sanitize($f) ?></option>
      <?php endforeach; ?>
    </select>
    <?php if ($selected && $bytes): ?>
    <span style="font-size:12px;color:var(--text3)"><?= number_format($bytes / 1024, 1, ',', ' ') ?> Ko — 400 dernières lignes</span>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="card-body" style="padding:0">
    <pre style="margin:0;padding:1rem;font-size:11px;line-height:1.4;max-height:70vh;overflow:auto;background:var(--surface2,#f4f4f5);color:var(--text);white-space:pre-wrap;word-break:break-word"><?= $content !== '' ? sanitize($content) : '—' ?></pre>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
