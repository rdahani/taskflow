<?php
require_once __DIR__ . '/../../includes/layout_init.php';
require_once __DIR__ . '/../../includes/dm.php';

$pageTitle   = 'Messages';
$breadcrumbs = [
    ['label' => 'Accueil', 'url' => APP_URL . '/index.php'],
    ['label' => 'Messages', 'url' => ''],
];

$pdo      = getDB();
$uid      = (int) $currentUser['id'];
$with     = (int) ($_GET['with'] ?? 0);
$dmError  = false;
$dmErrMsg = '';
$threads  = [];

try {
    $threads = dmListThreadsForUser($pdo, $uid);
} catch (Throwable $e) {
    $dmError  = true;
    $dmErrMsg = APP_DEBUG ? $e->getMessage() : '';
}

$threadId     = null;
$peerRow      = null;
$dmMessages   = [];
$lastDmId     = 0;
$allUsersStmt = $pdo->prepare('SELECT id, prenom, nom, role FROM users WHERE actif = 1 AND id != ? ORDER BY nom, prenom');
$allUsersStmt->execute([$uid]);
$allUsers = $allUsersStmt->fetchAll(PDO::FETCH_ASSOC);

if (!$dmError && $with > 0) {
    if ($with === $uid) {
        flashMessage('error', 'Vous ne pouvez pas vous envoyer un message à vous-même.');
        redirect('/pages/messages/index.php');
    }
    $pst = $pdo->prepare('SELECT id, prenom, nom, role FROM users WHERE id = ? AND actif = 1');
    $pst->execute([$with]);
    $peerRow = $pst->fetch(PDO::FETCH_ASSOC);
    if (!$peerRow) {
        flashMessage('error', 'Utilisateur introuvable.');
        redirect('/pages/messages/index.php');
    }
    try {
        $threadId = dmEnsureThread($pdo, $uid, $with);
    } catch (Throwable $e) {
        flashMessage('error', 'Impossible de créer la conversation.');
        redirect('/pages/messages/index.php');
    }
    $mst = $pdo->prepare(
        'SELECT m.*, u.prenom, u.nom FROM dm_messages m
         JOIN users u ON u.id = m.sender_id
         WHERE m.thread_id = ? ORDER BY m.id ASC LIMIT 400'
    );
    $mst->execute([$threadId]);
    $dmMessages = $mst->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($dmMessages)) {
        $last       = $dmMessages[count($dmMessages) - 1];
        $lastDmId   = (int) $last['id'];
        dmMarkRead($pdo, $threadId, $uid, $lastDmId);
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>
<meta name="csrf" content="<?= csrfToken() ?>">

<div class="page-header">
  <div>
    <div class="page-title">Messages</div>
    <div class="page-subtitle">Conversations privées entre collègues</div>
  </div>
  <!-- Bouton nouvelle conv (mobile) -->
  <button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('dmNewConvWrap').classList.toggle('open')" style="display:none" id="dmNewConvToggle">
    <i class="fa-solid fa-pen-to-square"></i> Nouveau
  </button>
</div>

<?php if ($dmError): ?>
<div class="card">
  <div class="card-body flash flash-error" style="padding:1.25rem">
    La messagerie directe nécessite la migration <code>004_direct_messages.sql</code>.
    <?php if (APP_DEBUG && $dmErrMsg !== ''): ?>
      <pre style="margin-top:8px;font-size:11px;overflow:auto"><?= sanitize($dmErrMsg) ?></pre>
    <?php endif; ?>
  </div>
</div>
<?php else: ?>

<div class="dm-layout">
  <!-- ─── Sidebar gauche ─── -->
  <aside class="dm-sidebar card" style="margin:0;padding:0;overflow:hidden;display:flex;flex-direction:column">

    <!-- Nouvelle conversation -->
    <div id="dmNewConvWrap" style="padding:12px 14px;border-bottom:1px solid var(--border)">
      <div style="font-size:11px;font-weight:600;color:var(--text3);text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px">Nouvelle conversation</div>
      <form method="get" action="" style="display:flex;gap:8px">
        <select name="with" id="dmNewPeer" class="form-control" style="flex:1" required>
          <option value="">— Choisir un collègue —</option>
          <?php foreach ($allUsers as $u): ?>
          <option value="<?= (int) $u['id'] ?>" <?= $with === (int) $u['id'] ? 'selected' : '' ?>>
            <?= sanitize($u['prenom'] . ' ' . $u['nom']) ?> · <?= sanitize(ROLES[$u['role']] ?? $u['role']) ?>
          </option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary btn-sm" title="Ouvrir la conversation"><i class="fa-solid fa-arrow-right"></i></button>
      </form>
    </div>

    <!-- Filtre conversations -->
    <?php if (count($threads) > 3): ?>
    <div style="padding:8px 12px;border-bottom:1px solid var(--border)">
      <div style="position:relative">
        <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:9px;top:50%;transform:translateY(-50%);color:var(--text3);font-size:12px"></i>
        <input type="search" id="dmThreadSearch" class="form-control" style="padding-left:28px;font-size:12px;height:32px" placeholder="Filtrer les conversations…" autocomplete="off">
      </div>
    </div>
    <?php endif; ?>

    <!-- Liste des conversations -->
    <div class="dm-thread-list" id="dmThreadList">
      <?php if (empty($threads)): ?>
      <div style="padding:32px 16px;text-align:center;color:var(--text3)">
        <i class="fa-regular fa-comments" style="font-size:28px;opacity:0.3;display:block;margin-bottom:10px"></i>
        <div style="font-size:13px">Aucune conversation</div>
        <div style="font-size:12px;margin-top:4px">Choisissez un collègue ci-dessus.</div>
      </div>
      <?php else: ?>
      <?php foreach ($threads as $t): ?>
      <?php $unread = (int) ($t['unread'] ?? 0); ?>
      <a href="<?= APP_URL ?>/pages/messages/index.php?with=<?= (int) $t['peer_id'] ?>"
         class="dm-thread-item <?= ($with === (int) $t['peer_id']) ? 'is-active' : '' ?>"
         data-name="<?= sanitize(strtolower($t['prenom'] . ' ' . $t['nom'])) ?>">
        <div class="user-avatar" style="width:40px;height:40px;font-size:13px;flex-shrink:0;background:<?= getUserColor((int) $t['peer_id']) ?>">
          <?= strtoupper(mb_substr($t['prenom'], 0, 1) . mb_substr($t['nom'], 0, 1)) ?>
        </div>
        <div class="dm-thread-meta">
          <div class="dm-thread-top">
            <strong class="dm-thread-name"><?= sanitize($t['prenom'] . ' ' . $t['nom']) ?></strong>
            <div class="dm-thread-right">
              <?php if ($unread > 0): ?>
              <span class="dm-unread"><?= $unread ?></span>
              <?php endif; ?>
              <?php if (!empty($t['last_at'])): ?>
              <span class="dm-thread-time"><?= sanitize(timeAgo($t['last_at'])) ?></span>
              <?php endif; ?>
            </div>
          </div>
          <div class="dm-thread-preview">
            <?= !empty($t['last_body']) ? sanitize(mb_substr((string) $t['last_body'], 0, 72)) : '<em>Aucun message</em>' ?>
          </div>
        </div>
      </a>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </aside>

  <!-- ─── Zone principale ─── -->
  <section class="dm-main card" style="margin:0;min-height:480px;display:flex;flex-direction:column">
    <?php if (!$peerRow || !$threadId): ?>
    <div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;color:var(--text3);padding:32px;text-align:center;gap:12px">
      <i class="fa-regular fa-paper-plane" style="font-size:40px;opacity:0.25"></i>
      <div style="font-size:14px;font-weight:500">Aucune conversation sélectionnée</div>
      <div style="font-size:13px">Choisissez une conversation ou démarrez-en une nouvelle.</div>
    </div>
    <?php else: ?>

    <!-- En-tête du fil -->
    <div style="padding:12px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px;flex-shrink:0">
      <div class="user-avatar" style="width:42px;height:42px;font-size:14px;flex-shrink:0;background:<?= getUserColor((int) $peerRow['id']) ?>">
        <?= strtoupper(mb_substr($peerRow['prenom'], 0, 1) . mb_substr($peerRow['nom'], 0, 1)) ?>
      </div>
      <div style="flex:1;min-width:0">
        <div style="font-weight:700;font-size:15px;line-height:1.2"><?= sanitize($peerRow['prenom'] . ' ' . $peerRow['nom']) ?></div>
        <div style="font-size:12px;color:var(--text3);margin-top:2px"><?= sanitize(ROLES[$peerRow['role']] ?? $peerRow['role']) ?></div>
      </div>
      <a href="<?= APP_URL ?>/pages/messages/index.php" class="btn btn-secondary btn-sm" title="Retour à la liste" style="flex-shrink:0">
        <i class="fa-solid fa-xmark"></i>
      </a>
    </div>

    <!-- Messages -->
    <div id="dmChatMessages"
         class="task-chat-messages dm-chat-pane"
         data-thread-id="<?= (int) $threadId ?>"
         data-peer-id="<?= (int) $with ?>"
         data-last-id="<?= (int) $lastDmId ?>"
         data-current-user="<?= (int) $uid ?>"
         role="log"
         aria-live="polite"
         aria-label="Fil de discussion"
         style="flex:1;max-height:none;min-height:300px;margin:0;border:none;padding:16px">
      <?php foreach ($dmMessages as $m): ?>
      <div class="chat-msg<?= (int) $m['sender_id'] === $uid ? ' chat-msg--me' : '' ?>" data-id="<?= (int) $m['id'] ?>">
        <div class="chat-msg-avatar">
          <div class="user-avatar" style="width:32px;height:32px;font-size:11px;background:<?= getUserColor((int) $m['sender_id']) ?>">
            <?= strtoupper(mb_substr($m['prenom'], 0, 1) . mb_substr($m['nom'], 0, 1)) ?>
          </div>
        </div>
        <div class="chat-msg-body">
          <div class="chat-msg-head">
            <strong><?= sanitize($m['prenom'] . ' ' . $m['nom']) ?></strong>
            <span class="chat-msg-time"><?= sanitize(formatDateTime($m['created_at'])) ?></span>
          </div>
          <div class="chat-msg-text"><?= nl2br(sanitize($m['body'])) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($dmMessages)): ?>
      <div class="task-chat-empty" style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;color:var(--text3);gap:8px;padding:24px">
        <i class="fa-regular fa-comment-dots" style="font-size:32px;opacity:0.3"></i>
        <div style="font-size:13px">Démarrez la conversation ci-dessous.</div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Indicateur de frappe -->
    <p id="dmChatTyping" class="task-chat-typing" style="margin:0 16px;padding-bottom:4px" hidden></p>

    <!-- Formulaire de saisie -->
    <form id="dmChatForm" style="padding:10px 14px 12px;border-top:1px solid var(--border);display:flex;gap:8px;align-items:flex-end" autocomplete="off">
      <div style="flex:1;position:relative">
        <label class="visually-hidden" for="dmChatInput">Votre message</label>
        <textarea id="dmChatInput" class="form-control dm-chat-input" rows="1" maxlength="4000"
                  placeholder="Écrire un message… (Entrée pour envoyer, Maj+Entrée pour nouvelle ligne)"
                  required style="resize:none;overflow:hidden;max-height:140px;line-height:1.5"></textarea>
      </div>
      <button type="submit" class="btn btn-primary" style="flex-shrink:0;padding:0.5rem 0.875rem" title="Envoyer (Entrée)">
        <i class="fa-solid fa-paper-plane"></i>
      </button>
    </form>
    <?php endif; ?>
  </section>
</div>

<script>
// Filtre de recherche dans la liste des conversations
(function () {
  const inp = document.getElementById('dmThreadSearch');
  if (!inp) return;
  inp.addEventListener('input', function () {
    const q = this.value.trim().toLowerCase();
    document.querySelectorAll('#dmThreadList .dm-thread-item').forEach(function (el) {
      const name = (el.dataset.name || '').toLowerCase();
      el.style.display = (!q || name.includes(q)) ? '' : 'none';
    });
  });
})();
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
