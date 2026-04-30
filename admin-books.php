<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/media.php';
$user = require_admin();

$noticeMap = [
    'created' => 'Book added.',
    'updated' => 'Book updated.',
    'deleted' => 'Book deleted.',
];
$message = $noticeMap[$_GET['notice'] ?? ''] ?? '';

$editingId = (int) ($_GET['edit'] ?? 0);
$editing = $editingId ? fetch_one('SELECT * FROM books WHERE id = ?', [$editingId]) : null;
$formValues = [
    'title' => $editing['title'] ?? '',
    'author' => $editing['author'] ?? '',
    'category' => $editing['category'] ?? '',
    'price' => (string) ($editing['price'] ?? '0.00'),
    'inventory' => (string) ($editing['inventory'] ?? 0),
    'cover_url' => $editing['cover_url'] ?? '',
    'description' => $editing['description'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $stmt = db()->prepare('DELETE FROM books WHERE id = ?');
        $stmt->execute([(int) $_POST['book_id']]);
        header('Location: admin-books.php?notice=deleted');
        exit;
    }

    $title = trim($_POST['title'] ?? '');
    $author = trim($_POST['author'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = (float) ($_POST['price'] ?? 0);
    $inventory = (int) ($_POST['inventory'] ?? 0);
    $coverUrl = trim($_POST['cover_url'] ?? '');

    $formValues = [
        'title' => $title,
        'author' => $author,
        'category' => $category,
        'price' => (string) $price,
        'inventory' => (string) $inventory,
        'cover_url' => $coverUrl,
        'description' => $description,
    ];

    if ($title !== '' && $author !== '' && $category !== '' && $description !== '') {
        if ($action === 'update') {
            $stmt = db()->prepare(
                'UPDATE books SET title = ?, author = ?, category = ?, description = ?, price = ?, inventory = ?, cover_url = ? WHERE id = ?'
            );
            $stmt->execute([$title, $author, $category, $description, $price, $inventory, $coverUrl, (int) $_POST['book_id']]);
            header('Location: admin-books.php?edit=' . (int) $_POST['book_id'] . '&notice=updated');
            exit;
        }

        $stmt = db()->prepare(
            'INSERT INTO books (title, author, category, description, price, inventory, cover_url) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$title, $author, $category, $description, $price, $inventory, $coverUrl]);
        $newId = (int) db()->lastInsertId();
        header('Location: admin-books.php?edit=' . $newId . '&notice=created');
        exit;
    }
}

$books = fetch_all('SELECT * FROM books ORDER BY title');
$lowStockBooks = fetch_all('SELECT title, inventory FROM books WHERE inventory <= 5 ORDER BY inventory ASC, title ASC');
$pageTitle = 'Manage Books';
include __DIR__ . '/includes/header.php';
?>

<section class="section">
    <div class="container layout">
        <div>
            <div class="section-head">
                <div>
                    <span class="eyebrow">Admin Bookstore</span>
                    <h1>Control catalogue content and stock levels.</h1>
                </div>
                <a class="button ghost" href="admin-dashboard.php">Back to Admin</a>
            </div>
            <?php if ($message): ?><p class="alert success"><?= htmlspecialchars($message) ?></p><?php endif; ?>
            <?php if ($lowStockBooks): ?>
                <div class="alert error" style="margin-bottom: 1rem;">
                    <strong>Low-stock alert:</strong>
                    <?php foreach ($lowStockBooks as $lowStockBook): ?>
                        <div><?= htmlspecialchars($lowStockBook['title']) ?> - <?= (int) $lowStockBook['inventory'] ?> left</div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="grid book-grid">
                <?php foreach ($books as $book): ?>
                    <article class="panel">
                        <img src="<?= htmlspecialchars(book_cover_src($book['cover_url'])) ?>" alt="<?= htmlspecialchars($book['title']) ?>" class="book-cover" referrerpolicy="no-referrer" onerror="this.onerror=null;this.src='assets/images/book-placeholder.svg';">
                        <span class="tag"><?= htmlspecialchars($book['category']) ?></span>
                        <h2><?= htmlspecialchars($book['title']) ?></h2>
                        <p class="muted"><?= htmlspecialchars($book['author']) ?></p>
                        <p>RM <?= number_format((float) $book['price'], 2) ?> | <?= (int) $book['inventory'] ?> in stock</p>
                        <div class="actions">
                            <a class="button small ghost" href="admin-books.php?edit=<?= (int) $book['id'] ?>">Edit</a>
                            <form method="post" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="book_id" value="<?= (int) $book['id'] ?>">
                                <button class="button small danger" type="submit">Delete</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>

        <aside class="panel">
            <h2><?= $editing ? 'Edit Book' : 'Add Book' ?></h2>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
                <input type="hidden" name="book_id" value="<?= (int) ($editing['id'] ?? 0) ?>">
                <label>Title <input name="title" value="<?= htmlspecialchars($formValues['title']) ?>" required></label>
                <label>Author <input name="author" value="<?= htmlspecialchars($formValues['author']) ?>" required></label>
                <label>Category <input name="category" value="<?= htmlspecialchars($formValues['category']) ?>" required></label>
                <label>Price <input name="price" type="number" step="0.01" min="0" value="<?= htmlspecialchars($formValues['price']) ?>" required></label>
                <label>Inventory <input name="inventory" type="number" min="0" value="<?= htmlspecialchars($formValues['inventory']) ?>" required></label>
                <label>Cover URL <input name="cover_url" value="<?= htmlspecialchars($formValues['cover_url']) ?>"></label>
                <label>Description <textarea name="description" required><?= htmlspecialchars($formValues['description']) ?></textarea></label>
                <?php if ($formValues['cover_url'] !== ''): ?>
                    <img src="<?= htmlspecialchars(book_cover_src($formValues['cover_url'])) ?>" alt="Book cover preview" class="book-cover" referrerpolicy="no-referrer" onerror="this.onerror=null;this.src='assets/images/book-placeholder.svg';">
                <?php endif; ?>
                <div class="actions">
                    <button type="submit"><?= $editing ? 'Update Book' : 'Add Book' ?></button>
                    <a class="button ghost" href="admin-books.php">Clear All</a>
                </div>
            </form>
        </aside>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
