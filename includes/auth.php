<?php

require_once __DIR__ . '/db.php';

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    return fetch_one(
        'SELECT id, name, first_name, last_name, email, role, account_status
         FROM users
         WHERE id = ? AND deleted_at IS NULL AND account_status <> "deleted"',
        [$_SESSION['user_id']]
    );
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'GET') {
            $_SESSION['post_login_redirect'] = app_safe_redirect_target(app_request_uri(), app_url('dashboard.php'));
        }
        header('Location: ' . app_url('login.php'));
        exit;
    }

    if (($user['account_status'] ?? 'active') === 'suspended') {
        $_SESSION = [];
        session_destroy();
        session_start();
        $_SESSION['login_error'] = 'This account is suspended. Please contact an administrator.';
        header('Location: ' . app_url('login.php'));
        exit;
    }

    return $user;
}

function is_moderator(?array $user): bool
{
    return is_admin($user);
}

function is_admin(?array $user): bool
{
    return $user && $user['role'] === 'admin';
}

function require_admin(): array
{
    $user = require_login();
    if (!is_admin($user)) {
        http_response_code(403);
        exit('Access denied.');
    }

    return $user;
}

function user_initials(array $user): string
{
    $f = mb_substr(trim((string) ($user['first_name'] ?? '')), 0, 1);
    $l = mb_substr(trim((string) ($user['last_name'] ?? '')), 0, 1);
    if ($f !== '' && $l !== '') {
        return strtoupper($f . $l);
    }
    $words = array_values(array_filter(explode(' ', trim((string) ($user['name'] ?? '')))));
    if (count($words) >= 2) {
        return strtoupper(mb_substr($words[0], 0, 1) . mb_substr(end($words), 0, 1));
    }
    return strtoupper(mb_substr((string) ($user['name'] ?? ''), 0, 2)) ?: 'U';
}
