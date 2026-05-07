<?php
session_start();
require_once __DIR__ . '/includes/admin-book-manager.php';
require_once __DIR__ . '/includes/admin-shell.php';
require_once __DIR__ . '/includes/csrf.php';

$user = require_admin();
$bookId = (int) ($_GET['id'] ?? 0);
$isCreate = isset($_GET['create']) || $bookId <= 0;
$book = $isCreate ? null : fetch_one('SELECT * FROM books WHERE id = ?', [$bookId]);

if (!$isCreate && !$book) {
    http_response_code(404);
    exit('Book not found.');
}

$formValues = admin_book_form_defaults($book);
$message = ($_GET['notice'] ?? '') === 'saved' ? 'Book saved.' : '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $result = admin_save_book($_POST, $formValues, $book ? (int) $book['id'] : null);
    if (!$result['ok']) {
        $error = $result['error'];
    } else {
        $notice = $book ? 'updated' : 'created';
        header('Location: ' . admin_book_edit_url((int) $result['id']) . '?notice=saved');
        exit;
    }
}

admin_render_start([
    'title' => $isCreate ? 'Create Book' : 'Edit Book',
    'page_title' => $isCreate ? 'Create Book' : $book['title'],
    'page_subtitle' => $isCreate ? 'Add a new inventory item with its own dedicated form.' : 'Update pricing, stock, category, and cover image.',
    'active_nav' => 'books',
    'breadcrumbs' => [
        ['label' => 'Dashboard', 'href' => app_url('admin')],
        ['label' => 'Bookstore', 'href' => admin_books_url()],
        ['label' => $isCreate ? 'Create' : 'Edit'],
    ],
    'actions' => [
        ['label' => 'Inventory', 'href' => admin_books_url(), 'secondary' => true],
    ],
    'notice' => $message,
    'error' => $error,
    'user' => $user,
]);
?>

<section class="panel admin-form-panel">
    <form method="post" enctype="multipart/form-data" class="admin-course-form">
        <?= csrf_field() ?>
        <input type="hidden" name="existing_cover_url" value="<?= htmlspecialchars($formValues['cover_url']) ?>">

        <div class="admin-form-grid">
            <label>Title <input name="title" value="<?= htmlspecialchars($formValues['title']) ?>" required></label>
            <label>Author <input name="author" value="<?= htmlspecialchars($formValues['author']) ?>" required></label>
            <label>Category <input name="category" value="<?= htmlspecialchars($formValues['category']) ?>" required></label>
            <label>Price
                <div class="input-with-prefix">
                    <span class="input-prefix">RM</span>
                    <input name="price" type="number" step="0.01" min="0" value="<?= htmlspecialchars($formValues['price']) ?>" required>
                </div>
            </label>
            <label>Inventory <input name="inventory" type="number" min="0" value="<?= htmlspecialchars($formValues['inventory']) ?>" required></label>
            <label>Cover Image
                <div class="file-input-row">
                    <input name="cover_image" type="file" accept="image/jpeg,image/png,image/webp,image/gif" data-file-input>
                    <button class="button ghost small" type="button" data-file-clear hidden>Remove</button>
                </div>
            </label>
            <label class="admin-form-span-2">Description <textarea name="description" required><?= htmlspecialchars($formValues['description']) ?></textarea></label>
        </div>

        <div class="panel admin-asset-preview">
            <span class="eyebrow">Cover Preview</span>
            <?php if ($formValues['cover_url'] !== ''): ?>
                <img src="<?= htmlspecialchars(book_cover_src($formValues['cover_url'])) ?>" alt="Book cover preview">
            <?php else: ?>
                <p class="muted">Upload a book cover to complete the listing.</p>
            <?php endif; ?>
        </div>

        <div class="actions">
            <button type="submit"><?= $isCreate ? 'Create Book' : 'Save Book' ?></button>
            <a class="button ghost" href="<?= htmlspecialchars(admin_books_url()) ?>">Back to Inventory</a>
        </div>
    </form>
</section>

<?php admin_render_end(); ?>
