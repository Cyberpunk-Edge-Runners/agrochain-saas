<?php
// src/register.php
//
// GET  -> show the signup form
// POST -> validate input, hash the password, insert the new user

require_once __DIR__ . '/includes/auth.php'; // gives us session_start() etc.
require_once __DIR__ . '/db.php';            // gives us $pdo

$error = '';

// Load the real list of co-ops from the DB to populate the dropdown below.
$tenants = $pdo->query("SELECT id, name FROM tenants ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name      = trim($_POST['name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $role      = $_POST['role'] ?? '';
    $tenant_id = $_POST['tenant_id'] ?? '';

    $validRoles = ['farmer', 'buyer', 'driver'];
    $validTenantIds = array_column($tenants, 'id');

    // A tenant represents a real co-op (e.g. "Volta Farmers Co-op"). Both
    // farmers AND drivers genuinely belong to one — a co-op has its own
    // farmers AND its own delivery drivers. This matters beyond just
    // record-keeping: when a farmer assigns a driver to deliver an order
    // (orders.php), they can only pick from drivers in THEIR OWN tenant —
    // that's the trust boundary. A driver with no tenant would never be
    // assignable by anyone, so this has to be required, not optional.
    //
    // Buyers are the one role that's genuinely NOT a co-op member — they're
    // an outside party purchasing FROM the marketplace. So the field is
    // required for farmer/driver, and not even shown for buyer.
    $tenantRequired = in_array($role, ['farmer', 'driver'], true);

    if ($name === '' || $email === '' || $password === '') {
        $error = 'Please fill in every field.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'That doesn\'t look like a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif (!in_array($role, $validRoles, true)) {
        $error = 'Please choose a valid role.';
    } elseif ($tenantRequired && $tenant_id === '') {
        $error = 'Please choose your co-op — required for farmer and driver accounts.';
    } elseif ($tenant_id !== '' && !in_array((int) $tenant_id, $validTenantIds, true)) {
        // Someone selected/submitted a tenant that doesn't actually exist —
        // whether that's tampering with the form or a stale dropdown, don't
        // let an invalid tenant_id reach the INSERT.
        $error = 'Please choose a valid co-op.';
    } else {
        // Buyers never get a tenant, even if one somehow arrived in the
        // POST data (e.g. via a tampered request) — force it to null
        // rather than trusting the submitted value for that role.
        $tenantIdForInsert = $tenantRequired && $tenant_id !== '' ? (int) $tenant_id : null;

        try {
            $hash = password_hash($password, PASSWORD_BCRYPT);

            $stmt = $pdo->prepare(
                "INSERT INTO users (tenant_id, name, email, role, password_hash) VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->execute([$tenantIdForInsert, $name, $email, $role, $hash]);

            $_SESSION['user'] = [
                'id'        => $pdo->lastInsertId(),
                'tenant_id' => $tenantIdForInsert,
                'name'      => $name,
                'email'     => $email,
                'role'      => $role,
            ];

            header("Location: /dashboard.php");
            exit;

        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $error = 'An account with that email already exists.';
            } else {
                error_log($e->getMessage());
                $error = 'Something went wrong creating your account. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account — AgroChain</title>
</head>
<body>
    <h1>Create an AgroChain Account</h1>

    <?php if ($error): ?>
        <p style="color:red;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="POST" action="/register.php">
        <label>Full Name
            <input type="text" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
        </label><br>

        <label>Email
            <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </label><br>

        <label>Password
            <input type="password" name="password" required minlength="8">
        </label><br>

        <label>I am a...
            <select name="role" id="role" required onchange="toggleTenantField()">
                <option value="">-- Select --</option>
                <option value="farmer">Farmer</option>
                <option value="buyer">Buyer</option>
                <option value="driver">Driver</option>
            </select>
        </label><br>

        <!-- This whole block starts hidden (style="display:none") and
             only gets shown by JS when role is farmer or driver. Buyers
             never see this field at all now, not even as "optional" —
             it genuinely doesn't apply to them. -->
        <div id="tenant-field" style="display:none;">
            <label>Co-op / Organization
                <select name="tenant_id" id="tenant_id">
                    <option value="">-- Select --</option>
                    <?php foreach ($tenants as $tenant): ?>
                        <option value="<?= (int) $tenant['id'] ?>">
                            <?= htmlspecialchars($tenant['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div><br>

        <button type="submit">Create Account</button>
    </form>

    <p>Already have an account? <a href="/login.php">Sign in</a></p>

    <script>
        // Progressive-enhancement UX only — the REAL enforcement is the
        // server-side PHP check above ($tenantRequired), which runs
        // regardless of whether JS executed at all. Anyone can disable
        // JS or edit the DOM before submitting, so this script existing
        // is purely about not showing/requiring a field that doesn't
        // apply to the role someone picked — it is never the actual
        // security boundary.
        function toggleTenantField() {
            const role = document.getElementById('role').value;
            const field = document.getElementById('tenant-field');
            const tenantSelect = document.getElementById('tenant_id');

            if (role === 'farmer' || role === 'driver') {
                field.style.display = 'block';
                tenantSelect.required = true;
            } else {
                field.style.display = 'none';
                tenantSelect.required = false;
                tenantSelect.value = ''; // clear any prior selection if they switch to buyer
            }
        }
    </script>
</body>
</html>