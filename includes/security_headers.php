<?php
/**
 * En-têtes HTTP de sécurité (appelé une fois par requête après démarrage session).
 */
function sendSecurityHeaders(): void {
    if (PHP_SAPI === 'cli' || headers_sent()) {
        return;
    }
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    $csp = implode('; ', [
        "default-src 'self'",
        "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net",
        "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net",
        "img-src 'self' data: blob:",
        "font-src 'self' https://cdn.jsdelivr.net data:",
        "connect-src 'self' https://cdn.jsdelivr.net",
        "frame-ancestors 'self'",
        "base-uri 'self'",
    ]);
    header('Content-Security-Policy: ' . $csp);
}
