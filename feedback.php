<?php
// feedback.php

header('Content-Type: application/json');

// Include configuration for file path
require_once __DIR__ . '/admin/includes/config.php';

// Path to the feedback data file
// Assuming feedback.php is in the root and data is in /data
$feedbackFilename = __DIR__ . '/data/feedback.json';

// Get and sanitize input
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$message = trim($_POST['message'] ?? '');

// Basic validation
if (empty($name) || empty($email) || empty($message)) {
    echo json_encode([
        'success' => false,
        'message' => 'Please fill in all fields.'
    ]);
    exit; // Stop execution
}

// Validate email format (basic check)
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'success' => false,
        'message' => 'Please enter a valid email address.'
    ]);
    exit; // Stop execution
}


// Load existing feedback
$feedback = [];
if (file_exists($feedbackFilename)) {
    $jsonData = file_get_contents($feedbackFilename);
    $feedback = json_decode($jsonData, true);
    if ($feedback === null || !is_array($feedback)) {
        $feedback = []; // Initialize as empty array if file is empty or invalid JSON
        error_log("Feedback Error: Could not decode feedback.json or file is empty/invalid.");
    }
} else {
     // Ensure the data directory exists if feedback.json didn't exist
     $dir = dirname($feedbackFilename);
     if (!is_dir($dir)) {
         if (!mkdir($dir, 0755, true)) {
              error_log("Feedback Error: Could not create data directory: " . $dir);
               echo json_encode([
                   'success' => false,
                   'message' => 'Error saving feedback. Please try again later.'
               ]);
               exit;
         }
     }
}


// Create new feedback entry
$newFeedback = [
    'id' => time() . '_' . mt_rand(1000, 9999), // Simple unique ID
    'name' => htmlspecialchars($name, ENT_QUOTES, 'UTF-8'), // Sanitize output
    'email' => htmlspecialchars($email, ENT_QUOTES, 'UTF-8'), // Sanitize output
    'message' => htmlspecialchars($message, ENT_QUOTES, 'UTF-8'), // Sanitize output
    'timestamp' => time(), // Unix timestamp
    'read' => false // New messages are unread
];

// Add the new feedback to the beginning of the array (most recent first)
array_unshift($feedback, $newFeedback);

// Save the updated feedback array
$jsonData = json_encode($feedback, JSON_PRETTY_PRINT);
if ($jsonData === false) {
    error_log("Feedback Error: Could not encode feedback data to JSON: " . json_last_error_msg());
    echo json_encode([
        'success' => false,
        'message' => 'Error saving feedback. Please try again later.'
    ]);
    exit;
}

// Use LOCK_EX to prevent concurrent writes from corrupting the file
if (file_put_contents($feedbackFilename, $jsonData, LOCK_EX) === false) {
    error_log("Feedback Error: Could not write feedback data to file: " . $feedbackFilename);
    echo json_encode([
        'success' => false,
        'message' => 'Error saving feedback. Please try again later.'
    ]);
    exit;
}

// Success response
echo json_encode([
    'success' => true,
    'message' => 'Thank you! Your message has been sent.'
]);

?>
