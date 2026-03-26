<?php
/**
 * Test local du flux « modifier utilisateur » (sans navigateur).
 * Usage (depuis la racine du projet) : php scripts/test_user_edit_local.php
 *
 * Nécessite une base joignable avec la config actuelle (config.local ou défauts).
 */
declare(strict_types=1);

$root = dirname(__DIR__);
chdir($root);

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['SCRIPT_NAME'] = '/taskflow/pages/users/edit.php';
$_SERVER['PHP_SELF'] = '/taskflow/pages/users/edit.php';
$_SERVER['REQUEST_URI'] = '/taskflow/pages/users/edit.php?id=0';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['HTTPS'] = 'off';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

require_once $root . '/config/config.php';
require_once $root . '/config/database.php';

$pdo = getDB();

$admin = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND actif = 1 ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$admin) {
    fwrite(STDERR, "Aucun utilisateur admin actif en base.\n");
    exit(1);
}
$adminId = (int) $admin['id'];

$target = $pdo->query("SELECT id, prenom, nom, email FROM users WHERE id != {$adminId} AND actif = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$target) {
    $target = $pdo->query("SELECT id, prenom, nom, email FROM users WHERE id = {$adminId} LIMIT 1")->fetch(PDO::FETCH_ASSOC);
}
if (!$target) {
    fwrite(STDERR, "Aucun utilisateur pour le test.\n");
    exit(1);
}
$uid = (int) $target['id'];

$mark = 'TFtest-' . bin2hex(random_bytes(4));

// Simule la requête POST vers edit.php (sans session : mise à jour PDO directe comme le ferait le formulaire)
$userRow = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$userRow->execute([$uid]);
$user = $userRow->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    exit(1);
}

$nom       = $user['nom'];
$prenom    = $user['prenom'];
$origEmail = $user['email'];
// Email unique : marqueur temporaire puis retour
$email = $mark . '+edit@test.local';
if (strlen($email) > 190) {
    $email = $mark . '@t.nl';
}
$role      = $user['role'];
$deptId    = $user['departement_id'] !== null ? (int) $user['departement_id'] : null;
$baseId    = $user['base_id'] !== null ? (int) $user['base_id'] : null;
$poste     = (string) ($user['poste'] ?? '');
$tel       = (string) ($user['telephone'] ?? '');
$actif     = (int) $user['actif'];
$notify    = isset($user['notify_email']) ? (int) $user['notify_email'] : 1;
$photoName = $user['photo'] ?? null;

$hasNotify = false;
try {
    $pdo->query('SELECT notify_email FROM users LIMIT 0');
    $hasNotify = true;
} catch (PDOException $e) {
    // ignore
}

try {
    $setCols = 'nom=?,prenom=?,email=?,role=?,departement_id=?,base_id=?,poste=?,telephone=?,actif=?';
    $params  = [$nom, $prenom, $email, $role, $deptId, $baseId, $poste, $tel, $actif];
    if ($hasNotify) {
        $setCols .= ',notify_email=?';
        $params[] = $notify;
    }
    $setCols .= ',photo=?';
    $params[] = $photoName;
    $params[] = $uid;
    $pdo->prepare("UPDATE users SET {$setCols} WHERE id=?")->execute($params);

    $check = $pdo->prepare('SELECT email FROM users WHERE id=?');
    $check->execute([$uid]);
    $saved = (string) $check->fetchColumn();
    if ($saved !== $email) {
        fwrite(STDERR, "Échec : email attendu {$email}, lu {$saved}\n");
        exit(1);
    }

    $paramsRestore  = [$nom, $prenom, $origEmail, $role, $deptId, $baseId, $poste, $tel, $actif];
    if ($hasNotify) {
        $paramsRestore[] = $notify;
    }
    $paramsRestore[] = $photoName;
    $paramsRestore[] = $uid;
    $pdo->prepare("UPDATE users SET {$setCols} WHERE id=?")->execute($paramsRestore);

    echo "OK — Requête UPDATE identique au formulaire : utilisateur #{$uid} écrit puis email restauré.\n";
    exit(0);
} catch (PDOException $e) {
    fwrite(STDERR, 'Erreur PDO : ' . $e->getMessage() . "\n");
    exit(1);
}
