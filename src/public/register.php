<?php
// src/register.php
//
// GET  -> show the signup form
// POST -> validate input, hash the password, insert the new user

require_once __DIR__ . '/../includes/bootstrap.php'; // gives us session_start(), $pdo

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

$pageTitle = 'Create Account';
require PARTIALS_PATH . '/header.php';
?>

<div class="auth-screen">
    <div class="ticket auth-card">
        <h1>Create Account</h1>
        <p class="auth-subtitle">Join the AgroChain marketplace</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="/register.php">
            <div class="field">
                <label for="name">Full Name</label>
                <input id="name" type="text" name="name" required
                       value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
            </div>

            <div class="field">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" required
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" required minlength="8">
            </div>

            <div class="field">
                <label for="role">I am a...</label>
                <select id="role" name="role" required onchange="toggleTenantField()">
                    <option value="">-- Select --</option>
                    <option value="farmer">Farmer</option>
                    <option value="buyer">Buyer</option>
                    <option value="driver">Driver</option>
                </select>
            </div>

            <!-- Starts hidden, only shown by JS when role is farmer or
                 driver — buyers never see this field, it doesn't apply. -->
            <div class="field" id="tenant-field" style="display:none;">
                <label for="tenant_id">Co-op / Organization</label>
                <select id="tenant_id" name="tenant_id">
                    <option value="">-- Select --</option>
                    <?php foreach ($tenants as $tenant): ?>
                        <option value="<?= (int) $tenant['id'] ?>">
                            <?= htmlspecialchars($tenant['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn btn-primary btn-block">Create Account</button>
        </form>

        <p class="auth-footer">Already have an account? <a href="/login.php">Sign in</a></p>
    </div>
</div>

<script>
    // Progressive-enhancement UX only — the REAL enforcement is the
    // server-side PHP check above ($tenantRequired), which runs
    // regardless of whether JS executed at all.
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
            tenantSelect.value = '';
        }
    }
</script>

<?php require PARTIALS_PATH . '/footer.php'; ?>