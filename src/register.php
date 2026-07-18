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

    // Only farmers actually belong to a co-op — a tenant represents a real
    // organization (e.g. "Volta Farmers Co-op"), and buyers/drivers aren't
    // members of one just because they use the marketplace. So the co-op
    // field is required ONLY when role=farmer; for everyone else it's
    // optional and gets stored as NULL if left blank.
    $tenantRequired = ($role === 'farmer');

    if ($name === '' || $email === '' || $password === '') {
        $error = 'Please fill in every field.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'That doesn\'t look like a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif (!in_array($role, $validRoles, true)) {
        $error = 'Please choose a valid role.';
    } elseif ($tenantRequired && $tenant_id === '') {
        $error = 'Please choose your co-op — required for farmer accounts.';
    } elseif ($tenant_id !== '' && !in_array((int) $tenant_id, $validTenantIds, true)) {
        // Someone selected/submitted a tenant that doesn't actually exist —
        // whether that's tampering with the form or a stale dropdown, don't
        // let an invalid tenant_id reach the INSERT.
        $error = 'Please choose a valid co-op.';
    } else {
        // Normalize: empty string -> real NULL for the database, not the
        // literal string "" (which would fail the FOREIGN KEY silently
        // miscast, or just be wrong data).
        $tenantIdForInsert = $tenant_id === '' ? null : (int) $tenant_id;

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

        <div id="tenant-field">
            <label>Co-op / Organization <span id="tenant-hint">(required for farmers)</span>
                <select name="tenant_id" id="tenant_id">
                    <option value="">-- None / Not Applicable --</option>
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
        // Small progressive-enhancement touch: mark the co-op field as
        // browser-required only when "Farmer" is picked, so buyers/drivers
        // aren't blocked by a required attribute that doesn't apply to
        // them. The REAL enforcement is server-side in the PHP above —
        // this JS is just UX, never trusted as the actual security check
        // (anyone can disable JS or edit the DOM before submitting).
        function toggleTenantField() {
            const role = document.getElementById('role').value;
            const tenantSelect = document.getElementById('tenant_id');
            const hint = document.getElementById('tenant-hint');

            if (role === 'farmer') {
                tenantSelect.required = true;
                hint.textContent = '(required for farmers)';
            } else {
                tenantSelect.required = false;
                hint.textContent = '(optional)';
            }
        }
    </script>
</body>
</html>