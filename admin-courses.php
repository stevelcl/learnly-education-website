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
        $editing = null;
        $editingId = 0;
    } elseif ($action === 'delete_resource') {
        $stmt = db()->prepare('DELETE FROM course_resources WHERE id = ?');
        $stmt->execute([(int) $_POST['resource_id']]);
        $message = 'Resource deleted.';
    } elseif ($action === 'delete_quiz') {
        $stmt = db()->prepare('DELETE FROM quiz_questions WHERE id = ?');
        $stmt->execute([(int) $_POST['quiz_id']]);
        $message = 'Quiz question deleted.';
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
                $editingId = (int) db()->lastInsertId();
                $editing = fetch_one('SELECT * FROM courses WHERE id = ?', [$editingId]);
                $message = 'Course added. You can now add notes, video, and quizzes below.';
            }
        }
    }

    if ($action === 'add_note') {
        $courseId = (int) $_POST['course_id'];
        $noteTitle = trim($_POST['note_title'] ?? '');
        $noteContent = trim($_POST['note_content'] ?? '');
        if ($noteTitle !== '' && $noteContent !== '') {
            $resourceStmt = db()->prepare(
                'INSERT INTO course_resources (course_id, title, resource_type, content, sort_order) VALUES (?, ?, "note", ?, ?)'
            );
            $resourceStmt->execute([$courseId, $noteTitle, $noteContent, ((int) $_POST['sort_order'] ?: 1)]);
            $message = 'Lecture note added.';
        }
    }

    if ($action === 'add_video') {
        $courseId = (int) $_POST['course_id'];
        $videoTitle = trim($_POST['video_title'] ?? '');
        $videoUrl = trim($_POST['video_url'] ?? '');
        $videoDescription = trim($_POST['video_description'] ?? '');
        if ($videoTitle !== '' && $videoUrl !== '') {
            $resourceStmt = db()->prepare(
                'INSERT INTO course_resources (course_id, title, resource_type, content, resource_url, sort_order) VALUES (?, ?, "video", ?, ?, ?)'
            );
            $resourceStmt->execute([$courseId, $videoTitle, $videoDescription ?: 'Video lesson resource.', $videoUrl, ((int) $_POST['sort_order'] ?: 2)]);
            $message = 'Video resource added.';
        }
    }

    if ($action === 'add_quiz') {
        $courseId = (int) $_POST['course_id'];
        $quizQuestion = trim($_POST['quiz_question'] ?? '');
        $optionA = trim($_POST['option_a'] ?? '');
        $optionB = trim($_POST['option_b'] ?? '');
        $optionC = trim($_POST['option_c'] ?? '');
        $correctOption = trim($_POST['correct_option'] ?? '');
        if (
            $quizQuestion !== '' &&
            $optionA !== '' &&
            $optionB !== '' &&
            $optionC !== '' &&
            in_array($correctOption, ['A', 'B', 'C'], true)
        ) {
            $resourceStmt = db()->prepare(
                'INSERT INTO course_resources (course_id, title, resource_type, content, sort_order) VALUES (?, ?, "quiz", ?, ?)'
            );
            $resourceStmt->execute([$courseId, 'Interactive Quiz', 'Quiz generated from admin course setup.', ((int) $_POST['sort_order'] ?: 3)]);

            $quizStmt = db()->prepare(
                'INSERT INTO quiz_questions (course_id, question, option_a, option_b, option_c, correct_option) VALUES (?, ?, ?, ?, ?, ?)'
            );
            $quizStmt->execute([$courseId, $quizQuestion, $optionA, $optionB, $optionC, $correctOption]);
            $message = 'Quiz question added.';
        }
    }

    if ($editingId > 0) {
        $editing = fetch_one('SELECT * FROM courses WHERE id = ?', [$editingId]);
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
                            <a class="button small ghost" href="course.php?id=<?= (int) $course['id'] ?>">View</a>
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

            <?php if ($editing): ?>
                <hr>
                <h2>Add Learning Resources</h2>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_note">
                    <input type="hidden" name="course_id" value="<?= (int) $editing['id'] ?>">
                    <label>Lecture Note Title <input name="note_title" required></label>
                    <label>Lecture Note Content <textarea name="note_content" required></textarea></label>
                    <label>Sort Order <input type="number" name="sort_order" value="1" min="1"></label>
                    <button type="submit">Add Note</button>
                </form>

                <form method="post" style="margin-top: 1rem;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_video">
                    <input type="hidden" name="course_id" value="<?= (int) $editing['id'] ?>">
                    <label>Video Title <input name="video_title" required></label>
                    <label>Video URL <input name="video_url" placeholder="https://www.youtube.com/embed/..." required></label>
                    <label>Video Description <textarea name="video_description"></textarea></label>
                    <label>Sort Order <input type="number" name="sort_order" value="2" min="1"></label>
                    <button type="submit">Add Video</button>
                </form>

                <form method="post" style="margin-top: 1rem;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_quiz">
                    <input type="hidden" name="course_id" value="<?= (int) $editing['id'] ?>">
                    <label>Quiz Question <input name="quiz_question" required></label>
                    <label>Option A <input name="option_a" required></label>
                    <label>Option B <input name="option_b" required></label>
                    <label>Option C <input name="option_c" required></label>
                    <label>Correct Option
                        <select name="correct_option" required>
                            <option value="">Choose one</option>
                            <option value="A">A</option>
                            <option value="B">B</option>
                            <option value="C">C</option>
                        </select>
                    </label>
                    <label>Sort Order <input type="number" name="sort_order" value="3" min="1"></label>
                    <button type="submit">Add Quiz</button>
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
