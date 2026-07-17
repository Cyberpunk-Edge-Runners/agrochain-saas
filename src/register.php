<?php
// src/register.php
//
// GET  -> show the signup form
// POST -> validate input, hash the password, insert the new user

require_once __DIR__ . '/includes/auth.php'; // gives us session_start() etc.
require_once __DIR__ . '/db.php';            // gives us $pdo

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // trim() strips accidental leading/trailing whitespace (very common
    // when people copy-paste an email address, for instance).
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? '';

    // Only accept roles that actually exist in the users.role ENUM.
    // Doing this check in PHP too (not just relying on the DB ENUM) means
    // we can show a friendly error instead of a raw SQL exception.
    $validRoles = ['farmer', 'buyer', 'driver'];

    if ($name === '' || $email === '' || $password === '') {
        $error = 'Please fill in every field.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'That doesn\'t look like a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif (!in_array($role, $validRoles, true)) {
        $error = 'Please choose a valid role.';
    } else {
        try {
            // password_hash() with PASSWORD_BCRYPT is the whole point here:
            // we never store the actual password anywhere, only a one-way
            // hash of it. Even if the users table leaked, an attacker still
            // can't read anyone's real password back out of password_hash.
            $hash = password_hash($password, PASSWORD_BCRYPT);

            $stmt = $pdo->prepare(
                "INSERT INTO users (name, email, role, password_hash) VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([$name, $email, $role, $hash]);

            // Log the new user straight in rather than making them submit
            // the login form immediately after — one less step of friction.
            $_SESSION['user'] = [
                'id'    => $pdo->lastInsertId(),
                'name'  => $name,
                'email' => $email,
                'role'  => $role,
            ];

            header("Location: /dashboard.php");
            exit;

        } catch (PDOException $e) {
            // Error code 23000 = integrity constraint violation — in this
            // table, that almost always means the email UNIQUE constraint
            // was hit (someone already registered with that email).
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
            <select name="role" required>
                <option value="">-- Select --</option>
                <option value="farmer">Farmer</option>
                <option value="buyer">Buyer</option>
                <option value="driver">Driver</option>
            </select>
        </label><br>

        <button type="submit">Create Account</button>
    </form>

    <p>Already have an account? <a href="/login.php">Sign in</a></p>
</body>
</html>