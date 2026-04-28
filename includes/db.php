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
