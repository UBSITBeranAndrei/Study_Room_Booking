<?php
$host = "localhost";
$user = "root";
$pass = "";
$db = "library_room_booking";
$charset = "utf8mb4";

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$attributes = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $attributes);

} catch (PDOException $e) {
    error_log("Database Connection Failed: " . $e->getMessage());

    die("Database connection failed. Please contact the administrator.");
}
?>
