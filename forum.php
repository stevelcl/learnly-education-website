<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

$user = current_user();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$user) {
        header('Location: login.php');
        exit;
    }
    verify_csrf();

    if (isset($_POST['moderate']) && is_moderator($user)) {
        $postId = (int) $_POST['post_id'];
        $status = $_POST['status'] === 'hidden' ? 'hidden' : 'visible';
        $stmt = db()->prepare('UPDATE forum_posts SET status = ? WHERE id = ?');
        $stmt->execute([$status, $postId]);
        $message = 'Post moderation updated.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $body = trim($_POST['body'] ?? '');
        $courseId = (int) ($_POST['course_id'] ?? 0);

        if ($title === '' || $body === '') {
            $error = 'Please enter a title and question.';
        } else {
            $stmt = db()->prepare('INSERT INTO forum_posts (user_id, course_id, title, body) VALUES (?, ?, ?, ?)');
            $stmt->execute([$user['id'], $courseId ?: null, $title, $body]);
            header('Location: forum.php');
            exit;
        }
    }
}

$courses = fetch_all('SELECT id, title FROM courses ORDER BY title');
$posts = fetch_all(
    'SELECT p.*, u.name, c.title AS course_title
     FROM forum_posts p
     JOIN users u ON u.id = p.user_id
     LEFT JOIN courses c ON c.id = p.course_id
     WHERE p.status = "visible" OR ? = 1
     ORDER BY p.created_at DESC',
    [is_moderator($user) ? 1 : 0]
);

$pageTitle = 'Forum';
include __DIR__ . '/includes/header.php';
?>

<section class="section">
    <div class="container layout">
        <div>
            <h1>Discussion Forum</h1>
            <?php if ($message): ?><p class="alert success"><?= htmlspecialchars($message) ?></p><?php endif; ?>
            <?php if ($error): ?><p class="alert error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
            <?php foreach ($posts as $post): ?>
                <article class="panel">
                    <span class="tag <?= $post['status'] === 'hidden' ? 'warn' : '' ?>"><?= htmlspecialchars($post['status']) ?></span>
                    <h2><a href="post.php?id=<?= (int) $post['id'] ?>"><?= htmlspecialchars($post['title']) ?></a></h2>
                    <?php $preview = strlen($post['body']) > 220 ? substr($post['body'], 0, 220) . '...' : $post['body']; ?>
                    <p><?= nl2br(htmlspecialchars($preview)) ?></p>
                    <p class="muted">By <?= htmlspecialchars($post['name']) ?> <?= $post['course_title'] ? '· ' . htmlspecialchars($post['course_title']) : '' ?></p>
                    <?php if (is_moderator($user)): ?>
                        <form method="post" class="inline-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="moderate" value="1">
                            <input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>">
                            <select name="status">
                                <option value="visible" <?= $post['status'] === 'visible' ? 'selected' : '' ?>>Visible</option>
                                <option value="hidden" <?= $post['status'] === 'hidden' ? 'selected' : '' ?>>Hidden</option>
                            </select>
                            <button class="small" type="submit">Update</button>
                        </form>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>

        <aside class="panel">
            <h2>Ask a Question</h2>
            <?php if ($user): ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <label>Course
                        <select name="course_id">
                            <option value="">General</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?= (int) $course['id'] ?>"><?= htmlspecialchars($course['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Title <input name="title" required></label>
                    <label>Question <textarea name="body" required></textarea></label>
                    <button type="submit">Post Question</button>
                </form>
            <?php else: ?>
                <p><a href="login.php">Login</a> to ask or answer questions.</p>
            <?php endif; ?>
        </aside>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
