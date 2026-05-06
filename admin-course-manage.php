<?php
session_start();
require_once __DIR__ . '/includes/admin-course-manager.php';
require_once __DIR__ . '/includes/admin-shell.php';
require_once __DIR__ . '/includes/csrf.php';

$user = require_admin();
$courseId = (int) ($_GET['id'] ?? 0);
$isNewCourse = isset($_GET['new']) || $courseId <= 0;
$tab = $_GET['tab'] ?? 'overview';
$tab = in_array($tab, ['overview', 'resources', 'students', 'feedback'], true) ? $tab : 'overview';
$course = $isNewCourse ? null : admin_fetch_course($courseId);

if (!$isNewCourse && !$course) {
    http_response_code(404);
    exit('Course not found.');
}

$messageMap = [
    'created' => 'Course created. Add lessons to build the learning path.',
    'saved' => 'Course overview saved.',
    'resource_created' => 'Lesson added to the course.',
    'resource_deleted' => 'Lesson removed.',
    'reordered' => 'Lesson order updated.',
];
$message = $messageMap[$_GET['notice'] ?? ''] ?? '';
$error = '';
$formValues = admin_course_form_defaults($course);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_course') {
        $result = admin_save_course($_POST, $formValues, $course ? (int) $course['id'] : null);
        if (!$result['ok']) {
            $error = $result['error'];
        } else {
            $savedCourseId = (int) $result['id'];
            $redirectTab = $course ? 'overview' : 'resources';
            $notice = $course ? 'saved' : 'created';
            header('Location: ' . admin_course_url($savedCourseId, $redirectTab) . '?notice=' . $notice);
            exit;
        }
    }

    if ($course) {
        if ($action === 'create_resource') {
            $result = admin_create_course_item((int) $course['id'], $_POST);
            if (!$result['ok']) {
                $error = $result['error'];
            } else {
                header('Location: ' . admin_course_url((int) $course['id'], 'resources') . '?notice=resource_created');
                exit;
            }
        }

        if ($action === 'delete_item') {
            $kind = $_POST['kind'] ?? 'resource';
            $itemId = (int) ($_POST['item_id'] ?? 0);
            admin_delete_course_item($kind, $itemId);
            header('Location: ' . admin_course_url((int) $course['id'], 'resources') . '?notice=resource_deleted');
            exit;
        }

        if ($action === 'reorder_items') {
            $orderedItems = array_filter(array_map('trim', explode(',', (string) ($_POST['ordered_items'] ?? ''))));
            admin_update_course_item_order((int) $course['id'], $orderedItems);
            header('Location: ' . admin_course_url((int) $course['id'], 'resources') . '?notice=reordered');
            exit;
        }
    }
}

$metrics = $course ? admin_fetch_course_metrics((int) $course['id']) : [
    'resource_count' => 0,
    'quiz_count' => 0,
    'enrollment_count' => 0,
    'average_rating' => 0,
    'review_count' => 0,
];
$resourceItems = $course ? admin_fetch_course_items((int) $course['id']) : [];
$students = $course ? admin_fetch_course_students((int) $course['id']) : [];
$feedbackRows = $course ? admin_fetch_course_feedback((int) $course['id']) : [];
$pageTitle = $isNewCourse ? 'Create Course' : 'Manage Course';
admin_render_start([
    'title' => $pageTitle,
    'page_title' => $course ? $course['title'] : 'Create Course',
    'page_subtitle' => $isNewCourse ? 'Start with course metadata, then build the learning path.' : 'Overview, resources, students, and feedback in one focused workspace.',
    'active_nav' => 'courses',
    'breadcrumbs' => [
        ['label' => 'Dashboard', 'href' => app_url('admin')],
        ['label' => 'Courses', 'href' => app_url('admin/courses')],
        ['label' => $course ? $course['title'] : 'New Course'],
    ],
    'actions' => array_values(array_filter([
        ['label' => 'Course List', 'href' => app_url('admin/courses'), 'secondary' => true],
        $course ? ['label' => 'Preview Course', 'href' => admin_course_preview_url((int) $course['id'])] : null,
    ])),
    'notice' => $message,
    'error' => $error,
    'user' => $user,
]);
?>

<?php if ($course): ?>
    <div class="admin-stats-row">
        <article class="panel admin-stat-card"><strong><?= (int) $metrics['resource_count'] ?></strong><span class="muted">Resources</span></article>
        <article class="panel admin-stat-card"><strong><?= (int) $metrics['quiz_count'] ?></strong><span class="muted">Quizzes</span></article>
        <article class="panel admin-stat-card"><strong><?= (int) $metrics['enrollment_count'] ?></strong><span class="muted">Students</span></article>
        <article class="panel admin-stat-card"><strong><?= number_format((float) $metrics['average_rating'], 1) ?></strong><span class="muted">Average rating</span></article>
        <article class="panel admin-stat-card"><strong><?= (int) $metrics['review_count'] ?></strong><span class="muted">Reviews</span></article>
    </div>

    <nav class="admin-app-nav" style="grid-template-columns: repeat(4, minmax(0, 1fr));">
        <?php foreach (admin_course_nav_items((int) $course['id']) as $key => $item): ?>
            <a class="<?= $tab === $key ? 'active' : '' ?>" href="<?= htmlspecialchars($item['href']) ?>"><?= htmlspecialchars($item['label']) ?></a>
        <?php endforeach; ?>
    </nav>
<?php endif; ?>

<?php if ($tab === 'overview' || $isNewCourse): ?>
                <section class="panel admin-form-panel">
                    <div class="section-head compact">
                        <div>
                            <span class="eyebrow">Overview</span>
                            <h2>Course metadata and visuals</h2>
                        </div>
                    </div>

                    <form method="post" enctype="multipart/form-data" class="admin-course-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="save_course">
                        <input type="hidden" name="existing_thumbnail_path" value="<?= htmlspecialchars($formValues['thumbnail_path']) ?>">
                        <input type="hidden" name="existing_banner_path" value="<?= htmlspecialchars($formValues['banner_path']) ?>">

                        <div class="admin-form-grid">
                            <label>Course title <input name="title" value="<?= htmlspecialchars($formValues['title']) ?>" required></label>
                            <label>Subject <input name="subject" value="<?= htmlspecialchars($formValues['subject']) ?>" required></label>
                            <label>Level <input name="level" value="<?= htmlspecialchars($formValues['level']) ?>" required></label>
                            <label>Thumbnail image <input name="thumbnail_image" type="file" accept="image/jpeg,image/png,image/webp,image/gif"></label>
                            <label class="admin-form-span-2">Banner image <input name="banner_image" type="file" accept="image/jpeg,image/png,image/webp,image/gif"></label>
                            <label class="admin-form-span-2">Description <textarea name="description" required><?= htmlspecialchars($formValues['description']) ?></textarea></label>
                        </div>

                        <div class="admin-asset-preview-grid">
                            <div class="panel admin-asset-preview">
                                <span class="eyebrow">Thumbnail</span>
                                <?php if ($formValues['thumbnail_path'] !== ''): ?>
                                    <img src="<?= htmlspecialchars($formValues['thumbnail_path']) ?>" alt="Course thumbnail preview">
                                <?php else: ?>
                                    <p class="muted">Upload a thumbnail to support richer course cards and previews.</p>
                                <?php endif; ?>
                            </div>
                            <div class="panel admin-asset-preview">
                                <span class="eyebrow">Banner</span>
                                <?php if ($formValues['banner_path'] !== ''): ?>
                                    <img src="<?= htmlspecialchars($formValues['banner_path']) ?>" alt="Course banner preview">
                                <?php else: ?>
                                    <p class="muted">Upload a banner to strengthen the course detail hero.</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="actions">
                            <button type="submit"><?= $course ? 'Save Overview' : 'Create Course' ?></button>
                            <?php if ($course): ?>
                                <a class="button ghost" href="<?= htmlspecialchars(admin_course_url((int) $course['id'], 'resources')) ?>">Go to Resources</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </section>
<?php elseif ($tab === 'resources'): ?>
                <div class="admin-content-grid">
                    <section class="panel">
                        <div class="section-head compact">
                            <div>
                                <span class="eyebrow">Resources</span>
                                <h2>Lesson library</h2>
                            </div>
                            <p class="muted">Drag to reorder, then save the sequence.</p>
                        </div>

                        <form method="post" class="admin-order-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="reorder_items">
                            <input type="hidden" name="ordered_items" value="<?= htmlspecialchars(implode(',', array_map(static fn(array $item): string => $item['kind'] . ':' . $item['id'], $resourceItems))) ?>" data-order-input>
                            <div class="admin-resource-list" data-sortable-list>
                                <?php foreach ($resourceItems as $item): ?>
                                    <article class="admin-resource-row" draggable="true" data-sort-token="<?= htmlspecialchars($item['kind'] . ':' . $item['id']) ?>">
                                        <div class="admin-resource-handle" aria-hidden="true">&#9776;</div>
                                        <div class="admin-resource-main">
                                            <div class="card-topline">
                                                <span class="tag"><?= htmlspecialchars(ucfirst($item['resource_type'])) ?></span>
                                                <span class="muted">Order <?= (int) $item['sort_order'] ?></span>
                                            </div>
                                            <strong><?= htmlspecialchars($item['title']) ?></strong>
                                            <p class="muted"><?= htmlspecialchars($item['meta']) ?></p>
                                        </div>
                                        <div class="actions">
                                            <a class="button small ghost" href="<?= htmlspecialchars(admin_resource_edit_url((int) $item['id'], $item['kind'])) ?>">Edit</a>
                                            <form method="post" class="inline-form">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete_item">
                                                <input type="hidden" name="kind" value="<?= htmlspecialchars($item['kind']) ?>">
                                                <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                                                <button class="button small danger" type="submit" data-confirm="Delete this lesson item?">Delete</button>
                                            </form>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                            <div class="actions">
                                <button type="submit" class="button ghost">Save Lesson Order</button>
                            </div>
                        </form>
                    </section>

                    <aside class="panel">
                        <div class="section-head compact">
                            <div>
                                <span class="eyebrow">Add Lesson</span>
                                <h2>Create a new resource</h2>
                            </div>
                        </div>
                        <form method="post" enctype="multipart/form-data" class="resource-builder">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="create_resource">
                            <label>Resource Type
                                <select name="resource_type" data-resource-select>
                                    <option value="note">Lecture Note</option>
                                    <option value="video">Video</option>
                                    <option value="quiz">Quiz Question</option>
                                </select>
                            </label>

                            <div data-resource-fields="note">
                                <label>Note title <input name="note_title"></label>
                                <label>Note content <textarea name="note_content"></textarea></label>
                                <label>Attachment / PDF <input name="note_attachment" type="file" accept=".pdf,.doc,.docx,.txt,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,text/plain"></label>
                            </div>

                            <div data-resource-fields="video" hidden>
                                <label>Video title <input name="video_title"></label>
                                <label>Video URL <input name="video_url" placeholder="https://www.youtube.com/watch?v=..."></label>
                                <label>Description <textarea name="video_description"></textarea></label>
                                <label>Thumbnail <input name="video_thumbnail" type="file" accept="image/jpeg,image/png,image/webp,image/gif"></label>
                            </div>

                            <div data-resource-fields="quiz" hidden>
                                <label>Quiz title <input name="quiz_title" placeholder="Knowledge Check"></label>
                                <label>Question <textarea name="quiz_question"></textarea></label>
                                <label>Option A <input name="option_a"></label>
                                <label>Option B <input name="option_b"></label>
                                <label>Option C <input name="option_c"></label>
                                <label>Correct Answer
                                    <select name="correct_option">
                                        <option value="">Choose one</option>
                                        <option value="A">A</option>
                                        <option value="B">B</option>
                                        <option value="C">C</option>
                                    </select>
                                </label>
                                <label>Explanation <textarea name="quiz_explanation"></textarea></label>
                            </div>

                            <button type="submit">Add Lesson</button>
                        </form>
                    </aside>
                </div>
<?php elseif ($tab === 'students'): ?>
                <section class="panel">
                    <div class="section-head compact">
                        <div>
                            <span class="eyebrow">Students</span>
                            <h2>Enrollment and progress</h2>
                        </div>
                    </div>

                    <?php if (!$students): ?>
                        <p class="muted">No enrolled students yet.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Enrolled</th>
                                    <th>Progress</th>
                                    <th>Saved</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($student['name']) ?></td>
                                        <td><?= htmlspecialchars($student['email']) ?></td>
                                        <td><?= htmlspecialchars($student['enrolled_at']) ?></td>
                                        <td><?= (int) $student['progress_percent'] ?>%</td>
                                        <td><?= !empty($student['saved']) ? 'Saved' : 'Not saved' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </section>
<?php elseif ($tab === 'feedback'): ?>
                <section class="panel">
                    <div class="section-head compact">
                        <div>
                            <span class="eyebrow">Feedback</span>
                            <h2>Ratings and learner comments</h2>
                        </div>
                    </div>

                    <?php if (!$feedbackRows): ?>
                        <p class="muted">No learner feedback yet.</p>
                    <?php else: ?>
                        <div class="admin-feedback-list">
                            <?php foreach ($feedbackRows as $feedback): ?>
                                <article class="panel admin-feedback-card">
                                    <div class="card-topline">
                                        <strong><?= htmlspecialchars($feedback['name']) ?></strong>
                                        <span class="tag"><?= (int) $feedback['rating'] ?>/5</span>
                                    </div>
                                    <p class="muted"><?= htmlspecialchars($feedback['email']) ?> | <?= htmlspecialchars($feedback['updated_at']) ?></p>
                                    <p><?= $feedback['comment'] !== '' ? nl2br(htmlspecialchars($feedback['comment'])) : 'Rated without a written comment.' ?></p>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
<?php endif; ?>

<?php admin_render_end(); ?>
