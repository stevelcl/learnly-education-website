<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/courses.php';

$pageTitle = 'Courses';
$subject = trim($_GET['subject'] ?? '');
$subjects = fetch_all('SELECT DISTINCT subject FROM courses ORDER BY subject');

$params = [];
$where = '';
if ($subject !== '') {
    $where = 'WHERE c.subject = ?';
    $params[] = $subject;
}

$courses = fetch_all(
    'SELECT
        c.*,
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
     ' . $where . '
     GROUP BY c.id
     ORDER BY COALESCE(AVG(rv.rating), 0) DESC, COUNT(DISTINCT ce.id) DESC, c.subject, c.title',
    $params
);

include __DIR__ . '/includes/header.php';
?>

<section class="section">
    <div class="container">
        <div class="section-head">
            <div>
                <span class="eyebrow">Learning Catalog</span>
                <h1>Browse guided course paths that feel credible, focused, and worth committing to.</h1>
            </div>
            <p>Each card surfaces the signal students look for first: quality, social proof, workload, and whether the course is already getting traction.</p>
        </div>

        <form class="filter-form course-filter" method="get">
            <label>Subject
                <select name="subject">
                    <option value="">All subjects</option>
                    <?php foreach ($subjects as $row): ?>
                        <option value="<?= htmlspecialchars($row['subject']) ?>" <?= $subject === $row['subject'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($row['subject']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit">Filter</button>
        </form>

        <div class="grid course-catalog-grid">
            <?php foreach ($courses as $course): ?>
                <?php
                $courseMinutes = max(20, ((int) $course['module_count'] * 14) + ((int) $course['quiz_count'] * 4));
                $roundedRating = number_format((float) $course['average_rating'], 1);
                $badges = course_badges($course);
                $primaryBadge = $badges[0] ?? '';
                ?>
                <article class="card course-catalog-card">
                    <a class="stretched-link" href="<?= htmlspecialchars(course_url((int) $course['id'])) ?>" aria-label="Open course overview: <?= htmlspecialchars($course['title']) ?>"></a>
                    <div class="card-topline">
                        <div class="course-card-top-tags">
                            <span class="tag"><?= htmlspecialchars($course['subject']) ?></span>
                            <?php if ($primaryBadge !== ''): ?>
                                <span class="tag course-badge"><?= htmlspecialchars($primaryBadge) ?></span>
                            <?php endif; ?>
                        </div>
                        <span class="course-card-duration"><?= $courseMinutes ?> mins</span>
                    </div>
                    <h2><?= htmlspecialchars($course['title']) ?></h2>
                    <p><?= htmlspecialchars($course['description']) ?></p>

                    <div class="course-card-trust">
                        <div class="course-trust-block">
                            <span class="stars" aria-hidden="true"><?= htmlspecialchars(course_star_text((float) $course['average_rating'])) ?></span>
                            <strong><?= $roundedRating ?></strong>
                            <span class="muted"><?= (int) $course['review_count'] ?> reviews</span>
                        </div>
                        <div class="course-trust-block">
                            <strong><?= (int) $course['enrollment_count'] ?></strong>
                            <span class="muted">enrolled</span>
                        </div>
                    </div>

                    <div class="course-card-footer">
                        <span class="inline-link">View course</span>
                        <span class="muted"><?= htmlspecialchars($course['level']) ?></span>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
