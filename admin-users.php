<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-shell.php';
require_once __DIR__ . '/includes/csrf.php';

$user = require_admin();
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['q'] ?? '');

$noticeMap = [
    'role_saved' => 'User role updated successfully.',
    'suspended' => 'User suspended successfully.',
    'reactivated' => 'User reactivated successfully.',
    'deleted' => 'User removed from active access.',
];
$errorMap = [
    'self_role' => 'You cannot remove your own admin role.',
    'self_delete' => 'You cannot delete your own account.',
    'self_suspend' => 'You cannot suspend your own account.',
    'last_admin' => 'At least one active admin account must remain.',
    'invalid' => 'That user action could not be completed.',
];
$message = $noticeMap[$_GET['notice'] ?? ''] ?? '';
$error = $errorMap[$_GET['error'] ?? ''] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $targetId = (int) ($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $target = fetch_one('SELECT * FROM users WHERE id = ?', [$targetId]);
    $redirectBase = app_url_with_query(app_url('admin/users'), ['filter' => $filter, 'q' => $search]);

    if (!$target) {
        header('Location: ' . $redirectBase . '&error=invalid');
        exit;
    }

    $activeAdminCount = (int) (fetch_one(
        'SELECT COUNT(*) AS total
         FROM users
         WHERE role = "admin"
           AND deleted_at IS NULL
           AND account_status = "active"'
    )['total'] ?? 0);

    $redirectWith = static function (string $param, string $value) use ($redirectBase): never {
        header('Location: ' . $redirectBase . '&' . $param . '=' . $value);
        exit;
    };

    if ($action === 'save_role') {
        $role = $_POST['role'] ?? 'student';
        if (!in_array($role, ['student', 'admin'], true)) {
            $redirectWith('error', 'invalid');
        }

        if ($targetId === (int) $user['id'] && $role !== 'admin') {
            $redirectWith('error', 'self_role');
        }

        if (
            $target['role'] === 'admin' &&
            $role !== 'admin' &&
            $target['account_status'] === 'active' &&
            empty($target['deleted_at']) &&
            $activeAdminCount <= 1
        ) {
            $redirectWith('error', 'last_admin');
        }

        db()->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$role, $targetId]);
        $redirectWith('notice', 'role_saved');
    }

    if ($action === 'suspend') {
        if ($targetId === (int) $user['id']) {
            $redirectWith('error', 'self_suspend');
        }
        if (
            $target['role'] === 'admin' &&
            $target['account_status'] === 'active' &&
            empty($target['deleted_at']) &&
            $activeAdminCount <= 1
        ) {
            $redirectWith('error', 'last_admin');
        }

        db()->prepare(
            'UPDATE users
             SET account_status = "suspended", suspended_at = NOW()
             WHERE id = ?'
        )->execute([$targetId]);
        $redirectWith('notice', 'suspended');
    }

    if ($action === 'reactivate') {
        db()->prepare(
            'UPDATE users
             SET account_status = "active", suspended_at = NULL, deleted_at = NULL
             WHERE id = ?'
        )->execute([$targetId]);
        $redirectWith('notice', 'reactivated');
    }

    if ($action === 'delete') {
        if ($targetId === (int) $user['id']) {
            $redirectWith('error', 'self_delete');
        }
        if (
            $target['role'] === 'admin' &&
            $target['account_status'] === 'active' &&
            empty($target['deleted_at']) &&
            $activeAdminCount <= 1
        ) {
            $redirectWith('error', 'last_admin');
        }

        db()->prepare(
            'UPDATE users
             SET account_status = "deleted",
                 deleted_at = NOW()
             WHERE id = ?'
        )->execute([$targetId]);
        $redirectWith('notice', 'deleted');
    }

    header('Location: ' . $redirectBase . '&error=invalid');
    exit;
}

$allowedFilters = ['all', 'students', 'admins', 'suspended'];
if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'all';
}

$where = ['u.account_status <> "deleted"', 'u.deleted_at IS NULL'];
$params = [];

if ($filter === 'students') {
    $where[] = 'u.role = "student"';
    $where[] = 'u.account_status <> "deleted"';
}
if ($filter === 'admins') {
    $where[] = 'u.role = "admin"';
    $where[] = 'u.account_status <> "deleted"';
}
if ($filter === 'suspended') {
    $where[] = 'u.account_status = "suspended"';
}
if ($search !== '') {
    $where[] = '(u.name LIKE ? OR u.email LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$users = fetch_all(
    'SELECT u.id, u.name, u.email, u.role, u.account_status, u.created_at, u.suspended_at, u.deleted_at
     FROM users u
     WHERE ' . implode(' AND ', $where) . '
     ORDER BY
        CASE
            WHEN u.account_status = "suspended" THEN 1
            WHEN u.role = "admin" THEN 2
            ELSE 3
        END,
        u.created_at DESC',
    $params
);

$stats = [
    'all' => (int) (fetch_one('SELECT COUNT(*) AS total FROM users WHERE account_status <> "deleted"')['total'] ?? 0),
    'students' => (int) (fetch_one('SELECT COUNT(*) AS total FROM users WHERE role = "student" AND account_status <> "deleted"')['total'] ?? 0),
    'admins' => (int) (fetch_one('SELECT COUNT(*) AS total FROM users WHERE role = "admin" AND account_status <> "deleted"')['total'] ?? 0),
    'suspended' => (int) (fetch_one('SELECT COUNT(*) AS total FROM users WHERE account_status = "suspended"')['total'] ?? 0),
];

admin_render_start([
    'title' => 'Users',
    'page_title' => 'Users',
    'page_subtitle' => 'Control roles, account access, and moderation states from one compact management table.',
    'active_nav' => 'students',
    'breadcrumbs' => [
        ['label' => 'Dashboard', 'href' => app_url('admin')],
        ['label' => 'Users'],
    ],
    'notice' => $message,
    'error' => $error,
    'user' => $user,
]);
?>

<section class="admin-stats-row">
    <article class="panel admin-stat-card"><strong><?= $stats['all'] ?></strong><span class="muted">Active Users</span></article>
    <article class="panel admin-stat-card"><strong><?= $stats['students'] ?></strong><span class="muted">Students</span></article>
    <article class="panel admin-stat-card"><strong><?= $stats['admins'] ?></strong><span class="muted">Admins</span></article>
    <article class="panel admin-stat-card"><strong><?= $stats['suspended'] ?></strong><span class="muted">Suspended</span></article>
</section>

<section class="panel">
    <form class="admin-filter-bar" method="get">
        <label>Search
            <input type="search" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search name or email">
        </label>
        <label>Filter
            <select name="filter">
                <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All users</option>
                <option value="students" <?= $filter === 'students' ? 'selected' : '' ?>>Students</option>
                <option value="admins" <?= $filter === 'admins' ? 'selected' : '' ?>>Admins</option>
                <option value="suspended" <?= $filter === 'suspended' ? 'selected' : '' ?>>Suspended users</option>
            </select>
        </label>
        <div class="form-actions">
            <button type="submit">Apply</button>
            <a class="button ghost" href="<?= htmlspecialchars(app_url('admin/users')) ?>">Reset</a>
        </div>
    </form>
</section>

<section class="panel admin-data-table">
    <?php if (!$users): ?>
        <div class="admin-empty-state"><strong>No users found</strong><span>Try adjusting the current filters or search query.</span></div>
    <?php else: ?>
        <table class="admin-compact-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $row): ?>
                    <?php
                    $status = $row['account_status'] === 'deleted' || !empty($row['deleted_at']) ? 'deleted' : $row['account_status'];
                    $isSelf = (int) $row['id'] === (int) $user['id'];
                    ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($row['name']) ?></strong>
                            <?php if ($isSelf): ?><span class="muted"> (You)</span><?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($row['email']) ?></td>
                        <td>
                            <form method="post" class="inline-form compact-inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
                                <input type="hidden" name="action" value="save_role">
                                <select name="role" <?= $status === 'deleted' ? 'disabled' : '' ?>>
                                    <option value="student" <?= $row['role'] === 'student' ? 'selected' : '' ?>>Student</option>
                                    <option value="admin" <?= $row['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                </select>
                                <button type="submit" class="button ghost small" <?= $status === 'deleted' ? 'disabled' : '' ?>>Save Role</button>
                            </form>
                        </td>
                        <td><span class="status-pill status-<?= htmlspecialchars($status) ?>"><?= htmlspecialchars(ucfirst($status)) ?></span></td>
                        <td><?= htmlspecialchars((string) $row['created_at']) ?></td>
                        <td>
                            <div class="admin-table-actions">
                                <?php if ($status === 'active'): ?>
                                    <form method="post" class="inline-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
                                        <input type="hidden" name="action" value="suspend">
                                        <button type="submit" class="button ghost small" data-confirm="Suspend this account? The user will no longer be able to sign in.">Suspend</button>
                                    </form>
                                <?php elseif ($status === 'suspended'): ?>
                                    <form method="post" class="inline-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
                                        <input type="hidden" name="action" value="reactivate">
                                        <button type="submit" class="button ghost small">Reactivate</button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($status !== 'deleted'): ?>
                                    <form method="post" class="inline-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="button danger small" data-confirm="Remove this account from active access? This keeps history intact but disables the account.">Delete User</button>
                                    </form>
                                <?php else: ?>
                                    <span class="muted">Removed</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<?php admin_render_end(); ?>
