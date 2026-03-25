<?php
/**
 * Limite les tentatives de connexion par IP + e-mail (fichier temporaire).
 */
function loginThrottlePath(string $email): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0';
    $key = hash('sha256', $ip . '|' . strtolower(trim($email)));
    return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tf_login_' . $key . '.json';
}

const LOGIN_MAX_ATTEMPTS = 8;
const LOGIN_LOCK_SECONDS = 900; // 15 min

/** @return string|null message d’erreur si bloqué */
function loginThrottleCheck(string $email): ?string {
    $path = loginThrottlePath($email);
    if (!is_readable($path)) {
        return null;
    }
    $raw = @file_get_contents($path);
    $data = json_decode($raw ?: '', true);
    if (!is_array($data)) {
        return null;
    }
    $until = (int) ($data['until'] ?? 0);
    if ($until > time()) {
        $min = max(1, (int) ceil(($until - time()) / 60));
        return 'Trop de tentatives. Réessayez dans ' . $min . ' minute(s).';
    }
    return null;
}

function loginThrottleFailure(string $email): void {
    $path = loginThrottlePath($email);
    $data = ['count' => 1, 'until' => 0];
    if (is_readable($path)) {
        $old = json_decode((string) file_get_contents($path), true);
        if (is_array($old)) {
            $data = $old;
        }
    }
    $data['count'] = (int) ($data['count'] ?? 0) + 1;
    if ($data['count'] >= LOGIN_MAX_ATTEMPTS) {
        $data['until'] = time() + LOGIN_LOCK_SECONDS;
        $data['count'] = 0;
    }
    @file_put_contents($path, json_encode($data), LOCK_EX);
}

function loginThrottleSuccess(string $email): void {
    @unlink(loginThrottlePath($email));
}
