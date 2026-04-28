<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
$user = require_admin();

$message = '';
$editingId = (int) ($_GET['edit'] ?? 0);
$editing = $editingId ? fetch_one('SELECT * FROM courses WHERE id = ?', [$editingId]) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $stmt = db()->prepare('DELETE FROM courses WHERE id = ?');
        $stmt->execute([(int) $_POST['course_id']]);
        $message = 'Course deleted.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $level = trim($_POST['level'] ?? '');

        if ($title !== '' && $subject !== '' && $description !== '' && $level !== '') {
            if ($action === 'update') {
                $stmt = db()->prepare('UPDATE courses SET title = ?, subject = ?, description = ?, level = ? WHERE id = ?');
                $stmt->execute([$title, $subject, $description, $level, (int) $_POST['course_id']]);
                $message = 'Course updated.';
            } else {
                $stmt = db()->prepare('INSERT INTO courses (title, subject, description, level) VALUES (?, ?, ?, ?)');
                $stmt->execute([$title, $subject, $description, $level]);
                $message = 'Course added.';
            }
        }
    }
}

$courses = fetch_all(
    'SELECT c.*, COUNT(cr.id) AS resource_count
     FROM courses c
     LEFT JOIN course_resources cr ON cr.course_id = c.id
     GROUP BY c.id
     ORDER BY c.created_at DESC'
);

$pageTitle = 'Manage Courses';
include __DIR__ . '/includes/header.php';
?>

<section class="section">
    <div class="container layout">
        <div>
            <div class="section-head">
                <div>
                    <span class="eyebrow">Admin Courses</span>
                    <h1>Create, edit, and remove course modules.</h1>
                </div>
                <a class="button ghost" href="admin-dashboard.php">Back to Admin</a>
            </div>
            <?php if ($message): ?><p class="alert success"><?= htmlspecialchars($message) ?></p><?php endif; ?>

            <div class="grid">
                <?php foreach ($courses as $course): ?>
                    <article class="panel">
                        <div class="card-topline">
                            <span class="tag"><?= htmlspecialchars($course['subject']) ?></span>
                            <span class="muted"><?= (int) $course['resource_count'] ?> resources</span>
                        </div>
                        <h2><?= htmlspecialchars($course['title']) ?></h2>
                        <p><?= htmlspecialchars($course['description']) ?></p>
                        <p class="muted"><?= htmlspecialchars($course['level']) ?></p>
                        <div class="actions">
                            <a class="button small ghost" href="admin-courses.php?edit=<?= (int) $course['id'] ?>">Edit</a>
                            <form method="post" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="course_id" value="<?= (int) $course['id'] ?>">
                                <button class="button small danger" type="submit">Delete</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>

        <aside class="panel">
            <h2><?= $editing ? 'Edit Course' : 'Add Course' ?></h2>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
                <input type="hidden" name="course_id" value="<?= (int) ($editing['id'] ?? 0) ?>">
                <label>Title <input name="title" value="<?= htmlspecialchars($editing['title'] ?? '') ?>" required></label>
                <label>Subject <input name="subject" value="<?= htmlspecialchars($editing['subject'] ?? '') ?>" required></label>
                <label>Level <input name="level" value="<?= htmlspecialchars($editing['level'] ?? '') ?>" required></label>
                <label>Description <textarea name="description" required><?= htmlspecialchars($editing['description'] ?? '') ?></textarea></label>
                <button type="submit"><?= $editing ? 'Update Course' : 'Add Course' ?></button>
            </form>
        </aside>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
