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
        COALESCE(rs.average_rating, 0) AS average_rating,
        COALESCE(rs.review_count, 0) AS review_count,
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
     LEFT JOIN (
        SELECT course_id, AVG(rating) AS average_rating, COUNT(*) AS review_count
        FROM course_reviews
        GROUP BY course_id
     ) rs ON rs.course_id = up.course_id
     ORDER BY up.updated_at DESC'
);

$feedbackRows = fetch_all(
    'SELECT
        cr.id,
        cr.rating,
        cr.comment,
        cr.updated_at,
        u.name,
        u.email,
        c.title,
        c.subject
     FROM course_reviews cr
     JOIN users u ON u.id = cr.user_id
     JOIN courses c ON c.id = cr.course_id
     ORDER BY cr.updated_at DESC, cr.id DESC'
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
                        <th>Course Rating</th>
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
                            <td><?= number_format((float) $row['average_rating'], 1) ?> / 5<br><span class="muted"><?= (int) $row['review_count'] ?> reviews</span></td>
                            <td><?= !empty($row['saved']) ? 'Saved' : 'No' ?></td>
                            <td><?= htmlspecialchars((string) $row['updated_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <div class="section-head" style="margin-top: 2rem;">
            <div>
                <span class="eyebrow">Course Feedback</span>
                <h2>Read the ratings and comments learners have submitted.</h2>
            </div>
        </div>

        <?php if (!$feedbackRows): ?>
            <p class="muted">No course feedback has been submitted yet.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Course</th>
                        <th>Rating</th>
                        <th>Comment</th>
                        <th>Updated</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($feedbackRows as $feedback): ?>
                        <tr>
                            <td><?= htmlspecialchars($feedback['name']) ?><br><span class="muted"><?= htmlspecialchars($feedback['email']) ?></span></td>
                            <td><?= htmlspecialchars($feedback['title']) ?><br><span class="muted"><?= htmlspecialchars($feedback['subject']) ?></span></td>
                            <td><?= (int) $feedback['rating'] ?> / 5</td>
                            <td><?= nl2br(htmlspecialchars((string) $feedback['comment'])) ?></td>
                            <td><?= htmlspecialchars((string) $feedback['updated_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
