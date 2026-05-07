<?php

require_once __DIR__ . '/db.php';

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    return fetch_one(
        'SELECT id, name, email, role, account_status
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
