<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/media.php';
require_once __DIR__ . '/includes/progress.php';

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
    'SELECT rv.*, u.name
     FROM course_reviews rv
     JOIN users u ON u.id = rv.user_id
     WHERE rv.course_id = ?
     ORDER BY rv.updated_at DESC, rv.created_at DESC
     LIMIT 8',
    [$courseId]
);

$userReview = $user ? fetch_one('SELECT * FROM course_reviews WHERE user_id = ? AND course_id = ?', [$user['id'], $courseId]) : null;
$isEnrolled = $user ? is_course_enrolled($user['id'], $courseId) : false;
$canAccessLearning = $adminPreview || $isEnrolled;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$user) {
        header('Location: login.php');
        exit;
    }

    verify_csrf();
    $action = $_POST['action'] ?? '';
    $baseUrl = 'course.php?id=' . $courseId . ($adminPreview ? '&admin_preview=1' : '');

    if ($action === 'enroll') {
        enroll_in_course($user['id'], $courseId);
        header('Location: ' . $baseUrl . '&notice=enrolled#course-content');
        exit;
    }

    if ($action === 'toggle_saved' && $canAccessLearning) {
        sync_user_course_progress($user['id'], $courseId, isset($_POST['saved']));
        header('Location: ' . $baseUrl . '&notice=saved#learner-sidebar');
        exit;
    }

    if ($action === 'complete_resource' && $canAccessLearning) {
        $resourceId = (int) ($_POST['resource_id'] ?? 0);
        $resource = fetch_one('SELECT id FROM course_resources WHERE id = ? AND course_id = ?', [$resourceId, $courseId]);
        if ($resource) {
            mark_course_item_complete($user['id'], $courseId, 'resource', $resourceId);
        }
        header('Location: ' . $baseUrl . '&notice=resource_completed#resource-' . $resourceId);
        exit;
    }

    if ($action === 'submit_quiz' && $canAccessLearning) {
        $quizId = (int) ($_POST['quiz_id'] ?? 0);
        $quizIndex = max(0, (int) ($_POST['quiz_index'] ?? 0));
        $selectedOption = strtoupper(trim($_POST['selected_option'] ?? ''));
        $question = fetch_one('SELECT * FROM quiz_questions WHERE id = ? AND course_id = ?', [$quizId, $courseId]);
        if ($question && in_array($selectedOption, ['A', 'B', 'C'], true)) {
            mark_course_item_complete($user['id'], $courseId, 'quiz', $quizId);
            $result = $selectedOption === $question['correct_option'] ? 'correct' : 'incorrect';
            $nextIndex = min($quizIndex + 1, max(count($questions) - 1, 0));
            header(
                'Location: ' . $baseUrl .
                '&quiz=' . $nextIndex .
                '&quiz_result=' . $result .
                '&answered=' . $quizId .
                '#quiz-panel'
            );
            exit;
        }
        header('Location: ' . $baseUrl . '&quiz=' . $quizIndex . '&quiz_result=invalid#quiz-panel');
        exit;
    }

    if ($action === 'save_review' && ($isEnrolled || $adminPreview)) {
        $rating = max(1, min(5, (int) ($_POST['rating'] ?? 0)));
        $comment = trim($_POST['comment'] ?? '');
        $stmt = db()->prepare(
            'INSERT INTO course_reviews (user_id, course_id, rating, comment)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment), updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([$user['id'], $courseId, $rating, $comment]);
        header('Location: ' . $baseUrl . '&notice=review_saved#feedback-panel');
        exit;
    }
}

if ($user && $canAccessLearning) {
    sync_user_course_progress($user['id'], $courseId);
}

$progress = ($user && $canAccessLearning)
    ? fetch_one('SELECT * FROM user_progress WHERE user_id = ? AND course_id = ?', [$user['id'], $courseId])
    : null;
$progressRows = ($user && $canAccessLearning)
    ? fetch_all('SELECT item_type, item_id FROM course_item_progress WHERE user_id = ? AND course_id = ?', [$user['id'], $courseId])
    : [];

$completedResourceIds = [];
$completedQuizIds = [];
foreach ($progressRows as $row) {
    if ($row['item_type'] === 'resource') {
        $completedResourceIds[(int) $row['item_id']] = true;
    }
    if ($row['item_type'] === 'quiz') {
        $completedQuizIds[(int) $row['item_id']] = true;
    }
}

$totalItems = count($resources) + count($questions);
$completedItems = count($completedResourceIds) + count($completedQuizIds);
$progressPercent = $totalItems > 0 ? (int) round(($completedItems / $totalItems) * 100) : 0;
$estimatedMinutes = max(20, (count($resources) * 14) + (count($questions) * 4));
$averageRating = (float) ($courseInsights['average_rating'] ?? 0);
$reviewCount = (int) ($courseInsights['review_count'] ?? 0);
$enrollmentCount = (int) ($courseInsights['enrollment_count'] ?? 0);
$moduleCount = (int) ($courseInsights['module_count'] ?? 0);
$quizCount = (int) ($courseInsights['quiz_count'] ?? 0);

$quizIndex = count($questions) > 0 ? max(0, min(count($questions) - 1, (int) ($_GET['quiz'] ?? 0))) : 0;
$currentQuestion = $questions[$quizIndex] ?? null;
$answeredQuizId = (int) ($_GET['answered'] ?? 0);
$answeredQuestion = $answeredQuizId ? fetch_one('SELECT question, correct_option FROM quiz_questions WHERE id = ? AND course_id = ?', [$answeredQuizId, $courseId]) : null;

$noticeMap = [
    'enrolled' => 'You are enrolled. Your course workspace is ready below.',
    'saved' => 'Course preferences saved.',
    'resource_completed' => 'Learning step marked as completed.',
    'review_saved' => 'Feedback saved. Thanks for sharing your view of the course.',
];
$message = $noticeMap[$_GET['notice'] ?? ''] ?? '';

$quizResult = $_GET['quiz_result'] ?? '';
$quizFeedback = '';
if ($answeredQuestion && in_array($quizResult, ['correct', 'incorrect'], true)) {
    $quizFeedback = $quizResult === 'correct'
        ? 'Answer recorded. Nice one.'
        : 'Answer recorded. The correct option for "' . $answeredQuestion['question'] . '" is ' . $answeredQuestion['correct_option'] . '.';
}
if ($quizResult === 'invalid') {
    $quizFeedback = 'Choose an answer before moving to the next question.';
}

$courseStats = $adminPreview
    ? fetch_one(
        'SELECT COUNT(*) AS learners, AVG(progress_percent) AS avg_progress
         FROM user_progress
         WHERE course_id = ?',
        [$courseId]
    )
    : null;

$pageTitle = $course['title'];
$showBackButton = true;
$backTarget = $adminPreview ? 'admin-courses.php?edit=' . $courseId : 'courses.php';
include __DIR__ . '/includes/header.php';
?>

<section class="section">
    <div class="container">
        <?php if ($adminPreview): ?>
            <p class="alert success">Admin preview mode. This is the same course experience learners see, with a preview-only admin sidebar.</p>
        <?php endif; ?>
        <?php if ($message): ?><p class="alert success"><?= htmlspecialchars($message) ?></p><?php endif; ?>

        <div class="course-shell">
            <section class="course-overview">
                <div class="course-hero">
                    <div class="course-hero-copy">
                        <span class="eyebrow"><?= htmlspecialchars($course['subject']) ?></span>
                        <h1><?= htmlspecialchars($course['title']) ?></h1>
                        <p class="course-lead"><?= htmlspecialchars($course['description']) ?></p>
                        <div class="course-signal-row">
                            <span class="signal-pill"><?= htmlspecialchars($course['level']) ?></span>
                            <span class="signal-pill"><?= $estimatedMinutes ?> mins</span>
                            <span class="signal-pill"><?= $moduleCount ?> modules</span>
                            <span class="signal-pill"><?= $quizCount ?> quiz questions</span>
                        </div>
                        <div class="course-rating-row">
                            <div class="rating-inline">
                                <span class="stars" aria-hidden="true"><?= str_repeat('&#9733;', max(1, (int) round($averageRating))) ?></span>
                                <span><?= number_format($averageRating, 1) ?></span>
                                <span class="muted">(<?= $reviewCount ?> reviews)</span>
                            </div>
                            <span class="muted"><?= $enrollmentCount ?> enrolled learners</span>
                        </div>
                        <div class="actions">
                            <?php if ($adminPreview): ?>
                                <a class="button" href="#course-content">Preview Learning Experience</a>
                                <a class="button ghost" href="admin-courses.php?edit=<?= $courseId ?>">Edit Course</a>
                            <?php elseif (!$user): ?>
                                <a class="button" href="login.php">Login to Enroll</a>
                            <?php elseif (!$isEnrolled): ?>
                                <form method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="enroll">
                                    <button type="submit">Enroll in Course</button>
                                </form>
                            <?php else: ?>
                                <a class="button" href="#course-content">Continue Learning</a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <aside class="course-overview-card">
                        <h2>What You’ll Work Through</h2>
                        <?php if (!$resources && !$questions): ?>
                            <p class="muted">This course outline is still being prepared.</p>
                        <?php endif; ?>
                        <?php foreach (array_slice($resources, 0, 3) as $resource): ?>
                            <div class="outline-row">
                                <strong><?= htmlspecialchars($resource['title']) ?></strong>
                                <span class="muted"><?= htmlspecialchars(ucfirst($resource['resource_type'])) ?></span>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($questions): ?>
                            <div class="outline-row">
                                <strong>Interactive assessment</strong>
                                <span class="muted"><?= count($questions) ?> graded prompts</span>
                            </div>
                        <?php endif; ?>
                        <?php if ($adminPreview && $courseStats): ?>
                            <hr>
                            <p><strong><?= (int) ($courseStats['learners'] ?? 0) ?></strong> learners tracked</p>
                            <p><strong><?= (int) round((float) ($courseStats['avg_progress'] ?? 0)) ?>%</strong> average completion</p>
                        <?php endif; ?>
                    </aside>
                </div>
            </section>

            <section class="course-feedback-preview">
                <div class="section-head compact">
                    <div>
                        <span class="eyebrow">Learner Feedback</span>
                        <h2>See how this course is landing with students.</h2>
                    </div>
                </div>
                <div class="grid feedback-preview-grid">
                    <?php if (!$reviews): ?>
                        <article class="panel">
                            <p class="muted">No ratings yet. Be the first learner to leave feedback after enrolling.</p>
                        </article>
                    <?php endif; ?>
                    <?php foreach (array_slice($reviews, 0, 3) as $review): ?>
                        <article class="panel">
                            <div class="card-topline">
                                <strong><?= htmlspecialchars($review['name']) ?></strong>
                                <span class="rating-chip"><?= str_repeat('&#9733;', (int) $review['rating']) ?></span>
                            </div>
                            <p><?= $review['comment'] !== '' ? nl2br(htmlspecialchars($review['comment'])) : 'Rated without a written comment.' ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <?php if ($canAccessLearning): ?>
                <section class="course-learning" id="course-content">
                    <div class="course-content-layout">
                        <div class="course-content-main">
                            <div class="section-head compact">
                                <div>
                                    <span class="eyebrow">Course Content</span>
                                    <h2>Your learning path</h2>
                                </div>
                                <p class="muted">Move through the modules in order, mark progress as you go, and finish with the quiz section.</p>
                            </div>

                            <?php foreach ($resources as $index => $resource): ?>
                                <article class="panel lesson-card" id="resource-<?= (int) $resource['id'] ?>">
                                    <div class="lesson-index"><?= $index + 1 ?></div>
                                    <div class="lesson-body">
                                        <div class="card-topline">
                                            <span class="tag"><?= htmlspecialchars($resource['resource_type']) ?></span>
                                            <?php if (!$adminPreview && isset($completedResourceIds[(int) $resource['id']])): ?>
                                                <span class="tag">Completed</span>
                                            <?php endif; ?>
                                        </div>
                                        <h3><?= htmlspecialchars($resource['title']) ?></h3>
                                        <p><?= nl2br(htmlspecialchars($resource['content'])) ?></p>
                                        <?php if ($resource['resource_type'] === 'video' && $resource['resource_url']): ?>
                                            <div class="video-frame">
                                                <iframe src="<?= htmlspecialchars(video_embed_src($resource['resource_url'])) ?>" title="<?= htmlspecialchars($resource['title']) ?>" allowfullscreen></iframe>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($user && !$adminPreview): ?>
                                            <form method="post" class="inline-form" style="margin-top: 1rem;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="complete_resource">
                                                <input type="hidden" name="resource_id" value="<?= (int) $resource['id'] ?>">
                                                <button type="submit" class="button small secondary">
                                                    <?= isset($completedResourceIds[(int) $resource['id']]) ? 'Reconfirm Completion' : 'Mark Completed' ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>

                            <?php if ($currentQuestion): ?>
                                <article class="panel course-quiz-panel" id="quiz-panel">
                                    <div class="card-topline">
                                        <span class="tag">Interactive Quiz</span>
                                        <span class="muted">Question <?= $quizIndex + 1 ?> of <?= count($questions) ?></span>
                                    </div>
                                    <?php if ($quizFeedback): ?>
                                        <p class="alert <?= $quizResult === 'correct' ? 'success' : 'error' ?>"><?= htmlspecialchars($quizFeedback) ?></p>
                                    <?php endif; ?>
                                    <h2><?= htmlspecialchars($currentQuestion['question']) ?></h2>
                                    <?php if (!$adminPreview): ?>
                                        <form method="post">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="submit_quiz">
                                            <input type="hidden" name="quiz_id" value="<?= (int) $currentQuestion['id'] ?>">
                                            <input type="hidden" name="quiz_index" value="<?= $quizIndex ?>">
                                            <label class="quiz-option"><input type="radio" name="selected_option" value="A"> A. <?= htmlspecialchars($currentQuestion['option_a']) ?></label>
                                            <label class="quiz-option"><input type="radio" name="selected_option" value="B"> B. <?= htmlspecialchars($currentQuestion['option_b']) ?></label>
                                            <label class="quiz-option"><input type="radio" name="selected_option" value="C"> C. <?= htmlspecialchars($currentQuestion['option_c']) ?></label>
                                            <div class="actions">
                                                <button type="submit">Submit Answer</button>
                                                <?php if ($quizIndex > 0): ?>
                                                    <a class="button ghost small" href="course.php?id=<?= $courseId ?><?= $adminPreview ? '&amp;admin_preview=1' : '' ?>&amp;quiz=<?= $quizIndex - 1 ?>#quiz-panel">Previous</a>
                                                <?php endif; ?>
                                                <?php if ($quizIndex < count($questions) - 1): ?>
                                                    <a class="button ghost small" href="course.php?id=<?= $courseId ?><?= $adminPreview ? '&amp;admin_preview=1' : '' ?>&amp;quiz=<?= $quizIndex + 1 ?>#quiz-panel">Next</a>
                                                <?php endif; ?>
                                            </div>
                                        </form>
                                    <?php else: ?>
                                        <div class="quiz-preview-list">
                                            <p>A. <?= htmlspecialchars($currentQuestion['option_a']) ?></p>
                                            <p>B. <?= htmlspecialchars($currentQuestion['option_b']) ?></p>
                                            <p>C. <?= htmlspecialchars($currentQuestion['option_c']) ?></p>
                                            <p class="muted">Correct answer: <?= htmlspecialchars($currentQuestion['correct_option']) ?></p>
                                            <div class="actions">
                                                <?php if ($quizIndex > 0): ?>
                                                    <a class="button ghost small" href="course.php?id=<?= $courseId ?>&amp;admin_preview=1&amp;quiz=<?= $quizIndex - 1 ?>#quiz-panel">Previous</a>
                                                <?php endif; ?>
                                                <?php if ($quizIndex < count($questions) - 1): ?>
                                                    <a class="button ghost small" href="course.php?id=<?= $courseId ?>&amp;admin_preview=1&amp;quiz=<?= $quizIndex + 1 ?>#quiz-panel">Next</a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </article>
                            <?php endif; ?>
                        </div>

                        <aside class="course-sidebar" id="learner-sidebar">
                            <?php if ($adminPreview): ?>
                                <div class="panel">
                                    <h2>Admin Snapshot</h2>
                                    <p><strong><?= $enrollmentCount ?></strong> enrolled learners</p>
                                    <p><strong><?= number_format($averageRating, 1) ?></strong> average rating</p>
                                    <p><strong><?= $reviewCount ?></strong> total reviews</p>
                                    <a class="button small" href="admin-courses.php?edit=<?= $courseId ?>">Back to course management</a>
                                </div>
                            <?php else: ?>
                                <div class="panel">
                                    <h2>Learning Progress</h2>
                                    <p><strong><?= $progressPercent ?>%</strong> complete</p>
                                    <div class="progress"><span style="width: <?= $progressPercent ?>%"></span></div>
                                    <p class="muted"><?= $completedItems ?> of <?= $totalItems ?> steps completed.</p>
                                    <form method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle_saved">
                                        <label class="checkbox"><input type="checkbox" name="saved" <?= !empty($progress['saved']) ? 'checked' : '' ?>> Save this course</label>
                                        <button type="submit">Update Saved Status</button>
                                    </form>
                                </div>

                                <div class="panel" id="feedback-panel">
                                    <h2>Rate This Course</h2>
                                    <form method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="save_review">
                                        <label>Rating
                                            <select name="rating" required>
                                                <option value="5" <?= (int) ($userReview['rating'] ?? 0) === 5 ? 'selected' : '' ?>>5 - Excellent</option>
                                                <option value="4" <?= (int) ($userReview['rating'] ?? 0) === 4 ? 'selected' : '' ?>>4 - Very good</option>
                                                <option value="3" <?= (int) ($userReview['rating'] ?? 0) === 3 ? 'selected' : '' ?>>3 - Solid</option>
                                                <option value="2" <?= (int) ($userReview['rating'] ?? 0) === 2 ? 'selected' : '' ?>>2 - Needs work</option>
                                                <option value="1" <?= (int) ($userReview['rating'] ?? 0) === 1 ? 'selected' : '' ?>>1 - Poor</option>
                                            </select>
                                        </label>
                                        <label>Comment
                                            <textarea name="comment" placeholder="What felt useful, clear, confusing, or worth improving?"><?= htmlspecialchars($userReview['comment'] ?? '') ?></textarea>
                                        </label>
                                        <button type="submit">Save Feedback</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </aside>
                    </div>
                </section>
            <?php else: ?>
                <section class="course-gated-message">
                    <article class="panel">
                        <h2>Enroll to unlock the learning workspace</h2>
                        <p class="muted">The course overview stays visible so learners can assess the topic, rating, and expected workload before committing. Once enrolled, notes, videos, quiz content, progress tracking, and feedback tools become available.</p>
                    </article>
                </section>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
