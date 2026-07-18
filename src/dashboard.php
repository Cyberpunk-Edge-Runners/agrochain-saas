<?php
// src/dashboard.php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/db.php';

$user = requireLogin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — AgroChain</title>
</head>
<body>
    <h1>Welcome, <?= htmlspecialchars($user['name']) ?></h1>
    <p>Logged in as: <?= htmlspecialchars($user['email']) ?> (<?= htmlspecialchars($user['role']) ?>)</p>

    <p><a href="/index.php">&larr; Browse Marketplace</a></p>

    <?php if ($user['role'] === 'farmer'): ?>
        <p><a href="/products.php">Manage My Listings</a></p>
    <?php endif; ?>

    <?php if (in_array($user['role'], ['farmer', 'driver'], true)): ?>
        <p><a href="/upload-documents.php">Upload Verification Documents</a></p>
    <?php endif; ?>

    <form method="POST" action="/logout.php">
        <button type="submit">Log Out</button>
    </form>
</body>
</html>