<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/cart.php';

$pageTitle = $pageTitle ?? 'Learnly';
$user = current_user();
$showBackButton = $showBackButton ?? ($pageTitle !== 'Home');
$backTarget = $backTarget ?? 'index.php';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle) ?> | Learnly</title>
    <link rel="icon" type="image/png" href="assets/images/learnly-logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <header class="site-header">
        <a class="brand" href="index.php" aria-label="Learnly home">
            <img src="assets/images/learnly-logo.png" alt="Learnly" class="brand-logo">
            <span class="brand-name">Learnly</span>
        </a>
        <form class="site-search" method="get" action="search.php">
            <label class="visually-hidden" for="site-search-input">Search Learnly</label>
            <input id="site-search-input" type="search" name="q" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" placeholder="Search courses, books, forum">
            <button type="submit" class="button small secondary">Search</button>
        </form>
        <button class="nav-toggle" type="button" aria-label="Toggle navigation" data-nav-toggle>&#9776;</button>
        <nav class="site-nav" data-nav>
            <a href="courses.php">Courses</a>
            <a href="forum.php">Forum</a>
            <a href="bookstore.php">Bookstore</a>
            <a href="cart.php">Cart <span class="badge"><?= cart_count() ?></span></a>
            <?php if ($user): ?>
                <a href="dashboard.php">Dashboard</a>
                <?php if (is_admin($user)): ?>
                    <a href="admin-dashboard.php">Admin</a>
                <?php endif; ?>
                <a href="logout.php">Logout</a>
            <?php else: ?>
                <a href="login.php">Login</a>
                <a class="button small nav-cta" href="register.php">Register</a>
            <?php endif; ?>
        </nav>
    </header>
    <main>
        <?php if ($showBackButton): ?>
            <div class="page-tools">
                <div class="container">
                    <a class="back-link" href="<?= htmlspecialchars($backTarget) ?>">&larr; Back</a>
                </div>
            </div>
        <?php endif; ?>
