<?php

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function verify_csrf(): void
{
    $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if (empty($_POST) && $contentLength > 0) {
        http_response_code(413);
        exit('Upload is too large or incomplete. Please use a smaller file and try again.');
    }

    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
        $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $originHost = parse_url((string) ($_SERVER['HTTP_ORIGIN'] ?? ''), PHP_URL_HOST);
        if (!$originHost) {
            $originHost = parse_url((string) ($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_HOST);
        }
        $currentHost = (string) ($_SERVER['HTTP_HOST'] ?? '');

        $sameOriginUploadFallback =
            !empty($_SESSION['user_id']) &&
            str_contains($contentType, 'multipart/form-data') &&
            str_contains($requestUri, '/admin/') &&
            is_string($originHost) &&
            $originHost !== '' &&
            strcasecmp($originHost, $currentHost) === 0;

        if ($sameOriginUploadFallback) {
            return;
        }

        http_response_code(403);
        exit('Invalid security token.');
    }
}
