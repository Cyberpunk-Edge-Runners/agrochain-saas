<?php
// src/orders.php
//
// GET  -> show orders, but WHICH orders depends on who's looking:
//           - a buyer sees orders THEY placed
//           - a farmer sees orders placed ON THEIR listings, and can
//             confirm or cancel each one
// POST -> only farmers can POST here, to confirm/cancel an order

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/db.php';

// requireAnyRole() (rather than requireRole()) because BOTH buyers and
// farmers have a legitimate reason to be on this page — just seeing
// different things once they're here. A driver or a guest has no reason
// to be here at all, so they're excluded.
$user = requireAnyRole(['buyer', 'farmer']);

// This whole block only runs for farmers, and only for POST requests —
// buyers never submit a form on this page, they only ever view.
if ($user['role'] === 'farmer' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // $_POST['order_id'] is which order row the farmer clicked
    // confirm/cancel on. $_POST['new_status'] is which button they
    // clicked — we set this via two separate submit buttons in the HTML
    // below, each with its own value.
    $orderId   = $_POST['order_id'] ?? '';
    $newStatus = $_POST['new_status'] ?? '';

    // We only ever allow moving an order to 'confirmed' or 'cancelled'
    // from this form — never back to 'pending' (that's the automatic
    // starting state from order.php, not something to manually set).
    // in_array(..., true) with the third argument true means STRICT
    // comparison — it checks type AND value, not just value, which
    // avoids odd PHP comparison quirks between different types.
    $allowedStatuses = ['confirmed', 'cancelled'];

    if (ctype_digit((string) $orderId) && in_array($newStatus, $allowedStatuses, true)) {
        // Wrapped in a transaction for the same reason as order.php: if
        // this is a cancellation, we're about to do TWO writes (update
        // the order's status, AND give the stock back to the product) —
        // a transaction guarantees both happen together, or neither does,
        // rather than risking one succeeding and the other failing.
        $pdo->beginTransaction();

        try {
            // Two things changed here versus before:
            //
            // 1. "AND status = 'pending'" is new. Without it, if a farmer
            //    double-clicked Cancel, or resubmitted an old form (e.g.
            //    via browser back button + resubmit), this UPDATE would
            //    match and run again — and if we're restoring stock on
            //    cancel below, that would give the stock back TWICE for
            //    one real cancellation. Requiring the CURRENT status to
            //    still be 'pending' means this can only ever fire once
            //    per order: after the first successful cancel, the row's
            //    status is 'cancelled', so a second identical request
            //    matches zero rows and does nothing.
            //
            // 2. The farmer_id ownership check (subquery) is unchanged
            //    from before — still the IDOR protection.
            $stmt = $pdo->prepare(
                'UPDATE orders
                 SET status = ?
                 WHERE id = ?
                   AND status = \'pending\'
                   AND product_id IN (SELECT id FROM products WHERE farmer_id = ?)'
            );
            $stmt->execute([$newStatus, (int) $orderId, $user['id']]);

            // rowCount() tells us how many rows the UPDATE actually
            // changed — 1 if this really was a genuine, first-time
            // pending -> (confirmed|cancelled) transition; 0 if nothing
            // matched (wrong order, not theirs, or already processed).
            // We only restore stock when we KNOW the transition really
            // just happened, never unconditionally.
            if ($stmt->rowCount() === 1 && $newStatus === 'cancelled') {
                // Look up how many bags this specific order held, and
                // which product it belongs to, so we know how much stock
                // to give back and to which listing.
                $orderStmt = $pdo->prepare('SELECT product_id, quantity_bags FROM orders WHERE id = ?');
                $orderStmt->execute([(int) $orderId]);
                $cancelledOrder = $orderStmt->fetch();

                // Add the bags back onto the listing's available stock.
                // This is the direct counterpart to the "quantity_bags -
                // ?" subtraction in order.php — cancelling an order
                // undoes exactly what placing it did.
                $restoreStmt = $pdo->prepare(
                    'UPDATE products SET quantity_bags = quantity_bags + ? WHERE id = ?'
                );
                $restoreStmt->execute([
                    (int) $cancelledOrder['quantity_bags'],
                    (int) $cancelledOrder['product_id'],
                ]);
            }

            // Confirming an order needs no stock change here — the bags
            // were already deducted from the listing back when the order
            // was first placed (in order.php). "Confirmed" just means the
            // farmer has acknowledged it; the stock was already reserved.

            $pdo->commit();

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log($e->getMessage());
            // We don't have a good way to show this error to the farmer
            // on this page (it's a redirect-based flow, not a form with
            // an error slot) — logging it server-side is the priority
            // here, so at least it's visible in `docker compose logs`.
        }
    }

    // After handling the POST, redirect back to this same page with a GET
    // request. This is the "Post/Redirect/Get" pattern — it stops the
    // browser from re-submitting the same status-change form again if the
    // user hits refresh on this page later.
    header('Location: /orders.php');
    exit;
}

// From here down, we're just fetching data to DISPLAY — no more writes.

if ($user['role'] === 'buyer') {
    // A buyer's view: every order THEY placed, joined with product info
    // so we can show what crop/farmer it was for, not just raw IDs.
    $stmt = $pdo->prepare(
        'SELECT o.*, p.crop_type, p.region, u.name AS farmer_name
         FROM orders o
         JOIN products p ON o.product_id = p.id
         JOIN users u ON p.farmer_id = u.id
         WHERE o.buyer_id = ?
         ORDER BY o.created_at DESC'
    );
    $stmt->execute([$user['id']]);
    $orders = $stmt->fetchAll();

} else {
    // A farmer's view: every order placed on ANY of their listings,
    // joined with the buyer's info so they know who's ordering.
    $stmt = $pdo->prepare(
        'SELECT o.*, p.crop_type, u.name AS buyer_name
         FROM orders o
         JOIN products p ON o.product_id = p.id
         WHERE p.farmer_id = ?
         ORDER BY o.created_at DESC'
    );
    $stmt->execute([$user['id']]);
    $orders = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders — AgroChain</title>
</head>
<body>
    <!-- The heading and empty-state text change based on role, so a
         buyer isn't looking at a page that says "Orders On Your
         Listings" when they've never listed anything. -->
    <h1><?= $user['role'] === 'buyer' ? 'My Orders' : 'Orders On My Listings' ?></h1>

    <?php if (empty($orders)): ?>
        <p><em><?= $user['role'] === 'buyer' ? 'You haven\'t placed any orders yet.' : 'No orders yet.' ?></em></p>
    <?php else: ?>
        <ul>
            <?php foreach ($orders as $order): ?>
                <li>
                    <strong><?= (int) $order['quantity_bags'] ?> bags of <?= htmlspecialchars($order['crop_type']) ?></strong>
                    — Status: <?= htmlspecialchars($order['status']) ?>
                    <br>

                    <?php if ($user['role'] === 'buyer'): ?>
                        <!-- Buyer view: show who they ordered from -->
                        <small>From <?= htmlspecialchars($order['farmer_name']) ?> · Ordered <?= htmlspecialchars($order['created_at']) ?></small>
                    <?php else: ?>
                        <!-- Farmer view: show who ordered from them -->
                        <small>From buyer: <?= htmlspecialchars($order['buyer_name']) ?> · Ordered <?= htmlspecialchars($order['created_at']) ?></small>
                    <?php endif; ?>

                    <?php
                    // Only farmers get action buttons, and only on orders
                    // that are still 'pending' — once an order is already
                    // confirmed or cancelled, there's nothing left to do
                    // to it from this screen.
                    ?>
                    <?php if ($user['role'] === 'farmer' && $order['status'] === 'pending'): ?>
                        <br>
                        <form method="POST" action="/orders.php" style="display:inline;">
                            <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                            <input type="hidden" name="new_status" value="confirmed">
                            <button type="submit">Confirm Order</button>
                        </form>
                        <form method="POST" action="/orders.php" style="display:inline;">
                            <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                            <input type="hidden" name="new_status" value="cancelled">
                            <button type="submit">Cancel Order</button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <p><a href="/dashboard.php">&larr; Back to Dashboard</a></p>
</body>
</html>