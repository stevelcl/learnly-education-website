<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
$user = require_admin();

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $targetId = (int) ($_POST['user_id'] ?? 0);
    $role = $_POST['role'] ?? 'student';
    $allowedRoles = ['student', 'admin'];

    if ($targetId !== $user['id'] && in_array($role, $allowedRoles, true)) {
        $stmt = db()->prepare('UPDATE users SET role = ? WHERE id = ?');
        $stmt->execute([$role, $targetId]);
        $message = 'User role updated.';
    } else {
        $message = 'You cannot change your own admin role from this screen.';
    }
}

$users = fetch_all('SELECT id, name, email, role, created_at FROM users ORDER BY created_at DESC');
$pageTitle = 'Manage Users';
include __DIR__ . '/includes/header.php';
?>

<section class="section">
    <div class="container">
        <div class="section-head">
            <div>
                <span class="eyebrow">Admin Users</span>
                <h1>Manage roles and registered accounts.</h1>
            </div>
            <a class="button ghost" href="admin-dashboard.php">Back to Admin</a>
        </div>
        <p class="muted">Moderator has been removed from active use. Access is now either student or admin.</p>
        <?php if ($message): ?><p class="alert success"><?= htmlspecialchars($message) ?></p><?php endif; ?>
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Joined</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <td><?= htmlspecialchars($row['email']) ?></td>
                        <td><?= htmlspecialchars($row['role']) ?></td>
                        <td><?= htmlspecialchars($row['created_at']) ?></td>
                        <td>
                            <form method="post" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
                                <select name="role">
                                    <option value="student" <?= $row['role'] === 'student' ? 'selected' : '' ?>>Student</option>
                                    <option value="admin" <?= $row['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                </select>
                                <button type="submit">Save</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
