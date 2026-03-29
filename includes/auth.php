<?php
// ============================================================
//  TaskFlow — Authentification & Permissions
// ============================================================
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// ---------- Vérification session active ----------
function isLoggedIn(): bool {
    if (!isset($_SESSION['user_id'])) return false;
    // Timeout inactivité
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        return false;
    }
    $_SESSION['last_activity'] = time();
    return true;
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
}

// ---------- Récupérer l'utilisateur connecté ----------
function currentUser(): ?array {
    if (!isLoggedIn()) return null;
    static $user = null;
    if ($user === null) {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT u.*, d.nom AS dept_nom, b.nom AS base_nom
            FROM users u
            LEFT JOIN departements d ON d.id = u.departement_id
            LEFT JOIN bases b ON b.id = u.base_id
            WHERE u.id = ? AND u.actif = 1");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch() ?: null;
    }
    return $user;
}

// ---------- Vérification de rôle ----------
function hasRole(string ...$roles): bool {
    $user = currentUser();
    return $user && in_array($user['role'], $roles);
}

function isAdmin(): bool           { return hasRole('admin'); }
function isCheffe(): bool          { return hasRole('admin','cheffe_mission'); }
function isChefDept(): bool        { return hasRole('admin','cheffe_mission','chef_dept'); }
function isSuperviseur(): bool     { return hasRole('admin','cheffe_mission','chef_dept','superviseur'); }

// ---------- Peut voir une tâche ? ----------
function canViewTask(array $task): bool {
    $user = currentUser();
    if (!$user) return false;
    if (isCheffe()) return true;
    if ($user['role'] === 'chef_dept')
        return $user['departement_id'] && (int)$task['departement_id'] === (int)$user['departement_id'];
    if ($user['role'] === 'superviseur')
        return $user['departement_id'] && (int)$task['departement_id'] === (int)$user['departement_id'];
    // Employé : seulement ses tâches assignées
    return isAssignedToTask($task['id'], $user['id']) || (int)$task['createur_id'] === (int)$user['id'];
}

function isAssignedToTask(int $taskId, int $userId): bool {
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT 1 FROM taches_assignees WHERE tache_id=? AND user_id=?");
    $stmt->execute([$taskId, $userId]);
    return (bool)$stmt->fetch();
}

// ---------- Peut modifier une tâche ? ----------
function canEditTask(array $task): bool {
    $user = currentUser();
    if (!$user) return false;
    if (isAdmin()) return true;
    if ($user['role'] === 'cheffe_mission') return false; // vue seule
    if ($user['role'] === 'chef_dept')
        return $user['departement_id'] && (int)$task['departement_id'] === (int)$user['departement_id'];
    if ($user['role'] === 'superviseur')
        return $user['departement_id'] && (int)$task['departement_id'] === (int)$user['departement_id'];
    // Employé : peut seulement changer le statut
    return isAssignedToTask($task['id'], $user['id']);
}

// ---------- Connexion ----------
function login(string $email, string $password): bool {
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email=? AND actif=1 LIMIT 1");
    $stmt->execute([trim($email)]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($password, $user['password'])) return false;

    // Regenerate session ID to prevent session fixation attacks
    session_regenerate_id(true);

    $_SESSION['user_id']       = $user['id'];
    $_SESSION['last_activity'] = time();
    // Regenerate CSRF token for the new session
    $_SESSION['csrf_token']    = bin2hex(random_bytes(32));
    // Mise à jour last_login
    $pdo->prepare("UPDATE users SET last_login=NOW() WHERE id=?")->execute([$user['id']]);
    return true;
}

/** Escape LIKE wildcards in user search input. */
function escapeLike(string $value): string {
    return str_replace(['%', '_'], ['\\%', '\\_'], $value);
}

// ---------- Déconnexion ----------
function logout(): void {
    session_unset();
    session_destroy();
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

// ---------- Token CSRF ----------
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        return;
    }
    http_response_code(403);
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $isApi  = strpos($script, '/api/') !== false;
    if ($isApi) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Token CSRF invalide', 'success' => false]);
        exit;
    }
    flashMessage('error', 'Session invalide ou formulaire expiré. Veuillez réessayer.');
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    $base = rtrim((string) APP_URL, '/');
    if ($ref !== '' && strpos($ref, $base) === 0) {
        header('Location: ' . $ref);
    } elseif (strpos($script, 'login.php') !== false) {
        header('Location: ' . APP_URL . '/login.php');
    } else {
        header('Location: ' . APP_URL . '/index.php');
    }
    exit;
}

// ---------- Utilitaires ----------
function sanitize(string $str): string {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

function jsonResponse(mixed $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function redirect(string $url): void {
    header('Location: ' . APP_URL . $url);
    exit;
}

function flashMessage(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (!isset($_SESSION['flash'])) return null;
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}
