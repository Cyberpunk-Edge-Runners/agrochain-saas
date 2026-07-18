<?php
// src/index.php
//
// The PUBLIC marketplace — no login required. Anyone (buyers, or guests
// who haven't registered yet) can browse every crop listing here.

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/db.php';

$user = currentUser();

$stmt = $pdo->query("
    SELECT p.*, u.name AS farmer_name, t.name AS tenant_name
    FROM products p
    JOIN users u ON p.farmer_id = u.id
    LEFT JOIN tenants t ON u.tenant_id = t.id
    ORDER BY p.created_at DESC
");
$products = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgroChain Marketplace</title>
</head>
<body>
    <header>
        <h1>🌾 AgroChain Marketplace</h1>
        <?php if ($user): ?>
            <p>Welcome back, <?= htmlspecialchars($user['name']) ?> — <a href="/dashboard.php">Go to Dashboard</a></p>
        <?php else: ?>
            <p><a href="/login.php">Sign In</a> | <a href="/register.php">Create an Account</a></p>
        <?php endif; ?>
    </header>

    <main>
        <h2>Available Produce</h2>

        <?php if (empty($products)): ?>
            <p><em>No produce listed yet — check back soon.</em></p>
        <?php else: ?>
            <ul>
                <?php foreach ($products as $product): ?>
                    <li>
                        <strong><?= htmlspecialchars($product['crop_type']) ?></strong>
                        — <?= (int) $product['quantity_bags'] ?> bags
                        @ GHS <?= htmlspecialchars($product['price_per_bag']) ?>/bag
                        <br>
                        <small>
                            <?= htmlspecialchars($product['region']) ?> ·
                            Sold by <?= htmlspecialchars($product['farmer_name']) ?><?php if ($product['tenant_name']): ?>
                                (<?= htmlspecialchars($product['tenant_name']) ?>)
                            <?php endif; ?> ·
                            Listed <?= htmlspecialchars($product['created_at']) ?>
                        </small>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </main>
</body>
</html>