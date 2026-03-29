<?php
/**
 * Limite les tentatives de connexion par IP + e-mail (fichier temporaire avec verrou exclusif).
 */
function loginThrottlePath(string $email): string {
    $ip = $_SERVER[‘REMOTE_ADDR’] ?? ‘0’;
    $key = hash(‘sha256’, $ip . ‘|’ . strtolower(trim($email)));
    return sys_get_temp_dir() . DIRECTORY_SEPARATOR . ‘tf_login_’ . $key . ‘.json’;
}

const LOGIN_MAX_ATTEMPTS = 8;
const LOGIN_LOCK_SECONDS = 900; // 15 min

/**
 * Read throttle data atomically with a shared lock.
 * @return array{count:int,until:int}|null
 */
function loginThrottleRead(string $path): ?array {
    $fp = @fopen($path, ‘r’);
    if (!$fp) {
        return null;
    }
    flock($fp, LOCK_SH);
    $raw = stream_get_contents($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    $data = json_decode($raw ?: ‘’, true);
    return is_array($data) ? $data : null;
}

/** @return string|null message d’erreur si bloqué */
function loginThrottleCheck(string $email): ?string {
    $path = loginThrottlePath($email);
    $data = loginThrottleRead($path);
    if ($data === null) {
        return null;
    }
    $until = (int) ($data[‘until’] ?? 0);
    if ($until > time()) {
        $min = max(1, (int) ceil(($until - time()) / 60));
        return ‘Trop de tentatives. Réessayez dans ‘ . $min . ‘ minute(s).’;
    }
    return null;
}

function loginThrottleFailure(string $email): void {
    $path = loginThrottlePath($email);
    // Use exclusive lock for atomic read-modify-write
    $fp = @fopen($path, ‘c+’);
    if (!$fp) {
        return;
    }
    flock($fp, LOCK_EX);
    $raw = stream_get_contents($fp);
    $data = json_decode($raw ?: ‘’, true);
    if (!is_array($data)) {
        $data = [‘count’ => 0, ‘until’ => 0];
    }
    $data[‘count’] = (int) ($data[‘count’] ?? 0) + 1;
    if ($data[‘count’] >= LOGIN_MAX_ATTEMPTS) {
        $data[‘until’] = time() + LOGIN_LOCK_SECONDS;
        $data[‘count’] = 0;
    }
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($data));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
}

function loginThrottleSuccess(string $email): void {
    @unlink(loginThrottlePath($email));
}
