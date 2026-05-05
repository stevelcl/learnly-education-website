<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/progress.php';
require_once __DIR__ . '/includes/courses.php';

$courseId = (int) ($_GET['id'] ?? 0);
$course = fetch_one('SELECT * FROM courses WHERE id = ?', [$courseId]);
if (!$course) {
    http_response_code(404);
    exit('Course not found.');
}

$user = current_user();
$adminPreview = isset($_GET['admin_preview']) && is_admin($user);

$resources = fetch_all(
    'SELECT * FROM course_resources
     WHERE course_id = ? AND resource_type <> "quiz"
     ORDER BY sort_order, id',
    [$courseId]
);
$questions = fetch_all('SELECT * FROM quiz_questions WHERE course_id = ? ORDER BY id', [$courseId]);

$courseInsights = fetch_one(
    'SELECT
        COUNT(DISTINCT CASE WHEN cr.resource_type <> "quiz" THEN cr.id END) AS module_count,
        COUNT(DISTINCT qq.id) AS quiz_count,
        COUNT(DISTINCT ce.id) AS enrollment_count,
        COALESCE(AVG(rv.rating), 0) AS average_rating,
        COUNT(DISTINCT rv.id) AS review_count
     FROM courses c
     LEFT JOIN course_resources cr ON cr.course_id = c.id
     LEFT JOIN quiz_questions qq ON qq.course_id = c.id
     LEFT JOIN course_enrollments ce ON ce.course_id = c.id
     LEFT JOIN course_reviews rv ON rv.course_id = c.id
     WHERE c.id = ?
     GROUP BY c.id',
    [$courseId]
);

$reviews = fetch_all(
    'SELECT rv.rating, rv.comment, rv.updated_at, u.name
     FROM course_reviews rv
     INNER JOIN users u ON u.id = rv.user_id
     WHERE rv.course_id = ?
     ORDER BY rv.updated_at DESC
     LIMIT 6',
    [$courseId]
);

$isEnrolled = $user ? is_course_enrolled((int) $user['id'], $courseId) : false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$user) {
        header('Location: login.php');
        exit;
    }

    verify_csrf();
    if (($_POST['action'] ?? '') === 'enroll') {
        enroll_in_course((int) $user['id'], $courseId);
        header('Location: ' . learn_url($courseId));
        exit;
    }
}

$averageRating = (float) ($courseInsights['average_rating'] ?? 0);
$reviewCount = (int) ($courseInsights['review_count'] ?? 0);
$enrollmentCount = (int) ($courseInsights['enrollment_count'] ?? 0);
$moduleCount = (int) ($courseInsights['module_count'] ?? 0);
$quizCount = (int) ($courseInsights['quiz_count'] ?? 0);
$estimatedMinutes = max(20, ($moduleCount * 14) + ($quizCount * 4));
$curriculum = course_curriculum_items($resources, $questions);
$learnPoints = course_learning_points($course, $resources, $questions);
$skillPoints = course_skill_points($course, $resources);
$audiencePoints = course_audience_points($course);
$badges = course_badges([
    'average_rating' => $averageRating,
    'enrollment_count' => $enrollmentCount,
    'review_count' => $reviewCount,
]);
$primaryBadge = $badges[0] ?? '';

$pageTitle = $course['title'];
$showBackButton = true;
$backTarget = $adminPreview ? 'admin-courses.php?edit=' . $courseId : 'courses.php';
include __DIR__ . '/includes/header.php';
?>

<section class="section">
    <div class="container">
        <?php if ($adminPreview): ?>
            <p class="alert success">Admin preview mode. You are viewing the learner-facing course detail page with access to the workspace shortcut.</p>
        <?php endif; ?>

        <div class="course-shell">
            <section class="course-overview">
                <div class="course-hero">
                    <div class="course-hero-copy">
                        <div class="course-hero-badges">
                            <span class="eyebrow"><?= htmlspecialchars($course['subject']) ?></span>
                            <?php if ($primaryBadge !== ''): ?>
                                <span class="tag course-badge-light"><?= htmlspecialchars($primaryBadge) ?></span>
                            <?php endif; ?>
                        </div>
                        <h1><?= htmlspecialchars($course['title']) ?></h1>
                        <p class="course-lead"><?= htmlspecialchars($course['description']) ?></p>
                        <div class="course-rating-row">
                            <div class="rating-inline">
                                <span class="stars" aria-hidden="true"><?= htmlspecialchars(course_star_text($averageRating)) ?></span>
                                <span><?= number_format($averageRating, 1) ?></span>
                                <span class="muted"><?= $reviewCount ?> reviews</span>
                            </div>
                            <span class="course-enrollment-pill"><?= $enrollmentCount ?> enrolled</span>
                        </div>
                        <div class="course-signal-row">
                            <span class="signal-pill"><?= htmlspecialchars($course['level']) ?></span>
                            <span class="signal-pill"><?= $moduleCount ?> modules</span>
                            <span class="signal-pill"><?= $quizCount ?> quizzes</span>
                            <span class="signal-pill"><?= $estimatedMinutes ?> mins</span>
                        </div>
                        <div class="actions">
                            <?php if ($adminPreview): ?>
                                <a class="button" href="<?= htmlspecialchars(learn_url($courseId, true)) ?>">Preview Learning Workspace</a>
                                <a class="button ghost" href="admin-courses.php?edit=<?= $courseId ?>">Edit Course</a>
                            <?php elseif (!$user): ?>
                                <a class="button" href="login.php">Login to Enroll</a>
                            <?php elseif (!$isEnrolled): ?>
                                <form method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="enroll">
                                    <button type="submit">Enroll and Start Learning</button>
                                </form>
                            <?php else: ?>
                                <a class="button" href="<?= htmlspecialchars(learn_url($courseId)) ?>">Go to Learning Workspace</a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <aside class="course-overview-card course-preview-card">
                        <div class="card-topline">
                            <span class="tag">Course Structure</span>
                            <span class="muted"><?= count($curriculum) ?> steps</span>
                        </div>
                        <h2>Preview the learning path</h2>
                        <?php foreach (array_slice($curriculum, 0, 5) as $index => $item): ?>
                            <div class="outline-row">
                                <strong><?= ($index + 1) . '. ' . htmlspecialchars($item['title']) ?></strong>
                                <span class="muted"><?= htmlspecialchars($item['label']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </aside>
                </div>
            </section>

            <section class="course-value-grid">
                <article class="panel" data-reveal="slide-up">
                    <div class="section-head compact">
                        <div>
                            <span class="eyebrow">What You Will Learn</span>
                            <h2>Practical outcomes</h2>
                        </div>
                    </div>
                    <ul class="insight-list">
                        <?php foreach ($learnPoints as $point): ?>
                            <li><?= htmlspecialchars($point) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </article>

                <article class="panel" data-reveal="slide-up" data-reveal-delay="1">
                    <div class="section-head compact">
                        <div>
                            <span class="eyebrow">Skills You Will Gain</span>
                            <h2>Takeaways</h2>
                        </div>
                    </div>
                    <div class="skill-chip-grid">
                        <?php foreach ($skillPoints as $skill): ?>
                            <span class="signal-pill"><?= htmlspecialchars($skill) ?></span>
                        <?php endforeach; ?>
                    </div>
                </article>

                <article class="panel" data-reveal="slide-up" data-reveal-delay="2">
                    <div class="section-head compact">
                        <div>
                            <span class="eyebrow">Who This Is For</span>
                            <h2>Best fit learners</h2>
                        </div>
                    </div>
                    <ul class="insight-list">
                        <?php foreach ($audiencePoints as $point): ?>
                            <li><?= htmlspecialchars($point) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </article>
            </section>

            <section class="course-feedback-preview">
                <div class="section-head compact">
                    <div>
                        <span class="eyebrow">Learner Feedback</span>
                        <h2>Social proof before you commit</h2>
                    </div>
                    <p class="muted">Students can leave feedback after they study inside the workspace.</p>
                </div>
                <div class="grid feedback-preview-grid">
                    <?php if (!$reviews): ?>
                        <article class="panel">
                            <p class="muted">No ratings yet. Be the first learner to enroll and leave feedback.</p>
                        </article>
                    <?php endif; ?>
                    <?php foreach (array_slice($reviews, 0, 3) as $review): ?>
                        <article class="panel">
                            <div class="card-topline">
                                <strong><?= htmlspecialchars($review['name']) ?></strong>
                                <span class="rating-chip"><?= htmlspecialchars(course_star_text((float) $review['rating'])) ?></span>
                            </div>
                            <p><?= $review['comment'] !== '' ? nl2br(htmlspecialchars($review['comment'])) : 'Rated without a written comment.' ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
