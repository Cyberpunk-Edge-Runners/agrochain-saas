<?php
// src/dashboard.php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/db.php';

// Blocks guests and bounces them to /login.php — nothing below this line
// runs unless someone is actually logged in.
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

    <p><em>Product listings coming next — this page is just proving the login/session flow works end to end.</em></p>

    <?php if (in_array($user['role'], ['farmer', 'driver'], true)): ?>
        <p><a href="/upload-document.php">Upload Verification Documents</a></p>
    <?php endif; ?>

    <form method="POST" action="/logout.php">
        <button type="submit">Log Out</button>
    </form>
</body>
</html>