<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/cart.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/media.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/books.php';

$bookId = (int) ($_GET['id'] ?? 0);
if ($bookId <= 0) {
    http_response_code(404);
    exit('Book not found.');
}

$user = current_user();
$message = $_GET['message'] ?? '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_to_cart') {
        add_to_cart($bookId, (int) ($_POST['quantity'] ?? 1));
        header('Location: ' . book_url($bookId) . '?message=added');
        exit;
    }

    if ($action === 'save_review') {
        if (!$user) {
            header('Location: login.php');
            exit;
        }

        $rating = max(1, min(5, (int) ($_POST['rating'] ?? 0)));
        $comment = trim($_POST['comment'] ?? '');

        if ($comment === '') {
            $error = 'Please write a short review before submitting.';
        } else {
            $stmt = db()->prepare(
                'INSERT INTO book_reviews (user_id, book_id, rating, comment)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment), updated_at = CURRENT_TIMESTAMP'
            );
            $stmt->execute([(int) $user['id'], $bookId, $rating, $comment]);
            header('Location: ' . book_url($bookId) . '?message=reviewed#reviews');
            exit;
        }
    }
}

$book = fetch_one(
    'SELECT
        b.*,
        COALESCE(r.avg_rating, 0) AS avg_rating,
        COALESCE(r.review_count, 0) AS review_count
     FROM books b
     LEFT JOIN (
        SELECT book_id, AVG(rating) AS avg_rating, COUNT(*) AS review_count
        FROM book_reviews
        GROUP BY book_id
     ) r ON r.book_id = b.id
     WHERE b.id = ?',
    [$bookId]
);

if (!$book) {
    http_response_code(404);
    exit('Book not found.');
}

$reviews = fetch_all(
    'SELECT br.rating, br.comment, br.created_at, br.updated_at, u.name
     FROM book_reviews br
     INNER JOIN users u ON u.id = br.user_id
     WHERE br.book_id = ?
     ORDER BY br.updated_at DESC, br.created_at DESC',
    [$bookId]
);

$userReview = null;
if ($user) {
    $userReview = fetch_one(
        'SELECT rating, comment FROM book_reviews WHERE user_id = ? AND book_id = ?',
        [(int) $user['id'], $bookId]
    );
}

$relatedBooks = fetch_all(
    'SELECT
        b.*,
        COALESCE(r.avg_rating, 0) AS avg_rating,
        COALESCE(r.review_count, 0) AS review_count
     FROM books b
     LEFT JOIN (
        SELECT book_id, AVG(rating) AS avg_rating, COUNT(*) AS review_count
        FROM book_reviews
        GROUP BY book_id
     ) r ON r.book_id = b.id
     WHERE b.category = ? AND b.id <> ?
     ORDER BY COALESCE(r.avg_rating, 0) DESC, b.title
     LIMIT 3',
    [$book['category'], $bookId]
);

$pageTitle = $book['title'];
$showBackButton = true;
$backTarget = 'bookstore.php';
include __DIR__ . '/includes/header.php';
?>

<section class="section">
    <div class="container book-detail-shell">
        <?php if ($message === 'added'): ?><p class="alert success">Book added to cart.</p><?php endif; ?>
        <?php if ($message === 'reviewed'): ?><p class="alert success">Your review has been saved.</p><?php endif; ?>
        <?php if ($error !== ''): ?><p class="alert"><?= htmlspecialchars($error) ?></p><?php endif; ?>

        <div class="book-detail-hero">
            <div class="panel book-detail-media" data-reveal="slide-up">
                <div class="book-detail-cover-frame">
                    <img src="<?= htmlspecialchars(book_cover_src($book['cover_url'])) ?>" alt="<?= htmlspecialchars($book['title']) ?>" class="book-detail-cover" referrerpolicy="no-referrer" onerror="this.onerror=null;this.src='assets/images/book-placeholder.svg';">
                </div>
            </div>

            <div class="panel book-detail-summary" data-reveal="slide-left">
                <div class="book-detail-meta-row">
                    <span class="tag"><?= htmlspecialchars($book['category']) ?></span>
                    <span class="stock-chip <?= (int) $book['inventory'] > 0 ? 'in-stock' : 'out-of-stock' ?>">
                        <?= (int) $book['inventory'] > 0 ? 'In stock' : 'Out of stock' ?>
                    </span>
                </div>
                <h1><?= htmlspecialchars($book['title']) ?></h1>
                <p class="book-detail-author">by <?= htmlspecialchars($book['author']) ?></p>
                <div class="book-detail-rating-row">
                    <span class="stars" aria-hidden="true"><?= htmlspecialchars(render_stars((float) $book['avg_rating'])) ?></span>
                    <strong><?= number_format((float) $book['avg_rating'], 1) ?></strong>
                    <span class="muted"><?= htmlspecialchars(rating_label((float) $book['avg_rating'], (int) $book['review_count'])) ?></span>
                </div>
                <div class="book-detail-price">RM <?= number_format((float) $book['price'], 2) ?></div>
                <p class="book-detail-lead"><?= htmlspecialchars($book['description']) ?></p>

                <form method="post" class="book-purchase-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_to_cart">
                    <label>
                        Quantity
                        <input type="number" name="quantity" value="1" min="1" max="<?= max(1, (int) $book['inventory']) ?>">
                    </label>
                    <button type="submit" <?= (int) $book['inventory'] <= 0 ? 'disabled' : '' ?>>Add to Cart</button>
                </form>

                <div class="book-detail-signal-grid">
                    <div>
                        <strong><?= htmlspecialchars($book['category']) ?></strong>
                        <span>Category</span>
                    </div>
                    <div>
                        <strong><?= (int) $book['inventory'] ?></strong>
                        <span>Available copies</span>
                    </div>
                    <div>
                        <strong><?= (int) $book['review_count'] ?></strong>
                        <span>Student reviews</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="book-detail-content-layout">
            <div class="book-detail-main">
                <article class="panel" data-reveal="slide-up">
                    <div class="section-head compact">
                        <div>
                            <span class="eyebrow">About this book</span>
                            <h2>Product details</h2>
                        </div>
                    </div>
                    <p><?= nl2br(htmlspecialchars($book['description'])) ?></p>
                    <div class="book-detail-info-grid">
                        <div class="outline-row">
                            <strong>Author</strong>
                            <span class="muted"><?= htmlspecialchars($book['author']) ?></span>
                        </div>
                        <div class="outline-row">
                            <strong>Category</strong>
                            <span class="muted"><?= htmlspecialchars($book['category']) ?></span>
                        </div>
                        <div class="outline-row">
                            <strong>Availability</strong>
                            <span class="muted"><?= (int) $book['inventory'] > 0 ? (int) $book['inventory'] . ' copies ready to order' : 'Currently unavailable' ?></span>
                        </div>
                        <div class="outline-row">
                            <strong>Price</strong>
                            <span class="muted">RM <?= number_format((float) $book['price'], 2) ?></span>
                        </div>
                    </div>
                </article>

                <article class="panel" id="reviews" data-reveal="slide-up">
                    <div class="section-head compact">
                        <div>
                            <span class="eyebrow">Reviews</span>
                            <h2>What students think</h2>
                        </div>
                        <p><?= htmlspecialchars(rating_label((float) $book['avg_rating'], (int) $book['review_count'])) ?></p>
                    </div>

                    <div class="review-summary-bar">
                        <div class="review-summary-score">
                            <strong><?= number_format((float) $book['avg_rating'], 1) ?></strong>
                            <span class="stars" aria-hidden="true"><?= htmlspecialchars(render_stars((float) $book['avg_rating'])) ?></span>
                            <span class="muted"><?= (int) $book['review_count'] ?> total</span>
                        </div>
                    </div>

                    <?php if ($reviews): ?>
                        <div class="review-list">
                            <?php foreach ($reviews as $review): ?>
                                <article class="review-card">
                                    <div class="review-card-head">
                                        <div>
                                            <strong><?= htmlspecialchars($review['name']) ?></strong>
                                            <div class="rating-inline">
                                                <span class="stars" aria-hidden="true"><?= htmlspecialchars(render_stars((float) $review['rating'])) ?></span>
                                                <span><?= (int) $review['rating'] ?>/5</span>
                                            </div>
                                        </div>
                                        <span class="muted"><?= htmlspecialchars(date('d M Y', strtotime((string) $review['updated_at']))) ?></span>
                                    </div>
                                    <p><?= nl2br(htmlspecialchars($review['comment'])) ?></p>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="muted">No reviews yet. Be the first to share what this book helped you understand.</p>
                    <?php endif; ?>
                </article>
            </div>

            <aside class="book-detail-sidebar">
                <article class="panel" data-reveal="slide-left">
                    <div class="section-head compact">
                        <div>
                            <span class="eyebrow">Your review</span>
                            <h2>Rate this book</h2>
                        </div>
                    </div>
                    <?php if ($user): ?>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="save_review">
                            <label>
                                Rating
                                <select name="rating">
                                    <?php for ($rating = 5; $rating >= 1; $rating--): ?>
                                        <option value="<?= $rating ?>" <?= (int) ($userReview['rating'] ?? 5) === $rating ? 'selected' : '' ?>>
                                            <?= $rating ?> star<?= $rating === 1 ? '' : 's' ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </label>
                            <label>
                                Comment
                                <textarea name="comment" placeholder="Share what this book was useful for, how clear it was, or whether you would recommend it."><?= htmlspecialchars($userReview['comment'] ?? '') ?></textarea>
                            </label>
                            <button type="submit"><?= $userReview ? 'Update Review' : 'Post Review' ?></button>
                        </form>
                    <?php else: ?>
                        <p class="muted">Sign in to rate this book and leave a review for other students.</p>
                        <a class="button" href="login.php">Login to Review</a>
                    <?php endif; ?>
                </article>

                <?php if ($relatedBooks): ?>
                    <article class="panel" data-reveal="slide-left">
                        <div class="section-head compact">
                            <div>
                                <span class="eyebrow">Related titles</span>
                                <h2>More in <?= htmlspecialchars($book['category']) ?></h2>
                            </div>
                        </div>
                        <div class="related-books-list">
                            <?php foreach ($relatedBooks as $related): ?>
                                <a class="related-book-row" href="<?= htmlspecialchars(book_url((int) $related['id'])) ?>">
                                    <img src="<?= htmlspecialchars(book_cover_src($related['cover_url'])) ?>" alt="<?= htmlspecialchars($related['title']) ?>" class="related-book-cover" referrerpolicy="no-referrer" onerror="this.onerror=null;this.src='assets/images/book-placeholder.svg';">
                                    <div>
                                        <strong><?= htmlspecialchars($related['title']) ?></strong>
                                        <p class="muted"><?= htmlspecialchars($related['author']) ?></p>
                                        <span class="rating-inline">
                                            <span class="stars" aria-hidden="true"><?= htmlspecialchars(render_stars((float) $related['avg_rating'])) ?></span>
                                            <span>RM <?= number_format((float) $related['price'], 2) ?></span>
                                        </span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </article>
                <?php endif; ?>
            </aside>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
