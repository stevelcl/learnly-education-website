<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-shell.php';

$user = require_admin();
$progressRows = fetch_all(
    'SELECT
        u.name,
        u.email,
        c.title,
        c.subject,
        COALESCE(up.saved, 0) AS saved,
        COALESCE(up.progress_percent, 0) AS progress_percent,
        up.updated_at,
        COALESCE(cp.completed_items, 0) AS completed_items,
        COALESCE(ct.total_items, 0) AS total_items
     FROM user_progress up
     JOIN users u ON u.id = up.user_id
     JOIN courses c ON c.id = up.course_id
     LEFT JOIN (
        SELECT user_id, course_id, COUNT(*) AS completed_items
        FROM course_item_progress
        GROUP BY user_id, course_id
     ) cp ON cp.user_id = up.user_id AND cp.course_id = up.course_id
     LEFT JOIN (
        SELECT grouped.course_id, COUNT(*) AS total_items
        FROM (
            SELECT course_id, id FROM course_resources WHERE resource_type <> "quiz"
            UNION ALL
            SELECT course_id, id FROM quiz_questions
        ) grouped
        GROUP BY grouped.course_id
     ) ct ON ct.course_id = up.course_id
     ORDER BY up.updated_at DESC'
);

admin_render_start([
    'title' => 'Progress Analytics',
    'page_title' => 'Progress Analytics',
    'page_subtitle' => 'Track enrollment momentum, saved courses, and course completion progress.',
    'active_nav' => 'analytics',
    'breadcrumbs' => [
        ['label' => 'Dashboard', 'href' => app_url('admin')],
        ['label' => 'Progress Analytics'],
    ],
    'user' => $user,
]);
?>

<section class="panel admin-data-table">
    <?php if (!$progressRows): ?>
        <div class="admin-empty-state"><strong>No progress recorded yet</strong><span>Learner progress will populate here once students start moving through lessons.</span></div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>User</th>
                    <th>Course</th>
                    <th>Progress</th>
                    <th>Completed Steps</th>
                    <th>Saved</th>
                    <th>Updated</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($progressRows as $row): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($row['name']) ?></strong><br><span class="muted"><?= htmlspecialchars($row['email']) ?></span></td>
                        <td><?= htmlspecialchars($row['title']) ?><br><span class="muted"><?= htmlspecialchars($row['subject']) ?></span></td>
                        <td><?= (int) $row['progress_percent'] ?>%</td>
                        <td><?= (int) $row['completed_items'] ?> / <?= (int) $row['total_items'] ?></td>
                        <td><?= !empty($row['saved']) ? 'Saved' : 'No' ?></td>
                        <td><?= htmlspecialchars((string) $row['updated_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<?php admin_render_end(); ?>
