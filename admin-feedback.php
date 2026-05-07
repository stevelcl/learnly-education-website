<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-shell.php';
require_once __DIR__ . '/includes/csrf.php';

$user = require_admin();
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['q'] ?? '');

$noticeMap = [
    'published' => 'Feedback published.',
    'hidden' => 'Feedback hidden from learners.',
    'flagged' => 'Feedback flagged for follow-up.',
    'deleted' => 'Feedback removed from public view.',
];
$message = $noticeMap[$_GET['notice'] ?? ''] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $feedbackId = (int) ($_POST['feedback_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $redirectBase = app_url_with_query(app_url('admin/feedback'), ['filter' => $filter, 'q' => $search]);

    if ($feedbackId > 0) {
        if ($action === 'publish') {
            db()->prepare(
                'UPDATE course_reviews
                 SET moderation_status = "published", deleted_at = NULL
                 WHERE id = ?'
            )->execute([$feedbackId]);
            header('Location: ' . $redirectBase . '&notice=published');
            exit;
        }

        if ($action === 'hide') {
            db()->prepare(
                'UPDATE course_reviews
                 SET moderation_status = "hidden"
                 WHERE id = ?'
            )->execute([$feedbackId]);
            header('Location: ' . $redirectBase . '&notice=hidden');
            exit;
        }

        if ($action === 'flag') {
            db()->prepare(
                'UPDATE course_reviews
                 SET moderation_status = "flagged"
                 WHERE id = ?'
            )->execute([$feedbackId]);
            header('Location: ' . $redirectBase . '&notice=flagged');
            exit;
        }

        if ($action === 'delete') {
            db()->prepare(
                'UPDATE course_reviews
                 SET moderation_status = "removed", deleted_at = NOW()
                 WHERE id = ?'
            )->execute([$feedbackId]);
            header('Location: ' . $redirectBase . '&notice=deleted');
            exit;
        }
    }

    header('Location: ' . $redirectBase);
    exit;
}

$allowedFilters = ['all', '5star', 'low', 'hidden', 'flagged'];
if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'all';
}

$where = ['1=1'];
$params = [];
if ($filter === '5star') {
    $where[] = 'cr.rating = 5';
}
if ($filter === 'low') {
    $where[] = 'cr.rating <= 2';
}
if ($filter === 'hidden') {
    $where[] = 'cr.moderation_status = "hidden"';
}
if ($filter === 'flagged') {
    $where[] = 'cr.moderation_status = "flagged"';
}
if ($search !== '') {
    $where[] = '(u.name LIKE ? OR u.email LIKE ? OR c.title LIKE ? OR cr.comment LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$feedbackRows = fetch_all(
    'SELECT
        cr.id,
        cr.rating,
        cr.comment,
        cr.moderation_status,
        cr.updated_at,
        u.name,
        u.email,
        c.id AS course_id,
        c.title,
        c.subject
     FROM course_reviews cr
     JOIN users u ON u.id = cr.user_id
     JOIN courses c ON c.id = cr.course_id
     WHERE ' . implode(' AND ', $where) . '
     ORDER BY
        CASE
            WHEN cr.moderation_status = "flagged" THEN 1
            WHEN cr.moderation_status = "hidden" THEN 2
            WHEN cr.moderation_status = "removed" THEN 3
            ELSE 4
        END,
        cr.updated_at DESC, cr.id DESC',
    $params
);

admin_render_start([
    'title' => 'Course Feedback',
    'page_title' => 'Feedback',
    'page_subtitle' => 'Moderate learner reviews, hide weak-fit comments, and keep published course feedback trustworthy.',
    'active_nav' => 'feedback',
    'breadcrumbs' => [
        ['label' => 'Dashboard', 'href' => app_url('admin')],
        ['label' => 'Feedback'],
    ],
    'notice' => $message,
    'user' => $user,
]);
?>

<section class="panel">
    <form class="admin-filter-bar" method="get">
        <label>Search
            <input type="search" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search student, course, or comment">
        </label>
        <label>Filter
            <select name="filter">
                <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All feedback</option>
                <option value="5star" <?= $filter === '5star' ? 'selected' : '' ?>>5-star</option>
                <option value="low" <?= $filter === 'low' ? 'selected' : '' ?>>Low rating</option>
                <option value="hidden" <?= $filter === 'hidden' ? 'selected' : '' ?>>Hidden</option>
                <option value="flagged" <?= $filter === 'flagged' ? 'selected' : '' ?>>Flagged</option>
            </select>
        </label>
        <div class="form-actions">
            <button type="submit">Apply</button>
            <a class="button ghost" href="<?= htmlspecialchars(app_url('admin/feedback')) ?>">Reset</a>
        </div>
    </form>
</section>

<section class="panel admin-data-table">
    <?php if (!$feedbackRows): ?>
        <div class="admin-empty-state"><strong>No feedback found</strong><span>Reviews will appear here after learners complete their courses.</span></div>
    <?php else: ?>
        <table class="admin-compact-table">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Course</th>
                    <th>Rating</th>
                    <th>Comment Preview</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($feedbackRows as $feedback): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($feedback['name']) ?></strong><br><span class="muted"><?= htmlspecialchars($feedback['email']) ?></span></td>
                        <td><?= htmlspecialchars($feedback['title']) ?><br><span class="muted"><?= htmlspecialchars($feedback['subject']) ?></span></td>
                        <td><?= str_repeat('★', (int) $feedback['rating']) ?><br><span class="muted"><?= (int) $feedback['rating'] ?>/5</span></td>
                        <td><?= htmlspecialchars(mb_strimwidth((string) ($feedback['comment'] ?: 'Rated without a written comment.'), 0, 92, '...')) ?></td>
                        <td><span class="status-pill status-<?= htmlspecialchars($feedback['moderation_status']) ?>"><?= htmlspecialchars(ucfirst($feedback['moderation_status'])) ?></span></td>
                        <td><?= htmlspecialchars((string) $feedback['updated_at']) ?></td>
                        <td>
                            <div class="admin-table-actions">
                                <a class="button ghost small" href="<?= htmlspecialchars(course_url((int) $feedback['course_id'], true)) ?>">View Course</a>
                                <form method="post" class="inline-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="feedback_id" value="<?= (int) $feedback['id'] ?>">
                                    <input type="hidden" name="action" value="publish">
                                    <button type="submit" class="button ghost small">Publish</button>
                                </form>
                                <form method="post" class="inline-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="feedback_id" value="<?= (int) $feedback['id'] ?>">
                                    <input type="hidden" name="action" value="hide">
                                    <button type="submit" class="button ghost small" data-confirm="Hide this feedback from learners?">Hide</button>
                                </form>
                                <form method="post" class="inline-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="feedback_id" value="<?= (int) $feedback['id'] ?>">
                                    <input type="hidden" name="action" value="flag">
                                    <button type="submit" class="button ghost small">Flag</button>
                                </form>
                                <form method="post" class="inline-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="feedback_id" value="<?= (int) $feedback['id'] ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="button danger small" data-confirm="Remove this feedback from learner-facing course pages?">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<?php admin_render_end(); ?>
