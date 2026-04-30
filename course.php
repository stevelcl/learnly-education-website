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
$resources = fetch_all('SELECT * FROM course_resources WHERE course_id = ? AND resource_type <> "quiz" ORDER BY sort_order, id', [$courseId]);
$questions = fetch_all('SELECT * FROM quiz_questions WHERE course_id = ? ORDER BY id', [$courseId]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$user) {
        header('Location: login.php');
        exit;
    }

    verify_csrf();
    $action = $_POST['action'] ?? '';
    $baseUrl = 'course.php?id=' . $courseId . ($adminPreview ? '&admin_preview=1' : '');

    if ($action === 'toggle_saved') {
        sync_user_course_progress($user['id'], $courseId, isset($_POST['saved']));
        header('Location: ' . $baseUrl . '&notice=saved#progress-panel');
        exit;
    }

    if ($action === 'complete_resource') {
        $resourceId = (int) ($_POST['resource_id'] ?? 0);
        $resource = fetch_one('SELECT id FROM course_resources WHERE id = ? AND course_id = ?', [$resourceId, $courseId]);
        if ($resource) {
            mark_course_item_complete($user['id'], $courseId, 'resource', $resourceId);
        }
        header('Location: ' . $baseUrl . '&notice=resource_completed#resource-' . $resourceId);
        exit;
    }

    if ($action === 'submit_quiz') {
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
}

if ($user) {
    sync_user_course_progress($user['id'], $courseId);
}

$progress = $user ? fetch_one('SELECT * FROM user_progress WHERE user_id = ? AND course_id = ?', [$user['id'], $courseId]) : null;
$progressRows = $user
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

$quizIndex = count($questions) > 0 ? max(0, min(count($questions) - 1, (int) ($_GET['quiz'] ?? 0))) : 0;
$currentQuestion = $questions[$quizIndex] ?? null;
$answeredQuizId = (int) ($_GET['answered'] ?? 0);
$answeredQuestion = $answeredQuizId ? fetch_one('SELECT question, correct_option FROM quiz_questions WHERE id = ? AND course_id = ?', [$answeredQuizId, $courseId]) : null;

$noticeMap = [
    'saved' => 'Course preferences saved.',
    'resource_completed' => 'Learning step marked as completed.',
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
$backTarget = $adminPreview ? 'admin-courses.php?edit=' . $courseId . '#resources' : 'courses.php';
include __DIR__ . '/includes/header.php';
?>

<section class="section">
    <div class="container layout">
        <div>
            <?php if ($adminPreview): ?>
                <p class="alert success">Admin preview mode. You are viewing this course from the course management area.</p>
            <?php endif; ?>

            <h1><?= htmlspecialchars($course['title']) ?></h1>
            <p class="muted"><?= htmlspecialchars($course['subject']) ?> | <?= htmlspecialchars($course['level']) ?></p>
            <p><?= htmlspecialchars($course['description']) ?></p>
            <?php if ($message): ?><p class="alert success"><?= htmlspecialchars($message) ?></p><?php endif; ?>

            <?php foreach ($resources as $resource): ?>
                <article class="panel" id="resource-<?= (int) $resource['id'] ?>">
                    <div class="card-topline">
                        <span class="tag"><?= htmlspecialchars($resource['resource_type']) ?></span>
                        <?php if (isset($completedResourceIds[(int) $resource['id']])): ?>
                            <span class="tag">Completed</span>
                        <?php endif; ?>
                    </div>
                    <h2><?= htmlspecialchars($resource['title']) ?></h2>
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
                                <?= isset($completedResourceIds[(int) $resource['id']]) ? 'Mark Again' : 'Mark Completed' ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>

            <?php if ($currentQuestion): ?>
                <article class="panel" id="quiz-panel">
                    <div class="card-topline">
                        <span class="tag">Interactive Quiz</span>
                        <span class="muted">Question <?= $quizIndex + 1 ?> of <?= count($questions) ?></span>
                    </div>
                    <?php if ($quizFeedback): ?>
                        <p class="alert <?= $quizResult === 'correct' ? 'success' : 'error' ?>"><?= htmlspecialchars($quizFeedback) ?></p>
                    <?php endif; ?>
                    <h2><?= htmlspecialchars($currentQuestion['question']) ?></h2>
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
                </article>
            <?php endif; ?>
        </div>

        <aside class="panel" id="progress-panel">
            <?php if ($adminPreview): ?>
                <h2>Admin Snapshot</h2>
                <p><strong><?= (int) ($courseStats['learners'] ?? 0) ?></strong> learners have tracked progress on this course.</p>
                <p><strong><?= (int) round((float) ($courseStats['avg_progress'] ?? 0)) ?>%</strong> average completion.</p>
                <p class="muted"><?= $totalItems ?> total learning steps across resources and quiz questions.</p>
                <a class="button small" href="admin-courses.php?edit=<?= $courseId ?>#resources">Back to course management</a>
            <?php elseif ($user): ?>
                <h2>Progress</h2>
                <p><strong><?= $progressPercent ?>%</strong> complete</p>
                <div class="progress"><span style="width: <?= $progressPercent ?>%"></span></div>
                <p class="muted"><?= $completedItems ?> of <?= $totalItems ?> learning steps completed.</p>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle_saved">
                    <label class="checkbox"><input type="checkbox" name="saved" <?= !empty($progress['saved']) ? 'checked' : '' ?>> Save this course</label>
                    <button type="submit">Update Saved Status</button>
                </form>
            <?php else: ?>
                <h2>Progress</h2>
                <p><a href="login.php">Login</a> to save this course, mark learning steps complete, and track quiz progress automatically.</p>
            <?php endif; ?>
        </aside>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
