<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-shell.php';

$user = require_admin();
$feedbackRows = fetch_all(
    'SELECT
        cr.id,
        cr.rating,
        cr.comment,
        cr.updated_at,
        u.name,
        u.email,
        c.title,
        c.subject
     FROM course_reviews cr
     JOIN users u ON u.id = cr.user_id
     JOIN courses c ON c.id = cr.course_id
     ORDER BY cr.updated_at DESC, cr.id DESC'
);

admin_render_start([
    'title' => 'Course Feedback',
    'page_title' => 'Feedback',
    'page_subtitle' => 'Review learner ratings and comments without mixing them into analytics tables.',
    'active_nav' => 'feedback',
    'breadcrumbs' => [
        ['label' => 'Dashboard', 'href' => app_url('admin')],
        ['label' => 'Feedback'],
    ],
    'user' => $user,
]);
?>

<section class="admin-section-grid">
    <?php if (!$feedbackRows): ?>
        <div class="panel admin-empty-state"><strong>No feedback yet</strong><span>Completed course reviews will appear here.</span></div>
    <?php else: ?>
        <?php foreach ($feedbackRows as $feedback): ?>
            <article class="panel admin-feedback-card">
                <div class="card-topline">
                    <strong><?= htmlspecialchars($feedback['name']) ?></strong>
                    <span class="tag"><?= (int) $feedback['rating'] ?>/5</span>
                </div>
                <p class="muted"><?= htmlspecialchars($feedback['email']) ?> | <?= htmlspecialchars($feedback['title']) ?> | <?= htmlspecialchars($feedback['updated_at']) ?></p>
                <p><?= $feedback['comment'] !== '' ? nl2br(htmlspecialchars((string) $feedback['comment'])) : 'Rated without a written comment.' ?></p>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<?php admin_render_end(); ?>
