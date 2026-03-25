<?php
require_once __DIR__ . '/../../includes/layout_init.php';

$taskId = (int)($_GET['id'] ?? 0);
if (!$taskId) { flashMessage('error','Tâche introuvable.'); redirect('/pages/tasks/list.php'); }

$pdo  = getDB();
$stmt = $pdo->prepare("SELECT t.*, u.prenom AS c_prenom, u.nom AS c_nom, d.nom AS dept_nom, b.nom AS base_nom, cat.nom AS cat_nom
    FROM taches t
    LEFT JOIN users u ON u.id=t.createur_id
    LEFT JOIN departements d ON d.id=t.departement_id
    LEFT JOIN bases b ON b.id=t.base_id
    LEFT JOIN categories cat ON cat.id=t.categorie_id
    WHERE t.id=?");
$stmt->execute([$taskId]);
$task = $stmt->fetch();
if (!$task || !canViewTask($task)) { flashMessage('error','Accès refusé.'); redirect('/pages/tasks/list.php'); }

$pageTitle = $task['titre'];
$realStatus = computeRealStatus($task);

// Assignés
$assignes = $pdo->prepare("SELECT u.id,u.prenom,u.nom,u.role FROM taches_assignees ta JOIN users u ON u.id=ta.user_id WHERE ta.tache_id=?");
$assignes->execute([$taskId]);
$assignes = $assignes->fetchAll();

// Commentaires
$comms = $pdo->prepare("SELECT c.*,u.prenom,u.nom FROM commentaires c JOIN users u ON u.id=c.user_id WHERE c.tache_id=? ORDER BY c.created_at ASC");
$comms->execute([$taskId]);
$comms = $comms->fetchAll();

// Fichiers
$files = $pdo->prepare("SELECT f.*,u.prenom,u.nom FROM fichiers f JOIN users u ON u.id=f.uploaded_by WHERE f.tache_id=? ORDER BY f.created_at DESC");
$files->execute([$taskId]);
$files = $files->fetchAll();

// Historique
$hist = $pdo->prepare("SELECT h.*,u.prenom,u.nom FROM historique h JOIN users u ON u.id=h.user_id WHERE h.tache_id=? ORDER BY h.created_at DESC LIMIT 20");
$hist->execute([$taskId]);
$hist = $hist->fetchAll();

// Sous-tâches
$subs = $pdo->prepare("SELECT t.* FROM taches t WHERE t.tache_parente_id=?");
$subs->execute([$taskId]);
$subs = $subs->fetchAll();

$chatMessages = [];
$lastChatId   = 0;
$chatOk       = true;
try {
    $chatStmt = $pdo->prepare(
        'SELECT m.*, u.prenom, u.nom FROM tache_chat_messages m
         JOIN users u ON u.id = m.user_id
         WHERE m.tache_id = ? ORDER BY m.id ASC LIMIT 300'
    );
    $chatStmt->execute([$taskId]);
    $chatMessages = $chatStmt->fetchAll();
    if (!empty($chatMessages)) {
        $last = $chatMessages[count($chatMessages) - 1];
        $lastChatId = (int) $last['id'];
    }
} catch (Throwable $e) {
    $chatOk = false;
}

// POST commentaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'comment') {
    verifyCsrf();
    $contenu = trim($_POST['contenu'] ?? '');
    if (!empty($contenu)) {
        $pdo->prepare("INSERT INTO commentaires (tache_id,user_id,contenu) VALUES (?,?,?)")
            ->execute([$taskId, $currentUser['id'], $contenu]);
        // Notifier les assignés
        foreach ($assignes as $a) {
            if ($a['id'] !== $currentUser['id']) {
                createNotification($a['id'],'comment','Nouveau commentaire',
                    sanitize($currentUser['prenom']).' a commenté : '.sanitize($task['titre']),
                    APP_URL.'/pages/tasks/view.php?id='.$taskId);
            }
        }
        notifyCommentMentions($taskId, (string) $task['titre'], $assignes, $contenu, $currentUser);
        redirect('/pages/tasks/view.php?id='.$taskId.'#comments');
    }
}

// POST changement statut
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'status') {
    verifyCsrf();
    if (canEditTask($task)) {
        $newStatus = $_POST['new_status'] ?? '';
        if (array_key_exists($newStatus, TASK_STATUSES)) {
            $old = $task['statut'];
            $closedAt = $newStatus === 'termine' ? date('Y-m-d H:i:s') : null;
            $pdo->prepare("UPDATE taches SET statut=?,date_cloture=? WHERE id=?")
                ->execute([$newStatus, $closedAt, $taskId]);
            logChange($taskId, $currentUser['id'], 'statut', $old, $newStatus);
            flashMessage('success','Statut mis à jour.');
            redirect('/pages/tasks/view.php?id='.$taskId);
        }
    }
}

// POST changement progression
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'progress') {
    verifyCsrf();
    if (canEditTask($task)) {
        $percent = min(100, max(0, (int)($_POST['pourcentage'] ?? 0)));
        $pdo->prepare("UPDATE taches SET pourcentage=? WHERE id=?")->execute([$percent, $taskId]);
        logChange($taskId, $currentUser['id'], 'pourcentage', $task['pourcentage'], $percent);
        flashMessage('success','Progression mise à jour.');
        redirect('/pages/tasks/view.php?id='.$taskId);
    }
}

// POST upload fichiers
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload') {
    verifyCsrf();
    if (!canEditTask($task)) {
        flashMessage('error', "Vous n'avez pas le droit d'ajouter des fichiers à cette tâche.");
        redirect('/pages/tasks/view.php?id='.$taskId);
    }
    $batch = collectUploadedFiles('fichiers');
    if (empty($batch)) {
        flashMessage('error', 'Aucun fichier reçu. Utilisez « Parcourir » ou glissez-déposez dans la zone, puis cliquez sur Uploader. Taille max 10 Mo par fichier.');
        redirect('/pages/tasks/view.php?id='.$taskId);
    }
    $fStmt = $pdo->prepare("INSERT INTO fichiers (tache_id,nom_original,chemin,taille,mime,uploaded_by) VALUES (?,?,?,?,?,?)");
    $ok = 0;
    foreach ($batch as $fdata) {
        $uploaded = handleFileUpload($fdata, $taskId);
        if ($uploaded) {
            $fStmt->execute([$taskId, $uploaded['nom_original'], $uploaded['chemin'], $uploaded['taille'], $uploaded['mime'], $currentUser['id']]);
            $ok++;
        }
    }
    if ($ok === 0) {
        flashMessage('error', "Échec de l'envoi. Formats : PDF, Word, Excel, images, ZIP/RAR — max 10 Mo. Vérifiez aussi les droits du dossier uploads/ sur le serveur.");
    } elseif ($ok < count($batch)) {
        flashMessage('success', $ok . ' fichier(s) enregistré(s). Certains fichiers ont été ignorés (format ou taille).');
    } else {
        flashMessage('success', 'Fichiers uploadés.');
    }
    redirect('/pages/tasks/view.php?id='.$taskId);
}

$breadcrumbs = [
  ['label'=>'Accueil','url'=>APP_URL.'/index.php'],
  ['label'=>'Tâches','url'=>APP_URL.'/pages/tasks/list.php'],
  ['label'=>mb_substr($task['titre'],0,40).'…','url'=>''],
];

require_once __DIR__ . '/../../includes/header.php';
?>
<meta name="csrf" content="<?= csrfToken() ?>">

<div class="page-header">
  <div>
    <div class="page-title"><?= sanitize($task['titre']) ?></div>
    <div style="display:flex;align-items:center;gap:8px;margin-top:6px">
      <?= getTaskStatusBadge($realStatus) ?>
      <?= getPriorityBadge($task['priorite']) ?>
      <span style="font-size:12px;color:var(--text3)">#<?= $task['id'] ?></span>
    </div>
  </div>
  <div style="display:flex;gap:8px">
    <?php if (canEditTask($task)): ?>
    <a href="<?= APP_URL ?>/pages/tasks/edit.php?id=<?= $taskId ?>" class="btn btn-secondary">✏️ Modifier</a>
    <?php endif; ?>
    <a href="<?= APP_URL ?>/pages/tasks/list.php" class="btn btn-secondary">← Retour</a>
  </div>
</div>

<div class="task-detail-grid">
  <!-- Colonne principale -->
  <div>
    <!-- Description -->
    <div class="card" style="margin-bottom:16px">
      <div class="card-header"><span class="card-title">Description</span></div>
      <div class="card-body">
        <?php if (!empty($task['description'])): ?>
          <div style="white-space:pre-wrap;font-size:14px;line-height:1.7"><?= sanitize($task['description']) ?></div>
        <?php else: ?>
          <div style="color:var(--text3);font-style:italic">Aucune description fournie.</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Progression -->
    <div class="card" style="margin-bottom:16px">
      <div class="card-header">
        <span class="card-title">Progression</span>
        <span style="font-weight:700;color:var(--primary)"><?= (int)$task['pourcentage'] ?>%</span>
      </div>
      <div class="card-body">
        <div class="progress-bar" style="height:10px">
          <div class="progress-fill" style="width:<?= (int)$task['pourcentage'] ?>%;background:<?= $realStatus==='termine'?'#16A34A':'var(--primary)' ?>"></div>
        </div>
        <?php if (canEditTask($task)): ?>
        <form method="POST" style="margin-top:10px;display:flex;align-items:center;gap:10px">
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
          <input type="hidden" name="action" value="progress">
          <input type="range" name="pourcentage" min="0" max="100" value="<?= (int)$task['pourcentage'] ?>" style="flex:1"
            oninput="this.nextElementSibling.textContent=this.value+'%'">
          <span style="font-size:13px;min-width:36px"><?= (int)$task['pourcentage'] ?>%</span>
          <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
        </form>
        <?php endif; ?>
      </div>
    </div>

    <!-- Changer statut -->
    <?php if (canEditTask($task)): ?>
    <div class="card" style="margin-bottom:16px">
      <div class="card-header"><span class="card-title">Changer le statut</span></div>
      <div class="card-body">
        <form method="POST" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
          <input type="hidden" name="action" value="status">
          <div style="display:flex;gap:8px;flex-wrap:wrap">
            <?php foreach (TASK_STATUSES as $k => $s): ?>
            <?php if ($k==='en_retard') continue; ?>
            <button type="submit" name="new_status" value="<?= $k ?>"
              class="btn btn-sm"
              style="background:<?= $s['bg'] ?>;color:<?= $s['color'] ?>;border-color:<?= $s['color'] ?>40;<?= $realStatus===$k?'box-shadow:0 0 0 2px '.$s['color']:''; ?>">
              <?= $s['label'] ?>
            </button>
            <?php endforeach; ?>
          </div>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <!-- Sous-tâches -->
    <?php if (!empty($subs)): ?>
    <div class="card" style="margin-bottom:16px">
      <div class="card-header"><span class="card-title">Sous-tâches (<?= count($subs) ?>)</span></div>
      <div class="card-body">
        <?php foreach ($subs as $sub): ?>
        <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--border)">
          <?= getTaskStatusBadge(computeRealStatus($sub)) ?>
          <a href="<?= APP_URL ?>/pages/tasks/view.php?id=<?= $sub['id'] ?>" style="flex:1;color:var(--text);text-decoration:none;font-size:13px">
            <?= sanitize($sub['titre']) ?>
          </a>
          <span style="font-size:12px;color:var(--text3)"><?= formatDate($sub['date_echeance']) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Commentaires -->
    <div class="card" id="comments">
      <div class="card-header"><span class="card-title">Commentaires (<?= count($comms) ?>)</span></div>
      <div class="card-body">
        <div class="comment-list" style="margin-bottom:20px">
          <?php foreach ($comms as $c): ?>
          <div class="comment-item">
            <div class="comment-avatar">
              <div class="user-avatar" style="background:<?= getUserColor($c['user_id']) ?>">
                <?= strtoupper(substr($c['prenom'],0,1).substr($c['nom'],0,1)) ?>
              </div>
            </div>
            <div style="flex:1">
              <div class="comment-body">
                <strong style="font-size:13px"><?= sanitize($c['prenom'].' '.$c['nom']) ?></strong>
                <div style="margin-top:4px;font-size:13px;white-space:pre-wrap"><?= sanitize($c['contenu']) ?></div>
              </div>
              <div class="comment-meta"><?= timeAgo($c['created_at']) ?></div>
            </div>
          </div>
          <?php endforeach; ?>
          <?php if (empty($comms)): ?>
            <div style="color:var(--text3);font-style:italic;text-align:center;padding:20px">Aucun commentaire.</div>
          <?php endif; ?>
        </div>
        <!-- Nouveau commentaire -->
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
          <input type="hidden" name="action" value="comment">
          <div class="form-group">
            <textarea name="contenu" class="form-control" rows="3" placeholder="Écrire un commentaire… (mention : @Prénom Nom d’un assigné)" required aria-label="Nouveau commentaire"></textarea>
          </div>
          <button type="submit" class="btn btn-primary btn-sm">💬 Commenter</button>
        </form>
      </div>
    </div>

    <!-- Chat tâche -->
    <div class="card" id="task-chat" style="margin-bottom:16px">
      <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;gap:8px">
        <span class="card-title">Discussion</span>
        <span class="badge" style="font-size:10px;font-weight:500;background:var(--primary-light);color:var(--primary)" title="Réception typiquement sous ~1 s">Instantané</span>
      </div>
      <div class="card-body" style="padding-top:0">
        <?php if (!$chatOk): ?>
        <p class="flash flash-error" style="margin:12px 0;font-size:13px">
          Chat indisponible : exécutez la migration <code>003_task_chat.sql</code> sur la base de données.
        </p>
        <?php else: ?>
        <div id="taskChatMessages"
             class="task-chat-messages"
             data-task-id="<?= (int) $taskId ?>"
             data-last-id="<?= (int) $lastChatId ?>"
             data-current-user="<?= (int) $currentUser['id'] ?>"
             role="log"
             aria-live="polite"
             aria-relevant="additions">
          <?php foreach ($chatMessages as $m): ?>
          <div class="chat-msg<?= (int) $m['user_id'] === (int) $currentUser['id'] ? ' chat-msg--me' : '' ?>" data-id="<?= (int) $m['id'] ?>">
            <div class="chat-msg-avatar">
              <div class="user-avatar" style="width:32px;height:32px;font-size:11px;background:<?= getUserColor((int) $m['user_id']) ?>">
                <?= strtoupper(mb_substr($m['prenom'], 0, 1) . mb_substr($m['nom'], 0, 1)) ?>
              </div>
            </div>
            <div class="chat-msg-body">
              <div class="chat-msg-head">
                <strong><?= sanitize($m['prenom'] . ' ' . $m['nom']) ?></strong>
                <span class="chat-msg-time"><?= sanitize(formatDateTime($m['created_at'])) ?></span>
              </div>
              <div class="chat-msg-text"><?= nl2br(sanitize($m['message'])) ?></div>
            </div>
          </div>
          <?php endforeach; ?>
          <?php if (empty($chatMessages)): ?>
          <p class="task-chat-empty" style="margin:0;padding:12px 0;color:var(--text3);font-size:13px;text-align:center">Aucun message. Démarrez la discussion.</p>
          <?php endif; ?>
        </div>
        <p id="taskChatTyping" class="task-chat-typing" hidden></p>
        <form id="taskChatForm" class="task-chat-form" autocomplete="off">
          <label class="visually-hidden" for="taskChatInput">Votre message</label>
          <textarea id="taskChatInput" class="form-control" rows="2" maxlength="2000" placeholder="Écrire un message… (Entrée pour envoyer, Maj+Entrée pour retour à la ligne)" required></textarea>
          <button type="submit" class="btn btn-primary btn-sm">Envoyer</button>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Colonne droite -->
  <div>
    <!-- Infos tâche -->
    <div class="card" style="margin-bottom:16px">
      <div class="card-header"><span class="card-title">Informations</span></div>
      <div class="card-body">
        <div class="detail-field"><div class="detail-label">Créé par</div><div class="detail-value"><?= sanitize($task['c_prenom'].' '.$task['c_nom']) ?></div></div>
        <div class="detail-field"><div class="detail-label">Date de création</div><div class="detail-value"><?= formatDateTime($task['date_creation']) ?></div></div>
        <div class="detail-field"><div class="detail-label">Date de début</div><div class="detail-value"><?= formatDate($task['date_debut']) ?></div></div>
        <div class="detail-field">
          <div class="detail-label">Date d'échéance</div>
          <div class="detail-value" style="color:<?= daysUntil($task['date_echeance'])<0?'#DC2626':'' ?>">
            <?= formatDate($task['date_echeance']) ?>
            <?php $d = daysUntil($task['date_echeance']); ?>
            <?php if ($realStatus !== 'termine' && $realStatus !== 'annule'): ?>
              <?php if ($d<0): ?><br><small style="color:#DC2626">⚠ Dépassé de <?= abs($d) ?>j</small>
              <?php elseif ($d===0): ?><br><small style="color:#D97706">⚡ Aujourd'hui</small>
              <?php elseif ($d<=3): ?><br><small style="color:#D97706">Dans <?= $d ?>j</small>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>
        <?php if (!empty($task['date_cloture'])): ?>
        <div class="detail-field"><div class="detail-label">Clôturé le</div><div class="detail-value"><?= formatDateTime($task['date_cloture']) ?></div></div>
        <?php endif; ?>
        <div class="detail-field"><div class="detail-label">Département</div><div class="detail-value"><?= sanitize($task['dept_nom'] ?? '—') ?></div></div>
        <div class="detail-field"><div class="detail-label">Base</div><div class="detail-value"><?= sanitize($task['base_nom'] ?? '—') ?></div></div>
        <div class="detail-field"><div class="detail-label">Catégorie</div><div class="detail-value"><?= sanitize($task['cat_nom'] ?? '—') ?></div></div>
      </div>
    </div>

    <!-- Assignés -->
    <div class="card" style="margin-bottom:16px">
      <div class="card-header"><span class="card-title">Assigné à (<?= count($assignes) ?>)</span></div>
      <div class="card-body">
        <?php if (empty($assignes)): ?>
          <div style="color:var(--text3);font-style:italic;font-size:13px">Non assigné</div>
        <?php else: ?>
        <?php foreach ($assignes as $a): ?>
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
          <div class="user-avatar" style="background:<?= getUserColor($a['id']) ?>">
            <?= strtoupper(substr($a['prenom'],0,1).substr($a['nom'],0,1)) ?>
          </div>
          <div>
            <div style="font-size:13px;font-weight:500"><?= sanitize($a['prenom'].' '.$a['nom']) ?></div>
            <div style="font-size:11px;color:var(--text3)"><?= ROLES[$a['role']] ?? '' ?></div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Fichiers -->
    <div class="card" style="margin-bottom:16px">
      <div class="card-header"><span class="card-title">Fichiers joints (<?= count($files) ?>)</span></div>
      <div class="card-body">
        <?php if (empty($files)): ?>
          <div style="color:var(--text3);font-style:italic;font-size:13px">Aucun fichier</div>
        <?php else: ?>
        <div class="file-list">
          <?php foreach ($files as $f): ?>
          <div class="file-item">
            <span class="file-icon">📎</span>
            <div style="flex:1;min-width:0">
              <div class="file-name" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= sanitize($f['nom_original']) ?></div>
              <div style="font-size:10px;color:var(--text3)"><?= formatSize($f['taille']) ?> — <?= sanitize($f['prenom'].' '.$f['nom']) ?></div>
            </div>
            <a href="<?= APP_URL ?>/uploads/<?= sanitize($f['chemin']) ?>" download class="btn-icon" title="Télécharger">⬇</a>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (canEditTask($task)): ?>
        <!-- Upload additionnel -->
        <form method="POST" enctype="multipart/form-data" style="margin-top:14px">
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
          <input type="hidden" name="action" value="upload">
          <div class="upload-zone" id="uploadZone2" style="padding:14px">
            <div style="font-size:20px">📎</div>
            <div style="font-size:12px">Glisser-déposer ou cliquer pour choisir</div>
            <input type="file" name="fichiers[]" id="fileInput2" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.zip,.rar">
          </div>
          <div class="file-list" id="fileList2"></div>
          <button type="submit" class="btn btn-secondary btn-sm" style="margin-top:8px;width:100%;justify-content:center">Uploader</button>
        </form>
        <?php endif; ?>
      </div>
    </div>

    <!-- Historique -->
    <div class="card">
      <div class="card-header"><span class="card-title">Historique</span></div>
      <div class="card-body" style="padding:12px">
        <?php foreach ($hist as $h): ?>
        <div class="history-item">
          <div class="history-dot"></div>
          <div style="flex:1;font-size:12px">
            <strong><?= sanitize($h['prenom'].' '.$h['nom']) ?></strong>
            <?php if ($h['action'] === 'creation'): ?>
              a créé la tâche
            <?php else: ?>
              a modifié <em><?= sanitize($h['champ']) ?></em>
              <?php if (!empty($h['ancienne_val']) && !empty($h['nouvelle_val'])): ?>
                : <span style="color:#DC2626"><?= sanitize(mb_substr($h['ancienne_val'],0,20)) ?></span>
                → <span style="color:#16A34A"><?= sanitize(mb_substr($h['nouvelle_val'],0,20)) ?></span>
              <?php endif; ?>
            <?php endif; ?>
          </div>
          <div class="history-time"><?= timeAgo($h['created_at']) ?></div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($hist)): ?>
          <div style="color:var(--text3);font-style:italic;font-size:12px;padding:10px 0">Aucun historique</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
if (document.getElementById('uploadZone2')) initUploadZone('uploadZone2','fileInput2','fileList2');
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
