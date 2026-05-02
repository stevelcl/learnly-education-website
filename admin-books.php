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
$error = '';

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
    $coverUrl = trim($_POST['existing_cover_url'] ?? '');

    if (isset($_FILES['cover_image']) && is_array($_FILES['cover_image']) && (int) ($_FILES['cover_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $upload = $_FILES['cover_image'];
        $uploadError = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($uploadError !== UPLOAD_ERR_OK) {
            $error = 'Book cover upload failed. Please try again.';
        } else {
            $mimeType = mime_content_type($upload['tmp_name']) ?: '';
            $allowedMimeTypes = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/gif' => 'gif',
            ];

            if (!isset($allowedMimeTypes[$mimeType])) {
                $error = 'Please upload a JPG, PNG, WEBP, or GIF cover image.';
            } else {
                $uploadDirectory = __DIR__ . '/assets/uploads/books';
                if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0775, true) && !is_dir($uploadDirectory)) {
                    $error = 'Upload folder could not be prepared.';
                } else {
                    $filename = 'book-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $allowedMimeTypes[$mimeType];
                    $destination = $uploadDirectory . '/' . $filename;

                    if (!move_uploaded_file($upload['tmp_name'], $destination)) {
                        $error = 'Book cover could not be saved on the server.';
                    } else {
                        $coverUrl = 'assets/uploads/books/' . $filename;
                    }
                }
            }
        }
    }

    $formValues = [
        'title' => $title,
        'author' => $author,
        'category' => $category,
        'price' => (string) $price,
        'inventory' => (string) $inventory,
        'cover_url' => $coverUrl,
        'description' => $description,
    ];

    if ($error === '' && $title !== '' && $author !== '' && $category !== '' && $description !== '') {
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

    if ($error === '' && ($title === '' || $author === '' || $category === '' || $description === '')) {
        $error = 'Please complete all required book fields.';
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
            <?php if ($error): ?><p class="alert error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
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
            <form method="post" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
                <input type="hidden" name="book_id" value="<?= (int) ($editing['id'] ?? 0) ?>">
                <input type="hidden" name="existing_cover_url" value="<?= htmlspecialchars($formValues['cover_url']) ?>">
                <label>Title <input name="title" value="<?= htmlspecialchars($formValues['title']) ?>" required></label>
                <label>Author <input name="author" value="<?= htmlspecialchars($formValues['author']) ?>" required></label>
                <label>Category <input name="category" value="<?= htmlspecialchars($formValues['category']) ?>" required></label>
                <label>Price <input name="price" type="number" step="0.01" min="0" value="<?= htmlspecialchars($formValues['price']) ?>" required></label>
                <label>Inventory <input name="inventory" type="number" min="0" value="<?= htmlspecialchars($formValues['inventory']) ?>" required></label>
                <label>Cover Image <input name="cover_image" type="file" accept="image/jpeg,image/png,image/webp,image/gif" data-cover-file-input></label>
                <label>Description <textarea name="description" required><?= htmlspecialchars($formValues['description']) ?></textarea></label>
                <img
                    src="<?= htmlspecialchars(book_cover_src($formValues['cover_url'])) ?>"
                    alt="Book cover preview"
                    class="book-cover"
                    data-cover-preview
                    data-has-existing="<?= $formValues['cover_url'] === '' ? '0' : '1' ?>"
                    data-placeholder-src="assets/images/book-placeholder.svg"
                    <?= $formValues['cover_url'] === '' ? 'hidden' : '' ?>
                    referrerpolicy="no-referrer"
                    onerror="this.onerror=null;this.src=this.dataset.placeholderSrc;">
                <div class="actions">
                    <button type="submit"><?= $editing ? 'Update Book' : 'Add Book' ?></button>
                    <a class="button ghost" href="admin-books.php">Clear All</a>
                </div>
            </form>
        </aside>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
