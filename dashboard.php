<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
$user = require_login();

$progressRows = fetch_all(
    'SELECT c.id, c.title, c.subject, up.saved, up.updated_at,
            COALESCE(cp.completed_items, 0) AS completed_items,
            COALESCE(ct.total_items, 0) AS total_items,
            COALESCE(up.progress_percent, 0) AS progress_percent
     FROM user_progress up
     JOIN courses c ON c.id = up.course_id
     LEFT JOIN (
        SELECT user_id, course_id, COUNT(*) AS completed_items
        FROM course_item_progress
        GROUP BY user_id, course_id
     ) cp ON cp.user_id = up.user_id AND cp.course_id = up.course_id
     LEFT JOIN (
        SELECT grouped.course_id, COUNT(*) AS total_items
        FROM (
            SELECT course_id, id FROM course_resources WHERE resource_type <> "quiz"
            UNION ALL
            SELECT course_id, id FROM quiz_questions
        ) grouped
        GROUP BY grouped.course_id
     ) ct ON ct.course_id = up.course_id
     WHERE up.user_id = ?
     ORDER BY up.updated_at DESC',
    [$user['id']]
);

$orders = fetch_all('SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 5', [$user['id']]);
$posts = fetch_all('SELECT * FROM forum_posts WHERE user_id = ? ORDER BY created_at DESC LIMIT 5', [$user['id']]);
$pageTitle = 'Dashboard';
include __DIR__ . '/includes/header.php';
?>

<section class="section">
    <div class="container">
        <h1>Welcome, <?= htmlspecialchars($user['name']) ?></h1>
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
                <h2>Recent Purchases</h2>
                <?php if (!$orders): ?>
                    <p class="muted">No purchases yet.</p>
                <?php endif; ?>
                <?php foreach ($orders as $order): ?>
                    <p><strong>Order #<?= (int) $order['id'] ?></strong><br>RM <?= number_format((float) $order['total'], 2) ?> | <?= htmlspecialchars($order['status']) ?></p>
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

            <?php if (is_admin($user)): ?>
                <article class="panel">
                    <h2>Administration</h2>
                    <p class="muted">This account can manage users, courses, books, orders, and moderation.</p>
                    <a class="button small" href="admin-dashboard.php">Open Admin Panel</a>
                </article>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
