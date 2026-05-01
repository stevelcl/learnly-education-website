<?php
require_once __DIR__ . '/includes/db.php';

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
     ORDER BY c.subject, c.title',
    $params
);

include __DIR__ . '/includes/header.php';
?>

<section class="section">
    <div class="container">
        <div class="section-head">
            <div>
                <span class="eyebrow">Learning Catalog</span>
                <h1>Browse course spaces that feel guided, credible, and worth enrolling in.</h1>
            </div>
            <p>Each card surfaces the practical signal first: topic, rating, learning workload, and how many people have already joined.</p>
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
                $starCount = max(1, (int) round((float) $course['average_rating']));
                ?>
                <article class="card course-catalog-card">
                    <a class="stretched-link" href="course.php?id=<?= (int) $course['id'] ?>" aria-label="Open course overview: <?= htmlspecialchars($course['title']) ?>"></a>
                    <div class="card-topline">
                        <span class="tag"><?= htmlspecialchars($course['subject']) ?></span>
                        <span class="muted"><?= htmlspecialchars($course['level']) ?></span>
                    </div>
                    <h2><?= htmlspecialchars($course['title']) ?></h2>
                    <p><?= htmlspecialchars($course['description']) ?></p>
                    <div class="course-card-meta">
                        <span><?= (int) $course['module_count'] ?> modules</span>
                        <span><?= (int) $course['quiz_count'] ?> quizzes</span>
                        <span><?= $courseMinutes ?> mins</span>
                    </div>
                    <div class="course-card-footer">
                        <div class="rating-inline">
                            <span class="stars" aria-hidden="true"><?= str_repeat('★', $starCount) ?></span>
                            <span><?= $roundedRating ?></span>
                            <span class="muted">(<?= (int) $course['review_count'] ?> reviews)</span>
                        </div>
                        <span class="muted"><?= (int) $course['enrollment_count'] ?> enrolled</span>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
