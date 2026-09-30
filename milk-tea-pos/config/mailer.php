<?php
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/db.php';

function posSmtpSettings(): array {
    $defaults = [
        'smtp_host' => 'smtp.gmail.com', 'smtp_port' => 587,
        'smtp_username' => '', 'smtp_password' => '',
        'smtp_from' => 'noreply@bobahouse.local', 'smtp_name' => 'Boba House',
        'smtp_secure' => 'tls', 'smtp_auth' => 1,
    ];
    try {
        $row = getDB()->query("SELECT * FROM smtp_settings WHERE id=1 LIMIT 1")->fetch();
        return array_merge($defaults, $row ?: []);
    } catch (Throwable $e) { return $defaults; }
}

function posSmtpRead($socket): string {
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (strlen($line) < 4 || $line[3] === ' ') break;
    }
    return $response;
}

function posSmtpCommand($socket, string $command, string $expected): void {
    fwrite($socket, $command . "\r\n");
    $response = posSmtpRead($socket);
    if ($response === '' || strpos($expected, $response[0]) === false) {
        throw new RuntimeException('SMTP command rejected.');
    }
}

/**
 * Send through the POS database's shared SMTP settings.  If SMTP is not
 * configured yet, preserve the existing localhost mail log for development
 * instead of pretending that a message was delivered.
 */
function sendEmail(string $to, string $subject, string $body): bool {
    $settings = posSmtpSettings();
    $to = trim($to);
    $log = static function () use ($to, $subject, $body): void {
        $entry = str_repeat('=', 60) . PHP_EOL . 'TIME    : ' . date('Y-m-d H:i:s') . PHP_EOL
               . 'TO      : ' . $to . PHP_EOL . 'SUBJECT : ' . $subject . PHP_EOL
               . 'BODY    :' . PHP_EOL . strip_tags($body) . PHP_EOL
               . str_repeat('=', 60) . PHP_EOL . PHP_EOL;
        @file_put_contents(__DIR__ . '/../mail_log.txt', $entry, FILE_APPEND | LOCK_EX);
    };
    if (!filter_var($to, FILTER_VALIDATE_EMAIL) || ((int)$settings['smtp_auth'] && !$settings['smtp_username'])) {
        $log(); return false;
    }
    $transport = $settings['smtp_secure'] === 'ssl' ? 'ssl://' . $settings['smtp_host'] : $settings['smtp_host'];
    $socket = @stream_socket_client($transport . ':' . (int)$settings['smtp_port'], $errno, $errstr, 12);
    if (!$socket) { $log(); return false; }
    try {
        stream_set_timeout($socket, 12);
        $greeting = posSmtpRead($socket);
        if ($greeting === '' || $greeting[0] !== '2') throw new RuntimeException('No SMTP greeting.');
        posSmtpCommand($socket, 'EHLO localhost', '2');
        if ($settings['smtp_secure'] === 'tls') {
            posSmtpCommand($socket, 'STARTTLS', '2');
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new RuntimeException('TLS failed.');
            posSmtpCommand($socket, 'EHLO localhost', '2');
        }
        if ((int)$settings['smtp_auth']) {
            try {
                posSmtpCommand($socket, 'AUTH LOGIN', '3');
                posSmtpCommand($socket, base64_encode($settings['smtp_username']), '3');
                posSmtpCommand($socket, base64_encode($settings['smtp_password']), '2');
            } catch (Throwable $e) {
                posSmtpCommand($socket, 'AUTH PLAIN ' . base64_encode("\0" . $settings['smtp_username'] . "\0" . $settings['smtp_password']), '2');
            }
        }
        $from = $settings['smtp_from'] ?: $settings['smtp_username'];
        if (!filter_var($from, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Invalid sender.');
        posSmtpCommand($socket, 'MAIL FROM:<' . $from . '>', '2');
        posSmtpCommand($socket, 'RCPT TO:<' . $to . '>', '2');
        posSmtpCommand($socket, 'DATA', '3');
        $headers = 'From: ' . ($settings['smtp_name'] ?: $from) . ' <' . $from . ">\r\n"
                 . 'To: <' . $to . ">\r\nSubject: " . str_replace(["\r","\n"], '', $subject)
                 . "\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n";
        $body = preg_replace('/^\./m', '..', str_replace(["\r\n","\r"], "\n", $body));
        fwrite($socket, str_replace("\n", "\r\n", $headers . $body) . "\r\n.\r\n");
        $response = posSmtpRead($socket);
        posSmtpCommand($socket, 'QUIT', '2');
        fclose($socket);
        if ($response === '' || $response[0] !== '2') throw new RuntimeException('Delivery failed.');
        return true;
    } catch (Throwable $e) {
        @fclose($socket); $log(); return false;
    }
}

function sendVerificationEmail(string $email, string $token): bool {
    return sendEmail($email, 'Verify Your Account — Boba House',
        "Welcome to Boba House POS!\n\nVerification token: {$token}");
}