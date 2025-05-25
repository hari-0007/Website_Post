<?php
session_start(); // Start the session at the beginning

// Ensure this path is correct for your config file
require_once __DIR__ . '/../includes/config.php'; 
// Ensure this path is correct for your database connection file
require_once __DIR__ . '/../../includes/db_connect.php'; 
// Ensure this path is correct for your functions file (if log_app_activity is there)
require_once __DIR__ . '/../includes/functions.php'; 

// Security: Check if admin is logged in and has appropriate role
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    $_SESSION['admin_status'] = ['message' => 'Unauthorized access. Please login.', 'type' => 'error'];
    header('Location: ' . APP_BASE_URL . 'admin/login.php');
    exit;
}

$loggedInUserRole = $_SESSION['admin_role'] ?? 'user';
// Define roles that can post jobs, adjust as necessary
$canPostJobRoles = ['super_admin', 'India_Admin', 'Middle_East_Admin', 'USA_Admin', 'Europe_Admin']; 
if (!in_array($loggedInUserRole, $canPostJobRoles)) {
    $_SESSION['admin_status'] = ['message' => 'Access Denied. You do not have permission to post jobs.', 'type' => 'error'];
    header('Location: ' . APP_BASE_URL . 'admin/dashboard.php'); // Or an appropriate redirect
    exit;
}


// --- Assume Form Processing and Database Insertion Happens Here ---
// For demonstration, let's simulate that a new job has been created
// In a real scenario, you would get these details from $_POST, validate them,
// and insert them into your database. $newJob would be populated with data
// from the database or the validated POST data.

// Example:
// if ($_SERVER['REQUEST_METHOD'] === 'POST') {
//     // Validate $_POST data (title, company, location, description, etc.)
//     // ...
//
//     // Insert into database
//     // $stmt = $pdo->prepare("INSERT INTO jobs (title, company, ...) VALUES (?, ?, ...)");
//     // $stmt->execute([...]);
//     // $newJobId = $pdo->lastInsertId();
//
//     // Fetch the newly created job details (or use the validated POST data)
//     // $newJob = ['id' => $newJobId, 'title' => $_POST['title'], ... ];
//
// } else {
//     // Handle cases where the script is accessed directly or with wrong method
//     $_SESSION['admin_status'] = ['message' => 'Invalid request method for posting a job.', 'type' => 'error'];
//     header('Location: ' . APP_BASE_URL . 'admin/post_job.php'); // Redirect back to job posting form
//     exit;
// }

// For this example, we'll hardcode $newJob for demonstration.
// REPLACE THIS WITH YOUR ACTUAL JOB DATA RETRIEVAL/HANDLING
$newJob = [
    'id' => rand(1000, 9999), // Simulated job ID
    'title' => $_POST['job_title'] ?? 'Sample Job Title', // Example: Get from POST or your DB object
    'company' => $_POST['company_name'] ?? 'Sample Company Inc.',
    'location' => $_POST['job_location'] ?? 'Remote',
    'description' => $_POST['job_description'] ?? 'This is a sample job description highlighting key responsibilities and qualifications.',
    'job_type' => $_POST['job_type'] ?? 'Full-time'
    // Add other relevant fields like 'salary_range', 'experience_level', etc.
];

// --- END OF SIMULATED JOB CREATION ---


// --- WHATSAPP AUTOMATION INTEGRATION ---
$whatsappEnabled = true; // Make this configurable, perhaps from a settings table or config file

if ($whatsappEnabled && isset($newJob) && !empty($newJob)) {
    $targetWhatsappGroupName = 'Auto Post Job'; // The name of your target WhatsApp group

    if (empty($targetWhatsappGroupName)) {
        error_log("WhatsApp Automation: Target WhatsApp Group Name is not configured. Skipping message for job ID '{$newJob['id']}'.");
        if (isset($_SESSION['admin_status']['message'])) {
             $_SESSION['admin_status']['message'] .= ' (WhatsApp notification skipped: Target Group Name not configured)';
        } else {
             // This case might not be hit if job creation itself sets a status message
             $_SESSION['admin_status'] = ['message' => 'Job post processed. (WhatsApp notification skipped: Target Group Name not configured)', 'type' => 'success'];
        }
    } else {
        // Construct the link to the job detail page on your website
        // Ensure APP_BASE_URL is defined in your config.php and ends with a '/' if needed
        $jobDisplayLink = rtrim(APP_BASE_URL, '/') . '/job_detail.php?id=' . $newJob['id'];
        
        // Format the message for WhatsApp
        $whatsappMessage = "📢 *New Job Opportunity Posted!* 📢\n\n" .
                           "✨ *Title:* " . ($newJob['title'] ?? 'N/A') . "\n" .
                           "🏢 *Company:* " . ($newJob['company'] ?? 'N/A') . "\n" .
                           "📍 *Location:* " . ($newJob['location'] ?? 'N/A') . "\n";
        if (!empty($newJob['job_type'])) {
            $whatsappMessage .= "⏰ *Type:* " . $newJob['job_type'] . "\n";
        }
        $whatsappMessage .= "\n📝 *Description (Snippet):* " . substr(strip_tags($newJob['description'] ?? ''), 0, 150) . "...\n\n" .
                           "🔗 *Apply Here & More Info:* " . $jobDisplayLink . "\n\n" .
                           "Good luck! 🚀";

        // URL to your whatsapp_manager.php script
        $whatsappManagerUrl = rtrim(APP_BASE_URL, '/') . '/admin/whatsapp_manager.php';

        $postData = [
            'action' => 'send_whatsapp_message',
            'target_identifier' => $targetWhatsappGroupName, // Use target_identifier with group name
            'message' => $whatsappMessage,
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $whatsappManagerUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 45); // Increased timeout for potentially longer Node script execution
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // Timeout for the connection phase (seconds)
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4); // Force IPv4 resolution

        // Forward admin session cookie if whatsapp_manager.php relies on it for auth
        if (isset($_COOKIE[session_name()])) {
            curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . $_COOKIE[session_name()]);
        }

        // --- SSL Verification Handling for cURL ---
        $urlPartsWA = parse_url($whatsappManagerUrl);
        $isLocalHttpsRequest = (isset($urlPartsWA['scheme']) && $urlPartsWA['scheme'] === 'https' &&
                               isset($urlPartsWA['host']) && ($urlPartsWA['host'] === 'localhost' || $urlPartsWA['host'] === '127.0.0.1'));

        if ($isLocalHttpsRequest) {
            // Unconditionally disable SSL verification for https://localhost or https://127.0.0.1
            error_log("[process_job_post.php] cURL Info: Local HTTPS detected for URL: {$whatsappManagerUrl}. Disabling SSL peer/host verification.");
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        } elseif (defined('APP_ENV') && APP_ENV === 'development' && isset($urlPartsWA['scheme']) && $urlPartsWA['scheme'] === 'https') {
            // For other HTTPS URLs in a development environment (e.g., https://myproject.local with self-signed cert)
            error_log("[process_job_post.php] cURL Info: Development environment HTTPS detected for URL: {$whatsappManagerUrl} (APP_ENV=development). Disabling SSL peer/host verification.");
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }
        // --- End SSL Verification Handling ---

        // If using API Key auth for whatsapp_manager.php for this action:
        // curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-API-Key: YOUR_SECRET_API_KEY_HERE'));


        $responseJson = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log("WhatsApp Automation cURL Error: " . $curlError . " for job ID '{$newJob['id']}'. URL: " . $whatsappManagerUrl);
            if (isset($_SESSION['admin_status']['message'])) {
                $_SESSION['admin_status']['message'] .= ' (WhatsApp notification failed: cURL error)';
            } else {
                $_SESSION['admin_status'] = ['message' => 'Job post processed. (WhatsApp notification failed: cURL error)', 'type' => 'warning'];
            }
        } else {
            $waResponse = json_decode($responseJson, true);
            if ($httpCode === 200 && $waResponse && isset($waResponse['success']) && $waResponse['success']) {
                // Use a generic success message from the controller if available, or a default one
                $controllerMessage = $waResponse['message'] ?? 'Notification sent.';
                log_app_activity("WhatsApp notification sent for job ID '{$newJob['id']}' to target '{$targetWhatsappGroupName}'. Controller: " . $controllerMessage, "WHATSAPP_SENT");
                 if (isset($_SESSION['admin_status']['message'])) {
                    $_SESSION['admin_status']['message'] .= ' (WhatsApp: ' . htmlspecialchars($controllerMessage) . ')';
                 } else {
                    $_SESSION['admin_status'] = ['message' => 'Job post processed. (WhatsApp: ' . htmlspecialchars($controllerMessage) . ')', 'type' => 'success'];
                 }
            } else {
                $waErrorMsg = $waResponse['message'] ?? 'Unknown WhatsApp controller error';
                if (isset($waResponse['raw_stderr_preview'])) { // If PHP manager provides stderr preview
                    $waErrorMsg .= ' (Preview: ' . $waResponse['raw_stderr_preview'] . ')';
                }
                error_log("WhatsApp Automation Failed for job ID '{$newJob['id']}'. HTTP Code: {$httpCode}. Controller Msg: " . $waErrorMsg . ". Raw Response: " . $responseJson);
                 if (isset($_SESSION['admin_status']['message'])) {
                    $_SESSION['admin_status']['message'] .= ' (WhatsApp notification failed: ' . htmlspecialchars($waErrorMsg) . ')';
                 } else {
                    $_SESSION['admin_status'] = ['message' => 'Job post processed. (WhatsApp notification failed: ' . htmlspecialchars($waErrorMsg) . ')', 'type' => 'warning'];
                 }
            }
        }
    }
}
// --- END WHATSAPP AUTOMATION INTEGRATION ---

// Set a default success message if not already set by WhatsApp logic
if (!isset($_SESSION['admin_status'])) {
    $_SESSION['admin_status'] = ['message' => 'Job post processed successfully!', 'type' => 'success'];
}


// Redirect back to the job listing page or a success page
// Ensure APP_BASE_URL is defined and appropriate
header('Location: ' . APP_BASE_URL . 'admin/manage_jobs.php');
exit;
?>
