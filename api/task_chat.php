<?php
/**
 * Chat quasi instantané : long polling (type messagerie) + indicateur « en train d'écrire ».
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('X-Accel-Buffering: no');
requireLogin();

// Release session lock so other browser requests aren't blocked during the poll
session_write_close();

$pdo = getDB();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$body = [];
$raw = file_get_contents('php://input');
if ($raw !== '' && $raw !== false) {
    $body = json_decode($raw, true) ?? [];
}

/**
 * Activité clavier (fichier temporaire, sans table dédiée).
 *
 * @return list<array{prenom:string,nom:string,user_id:int}>
 */
function taskChatTypingList(PDO $pdo, int $taskId, int $excludeUserId): array {
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tf_chat_typing';
    if (!is_dir($dir)) {
        @mkdir($dir, 0770, true);
    }
    $file = $dir . DIRECTORY_SEPARATOR . 'task_' . $taskId . '.json';
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

function taskChatTypingPing(int $taskId, int $userId): void {
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tf_chat_typing';
    if (!is_dir($dir)) {
        @mkdir($dir, 0770, true);
    }
    $file = $dir . DIRECTORY_SEPARATOR . 'task_' . $taskId . '.json';
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
 *
 * @return list<array<string,mixed>>
 */
function taskChatFormatRows(array $rows): array {
    foreach ($rows as &$r) {
        $r['message_html']  = sanitize($r['message']);
        $r['created_label'] = formatDateTime($r['created_at']);
    }
    unset($r);
    return $rows;
}

if ($method === 'GET') {
    $taskId  = (int) ($_GET['task_id'] ?? 0);
    $afterId = (int) ($_GET['after_id'] ?? 0);
    if (!$taskId) {
        echo json_encode(['success' => false, 'error' => 'task_id requis']);
        exit;
    }
    $stmt = $pdo->prepare('SELECT * FROM taches WHERE id=?');
    $stmt->execute([$taskId]);
    $task = $stmt->fetch();
    if (!$task || !canViewTask($task)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Accès refusé']);
        exit;
    }

    $uid       = (int) currentUser()['id'];
    $longPoll  = !empty($_GET['long_poll']) && $_GET['long_poll'] !== '0';
    $timeout   = min(50, max(5, (int) ($_GET['timeout'] ?? 28)));
    $sqlSelect = 'SELECT m.id, m.user_id, m.message, m.created_at, u.prenom, u.nom
         FROM tache_chat_messages m
         JOIN users u ON u.id = m.user_id
         WHERE m.tache_id = ? AND m.id > ?
         ORDER BY m.id ASC
         LIMIT 100';
    $q = $pdo->prepare($sqlSelect);

    if ($longPoll) {
        if (function_exists('ignore_user_abort')) {
            ignore_user_abort(false);
        }
        @set_time_limit($timeout + 20);
        while (ob_get_level()) ob_end_flush();
        flush();

        $until    = microtime(true) + $timeout;
        $sleep    = 50000;
        $maxSleep = 350000;
        while (microtime(true) < $until) {
            if (function_exists('connection_aborted') && connection_aborted()) {
                break;
            }
            $q->execute([$taskId, $afterId]);
            $rows = $q->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                echo json_encode([
                    'success'  => true,
                    'messages' => taskChatFormatRows($rows),
                    'typing'   => taskChatTypingList($pdo, $taskId, $uid),
                    'poll'     => 'long',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            usleep($sleep);
            $sleep = min((int)($sleep * 1.3), $maxSleep);
        }
        echo json_encode([
            'success'   => true,
            'messages'  => [],
            'typing'    => taskChatTypingList($pdo, $taskId, $uid),
            'poll'      => 'long',
            'heartbeat' => true,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $q->execute([$taskId, $afterId]);
    $rows = $q->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode([
        'success'  => true,
        'messages' => taskChatFormatRows($rows),
        'typing'   => taskChatTypingList($pdo, $taskId, $uid),
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
        $taskId = (int) ($body['task_id'] ?? 0);
        if (!$taskId) {
            echo json_encode(['success' => false, 'error' => 'task_id requis']);
            exit;
        }
        $stmt = $pdo->prepare('SELECT * FROM taches WHERE id=?');
        $stmt->execute([$taskId]);
        $task = $stmt->fetch();
        if (!$task || !canViewTask($task)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Accès refusé']);
            exit;
        }
        taskChatTypingPing($taskId, (int) currentUser()['id']);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action !== 'send') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Action invalide']);
        exit;
    }

    $taskId = (int) ($body['task_id'] ?? 0);
    $text   = trim((string) ($body['message'] ?? ''));
    if (!$taskId || $text === '') {
        echo json_encode(['success' => false, 'error' => 'Message vide']);
        exit;
    }
    if (mb_strlen($text) > 2000) {
        echo json_encode(['success' => false, 'error' => 'Message trop long (2000 caractères max)']);
        exit;
    }
    $stmt = $pdo->prepare('SELECT * FROM taches WHERE id=?');
    $stmt->execute([$taskId]);
    $task = $stmt->fetch();
    if (!$task || !canViewTask($task)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Accès refusé']);
        exit;
    }
    $uid = (int) currentUser()['id'];
    $pdo->prepare('INSERT INTO tache_chat_messages (tache_id, user_id, message) VALUES (?,?,?)')
        ->execute([$taskId, $uid, $text]);

    $newId   = (int) $pdo->lastInsertId();
    $u       = currentUser();
    $snippet = mb_strlen($text) > 120 ? mb_substr($text, 0, 117) . '…' : $text;

    $assignStmt = $pdo->prepare('SELECT user_id FROM taches_assignees WHERE tache_id = ?');
    $assignStmt->execute([$taskId]);
    $notifyIds = [];
    foreach (array_map('intval', $assignStmt->fetchAll(PDO::FETCH_COLUMN)) as $aid) {
        if ($aid !== $uid) {
            $notifyIds[$aid] = true;
        }
    }
    $creatorId = (int) ($task['createur_id'] ?? 0);
    if ($creatorId > 0 && $creatorId !== $uid) {
        $notifyIds[$creatorId] = true;
    }
    foreach (array_keys($notifyIds) as $nid) {
        createNotification(
            (int) $nid,
            'chat',
            'Message sur une tâche',
            sanitize($u['prenom']) . ' : ' . sanitize($snippet),
            APP_URL . '/pages/tasks/view.php?id=' . $taskId . '#task-chat'
        );
    }

    $rowStmt = $pdo->prepare(
        'SELECT m.id, m.user_id, m.message, m.created_at, u.prenom, u.nom
         FROM tache_chat_messages m JOIN users u ON u.id = m.user_id WHERE m.id = ?'
    );
    $rowStmt->execute([$newId]);
    $row = $rowStmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'message' => [
            'id'            => (int) ($row['id'] ?? $newId),
            'user_id'       => (int) ($row['user_id'] ?? $uid),
            'message'       => (string) ($row['message'] ?? $text),
            'message_html'  => sanitize((string) ($row['message'] ?? $text)),
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
