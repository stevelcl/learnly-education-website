<?php

require_once __DIR__ . '/db.php';

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    return fetch_one('SELECT id, name, email, role FROM users WHERE id = ?', [$_SESSION['user_id']]);
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        header('Location: login.php');
        exit;
    }

    return $user;
}

function is_moderator(?array $user): bool
{
    return $user && in_array($user['role'], ['admin', 'moderator'], true);
}

