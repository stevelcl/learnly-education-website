<?php
session_start();
require_once __DIR__ . '/includes/admin-course-manager.php';
require_once __DIR__ . '/includes/admin-shell.php';

$user = require_admin();

$stats = [
    'users' => (int) (fetch_one('SELECT COUNT(*) AS total FROM users WHERE deleted_at IS NULL AND account_status <> "deleted"')['total'] ?? 0),
    'courses' => (int) (fetch_one('SELECT COUNT(*) AS total FROM courses')['total'] ?? 0),
    'books' => (int) (fetch_one('SELECT COUNT(*) AS total FROM books')['total'] ?? 0),
    'orders' => (int) (fetch_one('SELECT COUNT(*) AS total FROM orders WHERE deleted_at IS NULL')['total'] ?? 0),
    'forum_posts' => (int) (fetch_one('SELECT COUNT(*) AS total FROM forum_posts')['total'] ?? 0),
];

$recentOrders = fetch_all(
    'SELECT o.id, o.total, o.status, o.created_at, u.name
     FROM orders o
     JOIN users u ON u.id = o.user_id
     WHERE o.deleted_at IS NULL
     ORDER BY o.created_at DESC
     LIMIT 5'
);
$recentEnrollments = fetch_all(
    'SELECT ce.enrolled_at, u.name, c.title
     FROM course_enrollments ce
     JOIN users u ON u.id = ce.user_id
     JOIN courses c ON c.id = ce.course_id
     WHERE ce.archived_at IS NULL
     ORDER BY ce.enrolled_at DESC
     LIMIT 5'
);
$recentFeedback = fetch_all(
    'SELECT u.name, c.title, cr.rating, cr.updated_at
     FROM course_reviews cr
     JOIN users u ON u.id = cr.user_id
     JOIN courses c ON c.id = cr.course_id
     WHERE cr.moderation_status = "published" AND cr.deleted_at IS NULL
     ORDER BY cr.updated_at DESC
     LIMIT 5'
);
$lowStockBooks = fetch_all(
    'SELECT id, title, inventory
     FROM books
     WHERE inventory <= 5
     ORDER BY inventory ASC, title ASC
     LIMIT 5'
);

admin_render_start([
    'title' => 'Admin Dashboard',
    'page_title' => 'Dashboard',
    'page_subtitle' => 'A compact control center for courses, students, commerce, and moderation.',
    'active_nav' => 'dashboard',
    'breadcrumbs' => [
        ['label' => 'Dashboard'],
    ],
    'actions' => [
        ['label' => 'New Course', 'href' => app_url('admin/course/new')],
        ['label' => 'Books', 'href' => app_url('admin/books'), 'secondary' => true],
    ],
    'user' => $user,
]);
?>

<section class="admin-stats-row">
    <article class="panel admin-stat-card"><strong><?= $stats['users'] ?></strong><span class="muted">Users</span></article>
    <article class="panel admin-stat-card"><strong><?= $stats['courses'] ?></strong><span class="muted">Courses</span></article>
    <article class="panel admin-stat-card"><strong><?= $stats['books'] ?></strong><span class="muted">Books</span></article>
    <article class="panel admin-stat-card"><strong><?= $stats['orders'] ?></strong><span class="muted">Orders</span></article>
    <article class="panel admin-stat-card"><strong><?= $stats['forum_posts'] ?></strong><span class="muted">Forum Posts</span></article>
</section>

<section class="admin-content-grid">
    <div class="admin-section-grid">
        <article class="panel">
            <div class="admin-panel-header">
                <div>
                    <span class="eyebrow">Quick Actions</span>
                    <h2>Platform workflows</h2>
                </div>
            </div>
            <div class="actions">
                <a class="button" href="<?= htmlspecialchars(app_url('admin/courses')) ?>">Manage Courses</a>
                <a class="button ghost" href="<?= htmlspecialchars(app_url('admin/orders')) ?>">Review Orders</a>
                <a class="button ghost" href="<?= htmlspecialchars(app_url('admin/forum')) ?>">Moderate Forum</a>
                <a class="button ghost" href="<?= htmlspecialchars(app_url('admin/analytics')) ?>">Analytics</a>
            </div>
        </article>

        <article class="panel">
            <div class="admin-panel-header">
                <div>
                    <span class="eyebrow">Recent Orders</span>
                    <h2>Commerce activity</h2>
                </div>
                <a class="button ghost" href="<?= htmlspecialchars(app_url('admin/orders')) ?>">View all</a>
            </div>
            <div class="admin-mini-list">
                <?php if (!$recentOrders): ?>
                    <div class="admin-empty-state"><strong>No recent orders</strong><span>Customer purchases will show up here.</span></div>
                <?php endif; ?>
                <?php foreach ($recentOrders as $order): ?>
                    <article class="admin-mini-row">
                        <div><strong>#<?= (int) $order['id'] ?></strong><div class="muted"><?= htmlspecialchars($order['name']) ?></div></div>
                        <div class="muted"><?= htmlspecialchars(ucfirst($order['status'])) ?></div>
                        <div>RM <?= number_format((float) $order['total'], 2) ?></div>
                    </article>
                <?php endforeach; ?>
            </div>
        </article>

        <article class="panel">
            <div class="admin-panel-header">
                <div>
                    <span class="eyebrow">Recent Activity</span>
                    <h2>Learner movement</h2>
                </div>
            </div>
            <div class="admin-mini-list">
                <?php foreach ($recentEnrollments as $row): ?>
                    <article class="admin-mini-row">
                        <div>
                            <strong><?= htmlspecialchars($row['name']) ?></strong>
                            <div class="muted">Enrolled in <?= htmlspecialchars($row['title']) ?></div>
                        </div>
                        <div class="muted"><?= htmlspecialchars($row['enrolled_at']) ?></div>
                    </article>
                <?php endforeach; ?>
            </div>
        </article>
    </div>

    <div class="admin-section-grid">
        <article class="panel">
            <div class="admin-panel-header">
                <div>
                    <span class="eyebrow">Feedback</span>
                    <h2>Latest ratings</h2>
                </div>
                <a class="button ghost" href="<?= htmlspecialchars(app_url('admin/feedback')) ?>">Open feedback</a>
            </div>
            <div class="admin-mini-list">
                <?php if (!$recentFeedback): ?>
                    <div class="admin-empty-state"><strong>No feedback yet</strong><span>Completed course reviews will appear here.</span></div>
                <?php endif; ?>
                <?php foreach ($recentFeedback as $feedback): ?>
                    <article class="admin-mini-row">
                        <div>
                            <strong><?= htmlspecialchars($feedback['name']) ?></strong>
                            <div class="muted"><?= htmlspecialchars($feedback['title']) ?></div>
                        </div>
                        <div class="tag"><?= (int) $feedback['rating'] ?>/5</div>
                    </article>
                <?php endforeach; ?>
            </div>
        </article>

        <article class="panel">
            <div class="admin-panel-header">
                <div>
                    <span class="eyebrow">Inventory Alerts</span>
                    <h2>Low stock books</h2>
                </div>
                <a class="button ghost" href="<?= htmlspecialchars(app_url('admin/books')) ?>">Open inventory</a>
            </div>
            <div class="admin-mini-list">
                <?php if (!$lowStockBooks): ?>
                    <div class="admin-empty-state"><strong>Inventory looks healthy</strong><span>No books are currently under the alert threshold.</span></div>
                <?php endif; ?>
                <?php foreach ($lowStockBooks as $book): ?>
                    <article class="admin-mini-row">
                        <div>
                            <strong><?= htmlspecialchars($book['title']) ?></strong>
                            <div class="muted">Book ID <?= (int) $book['id'] ?></div>
                        </div>
                        <div class="tag warn"><?= (int) $book['inventory'] ?> left</div>
                    </article>
                <?php endforeach; ?>
            </div>
        </article>
    </div>
</section>

<?php admin_render_end(); ?>
