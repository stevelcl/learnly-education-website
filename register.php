<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/cart.php';

$pageTitle = 'Register';
$error = '';
$values = ['first_name' => '', 'last_name' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name'] ?? '');
    $email     = strtolower(trim($_POST['email'] ?? ''));
    $password  = $_POST['password'] ?? '';

    $values = ['first_name' => $firstName, 'last_name' => $lastName, 'email' => $email];

    if ($firstName === '' || strlen($firstName) > 60) {
        $error = 'Please enter a valid first name (max 60 characters).';
    } elseif ($lastName === '' || strlen($lastName) > 60) {
        $error = 'Please enter a valid last name (max 60 characters).';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($existing = fetch_one('SELECT id, account_status FROM users WHERE email = ?', [$email])) {
        $status = $existing['account_status'] ?? 'active';
        if ($status === 'suspended') {
            $error = 'An account with this email is currently suspended.';
        } elseif ($status === 'deleted') {
            $fullName = $firstName . ' ' . $lastName;
            db()->prepare(
                'UPDATE users
                 SET name = ?, first_name = ?, last_name = ?, password_hash = ?,
                     account_status = \'active\', deleted_at = NULL, suspended_at = NULL,
                     role = \'student\'
                 WHERE id = ?'
            )->execute([$fullName, $firstName, $lastName, password_hash($password, PASSWORD_DEFAULT), $existing['id']]);
            $_SESSION['user_id'] = (int) $existing['id'];
            sync_session_cart_to_user((int) $existing['id']);
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'An account with this email already exists.';
        }
    } else {
        $fullName = $firstName . ' ' . $lastName;
        try {
            db()->prepare(
                'INSERT INTO users (name, first_name, last_name, email, password_hash) VALUES (?, ?, ?, ?, ?)'
            )->execute([$fullName, $firstName, $lastName, $email, password_hash($password, PASSWORD_DEFAULT)]);
            $_SESSION['user_id'] = (int) db()->lastInsertId();
            sync_session_cart_to_user((int) $_SESSION['user_id']);
            header('Location: dashboard.php');
            exit;
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), '1062') !== false) {
                $error = 'An account with this email already exists.';
            } else {
                throw $e;
            }
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<section class="section">
    <div class="container narrow">
        <div class="panel">
            <h1>Create Account</h1>
            <p class="muted">Join Learnly to access courses, the bookstore, and the forum.</p>
            <?php if ($error): ?>
                <p class="alert error"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>
            <form method="post">
                <?= csrf_field() ?>
                <div class="profile-form-row">
                    <label>First Name
                        <input name="first_name" type="text" required maxlength="60"
                               value="<?= htmlspecialchars($values['first_name']) ?>"
                               placeholder="e.g. Alex">
                    </label>
                    <label>Last Name
                        <input name="last_name" type="text" required maxlength="60"
                               value="<?= htmlspecialchars($values['last_name']) ?>"
                               placeholder="e.g. Johnson">
                    </label>
                </div>
                <label>Email Address
                    <input name="email" type="email" required
                           value="<?= htmlspecialchars($values['email']) ?>"
                           placeholder="you@example.com">
                </label>
                <label>Password <span class="profile-label-hint">(min 8 characters)</span>
                    <div class="password-field">
                        <input name="password" type="password" minlength="8" required data-password-input
                               placeholder="Choose a strong password">
                        <button type="button" class="password-toggle" data-password-toggle>Show</button>
                    </div>
                </label>
                <button type="submit">Create Account</button>
            </form>
            <p class="muted">Already have an account? <a href="login.php">Log in</a>.</p>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
