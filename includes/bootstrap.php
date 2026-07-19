<?php
// includes/bootstrap.php
//
// ONE file every page in public/ requires, instead of juggling separate
// relative paths to auth.php, db.php, storage.php, and the partials
// individually. If the folder structure ever moves again, THIS is the
// only file that needs updating — every page's own require line
// (__DIR__ . '/../includes/bootstrap.php') never has to change, because
// it's identical in every single page.

// dirname(__DIR__) from inside includes/ resolves to the project root —
// the parent of includes/ — regardless of where the whole project lives
// on disk. BASE_PATH is the one source of truth every other path
// constant below builds on, so there's never a second place that needs
// to agree with it.
define('BASE_PATH', dirname(__DIR__));
define('INCLUDES_PATH', BASE_PATH . '/includes');
define('PARTIALS_PATH', INCLUDES_PATH . '/partials');

// Route constants (ROUTE_LOGIN, ROUTE_DASHBOARD, etc.) load first, since
// auth.php's requireLogin() references ROUTE_LOGIN — it needs to already
// be defined by the time auth.php runs.
require_once INCLUDES_PATH . '/routes.php';

// auth.php (session helpers) and db.php ($pdo) are needed by virtually
// every page, so bootstrap.php pulls both in automatically. A page that
// also needs storage.php (currently just upload-documents.php) still
// requires that one explicitly, using the INCLUDES_PATH constant instead
// of a hand-typed relative path:
//     require_once INCLUDES_PATH . '/storage.php';
require_once INCLUDES_PATH . '/auth.php';
require_once INCLUDES_PATH . '/db.php';