<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/media.php';
$user = require_admin();

$noticeMap = [
    'course_created' => 'Course added. You can now build the learning flow below.',
    'course_updated' => 'Course updated.',
    'course_deleted' => 'Course deleted.',
    'resource_deleted' => 'Resource deleted.',
    'quiz_deleted' => 'Quiz question deleted.',
    'resource_added' => 'Learning resource added.',
];
$message = $noticeMap[$_GET['notice'] ?? ''] ?? '';

$editingId = (int) ($_GET['edit'] ?? 0);
$editing = $editingId ? fetch_one('SELECT * FROM courses WHERE id = ?', [$editingId]) : null;
$formValues = [
    'title' => $editing['title'] ?? '',
    'subject' => $editing['subject'] ?? '',
    'description' => $editing['description'] ?? '',
    'level' => $editing['level'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $stmt = db()->prepare('DELETE FROM courses WHERE id = ?');
        $stmt->execute([(int) $_POST['course_id']]);
        header('Location: admin-courses.php?notice=course_deleted');
        exit;
    }

    if ($action === 'delete_resource') {
        $courseId = (int) $_POST['course_id'];
        $stmt = db()->prepare('DELETE FROM course_resources WHERE id = ?');
        $stmt->execute([(int) $_POST['resource_id']]);
        header('Location: admin-courses.php?edit=' . $courseId . '&notice=resource_deleted#resources');
        exit;
    }

    if ($action === 'delete_quiz') {
        $courseId = (int) $_POST['course_id'];
        $stmt = db()->prepare('DELETE FROM quiz_questions WHERE id = ?');
        $stmt->execute([(int) $_POST['quiz_id']]);
        header('Location: admin-courses.php?edit=' . $courseId . '&notice=quiz_deleted#resources');
        exit;
    }

    if ($action === 'add_resource') {
        $courseId = (int) $_POST['course_id'];
        $resourceType = $_POST['resource_type'] ?? 'note';
        $sortOrder = max(1, (int) ($_POST['sort_order'] ?? 1));

        if ($resourceType === 'note') {
            $title = trim($_POST['note_title'] ?? '');
            $content = trim($_POST['note_content'] ?? '');
            if ($title !== '' && $content !== '') {
                $stmt = db()->prepare(
                    'INSERT INTO course_resources (course_id, title, resource_type, content, sort_order) VALUES (?, ?, "note", ?, ?)'
                );
                $stmt->execute([$courseId, $title, $content, $sortOrder]);
            }
        }

        if ($resourceType === 'video') {
            $title = trim($_POST['video_title'] ?? '');
            $description = trim($_POST['video_description'] ?? '');
            $videoUrl = video_embed_src(trim($_POST['video_url'] ?? ''));
            if ($title !== '' && $videoUrl !== '') {
                $stmt = db()->prepare(
                    'INSERT INTO course_resources (course_id, title, resource_type, content, resource_url, sort_order) VALUES (?, ?, "video", ?, ?, ?)'
                );
                $stmt->execute([$courseId, $title, $description ?: 'Video lesson resource.', $videoUrl, $sortOrder]);
            }
        }

        if ($resourceType === 'quiz') {
            $quizTitle = trim($_POST['quiz_title'] ?? '');
            $quizDescription = trim($_POST['quiz_description'] ?? '');
            $questionText = trim($_POST['quiz_question'] ?? '');
            $optionA = trim($_POST['option_a'] ?? '');
            $optionB = trim($_POST['option_b'] ?? '');
            $optionC = trim($_POST['option_c'] ?? '');
            $correctOption = trim($_POST['correct_option'] ?? '');

            if (
                $questionText !== '' &&
                $optionA !== '' &&
                $optionB !== '' &&
                $optionC !== '' &&
                in_array($correctOption, ['A', 'B', 'C'], true)
            ) {
                $quizResource = fetch_one(
                    'SELECT id FROM course_resources WHERE course_id = ? AND resource_type = "quiz" ORDER BY id LIMIT 1',
                    [$courseId]
                );

                if (!$quizResource) {
                    $stmt = db()->prepare(
                        'INSERT INTO course_resources (course_id, title, resource_type, content, sort_order) VALUES (?, ?, "quiz", ?, ?)'
                    );
                    $stmt->execute([
                        $courseId,
                        $quizTitle !== '' ? $quizTitle : 'Interactive Quiz',
                        $quizDescription !== '' ? $quizDescription : 'Quiz activity for this course.',
                        $sortOrder,
                    ]);
                }

                $quizStmt = db()->prepare(
                    'INSERT INTO quiz_questions (course_id, question, option_a, option_b, option_c, correct_option) VALUES (?, ?, ?, ?, ?, ?)'
                );
                $quizStmt->execute([$courseId, $questionText, $optionA, $optionB, $optionC, $correctOption]);
            }
        }

        header('Location: admin-courses.php?edit=' . $courseId . '&notice=resource_added#resources');
        exit;
    }

    $title = trim($_POST['title'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $level = trim($_POST['level'] ?? '');
    $formValues = [
        'title' => $title,
        'subject' => $subject,
        'description' => $description,
        'level' => $level,
    ];

    if ($title !== '' && $subject !== '' && $description !== '' && $level !== '') {
        if ($action === 'update') {
            $courseId = (int) $_POST['course_id'];
            $stmt = db()->prepare('UPDATE courses SET title = ?, subject = ?, description = ?, level = ? WHERE id = ?');
            $stmt->execute([$title, $subject, $description, $level, $courseId]);
            header('Location: admin-courses.php?edit=' . $courseId . '&notice=course_updated');
            exit;
        }

        $stmt = db()->prepare('INSERT INTO courses (title, subject, description, level) VALUES (?, ?, ?, ?)');
        $stmt->execute([$title, $subject, $description, $level]);
        $newId = (int) db()->lastInsertId();
        header('Location: admin-courses.php?edit=' . $newId . '&notice=course_created#resources');
        exit;
    }
}

$courses = fetch_all(
    'SELECT c.*, COUNT(cr.id) AS resource_count
     FROM courses c
     LEFT JOIN course_resources cr ON cr.course_id = c.id
     GROUP BY c.id
     ORDER BY c.created_at DESC'
);

$courseResources = $editingId ? fetch_all('SELECT * FROM course_resources WHERE course_id = ? ORDER BY sort_order, id', [$editingId]) : [];
$courseQuizzes = $editingId ? fetch_all('SELECT * FROM quiz_questions WHERE course_id = ? ORDER BY id', [$editingId]) : [];

$pageTitle = 'Manage Courses';
include __DIR__ . '/includes/header.php';
?>

<section class="section">
    <div class="container layout">
        <div>
            <div class="section-head">
                <div>
                    <span class="eyebrow">Admin Courses</span>
                    <h1>Create, edit, and extend course modules.</h1>
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
                            <a class="button small ghost" href="course.php?id=<?= (int) $course['id'] ?>&amp;admin_preview=1">Admin Preview</a>
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
                <label>Title <input name="title" value="<?= htmlspecialchars($formValues['title']) ?>" required></label>
                <label>Subject <input name="subject" value="<?= htmlspecialchars($formValues['subject']) ?>" required></label>
                <label>Level <input name="level" value="<?= htmlspecialchars($formValues['level']) ?>" required></label>
                <label>Description <textarea name="description" required><?= htmlspecialchars($formValues['description']) ?></textarea></label>
                <div class="actions">
                    <button type="submit"><?= $editing ? 'Update Course' : 'Add Course' ?></button>
                    <a class="button ghost" href="admin-courses.php">Clear All</a>
                </div>
            </form>

            <?php if ($editing): ?>
                <hr>
                <h2 id="resources">Add Learning Resources</h2>
                <form method="post" class="resource-builder">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_resource">
                    <input type="hidden" name="course_id" value="<?= (int) $editing['id'] ?>">
                    <label>Resource Type
                        <select name="resource_type" data-resource-select>
                            <option value="note">Lecture Note</option>
                            <option value="video">Video</option>
                            <option value="quiz">Quiz Question</option>
                        </select>
                    </label>

                    <div data-resource-fields="note">
                        <label>Lecture Note Title <input name="note_title"></label>
                        <label>Lecture Note Content <textarea name="note_content"></textarea></label>
                    </div>

                    <div data-resource-fields="video" hidden>
                        <label>Video Title <input name="video_title"></label>
                        <label>Video URL <input name="video_url" placeholder="https://www.youtube.com/watch?v=..."></label>
                        <label>Video Description <textarea name="video_description"></textarea></label>
                    </div>

                    <div data-resource-fields="quiz" hidden>
                        <label>Quiz Title <input name="quiz_title" placeholder="Interactive Quiz"></label>
                        <label>Quiz Description <textarea name="quiz_description"></textarea></label>
                        <label>Quiz Question <input name="quiz_question"></label>
                        <label>Option A <input name="option_a"></label>
                        <label>Option B <input name="option_b"></label>
                        <label>Option C <input name="option_c"></label>
                        <label>Correct Option
                            <select name="correct_option">
                                <option value="">Choose one</option>
                                <option value="A">A</option>
                                <option value="B">B</option>
                                <option value="C">C</option>
                            </select>
                        </label>
                    </div>

                    <label>Sort Order <input type="number" name="sort_order" value="1" min="1"></label>
                    <button type="submit">Add Resource</button>
                </form>
            <?php endif; ?>
        </aside>
    </div>
</section>

<?php if ($editing): ?>
<section class="section">
    <div class="container layout">
        <div class="panel">
            <h2>Existing Resources</h2>
            <?php if (!$courseResources): ?>
                <p class="muted">No course resources added yet.</p>
            <?php endif; ?>
            <?php foreach ($courseResources as $resource): ?>
                <div class="panel" style="margin-bottom: 0.9rem;">
                    <div class="card-topline">
                        <span class="tag"><?= htmlspecialchars($resource['resource_type']) ?></span>
                        <span class="muted">Order <?= (int) $resource['sort_order'] ?></span>
                    </div>
                    <h3><?= htmlspecialchars($resource['title']) ?></h3>
                    <p><?= htmlspecialchars($resource['content']) ?></p>
                    <?php if (!empty($resource['resource_url'])): ?>
                        <p><a href="<?= htmlspecialchars($resource['resource_url']) ?>" target="_blank" rel="noopener">Open video URL</a></p>
                    <?php endif; ?>
                    <form method="post" class="inline-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_resource">
                        <input type="hidden" name="course_id" value="<?= (int) $editing['id'] ?>">
                        <input type="hidden" name="resource_id" value="<?= (int) $resource['id'] ?>">
                        <button class="button small danger" type="submit">Delete Resource</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>

        <aside class="panel">
            <h2>Quiz Questions</h2>
            <?php if (!$courseQuizzes): ?>
                <p class="muted">No quiz questions added yet.</p>
            <?php endif; ?>
            <?php foreach ($courseQuizzes as $quiz): ?>
                <div class="panel" style="margin-bottom: 0.9rem;">
                    <h3><?= htmlspecialchars($quiz['question']) ?></h3>
                    <p>A. <?= htmlspecialchars($quiz['option_a']) ?></p>
                    <p>B. <?= htmlspecialchars($quiz['option_b']) ?></p>
                    <p>C. <?= htmlspecialchars($quiz['option_c']) ?></p>
                    <p class="muted">Correct: <?= htmlspecialchars($quiz['correct_option']) ?></p>
                    <form method="post" class="inline-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_quiz">
                        <input type="hidden" name="course_id" value="<?= (int) $editing['id'] ?>">
                        <input type="hidden" name="quiz_id" value="<?= (int) $quiz['id'] ?>">
                        <button class="button small danger" type="submit">Delete Question</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </aside>
    </div>
</section>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
