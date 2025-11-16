<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$host = 'localhost';
$db   = 'Jobhunt';
$user = 'Hari';
$pass = 'Hari(0007)';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

try {
    $pdo = new PDO($dsn, $user, $pass);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
