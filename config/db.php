<?php
// c:/Users/Public/Job_Post/config/db.php

// --- Database Configuration ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'Jobhunt'); // <-- REPLACE WITH YOUR DATABASE NAME
define('DB_USER', 'Hari');          // <-- REPLACE WITH YOUR DATABASE USERNAME
define('DB_PASS', 'Hari(0007)');              // <-- REPLACE WITH YOUR DATABASE PASSWORD
define('DB_CHARSET', 'utf8mb4');

// --- PDO Connection Function ---
function getPDO() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (\PDOException $e) {
            // In a production environment, you would log this error and
            // show a generic error message to the user.
            throw new \PDOException($e->getMessage(), (int)$e->getCode());
        }
    }
    return $pdo;
}
?>
