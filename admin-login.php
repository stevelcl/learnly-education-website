<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

$existing = current_user();
if (is_admin($existing)) {
    header('Location: admin-dashboard.php');
    exit;
}

$pageTitle = 'Admin Login';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $user = fetch_one('SELECT * FROM users WHERE email = ?', [$email]);

    if (!$user || !password_verify($password, $user['password_hash']) || $user['role'] !== 'admin') {
        $error = 'Admin access requires a valid administrator account.';
    } else {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        header('Location: admin-dashboard.php');
        exit;
    }
}

include __DIR__ . '/includes/header.php';
?>

<section class="section">
    <div class="container narrow">
        <div class="panel">
            <span class="eyebrow">Private Access</span>
            <h1>Administrator Login</h1>
            <p class="muted">This page is intended for platform administrators only and is not shown in the public navigation.</p>
            <?php if ($error): ?><p class="alert error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
            <form method="post">
                <?= csrf_field() ?>
                <label>Email <input name="email" type="email" required></label>
                <label>Password <input name="password" type="password" required></label>
                <button type="submit">Enter Admin Panel</button>
            </form>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
