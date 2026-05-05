<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/media.php';
require_once __DIR__ . '/includes/books.php';

$search = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');
$categories = fetch_all('SELECT DISTINCT category FROM books ORDER BY category');

$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(b.title LIKE ? OR b.author LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
if ($category !== '') {
    $where[] = 'b.category = ?';
    $params[] = $category;
}

$sql = '
    SELECT
        b.*,
        COALESCE(r.avg_rating, 0) AS avg_rating,
        COALESCE(r.review_count, 0) AS review_count
    FROM books b
    LEFT JOIN (
        SELECT book_id, AVG(rating) AS avg_rating, COUNT(*) AS review_count
        FROM book_reviews
        GROUP BY book_id
    ) r ON r.book_id = b.id
';

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' ORDER BY COALESCE(r.avg_rating, 0) DESC, b.title';
$books = fetch_all($sql, $params);

$pageTitle = 'Bookstore';
include __DIR__ . '/includes/header.php';
?>

<section class="section">
    <div class="container">
        <div class="section-head">
            <div>
                <span class="eyebrow">Academic Bookstore</span>
                <h1>Explore books with the context you need before you buy.</h1>
            </div>
            <p>Browse by subject, compare ratings, read student feedback, and open each title for full details before adding it to your cart.</p>
        </div>

        <form class="filter-form" method="get">
            <label>Search <input name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Title or author"></label>
            <label>Category
                <select name="category">
                    <option value="">All categories</option>
                    <?php foreach ($categories as $row): ?>
                        <option value="<?= htmlspecialchars($row['category']) ?>" <?= $category === $row['category'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($row['category']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit">Search</button>
        </form>

        <div class="grid book-grid book-catalog-grid">
            <?php foreach ($books as $index => $book): ?>
                <article class="card book-card" data-reveal="slide-up" data-reveal-delay="<?= $index ?>">
                    <a class="stretched-link" href="<?= htmlspecialchars(book_url((int) $book['id'])) ?>" aria-label="View <?= htmlspecialchars($book['title']) ?>"></a>
                    <div class="book-card-image-wrap">
                        <img src="<?= htmlspecialchars(book_cover_src($book['cover_url'])) ?>" alt="<?= htmlspecialchars($book['title']) ?>" class="book-cover" referrerpolicy="no-referrer" onerror="this.onerror=null;this.src='assets/images/book-placeholder.svg';">
                    </div>
                    <div class="book-card-content">
                        <div class="card-topline">
                            <span class="tag"><?= htmlspecialchars($book['category']) ?></span>
                            <span class="book-price">RM <?= number_format((float) $book['price'], 2) ?></span>
                        </div>
                        <h2><?= htmlspecialchars($book['title']) ?></h2>
                        <p class="muted book-author"><?= htmlspecialchars($book['author']) ?></p>
                        <div class="rating-inline book-rating-inline">
                            <span class="stars" aria-hidden="true"><?= htmlspecialchars(render_stars((float) $book['avg_rating'])) ?></span>
                            <span><?= htmlspecialchars(rating_label((float) $book['avg_rating'], (int) $book['review_count'])) ?></span>
                        </div>
                        <p class="book-card-description"><?= htmlspecialchars($book['description']) ?></p>
                        <div class="book-card-footer">
                            <span class="stock-chip <?= (int) $book['inventory'] > 0 ? 'in-stock' : 'out-of-stock' ?>">
                                <?= (int) $book['inventory'] > 0 ? (int) $book['inventory'] . ' in stock' : 'Out of stock' ?>
                            </span>
                            <span class="inline-link">View details</span>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
