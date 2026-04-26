<?php
require_once __DIR__ . '/includes/db.php';
$pageTitle = 'Courses';
$subject = trim($_GET['subject'] ?? '');
$subjects = fetch_all('SELECT DISTINCT subject FROM courses ORDER BY subject');

if ($subject !== '') {
    $courses = fetch_all('SELECT * FROM courses WHERE subject = ? ORDER BY title', [$subject]);
} else {
    $courses = fetch_all('SELECT * FROM courses ORDER BY subject, title');
}

include __DIR__ . '/includes/header.php';
?>

<section class="section">
    <div class="container">
        <h1>Course Content</h1>
        <form class="filter-form" method="get">
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
        <div class="grid">
            <?php foreach ($courses as $course): ?>
                <article class="card">
                    <span class="tag"><?= htmlspecialchars($course['subject']) ?></span>
                    <h2><?= htmlspecialchars($course['title']) ?></h2>
                    <p><?= htmlspecialchars($course['description']) ?></p>
                    <p class="muted"><?= htmlspecialchars($course['level']) ?></p>
                    <a class="button small" href="course.php?id=<?= (int) $course['id'] ?>">Open course</a>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

