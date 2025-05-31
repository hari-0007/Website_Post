<?php
// feedback.php

header('Content-Type: application/json');

// Include configuration for file path
require_once __DIR__ . '/admin/includes/config.php';

// Path to the feedback data file
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

        // For backward compatibility, also add the emoji directly to the analysis result
        // if other parts of the system might still expect `detected_emotion_emoji` directly.
        // $analysis['detected_emotion_emoji'] = $analysis['emoji'];

        return $analysis;
    }
}
// --- End Placeholder ---

// --- Wrapper for backward compatibility or accidental old calls ---
if (!function_exists('get_emotion_emoji_from_text')) {
    function get_emotion_emoji_from_text($text) {
        // Ensure get_ai_message_analysis is available.
        // It's defined above this point in the current file structure.
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
    'message' => $message, // Allow special characters without sanitizing here
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
