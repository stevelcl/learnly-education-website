<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/media.php';
require_once __DIR__ . '/includes/progress.php';
require_once __DIR__ . '/includes/courses.php';

function workspace_video_src(string $url): string
{
    $embed = video_embed_src($url);
    if ($embed === '') {
        return '';
    }

    $separator = str_contains($embed, '?') ? '&' : '?';
    return $embed . $separator . 'enablejsapi=1&rel=0&modestbranding=1';
}

$courseId = (int) ($_GET['id'] ?? 0);
$course = fetch_one('SELECT * FROM courses WHERE id = ?', [$courseId]);
if (!$course) {
    http_response_code(404);
    exit('Course not found.');
}

$user = current_user();
$adminPreview = isset($_GET['admin_preview']) && is_admin($user);
$isEnrolled = $user ? is_course_enrolled((int) $user['id'], $courseId) : false;

if (!$adminPreview && !$isEnrolled) {
    header('Location: ' . course_url($courseId));
    exit;
}

$resources = fetch_all(
    'SELECT * FROM course_resources
     WHERE course_id = ? AND resource_type <> "quiz"
     ORDER BY sort_order, id',
    [$courseId]
);
$questions = fetch_all('SELECT * FROM quiz_questions WHERE course_id = ? ORDER BY id', [$courseId]);
$curriculum = course_curriculum_items($resources, $questions);

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

$userReview = ($user && !$adminPreview)
    ? fetch_one('SELECT * FROM course_reviews WHERE user_id = ? AND course_id = ?', [(int) $user['id'], $courseId])
    : null;

$progress = ($user && !$adminPreview)
    ? fetch_one('SELECT * FROM user_progress WHERE user_id = ? AND course_id = ?', [(int) $user['id'], $courseId])
    : null;
$progressRows = ($user && !$adminPreview)
    ? fetch_all('SELECT item_type, item_id FROM course_item_progress WHERE user_id = ? AND course_id = ?', [(int) $user['id'], $courseId])
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

$completedMap = [];
foreach ($curriculum as $index => $item) {
    $completedMap[$index] = $adminPreview || ($item['type'] === 'resource'
        ? isset($completedResourceIds[$item['id']])
        : isset($completedQuizIds[$item['id']]));
}

$firstIncompleteStep = null;
foreach ($completedMap as $index => $isComplete) {
    if (!$isComplete) {
        $firstIncompleteStep = $index;
        break;
    }
}

$totalItems = count($curriculum);
$completedItems = count(array_filter($completedMap));
$progressPercent = $totalItems > 0 ? (int) round(($completedItems / $totalItems) * 100) : 0;
$courseComplete = $totalItems > 0 && $completedItems >= $totalItems;
$maxUnlockedStep = $adminPreview ? max($totalItems - 1, 0) : ($courseComplete ? max($totalItems - 1, 0) : (int) ($firstIncompleteStep ?? 0));
$requestedStep = max(0, min(max($totalItems - 1, 0), (int) ($_GET['step'] ?? 0)));
$step = min($requestedStep, $maxUnlockedStep);
$currentItem = $curriculum[$step] ?? null;
$baseUrl = learn_url($courseId, $adminPreview);

if (!$currentItem) {
    header('Location: ' . course_url($courseId) . ($adminPreview ? '&admin_preview=1' : ''));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$user && !$adminPreview) {
        header('Location: login.php');
        exit;
    }

    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_saved' && $user && !$adminPreview) {
        sync_user_course_progress((int) $user['id'], $courseId, isset($_POST['saved']));
        header('Location: ' . $baseUrl . '?step=' . $step . '&notice=saved#workspace-sidebar');
        exit;
    }

    if ($action === 'submit_quiz' && $user && !$adminPreview) {
        $quizId = (int) ($_POST['quiz_id'] ?? 0);
        $selectedOption = strtoupper(trim($_POST['selected_option'] ?? ''));
        $question = fetch_one('SELECT * FROM quiz_questions WHERE id = ? AND course_id = ?', [$quizId, $courseId]);
        if ($question && in_array($selectedOption, ['A', 'B', 'C'], true)) {
            mark_course_item_complete((int) $user['id'], $courseId, 'quiz', $quizId);
            $result = $selectedOption === $question['correct_option'] ? 'correct' : 'incorrect';
            $nextStep = min($step + 1, max(count($curriculum) - 1, 0));
            header('Location: ' . $baseUrl . '?step=' . $nextStep . '&quiz_result=' . $result . '&answered=' . $quizId . '#lesson-content');
            exit;
        }
        header('Location: ' . $baseUrl . '?step=' . $step . '&quiz_result=invalid#lesson-content');
        exit;
    }

    if ($action === 'save_review' && $user && !$adminPreview && $courseComplete) {
        $rating = max(1, min(5, (int) ($_POST['rating'] ?? 0)));
        $comment = trim($_POST['comment'] ?? '');
        $stmt = db()->prepare(
            'INSERT INTO course_reviews (user_id, course_id, rating, comment)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment), updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([(int) $user['id'], $courseId, $rating, $comment]);
        header('Location: ' . $baseUrl . '?step=' . $step . '&notice=review_saved#completion-panel');
        exit;
    }
}

$averageRating = (float) ($courseInsights['average_rating'] ?? 0);
$reviewCount = (int) ($courseInsights['review_count'] ?? 0);
$enrollmentCount = (int) ($courseInsights['enrollment_count'] ?? 0);
$estimatedMinutes = max(20, (((int) ($courseInsights['module_count'] ?? 0)) * 14) + (((int) ($courseInsights['quiz_count'] ?? 0)) * 4));

$noticeMap = [
    'saved' => 'Course preferences saved.',
    'review_saved' => 'Feedback saved. Thanks for sharing your view of the course.',
];
$message = $noticeMap[$_GET['notice'] ?? ''] ?? '';

$quizResult = $_GET['quiz_result'] ?? '';
$answeredQuizId = (int) ($_GET['answered'] ?? 0);
$answeredQuestion = $answeredQuizId
    ? fetch_one('SELECT question, correct_option FROM quiz_questions WHERE id = ? AND course_id = ?', [$answeredQuizId, $courseId])
    : null;
$quizFeedback = '';
if ($answeredQuestion && in_array($quizResult, ['correct', 'incorrect'], true)) {
    $quizFeedback = $quizResult === 'correct'
        ? 'Answer recorded. Nice work.'
        : 'Answer recorded. The correct option for "' . $answeredQuestion['question'] . '" is ' . $answeredQuestion['correct_option'] . '.';
}
if ($quizResult === 'invalid') {
    $quizFeedback = 'Choose an answer before moving to the next lesson.';
}

$currentComplete = !empty($completedMap[$step]);
$prevStep = $step > 0 ? $step - 1 : null;
$nextStep = $step < $totalItems - 1 ? $step + 1 : null;
$nextEnabled = $adminPreview || $currentComplete;
$completionMessage = $courseComplete
    ? 'Course completed. You unlocked the feedback step and finished the full learning path.'
    : 'Complete this lesson to continue.';

$pageTitle = $course['title'] . ' Learning';
$showBackButton = true;
$backTarget = course_url($courseId, $adminPreview);
include __DIR__ . '/includes/header.php';
?>

<section
    class="section learning-shell"
    data-learning-shell
    data-course-id="<?= $courseId ?>"
    data-csrf-token="<?= htmlspecialchars(csrf_token()) ?>"
    data-progress-endpoint="course-progress.php"
    data-base-url="<?= htmlspecialchars($baseUrl) ?>"
    data-current-step="<?= $step ?>"
    data-total-steps="<?= $totalItems ?>"
    data-current-complete="<?= $currentComplete ? '1' : '0' ?>"
    data-admin-preview="<?= $adminPreview ? '1' : '0' ?>"
>
    <div class="container">
        <?php if ($adminPreview): ?>
            <p class="alert success">Admin preview mode. This workspace mirrors the learner experience while keeping course management out of the way.</p>
        <?php endif; ?>
        <?php if ($message): ?><p class="alert success"><?= htmlspecialchars($message) ?></p><?php endif; ?>

        <div class="learning-header">
            <div>
                <span class="eyebrow">Learning Workspace</span>
                <h1><?= htmlspecialchars($course['title']) ?></h1>
                <p class="muted"><?= htmlspecialchars($course['subject']) ?> | <?= $estimatedMinutes ?> mins | <?= $enrollmentCount ?> enrolled</p>
            </div>
            <div class="learning-progress-hero">
                <strong data-progress-percent><?= $progressPercent ?>%</strong>
                <span class="muted" data-progress-summary><?= $completedItems ?> of <?= $totalItems ?> completed</span>
                <div class="progress"><span data-progress-bar style="width: <?= $progressPercent ?>%"></span></div>
            </div>
        </div>

        <div class="learning-layout">
            <aside class="panel learning-sidebar">
                <div class="section-head compact">
                    <div>
                        <span class="eyebrow">Course Map</span>
                        <h2>Modules and lessons</h2>
                    </div>
                </div>
                <div class="learning-outline-list">
                    <?php foreach ($curriculum as $index => $item): ?>
                        <?php
                        $isActive = $index === $step;
                        $isComplete = !empty($completedMap[$index]);
                        $isUnlocked = $adminPreview || $index <= $maxUnlockedStep;
                        $stepUrl = $baseUrl . '?step=' . $index;
                        ?>
                        <?php if ($isUnlocked): ?>
                            <a
                                class="learning-outline-item<?= $isActive ? ' active' : '' ?><?= $isComplete ? ' complete' : '' ?>"
                                href="<?= htmlspecialchars($stepUrl) ?>"
                                data-outline-item
                                data-step-index="<?= $index ?>"
                                data-step-url="<?= htmlspecialchars($stepUrl) ?>"
                            >
                                <span class="learning-outline-status"><?= $isComplete ? 'Done' : $index + 1 ?></span>
                                <span>
                                    <strong><?= htmlspecialchars($item['title']) ?></strong>
                                    <small><?= htmlspecialchars($item['label']) ?></small>
                                </span>
                            </a>
                        <?php else: ?>
                            <span
                                class="learning-outline-item locked"
                                aria-disabled="true"
                                data-outline-item
                                data-step-index="<?= $index ?>"
                                data-step-url="<?= htmlspecialchars($stepUrl) ?>"
                            >
                                <span class="learning-outline-status">Locked</span>
                                <span>
                                    <strong><?= htmlspecialchars($item['title']) ?></strong>
                                    <small><?= htmlspecialchars($item['label']) ?></small>
                                </span>
                            </span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </aside>

            <main class="learning-main">
                <article
                    class="panel learning-content-panel"
                    id="lesson-content"
                    data-learning-item
                    data-item-type="<?= htmlspecialchars($currentItem['type']) ?>"
                    data-item-id="<?= (int) $currentItem['id'] ?>"
                    data-item-complete="<?= $currentComplete ? '1' : '0' ?>"
                >
                    <div class="card-topline">
                        <span class="tag"><?= htmlspecialchars($currentItem['label']) ?></span>
                        <span class="muted">Lesson <?= $step + 1 ?> of <?= $totalItems ?></span>
                    </div>
                    <h2><?= htmlspecialchars($currentItem['title']) ?></h2>

                    <?php if ($currentItem['type'] === 'resource'): ?>
                        <div class="learning-note-content">
                            <p><?= nl2br(htmlspecialchars($currentItem['content'])) ?></p>
                        </div>

                        <?php if ($currentItem['label'] === 'Video' && $currentItem['resource_url'] !== ''): ?>
                            <div class="video-frame">
                                <iframe
                                    id="lesson-video-<?= (int) $currentItem['id'] ?>"
                                    src="<?= htmlspecialchars(workspace_video_src((string) $currentItem['resource_url'])) ?>"
                                    title="<?= htmlspecialchars($currentItem['title']) ?>"
                                    allowfullscreen
                                    data-video-progress
                                ></iframe>
                            </div>
                        <?php else: ?>
                            <div class="learning-complete-marker" data-complete-trigger></div>
                        <?php endif; ?>

                        <?php if (!$adminPreview): ?>
                            <p class="learning-status-hint" data-completion-hint>
                                <?= $currentItem['label'] === 'Video'
                                    ? 'Watch this lesson through to unlock the next step.'
                                    : 'Scroll to the end of this lesson to unlock the next step.' ?>
                            </p>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php if ($quizFeedback): ?>
                            <p class="alert <?= $quizResult === 'correct' ? 'success' : 'error' ?>"><?= htmlspecialchars($quizFeedback) ?></p>
                        <?php endif; ?>

                        <?php if ($adminPreview): ?>
                            <div class="quiz-preview-list">
                                <p><?= htmlspecialchars($currentItem['question']) ?></p>
                                <p>A. <?= htmlspecialchars($currentItem['option_a']) ?></p>
                                <p>B. <?= htmlspecialchars($currentItem['option_b']) ?></p>
                                <p>C. <?= htmlspecialchars($currentItem['option_c']) ?></p>
                                <p class="muted">Correct answer: <?= htmlspecialchars($currentItem['correct_option']) ?></p>
                            </div>
                        <?php else: ?>
                            <form method="post" class="learning-quiz-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="submit_quiz">
                                <input type="hidden" name="quiz_id" value="<?= (int) $currentItem['id'] ?>">
                                <p><?= htmlspecialchars($currentItem['question']) ?></p>
                                <label class="quiz-option"><input type="radio" name="selected_option" value="A"> A. <?= htmlspecialchars($currentItem['option_a']) ?></label>
                                <label class="quiz-option"><input type="radio" name="selected_option" value="B"> B. <?= htmlspecialchars($currentItem['option_b']) ?></label>
                                <label class="quiz-option"><input type="radio" name="selected_option" value="C"> C. <?= htmlspecialchars($currentItem['option_c']) ?></label>
                                <button type="submit">Submit Answer</button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($courseComplete && $step === $totalItems - 1): ?>
                        <div class="course-completion-card" id="completion-panel">
                            <span class="tag course-badge">Completed</span>
                            <h3>Course Completed</h3>
                            <p class="muted">You reached 100% completion and unlocked the final feedback step. Nice work.</p>
                        </div>
                    <?php endif; ?>

                    <div class="learning-nav-row">
                        <?php if ($prevStep !== null): ?>
                            <a class="button ghost" href="<?= htmlspecialchars($baseUrl . '?step=' . $prevStep) ?>">Previous Lesson</a>
                        <?php endif; ?>

                        <?php if ($nextStep !== null): ?>
                            <?php if ($nextEnabled): ?>
                                <a class="button" data-next-lesson href="<?= htmlspecialchars($baseUrl . '?step=' . $nextStep) ?>">Next Lesson</a>
                            <?php else: ?>
                                <span class="button disabled-button" data-next-lesson-disabled>Complete this lesson to continue</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <a class="button" href="<?= htmlspecialchars(course_url($courseId, $adminPreview)) ?>">Back to Course Overview</a>
                        <?php endif; ?>
                    </div>
                </article>
            </main>

            <aside class="learning-right-rail" id="workspace-sidebar">
                <div class="panel">
                    <h2>Progress Tracker</h2>
                    <p><strong><?= $progressPercent ?>%</strong> complete</p>
                    <div class="progress"><span style="width: <?= $progressPercent ?>%"></span></div>
                    <p class="muted"><?= $completedItems ?> of <?= $totalItems ?> lessons completed.</p>
                    <?php if ($user && !$adminPreview): ?>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle_saved">
                            <label class="checkbox"><input type="checkbox" name="saved" <?= !empty($progress['saved']) ? 'checked' : '' ?>> Save this course</label>
                            <button type="submit">Update Saved Status</button>
                        </form>
                    <?php endif; ?>
                </div>

                <div class="panel">
                    <h2>Course Summary</h2>
                    <p class="muted"><?= htmlspecialchars($course['description']) ?></p>
                    <div class="rating-inline">
                        <span class="stars" aria-hidden="true"><?= htmlspecialchars(course_star_text($averageRating)) ?></span>
                        <span><?= number_format($averageRating, 1) ?></span>
                        <span class="muted"><?= $reviewCount ?> reviews</span>
                    </div>
                </div>

                <?php if ($adminPreview): ?>
                    <div class="panel">
                        <h2>Admin Tools</h2>
                        <a class="button small" href="admin-courses.php?edit=<?= $courseId ?>">Back to course management</a>
                    </div>
                <?php elseif ($courseComplete): ?>
                    <div class="panel course-completion-side">
                        <h2>Completion Unlocked</h2>
                        <p class="muted"><?= $completionMessage ?></p>
                        <div class="course-completion-badge">100% Complete</div>
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
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
