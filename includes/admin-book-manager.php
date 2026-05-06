<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config-helper.php';
require_once __DIR__ . '/media.php';
require_once __DIR__ . '/admin-course-manager.php';

function admin_books_url(array $params = []): string
{
    return app_url_with_query(app_url('admin/books'), $params);
}

function admin_book_create_url(): string
{
    return app_url('admin/books/create');
}

function admin_book_edit_url(int $bookId): string
{
    return app_url('admin/books/edit/' . $bookId);
}

function admin_book_form_defaults(?array $book = null): array
{
    return [
        'title' => $book['title'] ?? '',
        'author' => $book['author'] ?? '',
        'category' => $book['category'] ?? '',
        'price' => (string) ($book['price'] ?? '0.00'),
        'inventory' => (string) ($book['inventory'] ?? '0'),
        'description' => $book['description'] ?? '',
        'cover_url' => $book['cover_url'] ?? '',
    ];
}

function admin_fetch_books(string $search = '', string $category = ''): array
{
    $where = [];
    $params = [];

    if ($search !== '') {
        $where[] = '(title LIKE ? OR author LIKE ?)';
        $like = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
    }

    if ($category !== '') {
        $where[] = 'category = ?';
        $params[] = $category;
    }

    $sql = 'SELECT * FROM books';
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY inventory ASC, title ASC';

    return fetch_all($sql, $params);
}

function admin_book_categories(): array
{
    return fetch_all('SELECT DISTINCT category FROM books ORDER BY category ASC');
}

function admin_save_book(array $post, array &$formValues, ?int $bookId = null): array
{
    $title = trim($post['title'] ?? '');
    $author = trim($post['author'] ?? '');
    $category = trim($post['category'] ?? '');
    $description = trim($post['description'] ?? '');
    $price = max(0, (float) ($post['price'] ?? 0));
    $inventory = max(0, (int) ($post['inventory'] ?? 0));
    $coverUrl = trim($post['existing_cover_url'] ?? '');

    $upload = admin_store_uploaded_asset('cover_image', 'books', admin_image_upload_types());
    if ($upload['error'] !== '') {
        return ['ok' => false, 'error' => 'Book cover: ' . $upload['error']];
    }
    if ($upload['path']) {
        $coverUrl = $upload['path'];
    }

    $formValues = [
        'title' => $title,
        'author' => $author,
        'category' => $category,
        'price' => (string) $price,
        'inventory' => (string) $inventory,
        'description' => $description,
        'cover_url' => $coverUrl,
    ];

    if ($title === '' || $author === '' || $category === '' || $description === '') {
        return ['ok' => false, 'error' => 'Please complete all required book fields.'];
    }

    if ($bookId) {
        $stmt = db()->prepare(
            'UPDATE books SET title = ?, author = ?, category = ?, description = ?, price = ?, inventory = ?, cover_url = ? WHERE id = ?'
        );
        $stmt->execute([$title, $author, $category, $description, $price, $inventory, $coverUrl, $bookId]);
        return ['ok' => true, 'id' => $bookId];
    }

    $stmt = db()->prepare(
        'INSERT INTO books (title, author, category, description, price, inventory, cover_url) VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$title, $author, $category, $description, $price, $inventory, $coverUrl]);
    return ['ok' => true, 'id' => (int) db()->lastInsertId()];
}

function admin_delete_book(int $bookId): array
{
    $book = fetch_one('SELECT cover_url FROM books WHERE id = ?', [$bookId]);
    if (!$book) {
        return ['ok' => false, 'error' => 'Book not found.'];
    }

    $coverPath = trim((string) ($book['cover_url'] ?? ''));
    $stmt = db()->prepare('DELETE FROM books WHERE id = ?');
    $stmt->execute([$bookId]);

    if ($coverPath !== '' && !preg_match('/^https?:\/\//i', $coverPath)) {
        $absolutePath = dirname(__DIR__) . '/' . ltrim($coverPath, '/');
        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }

    return ['ok' => true];
}
