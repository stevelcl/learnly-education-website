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
    'SELECT p.id, p.title, p.body, p.status, p.created_at, u.name,
            c.title AS course_title
     FROM forum_posts p
     JOIN users u ON u.id = p.user_id
     LEFT JOIN courses c ON c.id = p.course_id
     ORDER BY p.created_at DESC
     LIMIT 20'
);

$replies = fetch_all(
    'SELECT r.id, r.post_id, r.body, r.status, r.created_at, u.name
     FROM forum_replies r
     JOIN users u ON u.id = r.user_id
     ORDER BY r.created_at DESC'
);

$repliesByPost = [];
foreach ($replies as $reply) {
    $postId = (int) $reply['post_id'];
    if (!isset($repliesByPost[$postId])) {
        $repliesByPost[$postId] = [];
    }
    $repliesByPost[$postId][] = $reply;
}

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

        <div class="grid">
            <?php foreach ($posts as $post): ?>
                <article class="panel">
                    <div class="card-topline">
                        <span class="tag <?= $post['status'] === 'hidden' ? 'warn' : '' ?>"><?= htmlspecialchars($post['status']) ?></span>
                        <span class="muted"><?= htmlspecialchars($post['created_at']) ?></span>
                    </div>
                    <h2><?= htmlspecialchars($post['title']) ?></h2>
                    <p><?= nl2br(htmlspecialchars($post['body'])) ?></p>
                    <p class="muted">By <?= htmlspecialchars($post['name']) ?><?= $post['course_title'] ? ' | ' . htmlspecialchars($post['course_title']) : '' ?></p>
                    <form method="post" class="inline-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="type" value="post">
                        <input type="hidden" name="id" value="<?= (int) $post['id'] ?>">
                        <select name="status">
                            <option value="visible" <?= $post['status'] === 'visible' ? 'selected' : '' ?>>Visible</option>
                            <option value="hidden" <?= $post['status'] === 'hidden' ? 'selected' : '' ?>>Hidden</option>
                        </select>
                        <button type="submit">Save Post</button>
                    </form>

                    <div style="margin-top: 1rem;">
                        <h3>Replies</h3>
                        <?php $postReplies = $repliesByPost[(int) $post['id']] ?? []; ?>
                        <?php if (!$postReplies): ?>
                            <p class="muted">No replies for this post yet.</p>
                        <?php endif; ?>
                        <?php foreach ($postReplies as $reply): ?>
                            <div class="panel" style="margin-top: 0.8rem;">
                                <p><?= nl2br(htmlspecialchars($reply['body'])) ?></p>
                                <p class="muted">By <?= htmlspecialchars($reply['name']) ?> | <?= htmlspecialchars($reply['created_at']) ?> | <?= htmlspecialchars($reply['status']) ?></p>
                                <form method="post" class="inline-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="type" value="reply">
                                    <input type="hidden" name="id" value="<?= (int) $reply['id'] ?>">
                                    <select name="status">
                                        <option value="visible" <?= $reply['status'] === 'visible' ? 'selected' : '' ?>>Visible</option>
                                        <option value="hidden" <?= $reply['status'] === 'hidden' ? 'selected' : '' ?>>Hidden</option>
                                    </select>
                                    <button type="submit">Save Reply</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
