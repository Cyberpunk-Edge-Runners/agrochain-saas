<?php
// src/orders.php
//
// GET  -> show orders, but WHICH orders and WHAT actions depend on role:
//           - buyer:  their own order history, read-only
//           - farmer: orders on their listings — confirm/cancel a pending
//                     one, or assign a driver to a confirmed one
//           - driver: orders assigned to THEM — mark one delivered
// POST -> role-specific actions, each one ownership-checked so nobody can
//         act on an order that isn't theirs to act on

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/db.php';

// All three roles have a legitimate reason to be here — just very
// different things once they land.
$user = requireAnyRole(['buyer', 'farmer', 'driver']);

// --- POST handling: farmers confirm/cancel/assign, drivers mark delivered ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? '';
    $orderId = $_POST['order_id'] ?? '';

    // Every action needs a real numeric order_id — bail out early if
    // that's not even the right shape, before touching the database.
    if (!ctype_digit((string) $orderId)) {
        header('Location: /orders.php');
        exit;
    }

    if ($user['role'] === 'farmer' && in_array($action, ['confirm', 'cancel'], true)) {
        $newStatus = $action === 'confirm' ? 'confirmed' : 'cancelled';

        // beginTransaction() lives INSIDE try now, not before it — same
        // bug, same fix as order.php: called before try{}, an exception
        // from it would be completely uncaught, crashing the request
        // instead of being logged and handled like every other failure.
        try {
            $pdo->beginTransaction();

            // Same IDOR-safe pattern as before: the subquery on farmer_id
            // means this can only ever match an order on one of THIS
            // farmer's own listings. "AND status = 'pending'" means it
            // can only fire once — a resubmitted/replayed request finds
            // nothing left in 'pending' to match, and does nothing.
            $stmt = $pdo->prepare(
                'UPDATE orders
                 SET status = ?
                 WHERE id = ?
                   AND status = \'pending\'
                   AND product_id IN (SELECT id FROM products WHERE farmer_id = ?)'
            );
            $stmt->execute([$newStatus, (int) $orderId, $user['id']]);

            // Cancelling gives the reserved stock back to the listing —
            // same logic as before, just re-confirmed here since this is
            // a fresh rewrite of this file.
            if ($stmt->rowCount() === 1 && $newStatus === 'cancelled') {
                $orderStmt = $pdo->prepare('SELECT product_id, quantity_bags FROM orders WHERE id = ?');
                $orderStmt->execute([(int) $orderId]);
                $cancelled = $orderStmt->fetch();

                $restoreStmt = $pdo->prepare(
                    'UPDATE products SET quantity_bags = quantity_bags + ? WHERE id = ?'
                );
                $restoreStmt->execute([(int) $cancelled['quantity_bags'], (int) $cancelled['product_id']]);
            }

            $pdo->commit();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log($e->getMessage());
        }

    } elseif ($user['role'] === 'farmer' && $action === 'assign_driver') {
        $driverId = $_POST['driver_id'] ?? '';

        if (ctype_digit((string) $driverId)) {
            // This is the actual trust boundary the co-op field exists
            // for now: the driver being assigned must be role='driver'
            // AND belong to the SAME tenant as this farmer. Without the
            // tenant_id match, a farmer could assign literally any
            // driver on the whole platform — including ones with no
            // relationship to their co-op at all. We re-check this here
            // server-side rather than trusting the dropdown, the same
            // way we never trust any submitted ID without verifying it
            // against the database first.
            $driverCheck = $pdo->prepare(
                'SELECT id FROM users WHERE id = ? AND role = \'driver\' AND tenant_id = ?'
            );
            $driverCheck->execute([(int) $driverId, $user['tenant_id']]);

            if ($driverCheck->fetch()) {
                // The order itself must be:
                //   - on one of THIS farmer's own listings (IDOR guard)
                //   - currently 'confirmed' (can't assign a driver to a
                //     still-pending or already-cancelled/delivered order)
                // Moving straight from 'confirmed' to 'assigned' in one
                // UPDATE also sets driver_id at the same time.
                $stmt = $pdo->prepare(
                    'UPDATE orders
                     SET status = \'assigned\', driver_id = ?
                     WHERE id = ?
                       AND status = \'confirmed\'
                       AND product_id IN (SELECT id FROM products WHERE farmer_id = ?)'
                );
                $stmt->execute([(int) $driverId, (int) $orderId, $user['id']]);
            }
            // If the driver check failed (wrong tenant, wrong role, or
            // doesn't exist), we silently do nothing — same "don't
            // confirm or deny what exists" principle as the ownership
            // checks elsewhere in this file.
        }

    } elseif ($user['role'] === 'driver' && $action === 'deliver') {
        // A driver can only mark delivered an order that's actually
        // assigned to THEM specifically (driver_id = their own id) and
        // still in the 'assigned' state — this is the ownership check
        // for this role, same pattern as farmer_id checks elsewhere.
        $stmt = $pdo->prepare(
            'UPDATE orders
             SET status = \'delivered\'
             WHERE id = ?
               AND status = \'assigned\'
               AND driver_id = ?'
        );
        $stmt->execute([(int) $orderId, $user['id']]);
    }

    // Post/Redirect/Get — same reason as everywhere else in this app:
    // stops a page refresh from re-submitting the same action twice.
    header('Location: /orders.php');
    exit;
}

// --- GET: fetch and display, scoped and shaped differently per role ---

if ($user['role'] === 'buyer') {
    $stmt = $pdo->prepare(
        'SELECT o.*, p.crop_type, p.region, u.name AS farmer_name, d.name AS driver_name
         FROM orders o
         JOIN products p ON o.product_id = p.id
         JOIN users u ON p.farmer_id = u.id
         LEFT JOIN users d ON o.driver_id = d.id
         WHERE o.buyer_id = ?
         ORDER BY o.created_at DESC'
    );
    $stmt->execute([$user['id']]);
    $orders = $stmt->fetchAll();

} elseif ($user['role'] === 'farmer') {
    $stmt = $pdo->prepare(
        'SELECT o.*, p.crop_type, u.name AS buyer_name, d.name AS driver_name
         FROM orders o
         JOIN products p ON o.product_id = p.id
         JOIN users u ON o.buyer_id = u.id
         LEFT JOIN users d ON o.driver_id = d.id
         WHERE p.farmer_id = ?
         ORDER BY o.created_at DESC'
    );
    $stmt->execute([$user['id']]);
    $orders = $stmt->fetchAll();

    // Only fetch the driver list for orders that are actually 'confirmed'
    // and driverless — that's the only state the assignment dropdown
    // ever needs to appear for. Scoped to this farmer's own tenant, same
    // as the assignment check above.
    $driverStmt = $pdo->prepare(
        'SELECT id, name FROM users WHERE role = \'driver\' AND tenant_id = ? ORDER BY name'
    );
    $driverStmt->execute([$user['tenant_id']]);
    $availableDrivers = $driverStmt->fetchAll();

} else { // driver
    $stmt = $pdo->prepare(
        'SELECT o.*, p.crop_type, p.region, u.name AS buyer_name
         FROM orders o
         JOIN products p ON o.product_id = p.id
         JOIN users u ON o.buyer_id = u.id
         WHERE o.driver_id = ?
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
    <?php
    $titles = [
        'buyer'  => 'My Orders',
        'farmer' => 'Orders On My Listings',
        'driver' => 'My Delivery Queue',
    ];
    ?>
    <h1><?= htmlspecialchars($titles[$user['role']]) ?></h1>

    <?php if (empty($orders)): ?>
        <p><em>No orders yet.</em></p>
    <?php else: ?>
        <ul>
            <?php foreach ($orders as $order): ?>
                <li>
                    <strong><?= (int) $order['quantity_bags'] ?> bags of <?= htmlspecialchars($order['crop_type']) ?></strong>
                    — Status: <?= htmlspecialchars($order['status']) ?>
                    <br>

                    <?php if ($user['role'] === 'buyer'): ?>
                        <small>
                            From <?= htmlspecialchars($order['farmer_name']) ?> ·
                            Ordered <?= htmlspecialchars($order['created_at']) ?>
                            <?php if ($order['driver_name']): ?>
                                · Driver: <?= htmlspecialchars($order['driver_name']) ?>
                            <?php endif; ?>
                        </small>

                    <?php elseif ($user['role'] === 'farmer'): ?>
                        <small>
                            From buyer: <?= htmlspecialchars($order['buyer_name']) ?> ·
                            Ordered <?= htmlspecialchars($order['created_at']) ?>
                            <?php if ($order['driver_name']): ?>
                                · Driver: <?= htmlspecialchars($order['driver_name']) ?>
                            <?php endif; ?>
                        </small>

                        <?php if ($order['status'] === 'pending'): ?>
                            <br>
                            <form method="POST" action="/orders.php" style="display:inline;">
                                <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                                <input type="hidden" name="action" value="confirm">
                                <button type="submit">Confirm Order</button>
                            </form>
                            <form method="POST" action="/orders.php" style="display:inline;">
                                <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                                <input type="hidden" name="action" value="cancel">
                                <button type="submit">Cancel Order</button>
                            </form>

                        <?php elseif ($order['status'] === 'confirmed'): ?>
                            <br>
                            <?php if (empty($availableDrivers)): ?>
                                <em>No drivers registered under your co-op yet.</em>
                            <?php else: ?>
                                <form method="POST" action="/orders.php" style="display:inline;">
                                    <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                                    <input type="hidden" name="action" value="assign_driver">
                                    <select name="driver_id" required>
                                        <option value="">-- Choose a driver --</option>
                                        <?php foreach ($availableDrivers as $driver): ?>
                                            <option value="<?= (int) $driver['id'] ?>">
                                                <?= htmlspecialchars($driver['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit">Assign Driver</button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>

                    <?php else: // driver ?>
                        <small>
                            Deliver to buyer: <?= htmlspecialchars($order['buyer_name']) ?> ·
                            Pickup region: <?= htmlspecialchars($order['region']) ?>
                        </small>

                        <?php if ($order['status'] === 'assigned'): ?>
                            <br>
                            <form method="POST" action="/orders.php" style="display:inline;"
                                  onsubmit="return confirm('Confirm the goods have been delivered?');">
                                <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                                <input type="hidden" name="action" value="deliver">
                                <button type="submit">Mark Delivered</button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <p><a href="/dashboard.php">&larr; Back to Dashboard</a></p>
</body>
</html>