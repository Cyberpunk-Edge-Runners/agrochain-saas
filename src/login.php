<?php
// src/login.php
//
// GET  -> show the login form
// POST -> look up the user by email, verify the password, start a session

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/db.php';

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — AgroChain</title>
</head>
<body>
    <h1>Sign In</h1>

    <?php if ($error): ?>
        <p style="color:red;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="POST" action="/login.php">
        <label>Email
            <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </label><br>

        <label>Password
            <input type="password" name="password" required>
        </label><br>

        <button type="submit">Sign In</button>
    </form>

    <p>Don't have an account? <a href="/register.php">Create one</a></p>
</body>
</html>