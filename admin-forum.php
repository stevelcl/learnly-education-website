<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
$user = require_admin();

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $type = $_POST['type'] ?? '';
    $status = $_POST['status'] === 'hidden' ? 'hidden' : 'visible';

    if ($type === 'post') {
        $stmt = db()->prepare('UPDATE forum_posts SET status = ? WHERE id = ?');
        $stmt->execute([$status, (int) $_POST['id']]);
        $message = 'Post moderation updated.';
    }

    if ($type === 'reply') {
        $stmt = db()->prepare('UPDATE forum_replies SET status = ? WHERE id = ?');
        $stmt->execute([$status, (int) $_POST['id']]);
        $message = 'Reply moderation updated.';
    }
}

$posts = fetch_all(
    'SELECT p.id, p.title, p.status, p.created_at, u.name
     FROM forum_posts p
     JOIN users u ON u.id = p.user_id
     ORDER BY p.created_at DESC
     LIMIT 20'
);

$replies = fetch_all(
    'SELECT r.id, r.body, r.status, r.created_at, u.name
     FROM forum_replies r
     JOIN users u ON u.id = r.user_id
     ORDER BY r.created_at DESC
     LIMIT 20'
);

$pageTitle = 'Manage Forum';
include __DIR__ . '/includes/header.php';
?>

<section class="section">
    <div class="container">
        <div class="section-head">
            <div>
                <span class="eyebrow">Admin Forum</span>
                <h1>Moderate questions and discussion replies.</h1>
            </div>
            <a class="button ghost" href="admin-dashboard.php">Back to Admin</a>
        </div>
        <?php if ($message): ?><p class="alert success"><?= htmlspecialchars($message) ?></p><?php endif; ?>

        <div class="layout">
            <div class="panel">
                <h2>Recent Posts</h2>
                <?php foreach ($posts as $post): ?>
                    <div class="panel" style="margin-bottom: 0.9rem;">
                        <p><strong><?= htmlspecialchars($post['title']) ?></strong><br><span class="muted">By <?= htmlspecialchars($post['name']) ?> | <?= htmlspecialchars($post['created_at']) ?> | <?= htmlspecialchars($post['status']) ?></span></p>
                        <form method="post" class="inline-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="type" value="post">
                            <input type="hidden" name="id" value="<?= (int) $post['id'] ?>">
                            <select name="status">
                                <option value="visible" <?= $post['status'] === 'visible' ? 'selected' : '' ?>>Visible</option>
                                <option value="hidden" <?= $post['status'] === 'hidden' ? 'selected' : '' ?>>Hidden</option>
                            </select>
                            <button type="submit">Save</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>

            <aside class="panel">
                <h2>Recent Replies</h2>
                <?php foreach ($replies as $reply): ?>
                    <div class="panel" style="margin-bottom: 0.9rem;">
                        <p><?= htmlspecialchars($reply['body']) ?></p>
                        <p class="muted">By <?= htmlspecialchars($reply['name']) ?> | <?= htmlspecialchars($reply['created_at']) ?> | <?= htmlspecialchars($reply['status']) ?></p>
                        <form method="post" class="inline-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="type" value="reply">
                            <input type="hidden" name="id" value="<?= (int) $reply['id'] ?>">
                            <select name="status">
                                <option value="visible" <?= $reply['status'] === 'visible' ? 'selected' : '' ?>>Visible</option>
                                <option value="hidden" <?= $reply['status'] === 'hidden' ? 'selected' : '' ?>>Hidden</option>
                            </select>
                            <button type="submit">Save</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </aside>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
