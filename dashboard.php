<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/progress.php';
$user = require_login();

if (is_admin($user)) {
    header('Location: ' . app_url('admin'));
    exit;
}

$progressRows = fetch_enrolled_courses_with_progress($user['id']);

$activeOrders = fetch_all(
    'SELECT * FROM orders
     WHERE user_id = ? AND deleted_at IS NULL AND status IN ("pending", "paid", "processing", "shipped")
     ORDER BY created_at DESC
     LIMIT 5',
    [$user['id']]
);
$orderHistory = fetch_all(
    'SELECT * FROM orders
     WHERE user_id = ? AND deleted_at IS NULL AND status IN ("delivered", "cancelled", "refunded", "archived")
     ORDER BY created_at DESC
     LIMIT 5',
    [$user['id']]
);
$posts = fetch_all('SELECT * FROM forum_posts WHERE user_id = ? ORDER BY created_at DESC LIMIT 5', [$user['id']]);
$pageTitle = 'Dashboard';
include __DIR__ . '/includes/header.php';
?>

<section class="section">
    <div class="container">
        <div class="dashboard-welcome">
            <div>
                <h1>Welcome back, <?= htmlspecialchars((string) ($user['first_name'] ?: $user['name'])) ?></h1>
                <p class="muted">Here's a summary of your learning activity and orders.</p>
            </div>
            <a class="button ghost" href="profile.php">View Profile</a>
        </div>
        <div class="grid">
            <article class="panel">
                <h2>Saved Progress</h2>
                <?php if (!$progressRows): ?>
                    <p class="muted">No course progress yet.</p>
                <?php endif; ?>
                <?php foreach ($progressRows as $row): ?>
                    <h3><a href="course.php?id=<?= (int) $row['id'] ?>"><?= htmlspecialchars($row['title']) ?></a></h3>
                    <p class="muted"><?= htmlspecialchars($row['subject']) ?> <?= $row['saved'] ? '| Saved' : '' ?></p>
                    <div class="progress"><span style="width: <?= (int) $row['progress_percent'] ?>%"></span></div>
                    <p><?= (int) $row['progress_percent'] ?>% complete | <?= (int) $row['completed_items'] ?> / <?= (int) $row['total_items'] ?> steps done</p>
                <?php endforeach; ?>
            </article>

            <article class="panel">
                <h2>Open Orders</h2>
                <?php if (!$activeOrders): ?>
                    <p class="muted">No active orders right now.</p>
                <?php endif; ?>
                <?php foreach ($activeOrders as $order): ?>
                    <p>
                        <strong>Order #<?= (int) $order['id'] ?></strong><br>
                        RM <?= number_format((float) $order['total'], 2) ?> | <?= htmlspecialchars($order['status']) ?><br>
                        <span class="muted"><?= htmlspecialchars((string) ($order['delivery_address'] ?: 'No address saved')) ?></span>
                    </p>
                <?php endforeach; ?>
            </article>

            <article class="panel">
                <h2>Order History</h2>
                <?php if (!$orderHistory): ?>
                    <p class="muted">No completed order history yet.</p>
                <?php endif; ?>
                <?php foreach ($orderHistory as $order): ?>
                    <p>
                        <strong>Order #<?= (int) $order['id'] ?></strong><br>
                        RM <?= number_format((float) $order['total'], 2) ?> | <?= htmlspecialchars($order['status']) ?><br>
                        <span class="muted"><?= htmlspecialchars((string) ($order['delivery_address'] ?: 'No address saved')) ?></span>
                    </p>
                <?php endforeach; ?>
            </article>

            <article class="panel">
                <h2>Your Forum Posts</h2>
                <?php if (!$posts): ?>
                    <p class="muted">No forum posts yet.</p>
                <?php endif; ?>
                <?php foreach ($posts as $post): ?>
                    <p><a href="post.php?id=<?= (int) $post['id'] ?>"><?= htmlspecialchars($post['title']) ?></a><br><span class="muted"><?= htmlspecialchars($post['status']) ?></span></p>
                <?php endforeach; ?>
            </article>

            <article class="panel">
                <h2>Your Profile</h2>
                <div class="dashboard-profile-row">
                    <div class="dashboard-profile-avatar" aria-hidden="true"><?= htmlspecialchars(user_initials($user)) ?></div>
                    <div>
                        <strong><?= htmlspecialchars((string) ($user['name'] ?? '')) ?></strong>
                        <p class="muted"><?= htmlspecialchars((string) ($user['email'] ?? '')) ?></p>
                    </div>
                </div>
                <div class="form-actions">
                    <a class="button small" href="profile.php">View Profile</a>
                    <a class="button small ghost" href="profile.php?tab=edit">Edit</a>
                </div>
            </article>

            <?php if (is_admin($user)): ?>
                <article class="panel">
                    <h2>Administration</h2>
                    <p class="muted">This account can manage users, courses, books, orders, and moderation.</p>
                    <a class="button small" href="<?= htmlspecialchars(app_url('admin')) ?>">Open Admin Panel</a>
                </article>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
