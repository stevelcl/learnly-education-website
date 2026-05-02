<?php

function app_config(): array
{
    $default = [
        'DB_HOST' => getenv('DB_HOST') ?: 'localhost',
        'DB_PORT' => getenv('DB_PORT') ?: '3306',
        'DB_NAME' => getenv('DB_NAME') ?: 'learnly',
        'DB_USER' => getenv('DB_USER') ?: 'root',
        'DB_PASS' => getenv('DB_PASS') ?: '',
        'DB_SSL' => filter_var(getenv('DB_SSL') ?: false, FILTER_VALIDATE_BOOLEAN),
    ];

    $localConfig = dirname(__DIR__) . '/config.php';
    if (file_exists($localConfig)) {
        return array_merge($default, require $localConfig);
    }

    return $default;
}

function db(): PDO
{
    static $pdo = null;
    static $migrated = false;

    if ($pdo instanceof PDO) {
        if (!$migrated) {
            ensure_runtime_schema($pdo);
            $migrated = true;
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
    ensure_runtime_schema($pdo);
    $migrated = true;
    return $pdo;
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

    $pdo->exec("UPDATE users SET role = 'student' WHERE role = 'moderator'");
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

    if ($columnType !== '' && stripos($columnType, "'shipped'") === false) {
        $pdo->exec(
            "ALTER TABLE orders
             MODIFY COLUMN status ENUM('paid', 'processing', 'shipped', 'delivered', 'cancelled')
             NOT NULL DEFAULT 'processing'"
        );
    }
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
