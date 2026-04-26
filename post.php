<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

$user = current_user();
$postId = (int) ($_GET['id'] ?? 0);
$post = fetch_one(
    'SELECT p.*, u.name, c.title AS course_title
     FROM forum_posts p
     JOIN users u ON u.id = p.user_id
     LEFT JOIN courses c ON c.id = p.course_id
     WHERE p.id = ?',
    [$postId]
);

if (!$post || ($post['status'] === 'hidden' && !is_moderator($user))) {
    http_response_code(404);
    exit('Post not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$user) {
        header('Location: login.php');
        exit;
    }
    verify_csrf();

    if (isset($_POST['moderate_reply']) && is_moderator($user)) {
        $replyId = (int) $_POST['reply_id'];
        $status = $_POST['status'] === 'hidden' ? 'hidden' : 'visible';
        $stmt = db()->prepare('UPDATE forum_replies SET status = ? WHERE id = ?');
        $stmt->execute([$status, $replyId]);
    } else {
        $body = trim($_POST['body'] ?? '');
        if ($body !== '') {
            $stmt = db()->prepare('INSERT INTO forum_replies (post_id, user_id, body) VALUES (?, ?, ?)');
            $stmt->execute([$postId, $user['id'], $body]);
        }
    }

    header('Location: post.php?id=' . $postId);
    exit;
}

$replies = fetch_all(
    'SELECT r.*, u.name
     FROM forum_replies r
     JOIN users u ON u.id = r.user_id
     WHERE r.post_id = ? AND (r.status = "visible" OR ? = 1)
     ORDER BY r.created_at',
    [$postId, is_moderator($user) ? 1 : 0]
);

$pageTitle = $post['title'];
include __DIR__ . '/includes/header.php';
?>

<section class="section">
    <div class="container layout">
        <div>
            <article class="panel">
                <span class="tag"><?= htmlspecialchars($post['course_title'] ?: 'General') ?></span>
                <h1><?= htmlspecialchars($post['title']) ?></h1>
                <p><?= nl2br(htmlspecialchars($post['body'])) ?></p>
                <p class="muted">By <?= htmlspecialchars($post['name']) ?> on <?= htmlspecialchars($post['created_at']) ?></p>
            </article>

            <h2>Replies</h2>
            <?php foreach ($replies as $reply): ?>
                <article class="panel">
                    <p><?= nl2br(htmlspecialchars($reply['body'])) ?></p>
                    <p class="muted">By <?= htmlspecialchars($reply['name']) ?> · <?= htmlspecialchars($reply['status']) ?></p>
                    <?php if (is_moderator($user)): ?>
                        <form method="post" class="inline-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="moderate_reply" value="1">
                            <input type="hidden" name="reply_id" value="<?= (int) $reply['id'] ?>">
                            <select name="status">
                                <option value="visible" <?= $reply['status'] === 'visible' ? 'selected' : '' ?>>Visible</option>
                                <option value="hidden" <?= $reply['status'] === 'hidden' ? 'selected' : '' ?>>Hidden</option>
                            </select>
                            <button type="submit">Update</button>
                        </form>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>

        <aside class="panel">
            <h2>Reply</h2>
            <?php if ($user): ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <label>Your answer <textarea name="body" required></textarea></label>
                    <button type="submit">Submit Reply</button>
                </form>
            <?php else: ?>
                <p><a href="login.php">Login</a> to reply.</p>
            <?php endif; ?>
        </aside>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

