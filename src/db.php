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
    echo("Done");
} catch (\PDOException $e){
    die("Database connection failed $e");
}