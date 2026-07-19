<?php
// includes/partials/header.php
//
// Every page in public/ requires auth.php (for currentUser()) and sets
// $pageTitle BEFORE requiring this file. This is what keeps the site's
// header/nav consistent everywhere instead of nine slightly-different
// copies of the same <head> boilerplate.
//
// Not used for order.php (a POST-only endpoint that never renders HTML —
// it only ever redirects) or logout.php (same — pure redirect, no page).

$navUser = currentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — AgroChain</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;700&family=Work+Sans:wght@400;500;600&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<header class="topbar">
    <a href="/index.php" class="wordmark">AgroChain <span>Marketplace</span></a>
    <nav class="topnav">
        <?php if ($navUser): ?>
            <a href="/dashboard.php">Dashboard</a>
            <form method="POST" action="/logout.php" class="inline-form">
                <button type="submit" class="btn-ghost">Log Out</button>
            </form>
        <?php else: ?>
            <a href="/login.php">Sign In</a>
            <a href="/register.php">Create Account</a>
        <?php endif; ?>
    </nav>
</header>
<main class="page-body">