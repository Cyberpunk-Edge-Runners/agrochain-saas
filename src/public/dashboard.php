<?php
// public/dashboard.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

$user = requireLogin();

$orderLinkLabels = [
    'buyer'  => 'My Orders',
    'farmer' => 'Orders On My Listings',
    'driver' => 'My Delivery Queue',
];

$pageTitle = 'Dashboard';
require __DIR__ . '/../includes/partials/header.php';
?>

<h1>Welcome, <?= htmlspecialchars($user['name']) ?></h1>
<p class="row-meta">
    <?= htmlspecialchars($user['email']) ?> ·
    <span class="stamp"><?= htmlspecialchars($user['role']) ?></span>
</p>

<hr class="divider">

<div class="ticket">
    <?php if ($user['role'] === 'farmer'): ?>
        <p><a href="/products.php">Manage My Listings</a></p>
    <?php endif; ?>

    <?php if (isset($orderLinkLabels[$user['role']])): ?>
        <p><a href="/orders.php"><?= htmlspecialchars($orderLinkLabels[$user['role']]) ?></a></p>
    <?php endif; ?>

    <?php if (in_array($user['role'], ['farmer', 'driver'], true)): ?>
        <p><a href="/upload-documents.php">Upload Verification Documents</a></p>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/partials/footer.php'; ?>