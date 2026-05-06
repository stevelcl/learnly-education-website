<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-shell.php';

$user = require_admin();

admin_render_start([
    'title' => 'Settings',
    'page_title' => 'Settings',
    'page_subtitle' => 'A home for platform-level configuration as the admin system grows.',
    'active_nav' => 'settings',
    'breadcrumbs' => [
        ['label' => 'Dashboard', 'href' => app_url('admin')],
        ['label' => 'Settings'],
    ],
    'user' => $user,
]);
?>

<section class="panel admin-empty-state">
    <strong>Settings workspace coming next</strong>
    <span>This placeholder keeps the new admin navigation complete while the rest of the dashboard is now fully split into dedicated management areas.</span>
</section>

<?php admin_render_end(); ?>
