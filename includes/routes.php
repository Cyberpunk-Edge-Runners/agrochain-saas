<?php
// includes/routes.php
//
// Every page URL in the app, named once, here. Every <a href>,
// <form action>, and header('Location: ...') redirect elsewhere in the
// codebase uses one of these constants instead of typing the path
// directly. If a filename ever changes again, this is the ONLY file
// that needs updating — nothing else in the app has the literal string
// anywhere.
//
// These are absolute, clean paths — no .php extension, no leading "./".
// Absolute (starting with /) means they work identically no matter which
// page you're currently on, unlike relative "./" paths, which only
// resolve correctly if every page lives at the same folder depth — a
// fragile assumption to rely on. The missing .php is handled by Apache's
// rewrite rules in public/.htaccess: requesting /login internally serves
// login.php behind the scenes, and requesting /login.php directly gets
// redirected to the clean version. That ONLY works because
// docker-compose.yml mounts ./public (not the whole project) onto
// Apache's web root — if that mount is wrong, these clean paths won't
// resolve, no matter how correct this file is.
//
// Required automatically by bootstrap.php, before auth.php — so these
// constants are already defined by the time auth.php's requireLogin()
// needs ROUTE_LOGIN below.

define('ROUTE_HOME', '/');
define('ROUTE_LOGIN', '/login');
define('ROUTE_REGISTER', '/register');
define('ROUTE_LOGOUT', '/logout');
define('ROUTE_DASHBOARD', '/dashboard');
define('ROUTE_PRODUCTS', '/products');
define('ROUTE_ORDER', '/order');
define('ROUTE_ORDERS', '/orders');
define('ROUTE_UPLOAD_DOCUMENTS', '/upload-documents');

define('ASSET_CSS', '/assets/css/style.css');