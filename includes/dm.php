<?php
/**
 * Messagerie directe — fil de discussion entre deux utilisateurs.
 */

/** Crée le fil s’il n’existe pas (user_low = min, user_high = max). */
function dmEnsureThread(PDO $pdo, int $userId, int $peerId): int {
    if ($peerId < 1 || $userId === $peerId) {
        throw new InvalidArgumentException('Paire de discussion invalide.');
    }
    $low  = min($userId, $peerId);
    $high = max($userId, $peerId);
    $st = $pdo->prepare('SELECT id FROM dm_threads WHERE user_low_id = ? AND user_high_id = ?');
    $st->execute([$low, $high]);
    $existing = $st->fetchColumn();
    if ($existing) {
        return (int) $existing;
    }
    $pdo->prepare('INSERT INTO dm_threads (user_low_id, user_high_id) VALUES (?, ?)')->execute([$low, $high]);

    return (int) $pdo->lastInsertId();
}

function dmPeerId(array $threadRow, int $me): int {
    $low  = (int) $threadRow['user_low_id'];
    $high = (int) $threadRow['user_high_id'];

    return $me === $low ? $high : $low;
}

/** L’utilisateur participe-t-il à ce fil ? */
function dmUserInThread(PDO $pdo, int $threadId, int $userId): bool {
    $st = $pdo->prepare('SELECT 1 FROM dm_threads WHERE id = ? AND (user_low_id = ? OR user_high_id = ?)');
    $st->execute([$threadId, $userId, $userId]);

    return (bool) $st->fetchColumn();
}

/**
 * Liste des conversations pour la barre latérale.
 *
 * @return list<array<string,mixed>>
 */
function dmListThreadsForUser(PDO $pdo, int $userId): array {
    $sql = "SELECT t.id AS thread_id,
        CASE WHEN t.user_low_id = ? THEN t.user_high_id ELSE t.user_low_id END AS peer_id,
        u.prenom, u.nom, u.role,
        (SELECT body FROM dm_messages WHERE thread_id = t.id ORDER BY id DESC LIMIT 1) AS last_body,
        (SELECT created_at FROM dm_messages WHERE thread_id = t.id ORDER BY id DESC LIMIT 1) AS last_at,
        (SELECT COUNT(*) FROM dm_messages m
            WHERE m.thread_id = t.id AND m.sender_id != ?
            AND m.id > COALESCE(
                (SELECT last_read_message_id FROM dm_thread_reads r
                 WHERE r.thread_id = t.id AND r.user_id = ? LIMIT 1),
                0
            )
        ) AS unread
    FROM dm_threads t
    INNER JOIN users u ON u.id = CASE WHEN t.user_low_id = ? THEN t.user_high_id ELSE t.user_low_id END
    WHERE (t.user_low_id = ? OR t.user_high_id = ?) AND u.actif = 1
    ORDER BY (t.last_message_at IS NULL) DESC, t.last_message_at DESC, t.id DESC";

    $st = $pdo->prepare($sql);
    $st->execute([$userId, $userId, $userId, $userId, $userId, $userId]);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** Marque les messages jusqu’à lastMsgId comme lus pour cet utilisateur sur ce fil. */
function dmMarkRead(PDO $pdo, int $threadId, int $userId, int $lastMsgId): void {
    $sql = "INSERT INTO dm_thread_reads (thread_id, user_id, last_read_message_id) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE last_read_message_id = GREATEST(last_read_message_id, ?)";
    $pdo->prepare($sql)->execute([$threadId, $userId, $lastMsgId, $lastMsgId]);
}

/** Nombre total de messages non lus pour un utilisateur, tous fils confondus. */
function dmGetTotalUnread(PDO $pdo, int $userId): int {
    $sql = "SELECT COALESCE(SUM((SELECT COUNT(*) FROM dm_messages m WHERE m.thread_id = t.id AND m.sender_id != ? AND m.id > COALESCE((SELECT last_read_message_id FROM dm_thread_reads r WHERE r.thread_id = t.id AND r.user_id = ? LIMIT 1), 0))), 0) FROM dm_threads t WHERE t.user_low_id = ? OR t.user_high_id = ?";
    $st = $pdo->prepare($sql);
    $st->execute([$userId, $userId, $userId, $userId]);
    return (int) $st->fetchColumn();
}
