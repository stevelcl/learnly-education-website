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

    $action = $_POST['action'] ?? '';

    if ($action === 'delete_post') {
        if (is_admin($user) || (int) $post['user_id'] === (int) $user['id']) {
            $stmt = db()->prepare('DELETE FROM forum_posts WHERE id = ?');
            $stmt->execute([$postId]);
            header('Location: forum.php');
            exit;
        }
    } elseif ($action === 'delete_reply') {
        $replyId = (int) ($_POST['reply_id'] ?? 0);
        $replyRow = fetch_one('SELECT user_id FROM forum_replies WHERE id = ? AND post_id = ?', [$replyId, $postId]);
        if ($replyRow && (is_admin($user) || (int) $replyRow['user_id'] === (int) $user['id'])) {
            $stmt = db()->prepare('DELETE FROM forum_replies WHERE id = ?');
            $stmt->execute([$replyId]);
        }
    } elseif ($action === 'moderate_reply' && is_moderator($user)) {
        $replyId = (int) ($_POST['reply_id'] ?? 0);
        $status = ($_POST['status'] ?? '') === 'hidden' ? 'hidden' : 'visible';
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
                <?php if ($user && (is_admin($user) || (int) $post['user_id'] === (int) $user['id'])): ?>
                    <form method="post" class="inline-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_post">
                        <button class="button small danger" type="submit" data-confirm="Delete this post and all replies?">Delete Post</button>
                    </form>
                <?php endif; ?>
            </article>

            <h2>Replies</h2>
            <?php foreach ($replies as $reply): ?>
                <article class="panel">
                    <p><?= nl2br(htmlspecialchars($reply['body'])) ?></p>
                    <p class="muted">By <?= htmlspecialchars($reply['name']) ?> | <?= htmlspecialchars($reply['status']) ?></p>
                    <div class="actions">
                        <?php if ($user && (is_admin($user) || (int) $reply['user_id'] === (int) $user['id'])): ?>
                            <form method="post" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_reply">
                                <input type="hidden" name="reply_id" value="<?= (int) $reply['id'] ?>">
                                <button class="button small danger" type="submit" data-confirm="Delete this comment?">Delete Comment</button>
                            </form>
                        <?php endif; ?>
                        <?php if (is_moderator($user)): ?>
                            <form method="post" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="moderate_reply">
                                <input type="hidden" name="reply_id" value="<?= (int) $reply['id'] ?>">
                                <select name="status">
                                    <option value="visible" <?= $reply['status'] === 'visible' ? 'selected' : '' ?>>Visible</option>
                                    <option value="hidden" <?= $reply['status'] === 'hidden' ? 'selected' : '' ?>>Hidden</option>
                                </select>
                                <button type="submit">Update</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <aside class="panel">
            <h2>Reply</h2>
            <?php if ($user): ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create_reply">
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
