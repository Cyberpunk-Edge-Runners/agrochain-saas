<?php
// src/logout.php
require_once __DIR__ . '/includes/auth.php';

// Clear all session data, then destroy the session itself (removes the
// server-side session file and invalidates the session cookie).
$_SESSION = [];
session_destroy();

header("Location: /index.php");
exit;