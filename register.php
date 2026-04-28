<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/cart.php';

$pageTitle = 'Register';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
        $error = 'Please enter a name, valid email, and password with at least 8 characters.';
    } elseif (fetch_one('SELECT id FROM users WHERE email = ?', [$email])) {
        $error = 'An account with this email already exists.';
    } else {
        $stmt = db()->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
        $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
        $_SESSION['user_id'] = (int) db()->lastInsertId();
        sync_session_cart_to_user((int) $_SESSION['user_id']);
        header('Location: dashboard.php');
        exit;
    }
}

include __DIR__ . '/includes/header.php';
?>

<section class="section">
    <div class="container narrow">
        <div class="panel">
            <h1>Create Account</h1>
            <?php if ($error): ?><p class="alert error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
            <form method="post">
                <?= csrf_field() ?>
                <label>Name <input name="name" required></label>
                <label>Email <input name="email" type="email" required></label>
                <label>Password
                    <div class="password-field">
                        <input name="password" type="password" minlength="8" required data-password-input>
                        <button type="button" class="password-toggle" data-password-toggle>Show</button>
                    </div>
                </label>
                <button type="submit">Register</button>
            </form>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
