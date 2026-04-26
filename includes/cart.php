<?php

function cart_items(): array
{
    return $_SESSION['cart'] ?? [];
}

function cart_count(): int
{
    return array_sum(cart_items());
}

function add_to_cart(int $bookId, int $quantity = 1): void
{
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    $_SESSION['cart'][$bookId] = ($_SESSION['cart'][$bookId] ?? 0) + max(1, $quantity);
}

function update_cart_item(int $bookId, int $quantity): void
{
    if ($quantity <= 0) {
        unset($_SESSION['cart'][$bookId]);
        return;
    }

    $_SESSION['cart'][$bookId] = $quantity;
}

