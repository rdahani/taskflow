<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
requireLogin();
session_write_close();

$pdo    = getDB();
$user   = currentUser();
$action = $_GET['action'] ?? 'list';

switch ($action) {
    case 'list':
        $notifs = getUserNotifications($user['id'], 15);
        foreach ($notifs as &$n) {
            $n['created_at'] = timeAgo($n['created_at']);
        }
        echo json_encode(['items' => $notifs]);
        break;

    case 'count':
        echo json_encode(['count' => getUnreadNotificationsCount($user['id'])]);
        break;

    case 'read':
        $id = (int)($_GET['id'] ?? 0);
        if ($id) {
            $pdo->prepare("UPDATE notifications SET lu=1 WHERE id=? AND user_id=?")
                ->execute([$id, $user['id']]);
        }
        echo json_encode(['success' => true]);
        break;

    case 'read_all':
        $pdo->prepare("UPDATE notifications SET lu=1 WHERE user_id=?")
            ->execute([$user['id']]);
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['error' => 'Action inconnue']);
}
