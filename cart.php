<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/cart.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/media.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach ($_POST['quantities'] ?? [] as $bookId => $quantity) {
        update_cart_item((int) $bookId, (int) $quantity);
    }
    header('Location: cart.php');
    exit;
}

$cart = cart_items();
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

$pageTitle = 'Cart';
include __DIR__ . '/includes/header.php';
?>

<section class="section">
    <div class="container">
        <h1>Shopping Cart</h1>
        <?php if (!$books): ?>
            <p class="alert">Your cart is empty. <a href="bookstore.php">Browse books</a>.</p>
        <?php else: ?>
            <form method="post">
                <?= csrf_field() ?>
                <table>
                    <thead>
                        <tr>
                            <th>Book</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($books as $book): ?>
                            <tr>
                                <td><img src="<?= htmlspecialchars(book_cover_src($book['cover_url'])) ?>" alt="<?= htmlspecialchars($book['title']) ?>" class="book-cover" referrerpolicy="no-referrer" onerror="this.onerror=null;this.src='assets/images/book-placeholder.svg';"><?= htmlspecialchars($book['title']) ?></td>
                                <td>RM <?= number_format((float) $book['price'], 2) ?></td>
                                <td><input type="number" name="quantities[<?= (int) $book['id'] ?>]" value="<?= (int) $book['quantity'] ?>" min="0" max="<?= (int) $book['inventory'] ?>"></td>
                                <td>RM <?= number_format((float) $book['subtotal'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <h2>Total: RM <?= number_format($total, 2) ?></h2>
                <div class="form-actions">
                    <button type="submit">Update Cart</button>
                    <a class="button" href="checkout.php">Checkout</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
