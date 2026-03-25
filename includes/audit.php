<?php
/**
 * Journal d’audit (actions sensibles).
 */
function logAudit(int $actorUserId, string $action, string $entityType, ?int $entityId, string $details = ''): void {
    try {
        $pdo = getDB();
        $pdo->prepare('INSERT INTO audit_log (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?,?,?,?,?,?)')
            ->execute([
                $actorUserId,
                $action,
                $entityType,
                $entityId,
                mb_substr($details, 0, 2000),
                $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
    } catch (Throwable $e) {
        error_log('TaskFlow audit: ' . $e->getMessage());
    }
}
