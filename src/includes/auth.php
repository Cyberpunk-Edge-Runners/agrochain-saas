<?php
// src/includes/auth.php
//
// Shared helpers for anything session/login related. Include this at the
// top of any page that needs to know who's logged in, or that needs to
// block access to guests.

// session_start() has to run before ANY HTML output on the page, which is
// why every page includes this file first, before printing anything.
session_start();

// Returns the logged-in user's data (id, name, email, role) as an array,
// or null if nobody's logged in. Use this instead of reaching into
// $_SESSION directly everywhere — if we ever change what we store in the
// session, there's only one place to update.
function currentUser(): ?array {
    return $_SESSION['user'] ?? null;
}

// Call this at the top of any page that should only be visible to logged-in
// users (e.g. the dashboard). Redirects guests straight to the login page
// and stops the rest of the script from running.
function requireLogin(): array {
    $user = currentUser();
    if (!$user) {
        header("Location: /login.php");
        exit;
    }
    return $user;
}

// Call this at the top of any page restricted to a specific role
// (e.g. only farmers can reach the "add listing" page).
function requireRole(string $role): array {
    $user = requireLogin();
    if ($user['role'] !== $role) {
        http_response_code(403);
        die("You don't have access to this page.");
    }
    return $user;
}