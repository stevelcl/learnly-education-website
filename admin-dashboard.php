<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
$user = require_admin();

$stats = [
    'users' => (int) (fetch_one('SELECT COUNT(*) AS total FROM users')['total'] ?? 0),
    'courses' => (int) (fetch_one('SELECT COUNT(*) AS total FROM courses')['total'] ?? 0),
    'books' => (int) (fetch_one('SELECT COUNT(*) AS total FROM books')['total'] ?? 0),
    'orders' => (int) (fetch_one('SELECT COUNT(*) AS total FROM orders')['total'] ?? 0),
    'posts' => (int) (fetch_one('SELECT COUNT(*) AS total FROM forum_posts')['total'] ?? 0),
    'low_stock' => (int) (fetch_one('SELECT COUNT(*) AS total FROM books WHERE inventory <= 5')['total'] ?? 0),
];

$latestOrders = fetch_all(
    'SELECT o.id, o.total, o.status, o.created_at, u.name
     FROM orders o
     JOIN users u ON u.id = o.user_id
     ORDER BY o.created_at DESC
     LIMIT 5'
);

$lowStockBooks = fetch_all(
    'SELECT title, inventory
     FROM books
     WHERE inventory <= 5
     ORDER BY inventory ASC, title ASC
     LIMIT 8'
);

$pageTitle = 'Admin Dashboard';
include __DIR__ . '/includes/header.php';
?>

<section class="section">
    <div class="container">
        <div class="section-head">
            <div>
                <span class="eyebrow">Admin Panel</span>
                <h1>Platform management for Learnly.</h1>
            </div>
            <p>Signed in as <?= htmlspecialchars($user['name']) ?>. This area is hidden from public users and protected by server-side role checks.</p>
        </div>

        <div class="grid">
            <article class="panel"><h2><?= $stats['users'] ?></h2><p class="muted">Registered users</p></article>
            <article class="panel"><h2><?= $stats['courses'] ?></h2><p class="muted">Courses</p></article>
            <article class="panel"><h2><?= $stats['books'] ?></h2><p class="muted">Books</p></article>
            <article class="panel"><h2><?= $stats['orders'] ?></h2><p class="muted">Orders</p></article>
            <article class="panel"><h2><?= $stats['posts'] ?></h2><p class="muted">Forum posts</p></article>
            <article class="panel"><h2><?= $stats['low_stock'] ?></h2><p class="muted">Low-stock books</p></article>
        </div>

        <div class="grid" style="margin-top: 1rem;">
            <article class="panel">
                <h2>Management Areas</h2>
                <div class="actions">
                    <a class="button small" href="admin-users.php">Users</a>
                    <a class="button small" href="admin-progress.php">Progress</a>
                    <a class="button small" href="admin-courses.php">Courses</a>
                    <a class="button small" href="admin-books.php">Books</a>
                    <a class="button small" href="admin-orders.php">Orders</a>
                    <a class="button small" href="admin-forum.php">Forum</a>
                </div>
            </article>

            <article class="panel">
                <h2>Recent Orders</h2>
                <?php if (!$latestOrders): ?>
                    <p class="muted">No orders yet.</p>
                <?php endif; ?>
                <?php foreach ($latestOrders as $order): ?>
                    <p><strong>#<?= (int) $order['id'] ?></strong> | <?= htmlspecialchars($order['name']) ?> | RM <?= number_format((float) $order['total'], 2) ?> | <?= htmlspecialchars($order['status']) ?></p>
                <?php endforeach; ?>
            </article>

            <article class="panel">
                <h2>Low-Stock Alerts</h2>
                <?php if (!$lowStockBooks): ?>
                    <p class="muted">All books are currently above the low-stock threshold.</p>
                <?php endif; ?>
                <?php foreach ($lowStockBooks as $book): ?>
                    <p><strong><?= htmlspecialchars($book['title']) ?></strong><br><span class="muted"><?= (int) $book['inventory'] ?> left in stock</span></p>
                <?php endforeach; ?>
            </article>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
