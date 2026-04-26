<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

$courseId = (int) ($_GET['id'] ?? 0);
$course = fetch_one('SELECT * FROM courses WHERE id = ?', [$courseId]);
if (!$course) {
    http_response_code(404);
    exit('Course not found.');
}

$user = current_user();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    verify_csrf();
    $progress = max(0, min(100, (int) ($_POST['progress_percent'] ?? 0)));
    $saved = isset($_POST['saved']) ? 1 : 0;
    $stmt = db()->prepare(
        'INSERT INTO user_progress (user_id, course_id, progress_percent, saved)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE progress_percent = VALUES(progress_percent), saved = VALUES(saved)'
    );
    $stmt->execute([$user['id'], $courseId, $progress, $saved]);
    $message = 'Progress updated.';
}

$resources = fetch_all('SELECT * FROM course_resources WHERE course_id = ? ORDER BY sort_order, id', [$courseId]);
$questions = fetch_all('SELECT * FROM quiz_questions WHERE course_id = ?', [$courseId]);
$progress = $user ? fetch_one('SELECT * FROM user_progress WHERE user_id = ? AND course_id = ?', [$user['id'], $courseId]) : null;
$pageTitle = $course['title'];
include __DIR__ . '/includes/header.php';
?>

<section class="section">
    <div class="container layout">
        <div>
            <h1><?= htmlspecialchars($course['title']) ?></h1>
            <p class="muted"><?= htmlspecialchars($course['subject']) ?> · <?= htmlspecialchars($course['level']) ?></p>
            <p><?= htmlspecialchars($course['description']) ?></p>
            <?php if ($message): ?><p class="alert success"><?= htmlspecialchars($message) ?></p><?php endif; ?>

            <?php foreach ($resources as $resource): ?>
                <article class="panel">
                    <span class="tag"><?= htmlspecialchars($resource['resource_type']) ?></span>
                    <h2><?= htmlspecialchars($resource['title']) ?></h2>
                    <p><?= nl2br(htmlspecialchars($resource['content'])) ?></p>
                    <?php if ($resource['resource_type'] === 'video' && $resource['resource_url']): ?>
                        <div class="video-frame">
                            <iframe src="<?= htmlspecialchars($resource['resource_url']) ?>" title="<?= htmlspecialchars($resource['title']) ?>" allowfullscreen></iframe>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>

            <?php if ($questions): ?>
                <article class="panel">
                    <h2>Interactive Quiz</h2>
                    <?php foreach ($questions as $question): ?>
                        <details>
                            <summary><?= htmlspecialchars($question['question']) ?></summary>
                            <p>A. <?= htmlspecialchars($question['option_a']) ?></p>
                            <p>B. <?= htmlspecialchars($question['option_b']) ?></p>
                            <p>C. <?= htmlspecialchars($question['option_c']) ?></p>
                            <strong>Answer: <?= htmlspecialchars($question['correct_option']) ?></strong>
                        </details>
                    <?php endforeach; ?>
                </article>
            <?php endif; ?>
        </div>

        <aside class="panel">
            <h2>Progress</h2>
            <?php if ($user): ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <label>Completion
                        <input type="range" name="progress_percent" min="0" max="100" value="<?= (int) ($progress['progress_percent'] ?? 0) ?>">
                    </label>
                    <label class="checkbox"><input type="checkbox" name="saved" <?= !empty($progress['saved']) ? 'checked' : '' ?>> Save this course</label>
                    <button type="submit">Update</button>
                </form>
            <?php else: ?>
                <p><a href="login.php">Login</a> to save resources and track progress.</p>
            <?php endif; ?>
        </aside>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

