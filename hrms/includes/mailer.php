<?php
/**
 * Small dependency-free SMTP client for localhost installs.
 *
 * Settings are shared by every HRMS mail flow through hrms.smtp_settings.
 * It supports the common Gmail/Office 365 style STARTTLS and SSL transports,
 * AUTH LOGIN/PLAIN, HTML bodies, and a safe local log when SMTP is not set up.
 */

function smtpSettings(): array
{
    $defaults = [
        'smtp_host' => SMTP_HOST,
        'smtp_port' => SMTP_PORT,
        'smtp_username' => SMTP_USER,
        'smtp_password' => SMTP_PASS,
        'smtp_from' => SMTP_FROM_EMAIL,
        'smtp_name' => SMTP_FROM_NAME,
        'smtp_secure' => SMTP_SECURE,
        'smtp_auth' => SMTP_AUTH,
    ];
    try {
        $row = getDB()->query("SELECT * FROM smtp_settings WHERE id=1 LIMIT 1")->fetch();
        return array_merge($defaults, $row ?: []);
    } catch (Throwable $e) {
        return $defaults;
    }
}

function smtpRead($socket): string
{
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (strlen($line) < 4 || $line[3] === ' ') break;
    }
    return $response;
}

function smtpCommand($socket, string $command, string $expected): void
{
    fwrite($socket, $command . "\r\n");
    $response = smtpRead($socket);
    if ($response === '' || strpos($expected, $response[0]) === false) {
        throw new RuntimeException('SMTP server rejected command.');
    }
}

function sendSmtpEmail(string $to, string $subject, string $body, bool $html = false): bool
{
    $settings = smtpSettings();
    $to = trim($to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL) || empty($settings['smtp_host'])) return false;

    $log = static function () use ($to, $subject, $body): void {
        $line = str_repeat('=', 60) . PHP_EOL
              . 'TIME    : ' . date('Y-m-d H:i:s') . PHP_EOL
              . 'TO      : ' . $to . PHP_EOL
              . 'SUBJECT : ' . $subject . PHP_EOL
              . 'BODY    :' . PHP_EOL . strip_tags($body) . PHP_EOL
              . str_repeat('=', 60) . PHP_EOL . PHP_EOL;
        @file_put_contents(__DIR__ . '/../mail_log.txt', $line, FILE_APPEND | LOCK_EX);
    };

    // An empty username means SMTP is intentionally not configured.  Keep
    // development mail visible instead of silently discarding the message.
    if ((int)$settings['smtp_auth'] && empty($settings['smtp_username'])) {
        $log();
        return false;
    }

    $host = (string)$settings['smtp_host'];
    $port = (int)$settings['smtp_port'];
    $secure = (string)$settings['smtp_secure'];
    $transport = $secure === 'ssl' ? 'ssl://' . $host : $host;
    $socket = @stream_socket_client($transport . ':' . $port, $errno, $errstr, 12, STREAM_CLIENT_CONNECT);
    if (!$socket) { $log(); return false; }

    try {
        stream_set_timeout($socket, 12);
        $greeting = smtpRead($socket);
        if ($greeting === '' || $greeting[0] !== '2') throw new RuntimeException('No SMTP greeting.');
        smtpCommand($socket, 'EHLO localhost', '2');

        if ($secure === 'tls') {
            smtpCommand($socket, 'STARTTLS', '2');
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('STARTTLS failed.');
            }
            smtpCommand($socket, 'EHLO localhost', '2');
        }
        if ((int)$settings['smtp_auth']) {
            try {
                smtpCommand($socket, 'AUTH LOGIN', '3');
                smtpCommand($socket, base64_encode((string)$settings['smtp_username']), '3');
                smtpCommand($socket, base64_encode((string)$settings['smtp_password']), '2');
            } catch (Throwable $e) {
                smtpCommand($socket, 'AUTH PLAIN ' . base64_encode("\0" . $settings['smtp_username'] . "\0" . $settings['smtp_password']), '2');
            }
        }

        $from = (string)($settings['smtp_from'] ?: $settings['smtp_username']);
        if (!filter_var($from, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Invalid SMTP sender.');
        smtpCommand($socket, 'MAIL FROM:<' . $from . '>', '2');
        smtpCommand($socket, 'RCPT TO:<' . $to . '>', '2');
        smtpCommand($socket, 'DATA', '3');

        $contentType = $html ? 'text/html; charset=UTF-8' : 'text/plain; charset=UTF-8';
        $headers = 'From: ' . ($settings['smtp_name'] ?: $from) . ' <' . $from . ">\r\n"
                 . 'To: <' . $to . ">\r\n"
                 . 'Subject: ' . str_replace(["\r", "\n"], '', $subject) . "\r\n"
                 . "MIME-Version: 1.0\r\nContent-Type: {$contentType}\r\n\r\n";
        $payload = preg_replace('/^\./m', '..', str_replace(["\r\n", "\r"], "\n", $body));
        fwrite($socket, str_replace("\n", "\r\n", $headers . $payload) . "\r\n.\r\n");
        $response = smtpRead($socket);
        smtpCommand($socket, 'QUIT', '2');
        fclose($socket);
        if ($response === '' || $response[0] !== '2') throw new RuntimeException('SMTP delivery failed.');
        return true;
    } catch (Throwable $e) {
        @fclose($socket);
        $log();
        return false;
    }
}