<?php

require_once __DIR__ . '/config-helper.php';
require_once __DIR__ . '/auth.php';

function admin_sidebar_items(): array
{
    return [
        'dashboard' => ['label' => 'Dashboard', 'href' => app_url('admin')],
        'courses' => ['label' => 'Courses', 'href' => app_url('admin/courses')],
        'students' => ['label' => 'Students', 'href' => app_url('admin/users')],
        'analytics' => ['label' => 'Progress Analytics', 'href' => app_url('admin/analytics')],
        'feedback' => ['label' => 'Feedback', 'href' => app_url('admin/feedback')],
        'orders' => ['label' => 'Orders', 'href' => app_url('admin/orders')],
        'books' => ['label' => 'Bookstore', 'href' => app_url('admin/books')],
        'forum' => ['label' => 'Forum', 'href' => app_url('admin/forum')],
        'settings' => ['label' => 'Settings', 'href' => app_url('admin/settings')],
    ];
}

function admin_site_home_url(): string
{
    return app_url();
}

function admin_settings_url(): string
{
    return app_url('admin/settings');
}

function admin_render_start(array $options): void
{
    $title = $options['title'] ?? 'Admin';
    $activeNav = $options['active_nav'] ?? 'dashboard';
    $breadcrumbs = $options['breadcrumbs'] ?? [];
    $actions = $options['actions'] ?? [];
    $notice = $options['notice'] ?? '';
    $error = $options['error'] ?? '';
    $pageTitle = $options['page_title'] ?? $title;
    $pageSubtitle = $options['page_subtitle'] ?? '';
    $adminUser = $options['user'] ?? current_user();
    $sidebarItems = admin_sidebar_items();
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= htmlspecialchars($title) ?> | Learnly Admin</title>
        <base href="<?= htmlspecialchars(app_base_path()) ?>">
        <link rel="icon" type="image/png" href="assets/images/learnly-logo.png">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="assets/css/styles.css">
    </head>
    <body class="admin-app">
        <div class="admin-app-shell">
            <aside class="admin-app-sidebar" data-admin-sidebar>
                <div class="admin-app-sidebar-head">
                    <a class="admin-app-brand" href="<?= htmlspecialchars(app_url('admin')) ?>">
                        <img src="assets/images/learnly-logo.png" alt="Learnly">
                        <div>
                            <strong>Learnly</strong>
                            <span>Admin Dashboard</span>
                        </div>
                    </a>
                    <button class="admin-sidebar-close" type="button" data-admin-sidebar-close aria-label="Close navigation">&times;</button>
                </div>
                <nav class="admin-app-nav">
                    <?php foreach ($sidebarItems as $key => $item): ?>
                        <a class="<?= $activeNav === $key ? 'active' : '' ?>" href="<?= htmlspecialchars($item['href']) ?>"><?= htmlspecialchars($item['label']) ?></a>
                    <?php endforeach; ?>
                </nav>
            </aside>

            <div class="admin-app-main">
                <header class="admin-topbar">
                    <div class="admin-topbar-left">
                        <button class="admin-sidebar-toggle" type="button" data-admin-sidebar-toggle aria-label="Open navigation">&#9776;</button>
                        <div>
                            <?php if ($breadcrumbs): ?>
                                <nav class="admin-breadcrumbs" aria-label="Breadcrumb">
                                    <?php foreach ($breadcrumbs as $index => $crumb): ?>
                                        <?php if ($index > 0): ?><span>/</span><?php endif; ?>
                                        <?php if (!empty($crumb['href'])): ?>
                                            <a href="<?= htmlspecialchars($crumb['href']) ?>"><?= htmlspecialchars($crumb['label']) ?></a>
                                        <?php else: ?>
                                            <strong><?= htmlspecialchars($crumb['label']) ?></strong>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </nav>
                            <?php endif; ?>
                            <div class="admin-page-heading">
                                <h1><?= htmlspecialchars($pageTitle) ?></h1>
                                <?php if ($pageSubtitle !== ''): ?><p><?= htmlspecialchars($pageSubtitle) ?></p><?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="admin-topbar-actions">
                        <?php foreach ($actions as $action): ?>
                            <a class="button<?= !empty($action['secondary']) ? ' ghost' : '' ?>" href="<?= htmlspecialchars($action['href']) ?>"><?= htmlspecialchars($action['label']) ?></a>
                        <?php endforeach; ?>
                        <?php if ($adminUser): ?>
                            <details class="admin-user-menu">
                                <summary class="admin-user-chip">
                                    <div>
                                        <strong><?= htmlspecialchars($adminUser['name']) ?></strong>
                                        <span><?= htmlspecialchars($adminUser['role']) ?></span>
                                    </div>
                                    <span class="admin-user-caret" aria-hidden="true">&#9662;</span>
                                </summary>
                                <div class="admin-user-dropdown">
                                    <a href="<?= htmlspecialchars(app_url('admin')) ?>">Admin Home</a>
                                    <a href="<?= htmlspecialchars(admin_site_home_url()) ?>">Visit Site</a>
                                    <a href="<?= htmlspecialchars(admin_settings_url()) ?>">Settings</a>
                                    <a href="<?= htmlspecialchars(app_url('logout.php')) ?>">Logout</a>
                                </div>
                            </details>
                        <?php endif; ?>
                    </div>
                </header>

                <main class="admin-content">
                    <?php if ($notice !== ''): ?>
                        <div class="admin-toast-stack"><div class="admin-toast success" data-admin-toast><?= htmlspecialchars($notice) ?></div></div>
                    <?php endif; ?>
                    <?php if ($error !== ''): ?>
                        <div class="admin-toast-stack"><div class="admin-toast error" data-admin-toast><?= htmlspecialchars($error) ?></div></div>
                    <?php endif; ?>
    <?php
}

function admin_render_end(): void
{
    ?>
                </main>
            </div>
        </div>

        <div class="admin-confirm-modal" data-admin-confirm-modal hidden>
            <div class="admin-confirm-card">
                <h2>Confirm action</h2>
                <p data-admin-confirm-message>Are you sure?</p>
                <div class="actions">
                    <button type="button" class="button ghost" data-admin-confirm-cancel>Cancel</button>
                    <button type="button" class="button danger" data-admin-confirm-accept>Continue</button>
                </div>
            </div>
        </div>

        <script src="assets/js/app.js"></script>
    </body>
    </html>
    <?php
}
