<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-shell.php';
require_once __DIR__ . '/includes/csrf.php';

$user = require_admin();
$view = $_GET['view'] ?? 'all';
$sort = $_GET['sort'] ?? 'newest';
$search = trim($_GET['q'] ?? '');

$messageMap = [
    'saved' => 'Moderation update saved.',
    'deleted' => 'Forum content removed.',
];
$message = $messageMap[$_GET['notice'] ?? ''] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $postId = (int) ($_POST['post_id'] ?? 0);
    $replyId = (int) ($_POST['reply_id'] ?? 0);

    if ($action === 'delete_post' && $postId > 0) {
        db()->prepare('DELETE FROM forum_posts WHERE id = ?')->execute([$postId]);
        header('Location: ' . app_url_with_query(app_url('admin/forum'), ['notice' => 'deleted', 'view' => $view, 'sort' => $sort, 'q' => $search]));
        exit;
    }
    if ($action === 'delete_reply' && $replyId > 0) {
        db()->prepare('DELETE FROM forum_replies WHERE id = ?')->execute([$replyId]);
        header('Location: ' . app_url_with_query(app_url('admin/forum'), ['notice' => 'deleted', 'view' => $view, 'sort' => $sort, 'q' => $search]));
        exit;
    }
    if ($action === 'toggle_status' && $postId > 0) {
        $status = ($_POST['status'] ?? '') === 'hidden' ? 'hidden' : 'visible';
        db()->prepare('UPDATE forum_posts SET status = ? WHERE id = ?')->execute([$status, $postId]);
    }
    if ($action === 'toggle_pin' && $postId > 0) {
        db()->prepare('UPDATE forum_posts SET is_pinned = CASE WHEN is_pinned = 1 THEN 0 ELSE 1 END WHERE id = ?')->execute([$postId]);
    }
    if ($action === 'toggle_feature' && $postId > 0) {
        db()->prepare('UPDATE forum_posts SET is_featured = CASE WHEN is_featured = 1 THEN 0 ELSE 1 END WHERE id = ?')->execute([$postId]);
    }
    if ($action === 'toggle_lock' && $postId > 0) {
        db()->prepare('UPDATE forum_posts SET replies_locked = CASE WHEN replies_locked = 1 THEN 0 ELSE 1 END WHERE id = ?')->execute([$postId]);
    }
    if ($action === 'save_post' && $postId > 0) {
        $title = trim($_POST['title'] ?? '');
        $body = trim($_POST['body'] ?? '');
        $category = trim($_POST['category'] ?? '');
        db()->prepare('UPDATE forum_posts SET title = ?, body = ?, category = ? WHERE id = ?')->execute([$title, $body, $category, $postId]);
    }
    if ($action === 'toggle_reply_status' && $replyId > 0) {
        $status = ($_POST['status'] ?? '') === 'hidden' ? 'hidden' : 'visible';
        db()->prepare('UPDATE forum_replies SET status = ? WHERE id = ?')->execute([$status, $replyId]);
    }

    header('Location: ' . app_url_with_query(app_url('admin/forum'), ['notice' => 'saved', 'view' => $view, 'sort' => $sort, 'q' => $search]));
    exit;
}

$allowedViews = ['all', 'reported', 'hidden', 'replies', 'pending'];
if (!in_array($view, $allowedViews, true)) {
    $view = 'all';
}
$allowedSorts = ['newest', 'reported', 'unanswered', 'hidden', 'pinned'];
if (!in_array($sort, $allowedSorts, true)) {
    $sort = 'newest';
}

$where = [];
$params = [];
if ($view === 'reported') {
    $where[] = 'p.report_count > 0';
}
if ($view === 'hidden') {
    $where[] = 'p.status = "hidden"';
}
if ($view === 'pending') {
    $where[] = 'p.report_count > 0 AND p.status = "visible"';
}
if ($view === 'replies') {
    $where[] = 'reply_stats.reply_count > 0';
}
if ($search !== '') {
    $where[] = '(p.title LIKE ? OR p.body LIKE ? OR u.name LIKE ? OR COALESCE(p.category, "") LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like);
}

$sortSql = match ($sort) {
    'reported' => 'p.report_count DESC, p.created_at DESC',
    'unanswered' => 'reply_stats.reply_count ASC, p.created_at DESC',
    'hidden' => 'CASE WHEN p.status = "hidden" THEN 0 ELSE 1 END, p.created_at DESC',
    'pinned' => 'p.is_pinned DESC, p.created_at DESC',
    default => 'p.created_at DESC',
};

$posts = fetch_all(
    'SELECT
        p.*,
        u.name,
        c.title AS course_title,
        COALESCE(reply_stats.reply_count, 0) AS reply_count
     FROM forum_posts p
     JOIN users u ON u.id = p.user_id
     LEFT JOIN courses c ON c.id = p.course_id
     LEFT JOIN (
        SELECT post_id, COUNT(*) AS reply_count
        FROM forum_replies
        GROUP BY post_id
     ) reply_stats ON reply_stats.post_id = p.id
     ' . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . '
     ORDER BY ' . $sortSql . '
     LIMIT 80',
    $params
);

$replyRows = fetch_all(
    'SELECT r.*, u.name
     FROM forum_replies r
     JOIN users u ON u.id = r.user_id
     ORDER BY r.created_at DESC'
);
$repliesByPost = [];
foreach ($replyRows as $reply) {
    $repliesByPost[(int) $reply['post_id']][] = $reply;
}

$stats = [
    'posts' => (int) (fetch_one('SELECT COUNT(*) AS total FROM forum_posts')['total'] ?? 0),
    'hidden' => (int) (fetch_one('SELECT COUNT(*) AS total FROM forum_posts WHERE status = "hidden"')['total'] ?? 0),
    'reported' => (int) (fetch_one('SELECT COUNT(*) AS total FROM forum_posts WHERE report_count > 0')['total'] ?? 0),
    'active' => (int) (fetch_one('SELECT COUNT(*) AS total FROM forum_posts WHERE status = "visible"')['total'] ?? 0),
];

admin_render_start([
    'title' => 'Forum Moderation',
    'page_title' => 'Forum Moderation',
    'page_subtitle' => 'Moderate posts, replies, visibility, and thread behavior from one compact queue.',
    'active_nav' => 'forum',
    'breadcrumbs' => [
        ['label' => 'Dashboard', 'href' => app_url('admin')],
        ['label' => 'Forum'],
    ],
    'notice' => $message,
    'user' => $user,
]);
?>

<section class="admin-stats-row">
    <article class="panel admin-stat-card"><strong><?= $stats['posts'] ?></strong><span class="muted">Total posts</span></article>
    <article class="panel admin-stat-card"><strong><?= $stats['hidden'] ?></strong><span class="muted">Hidden posts</span></article>
    <article class="panel admin-stat-card"><strong><?= $stats['reported'] ?></strong><span class="muted">Reported posts</span></article>
    <article class="panel admin-stat-card"><strong><?= $stats['active'] ?></strong><span class="muted">Active discussions</span></article>
    <article class="panel admin-stat-card"><strong><?= count($replyRows) ?></strong><span class="muted">Replies</span></article>
</section>

<section class="panel">
    <form class="admin-filter-bar" method="get">
        <label>Search
            <input name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Title, content, user, category">
        </label>
        <label>View
            <select name="view">
                <option value="all" <?= $view === 'all' ? 'selected' : '' ?>>All Posts</option>
                <option value="reported" <?= $view === 'reported' ? 'selected' : '' ?>>Reported Posts</option>
                <option value="hidden" <?= $view === 'hidden' ? 'selected' : '' ?>>Hidden Posts</option>
                <option value="replies" <?= $view === 'replies' ? 'selected' : '' ?>>Replies</option>
                <option value="pending" <?= $view === 'pending' ? 'selected' : '' ?>>Pending Review</option>
            </select>
        </label>
        <label>Sort
            <select name="sort">
                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
                <option value="reported" <?= $sort === 'reported' ? 'selected' : '' ?>>Most Reported</option>
                <option value="unanswered" <?= $sort === 'unanswered' ? 'selected' : '' ?>>Unanswered</option>
                <option value="hidden" <?= $sort === 'hidden' ? 'selected' : '' ?>>Hidden First</option>
                <option value="pinned" <?= $sort === 'pinned' ? 'selected' : '' ?>>Pinned First</option>
            </select>
        </label>
        <div class="admin-table-actions">
            <button type="submit">Apply</button>
            <a class="button ghost" href="<?= htmlspecialchars(app_url('admin/forum')) ?>">Reset</a>
        </div>
    </form>
</section>

<section class="admin-section-grid">
    <?php if (!$posts): ?>
        <div class="panel admin-empty-state"><strong>No posts found</strong><span>Try another filter or search term.</span></div>
    <?php endif; ?>
    <?php foreach ($posts as $post): ?>
        <?php $postReplies = $repliesByPost[(int) $post['id']] ?? []; ?>
        <article class="panel admin-feedback-card">
            <div class="card-topline">
                <div class="admin-table-actions">
                    <span class="tag <?= $post['status'] === 'visible' ? 'good' : '' ?>"><?= htmlspecialchars($post['status']) ?></span>
                    <?php if ((int) $post['report_count'] > 0): ?><span class="tag danger"><?= (int) $post['report_count'] ?> reports</span><?php endif; ?>
                    <?php if (!empty($post['is_pinned'])): ?><span class="tag">Pinned</span><?php endif; ?>
                    <?php if (!empty($post['is_featured'])): ?><span class="tag course-badge">Featured</span><?php endif; ?>
                    <?php if (!empty($post['replies_locked'])): ?><span class="tag warn">Locked</span><?php endif; ?>
                </div>
                <span class="muted"><?= htmlspecialchars($post['created_at']) ?></span>
            </div>
            <div class="admin-panel-header">
                <div>
                    <h2><?= htmlspecialchars($post['title']) ?></h2>
                    <p class="muted">By <?= htmlspecialchars($post['name']) ?><?= $post['course_title'] ? ' | ' . htmlspecialchars($post['course_title']) : '' ?><?= !empty($post['category']) ? ' | ' . htmlspecialchars($post['category']) : '' ?> | <?= (int) $post['reply_count'] ?> replies</p>
                </div>
                <a class="button ghost small" href="<?= htmlspecialchars(app_url('post.php?id=' . (int) $post['id'])) ?>">View full thread</a>
            </div>
            <p><?= nl2br(htmlspecialchars($post['body'])) ?></p>

            <div class="admin-table-actions">
                <form method="post" class="inline-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>">
                    <input type="hidden" name="status" value="<?= $post['status'] === 'visible' ? 'hidden' : 'visible' ?>">
                    <button class="button small ghost" type="submit"><?= $post['status'] === 'visible' ? 'Hide' : 'Unhide' ?></button>
                </form>
                <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_pin"><input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>"><button class="button small ghost" type="submit"><?= !empty($post['is_pinned']) ? 'Unpin' : 'Pin' ?></button></form>
                <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_lock"><input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>"><button class="button small ghost" type="submit"><?= !empty($post['replies_locked']) ? 'Unlock Replies' : 'Lock Replies' ?></button></form>
                <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_feature"><input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>"><button class="button small ghost" type="submit"><?= !empty($post['is_featured']) ? 'Unfeature' : 'Feature' ?></button></form>
                <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="delete_post"><input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>"><button class="button small danger" type="submit" data-confirm="Delete this post and all replies?">Delete</button></form>
            </div>

            <details>
                <summary>Edit post content</summary>
                <form method="post" class="admin-course-form" style="margin-top:0.85rem;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save_post">
                    <input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>">
                    <div class="admin-form-grid">
                        <label>Title <input name="title" value="<?= htmlspecialchars($post['title']) ?>"></label>
                        <label>Category <input name="category" value="<?= htmlspecialchars((string) ($post['category'] ?? '')) ?>"></label>
                        <label class="admin-form-span-2">Content <textarea name="body"><?= htmlspecialchars($post['body']) ?></textarea></label>
                    </div>
                    <button type="submit">Save Post</button>
                </form>
            </details>

            <details>
                <summary>Replies (<?= count($postReplies) ?>)</summary>
                <div class="admin-mini-list" style="margin-top:0.85rem;">
                    <?php if (!$postReplies): ?>
                        <div class="admin-empty-state" style="min-height:120px;"><strong>No replies</strong><span>This thread has not received replies yet.</span></div>
                    <?php endif; ?>
                    <?php foreach ($postReplies as $reply): ?>
                        <article class="admin-mini-row" style="align-items:start;">
                            <div style="min-width:0;">
                                <strong><?= htmlspecialchars($reply['name']) ?></strong>
                                <div class="muted"><?= htmlspecialchars($reply['created_at']) ?> | <?= htmlspecialchars($reply['status']) ?></div>
                                <p style="margin:0.5rem 0 0;"><?= nl2br(htmlspecialchars($reply['body'])) ?></p>
                            </div>
                            <div class="admin-table-actions">
                                <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_reply_status"><input type="hidden" name="reply_id" value="<?= (int) $reply['id'] ?>"><input type="hidden" name="status" value="<?= $reply['status'] === 'visible' ? 'hidden' : 'visible' ?>"><button class="button small ghost" type="submit"><?= $reply['status'] === 'visible' ? 'Hide' : 'Unhide' ?></button></form>
                                <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="delete_reply"><input type="hidden" name="reply_id" value="<?= (int) $reply['id'] ?>"><button class="button small danger" type="submit" data-confirm="Delete this reply?">Delete</button></form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </details>
        </article>
    <?php endforeach; ?>
</section>

<?php admin_render_end(); ?>
