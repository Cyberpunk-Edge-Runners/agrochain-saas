<?php
// src/order.php
//
// POST-only endpoint: a buyer submits the "Order This" form on index.php,
// it lands here, we validate it and insert a row into the orders table.
// There's no GET view for this file on purpose — nobody visits
// /order.php directly, it only ever receives a form submission.

// Pulls in session_start(), currentUser(), requireLogin(), requireRole(),
// requireAnyRole() — everything this file needs for "who is this and are
// they allowed to be here."
require_once __DIR__ . '/../includes/auth.php';

// Pulls in $pdo, our shared database connection object.
require_once __DIR__ . '/../includes/db.php';

// requireRole('buyer') does three things in one call:
//   1. Calls requireLogin() internally, which redirects guests to
//      /login.php and stops the script right there (via exit).
//   2. If they ARE logged in, checks their role — if it's not 'buyer',
//      sends a 403 and stops the script.
//   3. If they ARE a buyer, returns their session array so we can use it
//      below as $user (id, tenant_id, name, email, role).
// This is the ONLY authorization check we need for "can this person place
// an order at all" — everything after this line only runs for real,
// logged-in buyers.
$user = requireRole('buyer');

// $_SERVER['REQUEST_METHOD'] tells us whether this request was a GET
// (someone just navigating to the URL) or POST (an actual form
// submission). Since this file is POST-only by design, if someone
// navigates here directly with a GET request, we don't want to run any
// order-creation logic — we just bounce them back to the marketplace.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // header("Location: ...") sends an HTTP redirect response instead of
    // rendering a page. exit immediately after is important — without it,
    // PHP would keep executing the rest of the script even after sending
    // the redirect header, which could run code we don't want to run.
    header('Location: /index.php');
    exit;
}

// $_POST is PHP's built-in array of form field values from the POST body.
// The `?? ''` after each one is the null-coalescing operator: if the key
// doesn't exist in $_POST at all (e.g. someone crafted a request missing
// a field), we get an empty string back instead of a PHP warning about an
// undefined array key.
$productId = $_POST['product_id'] ?? '';
$quantity  = $_POST['quantity_bags'] ?? '';

// We'll build this variable up as we validate, and only insert into the
// database once every check below has passed. Starting it as null means
// "no error yet."
$error = null;

// ctype_digit() checks that a string contains ONLY digit characters (0-9).
// We cast $productId to (string) first because ctype_digit() expects a
// string argument, and $_POST values technically come through as strings
// already, but being explicit here protects against edge cases.
// Why check this at all: without it, a non-numeric product_id would cause
// a confusing SQL error further down instead of a clean message here.
if (!ctype_digit((string) $productId)) {
    $error = 'Invalid product.';

// Same idea for quantity: it must be digits only, AND it must be greater
// than zero — "0 bags" or "-5 bags" isn't a real order.
} elseif (!ctype_digit((string) $quantity) || (int) $quantity <= 0) {
    $error = 'Please enter a valid quantity.';
}

// Only bother querying the database if the basic input shape already
// passed — no point checking "does this product exist" against a
// product_id we already know is garbage.
if ($error === null) {
    // --- WHY THIS IS WRAPPED IN A TRANSACTION ---
    //
    // Imagine a listing has 5 bags left, and two buyers both click
    // "Order 5 bags" within the same second. WITHOUT what's below, both
    // requests would:
    //   1. SELECT the product, see quantity_bags = 5
    //   2. Both think "5 >= 5, that's fine"
    //   3. Both INSERT an order for 5 bags
    // Now 10 bags are "sold" from a listing that only ever had 5 — the
    // farmer has been oversold, and there's no way to honor both orders.
    // This is a classic race condition: two things happening in that gap
    // between "read the value" and "act on the value."
    //
    // beginTransaction() + FOR UPDATE (below) fixes this by making the
    // second request physically WAIT until the first one finishes
    // (commits or rolls back) before it's even allowed to read the row.
    // So the second buyer's SELECT will correctly see the UPDATED
    // quantity_bags (0, after the first order took all 5) instead of the
    // stale value of 5 that was true before the first order happened.
    // beginTransaction() itself now lives INSIDE the try block below,
    // not before it. Originally it was called before try{}, which meant
    // if it ever threw a PDOException (PDO throws on transaction/
    // connection problems, since db.php sets PDO::ATTR_ERRMODE to
    // ERRMODE_EXCEPTION), that exception was completely uncaught — it
    // would crash straight past our error handling AND past the
    // redirect at the bottom of this file, instead of being logged and
    // shown as a clean message like every other failure here.
    try {
        $pdo->beginTransaction();

        // "FOR UPDATE" is the part that actually creates the lock — it
        // tells MySQL "I'm about to change this row, don't let anyone
        // else read-and-plan-to-change it until my transaction ends."
        // Without FOR UPDATE, this would just be a normal SELECT, and the
        // race condition described above would still be possible.
        $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ? FOR UPDATE');
        $stmt->execute([(int) $productId]);
        $product = $stmt->fetch();

        if (!$product) {
            // The product_id was a valid number, but no row in the
            // database actually has that id.
            $error = 'That listing no longer exists.';

        } elseif ((int) $quantity > (int) $product['quantity_bags']) {
            // Thanks to the lock above, this comparison is now checking
            // the TRUE current stock, not a possibly-stale value read
            // before some other concurrent order changed it.
            $error = 'Only ' . (int) $product['quantity_bags'] . ' bags are available.';

        } else {
            // Everything checks out — reduce the listing's available
            // stock by however many bags this order is taking...
            $updateStmt = $pdo->prepare(
                'UPDATE products SET quantity_bags = quantity_bags - ? WHERE id = ?'
            );
            $updateStmt->execute([(int) $quantity, (int) $productId]);

            // ...and record the order itself. status starts as 'pending'
            // — only the farmer confirming/cancelling it later (in
            // orders.php) changes that.
            $insertStmt = $pdo->prepare(
                'INSERT INTO orders (product_id, buyer_id, quantity_bags, status) VALUES (?, ?, ?, ?)'
            );
            $insertStmt->execute([(int) $productId, $user['id'], (int) $quantity, 'pending']);
        }

        if ($error === null) {
            // commit() releases the lock from FOR UPDATE and makes both
            // the quantity reduction AND the new order permanent
            // together, as a single all-or-nothing unit. This matters:
            // if we updated quantity_bags but the app crashed before the
            // INSERT ran, we'd have "lost" stock with no order to show
            // for it. A transaction guarantees both happen, or neither does.
            $pdo->commit();

            header('Location: /orders.php');
            exit;
        } else {
            // Validation failed AFTER we started the transaction (e.g.
            // not enough stock) — roll back so the lock is released and
            // nothing partial gets saved. Since we never actually ran the
            // UPDATE/INSERT in this branch, rollback here is mostly about
            // releasing the lock promptly rather than undoing writes.
            $pdo->rollBack();
        }

    } catch (PDOException $e) {
        // inTransaction() check matters here specifically because
        // beginTransaction() now lives inside this try block — if THAT
        // call is what failed, no transaction was ever actually started,
        // and calling rollBack() on a connection with no active
        // transaction throws its own new PDOException ("There is no
        // active transaction"). That second exception would be thrown
        // from inside this catch block, uncaught, defeating the entire
        // point of having a catch block here.
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log($e->getMessage());
        $error = 'Something went wrong placing your order. Please try again.';
    }
}

// If we get all the way down here, $error is definitely set to something
// (every success path above already did header()+exit and never reached
// this line). We redirect back to the marketplace with the error message
// attached as a URL query parameter, so index.php can read it and display
// it to the buyer.
//
// urlencode() is important here: $error might contain spaces, punctuation,
// or other characters that aren't safe to put directly into a URL —
// urlencode() converts them into a safe %XX-escaped form.
header('Location: /index.php?error=' . urlencode($error));
exit;