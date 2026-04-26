<?php
$pageTitle = 'Home';
require_once __DIR__ . '/includes/db.php';
$courses = fetch_all('SELECT * FROM courses ORDER BY created_at DESC LIMIT 3');
$books = fetch_all('SELECT * FROM books ORDER BY inventory DESC LIMIT 3');
$courseCount = (int) (fetch_one('SELECT COUNT(*) AS total FROM courses')['total'] ?? 0);
$bookCount = (int) (fetch_one('SELECT COUNT(*) AS total FROM books')['total'] ?? 0);
$forumCount = (int) (fetch_one('SELECT COUNT(*) AS total FROM forum_posts')['total'] ?? 0);
include __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <div class="hero-inner hero-grid">
        <div class="hero-copy">
            <span class="hero-pill">Built for undergraduate learning</span>
            <h1>A modern study space for courses, questions, and academic books.</h1>
            <p>Learnly brings together structured learning content, progress tracking, community support, and a student-focused bookstore in one clean platform.</p>
            <div class="actions">
                <a class="button" href="courses.php">Explore Courses</a>
                <a class="button ghost-light" href="bookstore.php">Visit Bookstore</a>
            </div>
            <div class="hero-quickstats">
                <div>
                    <strong><?= $courseCount ?>+</strong>
                    <span>Course modules</span>
                </div>
                <div>
                    <strong><?= $bookCount ?>+</strong>
                    <span>Academic books</span>
                </div>
                <div>
                    <strong><?= $forumCount ?>+</strong>
                    <span>Forum discussions</span>
                </div>
            </div>
        </div>
        <div class="hero-preview">
            <div class="preview-card preview-course">
                <div class="preview-head">
                    <span class="tag">Featured Path</span>
                    <span class="muted">Week 4</span>
                </div>
                <h2>Introduction to Programming</h2>
                <p>Progress is synced across notes, quiz review, and saved resources.</p>
                <div class="progress"><span style="width: 72%"></span></div>
                <div class="preview-metrics">
                    <div><strong>72%</strong><span>Completed</span></div>
                    <div><strong>12</strong><span>Lessons</span></div>
                    <div><strong>4.8</strong><span>Student rating</span></div>
                </div>
            </div>
            <div class="preview-card preview-stack">
                <div class="stack-item">
                    <span class="icon-chip">Q&amp;A</span>
                    <div>
                        <strong>Peer discussion</strong>
                        <p>Ask questions and get support tied to your course modules.</p>
                    </div>
                </div>
                <div class="stack-item">
                    <span class="icon-chip">Books</span>
                    <div>
                        <strong>Smart bookstore</strong>
                        <p>Search by topic, compare titles, and check out in a few clicks.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head">
            <div>
                <span class="eyebrow">Why students use Learnly</span>
                <h2>Designed to support actual study habits, not just store content.</h2>
            </div>
            <p>Everything is arranged around the flow of learning: discover a module, save what matters, track progress, ask for help, and get the books you need.</p>
        </div>
        <div class="feature-grid">
            <article class="feature-card">
                <div class="feature-icon">L</div>
                <h3>Structured learning</h3>
                <p>Courses organize notes, videos, and quiz material into a format that feels guided instead of scattered.</p>
            </article>
            <article class="feature-card">
                <div class="feature-icon">P</div>
                <h3>Progress visibility</h3>
                <p>Students can track completion, save modules, and revisit resources without losing momentum.</p>
            </article>
            <article class="feature-card">
                <div class="feature-icon">C</div>
                <h3>Community support</h3>
                <p>The forum turns isolated studying into collaborative learning with question threads and moderation.</p>
            </article>
            <article class="feature-card">
                <div class="feature-icon">B</div>
                <h3>Book discovery</h3>
                <p>The bookstore connects learning needs to book search, categories, inventory, and checkout.</p>
            </article>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head">
            <div>
                <span class="eyebrow">Featured modules</span>
                <h2>Start with course spaces that feel focused and easy to scan.</h2>
            </div>
            <a class="button ghost" href="courses.php">View all courses</a>
        </div>
        <div class="grid course-grid">
            <?php foreach ($courses as $course): ?>
                <article class="card resource-card">
                    <div class="card-topline">
                        <span class="tag"><?= htmlspecialchars($course['subject']) ?></span>
                        <span class="muted"><?= htmlspecialchars($course['level']) ?></span>
                    </div>
                    <h3><?= htmlspecialchars($course['title']) ?></h3>
                    <p><?= htmlspecialchars($course['description']) ?></p>
                    <a class="inline-link" href="course.php?id=<?= (int) $course['id'] ?>">View resources</a>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="spotlight">
            <div class="spotlight-copy">
                <span class="eyebrow">Bookstore</span>
                <h2>Pick up academic titles without leaving your study flow.</h2>
                <p>Browse by discipline, compare titles, and move straight into checkout with inventory already connected to the database.</p>
                <a class="button" href="bookstore.php">Browse the bookstore</a>
            </div>
            <div class="book-shelf">
                <?php foreach ($books as $book): ?>
                    <article class="card shelf-book">
                        <img src="<?= htmlspecialchars($book['cover_url']) ?>" alt="" class="book-cover">
                        <h3><?= htmlspecialchars($book['title']) ?></h3>
                        <p class="muted"><?= htmlspecialchars($book['author']) ?></p>
                        <p><strong>RM <?= number_format((float) $book['price'], 2) ?></strong></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
