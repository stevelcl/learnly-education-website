<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/csrf.php';

$pageTitle = 'Forgot Password';
$error = '';
$message = '';
$step = $_POST['step'] ?? 'request';
$generatedToken = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if ($step === 'request') {
        $email = strtolower(trim($_POST['email'] ?? ''));
        $user = fetch_one('SELECT id FROM users WHERE email = ?', [$email]);

        if (!$user) {
            $error = 'We could not find an account with that email.';
        } else {
            $generatedToken = strtoupper(bin2hex(random_bytes(4)));
            $expiresAt = date('Y-m-d H:i:s', time() + 3600);
            $stmt = db()->prepare('INSERT INTO password_resets (user_id, reset_token, expires_at) VALUES (?, ?, ?)');
            $stmt->execute([(int) $user['id'], $generatedToken, $expiresAt]);
            $message = 'Reset code created. For this coursework demo, use the code below to set a new password.';
            $step = 'reset';
        }
    }

    if ($step === 'reset') {
        $email = strtolower(trim($_POST['email'] ?? ''));
        $token = strtoupper(trim($_POST['token'] ?? ''));
        $password = $_POST['password'] ?? '';
        $user = fetch_one('SELECT id FROM users WHERE email = ?', [$email]);

        if (!$user || strlen($password) < 8) {
            $error = 'Enter a valid account email and a password with at least 8 characters.';
        } else {
            $reset = fetch_one(
                'SELECT * FROM password_resets WHERE user_id = ? AND reset_token = ? AND used_at IS NULL AND expires_at >= NOW() ORDER BY created_at DESC LIMIT 1',
                [(int) $user['id'], $token]
            );

            if (!$reset) {
                $error = 'That reset code is invalid or has expired.';
            } else {
                $stmt = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                $stmt->execute([password_hash($password, PASSWORD_DEFAULT), (int) $user['id']]);
                $mark = db()->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?');
                $mark->execute([(int) $reset['id']]);
                $message = 'Password updated. You can now log in with the new password.';
                $step = 'request';
            }
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<section class="section">
    <div class="container narrow">
        <div class="panel">
            <h1>Forgot Password</h1>
            <p class="muted">This demo reset flow creates a temporary code locally so users can recover access without needing email integration.</p>
            <?php if ($error): ?><p class="alert error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
            <?php if ($message): ?><p class="alert success"><?= htmlspecialchars($message) ?></p><?php endif; ?>

            <?php if ($generatedToken): ?>
                <div class="alert">
                    <strong>Reset code:</strong> <?= htmlspecialchars($generatedToken) ?>
                </div>
            <?php endif; ?>

            <div class="grid">
                <div class="panel">
                    <h2>1. Request Reset Code</h2>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="step" value="request">
                        <label>Email <input name="email" type="email" required></label>
                        <button type="submit">Generate Code</button>
                    </form>
                </div>

                <div class="panel">
                    <h2>2. Set New Password</h2>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="step" value="reset">
                        <label>Email <input name="email" type="email" required></label>
                        <label>Reset Code <input name="token" required></label>
                        <label>New Password
                            <div class="password-field">
                                <input name="password" type="password" minlength="8" required data-password-input>
                                <button type="button" class="password-toggle" data-password-toggle>Show</button>
                            </div>
                        </label>
                        <button type="submit">Update Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
