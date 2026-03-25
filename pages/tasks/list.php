<?php
require_once __DIR__ . '/../../config/config.php';
$pageTitle  = 'Liste des tâches';
$breadcrumbs = [
  ['label'=>'Accueil','url'=>APP_URL.'/index.php'],
  ['label'=>'Tâches','url'=>''],
];
require_once __DIR__ . '/../../includes/header.php';
?>
<meta name="csrf" content="<?= csrfToken() ?>">
<?php

$pdo = getDB();

// --- Filtres ---
$filterStatut   = $_GET['statut']   ?? '';
$filterPriorite = $_GET['priorite'] ?? '';
$filterDept     = (int)($_GET['dept'] ?? 0);
$filterBase     = (int)($_GET['base'] ?? 0);
$filterSearch   = trim($_GET['q'] ?? '');
$page           = max(1, (int)($_GET['page'] ?? 1));

// --- Construction de la requête selon le rôle ---
$where  = ['1=1'];
$params = [];

if ($currentUser['role'] === 'employe') {
    $where[] = "ta.user_id = ?";
    $params[] = $currentUser['id'];
    $join = "JOIN taches_assignees ta ON ta.tache_id = t.id";
} elseif (in_array($currentUser['role'], ['superviseur','chef_dept'])) {
    $where[] = "t.departement_id = ?";
    $params[] = $currentUser['departement_id'];
    $join = "";
} else {
    $join = "";
}

if ($filterStatut)   { $where[] = "t.statut = ?";           $params[] = $filterStatut; }
if ($filterPriorite) { $where[] = "t.priorite = ?";         $params[] = $filterPriorite; }
if ($filterDept)     { $where[] = "t.departement_id = ?";   $params[] = $filterDept; }
if ($filterBase)     { $where[] = "t.base_id = ?";          $params[] = $filterBase; }
if ($filterSearch)   { $where[] = "(t.titre LIKE ? OR t.description LIKE ?)"; $params[] = "%$filterSearch%"; $params[] = "%$filterSearch%"; }

$whereStr = implode(' AND ', $where);

// Total
$countSql  = "SELECT COUNT(DISTINCT t.id) FROM taches t $join WHERE $whereStr";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$pag = paginate($total, $page);

// Données
$dataSql = "SELECT DISTINCT t.*, 
    u.prenom AS createur_prenom, u.nom AS createur_nom,
    d.nom AS dept_nom, b.nom AS base_nom,
    (SELECT GROUP_CONCAT(CONCAT(u2.prenom,' ',u2.nom) SEPARATOR ', ')
     FROM taches_assignees ta2 JOIN users u2 ON u2.id=ta2.user_id
     WHERE ta2.tache_id=t.id) AS assignes
    FROM taches t
    $join
    LEFT JOIN users u ON u.id=t.createur_id
    LEFT JOIN departements d ON d.id=t.departement_id
    LEFT JOIN bases b ON b.id=t.base_id
    WHERE $whereStr
    ORDER BY FIELD(t.priorite,'urgente','haute','normale','basse'), t.date_echeance ASC
    LIMIT ? OFFSET ?";

$dataParams = array_merge($params, [$pag['per_page'], $pag['offset']]);
$dataStmt   = $pdo->prepare($dataSql);
$dataStmt->execute($dataParams);
$tasks = $dataStmt->fetchAll();

// Listes filtres
$depts = $pdo->query("SELECT id,nom FROM departements WHERE actif=1 ORDER BY nom")->fetchAll();
$bases = $pdo->query("SELECT id,nom FROM bases WHERE actif=1 ORDER BY nom")->fetchAll();

$filterUrl = '?statut='.urlencode($filterStatut).'&priorite='.urlencode($filterPriorite)
           . '&dept='.$filterDept.'&base='.$filterBase.'&q='.urlencode($filterSearch);
?>

<div class="page-header">
  <div>
    <div class="page-title">Tâches</div>
    <div class="page-subtitle"><?= $total ?> tâche<?= $total > 1 ? 's' : '' ?> trouvée<?= $total > 1 ? 's' : '' ?></div>
  </div>
  <div style="display:flex;gap:8px">
    <a href="<?= APP_URL ?>/pages/tasks/kanban.php" class="btn btn-secondary"><i class="fa-solid fa-table-columns"></i> Kanban</a>
    <?php if (isSuperviseur()): ?>
    <a href="<?= APP_URL ?>/pages/tasks/create.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Nouvelle tâche</a>
    <?php endif; ?>
  </div>
</div>

<!-- Filtres -->
<div class="filter-bar">
  <input type="text" id="searchInput" placeholder="🔍 Rechercher..." value="<?= sanitize($filterSearch) ?>" style="min-width:200px">
  <select id="filterStatut">
    <option value="">Tous les statuts</option>
    <?php foreach (TASK_STATUSES as $k => $s): ?>
      <option value="<?= $k ?>" <?= $filterStatut===$k?'selected':'' ?>><?= $s['label'] ?></option>
    <?php endforeach; ?>
  </select>
  <select id="filterPriorite">
    <option value="">Toutes priorités</option>
    <?php foreach (TASK_PRIORITIES as $k => $p): ?>
      <option value="<?= $k ?>" <?= $filterPriorite===$k?'selected':'' ?>><?= $p['label'] ?></option>
    <?php endforeach; ?>
  </select>
  <?php if (isChefDept()): ?>
  <select id="filterDept">
    <option value="">Tous départements</option>
    <?php foreach ($depts as $d): ?>
      <option value="<?= $d['id'] ?>" <?= $filterDept==$d['id']?'selected':'' ?>><?= sanitize($d['nom']) ?></option>
    <?php endforeach; ?>
  </select>
  <?php endif; ?>
  <?php if (isCheffe()): ?>
  <select id="filterBase">
    <option value="">Toutes les bases</option>
    <?php foreach ($bases as $b): ?>
      <option value="<?= $b['id'] ?>" <?= $filterBase==$b['id']?'selected':'' ?>><?= sanitize($b['nom']) ?></option>
    <?php endforeach; ?>
  </select>
  <?php endif; ?>
  <button onclick="applyFilters()" class="btn btn-primary btn-sm">Filtrer</button>
  <a href="?" class="btn btn-secondary btn-sm" onclick="localStorage.removeItem('taskflow_list_filters');">Réinitialiser</a>
</div>

<!-- Tableau -->
<div class="card">
  <div class="table-wrapper">
    <table id="tasksTable">
      <thead>
        <tr>
          <?php if (isSuperviseur()): ?>
          <th style="width:36px;text-align:center">
            <input type="checkbox" id="selectAll" title="Tout sélectionner" style="cursor:pointer;width:15px;height:15px">
          </th>
          <?php endif; ?>
          <th>#</th>
          <th>Titre</th>
          <th>Statut</th>
          <th>Priorité</th>
          <th>Créé par</th>
          <th>Département / Base</th>
          <th>Échéance</th>
          <th>%</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($tasks as $task): ?>
        <?php $realStatus = computeRealStatus($task); ?>
        <tr class="<?= getTaskRowClass($task) ?>" data-id="<?= (int) $task['id'] ?>">
          <?php if (isSuperviseur()): ?>
          <td style="text-align:center">
            <input type="checkbox" class="task-cb" value="<?= (int) $task['id'] ?>"
                   data-titre="<?= sanitize($task['titre']) ?>"
                   data-echeance="<?= sanitize($task['date_echeance'] ?? '') ?>"
                   style="cursor:pointer;width:15px;height:15px">
          </td>
          <?php endif; ?>
          <td style="color:var(--text3);font-size:12px">#<?= $task['id'] ?></td>
          <td>
            <a href="<?= APP_URL ?>/pages/tasks/view.php?id=<?= $task['id'] ?>" style="color:var(--text);text-decoration:none;font-weight:500;display:block">
              <?= sanitize($task['titre']) ?>
            </a>
            <?php if (!empty($task['assignes'])): ?>
            <div style="font-size:11px;color:var(--text3)"><i class="fa-solid fa-user" style="font-size:9px"></i> <?= sanitize($task['assignes']) ?></div>
            <?php endif; ?>
          </td>
          <td><?= getTaskStatusBadge($realStatus) ?></td>
          <td><?= getPriorityBadge($task['priorite']) ?></td>
          <td style="font-size:12px"><?= sanitize($task['createur_prenom'].' '.$task['createur_nom']) ?></td>
          <td style="font-size:12px">
            <?= sanitize($task['dept_nom'] ?? '—') ?><br>
            <span style="color:var(--text3)"><?= sanitize($task['base_nom'] ?? '') ?></span>
          </td>
          <td>
            <?php $days = daysUntil($task['date_echeance']); ?>
            <span style="color:<?= $days < 0 ? '#DC2626' : ($days <= 3 ? '#D97706' : 'var(--text)') ?>;font-size:13px">
              <?= formatDate($task['date_echeance']) ?>
            </span>
          </td>
          <td>
            <div style="font-size:12px;margin-bottom:3px"><?= (int)$task['pourcentage'] ?>%</div>
            <div class="progress-bar" style="width:60px">
              <div class="progress-fill" style="width:<?= (int)$task['pourcentage'] ?>%"></div>
            </div>
          </td>
          <td>
            <div style="display:flex;gap:4px">
              <a href="<?= APP_URL ?>/pages/tasks/view.php?id=<?= $task['id'] ?>" class="btn-icon" title="Voir"><i class="fa-regular fa-eye"></i></a>
              <?php if (canEditTask($task)): ?>
              <a href="<?= APP_URL ?>/pages/tasks/edit.php?id=<?= $task['id'] ?>" class="btn-icon" title="Modifier"><i class="fa-solid fa-pen"></i></a>
              <?php endif; ?>
              <?php if (isAdmin()): ?>
              <button type="button" class="btn-icon" onclick="deleteTask(<?= (int) $task['id'] ?>)" title="Supprimer" aria-label="Supprimer la tâche"><i class="fa-solid fa-trash"></i></button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($tasks)): ?>
        <tr><td colspan="<?= isSuperviseur() ? 10 : 9 ?>" style="text-align:center;color:var(--text3);padding:40px">Aucune tâche trouvée</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?= renderPagination($pag, $filterUrl) ?>

<?php if (isSuperviseur()): ?>
<!-- ══ Barre d'actions groupées ══ -->
<div id="bulkBar" style="
  display:none;position:fixed;bottom:24px;left:50%;transform:translateX(-50%);
  background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-xl);
  box-shadow:0 8px 32px rgba(0,0,0,0.18);padding:12px 20px;
  display:none;align-items:center;gap:12px;z-index:500;min-width:360px;
  animation:slideUp 0.2s ease;">
  <div style="display:flex;align-items:center;gap:8px;flex:1">
    <div style="width:32px;height:32px;border-radius:8px;background:var(--primary-light);display:flex;align-items:center;justify-content:center">
      <i class="fa-solid fa-check-double" style="color:var(--primary);font-size:14px"></i>
    </div>
    <span id="bulkCount" style="font-weight:600;font-size:14px">0 sélectionnée(s)</span>
  </div>
  <button type="button" class="btn btn-primary btn-sm" onclick="openReconduireModal()" title="Reconduire les tâches sélectionnées vers les mois suivants">
    <i class="fa-solid fa-rotate-right"></i> Reconduire
  </button>
  <button type="button" class="btn btn-secondary btn-sm" onclick="clearSelection()">
    <i class="fa-solid fa-xmark"></i>
  </button>
</div>
<style>
@keyframes slideUp { from { opacity:0;transform:translateX(-50%) translateY(16px); } to { opacity:1;transform:translateX(-50%) translateY(0); } }
</style>

<!-- ══ Modal de reconduction ══ -->
<div class="modal-overlay" id="reconduireModal">
  <div class="modal" style="max-width:520px;width:95%">
    <div class="modal-header" style="padding:1.25rem 1.5rem;border-bottom:1px solid var(--border)">
      <div style="display:flex;align-items:center;gap:12px">
        <div style="width:40px;height:40px;border-radius:10px;background:var(--primary-light);display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <i class="fa-solid fa-rotate-right" style="color:var(--primary);font-size:16px"></i>
        </div>
        <div>
          <div class="modal-title" style="font-size:16px;font-weight:700">Reconduire les tâches</div>
          <div style="font-size:12px;color:var(--text3)" id="reconduireSubtitle">0 tâche(s) sélectionnée(s)</div>
        </div>
      </div>
    </div>

    <div class="modal-body" style="padding:1.25rem 1.5rem">

      <!-- Liste des tâches sélectionnées -->
      <div style="background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius);padding:10px 12px;margin-bottom:1.25rem;max-height:120px;overflow-y:auto">
        <ul id="reconduireTaskList" style="margin:0;padding:0;list-style:none;font-size:13px;display:flex;flex-direction:column;gap:4px"></ul>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:1rem">
        <!-- Décalage -->
        <div class="form-group" style="margin:0">
          <label class="form-label">Décalage mensuel</label>
          <select id="rcDecalage" class="form-control">
            <option value="1">+1 mois (mois suivant)</option>
            <option value="2">+2 mois</option>
            <option value="3">+3 mois (trimestriel)</option>
            <option value="4">+4 mois</option>
            <option value="6">+6 mois (semestriel)</option>
            <option value="12">+12 mois (annuel)</option>
          </select>
          <div style="font-size:11px;color:var(--text3);margin-top:4px">Intervalle entre chaque copie</div>
        </div>

        <!-- Nombre de répétitions -->
        <div class="form-group" style="margin:0">
          <label class="form-label">Nombre de répétitions</label>
          <select id="rcRepetitions" class="form-control">
            <?php for ($i = 1; $i <= 12; $i++): ?>
            <option value="<?= $i ?>" <?= $i === 1 ? 'selected' : '' ?>><?= $i ?> copie<?= $i > 1 ? 's' : '' ?></option>
            <?php endfor; ?>
          </select>
          <div style="font-size:11px;color:var(--text3);margin-top:4px">Nombre de mois couverts</div>
        </div>
      </div>

      <!-- Aperçu des dates -->
      <div id="rcPreview" style="background:var(--primary-light);border:1px solid rgba(0,134,205,0.2);border-radius:var(--radius);padding:10px 14px;margin-bottom:1rem;font-size:12px">
        <div style="font-weight:600;color:var(--primary);margin-bottom:6px"><i class="fa-solid fa-calendar-days"></i> Aperçu des dates créées</div>
        <div id="rcPreviewDates" style="color:var(--text2);line-height:1.8"></div>
      </div>

      <!-- Options -->
      <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:13px;padding:8px 0">
        <input type="checkbox" id="rcCopierAssignes" checked style="width:15px;height:15px;cursor:pointer">
        <span><strong>Conserver les personnes assignées</strong> des tâches d'origine</span>
      </label>
    </div>

    <div class="modal-footer" style="padding:1rem 1.5rem;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:8px">
      <button type="button" class="btn btn-secondary" onclick="closeModal('reconduireModal')">Annuler</button>
      <button type="button" class="btn btn-primary" id="rcSubmitBtn" onclick="submitReconduire()">
        <i class="fa-solid fa-rotate-right"></i>
        <span id="rcSubmitLabel">Reconduire</span>
      </button>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
const TF_FILTERS_KEY = 'taskflow_list_filters';
function applyFilters() {
  const q = document.getElementById('searchInput').value;
  const statut = document.getElementById('filterStatut').value;
  const priorite = document.getElementById('filterPriorite').value;
  const dept = document.getElementById('filterDept')?.value || '';
  const base = document.getElementById('filterBase')?.value || '';
  try {
    localStorage.setItem(TF_FILTERS_KEY, JSON.stringify({ q, statut, priorite, dept, base }));
  } catch (e) {}
  window.location = '?q='+encodeURIComponent(q)+'&statut='+statut+'&priorite='+priorite+'&dept='+dept+'&base='+base;
}
document.getElementById('searchInput').addEventListener('keydown', e => { if (e.key==='Enter') applyFilters(); });

// ══ Sélection multiple + Reconduction ══
(function () {
  const isSuperv = <?= isSuperviseur() ? 'true' : 'false' ?>;
  if (!isSuperv) return;

  const bar       = document.getElementById('bulkBar');
  const countEl   = document.getElementById('bulkCount');
  const selectAll = document.getElementById('selectAll');

  function getChecked() {
    return [...document.querySelectorAll('.task-cb:checked')];
  }

  function updateBar() {
    const checked = getChecked();
    const n = checked.length;
    if (n > 0) {
      countEl.textContent = n + ' tâche' + (n > 1 ? 's' : '') + ' sélectionnée' + (n > 1 ? 's' : '');
      bar.style.display = 'flex';
    } else {
      bar.style.display = 'none';
    }
    // sync select-all state
    const total = document.querySelectorAll('.task-cb').length;
    if (selectAll) {
      selectAll.indeterminate = n > 0 && n < total;
      selectAll.checked = n > 0 && n === total;
    }
  }

  // Highlight row on select
  document.querySelectorAll('.task-cb').forEach(function (cb) {
    cb.addEventListener('change', function () {
      const row = this.closest('tr');
      if (row) row.style.background = this.checked ? 'var(--primary-light)' : '';
      updateBar();
      updatePreview();
    });
  });

  if (selectAll) {
    selectAll.addEventListener('change', function () {
      document.querySelectorAll('.task-cb').forEach(function (cb) {
        cb.checked = selectAll.checked;
        const row = cb.closest('tr');
        if (row) row.style.background = selectAll.checked ? 'var(--primary-light)' : '';
      });
      updateBar();
      updatePreview();
    });
  }

  window.clearSelection = function () {
    document.querySelectorAll('.task-cb').forEach(function (cb) {
      cb.checked = false;
      const row = cb.closest('tr');
      if (row) row.style.background = '';
    });
    if (selectAll) { selectAll.checked = false; selectAll.indeterminate = false; }
    updateBar();
  };

  // ── Modal reconduction ──
  function getSelectionData() {
    return getChecked().map(function (cb) {
      return { id: parseInt(cb.value), titre: cb.dataset.titre, echeance: cb.dataset.echeance };
    });
  }

  function addMonths(dateStr, months) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    d.setMonth(d.getMonth() + months);
    return d.toLocaleDateString('fr-FR', { day: '2-digit', month: 'long', year: 'numeric' });
  }

  function updatePreview() {
    const previewEl = document.getElementById('rcPreviewDates');
    const taskList  = document.getElementById('reconduireTaskList');
    const subtitle  = document.getElementById('reconduireSubtitle');
    if (!previewEl) return;

    const sel   = getSelectionData();
    const decal = parseInt(document.getElementById('rcDecalage')?.value || 1);
    const reps  = parseInt(document.getElementById('rcRepetitions')?.value || 1);

    if (subtitle) subtitle.textContent = sel.length + ' tâche' + (sel.length > 1 ? 's' : '') + ' sélectionnée' + (sel.length > 1 ? 's' : '');

    if (taskList) {
      taskList.innerHTML = sel.map(function (t) {
        return '<li style="display:flex;align-items:center;gap:6px"><i class="fa-solid fa-list-check" style="color:var(--primary);font-size:10px;flex-shrink:0"></i><span>' + escHtml(t.titre) + (t.echeance ? '<span style="color:var(--text3)"> — ' + new Date(t.echeance).toLocaleDateString('fr-FR') + '</span>' : '') + '</span></li>';
      }).join('') || '<li style="color:var(--text3)">Aucune tâche sélectionnée</li>';
    }

    if (!sel.length) { previewEl.innerHTML = '<em style="color:var(--text3)">Sélectionnez des tâches dans le tableau.</em>'; return; }

    const firstTask = sel[0];
    let html = '';
    for (let rep = 1; rep <= reps; rep++) {
      const shift = decal * rep;
      const newDate = addMonths(firstTask.echeance, shift);
      const badge = rep === 1 ? ' <span style="background:var(--primary);color:#fff;border-radius:4px;padding:1px 6px;font-size:10px">prochain</span>' : '';
      html += '<div style="display:flex;align-items:center;gap:8px"><i class="fa-solid fa-arrow-right" style="color:var(--primary);font-size:10px;flex-shrink:0"></i>';
      html += '<span>Copie ' + rep + ' · ';
      html += (sel.length > 1 ? sel.length + ' tâches' : escHtml(firstTask.titre));
      html += ' → <strong>' + (newDate || '+' + shift + ' mois') + '</strong>' + badge + '</span></div>';
    }
    previewEl.innerHTML = html;
  }

  window.openReconduireModal = function () {
    updatePreview();
    document.getElementById('reconduireModal')?.classList.add('open');
  };

  ['rcDecalage', 'rcRepetitions'].forEach(function (id) {
    const el = document.getElementById(id);
    if (el) el.addEventListener('change', updatePreview);
  });

  window.submitReconduire = async function () {
    const sel  = getSelectionData();
    if (!sel.length) { Toast.warning('Sélection vide', 'Cochez des tâches dans le tableau.'); return; }

    const ids          = sel.map(function (t) { return t.id; });
    const decal        = parseInt(document.getElementById('rcDecalage').value);
    const reps         = parseInt(document.getElementById('rcRepetitions').value);
    const copierAssign = document.getElementById('rcCopierAssignes').checked;
    const csrf         = document.querySelector('meta[name=csrf]')?.content || '';

    const btn   = document.getElementById('rcSubmitBtn');
    const label = document.getElementById('rcSubmitLabel');
    btn.disabled = true;
    label.textContent = 'En cours…';

    try {
      const res  = await tfFetch('/taskflow/api/tasks.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
        body: JSON.stringify({
          action: 'reconduire',
          ids: ids,
          decalage_mois: decal,
          nb_repetitions: reps,
          copier_assignes: copierAssign,
        }),
      });
      const data = await res.json();
      closeModal('reconduireModal');
      clearSelection();
      if (data.success) {
        Toast.success('Reconduction réussie', data.message);
        setTimeout(function () { window.location.reload(); }, 1500);
      } else {
        Toast.error('Erreur', data.error || data.message || 'Échec');
      }
    } catch (e) {
      Toast.error('Erreur réseau');
    } finally {
      btn.disabled = false;
      label.textContent = 'Reconduire';
    }
  };
})();
document.addEventListener('DOMContentLoaded', () => {
  const params = new URLSearchParams(window.location.search);
  const keys = [...params.keys()];
  if (keys.length === 1 && keys[0] === 'page') return;
  if (keys.some(k => k !== 'page')) {
    const hasFilter = ['q','statut','priorite','dept','base'].some(k => {
      const v = params.get(k);
      return v !== null && v !== '';
    });
    if (hasFilter) return;
  }
  try {
    const raw = localStorage.getItem(TF_FILTERS_KEY);
    if (!raw) return;
    const f = JSON.parse(raw);
    const q = new URLSearchParams();
    if (f.q) q.set('q', f.q);
    if (f.statut) q.set('statut', f.statut);
    if (f.priorite) q.set('priorite', f.priorite);
    if (f.dept) q.set('dept', f.dept);
    if (f.base) q.set('base', f.base);
    if ([...q.keys()].length) window.location.replace('?' + q.toString());
  } catch (e) {}
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
