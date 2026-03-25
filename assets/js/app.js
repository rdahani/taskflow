/* ============================================================
   TaskFlow — JavaScript principal
   ============================================================ */

'use strict';

/** Préfixe chemin app (ex. '' ou '/taskflow'), défini dans includes/header.php */
function tfUrl(path) {
  const base = typeof window.TF_BASE === 'string' ? window.TF_BASE : '';
  const p = path.startsWith('/') ? path : '/' + path;
  return base + p;
}
window.tfUrl = tfUrl;

/* ============================================================
   DARK MODE
   ============================================================ */
const DarkMode = {
  init() {
    const saved = localStorage.getItem('darkMode');
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    if (saved === '1' || (!saved && prefersDark)) {
      document.documentElement.classList.add('dark');
    }
    this._updateIcon();
  },
  toggle() {
    const isDark = document.documentElement.classList.toggle('dark');
    localStorage.setItem('darkMode', isDark ? '1' : '0');
    this._updateIcon();
  },
  _updateIcon() {
    const isDark = document.documentElement.classList.contains('dark');
    const icon = document.getElementById('darkIcon');
    if (icon) {
      icon.className = isDark ? 'fa-solid fa-sun' : 'fa-regular fa-moon';
    }
    const btn = document.getElementById('darkBtn');
    if (btn) btn.title = isDark ? 'Mode clair' : 'Mode sombre';
  }
};
window.DarkMode = DarkMode;

// Run immediately so there's no flash
DarkMode.init();

/* ============================================================
   SIDEBAR
   ============================================================ */
const Sidebar = {
  el: null,
  overlay: null,
  init() {
    this.el = document.getElementById('appSidebar');
    this.overlay = document.getElementById('sidebarOverlay');
    if (!this.el) return;
    // Restore collapsed state
    if (localStorage.getItem('sidebarCollapsed') === '1' && window.innerWidth > 768) {
      this.el.classList.add('collapsed');
    }
  },
  toggle() {
    if (!this.el) return;
    if (window.innerWidth <= 768) {
      this.el.classList.toggle('mobile-open');
      this.overlay?.classList.toggle('active', this.el.classList.contains('mobile-open'));
    } else {
      const collapsed = this.el.classList.toggle('collapsed');
      localStorage.setItem('sidebarCollapsed', collapsed ? '1' : '0');
    }
  },
  closeMobile() {
    this.el?.classList.remove('mobile-open');
    this.overlay?.classList.remove('active');
  }
};
window.Sidebar = Sidebar;

/* ============================================================
   TOAST NOTIFICATIONS
   ============================================================ */
const Toast = {
  container: null,
  icons: {
    success: 'fa-solid fa-circle-check',
    error:   'fa-solid fa-circle-xmark',
    warning: 'fa-solid fa-triangle-exclamation',
    info:    'fa-solid fa-circle-info'
  },
  _getContainer() {
    if (!this.container) {
      this.container = document.getElementById('toast-container');
      if (!this.container) {
        this.container = document.createElement('div');
        this.container.id = 'toast-container';
        document.body.appendChild(this.container);
      }
    }
    return this.container;
  },
  show(type, title, message = '', duration = 4000) {
    const container = this._getContainer();
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
      <div class="toast-icon"><i class="${this.icons[type] || this.icons.info}"></i></div>
      <div class="toast-body">
        <div class="toast-title">${title}</div>
        ${message ? `<div class="toast-msg">${message}</div>` : ''}
      </div>
      <button class="toast-close" onclick="this.closest('.toast').remove()">
        <i class="fa-solid fa-xmark"></i>
      </button>
    `;
    container.appendChild(toast);
    if (duration > 0) {
      setTimeout(() => {
        toast.classList.add('removing');
        setTimeout(() => toast.remove(), 250);
      }, duration);
    }
    return toast;
  },
  success(title, msg, dur) { return this.show('success', title, msg, dur); },
  error(title, msg, dur)   { return this.show('error', title, msg, dur); },
  warning(title, msg, dur) { return this.show('warning', title, msg, dur); },
  info(title, msg, dur)    { return this.show('info', title, msg, dur); }
};
window.Toast = Toast;

// Legacy wrapper used in existing PHP pages
function showToast(message, type = 'info', duration = 4000) {
  Toast.show(type, message, '', duration);
}
window.showToast = showToast;

/* ============================================================
   CONFIRM MODAL
   ============================================================ */
const Confirm = {
  show(options) {
    return new Promise((resolve) => {
      const {
        title = 'Confirmer',
        message = 'Êtes-vous sûr ?',
        confirmText = 'Confirmer',
        cancelText = 'Annuler',
        type = 'warning'
      } = options;

      const styles = {
        warning: { icon: 'fa-triangle-exclamation', bg: '#FEF3C7', color: '#D97706', btn: 'btn btn-primary' },
        danger:  { icon: 'fa-trash-can',            bg: '#FEE2E2', color: '#DC2626', btn: 'btn btn-danger' },
        info:    { icon: 'fa-circle-info',           bg: '#DBEAFE', color: '#2563EB', btn: 'btn btn-primary' }
      };
      const s = styles[type] || styles.warning;

      const overlay = document.createElement('div');
      overlay.className = 'modal-overlay';
      overlay.innerHTML = `
        <div class="modal" style="max-width:400px">
          <div class="modal-header">
            <div class="modal-icon" style="background:${s.bg};color:${s.color}">
              <i class="fa-solid ${s.icon}"></i>
            </div>
            <div>
              <div class="modal-title">${title}</div>
            </div>
          </div>
          <div class="modal-body">${message}</div>
          <div class="modal-footer">
            <button class="btn btn-secondary" id="confirmCancel">${cancelText}</button>
            <button class="${s.btn}" id="confirmOk">${confirmText}</button>
          </div>
        </div>
      `;

      document.body.appendChild(overlay);
      requestAnimationFrame(() => overlay.classList.add('open'));

      const close = (result) => {
        overlay.classList.remove('open');
        setTimeout(() => overlay.remove(), 200);
        resolve(result);
      };

      overlay.querySelector('#confirmOk').onclick = () => close(true);
      overlay.querySelector('#confirmCancel').onclick = () => close(false);
      overlay.addEventListener('click', e => { if (e.target === overlay) close(false); });
    });
  }
};
window.Confirm = Confirm;

/* ============================================================
   NOTIFICATIONS
   ============================================================ */
function toggleNotifs() {
  const panel = document.getElementById('notifPanel');
  if (!panel) return;
  const isOpen = panel.classList.toggle('open');
  if (isOpen) loadNotifs();
}

async function tfFetch(input, init) {
  document.body.classList.add('tf-fetching');
  try {
    return await fetch(input, init);
  } finally {
    document.body.classList.remove('tf-fetching');
  }
}

async function loadNotifs() {
  const list = document.getElementById('notifList');
  if (!list) return;
  try {
    const res = await tfFetch(tfUrl('/api/notifications.php?action=list'));
    const data = await res.json();
    if (!data.items?.length) {
      list.innerHTML = '<div style="padding:20px;text-align:center;color:var(--text3);font-size:13px"><i class="fa-regular fa-bell-slash" style="font-size:20px;opacity:0.4;display:block;margin-bottom:8px"></i>Aucune notification</div>';
      return;
    }
    list.innerHTML = data.items.map(n => `
      <div class="notif-item ${n.lu == 0 ? 'unread' : ''}" onclick="readNotif(${n.id}, '${escHtml(n.lien || '')}')">
        <div class="notif-item-title">${escHtml(n.titre || '')}</div>
        <div class="notif-item-msg">${escHtml(n.message || '')}</div>
        <div class="notif-item-time">${escHtml(n.created_at || '')}</div>
      </div>
    `).join('');
  } catch (e) {
    if (list) list.innerHTML = '<div style="padding:16px;text-align:center;color:var(--text3);font-size:13px">Erreur de chargement</div>';
  }
}

async function readNotif(id, lien) {
  await tfFetch(tfUrl(`/api/notifications.php?action=read&id=${id}`), { method: 'POST' });
  updateNotifBadge();
  if (lien) window.location = lien;
}

async function markAllRead() {
  await tfFetch(tfUrl('/api/notifications.php?action=read_all'), { method: 'POST' });
  document.getElementById('notifPanel')?.classList.remove('open');
  document.querySelector('.notif-badge')?.remove();
}

async function updateNotifBadge() {
  try {
    const res = await tfFetch(tfUrl('/api/notifications.php?action=count'));
    const data = await res.json();
    const badge = document.querySelector('.notif-badge');
    if (data.count > 0) {
      if (badge) badge.textContent = data.count;
    } else {
      badge?.remove();
    }
  } catch (e) {}
}

// Close notif panel on outside click
document.addEventListener('click', e => {
  const wrap = document.getElementById('notifWrap');
  if (wrap && !wrap.contains(e.target)) {
    document.getElementById('notifPanel')?.classList.remove('open');
  }
});

/* ============================================================
   RECHERCHE GLOBALE
   ============================================================ */
let _searchTimer;
document.addEventListener('DOMContentLoaded', () => {
  const searchInput = document.getElementById('globalSearch');
  const searchResults = document.getElementById('searchResults');
  if (!searchInput || !searchResults) return;

  searchInput.addEventListener('input', () => {
    clearTimeout(_searchTimer);
    const q = searchInput.value.trim();
    if (q.length < 2) { searchResults.classList.remove('visible'); return; }
    _searchTimer = setTimeout(() => _doSearch(q, searchResults), 300);
  });

  document.addEventListener('click', e => {
    if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
      searchResults.classList.remove('visible');
    }
  });

  // Ctrl+K focus
  document.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
      e.preventDefault();
      searchInput.focus();
      searchInput.select();
    }
  });
});

async function _doSearch(q, resultsEl) {
  try {
    const res = await tfFetch(tfUrl(`/api/tasks.php?action=search&q=${encodeURIComponent(q)}`));
    const data = await res.json();
    if (!data.results?.length) {
      resultsEl.innerHTML = '<div class="search-result-item" style="color:var(--text3);justify-content:center">Aucun résultat</div>';
    } else {
      resultsEl.innerHTML = data.results.map(t => `
        <div class="search-result-item" onclick="window.location='${tfUrl('/pages/tasks/view.php?id=' + t.id)}'">
          <i class="fa-solid fa-list-check" style="color:var(--primary);flex-shrink:0;font-size:12px"></i>
          <strong style="font-size:13px">${escHtml(t.titre)}</strong>
          <span class="badge" style="margin-left:auto;background:${t.statut_bg||'var(--border)'};color:${t.statut_color||'var(--text)'};">${escHtml(t.statut_label||'')}</span>
        </div>
      `).join('');
    }
    resultsEl.classList.add('visible');
  } catch (e) {}
}

/* ============================================================
   KANBAN DRAG & DROP
   ============================================================ */
function initKanban() {
  document.querySelectorAll('.kanban-cards').forEach(col => {
    if (typeof Sortable === 'undefined') return;
    Sortable.create(col, {
      group: 'kanban',
      animation: 150,
      ghostClass: 'kanban-ghost',
      dragClass: 'dragging',
      onEnd(evt) {
        const taskId = evt.item.dataset.id;
        const newStatus = evt.to.closest('.kanban-col')?.dataset.status;
        if (taskId && newStatus) updateTaskStatus(taskId, newStatus);
      }
    });
  });
}

async function updateTaskStatus(taskId, status) {
  const csrf = document.querySelector('meta[name=csrf]')?.content || '';
  try {
    const res = await tfFetch(tfUrl('/api/tasks.php'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
      body: JSON.stringify({ action: 'update_status', id: taskId, statut: status })
    });
    const data = await res.json();
    if (data.success) Toast.success('Statut mis à jour');
    else Toast.error('Erreur', data.message || 'Mise à jour échouée');
  } catch (e) {
    Toast.error('Erreur réseau');
  }
}

/* ============================================================
   DATEPICKERS
   ============================================================ */
document.addEventListener('DOMContentLoaded', () => {
  if (typeof flatpickr !== 'undefined') {
    flatpickr('.datepicker', { locale: 'fr', dateFormat: 'Y-m-d', allowInput: true });
    flatpickr('.datepicker-future', { locale: 'fr', dateFormat: 'Y-m-d', allowInput: true, minDate: new Date() });
    flatpickr('.datetimepicker', { locale: 'fr', enableTime: true, dateFormat: 'Y-m-d H:i' });
  }
  if (document.querySelector('.kanban-board')) initKanban();
});

/* ============================================================
   UPLOAD ZONE
   ============================================================ */
function initUploadZone(zoneId, inputId, listId) {
  const zone  = document.getElementById(zoneId);
  const input = document.getElementById(inputId);
  const list  = document.getElementById(listId);
  if (!zone || !input) return;

  function fileSig(f) {
    return f.name + '\0' + f.size + '\0' + f.lastModified;
  }

  /** Fusionne des fichiers glissés-déposés dans l’input (sinon le formulaire part sans fichiers). */
  function mergeIntoInput(newFiles) {
    const dt = new DataTransfer();
    const seen = new Set();
    function add(file) {
      const k = fileSig(file);
      if (seen.has(k)) return;
      seen.add(k);
      dt.items.add(file);
    }
    for (let i = 0; i < input.files.length; i++) add(input.files[i]);
    for (let i = 0; i < newFiles.length; i++) add(newFiles[i]);
    input.files = dt.files;
  }

  function renderFileList() {
    if (!list) return;
    list.innerHTML = '';
    Array.from(input.files || []).forEach(f => {
      const item = document.createElement('div');
      item.className = 'file-item';
      item.innerHTML = `
      <span class="file-icon"><i class="fa-solid fa-paperclip" style="color:var(--text3)"></i></span>
      <span class="file-name">${escHtml(f.name)}</span>
      <span class="file-size">${formatSize(f.size)}</span>
    `;
      list.appendChild(item);
    });
  }

  /* Clic : l’input en overlay reçoit le clic directement (évite display:none + .click() bloqué par le navigateur). */
  /* Glisser-déposer : capture sur la zone pour intercepter avant le comportement natif de l’input (remplacement des fichiers). */
  zone.addEventListener('dragover', e => {
    e.preventDefault();
    zone.classList.add('drag-over');
  }, true);
  zone.addEventListener('dragleave', e => {
    if (!e.relatedTarget || !zone.contains(e.relatedTarget)) {
      zone.classList.remove('drag-over');
    }
  });
  zone.addEventListener('drop', e => {
    e.preventDefault();
    e.stopPropagation();
    zone.classList.remove('drag-over');
    if (e.dataTransfer.files && e.dataTransfer.files.length) {
      mergeIntoInput(e.dataTransfer.files);
      renderFileList();
    }
  }, true);
  input.addEventListener('change', () => renderFileList());
}

function formatSize(bytes) {
  if (bytes < 1024) return bytes + ' o';
  if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' Ko';
  return (bytes / 1048576).toFixed(1) + ' Mo';
}

/* ============================================================
   MODAL
   ============================================================ */
function openModal(id) {
  document.getElementById(id)?.classList.add('open');
}
function closeModal(id) {
  document.getElementById(id)?.classList.remove('open');
}
window.openModal = openModal;
window.closeModal = closeModal;

document.addEventListener('click', e => {
  if (e.target.classList.contains('modal-overlay')) {
    e.target.classList.remove('open');
  }
});

/* ============================================================
   UTILITAIRES
   ============================================================ */
function escHtml(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;')
    .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
window.escHtml = escHtml;

const CHAT_USER_COLORS = ['#0086CD', '#7C3AED', '#059669', '#D97706', '#DC2626', '#0891B2', '#BE185D'];
function chatUserBg(userId) {
  return CHAT_USER_COLORS[Number(userId) % CHAT_USER_COLORS.length];
}

function appendTaskChatRow(container, m, currentUserId) {
  if (container.querySelector('[data-id="' + String(m.id) + '"]')) return;
  const empty = container.querySelector('.task-chat-empty');
  if (empty) empty.remove();
  const initials = (m.prenom || '').charAt(0) + (m.nom || '').charAt(0);
  const isMe = Number(m.user_id) === Number(currentUserId);
  const div = document.createElement('div');
  div.className = 'chat-msg' + (isMe ? ' chat-msg--me' : '');
  div.dataset.id = String(m.id);
  const bodyHtml = m.message_html || escHtml(m.message || '').replace(/\n/g, '<br>');
  div.innerHTML = `
    <div class="chat-msg-avatar">
      <div class="user-avatar" style="width:32px;height:32px;font-size:11px;background:${chatUserBg(m.user_id)}">${escHtml(initials.toUpperCase())}</div>
    </div>
    <div class="chat-msg-body">
      <div class="chat-msg-head">
        <strong>${escHtml((m.prenom || '') + ' ' + (m.nom || ''))}</strong>
        <span class="chat-msg-time">${escHtml(m.created_label || '')}</span>
      </div>
      <div class="chat-msg-text">${bodyHtml}</div>
    </div>`;
  container.appendChild(div);
  container.scrollTop = container.scrollHeight;
}

async function deleteTask(taskId, msg = 'Supprimer définitivement cette tâche ?') {
  const ok = await Confirm.show({
    title: 'Supprimer la tâche',
    message: msg,
    confirmText: 'Supprimer',
    type: 'danger'
  });
  if (!ok) return;
  const csrf = document.querySelector('meta[name=csrf]')?.content || '';
  try {
    const res = await tfFetch(tfUrl('/api/tasks.php'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
      body: JSON.stringify({ action: 'delete', id: Number(taskId) })
    });
    const data = await res.json();
    if (data.success) {
      Toast.success('Tâche supprimée');
      window.location.href = tfUrl('/pages/tasks/list.php');
    } else {
      Toast.error('Erreur', data.error || 'Suppression impossible');
    }
  } catch (e) {
    Toast.error('Erreur réseau');
  }
}
window.deleteTask = deleteTask;

function initTaskChat() {
  const box = document.getElementById('taskChatMessages');
  const form = document.getElementById('taskChatForm');
  const inp = document.getElementById('taskChatInput');
  const typingEl = document.getElementById('taskChatTyping');
  if (!box || !form || !inp) return;

  const taskId = parseInt(box.dataset.taskId, 10);
  let lastId = parseInt(box.dataset.lastId, 10) || 0;
  const currentUserId = parseInt(box.dataset.currentUser, 10);
  let pollAbort = null;
  let pollSeq = 0;
  let stopped = false;
  let lastTypingPost = 0;

  function scrollChat(smooth) {
    if (smooth) {
      box.scrollTo({ top: box.scrollHeight, behavior: 'smooth' });
    } else {
      box.scrollTop = box.scrollHeight;
    }
  }
  scrollChat(false);

  function applyTyping(typers) {
    if (!typingEl) return;
    const list = Array.isArray(typers) ? typers : [];
    const others = list.filter((t) => Number(t.user_id) !== Number(currentUserId));
    if (!others.length) {
      typingEl.textContent = '';
      typingEl.setAttribute('hidden', '');
      return;
    }
    typingEl.removeAttribute('hidden');
    const labels = others.map((t) => {
      const p = (t.prenom || '').trim();
      const n = (t.nom || '').trim();
      return n ? `${p} ${n.charAt(0)}.` : p;
    });
    let msg;
    if (labels.length === 1) msg = `${labels[0]} est en train d’écrire…`;
    else if (labels.length === 2) msg = `${labels[0]} et ${labels[1]} écrivent…`;
    else msg = `${labels.length} personnes écrivent…`;
    typingEl.textContent = msg;
  }

  function sendTypingPing() {
    const now = Date.now();
    if (now - lastTypingPost < 2000) return;
    lastTypingPost = now;
    const csrf = document.querySelector('meta[name=csrf]')?.content || '';
    fetch(tfUrl('/api/task_chat.php'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
      body: JSON.stringify({ action: 'typing', task_id: taskId }),
    }).catch(() => {});
  }

  async function pollOnce() {
    const visible = document.visibilityState === 'visible';
    const longPoll = visible;
    const timeout = visible ? 34 : 12;
    const url = tfUrl(`/api/task_chat.php?task_id=${taskId}&after_id=${lastId}&long_poll=${longPoll ? 1 : 0}&timeout=${timeout}`);
    pollAbort = new AbortController();
    const res = await fetch(url, { signal: pollAbort.signal });
    return res.json();
  }

  async function pollLoop() {
    const my = pollSeq;
    if (stopped || !document.getElementById('taskChatMessages')) return;
    try {
      const data = await pollOnce();
      if (my !== pollSeq) return;
      if (data.success && data.typing) applyTyping(data.typing);
      if (data.success && data.messages?.length) {
        let incomingFromOther = false;
        for (const m of data.messages) {
          if (Number(m.user_id) !== Number(currentUserId)) incomingFromOther = true;
          appendTaskChatRow(box, m, currentUserId);
          lastId = m.id;
        }
        box.dataset.lastId = String(lastId);
        scrollChat(incomingFromOther);
      }
    } catch (e) {
      if (my !== pollSeq) return;
      if (e.name !== 'AbortError') {
        await new Promise((r) => setTimeout(r, 500));
      }
    }
    if (stopped || my !== pollSeq) return;
    setTimeout(() => pollLoop(), 0);
  }

  document.addEventListener('visibilitychange', () => {
    pollSeq += 1;
    pollAbort?.abort();
    if (!stopped) setTimeout(() => pollLoop(), 0);
  });

  window.addEventListener('beforeunload', () => {
    stopped = true;
    pollAbort?.abort();
  });

  // Auto-resize task chat textarea
  if (inp.tagName === 'TEXTAREA') {
    inp.addEventListener('input', () => autoResizeTextarea(inp));
    autoResizeTextarea(inp);
  }

  inp.addEventListener('input', () => {
    if (inp.value.trim().length > 0) sendTypingPing();
  });
  inp.addEventListener('focus', () => {
    if (inp.value.trim().length > 0) sendTypingPing();
  });

  inp.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      form.requestSubmit();
    }
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const text = inp.value.trim();
    if (!text) return;
    const csrf = document.querySelector('meta[name=csrf]')?.content || '';
    inp.value = '';
    if (inp.tagName === 'TEXTAREA') autoResizeTextarea(inp);
    try {
      const res = await tfFetch(tfUrl('/api/task_chat.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
        body: JSON.stringify({ action: 'send', task_id: taskId, message: text }),
      });
      const data = await res.json();
      if (data.success && data.message) {
        appendTaskChatRow(box, data.message, currentUserId);
        lastId = data.message.id;
        box.dataset.lastId = String(lastId);
        scrollChat(false);
      } else {
        Toast.error('Chat', data.error || 'Envoi impossible');
        inp.value = text;
      }
    } catch (err) {
      Toast.error('Erreur réseau');
      inp.value = text;
    }
  });

  pollLoop();
}

function appendDmChatRow(container, m, currentUserId) {
  if (container.querySelector('[data-id="' + String(m.id) + '"]')) return;
  const empty = container.querySelector('.task-chat-empty');
  if (empty) empty.remove();
  const initials = (m.prenom || '').charAt(0) + (m.nom || '').charAt(0);
  const isMe = Number(m.sender_id) === Number(currentUserId);
  const div = document.createElement('div');
  div.className = 'chat-msg' + (isMe ? ' chat-msg--me' : '');
  div.dataset.id = String(m.id);
  const bodyHtml = m.body_html || escHtml(m.body || '').replace(/\n/g, '<br>');
  div.innerHTML = `
    <div class="chat-msg-avatar">
      <div class="user-avatar" style="width:32px;height:32px;font-size:11px;background:${chatUserBg(m.sender_id)}">${escHtml(initials.toUpperCase())}</div>
    </div>
    <div class="chat-msg-body">
      <div class="chat-msg-head">
        <strong>${escHtml((m.prenom || '') + ' ' + (m.nom || ''))}</strong>
        <span class="chat-msg-time">${escHtml(m.created_label || '')}</span>
      </div>
      <div class="chat-msg-text">${bodyHtml}</div>
    </div>`;
  container.appendChild(div);
  container.scrollTop = container.scrollHeight;
}

async function dmMarkReadRequest(threadId, lastMsgId) {
  if (!threadId || !lastMsgId) return;
  const csrf = document.querySelector('meta[name=csrf]')?.content || '';
  try {
    await fetch(tfUrl('/api/dm_chat.php'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
      body: JSON.stringify({
        action: 'read',
        thread_id: threadId,
        last_message_id: lastMsgId,
      }),
    });
  } catch (e) { /* ignore */ }
}

/* ── Auto-resize textarea ── */
function autoResizeTextarea(el) {
  el.style.height = 'auto';
  el.style.height = Math.min(el.scrollHeight, 140) + 'px';
}

function updateDmNavBadge(delta) {
  const badge = document.getElementById('dmNavBadge');
  if (!badge) return;
  const current = parseInt(badge.textContent, 10) || 0;
  const next = Math.max(0, current + delta);
  badge.textContent = next;
  badge.style.display = next > 0 ? '' : 'none';
}

function initDmChat() {
  const box = document.getElementById('dmChatMessages');
  const form = document.getElementById('dmChatForm');
  const inp = document.getElementById('dmChatInput');
  const typingEl = document.getElementById('dmChatTyping');
  if (!box || !form || !inp) return;

  // Auto-resize textarea
  inp.addEventListener('input', () => autoResizeTextarea(inp));
  autoResizeTextarea(inp);

  const threadId = parseInt(box.dataset.threadId, 10);
  const peerId = parseInt(box.dataset.peerId, 10);
  let lastId = parseInt(box.dataset.lastId, 10) || 0;
  const currentUserId = parseInt(box.dataset.currentUser, 10);
  let pollAbort = null;
  let pollSeq = 0;
  let stopped = false;
  let lastTypingPost = 0;

  function scrollDm(smooth) {
    if (smooth) box.scrollTo({ top: box.scrollHeight, behavior: 'smooth' });
    else box.scrollTop = box.scrollHeight;
  }
  scrollDm(false);

  function applyDmTyping(typers) {
    if (!typingEl) return;
    const list = Array.isArray(typers) ? typers : [];
    const others = list.filter((t) => Number(t.user_id) !== Number(currentUserId));
    if (!others.length) {
      typingEl.textContent = '';
      typingEl.setAttribute('hidden', '');
      return;
    }
    typingEl.removeAttribute('hidden');
    const labels = others.map((t) => {
      const p = (t.prenom || '').trim();
      const n = (t.nom || '').trim();
      return n ? `${p} ${n.charAt(0)}.` : p;
    });
    let msg;
    if (labels.length === 1) msg = `${labels[0]} est en train d’écrire…`;
    else if (labels.length === 2) msg = `${labels[0]} et ${labels[1]} écrivent…`;
    else msg = `${labels.length} personnes écrivent…`;
    typingEl.textContent = msg;
  }

  function sendDmTypingPing() {
    const now = Date.now();
    if (now - lastTypingPost < 2000) return;
    lastTypingPost = now;
    const csrf = document.querySelector('meta[name=csrf]')?.content || '';
    fetch(tfUrl('/api/dm_chat.php'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
      body: JSON.stringify({ action: 'typing', thread_id: threadId }),
    }).catch(() => {});
  }

  async function dmPollOnce() {
    const visible = document.visibilityState === 'visible';
    const longPoll = visible;
    const timeout = visible ? 34 : 12;
    const url = tfUrl(`/api/dm_chat.php?action=messages&thread_id=${threadId}&after_id=${lastId}&long_poll=${longPoll ? 1 : 0}&timeout=${timeout}`);
    pollAbort = new AbortController();
    const res = await fetch(url, { signal: pollAbort.signal });
    return res.json();
  }

  async function dmPollLoop() {
    const my = pollSeq;
    if (stopped || !document.getElementById('dmChatMessages')) return;
    try {
      const data = await dmPollOnce();
      if (my !== pollSeq) return;
      if (data.success && data.typing) applyDmTyping(data.typing);
      if (data.success && data.messages?.length) {
        let fromOther = false;
        for (const m of data.messages) {
          if (Number(m.sender_id) !== Number(currentUserId)) fromOther = true;
          appendDmChatRow(box, m, currentUserId);
          lastId = m.id;
        }
        box.dataset.lastId = String(lastId);
        scrollDm(fromOther);
        dmMarkReadRequest(threadId, lastId);
        // Clear nav badge — we just read the messages
        const badge = document.getElementById('dmNavBadge');
        if (badge) { badge.textContent = '0'; badge.style.display = 'none'; }
      }
    } catch (e) {
      if (my !== pollSeq) return;
      if (e.name !== 'AbortError') {
        await new Promise((r) => setTimeout(r, 500));
      }
    }
    if (stopped || my !== pollSeq) return;
    setTimeout(() => dmPollLoop(), 0);
  }

  document.addEventListener('visibilitychange', () => {
    pollSeq += 1;
    pollAbort?.abort();
    if (!stopped) setTimeout(() => dmPollLoop(), 0);
  });

  window.addEventListener('beforeunload', () => {
    stopped = true;
    pollAbort?.abort();
  });

  inp.addEventListener('input', () => {
    if (inp.value.trim().length > 0) sendDmTypingPing();
  });
  inp.addEventListener('focus', () => {
    if (inp.value.trim().length > 0) sendDmTypingPing();
  });

  inp.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      form.requestSubmit();
    }
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const text = inp.value.trim();
    if (!text) return;
    const csrf = document.querySelector('meta[name=csrf]')?.content || '';
    inp.value = '';
    autoResizeTextarea(inp);
    try {
      const res = await tfFetch(tfUrl('/api/dm_chat.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
        body: JSON.stringify({
          action: 'send',
          thread_id: threadId,
          peer_id: peerId,
          message: text,
        }),
      });
      const data = await res.json();
      if (data.success && data.message) {
        appendDmChatRow(box, data.message, currentUserId);
        lastId = data.message.id;
        box.dataset.lastId = String(lastId);
        scrollDm(false);
        dmMarkReadRequest(threadId, lastId);
      } else {
        Toast.error('Message', data.error || 'Envoi impossible');
        inp.value = text;
      }
    } catch (err) {
      Toast.error('Erreur réseau');
      inp.value = text;
    }
  });

  dmPollLoop();
}

function confirmDelete(url, msg = 'Confirmer la suppression ?') {
  Confirm.show({ title: 'Supprimer', message: msg, confirmText: 'Supprimer', type: 'danger' })
    .then(ok => { if (ok) window.location = url; });
}
window.confirmDelete = confirmDelete;

// Auto-dismiss flash after 5 seconds
setTimeout(() => {
  const flash = document.getElementById('flashMsg');
  if (flash) {
    flash.style.transition = 'opacity 0.4s ease';
    flash.style.opacity = '0';
    setTimeout(() => flash.remove(), 400);
  }
}, 5000);

/* ============================================================
   INIT
   ============================================================ */
document.addEventListener('DOMContentLoaded', () => {
  Sidebar.init();
  initTaskChat();
  initDmChat();
});
