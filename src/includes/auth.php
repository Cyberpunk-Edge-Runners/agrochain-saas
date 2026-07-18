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
    return requireAnyRole([$role]);
}

// Same idea, but for pages more than one role can reach — e.g. document
// upload is relevant to both farmers (crop certificates) and drivers
// (license, insurance), but not buyers.
function requireAnyRole(array $roles): array {
    $user = requireLogin();
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        die("You don't have access to this page.");
    }
    return $user;
}

// --- FLASH MESSAGES ---
//
// WHY THIS EXISTS
// -----------------
// order.php and orders.php already redirect after every POST (the
// "Post/Redirect/Get" pattern): handle the form submission, then send an
// HTTP redirect to a GET request, instead of just re-rendering the same
// page in response to the POST. This matters because a browser remembers
// the LAST request it made — if that request was a POST that isn't
// followed by a redirect, hitting refresh re-sends that exact same POST
// again. For a form that publishes a new listing, that means refreshing
// the page after a successful publish creates a SECOND identical listing,
// then a third, etc. Redirecting afterward means the last request the
// browser remembers is a harmless GET, so refresh just re-loads the page
// normally.
//
// The catch: once you redirect, you've started a brand new page load with
// no memory of what just happened — so how do you still show "Listing
// published successfully" on the page the user lands on? That's what
// these two functions solve: stash a message in the session right before
// redirecting, then read (and immediately erase) it on the very next page
// load. "Flash" because it shows up once and is gone — refreshing again
// after that won't keep re-showing the same success message.

// Call this right before a header("Location: ...") + exit, to queue up a
// message that should be shown once the redirect finishes loading.
function flashSet(string $type, string $message): void {
    $_SESSION['flash'][$type] = $message;
}

// Call this where you're about to render a page, to check if there's a
// queued message waiting. Returns the message string, or null if there
// isn't one. Reading it also deletes it immediately (via unset) — that's
// what makes it "flash" instead of permanent: it only ever gets shown on
// the ONE page load right after it was set.
function flashGet(string $type): ?string {
    if (empty($_SESSION['flash'][$type])) {
        return null;
    }
    $message = $_SESSION['flash'][$type];
    unset($_SESSION['flash'][$type]);
    return $message;
}