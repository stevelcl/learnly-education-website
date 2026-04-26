<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/cart.php';

$pageTitle = $pageTitle ?? 'Learnly';
$user = current_user();
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
        <button class="nav-toggle" type="button" aria-label="Toggle navigation" data-nav-toggle>&#9776;</button>
        <nav class="site-nav" data-nav>
            <a href="courses.php">Courses</a>
            <a href="forum.php">Forum</a>
            <a href="bookstore.php">Bookstore</a>
            <a href="cart.php">Cart <span class="badge"><?= cart_count() ?></span></a>
            <?php if ($user): ?>
                <a href="dashboard.php">Dashboard</a>
                <a href="logout.php">Logout</a>
            <?php else: ?>
                <a href="login.php">Login</a>
                <a class="button small" href="register.php">Register</a>
            <?php endif; ?>
        </nav>
    </header>
    <main>
