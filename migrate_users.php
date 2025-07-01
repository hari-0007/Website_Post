<?php
// c:\Users\Public\Job_Post\migrate_users.php

require_once __DIR__ . '/config/db.php';

$usersFilePath = __DIR__ . '/data/users.json';

try {
    $pdo = getPDO();

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

    // Check if the table exists. If not, create it.
    $checkTableStmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($checkTableStmt->rowCount() == 0) {
        echo "Table 'users' not found. Creating...\n";
        $createTableStmt = $pdo->prepare("
            CREATE TABLE users (
                id VARCHAR(255) PRIMARY KEY,
                username VARCHAR(255) NOT NULL,
                email VARCHAR(255) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                role ENUM('jobseeker', 'recruiter', 'admin', 'super_admin', 'user_group_manager', 'user') NOT NULL DEFAULT 'jobseeker',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                phone VARCHAR(50),
                address TEXT,
                linkedin_profile VARCHAR(255),
                skills TEXT,
                experience_summary TEXT,
                resume_path VARCHAR(255)
            )
        ");
        $createTableStmt->execute();
        echo "Table 'users' created successfully.\n";
    }

    $stmt = $pdo->prepare("INSERT INTO users (id, username, email, password, role, created_at, phone, address, linkedin_profile, skills, experience_summary, resume_path) VALUES (:id, :username, :email, :password, :role, :created_at, :phone, :address, :linkedin_profile, :skills, :experience_summary, :resume_path)");

    $migratedCount = 0;
    foreach ($users as $user) {
        // Ensure all required fields exist and are not empty
        if (empty($user['id']) || empty($user['username']) || empty($user['email']) || empty($user['password']) || empty($user['role'])) {
            echo "Skipping invalid user entry: " . json_encode($user) . "\n";
            continue;
        }

        // Prevent duplicates based on email
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
        $checkStmt->execute([':email' => $user['email']]);
        if ($checkStmt->fetchColumn() > 0) { 
            echo "User with email '{$user['email']}' already exists in DB. Skipping.\n";
            continue;
        }

        // Skills are stored as an array in the JSON, so we should serialize them for the DB.
        $skills_to_db = isset($user['skills']) && is_array($user['skills']) ? json_encode($user['skills']) : null;

        // Execute the prepared statement with all the user data.
        // Optional fields default to null if not present in the JSON.
        $stmt->execute([
            ':id' => $user['id'],
            ':username' => $user['username'],
            ':email' => $user['email'],
            ':password' => $user['password'], // Assuming passwords are already hashed
            ':role' => $user['role'],
            ':created_at' => $user['created_at'] ?? date('Y-m-d H:i:s'),
            ':phone' => $user['phone'] ?? null,
            ':address' => $user['address'] ?? null,
            ':linkedin_profile' => $user['linkedin_profile'] ?? null,
            ':skills' => $skills_to_db,
            ':experience_summary' => $user['experience_summary'] ?? null,
            ':resume_path' => $user['resume_path'] ?? null
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
