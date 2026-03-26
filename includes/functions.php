<?php
// ============================================================
//  TaskFlow — Fonctions utilitaires
// ============================================================

function getUserColor(int $id): string {
    $colors = ['#0086CD', '#7C3AED', '#059669', '#D97706', '#DC2626', '#0891B2', '#BE185D'];
    return $colors[$id % count($colors)];
}

// ---------- Tâches ----------
function getTaskStatusBadge(string $statut): string {
    $statuses = TASK_STATUSES;
    if (!isset($statuses[$statut])) $statut = 'pas_fait';
    $s = $statuses[$statut];
    return '<span class="badge" style="background:'.$s['bg'].';color:'.$s['color'].';border:1px solid '.$s['color'].'40">'
         . sanitize($s['label']) . '</span>';
}

function getPriorityBadge(string $priorite): string {
    $priorities = TASK_PRIORITIES;
    if (!isset($priorities[$priorite])) $priorite = 'normale';
    $p = $priorities[$priorite];
    $icons = ['basse'=>'↓','normale'=>'→','haute'=>'↑','urgente'=>'⚡'];
    return '<span class="priority-badge priority-'.$priorite.'">'
         . ($icons[$priorite]??'') . ' ' . sanitize($p['label']) . '</span>';
}

function getTaskRowClass(array $task): string {
    $statut = computeRealStatus($task);
    $map = [
        'pas_fait'   => 'row-pasfait',
        'en_cours'   => 'row-encours',
        'en_attente' => 'row-attente',
        'termine'    => 'row-termine',
        'annule'     => 'row-annule',
        'en_retard'  => 'row-retard',
        'rejete'     => 'row-rejete',
    ];
    return $map[$statut] ?? '';
}

// Calcule le statut réel (ajoute en_retard automatiquement)
function computeRealStatus(array $task): string {
    if (in_array($task['statut'], ['termine','annule','rejete'])) return $task['statut'];
    if (!empty($task['date_echeance']) && strtotime($task['date_echeance']) < time()) {
        return 'en_retard';
    }
    return $task['statut'];
}

// ---------- Date helpers ----------
function formatDate(string $date, string $format = 'd/m/Y'): string {
    if (empty($date) || $date === '0000-00-00') return '—';
    return date($format, strtotime($date));
}

function formatDateTime(string $dt): string {
    if (empty($dt)) return '—';
    return date('d/m/Y H:i', strtotime($dt));
}

function daysUntil(string $date): int {
    return (int) ceil((strtotime($date) - time()) / 86400);
}

function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return 'à l\'instant';
    if ($diff < 3600)   return floor($diff/60) . ' min';
    if ($diff < 86400)  return floor($diff/3600) . 'h';
    if ($diff < 604800) return floor($diff/86400) . 'j';
    return formatDate($datetime);
}

// ---------- Pagination ----------
function paginate(int $total, int $page, int $perPage = ITEMS_PER_PAGE): array {
    $totalPages = max(1, ceil($total / $perPage));
    $page = max(1, min($page, $totalPages));
    return [
        'total'       => $total,
        'per_page'    => $perPage,
        'current'     => $page,
        'total_pages' => $totalPages,
        'offset'      => ($page - 1) * $perPage,
        'has_prev'    => $page > 1,
        'has_next'    => $page < $totalPages,
    ];
}

function renderPagination(array $pag, string $url): string {
    if ($pag['total_pages'] <= 1) return '';
    $html = '<div class="pagination">';
    if ($pag['has_prev'])
        $html .= '<a href="'.$url.'&page='.($pag['current']-1).'" class="page-btn">‹ Précédent</a>';
    for ($i = max(1,$pag['current']-2); $i <= min($pag['total_pages'],$pag['current']+2); $i++) {
        $active = $i == $pag['current'] ? ' active' : '';
        $html .= '<a href="'.$url.'&page='.$i.'" class="page-btn'.$active.'">'.$i.'</a>';
    }
    if ($pag['has_next'])
        $html .= '<a href="'.$url.'&page='.($pag['current']+1).'" class="page-btn">Suivant ›</a>';
    $html .= '</div>';
    return $html;
}

// ---------- Upload de fichiers ----------
/**
 * Normalise $_FILES['fichiers'] (tableau ou fichier unique selon PHP / navigateur).
 *
 * @return list<array{name:string,tmp_name:string,size:int|float,error:int}>
 */
function collectUploadedFiles(string $field = 'fichiers'): array {
    if (empty($_FILES[$field]) || !isset($_FILES[$field]['name'])) {
        return [];
    }
    $names = $_FILES[$field]['name'];
    if (!is_array($names)) {
        if ($names === '' || $names === null) {
            return [];
        }
        return [[
            'name'     => $names,
            'tmp_name' => $_FILES[$field]['tmp_name'],
            'size'     => $_FILES[$field]['size'],
            'error'    => (int) $_FILES[$field]['error'],
        ]];
    }
    $out = [];
    foreach ($names as $i => $name) {
        if ($name === '' || $name === null) {
            continue;
        }
        $out[] = [
            'name'     => $name,
            'tmp_name' => $_FILES[$field]['tmp_name'][$i],
            'size'     => $_FILES[$field]['size'][$i],
            'error'    => (int) $_FILES[$field]['error'][$i],
        ];
    }
    return $out;
}

function detectMimeType(string $path): string {
    $mime = 'application/octet-stream';
    if (function_exists('mime_content_type')) {
        $t = @mime_content_type($path);
        if ($t !== false && $t !== '') {
            return $t;
        }
    }
    if (function_exists('finfo_open')) {
        $f = @finfo_open(FILEINFO_MIME_TYPE);
        if ($f) {
            $t = finfo_file($f, $path);
            finfo_close($f);
            if ($t !== false && $t !== '') {
                return $t;
            }
        }
    }
    return $mime;
}

function handleFileUpload(array $file, int $taskId): ?array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext === '' || !in_array($ext, UPLOAD_ALLOWED, true)) {
        return null;
    }
    if ((int) $file['size'] > UPLOAD_MAX_SIZE) {
        return null;
    }

    $dir = UPLOAD_DIR . 'tasks/' . $taskId . '/';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        error_log('TaskFlow: impossible de créer le dossier uploads: ' . $dir);
        return null;
    }
    if (!is_writable($dir)) {
        error_log('TaskFlow: dossier uploads non inscriptible: ' . $dir);
        return null;
    }

    $newName = uniqid('file_', true) . '.' . $ext;
    $dest    = $dir . $newName;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return null;
    }
    return [
        'nom_original' => $file['name'],
        'chemin'       => 'tasks/' . $taskId . '/' . $newName,
        'taille'       => (int) $file['size'],
        'mime'         => detectMimeType($dest),
    ];
}

/** Supprime une pièce jointe si elle appartient à la tâche. Retourne le nom d’origine ou null. */
function deleteTaskFile(int $fileId, int $taskId): ?string {
    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT chemin, nom_original FROM fichiers WHERE id = ? AND tache_id = ?');
    $stmt->execute([$fileId, $taskId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    $abs = UPLOAD_DIR . $row['chemin'];
    if (is_file($abs)) {
        @unlink($abs);
    }
    $pdo->prepare('DELETE FROM fichiers WHERE id = ?')->execute([$fileId]);
    return $row['nom_original'];
}

// ---------- Notifications ----------
function createNotification(int $userId, string $type, string $titre, string $message, string $lien = ''): void {
    $pdo = getDB();
    $pdo->prepare("INSERT INTO notifications (user_id,type,titre,message,lien) VALUES (?,?,?,?,?)")
        ->execute([$userId, $type, $titre, $message, $lien]);

    if (!defined('MAIL_NOTIFICATIONS') || !MAIL_NOTIFICATIONS) {
        return;
    }

    $stmt = $pdo->prepare('SELECT email, prenom, nom, notify_email FROM users WHERE id = ? AND actif = 1');
    $stmt->execute([$userId]);
    $u = $stmt->fetch();
    if (!$u || empty($u['email'])) {
        return;
    }
    $wantMail = array_key_exists('notify_email', $u) ? (int) $u['notify_email'] : 1;
    if ($wantMail !== 1) {
        return;
    }

    require_once __DIR__ . '/mail.php';
    $subject = '[' . APP_NAME . '] ' . $titre;
    $greet   = 'Bonjour ' . $u['prenom'] . ',';
    $safeMsg = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $linkH   = '';
    $linkP   = '';
    if ($lien !== '') {
        $linkH = '<p style="margin-top:16px"><a href="' . htmlspecialchars($lien, ENT_QUOTES, 'UTF-8') . '" style="color:#0086CD">Ouvrir dans TaskFlow</a></p>';
        $linkP = "\n\n" . $lien;
    }
    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:system-ui,Segoe UI,sans-serif;font-size:15px;line-height:1.5;color:#0f172a">';
    $html .= '<p>' . htmlspecialchars($greet, ENT_QUOTES, 'UTF-8') . '</p><p>' . nl2br($safeMsg) . '</p>' . $linkH;
    $html .= '<p style="margin-top:24px;font-size:12px;color:#64748b">— ' . htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') . '</p></body></html>';
    $plain = $greet . "\n\n" . $message . $linkP . "\n\n— " . APP_NAME;

    sendTaskflowMail($u['email'], trim($u['prenom'] . ' ' . $u['nom']), $subject, $plain, $html);
}

/**
 * Notifications supplémentaires pour @Prénom Nom dans le texte (parmi les assignés).
 */
function notifyCommentMentions(int $taskId, string $taskTitle, array $assignesRows, string $contenu, array $author): void {
    $contenuLower = mb_strtolower($contenu);
    foreach ($assignesRows as $a) {
        if ((int) $a['id'] === (int) $author['id']) {
            continue;
        }
        $needle = mb_strtolower('@' . trim($a['prenom']) . ' ' . trim($a['nom']));
        if ($needle === '@' || mb_strpos($contenuLower, $needle) === false) {
            continue;
        }
        $label = sanitize($author['prenom']) . ' vous a mentionné sur : ' . sanitize($taskTitle);
        createNotification(
            (int) $a['id'],
            'mention',
            'Mention dans un commentaire',
            $label,
            APP_URL . '/pages/tasks/view.php?id=' . $taskId
        );
    }
}

function getUnreadNotificationsCount(int $userId): int {
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND lu=0");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

function getUserNotifications(int $userId, int $limit = 10): array {
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT ?");
    $stmt->execute([$userId, $limit]);
    return $stmt->fetchAll();
}

// ---------- Stats dashboard ----------
function getDashboardStats(array $user): array {
    $pdo = getDB();
    $params = [];

    // Calcule le statut réel via SQL : détecte en_retard dynamiquement
    $realStatut = "CASE
        WHEN t.statut IN ('termine','annule','rejete') THEN t.statut
        WHEN t.date_echeance < CURDATE() THEN 'en_retard'
        ELSE t.statut
    END";

    if ($user['role'] === 'employe') {
        $params[] = $user['id'];
        $sql = "SELECT ($realStatut) AS real_statut, COUNT(*) as n
                FROM taches t
                JOIN taches_assignees ta ON ta.tache_id = t.id
                WHERE ta.user_id = ?
                GROUP BY real_statut";
    } elseif (in_array($user['role'], ['superviseur','chef_dept'])) {
        if (empty($user['departement_id'])) {
            return $stats; // Aucun département : stats vides
        }
        $params[] = $user['departement_id'];
        $sql = "SELECT ($realStatut) AS real_statut, COUNT(*) as n
                FROM taches t
                WHERE t.departement_id = ?
                GROUP BY real_statut";
    } else {
        $sql = "SELECT ($realStatut) AS real_statut, COUNT(*) as n
                FROM taches t
                GROUP BY real_statut";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $stats = ['total'=>0,'pas_fait'=>0,'en_cours'=>0,'en_attente'=>0,'termine'=>0,'annule'=>0,'en_retard'=>0,'rejete'=>0];
    foreach ($rows as $r) {
        $key = $r['real_statut'];
        $stats[$key] = ($stats[$key] ?? 0) + $r['n'];
        $stats['total'] += $r['n'];
    }
    return $stats;
}

// ---------- Taille fichier ----------
function formatSize(int $bytes): string {
    if ($bytes < 1024) return $bytes . ' o';
    if ($bytes < 1048576) return number_format($bytes / 1024, 1) . ' Ko';
    return number_format($bytes / 1048576, 1) . ' Mo';
}

// ---------- Historique ----------
function logChange(int $taskId, int $userId, string $champ, string $old, string $new, string $action = 'modification'): void {
    $pdo = getDB();
    $pdo->prepare("INSERT INTO historique (tache_id,user_id,champ,ancienne_val,nouvelle_val,action) VALUES (?,?,?,?,?,?)")
        ->execute([$taskId, $userId, $champ, $old, $new, $action]);
}
