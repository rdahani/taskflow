<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
requireLogin();

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$pdo    = getDB();

// Lire body JSON si nécessaire
$body = [];
$raw  = file_get_contents('php://input');
if (!empty($raw)) {
    $body = json_decode($raw, true) ?? [];
    if (isset($body['action'])) $action = $body['action'];
}

switch ($action) {

    // ---------------------------------------------------------
    case 'list':
        $user = currentUser();
        $where = ['1=1']; $params = [];
        if ($user['role'] === 'employe') {
            $where[] = 'ta.user_id = ?'; $params[] = $user['id'];
            $join = 'JOIN taches_assignees ta ON ta.tache_id=t.id';
        } elseif (in_array($user['role'],['superviseur','chef_dept'])) {
            if ($user['departement_id']) {
                $where[] = 't.departement_id = ?'; $params[] = $user['departement_id'];
            } else {
                $where[] = '1=0';
            }
            $join = '';
        } else { $join = ''; }

        $q = trim($_GET['q'] ?? '');
        if ($q) { $ql = '%' . escapeLike($q) . '%'; $where[] = '(t.titre LIKE ? OR t.description LIKE ?)'; $params[]=$ql; $params[]=$ql; }
        if (!empty($_GET['statut'])) { $where[] = 't.statut=?'; $params[]=$_GET['statut']; }

        $stmt = $pdo->prepare("SELECT DISTINCT t.id,t.titre,t.statut,t.priorite,t.date_echeance,t.pourcentage FROM taches t $join WHERE ".implode(' AND ',$where)." ORDER BY t.date_echeance ASC LIMIT 50");
        $stmt->execute($params);
        $tasks = $stmt->fetchAll();
        // Ajouter infos statut pour affichage
        foreach ($tasks as &$t) {
            $real = computeRealStatus($t);
            $s = TASK_STATUSES[$real] ?? TASK_STATUSES['pas_fait'];
            $t['statut_real'] = $real;
            $t['statut_label'] = $s['label'];
            $t['statut_color'] = $s['color'];
            $t['statut_bg']    = $s['bg'];
        }
        echo json_encode(['success'=>true,'tasks'=>$tasks]);
        break;

    // ---------------------------------------------------------
    case 'search':
        $q = trim($_GET['q'] ?? '');
        if (strlen($q) < 2) { echo json_encode(['results'=>[]]); break; }
        $user = currentUser();
        $ql = '%' . escapeLike($q) . '%';
        $params = [$ql, $ql];
        $where = ['(t.titre LIKE ? OR t.description LIKE ?)'];
        $join = '';

        if ($user['role'] === 'employe') {
            $where[] = 'ta.user_id=?'; $params[]=$user['id'];
            $join = 'JOIN taches_assignees ta ON ta.tache_id=t.id';
        } elseif (in_array($user['role'],['superviseur','chef_dept'])) {
            if ($user['departement_id']) {
                $where[] = 't.departement_id=?'; $params[]=$user['departement_id'];
            } else {
                $where[] = '1=0';
            }
        }

        $stmt = $pdo->prepare("SELECT DISTINCT t.id,t.titre,t.statut FROM taches t $join WHERE ".implode(' AND ',$where)." LIMIT 8");
        $stmt->execute($params);
        $results = $stmt->fetchAll();
        foreach ($results as &$r) {
            $real = computeRealStatus($r);
            $s = TASK_STATUSES[$real] ?? TASK_STATUSES['pas_fait'];
            $r['statut_label'] = $s['label'];
            $r['statut_color'] = $s['color'];
            $r['statut_bg']    = $s['bg'];
        }
        echo json_encode(['results'=>$results]);
        break;

    // ---------------------------------------------------------
    case 'update_status':
        $id     = (int)($body['id'] ?? 0);
        $statut = $body['statut'] ?? '';
        if (!$id || !array_key_exists($statut, TASK_STATUSES) || $statut === 'en_retard') {
            echo json_encode(['success'=>false,'error'=>'Paramètres invalides']); break;
        }
        $stmt = $pdo->prepare("SELECT * FROM taches WHERE id=?");
        $stmt->execute([$id]);
        $task = $stmt->fetch();
        if (!$task || !canEditTask($task)) {
            echo json_encode(['success'=>false,'error'=>'Accès refusé']); break;
        }
        // CSRF check via header
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
            echo json_encode(['success'=>false,'error'=>'CSRF invalide']); break;
        }
        $closedAt = $statut === 'termine' ? date('Y-m-d H:i:s') : null;
        $pdo->prepare("UPDATE taches SET statut=?,date_cloture=? WHERE id=?")->execute([$statut,$closedAt,$id]);
        logChange($id, currentUser()['id'], 'statut', $task['statut'], $statut);
        echo json_encode(['success'=>true]);
        break;

    // ---------------------------------------------------------
    case 'update_progress':
        verifyCsrf();
        $id      = (int)($_POST['id'] ?? 0);
        $percent = min(100, max(0, (int)($_POST['pourcentage'] ?? 0)));
        $stmt    = $pdo->prepare("SELECT * FROM taches WHERE id=?");
        $stmt->execute([$id]);
        $task = $stmt->fetch();
        if (!$task || !canEditTask($task)) { echo json_encode(['success'=>false]); break; }
        $pdo->prepare("UPDATE taches SET pourcentage=? WHERE id=?")->execute([$percent,$id]);
        logChange($id, currentUser()['id'], 'pourcentage', $task['pourcentage'], $percent);
        echo json_encode(['success'=>true,'pourcentage'=>$percent]);
        break;

    // ---------------------------------------------------------
    case 'delete':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
            break;
        }
        if (!isAdmin()) {
            echo json_encode(['success' => false, 'error' => 'Accès refusé']);
            break;
        }
        $id = (int) ($body['id'] ?? 0);
        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'ID manquant']);
            break;
        }
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
            echo json_encode(['success' => false, 'error' => 'CSRF invalide']);
            break;
        }
        $stmt = $pdo->prepare('SELECT id, titre FROM taches WHERE id=?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            echo json_encode(['success' => false, 'error' => 'Tâche introuvable']);
            break;
        }
        require_once __DIR__ . '/../includes/audit.php';
        // Supprimer les fichiers physiques avant suppression BDD
        $fStmt = $pdo->prepare('SELECT chemin FROM fichiers WHERE tache_id=?');
        $fStmt->execute([$id]);
        foreach ($fStmt->fetchAll() as $f) {
            $abs = UPLOAD_DIR . $f['chemin'];
            if (is_file($abs)) @unlink($abs);
        }
        // Suppression en cascade (si pas de FK dans la BDD)
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM taches_assignees WHERE tache_id=?')->execute([$id]);
            $pdo->prepare('DELETE FROM commentaires WHERE tache_id=?')->execute([$id]);
            $pdo->prepare('DELETE FROM fichiers WHERE tache_id=?')->execute([$id]);
            $pdo->prepare('DELETE FROM historique WHERE tache_id=?')->execute([$id]);
            try {
                $pdo->prepare('DELETE FROM tache_chat_messages WHERE tache_id=?')->execute([$id]);
            } catch (Throwable $ignored) {}
            $pdo->prepare('DELETE FROM taches WHERE id=?')->execute([$id]);
            logAudit((int) currentUser()['id'], 'task_delete', 'tache', $id, mb_substr((string) $row['titre'], 0, 200));
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('TaskFlow delete task: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Erreur lors de la suppression']);
            break;
        }
        echo json_encode(['success' => true]);
        break;

    // ---------------------------------------------------------
    case 'stats':
        $user  = currentUser();
        $stats = getDashboardStats($user);
        echo json_encode(['success'=>true,'stats'=>$stats]);
        break;

    // ---------------------------------------------------------
    case 'reconduire':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
            break;
        }
        if (!isSuperviseur()) {
            echo json_encode(['success' => false, 'error' => 'Accès refusé — superviseur requis']);
            break;
        }
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
            echo json_encode(['success' => false, 'error' => 'CSRF invalide']);
            break;
        }

        $ids           = array_map('intval', (array) ($body['ids']          ?? []));
        $decalageMois  = max(1, min(24, (int) ($body['decalage_mois']       ?? 1)));
        $nbRepetitions = max(1, min(12, (int) ($body['nb_repetitions']      ?? 1)));
        $copierAssigns = !empty($body['copier_assignes']);
        $statutInitial = 'pas_fait';

        if (empty($ids)) {
            echo json_encode(['success' => false, 'error' => 'Aucune tâche sélectionnée']);
            break;
        }
        if (count($ids) > 50) {
            echo json_encode(['success' => false, 'error' => 'Maximum 50 tâches à la fois']);
            break;
        }

        $user        = currentUser();
        $createdTotal = 0;
        $errors       = [];

        $fetchStmt  = $pdo->prepare('SELECT * FROM taches WHERE id = ?');
        $assignStmt = $pdo->prepare('SELECT user_id FROM taches_assignees WHERE tache_id = ?');
        $insertTask = $pdo->prepare(
            'INSERT INTO taches (titre, description, statut, priorite, date_debut, date_echeance,
             createur_id, departement_id, base_id, categorie_id, pourcentage)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)'
        );
        $insertAssign = $pdo->prepare('INSERT IGNORE INTO taches_assignees (tache_id, user_id) VALUES (?, ?)');

        foreach ($ids as $taskId) {
            $fetchStmt->execute([$taskId]);
            $orig = $fetchStmt->fetch(PDO::FETCH_ASSOC);
            if (!$orig || !canViewTask($orig)) {
                $errors[] = "Tâche #$taskId inaccessible";
                continue;
            }

            // Récupérer les assignés si demandé
            $assignes = [];
            if ($copierAssigns) {
                $assignStmt->execute([$taskId]);
                $assignes = $assignStmt->fetchAll(PDO::FETCH_COLUMN);
            }

            for ($rep = 1; $rep <= $nbRepetitions; $rep++) {
                $shiftMois = $decalageMois * $rep;

                // Décaler date_debut
                $newDebut = null;
                if (!empty($orig['date_debut'])) {
                    $d = new DateTime($orig['date_debut']);
                    $d->modify("+{$shiftMois} months");
                    $newDebut = $d->format('Y-m-d');
                }

                // Décaler date_echeance (obligatoire)
                $newEch = new DateTime($orig['date_echeance']);
                $newEch->modify("+{$shiftMois} months");
                $newEchStr = $newEch->format('Y-m-d');

                $insertTask->execute([
                    $orig['titre'],
                    $orig['description'],
                    $statutInitial,
                    $orig['priorite'],
                    $newDebut,
                    $newEchStr,
                    (int) $user['id'],
                    $orig['departement_id'],
                    $orig['base_id'],
                    $orig['categorie_id'],
                ]);
                $newId = (int) $pdo->lastInsertId();
                $createdTotal++;

                // Copier les assignés
                foreach ($assignes as $uid) {
                    $insertAssign->execute([$newId, (int) $uid]);
                }

                // Historique
                logChange($newId, (int) $user['id'], 'creation',
                    '', "Reconduit depuis #$taskId (+{$shiftMois} mois)");
            }
        }

        $msg = "$createdTotal tâche(s) créée(s)";
        if (!empty($errors)) $msg .= ' (' . count($errors) . ' erreur(s))';
        echo json_encode([
            'success' => $createdTotal > 0,
            'created' => $createdTotal,
            'errors'  => $errors,
            'message' => $msg,
        ], JSON_UNESCAPED_UNICODE);
        break;

    // ---------------------------------------------------------
    default:
        http_response_code(400);
        echo json_encode(['error'=>'Action inconnue: '.$action]);
}
