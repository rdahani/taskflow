<?php
/**
 * TaskFlow — journal applicatif (fichiers + gestionnaires PHP).
 *
 * config.local.php : LOG_ENABLED (bool), LOG_LEVEL (debug|info|warning|error)
 */
declare(strict_types=1);

if (!defined('TF_LOG_DIR')) {
    define('TF_LOG_DIR', dirname(__DIR__) . '/storage/logs');
}

/** Niveaux numériques (filtrage LOG_LEVEL) */
const TF_LOG_PRIO = [
    'debug'   => 10,
    'info'    => 20,
    'notice'  => 25,
    'warning' => 30,
    'error'   => 40,
    'fatal'   => 50,
];

function tf_log_min_priority(): int {
    static $min = null;
    if ($min !== null) {
        return $min;
    }
    $lvl = defined('TF_LOG_LEVEL_STR') ? TF_LOG_LEVEL_STR : 'debug';
    $min = TF_LOG_PRIO[$lvl] ?? TF_LOG_PRIO['debug'];
    return $min;
}

function tf_log_enabled(): bool {
    return defined('TF_LOG_ENABLED') ? TF_LOG_ENABLED : true;
}

/**
 * @param string $level debug|info|notice|warning|error|fatal
 * @param array<string,mixed> $context
 */
function tf_log(string $level, string $message, array $context = []): void {
    if (!tf_log_enabled()) {
        return;
    }
    $p = TF_LOG_PRIO[$level] ?? TF_LOG_PRIO['info'];
    if ($p < tf_log_min_priority()) {
        return;
    }

    $dir = TF_LOG_DIR;
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $file = $dir . '/taskflow-' . date('Y-m-d') . '.log';
    $line = sprintf(
        "[%s] %s %s %s\n",
        date('Y-m-d H:i:s'),
        strtoupper($level),
        $message,
        $context === [] ? '' : json_encode(tf_log_sanitize_context($context), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );

    $fp = @fopen($file, 'ab');
    if ($fp !== false) {
        if (flock($fp, LOCK_EX)) {
            fwrite($fp, $line);
            flock($fp, LOCK_UN);
        }
        fclose($fp);
    } else {
        error_log('[TaskFlow] ' . trim($line));
    }
}

/** @param array<string,mixed> $context @return array<string,mixed> */
function tf_log_sanitize_context(array $context): array {
    $redact = ['password', 'passwd', 'secret', 'authorization', 'db_pass', 'mail_smtp_pass', 'creditcard'];
    $out    = [];
    foreach ($context as $k => $v) {
        $key = strtolower((string) $k);
        foreach ($redact as $r) {
            if (str_contains($key, $r)) {
                $v = '[redacted]';
                break;
            }
        }
        if (is_array($v)) {
            $v = tf_log_sanitize_context($v);
        }
        $out[(string) $k] = $v;
    }
    return $out;
}

function tf_log_exception(Throwable $e, string $label = ''): void {
    $msg = $label !== '' ? $label . ': ' : '';
    $msg .= $e->getMessage();
    tf_log('error', $msg, [
        'exception' => $e::class,
        'file'      => $e->getFile(),
        'line'      => $e->getLine(),
        'trace'     => APP_DEBUG ? $e->getTraceAsString() : '(trace masquée hors debug)',
    ]);
}

function tf_logger_is_api_request(): bool {
    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $script = str_replace('\\', '/', $script);
    return $script !== '' && strpos($script, '/api/') !== false;
}

function tf_error_handler(int $errno, string $errstr, string $errfile, int $errline): bool {
    if (!(error_reporting() & $errno)) {
        return false;
    }

    $ignore = [E_DEPRECATED, E_USER_DEPRECATED, E_STRICT];
    if (in_array($errno, $ignore, true)) {
        return false;
    }

    $level = 'warning';
    if (in_array($errno, [E_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR, E_CORE_ERROR], true)) {
        $level = 'error';
    } elseif (in_array($errno, [E_NOTICE, E_USER_NOTICE], true)) {
        $level = 'notice';
    }

    tf_log($level, $errstr, ['file' => $errfile, 'line' => $errline, 'errno' => $errno]);
    return false;
}

function tf_shutdown_handler(): void {
    $e = error_get_last();
    if ($e === null) {
        return;
    }
    $types = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_COMPILE_WARNING];
    if (!in_array((int) $e['type'], $types, true)) {
        return;
    }
    tf_log('fatal', (string) $e['message'], ['file' => $e['file'], 'line' => $e['line'], 'type' => $e['type']]);
}

function tf_exception_handler(Throwable $ex): void {
    tf_log_exception($ex, 'Exception non attrapée');

    if (headers_sent()) {
        return;
    }

    if (tf_logger_is_api_request()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error'   => APP_DEBUG ? $ex->getMessage() : 'Erreur serveur.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $ex->getMessage() . PHP_EOL);
        exit(1);
    }

    http_response_code(500);
    if (APP_DEBUG) {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Erreur</title></head><body><pre>';
        echo htmlspecialchars($ex->getMessage() . "\n" . $ex->getFile() . ':' . $ex->getLine());
        echo '</pre></body></html>';
    } else {
        header('Content-Type: text/plain; charset=utf-8');
        echo "Une erreur technique est survenue.\n";
    }
    exit;
}

function tf_register_error_handlers(): void {
    if (defined('TF_LOG_HANDLERS_REGISTERED')) {
        return;
    }
    define('TF_LOG_HANDLERS_REGISTERED', true);

    if (!tf_log_enabled()) {
        return;
    }

    set_exception_handler('tf_exception_handler');
    register_shutdown_function('tf_shutdown_handler');
    set_error_handler('tf_error_handler', E_ALL);
}
