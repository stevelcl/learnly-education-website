<?php
session_start();
require_once __DIR__ . '/includes/db.php';

$query = trim($_GET['q'] ?? '');
$courses = [];
$books = [];
$posts = [];

if ($query !== '') {
    $like = '%' . $query . '%';
    $courses = fetch_all(
        'SELECT id, title, subject, level, description
         FROM courses
         WHERE title LIKE ? OR subject LIKE ? OR description LIKE ?
         ORDER BY created_at DESC
         LIMIT 8',
        [$like, $like, $like]
    );

    $books = fetch_all(
        'SELECT id, title, author, category, price
         FROM books
         WHERE title LIKE ? OR author LIKE ? OR category LIKE ?
         ORDER BY inventory DESC, title
         LIMIT 8',
        [$like, $like, $like]
    );

    $posts = fetch_all(
        'SELECT id, title, body, created_at
         FROM forum_posts
         WHERE status = "visible" AND (title LIKE ? OR body LIKE ?)
         ORDER BY created_at DESC
         LIMIT 8',
        [$like, $like]
    );
}

$pageTitle = 'Search';
include __DIR__ . '/includes/header.php';
?>

<section class="section">
    <div class="container">
        <div class="section-head">
            <div>
                <span class="eyebrow">Search Learnly</span>
                <h1>Find courses, books, and discussion threads from one place.</h1>
            </div>
            <p>Search works across course titles and subjects, bookstore titles and authors, and forum questions.</p>
        </div>

        <div class="panel" style="margin-bottom: 1rem;">
            <form method="get" class="search-page-form">
                <label>
                    Search
                    <input name="q" value="<?= htmlspecialchars($query) ?>" placeholder="Programming, statistics, Maya Collins, SQL joins">
                </label>
                <button type="submit">Search</button>
            </form>
        </div>

        <?php if ($query === ''): ?>
            <p class="muted">Try a course topic, book title, author, subject area, or forum keyword.</p>
        <?php else: ?>
            <p class="muted">Results for "<?= htmlspecialchars($query) ?>"</p>

            <div class="grid">
                <article class="panel">
                    <h2>Courses</h2>
                    <?php if (!$courses): ?>
                        <p class="muted">No course matches found.</p>
                    <?php endif; ?>
                    <?php foreach ($courses as $course): ?>
                        <div style="margin-bottom: 1rem;">
                            <h3><a href="course.php?id=<?= (int) $course['id'] ?>"><?= htmlspecialchars($course['title']) ?></a></h3>
                            <p class="muted"><?= htmlspecialchars($course['subject']) ?> | <?= htmlspecialchars($course['level']) ?></p>
                            <p><?= htmlspecialchars($course['description']) ?></p>
                        </div>
                    <?php endforeach; ?>
                </article>

                <article class="panel">
                    <h2>Books</h2>
                    <?php if (!$books): ?>
                        <p class="muted">No book matches found.</p>
                    <?php endif; ?>
                    <?php foreach ($books as $book): ?>
                        <div style="margin-bottom: 1rem;">
                            <h3><a href="bookstore.php?search=<?= urlencode($book['title']) ?>"><?= htmlspecialchars($book['title']) ?></a></h3>
                            <p class="muted"><?= htmlspecialchars($book['author']) ?> | <?= htmlspecialchars($book['category']) ?></p>
                            <p>RM <?= number_format((float) $book['price'], 2) ?></p>
                        </div>
                    <?php endforeach; ?>
                </article>

                <article class="panel">
                    <h2>Forum</h2>
                    <?php if (!$posts): ?>
                        <p class="muted">No forum matches found.</p>
                    <?php endif; ?>
                    <?php foreach ($posts as $post): ?>
                        <div style="margin-bottom: 1rem;">
                            <h3><a href="post.php?id=<?= (int) $post['id'] ?>"><?= htmlspecialchars($post['title']) ?></a></h3>
                            <?php $preview = strlen($post['body']) > 160 ? substr($post['body'], 0, 160) . '...' : $post['body']; ?>
                            <p><?= nl2br(htmlspecialchars($preview)) ?></p>
                        </div>
                    <?php endforeach; ?>
                </article>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
