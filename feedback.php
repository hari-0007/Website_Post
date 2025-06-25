<?php
session_start();
header('Content-Type: application/json');

// Set a consistent timezone
date_default_timezone_set('Asia/Kolkata');

// --- Configuration ---
// Replace with your actual reCAPTCHA Secret Key
// You can find this in your Google reCAPTCHA admin console for your site.
const RECAPTCHA_SECRET_KEY = 'YOUR_RECAPTCHA_SECRET_KEY'; 

// Path to store feedback messages
const FEEDBACK_FILE_PATH = __DIR__ . '/data/feedback.json';

// --- Helper Functions ---
function readJsonFile($filePath) {
    if (!file_exists($filePath) || filesize($filePath) === 0) {
        return [];
    }
    $data = json_decode(file_get_contents($filePath), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("readJsonFile Error: JSON decode error for {$filePath}: " . json_last_error_msg());
        return [];
    }
    return is_array($data) ? $data : [];
}

function writeJsonFile($filePath, $data) {
    $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($jsonData === false) {
        error_log("writeJsonFile Error: JSON encode error for {$filePath}: " . json_last_error_msg());
        return false;
    }
    // Use LOCK_EX to prevent race conditions during file write
    if (file_put_contents($filePath, $jsonData, LOCK_EX) === false) {
        error_log("writeJsonFile Error: Failed to write to file {$filePath}. Check permissions.");
        return false;
    }
    return true;
}

// --- Main Logic ---
$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $rating = intval($_POST['rating'] ?? 0);
    $message = trim($_POST['message'] ?? '');
    $recaptchaResponse = $_POST['g-recaptcha-response'] ?? '';

    // 0. Check if reCAPTCHA secret key is set
    if (RECAPTCHA_SECRET_KEY === 'YOUR_RECAPTCHA_SECRET_KEY' || empty(RECAPTCHA_SECRET_KEY)) {
        $response['message'] = 'Server-side reCAPTCHA secret key is not configured. Please contact the administrator.';
        error_log("FEEDBACK ERROR: RECAPTCHA_SECRET_KEY is not set or is default placeholder.");
        echo json_encode($response);
        exit;
    }

    // 1. Validate reCAPTCHA response from client
    if (empty($recaptchaResponse)) {
        $response['message'] = 'reCAPTCHA verification failed. Please ensure you are not a robot.';
        $response['captcha_error'] = true; // Custom flag for JS to reset captcha
        error_log("FEEDBACK ERROR: Empty reCAPTCHA response from client.");
        echo json_encode($response);
        exit;
    }

    // 2. Verify reCAPTCHA with Google
    $recaptchaUrl = 'https://www.google.com/recaptcha/api/siteverify';
    $recaptchaData = [
        'secret' => RECAPTCHA_SECRET_KEY,
        'response' => $recaptchaResponse,
        'remoteip' => $_SERVER['REMOTE_ADDR']
    ];

    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($recaptchaData),
            'timeout' => 10 // Set a timeout for the API call
        ]
    ];
    $context  = stream_context_create($options);
    
    $verifyResponse = @file_get_contents($recaptchaUrl, false, $context); // Use @ to suppress warnings, handle errors manually
    
    if ($verifyResponse === false) {
        $response['message'] = 'reCAPTCHA verification failed due to a server communication error. Please try again.';
        $response['captcha_error'] = true;
        error_log("FEEDBACK ERROR: Failed to communicate with Google reCAPTCHA API. Check network/firewall. Last error: " . error_get_last()['message'] ?? 'No specific error.');
        echo json_encode($response);
        exit;
    }

    $captchaSuccess = json_decode($verifyResponse, true);

    if (!$captchaSuccess || !isset($captchaSuccess['success']) || !$captchaSuccess['success']) {
        $response['message'] = 'reCAPTCHA verification failed. Please try again. (Code: ' . ($captchaSuccess['error-codes'][0] ?? 'unknown') . ')';
        $response['captcha_error'] = true;
        error_log("FEEDBACK ERROR: Google reCAPTCHA verification failed. Response: " . print_r($captchaSuccess, true));
        echo json_encode($response);
        exit;
    }

    // 3. Validate other form fields
    if (empty($name) || empty($email) || empty($message)) {
        $response['message'] = 'All fields (Name, Email, Message) are required.';
        error_log("FEEDBACK ERROR: Required fields are empty. Name: '{$name}', Email: '{$email}', Message: '{$message}'.");
        echo json_encode($response);
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'Invalid email format.';
        echo json_encode($response);
        exit;
    }
    if ($rating < 0 || $rating > 5) {
        $rating = 0; // Default to 0 if invalid
    }

    // 4. Save feedback
    $feedbacks = readJsonFile(FEEDBACK_FILE_PATH);
    if (!is_array($feedbacks)) { // Ensure $feedbacks is an array even if readJsonFile returned empty
        $feedbacks = [];
    }
    $newFeedback = [
        'id' => uniqid('feedback_'),
        'name' => htmlspecialchars($name),
        'email' => htmlspecialchars($email),
        'rating' => $rating,
        'message' => htmlspecialchars($message),
        'timestamp' => date('Y-m-d H:i:s'),
        'read' => false // New messages are unread by default
    ];
    array_unshift($feedbacks, $newFeedback); // Add to the beginning

    if (writeJsonFile(FEEDBACK_FILE_PATH, $feedbacks)) {
        $response = ['success' => true, 'message' => 'Thank you for your feedback!'];
    } else {
        $response['message'] = 'Failed to save feedback. Please try again later. (File write error)';
        error_log("FEEDBACK ERROR: Failed to write feedback to file. Check permissions for " . FEEDBACK_FILE_PATH);
    }

    echo json_encode($response);
} else {
    $response = ['success' => false, 'message' => 'Invalid request method.'];
    error_log("FEEDBACK ERROR: Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    echo json_encode($response);
}
?>
