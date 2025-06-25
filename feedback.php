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
    return is_array($data) ? $data : [];
}

function writeJsonFile($filePath, $data) {
    return file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

// --- Main Logic ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => 'An unknown error occurred.'];

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $rating = intval($_POST['rating'] ?? 0);
    $message = trim($_POST['message'] ?? '');
    $recaptchaResponse = $_POST['g-recaptcha-response'] ?? '';

    // 1. Validate reCAPTCHA
    if (empty($recaptchaResponse)) {
        $response['message'] = 'reCAPTCHA verification failed. Please try again.';
        $response['captcha_error'] = true; // Custom flag for JS to reset captcha
        echo json_encode($response);
        exit;
    }

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
            'content' => http_build_query($recaptchaData)
        ]
    ];
    $context  = stream_context_create($options);
    $verifyResponse = file_get_contents($recaptchaUrl, false, $context);
    $captchaSuccess = json_decode($verifyResponse, true);

    if (!$captchaSuccess['success']) {
        $response['message'] = 'reCAPTCHA verification failed. Are you a robot?';
        $response['captcha_error'] = true;
        error_log("reCAPTCHA verification failed: " . json_encode($captchaSuccess['error-codes'] ?? 'No error codes'));
        echo json_encode($response);
        exit;
    }

    // 2. Validate other form fields
    if (empty($name) || empty($email) || empty($message)) {
        $response['message'] = 'All fields (Name, Email, Message) are required.';
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

    // 3. Save feedback
    $feedbacks = readJsonFile(FEEDBACK_FILE_PATH);
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
        $response['message'] = 'Failed to save feedback. Please try again later.';
    }

    echo json_encode($response);
} else {
    $response = ['success' => false, 'message' => 'Invalid request method.'];
    echo json_encode($response);
}
?>