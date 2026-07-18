<?php
// src/index.php
//
// The PUBLIC marketplace — no login required. Anyone (buyers, or guests
// who haven't registered yet) can browse every crop listing here.

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/db.php';

$user = currentUser();

// order.php redirects back here with ?error=... when something goes wrong
// placing an order (e.g. "only 5 bags are available"). $_GET is PHP's
// array of URL query-string parameters — this reads that value if it's
// present, or gives us null if the page was loaded normally with no
// error to show.
$orderError = $_GET['error'] ?? null;

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
        <?php if ($orderError): ?>
            <!-- htmlspecialchars() here is important even though we
                 control what order.php puts into this URL — it's still
                 user-influenced (the quantity number can end up embedded
                 in the message), so we escape it the same as any other
                 output to prevent it being interpreted as HTML. -->
            <p style="color:red;"><?= htmlspecialchars($orderError) ?></p>
        <?php endif; ?>

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

                        <?php
                        // This ternary-like if/elseif/else decides what to
                        // show under each listing based on WHO is looking:
                        //   - a logged-in buyer gets an actual order form
                        //   - anyone else logged in (farmer/driver) gets a
                        //     note explaining ordering is buyer-only
                        //   - a guest (not logged in at all) gets a
                        //     prompt to sign in, since we can't let an
                        //     anonymous visitor place an order (there'd
                        //     be no buyer_id to attach it to)
                        ?>
                        <?php if ($user && $user['role'] === 'buyer'): ?>
                            <br>
                            <form method="POST" action="/order.php">
                                <!-- A hidden field carries the product_id
                                     along with the form — the buyer never
                                     sees or edits this, it's just how
                                     order.php knows WHICH listing this
                                     order is for. -->
                                <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">

                                <label>
                                    Bags:
                                    <!-- max is set to however many bags
                                         are actually left, so the browser
                                         itself nudges the buyer toward a
                                         valid number — though remember
                                         this is just UX. order.php
                                         re-checks this server-side too,
                                         since a browser-level max="" is
                                         trivially bypassable. -->
                                    <input type="number" name="quantity_bags" min="1"
                                           max="<?= (int) $product['quantity_bags'] ?>" required>
                                </label>

                                <button type="submit">Order This</button>
                            </form>
                        <?php elseif ($user): ?>
                            <p><em>Only buyer accounts can place orders.</em></p>
                        <?php else: ?>
                            <p><em><a href="/login.php">Sign in</a> as a buyer to place an order.</em></p>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </main>
</body>
</html>