<?php

function app_config(): array
{
    static $config;

    if ($config === null) {
        $config = require __DIR__ . '/../config.php';
    }

    return $config;
}

function send_app_mail(string $to, string $subject, string $textBody, ?string $htmlBody = null): bool
{
    $config = app_config();
    $fromEmail = trim((string) ($config['MAIL_FROM_EMAIL'] ?? 'no-reply@learnly.local'));
    $fromName = trim((string) ($config['MAIL_FROM_NAME'] ?? 'Learnly'));

    $headers = [
        'MIME-Version: 1.0',
        'From: ' . sprintf('"%s" <%s>', addcslashes($fromName, '"\\'), $fromEmail),
        'Reply-To: ' . $fromEmail,
    ];

    if ($htmlBody !== null && $htmlBody !== '') {
        $boundary = 'learnly-' . bin2hex(random_bytes(8));
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

        $message = "--{$boundary}\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
        $message .= $textBody . "\r\n\r\n";
        $message .= "--{$boundary}\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
        $message .= $htmlBody . "\r\n\r\n";
        $message .= "--{$boundary}--";
    } else {
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $message = $textBody;
    }

    return mail($to, $subject, $message, implode("\r\n", $headers));
}
