<?php
// Database connection details - UPDATE THESE!
$dbHost = 'localhost';
$dbName = 'job_portal_db'; // <--- REPLACE WITH YOUR DATABASE NAME
$dbUser = 'root';          // <--- REPLACE WITH YOUR DATABASE USERNAME
$dbPass = '';              // <--- REPLACE WITH YOUR DATABASE PASSWORD

$usersFilePath = __DIR__ . '/data/users.json';

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    echo "Connected to database successfully.\n";

    if (!file_exists($usersFilePath)) {
        die("Error: users.json not found at $usersFilePath\n");
    }

    $jsonData = file_get_contents($usersFilePath);
    $users = json_decode($jsonData, true);

    if (!is_array($users)) {
        die("Error: Could not decode users.json or it's not an array.\n");
    }

    if (empty($users)) {
        echo "No users found in users.json to migrate.\n";
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO users (id, username, email, password, role, created_at) VALUES (:id, :username, :email, :password, :role, :created_at)");

    $migratedCount = 0;
    foreach ($users as $user) {
        // Ensure all required fields exist and are not empty
        if (empty($user['id']) || empty($user['username']) || empty($user['email']) || empty($user['password']) || empty($user['role'])) {
            echo "Skipping invalid user entry: " . json_encode($user) . "\n";
            continue;
        }

        // Check if user already exists by email to prevent duplicates on re-run
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
        $checkStmt->execute([':email' => $user['email']]);
        if ($checkStmt->fetchColumn() > 0) {
            echo "User with email '{$user['email']}' already exists in DB. Skipping.\n";
            continue;
        }

        $stmt->execute([
            ':id' => $user['id'],
            ':username' => $user['username'],
            ':email' => $user['email'],
            ':password' => $user['password'], // Assuming passwords are already hashed
            ':role' => $user['role'],
            ':created_at' => $user['created_at'] ?? date('Y-m-d H:i:s')
        ]);
        $migratedCount++;
    }

    echo "Migration complete. $migratedCount users migrated successfully.\n";

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage() . "\n");
} catch (Exception $e) {
    die("General error: " . $e->getMessage() . "\n");
}
?>