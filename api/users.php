<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
requireLogin();

$pdo    = getDB();
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $body['action'] ?? ($_GET['action'] ?? '');

// Vérification CSRF pour POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($body['csrf_token'] ?? '');
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
        echo json_encode(['success'=>false,'error'=>'CSRF invalide']); exit;
    }
}

switch ($action) {
    case 'toggle':
        if (!isAdmin()) { echo json_encode(['success'=>false,'error'=>'Accès refusé']); break; }
        $id    = (int)($body['id'] ?? 0);
        $actif = (int)($body['actif'] ?? 0);
        $pdo->prepare("UPDATE users SET actif=? WHERE id=?")->execute([$actif, $id]);
        require_once __DIR__ . '/../includes/audit.php';
        logAudit((int) currentUser()['id'], $actif ? 'user_activate' : 'user_deactivate', 'user', $id, '');
        echo json_encode(['success'=>true]);
        break;

    case 'update_role':
        if (!isAdmin()) { echo json_encode(['success'=>false,'error'=>'Accès refusé']); break; }
        $id   = (int)($body['id'] ?? 0);
        $role = trim($body['role'] ?? '');
        if ($id < 1 || !array_key_exists($role, ROLES)) {
            echo json_encode(['success'=>false,'error'=>'Données invalides']); break;
        }
        // Interdire de se rétrograder soi-même
        if ($id === (int)($_SESSION['user_id'] ?? 0)) {
            echo json_encode(['success'=>false,'error'=>'Impossible de modifier son propre rôle']); break;
        }
        $pdo->prepare("UPDATE users SET role=? WHERE id=?")->execute([$role, $id]);
        require_once __DIR__ . '/../includes/audit.php';
        logAudit((int) currentUser()['id'], 'user_update_role', 'user', $id, 'role:'.$role);
        echo json_encode(['success'=>true]);
        break;

    case 'list_assignable':
        // Liste des utilisateurs assignables (pour AJAX) — restreint aux superviseurs+
        if (!isSuperviseur()) {
            echo json_encode(['success'=>false,'error'=>'Accès refusé']); break;
        }
        $q    = trim($_GET['q'] ?? '');
        $dept = (int)($_GET['dept'] ?? 0);
        $where = ['actif=1'];
        $params = [];
        if ($q)    { $ql = '%' . escapeLike($q) . '%'; $where[] = '(nom LIKE ? OR prenom LIKE ?)'; $params[]=$ql; $params[]=$ql; }
        if ($dept) { $where[] = 'departement_id=?'; $params[]=$dept; }
        $stmt = $pdo->prepare("SELECT id,nom,prenom,role,departement_id FROM users WHERE ".implode(' AND ',$where)." ORDER BY nom LIMIT 20");
        $stmt->execute($params);
        echo json_encode(['users'=>$stmt->fetchAll()]);
        break;

    default:
        echo json_encode(['error'=>'Action inconnue']);
}
