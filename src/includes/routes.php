<?php
// includes/routes.php
//
// Every page URL in the app, named once, here. Every <a href>,
// <form action>, and header('Location: ...') redirect elsewhere in the
// codebase uses one of these constants instead of typing the path
// directly. If a filename ever changes again (like upload-document.php
// -> upload-documents.php did), this is the ONLY file that needs
// updating — nothing else in the app has the literal string anywhere.
//
// Required automatically by bootstrap.php, before auth.php — so these
// constants are already defined by the time auth.php's requireLogin()
// needs ROUTE_LOGIN below.

define('ROUTE_HOME', './index.php');
define('ROUTE_LOGIN', './login.php');
define('ROUTE_REGISTER', './register.php');
define('ROUTE_LOGOUT', './logout.php');
define('ROUTE_DASHBOARD', './dashboard.php');
define('ROUTE_PRODUCTS', './products.php');
define('ROUTE_ORDER', './order.php');
define('ROUTE_ORDERS', './orders.php');
define('ROUTE_UPLOAD_DOCUMENTS', './upload-documents.php');

define('ASSET_CSS', './assets/style.css');