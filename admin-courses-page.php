<?php
session_start();
require_once __DIR__ . '/includes/admin-course-manager.php';
require_once __DIR__ . '/includes/admin-shell.php';
require_once __DIR__ . '/includes/csrf.php';

$user = require_admin();
$noticeMap = [
    'deleted' => 'Course deleted.',
    'created' => 'Course created. You can now refine the overview and resources.',
    'resources_in_courses' => 'Resources are managed inside each course.',
];
$message = $noticeMap[$_GET['notice'] ?? ''] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (($_POST['action'] ?? '') === 'delete_course') {
        $courseId = (int) ($_POST['course_id'] ?? 0);
        if ($courseId > 0) {
            $stmt = db()->prepare('DELETE FROM courses WHERE id = ?');
            $stmt->execute([$courseId]);
        }
        header('Location: ' . admin_courses_url() . '?notice=deleted');
        exit;
    }
}

$courses = admin_fetch_course_catalog();

admin_render_start([
    'title' => 'Course Library',
    'page_title' => 'Courses',
    'page_subtitle' => 'Browse the full catalog, then open dedicated course workspaces for editing.',
    'active_nav' => 'courses',
    'breadcrumbs' => [
        ['label' => 'Dashboard', 'href' => app_url('admin')],
        ['label' => 'Courses'],
    ],
    'actions' => [
        ['label' => 'Create Course', 'href' => app_url('admin/course/new')],
    ],
    'notice' => $message,
    'user' => $user,
]);
?>

<section class="admin-course-grid">
    <?php foreach ($courses as $course): ?>
        <article class="panel admin-course-card">
            <div class="card-topline">
                <span class="tag"><?= htmlspecialchars($course['subject']) ?></span>
                <span class="muted"><?= htmlspecialchars($course['level']) ?></span>
            </div>
            <h2><?= htmlspecialchars($course['title']) ?></h2>
            <p class="muted"><?= (int) $course['resource_count'] ?> resources | <?= (int) $course['quiz_count'] ?> quizzes</p>
            <div class="admin-course-metrics">
                <span><strong><?= (int) $course['enrollment_count'] ?></strong> enrolled</span>
                <span><strong><?= number_format((float) $course['average_rating'], 1) ?></strong> rating</span>
            </div>
            <div class="actions">
                <a class="button" href="<?= htmlspecialchars(admin_course_url((int) $course['id'])) ?>">Manage Course</a>
                <a class="button ghost" href="<?= htmlspecialchars(admin_course_preview_url((int) $course['id'])) ?>">Preview</a>
                <form method="post" class="inline-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete_course">
                    <input type="hidden" name="course_id" value="<?= (int) $course['id'] ?>">
                    <button class="button danger" type="submit" data-confirm="Delete this course and all of its resources?">Delete</button>
                </form>
            </div>
        </article>
    <?php endforeach; ?>
</section>

<?php admin_render_end(); ?>
