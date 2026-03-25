<?php
/**
 * Envoi d’e-mails TaskFlow (SMTP ou mail() PHP).
 * Nécessite les constantes définies dans config.php.
 */

function sendTaskflowMail(string $toEmail, string $toName, string $subject, string $plainBody, string $htmlBody = ''): bool {
    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $html = $htmlBody !== '' ? $htmlBody : '<pre style="font-family:sans-serif">' . htmlspecialchars($plainBody, ENT_QUOTES, 'UTF-8') . '</pre>';

    if (MAIL_USE_SMTP && MAIL_SMTP_HOST !== '' && MAIL_SMTP_PORT > 0) {
        return taskflowMailViaSmtp($toEmail, $toName, $subject, $plainBody, $html);
    }

    return taskflowMailViaPhp($toEmail, $subject, $plainBody, $html);
}

function taskflowMailEncodeHeader(string $s): string {
    return '=?UTF-8?B?' . base64_encode($s) . '?=';
}

function taskflowMailViaPhp(string $toEmail, string $subject, string $plain, string $html): bool {
    $boundary = 'tf_' . bin2hex(random_bytes(8));
    $fromName = taskflowMailEncodeHeader(MAIL_FROM_NAME);
    $headers  = [
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        'From: ' . $fromName . ' <' . MAIL_FROM . '>',
        'Reply-To: ' . MAIL_FROM,
        'X-Mailer: TaskFlow/' . APP_VERSION,
    ];
    $body  = "--$boundary\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n";
    $body .= str_replace(["\r\n", "\r"], "\n", $plain) . "\r\n";
    $body .= "--$boundary\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n";
    $body .= $html . "\r\n";
    $body .= "--$boundary--\r\n";

    $subj = taskflowMailEncodeHeader($subject);
    $ok   = @mail($toEmail, $subj, $body, implode("\r\n", $headers), '-f' . MAIL_FROM);
    if (!$ok) {
        error_log('TaskFlow mail(): échec envoi vers ' . $toEmail);
    }
    return $ok;
}

function taskflowMailRead($fp): string {
    $data = '';
    while ($line = fgets($fp, 515)) {
        $data .= $line;
        if (strlen($line) < 4 || $line[3] === ' ') {
            break;
        }
    }
    return $data;
}

function taskflowMailExpect($fp, array $codes): bool {
    $line = taskflowMailRead($fp);
    $code = (int) substr($line, 0, 3);
    return in_array($code, $codes, true);
}

function taskflowMailViaSmtp(string $toEmail, string $toName, string $subject, string $plain, string $html): bool {
    $host = MAIL_SMTP_HOST;
    $port = MAIL_SMTP_PORT;
    $user = MAIL_SMTP_USER;
    $pass = MAIL_SMTP_PASS;
    $enc  = MAIL_SMTP_ENCRYPTION;

    $boundary = 'tf_' . bin2hex(random_bytes(8));
    $fromName = MAIL_FROM_NAME;
    $message  = "From: $fromName <" . MAIL_FROM . ">\r\n";
    $message .= "To: " . ($toName !== '' ? "$toName <$toEmail>" : $toEmail) . "\r\n";
    $message .= 'Subject: ' . taskflowMailEncodeHeader($subject) . "\r\n";
    $message .= "MIME-Version: 1.0\r\n";
    $message .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n\r\n";
    $message .= "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n";
    $message .= str_replace(["\r\n", "\r"], "\n", $plain) . "\r\n";
    $message .= "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n";
    $message .= $html . "\r\n--$boundary--\r\n";
    $message = str_replace("\n", "\r\n", str_replace("\r\n", "\n", $message));

    $remote = ($enc === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $ctx    = stream_context_create([
        'ssl' => [
            'verify_peer'      => true,
            'verify_peer_name' => true,
            'allow_self_signed'=> false,
        ],
    ]);

    $fp = @stream_socket_client($remote, $errno, $errstr, 25, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) {
        error_log("TaskFlow SMTP: connexion $remote — $errstr ($errno)");
        return false;
    }
    stream_set_timeout($fp, 25);

    try {
        if (!taskflowMailExpect($fp, [220])) {
            throw new RuntimeException('SMTP welcome');
        }
        $ehloHost = parse_url(APP_URL, PHP_URL_HOST) ?: 'localhost';
        $ehlo     = 'EHLO ' . $ehloHost . "\r\n";
        fwrite($fp, $ehlo);
        if (!taskflowMailExpect($fp, [250])) {
            throw new RuntimeException('EHLO');
        }

        if ($enc === 'tls' && $port !== 465) {
            fwrite($fp, "STARTTLS\r\n");
            if (!taskflowMailExpect($fp, [220])) {
                throw new RuntimeException('STARTTLS');
            }
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('TLS crypto');
            }
            fwrite($fp, $ehlo);
            if (!taskflowMailExpect($fp, [250])) {
                throw new RuntimeException('EHLO after TLS');
            }
        }

        if ($user !== '' && $pass !== '') {
            fwrite($fp, "AUTH LOGIN\r\n");
            if (!taskflowMailExpect($fp, [334])) {
                throw new RuntimeException('AUTH LOGIN');
            }
            fwrite($fp, base64_encode($user) . "\r\n");
            if (!taskflowMailExpect($fp, [334])) {
                throw new RuntimeException('SMTP user');
            }
            fwrite($fp, base64_encode($pass) . "\r\n");
            if (!taskflowMailExpect($fp, [235])) {
                throw new RuntimeException('SMTP password');
            }
        }

        fwrite($fp, 'MAIL FROM:<' . MAIL_FROM . ">\r\n");
        if (!taskflowMailExpect($fp, [250])) {
            throw new RuntimeException('MAIL FROM');
        }
        fwrite($fp, 'RCPT TO:<' . $toEmail . ">\r\n");
        if (!taskflowMailExpect($fp, [250, 251])) {
            throw new RuntimeException('RCPT TO');
        }
        fwrite($fp, "DATA\r\n");
        if (!taskflowMailExpect($fp, [354])) {
            throw new RuntimeException('DATA');
        }
        fwrite($fp, $message . "\r\n.\r\n");
        if (!taskflowMailExpect($fp, [250])) {
            throw new RuntimeException('message body');
        }
        fwrite($fp, "QUIT\r\n");
    } catch (Throwable $e) {
        error_log('TaskFlow SMTP: ' . $e->getMessage());
        fclose($fp);
        return false;
    }

    fclose($fp);
    return true;
}
