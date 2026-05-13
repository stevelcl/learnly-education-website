<?php
require_once __DIR__ . '/config-helper.php';

const LEARNLY_RUNTIME_SCHEMA_VERSION = 8;

function db(): PDO
{
    static $pdo = null;
    static $isMigrating = false;

    if ($pdo instanceof PDO) {
        if (!$isMigrating && should_run_runtime_schema()) {
            $isMigrating = true;
            try {
                ensure_runtime_schema($pdo);
                mark_runtime_schema_checked();
            } finally {
                $isMigrating = false;
            }
        }
        return $pdo;
    }

    $config = app_config();
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $config['DB_HOST'],
        $config['DB_PORT'],
        $config['DB_NAME']
    );

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    if (!empty($config['DB_SSL']) && defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
    }

    $pdo = new PDO($dsn, $config['DB_USER'], $config['DB_PASS'], $options);
    if (should_run_runtime_schema()) {
        $isMigrating = true;
        try {
            ensure_runtime_schema($pdo);
            mark_runtime_schema_checked();
        } finally {
            $isMigrating = false;
        }
    }
    return $pdo;
}

function should_run_runtime_schema(): bool
{
    static $shouldRun = null;

    if ($shouldRun !== null) {
        return $shouldRun;
    }

    $cacheFile = runtime_schema_cache_file();
    if (is_file($cacheFile)) {
        $cachedVersion = trim((string) @file_get_contents($cacheFile));
        if ($cachedVersion === (string) LEARNLY_RUNTIME_SCHEMA_VERSION) {
            $shouldRun = false;
            return $shouldRun;
        }
    }

    $shouldRun = true;
    return $shouldRun;
}

function mark_runtime_schema_checked(): void
{
    static $marked = false;

    if ($marked) {
        return;
    }

    $cacheFile = runtime_schema_cache_file();
    @file_put_contents($cacheFile, (string) LEARNLY_RUNTIME_SCHEMA_VERSION, LOCK_EX);
    $marked = true;
}

function runtime_schema_cache_file(): string
{
    return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'learnly-runtime-schema-v' . LEARNLY_RUNTIME_SCHEMA_VERSION . '.flag';
}

function ensure_runtime_schema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS cart_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            book_id INT NOT NULL,
            quantity INT NOT NULL DEFAULT 1,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_book (user_id, book_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            reset_token VARCHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS course_item_progress (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            course_id INT NOT NULL,
            item_type ENUM("resource", "quiz") NOT NULL,
            item_id INT NOT NULL,
            completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_course_item (user_id, course_id, item_type, item_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS course_enrollments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            course_id INT NOT NULL,
            enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_enrollment (user_id, course_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS course_reviews (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            course_id INT NOT NULL,
            rating TINYINT NOT NULL,
            comment TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_review (user_id, course_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS book_reviews (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            book_id INT NOT NULL,
            rating TINYINT NOT NULL,
            comment TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_book_review (user_id, book_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE
        )'
    );

    ensure_column(
        $pdo,
        'users',
        'account_status',
        "ALTER TABLE users ADD COLUMN account_status ENUM('active', 'suspended', 'deleted') NOT NULL DEFAULT 'active' AFTER role"
    );
    ensure_column(
        $pdo,
        'users',
        'suspended_at',
        'ALTER TABLE users ADD COLUMN suspended_at DATETIME NULL AFTER account_status'
    );
    ensure_column(
        $pdo,
        'users',
        'deleted_at',
        'ALTER TABLE users ADD COLUMN deleted_at DATETIME NULL AFTER suspended_at'
    );

    ensure_column(
        $pdo,
        'orders',
        'delivery_address',
        'ALTER TABLE orders ADD COLUMN delivery_address TEXT NULL AFTER status'
    );
    ensure_column(
        $pdo,
        'orders',
        'payment_method',
        'ALTER TABLE orders ADD COLUMN payment_method VARCHAR(80) NULL AFTER delivery_address'
    );

    ensure_order_status_states($pdo);
    ensure_order_item_book_reference($pdo);

    ensure_column(
        $pdo,
        'orders',
        'deleted_at',
        'ALTER TABLE orders ADD COLUMN deleted_at DATETIME NULL AFTER payment_method'
    );

    ensure_column(
        $pdo,
        'courses',
        'thumbnail_path',
        'ALTER TABLE courses ADD COLUMN thumbnail_path VARCHAR(255) NULL AFTER level'
    );
    ensure_column(
        $pdo,
        'courses',
        'banner_path',
        'ALTER TABLE courses ADD COLUMN banner_path VARCHAR(255) NULL AFTER thumbnail_path'
    );
    ensure_column(
        $pdo,
        'course_resources',
        'attachment_path',
        'ALTER TABLE course_resources ADD COLUMN attachment_path VARCHAR(255) NULL AFTER resource_url'
    );
    ensure_column(
        $pdo,
        'course_resources',
        'thumbnail_path',
        'ALTER TABLE course_resources ADD COLUMN thumbnail_path VARCHAR(255) NULL AFTER attachment_path'
    );
    ensure_column(
        $pdo,
        'quiz_questions',
        'title',
        'ALTER TABLE quiz_questions ADD COLUMN title VARCHAR(160) NULL AFTER course_id'
    );
    ensure_column(
        $pdo,
        'quiz_questions',
        'explanation',
        'ALTER TABLE quiz_questions ADD COLUMN explanation TEXT NULL AFTER correct_option'
    );
    ensure_column(
        $pdo,
        'quiz_questions',
        'sort_order',
        'ALTER TABLE quiz_questions ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER explanation'
    );
    ensure_column(
        $pdo,
        'course_reviews',
        'moderation_status',
        "ALTER TABLE course_reviews ADD COLUMN moderation_status ENUM('published', 'hidden', 'flagged', 'removed') NOT NULL DEFAULT 'published' AFTER comment"
    );
    ensure_column(
        $pdo,
        'course_reviews',
        'deleted_at',
        'ALTER TABLE course_reviews ADD COLUMN deleted_at DATETIME NULL AFTER moderation_status'
    );

    ensure_column(
        $pdo,
        'user_progress',
        'archived_at',
        'ALTER TABLE user_progress ADD COLUMN archived_at DATETIME NULL AFTER updated_at'
    );
    ensure_column(
        $pdo,
        'course_enrollments',
        'archived_at',
        'ALTER TABLE course_enrollments ADD COLUMN archived_at DATETIME NULL AFTER enrolled_at'
    );

    ensure_column(
        $pdo,
        'forum_posts',
        'category',
        'ALTER TABLE forum_posts ADD COLUMN category VARCHAR(120) NULL AFTER course_id'
    );
    ensure_column(
        $pdo,
        'forum_posts',
        'is_pinned',
        'ALTER TABLE forum_posts ADD COLUMN is_pinned TINYINT(1) NOT NULL DEFAULT 0 AFTER status'
    );
    ensure_column(
        $pdo,
        'forum_posts',
        'is_featured',
        'ALTER TABLE forum_posts ADD COLUMN is_featured TINYINT(1) NOT NULL DEFAULT 0 AFTER is_pinned'
    );
    ensure_column(
        $pdo,
        'forum_posts',
        'replies_locked',
        'ALTER TABLE forum_posts ADD COLUMN replies_locked TINYINT(1) NOT NULL DEFAULT 0 AFTER is_featured'
    );
    ensure_column(
        $pdo,
        'forum_posts',
        'report_count',
        'ALTER TABLE forum_posts ADD COLUMN report_count INT NOT NULL DEFAULT 0 AFTER replies_locked'
    );

    ensure_column(
        $pdo,
        'users',
        'bio',
        'ALTER TABLE users ADD COLUMN bio TEXT NULL AFTER email'
    );
    ensure_column(
        $pdo,
        'users',
        'phone',
        "ALTER TABLE users ADD COLUMN phone VARCHAR(40) NULL AFTER bio"
    );
    ensure_column(
        $pdo,
        'users',
        'first_name',
        'ALTER TABLE users ADD COLUMN first_name VARCHAR(60) NULL AFTER name'
    );
    ensure_column(
        $pdo,
        'users',
        'last_name',
        'ALTER TABLE users ADD COLUMN last_name VARCHAR(60) NULL AFTER first_name'
    );

    $pdo->exec('UPDATE quiz_questions SET sort_order = id + 1000 WHERE sort_order = 0');

    $pdo->exec("UPDATE users SET role = 'student' WHERE role = 'moderator'");
    $pdo->exec("UPDATE users SET account_status = 'active' WHERE account_status IS NULL OR account_status = ''");
    $pdo->exec("UPDATE course_reviews SET moderation_status = 'published' WHERE moderation_status IS NULL OR moderation_status = ''");
    $pdo->exec("UPDATE orders SET deleted_at = NULL WHERE deleted_at = '0000-00-00 00:00:00'");
    $pdo->exec("UPDATE course_enrollments SET archived_at = NULL WHERE archived_at = '0000-00-00 00:00:00'");
    $pdo->exec("UPDATE user_progress SET archived_at = NULL WHERE archived_at = '0000-00-00 00:00:00'");
}

function ensure_column(PDO $pdo, string $table, string $column, string $sql): void
{
    $stmt = $pdo->prepare(
        'SELECT 1
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = ?
           AND column_name = ?
         LIMIT 1'
    );
    $stmt->execute([$table, $column]);
    if (!$stmt->fetchColumn()) {
        $pdo->exec($sql);
    }
}

function ensure_order_status_states(PDO $pdo): void
{
    $stmt = $pdo->prepare(
        'SELECT column_type
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = ?
           AND column_name = ?
         LIMIT 1'
    );
    $stmt->execute(['orders', 'status']);
    $columnType = (string) ($stmt->fetchColumn() ?: '');

    if (
        $columnType !== '' &&
        (
            stripos($columnType, "'pending'") === false ||
            stripos($columnType, "'refunded'") === false ||
            stripos($columnType, "'archived'") === false
        )
    ) {
        $pdo->exec(
            "ALTER TABLE orders
             MODIFY COLUMN status ENUM('pending', 'paid', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded', 'archived')
             NOT NULL DEFAULT 'pending'"
        );
    }
}

function ensure_order_item_book_reference(PDO $pdo): void
{
    ensure_column(
        $pdo,
        'order_items',
        'book_title',
        'ALTER TABLE order_items ADD COLUMN book_title VARCHAR(180) NULL AFTER book_id'
    );

    $pdo->exec(
        'UPDATE order_items oi
         LEFT JOIN books b ON b.id = oi.book_id
         SET oi.book_title = COALESCE(oi.book_title, b.title)
         WHERE oi.book_title IS NULL OR oi.book_title = ""'
    );

    $bookIdColumn = fetch_one(
        'SELECT is_nullable
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = ?
           AND column_name = ?
         LIMIT 1',
        ['order_items', 'book_id']
    );

    if (($bookIdColumn['is_nullable'] ?? 'NO') !== 'YES') {
        $pdo->exec('ALTER TABLE order_items MODIFY COLUMN book_id INT NULL');
    }

    $existingTargetConstraint = fetch_one(
        'SELECT delete_rule
         FROM information_schema.referential_constraints
         WHERE constraint_schema = DATABASE()
           AND table_name = ?
           AND constraint_name = ?
         LIMIT 1',
        ['order_items', 'fk_order_items_book']
    );

    if (($existingTargetConstraint['delete_rule'] ?? '') === 'SET NULL') {
        return;
    }

    $foreignKeys = fetch_all(
        'SELECT constraint_name
         FROM information_schema.key_column_usage
         WHERE table_schema = DATABASE()
           AND table_name = ?
           AND column_name = ?
           AND referenced_table_name = ?',
        ['order_items', 'book_id', 'books']
    );

    foreach ($foreignKeys as $foreignKey) {
        $constraintName = $foreignKey['constraint_name'] ?? '';
        if ($constraintName === '') {
            continue;
        }

        $deleteRule = fetch_one(
            'SELECT delete_rule
             FROM information_schema.referential_constraints
             WHERE constraint_schema = DATABASE()
               AND table_name = ?
               AND constraint_name = ?
             LIMIT 1',
            ['order_items', $constraintName]
        );

        if (($deleteRule['delete_rule'] ?? '') === 'SET NULL') {
            return;
        }

        $pdo->exec('ALTER TABLE order_items DROP FOREIGN KEY `' . str_replace('`', '``', $constraintName) . '`');
    }

    $pdo->exec(
        'ALTER TABLE order_items
         ADD CONSTRAINT fk_order_items_book
         FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE SET NULL'
    );
}

function fetch_all(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function fetch_one(string $sql, array $params = []): ?array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row ?: null;
}
