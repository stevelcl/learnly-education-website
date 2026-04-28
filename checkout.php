<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/cart.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/media.php';

$user = require_login();
$cart = cart_items();
$error = '';
$success = '';
$books = [];
$total = 0;

if ($cart) {
    $placeholders = implode(',', array_fill(0, count($cart), '?'));
    $books = fetch_all('SELECT * FROM books WHERE id IN (' . $placeholders . ')', array_keys($cart));
    foreach ($books as &$book) {
        $book['quantity'] = $cart[$book['id']] ?? 0;
        $book['subtotal'] = $book['quantity'] * (float) $book['price'];
        $total += $book['subtotal'];
    }
    unset($book);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!$books) {
        $error = 'Your cart is empty.';
    } else {
        try {
            db()->beginTransaction();
            foreach ($books as $book) {
                if ((int) $book['inventory'] < (int) $book['quantity']) {
                    throw new RuntimeException($book['title'] . ' does not have enough stock.');
                }
            }

            $stmt = db()->prepare('INSERT INTO orders (user_id, total, status) VALUES (?, ?, "paid")');
            $stmt->execute([$user['id'], $total]);
            $orderId = (int) db()->lastInsertId();

            $itemStmt = db()->prepare('INSERT INTO order_items (order_id, book_id, quantity, unit_price) VALUES (?, ?, ?, ?)');
            $stockStmt = db()->prepare('UPDATE books SET inventory = inventory - ? WHERE id = ?');
            foreach ($books as $book) {
                $itemStmt->execute([$orderId, $book['id'], $book['quantity'], $book['price']]);
                $stockStmt->execute([$book['quantity'], $book['id']]);
            }

            db()->commit();
            clear_cart();
            $success = 'Checkout successful. Your order number is #' . $orderId . '.';
            $books = [];
        } catch (Throwable $e) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            $error = $e->getMessage();
        }
    }
}

$pageTitle = 'Checkout';
include __DIR__ . '/includes/header.php';
?>

<section class="section">
    <div class="container narrow">
        <div class="panel">
            <h1>Checkout</h1>
            <?php if ($error): ?><p class="alert error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
            <?php if ($success): ?><p class="alert success"><?= htmlspecialchars($success) ?></p><?php endif; ?>
            <?php if (!$books && !$success): ?>
                <p>Your cart is empty. <a href="bookstore.php">Browse books</a>.</p>
            <?php elseif ($books): ?>
                <table>
                    <thead><tr><th>Book</th><th>Qty</th><th>Subtotal</th></tr></thead>
                    <tbody>
                    <?php foreach ($books as $book): ?>
                        <tr>
                            <td><img src="<?= htmlspecialchars(book_cover_src($book['cover_url'])) ?>" alt="<?= htmlspecialchars($book['title']) ?>" class="book-cover" referrerpolicy="no-referrer" onerror="this.onerror=null;this.src='assets/images/book-placeholder.svg';"><?= htmlspecialchars($book['title']) ?></td>
                            <td><?= (int) $book['quantity'] ?></td>
                            <td>RM <?= number_format((float) $book['subtotal'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <h2>Total: RM <?= number_format($total, 2) ?></h2>
                <form method="post">
                    <?= csrf_field() ?>
                    <label>Delivery Address <textarea name="address" required></textarea></label>
                    <label>Payment Method
                        <select name="payment_method" required>
                            <option>Online Banking</option>
                            <option>Credit/Debit Card</option>
                            <option>Campus Pickup Payment</option>
                        </select>
                    </label>
                    <button type="submit" data-confirm="Confirm checkout?">Place Order</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
