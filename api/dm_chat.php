<?php
/**
 * API messagerie directe (long polling, « en train d’écrire », envoi).
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/dm.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('X-Accel-Buffering: no'); // Disable nginx/proxy buffering
requireLogin();

// Release session lock immediately — without this PHP holds the file lock
// for the full long-poll duration (up to 34 s), blocking every other page
// load from the same browser tab.
session_write_close();

$pdo    = getDB();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$me     = (int) currentUser()['id'];

$body = [];
$raw  = file_get_contents('php://input');
if ($raw !== '' && $raw !== false) {
    $body = json_decode($raw, true) ?? [];
}

function dmTypingList(PDO $pdo, int $threadId, int $excludeUserId): array {
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tf_dm_typing';
    if (!is_dir($dir)) {
        @mkdir($dir, 0770, true);
    }
    $file = $dir . DIRECTORY_SEPARATOR . 'thread_' . $threadId . '.json';
    if (!is_readable($file)) {
        return [];
    }
    $map = json_decode((string) file_get_contents($file), true);
    if (!is_array($map)) {
        return [];
    }
    $now = microtime(true);
    $ids = [];
    foreach ($map as $uid => $t) {
        $uid = (int) $uid;
        if ($uid === $excludeUserId || $uid < 1) {
            continue;
        }
        if (!is_numeric($t) || ($now - (float) $t) > 7) {
            continue;
        }
        $ids[] = $uid;
    }
    if ($ids === []) {
        return [];
    }
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT id, prenom, nom FROM users WHERE id IN ($in) AND actif = 1");
    $st->execute($ids);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[] = [
            'user_id' => (int) $r['id'],
            'prenom'  => (string) $r['prenom'],
            'nom'     => (string) $r['nom'],
        ];
    }

    return $out;
}

function dmTypingPing(int $threadId, int $userId): void {
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tf_dm_typing';
    if (!is_dir($dir)) {
        @mkdir($dir, 0770, true);
    }
    $file = $dir . DIRECTORY_SEPARATOR . 'thread_' . $threadId . '.json';
    $map  = [];
    if (is_readable($file)) {
        $old = json_decode((string) file_get_contents($file), true);
        if (is_array($old)) {
            $map = $old;
        }
    }
    $now = microtime(true);
    foreach ($map as $k => $t) {
        if (!is_numeric($t) || ($now - (float) $t) > 30) {
            unset($map[$k]);
        }
    }
    $map[(string) $userId] = $now;
    @file_put_contents($file, json_encode($map), LOCK_EX);
}

/**
 * @param list<array<string,mixed>> $rows
 */
function dmFormatMessages(array $rows): array {
    foreach ($rows as &$r) {
        $r['body_html']     = sanitize($r['body']);
        $r['created_label'] = formatDateTime($r['created_at']);
    }
    unset($r);

    return $rows;
}

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'messages';

    if ($action === 'threads') {
        echo json_encode(['success' => true, 'threads' => dmListThreadsForUser($pdo, $me)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action !== 'messages') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Action inconnue']);
        exit;
    }

    $threadId = (int) ($_GET['thread_id'] ?? 0);
    $afterId  = (int) ($_GET['after_id'] ?? 0);
    if (!$threadId || !dmUserInThread($pdo, $threadId, $me)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Accès refusé']);
        exit;
    }

    $longPoll = !empty($_GET['long_poll']) && $_GET['long_poll'] !== '0';
    $timeout  = min(50, max(5, (int) ($_GET['timeout'] ?? 28)));
    $sql      = 'SELECT m.id, m.thread_id, m.sender_id, m.body, m.created_at, u.prenom, u.nom
        FROM dm_messages m
        JOIN users u ON u.id = m.sender_id
        WHERE m.thread_id = ? AND m.id > ?
        ORDER BY m.id ASC
        LIMIT 100';
    $q = $pdo->prepare($sql);

    if ($longPoll) {
        if (function_exists('ignore_user_abort')) {
            ignore_user_abort(false);
        }
        @set_time_limit($timeout + 20);
        // Flush headers now so the browser knows the connection is alive
        while (ob_get_level()) ob_end_flush();
        flush();

        $until   = microtime(true) + $timeout;
        $sleep   = 50000;   // start at 50 ms
        $maxSleep = 350000; // cap at 350 ms
        while (microtime(true) < $until) {
            if (function_exists('connection_aborted') && connection_aborted()) {
                break;
            }
            $q->execute([$threadId, $afterId]);
            $rows = $q->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                echo json_encode([
                    'success'  => true,
                    'messages' => dmFormatMessages($rows),
                    'typing'   => dmTypingList($pdo, $threadId, $me),
                    'poll'     => 'long',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            usleep($sleep);
            // Adaptive backoff: grow sleep by 30 % each idle cycle, max 350 ms
            $sleep = min((int)($sleep * 1.3), $maxSleep);
        }
        echo json_encode([
            'success'   => true,
            'messages'  => [],
            'typing'    => dmTypingList($pdo, $threadId, $me),
            'poll'      => 'long',
            'heartbeat' => true,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $q->execute([$threadId, $afterId]);
    $rows = $q->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode([
        'success'  => true,
        'messages' => dmFormatMessages($rows),
        'typing'   => dmTypingList($pdo, $threadId, $me),
        'poll'     => 'short',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'POST') {
    $action = $body['action'] ?? '';
    $csrf   = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'CSRF invalide']);
        exit;
    }

    if ($action === 'typing') {
        $threadId = (int) ($body['thread_id'] ?? 0);
        if (!$threadId || !dmUserInThread($pdo, $threadId, $me)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Accès refusé']);
            exit;
        }
        dmTypingPing($threadId, $me);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'read') {
        $threadId  = (int) ($body['thread_id'] ?? 0);
        $lastMsgId = (int) ($body['last_message_id'] ?? 0);
        if (!$threadId || !dmUserInThread($pdo, $threadId, $me) || $lastMsgId < 1) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Paramètres invalides']);
            exit;
        }
        dmMarkRead($pdo, $threadId, $me, $lastMsgId);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action !== 'send') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Action invalide']);
        exit;
    }

    $threadId = (int) ($body['thread_id'] ?? 0);
    $peerId   = (int) ($body['peer_id'] ?? 0);
    $text     = trim((string) ($body['message'] ?? ''));

    if ($text === '') {
        echo json_encode(['success' => false, 'error' => 'Message vide']);
        exit;
    }
    if (mb_strlen($text) > 4000) {
        echo json_encode(['success' => false, 'error' => 'Message trop long (4000 caractères max)']);
        exit;
    }

    if ($threadId < 1 && $peerId > 0) {
        // Validate peer exists and is active BEFORE creating thread
        $chk = $pdo->prepare('SELECT 1 FROM users WHERE id = ? AND actif = 1');
        $chk->execute([$peerId]);
        if (!$chk->fetchColumn()) {
            echo json_encode(['success' => false, 'error' => 'Utilisateur introuvable']);
            exit;
        }
        try {
            $threadId = dmEnsureThread($pdo, $me, $peerId);
        } catch (InvalidArgumentException $e) {
            echo json_encode(['success' => false, 'error' => 'Destinataire invalide']);
            exit;
        }
    }

    if ($threadId < 1 || !dmUserInThread($pdo, $threadId, $me)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Accès refusé']);
        exit;
    }

    $pdo->prepare('INSERT INTO dm_messages (thread_id, sender_id, body) VALUES (?, ?, ?)')
        ->execute([$threadId, $me, $text]);
    $newId = (int) $pdo->lastInsertId();
    $pdo->prepare('UPDATE dm_threads SET last_message_at = NOW() WHERE id = ?')->execute([$threadId]);

    $u = currentUser();
    $peerStmt = $pdo->prepare('SELECT user_low_id, user_high_id FROM dm_threads WHERE id = ?');
    $peerStmt->execute([$threadId]);
    $trow = $peerStmt->fetch(PDO::FETCH_ASSOC);
    $recipient = $trow ? dmPeerId($trow, $me) : 0;
    if ($recipient > 0) {
        $snippet = mb_strlen($text) > 100 ? mb_substr($text, 0, 97) . '…' : $text;
        createNotification(
            $recipient,
            'dm',
            'Nouveau message',
            sanitize($u['prenom'] . ' ' . $u['nom']) . ' : ' . sanitize($snippet),
            APP_URL . '/pages/messages/index.php?with=' . $me
        );
    }

    $rowStmt = $pdo->prepare(
        'SELECT m.id, m.thread_id, m.sender_id, m.body, m.created_at, u.prenom, u.nom
         FROM dm_messages m JOIN users u ON u.id = m.sender_id WHERE m.id = ?'
    );
    $rowStmt->execute([$newId]);
    $row = $rowStmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'   => true,
        'thread_id' => $threadId,
        'message'   => [
            'id'            => (int) ($row['id'] ?? $newId),
            'thread_id'     => (int) ($row['thread_id'] ?? $threadId),
            'sender_id'     => (int) ($row['sender_id'] ?? $me),
            'body'          => (string) ($row['body'] ?? $text),
            'body_html'     => sanitize((string) ($row['body'] ?? $text)),
            'created_at'    => (string) ($row['created_at'] ?? ''),
            'created_label' => formatDateTime((string) ($row['created_at'] ?? date('Y-m-d H:i:s'))),
            'prenom'        => (string) ($row['prenom'] ?? $u['prenom']),
            'nom'           => (string) ($row['nom'] ?? $u['nom']),
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Méthode non supportée']);
