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
    <title><?= htmlspecialchars($pageTitle) ?> — CultiNet</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= ASSET_CSS ?>">
</head>
<body>
<header class="topbar">
    <a href="<?= ROUTE_HOME ?>" class="wordmark">CultiNet<span>Marketplace</span></a>
    <nav class="topnav">
        <?php if ($navUser): ?>
            <a href="<?= ROUTE_DASHBOARD ?>">Dashboard</a>
            <form method="POST" action="<?= ROUTE_LOGOUT ?>" class="inline-form">
                <button type="submit" class="btn-ghost">Log Out</button>
            </form>
        <?php else: ?>
            <a href="<?= ROUTE_LOGIN ?>">Sign In</a>
            <a href="<?= ROUTE_REGISTER ?>">Create Account</a>
        <?php endif; ?>
    </nav>
</header>
<main class="page-body">