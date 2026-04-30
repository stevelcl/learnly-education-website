<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
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

$pageTitle = 'Learner Progress';
include __DIR__ . '/includes/header.php';
?>

<section class="section">
    <div class="container">
        <div class="section-head">
            <div>
                <span class="eyebrow">Admin Progress</span>
                <h1>Track how learners are moving through course content.</h1>
            </div>
            <a class="button ghost" href="admin-dashboard.php">Back to Admin</a>
        </div>

        <?php if (!$progressRows): ?>
            <p class="muted">No user progress has been recorded yet.</p>
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
                            <td><?= htmlspecialchars($row['name']) ?><br><span class="muted"><?= htmlspecialchars($row['email']) ?></span></td>
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
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
