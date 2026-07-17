<?php
$host = getenv("DB_HOST");
$db = getenv("MYSQL_DATABASE");
$user = getenv("MYSQL_USER");
$pass = getenv("MYSQL_PASSWORD");
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false, #forces you to prepare your sql statements else wiil run into an error...one step away from SQLi
];


try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    // No echo here on purpose: db.php gets require_once'd at the TOP of every
    // page (before any HTML is sent). Printing anything here — even "Done" —
    // would land above your <!DOCTYPE html>, and browsers/PHP will silently
    // shove it in front of your actual page content.
} catch (\PDOException $e) {
    // die() with the raw exception is fine while you're the only one testing
    // locally, but it leaks internals (DB host, sometimes credentials in the
    // message) to anyone who can trigger a failed connection once this is
    // public. Swap this for a generic message + error_log($e) before deploying.
    die("Database connection failed.");
}