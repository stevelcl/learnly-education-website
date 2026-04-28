<?php

require_once __DIR__ . '/db.php';

function cart_user_id(): int
{
    return (int) ($_SESSION['user_id'] ?? 0);
}

function session_cart_items(): array
{
    return $_SESSION['cart'] ?? [];
}

function cart_items(): array
{
    $userId = cart_user_id();
    if ($userId > 0) {
        $rows = fetch_all('SELECT book_id, quantity FROM cart_items WHERE user_id = ?', [$userId]);
        $items = [];
        foreach ($rows as $row) {
            $items[(int) $row['book_id']] = (int) $row['quantity'];
        }
        return $items;
    }

    return session_cart_items();
}

function cart_count(): int
{
    return array_sum(cart_items());
}

function add_to_cart(int $bookId, int $quantity = 1): void
{
    $quantity = max(1, $quantity);
    $userId = cart_user_id();

    if ($userId > 0) {
        $stmt = db()->prepare(
            'INSERT INTO cart_items (user_id, book_id, quantity)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity), updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([$userId, $bookId, $quantity]);
        return;
    }

    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    $_SESSION['cart'][$bookId] = ($_SESSION['cart'][$bookId] ?? 0) + $quantity;
}

function update_cart_item(int $bookId, int $quantity): void
{
    $userId = cart_user_id();
    if ($userId > 0) {
        if ($quantity <= 0) {
            $stmt = db()->prepare('DELETE FROM cart_items WHERE user_id = ? AND book_id = ?');
            $stmt->execute([$userId, $bookId]);
            return;
        }

        $stmt = db()->prepare(
            'INSERT INTO cart_items (user_id, book_id, quantity)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([$userId, $bookId, $quantity]);
        return;
    }

    if ($quantity <= 0) {
        unset($_SESSION['cart'][$bookId]);
        return;
    }

    $_SESSION['cart'][$bookId] = $quantity;
}

function clear_cart(): void
{
    $userId = cart_user_id();
    if ($userId > 0) {
        $stmt = db()->prepare('DELETE FROM cart_items WHERE user_id = ?');
        $stmt->execute([$userId]);
    }

    $_SESSION['cart'] = [];
}

function sync_session_cart_to_user(int $userId): void
{
    $sessionCart = session_cart_items();
    if ($userId <= 0 || !$sessionCart) {
        return;
    }

    $stmt = db()->prepare(
        'INSERT INTO cart_items (user_id, book_id, quantity)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity), updated_at = CURRENT_TIMESTAMP'
    );

    foreach ($sessionCart as $bookId => $quantity) {
        $stmt->execute([$userId, (int) $bookId, max(1, (int) $quantity)]);
    }

    $_SESSION['cart'] = [];
}
