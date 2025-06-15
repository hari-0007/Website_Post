<?php

header('Content-Type: application/json');

// Include configuration for file path (if needed for other constants, though not directly for feedback.json path here)
// require_once __DIR__ . '/admin/includes/config.php'; // Assuming USER_UNIQUE_ID_COOKIE_NAME is defined here or globally

// Define USER_UNIQUE_ID_COOKIE_NAME if not already defined via config.php
if (!defined('USER_UNIQUE_ID_COOKIE_NAME')) {
    define('USER_UNIQUE_ID_COOKIE_NAME', 'user_unique_site_id');
}


// Path to the feedback data file
$feedbackFilename = __DIR__ . '/data/feedback.json';
error_log("[FEEDBACK_DEBUG] Script started. Feedback file: " . $feedbackFilename);

// --- Google reCAPTCHA Verification ---
function verifyRecaptcha($recaptchaResponse, $secretKey) {
    $url = 'https://www.google.com/recaptcha/api/siteverify';
    $data = [
        'secret'   => $secretKey,
        'response' => $recaptchaResponse,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? null // Optional: IP of the user
    ];
    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
            'timeout' => 5 // 5 second timeout for the request
        ],
    ];
    $context  = stream_context_create($options);
    $result = @file_get_contents($url, false, $context); // Use @ to suppress errors if request fails, check $result

    if ($result === FALSE) {
        // Log error or handle failure to connect to Google's API
        error_log("[FEEDBACK_DEBUG] reCAPTCHA verification request failed to connect to Google.");
        return false;
    }
    $responseKeys = json_decode($result, true);
    error_log("[FEEDBACK_DEBUG] reCAPTCHA verification response from Google: " . print_r($responseKeys, true));
    return ($responseKeys && isset($responseKeys["success"]) && $responseKeys["success"]);
}

$recaptchaSecretKey = '6LcF92ErAAAAAHO38liOFIgrapN-KriFuVxK3zwq'; // <-- REPLACE WITH YOUR ACTUAL SECRET KEY
$userRecaptchaResponse = $_POST['g-recaptcha-response'] ?? '';
error_log("[FEEDBACK_DEBUG] User reCAPTCHA response token: " . $userRecaptchaResponse);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verifyRecaptcha($userRecaptchaResponse, $recaptchaSecretKey)) {
    error_log("[FEEDBACK_DEBUG] reCAPTCHA verification FAILED by verifyRecaptcha function.");
    echo json_encode(['success' => false, 'message' => 'reCAPTCHA verification failed. Please try again.', 'captcha_error' => true]);
    exit;
}
error_log("[FEEDBACK_DEBUG] reCAPTCHA verification PASSED.");
// --- End Google reCAPTCHA Verification ---


// Get and sanitize input
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$message = trim($_POST['message'] ?? '');
$rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0; // Get and sanitize rating

error_log("[FEEDBACK_DEBUG] Received POST data - Name: {$name}, Email: {$email}, Rating: {$rating}, Message (first 50 chars): " . substr($message, 0, 50));


// Basic validation
if (empty($name) || empty($email) || empty($message)) {
    echo json_encode([
        'success' => false,
        'message' => 'Please fill in all fields.'
    ]);
    exit;
}

// Validate rating (optional, but good practice)
if ($rating < 0 || $rating > 5) { // Assuming 0 means no rating, 1-5 are valid
    echo json_encode([
        'success' => false,
        'message' => 'Invalid rating value.'
    ]);
    exit;
}
// Validate email format (basic check)
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'success' => false,
        'message' => 'Please enter a valid email address.'
    ]);
    exit;
}

// Load existing feedback
$feedback = [];
if (file_exists($feedbackFilename)) {
    $jsonData = file_get_contents($feedbackFilename);
    $feedback = json_decode($jsonData, true);
    if ($feedback === null || !is_array($feedback)) {
        $feedback = []; 
        error_log("[FEEDBACK_DEBUG] Feedback Error: Could not decode feedback.json or file is empty/invalid. Initializing as empty array.");
    }
} else {
    error_log("[FEEDBACK_DEBUG] feedback.json does not exist. Will attempt to create it.");
    $dir = dirname($feedbackFilename);
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0755, true)) { // Suppress error if dir already exists due to race condition
            error_log("[FEEDBACK_DEBUG] Feedback Error: Could not create data directory: " . $dir);
            echo json_encode([
                'success' => false,
                'message' => 'Error saving feedback. Please try again later.'
            ]);
            exit;
        }
    }
}

// --- Enhanced Placeholder AI Message Analysis ---
// In a real application, replace this with a call to an actual AI service/library.
if (!function_exists('get_ai_message_analysis')) {
    function get_ai_message_analysis($text) {
        $textLower = strtolower(trim($text ?? ''));
        $analysis = [
            'emotion_label' => 'neutral',
            'emoji' => '😐', // Default neutral emoji
            'sticker_id' => 'sticker_neutral.png' // Placeholder sticker
        ];

        // --- More Specific & Negative Contexts First ---
        if (preg_match('/\b(not (good|happy|satisfied|pleased|great)|very bad|terrible|awful)\b/i', $textLower)) {
            $analysis = ['emotion_label' => 'very_negative', 'emoji' => '😠', 'sticker_id' => 'sticker_very_negative.png'];
        } elseif (preg_match('/\b(problem|issue|complaint|broken|not working|error|fail(ed)?)\b/i', $textLower)) {
            $analysis = ['emotion_label' => 'problem_report', 'emoji' => '🛠️', 'sticker_id' => 'sticker_problem.png']; // Could also be 🐞 for bug
        } elseif (preg_match('/\b(urgent|asap|immediate(ly)?|critical)\b/i', $textLower)) {
            $analysis = ['emotion_label' => 'urgent', 'emoji' => '❗', 'sticker_id' => 'sticker_urgent.png'];
        }
        // --- Positive Emotions ---
        elseif (preg_match('/\b(love|fantastic|amazing|awesome|thrilled|delighted|perfect|excellent|wonderful|outstanding|superb)\b/i', $textLower)) {
            $analysis = ['emotion_label' => 'very_positive', 'emoji' => '😍', 'sticker_id' => 'sticker_very_positive.png']; // Or 🎉, 😄
        } elseif (preg_match('/\b(great|happy|good|nice|pleased|like|glad|satisfied|well done|kudos|impressed)\b/i', $textLower)) {
            $analysis = ['emotion_label' => 'positive', 'emoji' => '😊', 'sticker_id' => 'sticker_positive.png'];
        } elseif (preg_match('/\b(thanks|thank you|appreciate|grateful)\b/i', $textLower)) {
            $analysis = ['emotion_label' => 'grateful', 'emoji' => '🙏', 'sticker_id' => 'sticker_grateful.png'];
        } elseif (preg_match('/\b(congratulations|congrats|yay)\b/i', $textLower)) {
            $analysis = ['emotion_label' => 'celebration', 'emoji' => '🎉', 'sticker_id' => 'sticker_celebration.png'];
        }
        // --- Negative Emotions ---
        elseif (preg_match('/\b(sad|unhappy|cry|upset|sorry to hear|bad news)\b/i', $textLower)) {
            $analysis = ['emotion_label' => 'sad', 'emoji' => '😞', 'sticker_id' => 'sticker_sad.png'];
        } elseif (preg_match('/\b(disappointed)\b/i', $textLower)) {
            $analysis = ['emotion_label' => 'disappointed', 'emoji' => '😟', 'sticker_id' => 'sticker_disappointed.png'];
        } elseif (preg_match('/\b(angry|furious|mad|hate|frustrated|annoyed|irritated)\b/i', $textLower)) {
            $analysis = ['emotion_label' => 'angry', 'emoji' => '😠', 'sticker_id' => 'sticker_angry.png'];
        } elseif (preg_match('/\b(scared|fear|afraid|nervous|anxious|worried)\b/i', $textLower)) {
            $analysis = ['emotion_label' => 'fear_anxiety', 'emoji' => '😨', 'sticker_id' => 'sticker_fear.png'];
        }
        // --- Inquiry & Suggestions ---
        elseif (preg_match('/\b(help|question|query|wondering|how to|support|assist|enquire|inquire)\b/i', $textLower) || str_ends_with($textLower, '?')) {
            $analysis = ['emotion_label' => 'question_help', 'emoji' => '🤔', 'sticker_id' => 'sticker_question.png'];
        } elseif (preg_match('/\b(confused|huh|what(\?)?)\b/i', $textLower)) {
            $analysis = ['emotion_label' => 'confused', 'emoji' => '😕', 'sticker_id' => 'sticker_confused.png'];
        } elseif (preg_match('/\b(idea|suggest(ion)?|feature|improve|request|recommend)\b/i', $textLower)) {
            $analysis = ['emotion_label' => 'suggestion_idea', 'emoji' => '💡', 'sticker_id' => 'sticker_idea.png'];
        }
        // --- Other Expressions ---
        elseif (preg_match('/\b(wow|omg|omfg|whoa|incredible|surprised|unbelievable)\b/i', $textLower)) {
            $analysis = ['emotion_label' => 'surprise_shock', 'emoji' => '😮', 'sticker_id' => 'sticker_surprise.png'];
        } elseif (preg_match('/\b(lol|haha|funny|hilarious)\b/i', $textLower)) {
            $analysis = ['emotion_label' => 'laughing', 'emoji' => '😂', 'sticker_id' => 'sticker_laughing.png'];
        } elseif (preg_match('/\b(sorry|apologize|my mistake|my bad)\b/i', $textLower)) {
            $analysis = ['emotion_label' => 'apology', 'emoji' => '😔', 'sticker_id' => 'sticker_apology.png'];
        } elseif (preg_match('/\b(hello|hi|hey|greetings)\b/i', $textLower)) {
            $analysis = ['emotion_label' => 'greeting', 'emoji' => '👋', 'sticker_id' => 'sticker_greeting.png'];
        } elseif (preg_match('/\b(bye|goodbye|see you|farewell)\b/i', $textLower)) {
            $analysis = ['emotion_label' => 'farewell', 'emoji' => '👋', 'sticker_id' => 'sticker_farewell.png'];
        } elseif (preg_match('/\b(yes|agree|sure|ok|alright|indeed|correct)\b/i', $textLower)) {
            $analysis = ['emotion_label' => 'agreement', 'emoji' => '👍', 'sticker_id' => 'sticker_agreement.png'];
        } elseif (preg_match('/\b(no|disagree|nope|not really)\b/i', $textLower)) {
            $analysis = ['emotion_label' => 'disagreement', 'emoji' => '👎', 'sticker_id' => 'sticker_disagreement.png'];
        }

        return $analysis;
    }
}
// --- End Placeholder ---

// --- Wrapper for backward compatibility or accidental old calls ---
if (!function_exists('get_emotion_emoji_from_text')) {
    function get_emotion_emoji_from_text($text) {
        $analysis = get_ai_message_analysis($text);
        return $analysis['emoji'] ?? '😐'; // Return emoji, with a fallback
    }
}
// --- End Wrapper ---

// Get the unique user site ID from the cookie
$userSiteID = $_COOKIE[USER_UNIQUE_ID_COOKIE_NAME] ?? null;

$aiAnalysis = get_ai_message_analysis($message);

// Create new feedback entry
$newFeedback = [
    'id' => time() . '_' . mt_rand(1000, 9999), // Simple unique ID
    'name' => htmlspecialchars($name, ENT_QUOTES, 'UTF-8'), // Sanitize output
    'email' => htmlspecialchars($email, ENT_QUOTES, 'UTF-8'), // Sanitize output
    'message' => $message, // Allow special characters without sanitizing here for storage
    'rating' => $rating, // Add the rating
    'timestamp' => time(), // Unix timestamp
    'read' => false, // New messages are unread
    'flagged' => false, // New messages are not flagged
    'user_site_id' => $userSiteID, // Store the unique site ID of the user
    'ai_analysis' => $aiAnalysis, // Store the full AI analysis object
    'detected_emotion_emoji' => $aiAnalysis['emoji'], // Keep for easier access or backward compatibility
    'commands' => [] // Initialize an empty array for commands
];


// Add the new feedback to the beginning of the array (most recent first)
array_unshift($feedback, $newFeedback);

// Save the updated feedback array
$jsonData = json_encode($feedback, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); // Added JSON_UNESCAPED_SLASHES
if ($jsonData === false) {
    error_log("[FEEDBACK_DEBUG] Feedback Error: Could not encode feedback data to JSON: " . json_last_error_msg());
    echo json_encode([
        'success' => false,
        'message' => 'Error saving feedback. Please try again later.'
    ]);
    exit;
}

// Use LOCK_EX to prevent concurrent writes from corrupting the file
if (file_put_contents($feedbackFilename, $jsonData, LOCK_EX) === false) {
    error_log("[FEEDBACK_DEBUG] Feedback Error: Could not write feedback data to file: " . $feedbackFilename . " - Check permissions and path.");
    echo json_encode([
        'success' => false,
        'message' => 'Error saving feedback. Please try again later.'
    ]);
    exit;
}

error_log("[FEEDBACK_DEBUG] Feedback successfully saved to " . $feedbackFilename);
// Success response
echo json_encode([
    'success' => true,
    'message' => 'Thank you! Your message has been sent.'
]);

?>
