<?php
// public/login.php
//
// GET  -> show the login form
// POST -> look up the user by email, verify the password, start a session

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter both your email and password.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // password_verify() re-hashes the submitted password using the same
        // salt/cost stored inside $user['password_hash'] and compares the
        // result. This is the correct counterpart to password_hash() in
        // register.php — never compare passwords with `===`.
        //
        // Deliberately vague error message: we say "Invalid email or
        // password" whether the email doesn't exist OR the password was
        // wrong. Saying "no account with that email" instead would let an
        // attacker enumerate which emails are registered.
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user'] = [
                'id'        => $user['id'],
                'tenant_id' => $user['tenant_id'], // may be null — that's fine, buyers/drivers often have none
                'name'      => $user['name'],
                'email'     => $user['email'],
                'role'      => $user['role'],
            ];
            header("Location: /dashboard.php");
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    }
}

$pageTitle = 'Sign In';
require __DIR__ . '/../includes/partials/header.php';
?>

<div class="auth-screen">
    <div class="ticket auth-card">
        <h1>Sign In</h1>
        <p class="auth-subtitle">Access your AgroChain account</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="/login.php">
            <div class="field">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" required
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block">Sign In</button>
        </form>

        <p class="auth-footer">Don't have an account? <a href="/register.php">Create one</a></p>
    </div>
</div>

<?php require __DIR__ . '/../includes/partials/footer.php'; ?>