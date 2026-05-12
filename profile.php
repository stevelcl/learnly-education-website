<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/progress.php';

$user = require_login();

$activeTab = $_GET['tab'] ?? 'overview';
if (!in_array($activeTab, ['overview', 'edit', 'password', 'courses', 'orders'], true)) {
    $activeTab = 'overview';
}

$error = '';
$successType = $_GET['success'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName  = trim($_POST['last_name'] ?? '');
        $email     = strtolower(trim($_POST['email'] ?? ''));
        $bio       = trim($_POST['bio'] ?? '');
        $phone     = trim($_POST['phone'] ?? '');

        if ($firstName === '' || strlen($firstName) > 60) {
            $error = 'Please enter a valid first name (max 60 characters).';
        } elseif ($lastName === '' || strlen($lastName) > 60) {
            $error = 'Please enter a valid last name (max 60 characters).';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            $error = 'Please enter a valid email address.';
        } elseif ($bio !== '' && strlen($bio) > 600) {
            $error = 'Bio must be 600 characters or fewer.';
        } else {
            $existing = fetch_one('SELECT id FROM users WHERE email = ? AND id != ? AND deleted_at IS NULL AND account_status <> "deleted"', [$email, $user['id']]);
            if ($existing) {
                $error = 'That email is already registered to another account.';
            } else {
                $fullName = $firstName . ' ' . $lastName;
                db()->prepare(
                    'UPDATE users SET name = ?, first_name = ?, last_name = ?, email = ?, bio = ?, phone = ? WHERE id = ?'
                )->execute([
                    $fullName, $firstName, $lastName, $email,
                    $bio !== '' ? $bio : null,
                    $phone !== '' ? $phone : null,
                    $user['id'],
                ]);
                header('Location: profile.php?tab=edit&success=profile');
                exit;
            }
        }
    }

    if ($action === 'change_password') {
        $currentPw = $_POST['current_password'] ?? '';
        $newPw     = $_POST['new_password'] ?? '';
        $confirmPw = $_POST['confirm_password'] ?? '';
        $fullUser  = fetch_one('SELECT password_hash FROM users WHERE id = ?', [$user['id']]);

        if (!password_verify($currentPw, (string) ($fullUser['password_hash'] ?? ''))) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($newPw) < 8) {
            $error = 'New password must be at least 8 characters long.';
        } elseif ($newPw !== $confirmPw) {
            $error = 'New passwords do not match.';
        } else {
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                 ->execute([password_hash($newPw, PASSWORD_DEFAULT), $user['id']]);
            header('Location: profile.php?tab=password&success=password');
            exit;
        }
    }
}

$user = fetch_one(
    'SELECT id, name, first_name, last_name, email, role, account_status, bio, phone, created_at
     FROM users WHERE id = ? AND deleted_at IS NULL',
    [$user['id']]
) ?? $user;

// Stats counts — always needed for the hero bar
$courseCount = (int) (fetch_one(
    'SELECT COUNT(*) AS c FROM course_enrollments WHERE user_id = ? AND archived_at IS NULL',
    [$user['id']]
)['c'] ?? 0);
$orderCount = (int) (fetch_one(
    'SELECT COUNT(*) AS c FROM orders WHERE user_id = ? AND deleted_at IS NULL',
    [$user['id']]
)['c'] ?? 0);
$postCount = (int) (fetch_one(
    'SELECT COUNT(*) AS c FROM forum_posts WHERE user_id = ?',
    [$user['id']]
)['c'] ?? 0);

// Full data — only fetched for the tabs that actually display it
$enrolledCourses = [];
$orders          = [];
$posts           = [];

if (in_array($activeTab, ['overview', 'courses'], true)) {
    $enrolledCourses = fetch_enrolled_courses_with_progress($user['id']);
}
if (in_array($activeTab, ['overview', 'orders'], true)) {
    $orders = fetch_all(
        'SELECT o.*,
                GROUP_CONCAT(COALESCE(oi.book_title, "Unknown book") ORDER BY oi.id SEPARATOR ", ") AS book_names,
                COUNT(oi.id) AS item_count
         FROM orders o
         LEFT JOIN order_items oi ON oi.order_id = o.id
         WHERE o.user_id = ? AND o.deleted_at IS NULL
         GROUP BY o.id
         ORDER BY o.created_at DESC',
        [$user['id']]
    );
}
if ($activeTab === 'overview') {
    $posts = fetch_all(
        'SELECT id, title, status, created_at FROM forum_posts WHERE user_id = ? ORDER BY created_at DESC LIMIT 10',
        [$user['id']]
    );
}

$memberSince = !empty($user['created_at'])
    ? date('F Y', strtotime((string) $user['created_at']))
    : '';

$tabs = [
    'overview' => 'Overview',
    'edit'     => 'Edit Profile',
    'password' => 'Change Password',
    'courses'  => 'My Courses',
    'orders'   => 'My Orders',
];

$pageTitle      = 'My Profile';
$showBackButton = false;
include __DIR__ . '/includes/header.php';
?>

<section class="profile-hero">
    <div class="container">
        <div class="profile-hero-inner">
            <div class="profile-avatar-wrap">
                <div class="profile-avatar" aria-hidden="true"><?= htmlspecialchars(user_initials($user)) ?></div>
            </div>
            <div class="profile-hero-copy">
                <div class="profile-name-row">
                    <h1><?= htmlspecialchars((string) ($user['name'] ?? '')) ?></h1>
                    <span class="profile-role-badge <?= is_admin($user) ? 'admin' : 'student' ?>">
                        <?= is_admin($user) ? 'Admin' : 'Student' ?>
                    </span>
                </div>
                <p class="profile-meta muted">
                    <?= htmlspecialchars((string) ($user['email'] ?? '')) ?>
                    <?php if ($memberSince): ?>
                        <span class="profile-meta-sep">·</span>
                        Member since <?= htmlspecialchars($memberSince) ?>
                    <?php endif; ?>
                    <?php if (!empty($user['phone'])): ?>
                        <span class="profile-meta-sep">·</span>
                        <?= htmlspecialchars((string) $user['phone']) ?>
                    <?php endif; ?>
                </p>
                <?php if (!empty($user['bio'])): ?>
                    <p class="profile-bio"><?= htmlspecialchars((string) $user['bio']) ?></p>
                <?php else: ?>
                    <p class="profile-bio muted"><a href="profile.php?tab=edit">Add a bio</a> to introduce yourself.</p>
                <?php endif; ?>
            </div>
            <div class="profile-hero-actions">
                <a class="button small ghost" href="profile.php?tab=edit">Edit Profile</a>
            </div>
        </div>
        <div class="profile-stats">
            <div class="profile-stat">
                <strong><?= $courseCount ?></strong>
                <span>Courses enrolled</span>
            </div>
            <div class="profile-stat">
                <strong><?= $orderCount ?></strong>
                <span>Orders placed</span>
            </div>
            <div class="profile-stat">
                <strong><?= $postCount ?></strong>
                <span>Forum posts</span>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">

        <nav class="profile-tabs" aria-label="Profile sections">
            <?php foreach ($tabs as $key => $label): ?>
                <a href="profile.php?tab=<?= $key ?>"
                   class="profile-tab<?= $activeTab === $key ? ' active' : '' ?>">
                    <?= htmlspecialchars($label) ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <?php if ($successType === 'profile'): ?>
            <p class="alert">Profile updated successfully.</p>
        <?php elseif ($successType === 'password'): ?>
            <p class="alert">Password changed successfully.</p>
        <?php endif; ?>
        <?php if ($error): ?>
            <p class="alert error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <?php if ($activeTab === 'overview'): ?>
        <div class="profile-tab-panel">
            <div class="profile-overview-grid">
                <div class="panel">
                    <h2>About</h2>
                    <?php if (!empty($user['bio'])): ?>
                        <p><?= nl2br(htmlspecialchars((string) $user['bio'])) ?></p>
                    <?php else: ?>
                        <p class="muted">No bio added yet. <a href="profile.php?tab=edit">Add one</a>.</p>
                    <?php endif; ?>
                    <?php if (!empty($user['phone'])): ?>
                        <p><strong>Phone:</strong> <?= htmlspecialchars((string) $user['phone']) ?></p>
                    <?php endif; ?>
                    <?php
                    $fn = (string) ($user['first_name'] ?? '');
                    $ln = (string) ($user['last_name'] ?? '');
                    if ($fn !== '' || $ln !== ''): ?>
                        <p><strong>First Name:</strong> <?= htmlspecialchars($fn) ?></p>
                        <p><strong>Last Name:</strong> <?= htmlspecialchars($ln) ?></p>
                    <?php endif; ?>
                    <p><strong>Member since:</strong> <?= htmlspecialchars($memberSince) ?></p>
                    <p><strong>Role:</strong> <?= is_admin($user) ? 'Admin' : 'Student' ?></p>
                </div>

                <div class="panel">
                    <h2>Recent Courses</h2>
                    <?php if (!$enrolledCourses): ?>
                        <p class="muted">Not enrolled in any courses yet.</p>
                        <a class="button small" href="courses.php">Browse Courses</a>
                    <?php else: ?>
                        <?php foreach (array_slice($enrolledCourses, 0, 3) as $course): ?>
                            <div class="profile-overview-item">
                                <div class="profile-overview-item-head">
                                    <a href="course.php?id=<?= (int) $course['id'] ?>"><?= htmlspecialchars($course['title']) ?></a>
                                    <span class="muted"><?= (int) $course['progress_percent'] ?>%</span>
                                </div>
                                <div class="progress"><span style="width:<?= (int) $course['progress_percent'] ?>%"></span></div>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($courseCount > 3): ?>
                            <a class="inline-link" href="profile.php?tab=courses">All <?= $courseCount ?> courses</a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <div class="panel">
                    <h2>Recent Orders</h2>
                    <?php if (!$orders): ?>
                        <p class="muted">No orders placed yet.</p>
                        <a class="button small" href="bookstore.php">Visit Bookstore</a>
                    <?php else: ?>
                        <?php foreach (array_slice($orders, 0, 3) as $order): ?>
                            <div class="profile-overview-item">
                                <div class="profile-overview-item-head">
                                    <strong>Order #<?= (int) $order['id'] ?></strong>
                                    <span class="status-pill status-<?= htmlspecialchars($order['status']) ?>">
                                        <?= htmlspecialchars(ucfirst($order['status'])) ?>
                                    </span>
                                </div>
                                <p class="muted profile-small-text">
                                    RM <?= number_format((float) $order['total'], 2) ?>
                                    · <?= htmlspecialchars(date('d M Y', strtotime((string) $order['created_at']))) ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($orderCount > 3): ?>
                            <a class="inline-link" href="profile.php?tab=orders">All <?= $orderCount ?> orders</a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <div class="panel">
                    <h2>Forum Posts</h2>
                    <?php if (!$posts): ?>
                        <p class="muted">No forum posts yet.</p>
                        <a class="button small" href="forum.php">Join Discussion</a>
                    <?php else: ?>
                        <?php foreach (array_slice($posts, 0, 4) as $post): ?>
                            <p>
                                <a href="post.php?id=<?= (int) $post['id'] ?>"><?= htmlspecialchars($post['title']) ?></a><br>
                                <span class="muted profile-small-text"><?= htmlspecialchars(date('d M Y', strtotime((string) $post['created_at']))) ?></span>
                            </p>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php elseif ($activeTab === 'edit'): ?>
        <div class="profile-tab-panel">
            <div class="panel profile-form-panel">
                <h2>Edit Profile</h2>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_profile">
                    <div class="profile-form-row">
                        <label>First Name
                            <input name="first_name" type="text"
                                   value="<?= htmlspecialchars((string) ($user['first_name'] ?? '')) ?>"
                                   required maxlength="60" placeholder="e.g. Alex">
                        </label>
                        <label>Last Name
                            <input name="last_name" type="text"
                                   value="<?= htmlspecialchars((string) ($user['last_name'] ?? '')) ?>"
                                   required maxlength="60" placeholder="e.g. Johnson">
                        </label>
                    </div>
                    <label>Email Address
                        <input name="email" type="email"
                               value="<?= htmlspecialchars((string) ($user['email'] ?? '')) ?>"
                               required maxlength="190">
                    </label>
                    <label>Phone Number <span class="profile-label-hint">(optional)</span>
                        <input name="phone" type="tel"
                               value="<?= htmlspecialchars((string) ($user['phone'] ?? '')) ?>"
                               maxlength="40" placeholder="+60 12-345 6789">
                    </label>
                    <label>Bio <span class="profile-label-hint">(optional &mdash; max 600 characters)</span>
                        <textarea name="bio" maxlength="600"
                                  placeholder="Tell others a bit about yourself..."><?= htmlspecialchars((string) ($user['bio'] ?? '')) ?></textarea>
                    </label>
                    <div class="form-actions">
                        <button type="submit">Save Changes</button>
                        <a class="button ghost" href="profile.php?tab=overview">Cancel</a>
                    </div>
                </form>
            </div>
        </div>

        <?php elseif ($activeTab === 'password'): ?>
        <div class="profile-tab-panel">
            <div class="panel profile-form-panel">
                <h2>Change Password</h2>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="change_password">
                    <label>Current Password
                        <div class="password-field">
                            <input name="current_password" type="password" required data-password-input>
                            <button type="button" class="password-toggle" data-password-toggle>Show</button>
                        </div>
                    </label>
                    <label>New Password <span class="profile-label-hint">(min 8 characters)</span>
                        <div class="password-field">
                            <input name="new_password" type="password" minlength="8" required data-password-input>
                            <button type="button" class="password-toggle" data-password-toggle>Show</button>
                        </div>
                    </label>
                    <label>Confirm New Password
                        <div class="password-field">
                            <input name="confirm_password" type="password" minlength="8" required data-password-input>
                            <button type="button" class="password-toggle" data-password-toggle>Show</button>
                        </div>
                    </label>
                    <div class="form-actions">
                        <button type="submit">Update Password</button>
                    </div>
                </form>
            </div>
        </div>

        <?php elseif ($activeTab === 'courses'): ?>
        <div class="profile-tab-panel">
            <h2 class="profile-section-title">My Courses</h2>
            <?php if (!$enrolledCourses): ?>
                <div class="panel">
                    <p class="muted">You are not enrolled in any courses yet.</p>
                    <a class="button" href="courses.php">Browse Courses</a>
                </div>
            <?php else: ?>
                <div class="profile-courses-grid">
                    <?php foreach ($enrolledCourses as $course): ?>
                        <article class="card resource-card">
                            <div class="card-topline">
                                <span class="tag"><?= htmlspecialchars($course['subject']) ?></span>
                                <span class="muted"><?= htmlspecialchars($course['level']) ?></span>
                            </div>
                            <h3><?= htmlspecialchars($course['title']) ?></h3>
                            <div class="profile-course-progress">
                                <div class="progress"><span style="width:<?= (int) $course['progress_percent'] ?>%"></span></div>
                                <div class="profile-course-meta">
                                    <span><?= (int) $course['progress_percent'] ?>% complete</span>
                                    <span class="muted"><?= (int) $course['completed_items'] ?> / <?= (int) $course['total_items'] ?> steps</span>
                                </div>
                            </div>
                            <a class="inline-link" href="learn.php?course=<?= (int) $course['id'] ?>">Continue learning</a>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php elseif ($activeTab === 'orders'): ?>
        <div class="profile-tab-panel">
            <h2 class="profile-section-title">My Orders</h2>
            <?php if (!$orders): ?>
                <div class="panel">
                    <p class="muted">No orders found.</p>
                    <a class="button" href="bookstore.php">Visit the Bookstore</a>
                </div>
            <?php else: ?>
                <div class="profile-orders-list">
                    <?php foreach ($orders as $order): ?>
                        <div class="profile-order-card">
                            <div class="profile-order-head">
                                <div>
                                    <strong class="profile-order-id">Order #<?= (int) $order['id'] ?></strong>
                                    <p class="muted profile-small-text">
                                        <?= htmlspecialchars(date('d M Y, g:ia', strtotime((string) $order['created_at']))) ?>
                                        <?php if (!empty($order['item_count'])): ?>
                                            · <?= (int) $order['item_count'] ?> item<?= (int) $order['item_count'] !== 1 ? 's' : '' ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <span class="status-pill status-<?= htmlspecialchars($order['status']) ?>">
                                    <?= htmlspecialchars(ucfirst($order['status'])) ?>
                                </span>
                            </div>
                            <?php if (!empty($order['book_names'])): ?>
                                <p class="muted"><?= htmlspecialchars($order['book_names']) ?></p>
                            <?php endif; ?>
                            <div class="profile-order-footer">
                                <div>
                                    <?php if (!empty($order['delivery_address'])): ?>
                                        <p class="muted profile-small-text">Delivery: <?= htmlspecialchars($order['delivery_address']) ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($order['payment_method'])): ?>
                                        <p class="muted profile-small-text">Payment: <?= htmlspecialchars($order['payment_method']) ?></p>
                                    <?php endif; ?>
                                </div>
                                <strong class="profile-order-total">RM <?= number_format((float) $order['total'], 2) ?></strong>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
