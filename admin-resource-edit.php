<?php
session_start();
require_once __DIR__ . '/includes/admin-course-manager.php';
require_once __DIR__ . '/includes/admin-shell.php';
require_once __DIR__ . '/includes/csrf.php';

$user = require_admin();
$itemId = (int) ($_GET['id'] ?? 0);
$kind = ($_GET['kind'] ?? 'resource') === 'quiz' ? 'quiz' : 'resource';
$item = admin_fetch_editable_item($itemId, $kind);

if (!$item) {
    http_response_code(404);
    exit('Resource not found.');
}

$courseId = (int) $item['course_id'];
$course = admin_fetch_course($courseId);
if (!$course) {
    http_response_code(404);
    exit('Course not found.');
}

$error = '';
$message = ($_GET['notice'] ?? '') === 'saved' ? 'Resource updated.' : '';
$formValues = admin_resource_form_defaults($item, $kind);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if ($kind === 'quiz') {
        $result = admin_update_quiz_item($itemId, $_POST, $formValues);
    } else {
        $result = admin_update_resource_item($itemId, $_POST, $formValues);
    }

    if (!$result['ok']) {
        $error = $result['error'];
    } else {
        header('Location: ' . admin_resource_edit_url($itemId, $kind) . '&notice=saved');
        exit;
    }
}

$pageTitle = 'Edit Resource';
admin_render_start([
    'title' => $pageTitle,
    'page_title' => $kind === 'quiz' ? 'Edit Quiz Item' : 'Edit Resource',
    'page_subtitle' => $course['title'],
    'active_nav' => 'courses',
    'breadcrumbs' => [
        ['label' => 'Dashboard', 'href' => app_url('admin')],
        ['label' => 'Courses', 'href' => app_url('admin/courses')],
        ['label' => $course['title'], 'href' => admin_course_url($courseId, 'resources')],
        ['label' => 'Edit Resource'],
    ],
    'actions' => [
        ['label' => 'Back to Resources', 'href' => admin_course_url($courseId, 'resources'), 'secondary' => true],
        ['label' => 'Preview Course', 'href' => admin_course_preview_url($courseId)],
    ],
    'notice' => $message,
    'error' => $error,
    'user' => $user,
]);
?>

<section class="panel admin-form-panel">
                <?php if ($kind === 'quiz'): ?>
                    <form method="post" class="admin-course-form">
                        <?= csrf_field() ?>
                        <div class="admin-form-grid">
                            <label>Quiz title <input name="title" value="<?= htmlspecialchars($formValues['title']) ?>"></label>
                            <label>Order <input name="sort_order" type="number" min="1" value="<?= htmlspecialchars($formValues['sort_order']) ?>"></label>
                            <label class="admin-form-span-2">Question <textarea name="question" required><?= htmlspecialchars($formValues['question']) ?></textarea></label>
                            <label>Option A <input name="option_a" value="<?= htmlspecialchars($formValues['option_a']) ?>" required></label>
                            <label>Option B <input name="option_b" value="<?= htmlspecialchars($formValues['option_b']) ?>" required></label>
                            <label>Option C <input name="option_c" value="<?= htmlspecialchars($formValues['option_c']) ?>" required></label>
                            <label>Correct Answer
                                <select name="correct_option" required>
                                    <option value="">Choose one</option>
                                    <option value="A" <?= $formValues['correct_option'] === 'A' ? 'selected' : '' ?>>A</option>
                                    <option value="B" <?= $formValues['correct_option'] === 'B' ? 'selected' : '' ?>>B</option>
                                    <option value="C" <?= $formValues['correct_option'] === 'C' ? 'selected' : '' ?>>C</option>
                                </select>
                            </label>
                            <label class="admin-form-span-2">Explanation <textarea name="explanation"><?= htmlspecialchars($formValues['explanation']) ?></textarea></label>
                        </div>
                        <button type="submit">Save Quiz Question</button>
                    </form>
                <?php else: ?>
                    <form method="post" enctype="multipart/form-data" class="admin-course-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="existing_attachment_path" value="<?= htmlspecialchars($formValues['attachment_path']) ?>">
                        <input type="hidden" name="existing_thumbnail_path" value="<?= htmlspecialchars($formValues['thumbnail_path']) ?>">

                        <div class="admin-form-grid">
                            <label>Title <input name="title" value="<?= htmlspecialchars($formValues['title']) ?>" required></label>
                            <label>Order <input name="sort_order" type="number" min="1" value="<?= htmlspecialchars($formValues['sort_order']) ?>"></label>
                            <?php if ($formValues['resource_type'] === 'video'): ?>
                                <label class="admin-form-span-2">Video URL <input name="resource_url" value="<?= htmlspecialchars($formValues['resource_url']) ?>" required></label>
                                <label class="admin-form-span-2">Description <textarea name="content"><?= htmlspecialchars($formValues['content']) ?></textarea></label>
                                <label>Thumbnail image <input name="thumbnail_image" type="file" accept="image/jpeg,image/png,image/webp,image/gif"></label>
                            <?php else: ?>
                                <label class="admin-form-span-2">Note content <textarea name="content" required><?= htmlspecialchars($formValues['content']) ?></textarea></label>
                                <label>Attachment / PDF <input name="attachment_file" type="file" accept=".pdf,.doc,.docx,.txt,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,text/plain"></label>
                            <?php endif; ?>
                        </div>

                        <?php if ($formValues['resource_type'] === 'video' && $formValues['thumbnail_path'] !== ''): ?>
                            <div class="panel admin-asset-preview">
                                <span class="eyebrow">Current Thumbnail</span>
                                <img src="<?= htmlspecialchars($formValues['thumbnail_path']) ?>" alt="Resource thumbnail preview">
                            </div>
                        <?php elseif ($formValues['resource_type'] === 'note' && $formValues['attachment_path'] !== ''): ?>
                            <div class="panel admin-asset-preview">
                                <span class="eyebrow">Current Attachment</span>
                                <p><a href="<?= htmlspecialchars($formValues['attachment_path']) ?>" target="_blank" rel="noopener">Open attached file</a></p>
                            </div>
                        <?php endif; ?>

                        <button type="submit">Save Resource</button>
                    </form>
                <?php endif; ?>
            </section>

<?php admin_render_end(); ?>
