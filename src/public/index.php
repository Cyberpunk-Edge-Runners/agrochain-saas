<?php
// public/index.php
//
// The PUBLIC marketplace — no login required. Anyone (buyers, or guests
// who haven't registered yet) can browse every crop listing here.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

$user = currentUser();

// order.php redirects back here with ?error=... when something goes wrong
// placing an order (e.g. "only 5 bags are available").
$orderError = $_GET['error'] ?? null;

$stmt = $pdo->query("
    SELECT p.*, u.name AS farmer_name, t.name AS tenant_name
    FROM products p
    JOIN users u ON p.farmer_id = u.id
    LEFT JOIN tenants t ON u.tenant_id = t.id
    ORDER BY p.created_at DESC
");
$products = $stmt->fetchAll();

$pageTitle = 'Marketplace';
require __DIR__ . '/../includes/partials/header.php';
?>

<h1>Available Produce</h1>

<?php if ($orderError): ?>
    <div class="alert alert-error"><?= htmlspecialchars($orderError) ?></div>
<?php endif; ?>

<?php if (empty($products)): ?>
    <p class="empty-note">No produce listed yet — check back soon.</p>
<?php else: ?>
    <ul class="data-list">
        <?php foreach ($products as $product): ?>
            <li class="ticket">
                <div class="row-title"><?= htmlspecialchars($product['crop_type']) ?></div>

                <p class="row-meta">
                    <span class="row-figure"><?= (int) $product['quantity_bags'] ?> bags</span>
                    @ <span class="row-figure">GHS <?= htmlspecialchars($product['price_per_bag']) ?></span>/bag
                </p>

                <p class="row-meta">
                    <span class="stamp"><?= htmlspecialchars($product['region']) ?></span>
                    Sold by <?= htmlspecialchars($product['farmer_name']) ?><?php if ($product['tenant_name']): ?>
                        (<?= htmlspecialchars($product['tenant_name']) ?>)
                    <?php endif; ?>
                    · Listed <?= htmlspecialchars($product['created_at']) ?>
                </p>

                <hr class="divider">

                <?php if ($user && $user['role'] === 'buyer'): ?>
                    <form method="POST" action="/order.php">
                        <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                        <div class="field">
                            <label>Bags to order</label>
                            <input type="number" name="quantity_bags" min="1"
                                   max="<?= (int) $product['quantity_bags'] ?>" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Order This</button>
                    </form>
                <?php elseif ($user): ?>
                    <p class="row-meta"><em>Only buyer accounts can place orders.</em></p>
                <?php else: ?>
                    <p class="row-meta"><em><a href="/login.php">Sign in</a> as a buyer to place an order.</em></p>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php require __DIR__ . '/../includes/partials/footer.php'; ?>