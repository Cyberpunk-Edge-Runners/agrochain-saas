<?php
// src/products.php
//
// Farmer-only page: publish new crop listings, and view/delete your own.
// GET             -> show the form + "My Listings"
// POST action=create -> insert a new product row
// POST action=delete -> remove one of YOUR OWN listings

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/db.php';

$user = requireRole('farmer');

// $error only ever gets used for VALIDATION failures (bad input) — those
// don't redirect, because we want to re-show the form so the farmer isn't
// forced to retype everything just to fix one field. This is safe from
// the double-submit problem because a validation failure never actually
// wrote anything to the database — refreshing just re-shows the same
// harmless error, nothing gets duplicated.
//
// $success used to work the same way (no redirect) — that was the actual
// bug. Now, every path that DOES write to the database redirects
// afterward and uses flashSet()/flashGet() (see includes/auth.php) to
// carry the success message across that redirect instead.
$error = '';
$success = flashGet('success');

$ghanaRegions = [
    'Greater Accra', 'Ashanti', 'Eastern', 'Western', 'Central',
    'Volta', 'Northern', 'Upper East', 'Upper West', 'Bono',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $cropType = trim($_POST['crop_type'] ?? '');
        $quantity = $_POST['quantity_bags'] ?? '';
        $price    = $_POST['price_per_bag'] ?? '';
        $region   = $_POST['region'] ?? '';

        if ($cropType === '' || $region === '' || $quantity === '' || $price === '') {
            $error = 'Please fill in every field.';
        } elseif (!ctype_digit((string) $quantity) || (int) $quantity <= 0) {
            $error = 'Quantity must be a positive whole number.';
        } elseif (!is_numeric($price) || (float) $price <= 0) {
            $error = 'Price must be a positive number.';
        } elseif (!in_array($region, $ghanaRegions, true)) {
            $error = 'Please choose a valid region.';
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO products (farmer_id, crop_type, quantity_bags, price_per_bag, region) VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->execute([$user['id'], $cropType, (int) $quantity, (float) $price, $region]);

            // The write succeeded — queue the success message, then
            // redirect to a plain GET of this same page. The browser's
            // "last request" is now that GET, not the POST, so a refresh
            // from here on just reloads the page instead of re-publishing
            // the listing.
            flashSet('success', 'Listing published.');
            header('Location: /products.php');
            exit;
        }

    } elseif ($action === 'delete') {
        $productId = $_POST['product_id'] ?? '';

        // "AND farmer_id = ?" is doing real security work here, not just
        // filtering — without it, this is an IDOR: any farmer could delete
        // any other farmer's listing by changing product_id in the form.
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ? AND farmer_id = ?");
        $stmt->execute([$productId, $user['id']]);

        flashSet('success', $stmt->rowCount() > 0 ? 'Listing removed.' : 'Listing not found.');
        header('Location: /products.php');
        exit;
    }
}

$stmt = $pdo->prepare("SELECT * FROM products WHERE farmer_id = ? ORDER BY created_at DESC");
$stmt->execute([$user['id']]);
$myProducts = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Listings — AgroChain</title>
</head>
<body>
    <h1>Manage Your Listings</h1>

    <?php if ($error): ?>
        <p style="color:red;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <?php if ($success): ?>
        <p style="color:green;"><?= htmlspecialchars($success) ?></p>
    <?php endif; ?>

    <h2>Publish a New Listing</h2>
    <form method="POST" action="/products.php">
        <input type="hidden" name="action" value="create">

        <label>Crop Type
            <input type="text" name="crop_type" placeholder="e.g., White Maize, Yam, Cocoa" required>
        </label><br>

        <label>Quantity (Bags)
            <input type="number" name="quantity_bags" min="1" required>
        </label><br>

        <label>Price per Bag (GHS)
            <input type="number" name="price_per_bag" min="0.01" step="0.01" required>
        </label><br>

        <label>Region
            <select name="region" required>
                <option value="">-- Select --</option>
                <?php foreach ($ghanaRegions as $r): ?>
                    <option value="<?= htmlspecialchars($r) ?>"><?= htmlspecialchars($r) ?></option>
                <?php endforeach; ?>
            </select>
        </label><br>

        <button type="submit">Publish Listing</button>
    </form>

    <h2>My Listings</h2>
    <?php if (empty($myProducts)): ?>
        <p><em>You haven't listed any produce yet.</em></p>
    <?php else: ?>
        <ul>
            <?php foreach ($myProducts as $product): ?>
                <li>
                    <strong><?= htmlspecialchars($product['crop_type']) ?></strong>
                    — <?= (int) $product['quantity_bags'] ?> bags
                    @ GHS <?= htmlspecialchars($product['price_per_bag']) ?>/bag
                    (<?= htmlspecialchars($product['region']) ?>)

                    <form method="POST" action="/products.php" style="display:inline;"
                          onsubmit="return confirm('Remove this listing?');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                        <button type="submit">Remove</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <p><a href="/dashboard.php">&larr; Back to Dashboard</a></p>
</body>
</html>