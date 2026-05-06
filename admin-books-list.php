<?php
session_start();
require_once __DIR__ . '/includes/admin-book-manager.php';
require_once __DIR__ . '/includes/admin-shell.php';
require_once __DIR__ . '/includes/csrf.php';

$user = require_admin();
$search = trim($_GET['q'] ?? '');
$category = trim($_GET['category'] ?? '');
$messageMap = [
    'created' => 'Book added to inventory.',
    'updated' => 'Book updated.',
    'deleted' => 'Book removed from inventory.',
];
$message = $messageMap[$_GET['notice'] ?? ''] ?? '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (($_POST['action'] ?? '') === 'delete_book') {
        $result = admin_delete_book((int) ($_POST['book_id'] ?? 0));
        if (!$result['ok']) {
            $error = $result['error'];
        } else {
            header('Location: ' . admin_books_url(['notice' => 'deleted', 'q' => $search, 'category' => $category]));
            exit;
        }
    }
}

$books = admin_fetch_books($search, $category);
$categories = admin_book_categories();
$lowStockCount = (int) (fetch_one('SELECT COUNT(*) AS total FROM books WHERE inventory <= 5')['total'] ?? 0);

admin_render_start([
    'title' => 'Bookstore Admin',
    'page_title' => 'Bookstore',
    'page_subtitle' => 'Manage inventory, pricing, and stock alerts without mixing forms into the listing.',
    'active_nav' => 'books',
    'breadcrumbs' => [
        ['label' => 'Dashboard', 'href' => app_url('admin')],
        ['label' => 'Bookstore'],
    ],
    'actions' => [
        ['label' => 'Create Book', 'href' => admin_book_create_url()],
    ],
    'notice' => $message,
    'error' => $error,
    'user' => $user,
]);
?>

<section class="admin-stats-row">
    <article class="panel admin-stat-card"><strong><?= count($books) ?></strong><span class="muted">Visible books</span></article>
    <article class="panel admin-stat-card"><strong><?= $lowStockCount ?></strong><span class="muted">Low stock alerts</span></article>
    <article class="panel admin-stat-card"><strong><?= count($categories) ?></strong><span class="muted">Categories</span></article>
    <article class="panel admin-stat-card"><strong><?= array_sum(array_map(static fn(array $book): int => (int) $book['inventory'], $books)) ?></strong><span class="muted">Units on hand</span></article>
    <article class="panel admin-stat-card"><strong>RM <?= number_format(array_sum(array_map(static fn(array $book): float => ((float) $book['price']) * ((int) $book['inventory']), $books)), 2) ?></strong><span class="muted">Estimated stock value</span></article>
</section>

<section class="panel">
    <form class="admin-filter-bar" method="get">
        <label>Search
            <input name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Title or author">
        </label>
        <label>Category
            <select name="category">
                <option value="">All categories</option>
                <?php foreach ($categories as $row): ?>
                    <option value="<?= htmlspecialchars($row['category']) ?>" <?= $category === $row['category'] ? 'selected' : '' ?>><?= htmlspecialchars($row['category']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="admin-table-actions">
            <button type="submit">Filter</button>
            <a class="button ghost" href="<?= htmlspecialchars(admin_books_url()) ?>">Reset</a>
        </div>
    </form>
</section>

<section class="panel admin-data-table">
    <?php if (!$books): ?>
        <div class="admin-empty-state">
            <strong>No books found</strong>
            <span>Try a different filter or create a new inventory item.</span>
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Book</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($books as $book): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($book['title']) ?></strong><br>
                            <span class="muted"><?= htmlspecialchars($book['author']) ?></span>
                        </td>
                        <td><?= htmlspecialchars($book['category']) ?></td>
                        <td>RM <?= number_format((float) $book['price'], 2) ?></td>
                        <td>
                            <span class="tag<?= (int) $book['inventory'] <= 5 ? ' warn' : '' ?>">
                                <?= (int) $book['inventory'] <= 5 ? 'Low: ' : '' ?><?= (int) $book['inventory'] ?>
                            </span>
                        </td>
                        <td>
                            <div class="admin-table-actions">
                                <a class="button small ghost" href="<?= htmlspecialchars(admin_book_edit_url((int) $book['id'])) ?>">Edit</a>
                                <form method="post" class="inline-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_book">
                                    <input type="hidden" name="book_id" value="<?= (int) $book['id'] ?>">
                                    <button class="button small danger" type="submit" data-confirm="Delete this book and remove its uploaded cover file?">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<?php admin_render_end(); ?>
