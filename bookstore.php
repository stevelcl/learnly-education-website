<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/cart.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/media.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    add_to_cart((int) $_POST['book_id'], (int) ($_POST['quantity'] ?? 1));
    $message = 'Book added to cart.';
}

$search = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');
$categories = fetch_all('SELECT DISTINCT category FROM books ORDER BY category');

$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(title LIKE ? OR author LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
if ($category !== '') {
    $where[] = 'category = ?';
    $params[] = $category;
}

$sql = 'SELECT * FROM books';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY title';
$books = fetch_all($sql, $params);

$pageTitle = 'Bookstore';
include __DIR__ . '/includes/header.php';
?>

<section class="section">
    <div class="container">
        <h1>Academic Bookstore</h1>
        <?php if ($message): ?><p class="alert success"><?= htmlspecialchars($message) ?></p><?php endif; ?>
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

        <div class="grid book-grid">
            <?php foreach ($books as $book): ?>
                <article class="card">
                    <img src="<?= htmlspecialchars(book_cover_src($book['cover_url'])) ?>" alt="<?= htmlspecialchars($book['title']) ?>" class="book-cover" referrerpolicy="no-referrer" onerror="this.onerror=null;this.src='assets/images/book-placeholder.svg';">
                    <span class="tag"><?= htmlspecialchars($book['category']) ?></span>
                    <h2><?= htmlspecialchars($book['title']) ?></h2>
                    <p class="muted"><?= htmlspecialchars($book['author']) ?></p>
                    <p><?= htmlspecialchars($book['description']) ?></p>
                    <p><strong>RM <?= number_format((float) $book['price'], 2) ?></strong> | <?= (int) $book['inventory'] ?> in stock</p>
                    <form method="post" class="inline-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="book_id" value="<?= (int) $book['id'] ?>">
                        <input type="number" name="quantity" value="1" min="1" max="<?= max(1, (int) $book['inventory']) ?>" aria-label="Quantity">
                        <button type="submit" <?= (int) $book['inventory'] <= 0 ? 'disabled' : '' ?>>Add</button>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
