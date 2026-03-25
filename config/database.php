<?php
// ============================================================
//  TaskFlow — Connexion PDO (constantes DB dans config.php)
// ============================================================
require_once __DIR__ . '/config.php';

function tf_request_is_api(): bool {
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    return $script !== '' && strpos($script, '/api/') !== false;
}

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('TaskFlow DB: ' . $e->getMessage());
            http_response_code(503);
            $detail = (defined('APP_DEBUG') && APP_DEBUG) ? $e->getMessage() : '';
            if (tf_request_is_api()) {
                if (!headers_sent()) {
                    header('Content-Type: application/json; charset=utf-8');
                }
                echo json_encode([
                    'error' => $detail !== '' ? 'Connexion BD impossible: ' . $detail : 'Service indisponible.',
                ], JSON_UNESCAPED_UNICODE);
            } else {
                if (!headers_sent()) {
                    header('Content-Type: text/plain; charset=utf-8');
                }
                echo $detail !== '' ? 'Connexion à la base impossible: ' . $detail : "Service temporairement indisponible.\n";
            }
            exit;
        }
    }
    return $pdo;
}
