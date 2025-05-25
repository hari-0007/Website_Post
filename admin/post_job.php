<?php

// admin/post_job.php - Handles the job posting form submission

session_start(); // Start the session to access session variables

// Include configuration and helper functions
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/job_helpers.php';

$loggedInUsernameForLog = $_SESSION['admin_username'] ?? 'UnknownAdmin'; // For logging


// Check if the form was submitted via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'initial_post'; // New: Differentiate actions

    // --- Retrieve and sanitize form inputs (common for both steps) ---
    // For initial_post, these come from the first form.
    // For final_post, these will be resubmitted from the review form.
    $title = trim($_POST['title'] ?? '');
    $company = trim($_POST['company'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $experienceValue = trim($_POST['experience'] ?? '0'); // Raw value from select
    $custom_experience = trim($_POST['custom_experience'] ?? ''); // Custom experience text

    $experience = $experienceValue; // Use the select value by default
    if ($experience === 'other') {
        $experience = trim($_POST['custom_experience'] ?? ''); // Use the custom input value
    }
    $type = trim($_POST['type'] ?? 'Full Time'); // Default to Full Time
    $salary = trim($_POST['salary'] ?? '0'); // Default to 0
    $phones = trim($_POST['phones'] ?? '');
    $emails = trim($_POST['emails'] ?? '');
    $vacant_positions = intval($_POST['vacant_positions'] ?? 1); // Default to 1

    // AI summary will be specifically handled based on the action
    $aiSummary = trim($_POST['ai_summary'] ?? ''); // For final_post

    if ($action === 'initial_post') {
        // --- Step 1: Initial Post - Validate, Check Duplicate, Generate AI Summary, Show Review Form ---

        // Validate required fields for initial post
        if (empty($title)) {
            $_SESSION['admin_status'] = ['message' => 'Job title is required.', 'type' => 'error'];
            $_SESSION['form_data'] = $_POST;
            log_app_activity("Job post attempt by '$loggedInUsernameForLog' failed: Title missing.", "JOB_POST_VALIDATION_ERROR");
            header('Location: dashboard.php?view=post_job');
            exit();
        }
        if (empty($phones) && empty($emails)) {
            $_SESSION['admin_status'] = ['message' => 'At least one contact method (phone or email) is required.', 'type' => 'error'];
            $_SESSION['form_data'] = $_POST;
            log_app_activity("Job post attempt by '$loggedInUsernameForLog' (Title: '$title') failed: Contact info missing.", "JOB_POST_VALIDATION_ERROR");
            header('Location: dashboard.php?view=post_job');
            exit();
        }

        // --- Check for Duplicate Job (within last 7 days) BEFORE AI Summary ---
        $jobsFile = __DIR__ . '/../data/jobs.json';
        $allExistingJobs = file_exists($jobsFile) ? json_decode(file_get_contents($jobsFile), true) : [];
        if (!is_array($allExistingJobs)) {
            $allExistingJobs = []; 
        }

        $sevenDaysAgo = strtotime('-7 days');
        $recentJobs = array_filter($allExistingJobs, function ($job) use ($sevenDaysAgo) {
            $postedTimestamp = $job['posted_on_unix_ts'] ?? (isset($job['posted_on']) ? strtotime($job['posted_on']) : 0);
            return $postedTimestamp >= $sevenDaysAgo;
        });

        $isDuplicateInRecent = false;
        $newJobTitleLower = strtolower(trim($title));
        $newJobEmailsArray = array_map('trim', explode(',', strtolower($emails)));
        $newJobPhonesArray = array_map('trim', explode(',', strtolower($phones)));

        foreach ($recentJobs as $recentJob) {
            $existingTitleLower = strtolower(trim($recentJob['title'] ?? ''));
            if ($existingTitleLower === $newJobTitleLower) {
                $existingJobEmailsArray = array_map('trim', explode(',', strtolower($recentJob['emails'] ?? '')));
                $existingJobPhonesArray = array_map('trim', explode(',', strtolower($recentJob['phones'] ?? '')));
                if (!empty(array_intersect($newJobEmailsArray, $existingJobEmailsArray)) || !empty(array_intersect($newJobPhonesArray, $existingJobPhonesArray))) {
                    $isDuplicateInRecent = true;
                    break;
                }
            }
        }

        if ($isDuplicateInRecent) {
            $_SESSION['admin_status'] = ['message' => 'Error: This job (based on title and contact info) seems to be a duplicate of one posted in the last 7 days.', 'type' => 'error'];
            log_app_activity("Duplicate job post attempt by '$loggedInUsernameForLog'. Title: '$title'. Contacts: P-$phones E-$emails.", "JOB_POST_DUPLICATE");
            $_SESSION['form_data'] = $_POST; 
            header('Location: dashboard.php?view=post_job');
            exit();
        }
        // --- End Duplicate Check ---

        // Generate AI Summary (if description is provided)
        $generatedAiSummary = ''; // Use a different variable name to avoid conflict
        // Always attempt to generate summary, even if description is empty, using other fields.
        error_log("[AI_SUMMARY_DEBUG] Attempting to generate AI summary. Description length: " . strlen($description) . ". Title: " . $title); // Log attempt
            try {
                // IMPORTANT: Replace with your actual valid API key
                $apiKey = 'AIzaSyCWoj7th8DArYw7PGf83JAVcYsXBJHFjAk'; // <<<<----- REPLACE THIS WITH YOUR REAL API KEY
                
                // Check if the API key is still the placeholder or the old example one
                if ($apiKey === 'AIzaSyCWoj7th8DArYw7PGf83JAVcYsXBJHFjAk' || 
                    $apiKey === 'AIzaSyCWoj7th8DArYw7PGf83JAVcYsXBJHFjAk' || // Old placeholder check
                    empty($apiKey)) {
                    log_app_activity("AI Summary generation skipped for job '$title': API Key is placeholder or empty.", "AI_SUMMARY_SKIP");
                    error_log("[AI_SUMMARY_ERROR] API Key is a placeholder or potentially invalid. Please set a valid API key.");
                    // Optionally, you could set $generatedAiSummary to an error message here or skip the API call.
                }
                $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=' . $apiKey; // Using gemini-1.5-flash-latest as an example
                // Construct a more detailed prompt using all available fields
                $prompt = "Generate a professional job description based on the following details:\n\n";
                $prompt .= "- Job Title: $title\n";
                if (!empty($company)) $prompt .= "- Company: $company\n";
                if (!empty($location)) $prompt .= "- Location: $location\n";
                if (!empty($experience) && $experience !== '0') {
                    // Use $experience which already holds custom_experience if 'other' was selected
                    $experienceText = $experience; 
                    if ($experienceText !== 'internship' && $experienceText !== '0' && strpos($experienceText, 'year') === false) $experienceText .= (is_numeric($experienceText) && $experienceText > 1 || $experienceText === '15+' || $experienceText === '20+') ? " years" : " year";
                    $prompt .= "- Experience Required: $experienceText\n";
                }
                if (!empty($type)) $prompt .= "- Job Type: $type\n";
                if (!empty($salary) && $salary !== '0') $prompt .= "- Salary: $salary\n";
                if ($vacant_positions > 1) $prompt .= "- Number of Vacancies: $vacant_positions\n";
                $prompt .= "- Key Responsibilities/Details: $description\n\n";
                   $prompt .= "Format the output with clear headings of Job Summary, Key Responsibilities and Requirements. Do not include contact information like emails or phone numbers, job title and location";
                // $prompt .= "The summary should be attractive to potential candidates and provide a clear overview of the role. Focus on the most important information. Do not include contact information like emails or phone numbers, summary, job title and location in this summary.";
                error_log("[AI_SUMMARY_DEBUG] Prompt being sent to API: " . $prompt);

                $data = ['contents' => [['parts' => [['text' => $prompt]]]]];
                $ch = curl_init($apiUrl);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                // For production, SSL verification should be enabled.
                // curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                // If you have SSL issues, you might need to specify a CA bundle:
                // curl_setopt($ch, CURLOPT_CAINFO, '/path/to/cacert.pem'); 
                // For now, keeping them disabled as per your original code for debugging, but this is a security risk.
                 curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); 
                 curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_close($ch);

                error_log("[AI_SUMMARY_DEBUG] API HTTP Code: $httpCode");
                error_log("[AI_SUMMARY_DEBUG] API Response: $response");
                if (!empty($curlError)) {
                    error_log("[AI_SUMMARY_ERROR] cURL Error: $curlError");
                }

                if ($httpCode === 200) {
                    $responseData = json_decode($response, true);
                    if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
                        $generatedAiSummary = $responseData['candidates'][0]['content']['parts'][0]['text'];
                        log_app_activity("AI summary generated successfully for job '$title'.", "AI_SUMMARY_SUCCESS");
                        error_log("[AI_SUMMARY_SUCCESS] Successfully extracted summary. Length: " . strlen($generatedAiSummary) . ". Start: " . substr($generatedAiSummary, 0, 100) . "...");
                    } elseif (isset($responseData['error'])) {
                        log_app_activity("AI summary generation FAILED for job '$title'. API Error: " . print_r($responseData['error'], true), "AI_SUMMARY_ERROR");
                        error_log('[AI_SUMMARY_ERROR] API returned an error: ' . print_r($responseData['error'], true));
                    } else { 
                        log_app_activity("AI summary generation FAILED for job '$title'. Unexpected API response structure.", "AI_SUMMARY_ERROR");
                        error_log('[AI_SUMMARY_ERROR] AI summary not found in expected path in API response. Is the response structure correct? Full response: ' . print_r($responseData, true)); 
                    }
                } else { 
                    log_app_activity("AI summary generation FAILED for job '$title'. API HTTP Code: $httpCode. Response: $response", "AI_SUMMARY_ERROR");
                    error_log("[AI_SUMMARY_ERROR] Gemini AI API error: HTTP Code $httpCode. Response: $response. cURL Error: $curlError"); 
                }
            } catch (Exception $e) { 
                error_log('[AI_SUMMARY_EXCEPTION] Error generating AI summary: ' . $e->getMessage()); 
            }        
        

        // Store all data in session for the review step, regardless of whether summary was generated
        $_SESSION['review_job_data'] = [
            'title' => $title, 'company' => $company, 'location' => $location,
            'description' => $description, 
            'experience' => $experienceValue, // Store the raw select value
            'custom_experience' => $custom_experience, // Store custom experience separately
            'type' => $type,
            'salary' => $salary, 'phones' => $phones, 'emails' => $emails,
            'vacant_positions' => $vacant_positions,
            'ai_summary' => trim($generatedAiSummary) // The newly generated summary (will be empty if not generated)
        ];

        // Redirect to the post_job view, which will now be in "review mode"
        log_app_activity("Job post by '$loggedInUsernameForLog' (Title: '$title') ready for review. AI Summary generated (length: " . strlen(trim($generatedAiSummary)) . ").", "JOB_POST_REVIEW_READY");
        header('Location: dashboard.php?view=post_job&step=review');
        exit();

    } elseif ($action === 'final_post') { // ... rest of the final_post logic
        // --- Step 2: Final Post - Save the job with potentially edited AI summary ---
        // The $aiSummary variable is already populated from $_POST['ai_summary'] at the top.
        // All other fields ($title, $company, etc.) are also repopulated from $_POST.

        // Basic validation for final post (though most fields should be pre-filled and non-empty)
        if (empty($title) || (empty($phones) && empty($emails))) {
            $_SESSION['admin_status'] = ['message' => 'Error: Required fields are missing for final submission.', 'type' => 'error'];
            log_app_activity("Final job post by '$loggedInUsernameForLog' (Title: '$title') failed: Required fields missing.", "JOB_POST_FINAL_VALIDATION_ERROR");
            // Re-populate form data for review again if something went wrong
            $_SESSION['review_job_data'] = $_POST; // Use current POST data
            header('Location: dashboard.php?view=post_job&step=review');
            exit();
        }

        // No need for duplicate check here again, as it was done in step 1.
        // If a strict check is needed again, it could be added, but might be redundant.

        // Prepare job data for saving
        $jobIdForLog = time() . '_' . rand(1000, 9999); // Generate ID before creating array to log it
        $jobData = [ // Keep this structure
            'id' => time() . '_' . rand(1000, 9999),
            'title' => $title, 'company' => $company, 'location' => $location,
            'description' => $description, // Original description
            'ai_summary' => $aiSummary,    // Potentially edited AI summary
            'experience' => $experience, 'type' => $type, 'salary' => $salary,
            'phones' => $phones, 'emails' => $emails, 'vacant_positions' => $vacant_positions,
            'posted_on' => date('Y-m-d H:i:s'),
            'posted_on_unix_ts' => time(),
            // Add the logged-in user's ID
            'posted_by_user_id' => $_SESSION['admin_username'] ?? null,
            'total_views_count' => 0, // Initialize new field
            'total_shares_count' => 0  // Initialize new field
        ];

        $jobsFile = __DIR__ . '/../data/jobs.json';
        $allExistingJobs = file_exists($jobsFile) ? json_decode(file_get_contents($jobsFile), true) : [];
        if (!is_array($allExistingJobs)) { $allExistingJobs = []; }

        array_unshift($allExistingJobs, $jobData);
        file_put_contents($jobsFile, json_encode($allExistingJobs, JSON_PRETTY_PRINT));

        unset($_SESSION['review_job_data']); // Clear review data from session
        log_app_activity("Job ID '{$jobData['id']}' (Title: '$title') posted successfully by '$loggedInUsernameForLog'.", "JOB_POST_SUCCESS");
        $_SESSION['admin_status'] = ['message' => 'Job posted successfully!', 'type' => 'success'];
         // --- AI Image Generation for Job Poster ---
         // --- WHATSAPP AUTOMATION INTEGRATION ---
        // This block is copied and adapted from actions/process_job_post.php
        $whatsappEnabled = true; // Make this configurable
        if ($whatsappEnabled) {
            // Use the specific Group ID for reliability
            $targetWhatsappGroupId = '120363401972837358@g.us'; // Specific Group ID for "Auto Post Job"

            if (empty($targetWhatsappGroupId)) { // Should not be empty if hardcoded, but good practice
                error_log("WhatsApp Automation (from post_job.php): Target WhatsApp Group ID is not configured. Skipping message for job ID '{$jobData['id']}'.");
                if (isset($_SESSION['admin_status']['message'])) {
                     $_SESSION['admin_status']['message'] .= ' (WhatsApp notification skipped: Target Group Name not configured)';
                }
            } else {
                // Construct the link to the job detail page on your website
                $jobDisplayLink = rtrim(APP_BASE_URL, '/') . '/job_detail.php?id=' . $jobData['id'];
                
                // Format the message for WhatsApp
                $whatsappMessage = "📢 *New Job Opportunity Posted!* 📢\n\n" .
                                   "✨ *Title:* " . ($jobData['title'] ?? 'N/A') . "\n" .
                                   "🏢 *Company:* " . ($jobData['company'] ?? 'N/A') . "\n" .
                                   "📍 *Location:* " . ($jobData['location'] ?? 'N/A') . "\n";
                if (!empty($jobData['type'])) { // Assuming 'type' is the job type field in $jobData
                    $whatsappMessage .= "⏰ *Type:* " . $jobData['type'] . "\n";
                }
                // Use ai_summary if available and not empty, otherwise fallback to a snippet of description
                $descriptionSnippet = !empty($jobData['ai_summary']) ? $jobData['ai_summary'] : substr(strip_tags($jobData['description'] ?? ''), 0, 150) . "...";
                $whatsappMessage .= "\n📝 *Summary:* " . $descriptionSnippet . "\n\n" .
                                   "🔗 *Apply Here & More Info:* " . $jobDisplayLink . "\n\n" .
                                   "Good luck! 🚀";

                // URL to your whatsapp_manager.php script
                $whatsappManagerUrl = rtrim(APP_BASE_URL, '/') . '/admin/whatsapp_manager.php';

                $postDataForWhatsApp = [
                    'action' => 'send_whatsapp_message',
                    'target_identifier' => $targetWhatsappGroupId, // Use the Group ID
                    'message' => $whatsappMessage,
                ];
                
                error_log("WhatsApp Automation: Attempting to call whatsapp_manager.php at URL: " . $whatsappManagerUrl); // Add this line

               // ...
$ch_wa = curl_init();
curl_setopt($ch_wa, CURLOPT_URL, $whatsappManagerUrl);
curl_setopt($ch_wa, CURLOPT_POST, true);
curl_setopt($ch_wa, CURLOPT_POSTFIELDS, http_build_query($postDataForWhatsApp));
curl_setopt($ch_wa, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch_wa, CURLOPT_TIMEOUT, 45);
curl_setopt($ch_wa, CURLOPT_CONNECTTIMEOUT, 10); // Timeout for the connection phase (seconds)
curl_setopt($ch_wa, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4); // Force IPv4 resolution

// --- Start Enhanced Debugging for cURL ---
curl_setopt($ch_wa, CURLOPT_VERBOSE, true);
$verbose_log_wa = fopen('php://temp', 'w+'); // Capture verbose output to a temporary stream
curl_setopt($ch_wa, CURLOPT_STDERR, $verbose_log_wa);
// --- End Enhanced Debugging for cURL ---

// --- Start Pre-cURL Call Logging ---
error_log("[post_job.php] WhatsApp Notification: Attempting cURL call.");
error_log("[post_job.php] Target URL: " . $whatsappManagerUrl);
error_log("[post_job.php] POST Data: " . http_build_query($postDataForWhatsApp));
$urlPartsForLog = parse_url($whatsappManagerUrl);
if (isset($urlPartsForLog['scheme']) && $urlPartsForLog['scheme'] === 'https') {
    if (defined('APP_ENV') && APP_ENV === 'development' && ($urlPartsForLog['host'] === 'localhost' || $urlPartsForLog['host'] === '127.0.0.1')) {
        error_log("[post_job.php] SSL verification bypass is ON for this local HTTPS request.");
    } else {
        error_log("[post_job.php] SSL verification bypass is OFF. Standard SSL verification will apply for this HTTPS request.");
    }
} else {
    error_log("[post_job.php] This is an HTTP request. No SSL verification applicable.");
}
// --- End Pre-cURL Call Logging ---
if (isset($_COOKIE[session_name()])) {
    curl_setopt($ch_wa, CURLOPT_COOKIE, session_name() . '=' . $_COOKIE[session_name()]);
}

// --- Adjust SSL verification bypass for local development HTTPS ---
// --- SSL Verification Handling for cURL ---
$urlPartsWA_post = parse_url($whatsappManagerUrl); // Use a unique variable name to avoid scope issues if included elsewhere
$isLocalHttpsRequest_post = (isset($urlPartsWA_post['scheme']) && $urlPartsWA_post['scheme'] === 'https' &&
                           isset($urlPartsWA_post['host']) && ($urlPartsWA_post['host'] === 'localhost' || $urlPartsWA_post['host'] === '127.0.0.1'));

if ($isLocalHttpsRequest_post) {
    // Unconditionally disable SSL verification for https://localhost or https://127.0.0.1
    error_log("[post_job.php] cURL Info: Local HTTPS detected for URL: {$whatsappManagerUrl}. Disabling SSL peer/host verification.");
    curl_setopt($ch_wa, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch_wa, CURLOPT_SSL_VERIFYHOST, false);
} elseif (defined('APP_ENV') && APP_ENV === 'development' && isset($urlPartsWA_post['scheme']) && $urlPartsWA_post['scheme'] === 'https') {
    // For other HTTPS URLs in a development environment (e.g., https://myproject.local with self-signed cert)
    error_log("[post_job.php] cURL Info: Development environment HTTPS detected for URL: {$whatsappManagerUrl} (APP_ENV=development). Disabling SSL peer/host verification.");
        curl_setopt($ch_wa, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch_wa, CURLOPT_SSL_VERIFYHOST, false);
}
// --- End SSL Verification Handling ---


                $responseJson_wa = curl_exec($ch_wa);
                $httpCode_wa = curl_getinfo($ch_wa, CURLINFO_HTTP_CODE);
                $curlError_wa = curl_error($ch_wa);

                // --- Retrieve and log verbose output ---
                rewind($verbose_log_wa);
                $verboseOutput_wa = stream_get_contents($verbose_log_wa);
                fclose($verbose_log_wa);
                if (!empty($verboseOutput_wa)) {
                    error_log("WhatsApp Automation cURL VERBOSE (from post_job.php) for job ID '{$jobData['id']}': " . trim($verboseOutput_wa));
                }
                // --- End Retrieve and log verbose output ---

                curl_close($ch_wa);

                if ($curlError_wa) {
                    // Log the specific cURL error, the URL, and the HTTP code if available
                    error_log("WhatsApp Automation cURL Error (from post_job.php) for job ID '{$jobData['id']}': " . $curlError_wa . ". URL: " . $whatsappManagerUrl . ". HTTP Code: " . $httpCode_wa);
                    $_SESSION['admin_status']['message'] .= ' (WhatsApp notification failed: cURL error - please check server logs for details)';
                } else {
                    $waResponse = json_decode($responseJson_wa, true);
                    if ($httpCode_wa === 200 && $waResponse && isset($waResponse['success']) && $waResponse['success']) {
                        log_app_activity("WhatsApp notification sent (from post_job.php) for job ID '{$jobData['id']}' to target '{$targetWhatsappGroupId}'. Controller: " . ($waResponse['message'] ?? 'OK'), "WHATSAPP_SENT");
                        $_SESSION['admin_status']['message'] .= ' (WhatsApp: ' . htmlspecialchars($waResponse['message'] ?? 'Notification sent.') . ')';
                    } else {
                        $waErrorMsg = $waResponse['message'] ?? 'Unknown WhatsApp controller error';
                        error_log("WhatsApp Automation Failed (from post_job.php) for job ID '{$jobData['id']}'. HTTP Code: {$httpCode_wa}. Controller Msg: " . $waErrorMsg . ". Raw Response: " . $responseJson_wa);
                        $_SESSION['admin_status']['message'] .= ' (WhatsApp notification failed: ' . htmlspecialchars($waErrorMsg) . ')';
                    }
                }
            }
        }
        // --- END WHATSAPP AUTOMATION INTEGRATION ---

        $posterImageStoragePath = __DIR__ . '/../data/job_posters/';
        if (!is_dir($posterImageStoragePath)) {
            if (!mkdir($posterImageStoragePath, 0777, true)) {
                log_app_activity("Failed to create job poster image directory: {$posterImageStoragePath}", "AI_IMAGE_ERROR");
                error_log("[AI_IMAGE_ERROR] Failed to create directory: {$posterImageStoragePath}");
            }
        }

        if (is_dir($posterImageStoragePath) && is_writable($posterImageStoragePath)) {
            $imageJobId = $jobData['id'];
// Refined prompt for image generation
            $imagePrompt = "Create a professional and visually appealing poster for a job opening. " .
                           "Job Title: '{$jobData['title']}'. " .
                           "Company: '{$jobData['company']}'. " .
                           "Location: '{$jobData['location']}'. " .
                           "Key elements to convey: opportunity, growth, modern workplace. " .
                           "Style: Clean, corporate, with a touch of innovation. " .
                           "Dominant colors: blues, greys, with an accent color like teal or orange. " .
                           "Include abstract representations of collaboration or technology if appropriate. Avoid text clutter.";
                        
            // Placeholder for actual AI image generation
            // In a real scenario, you would call an AI image generation API here.
            // For now, we'll simulate it and create a dummy file or just log.
            $generatedImagePath = $posterImageStoragePath . $imageJobId . '.png';
            $imageGenerationSuccess = false;

            // --- Replace this block with your actual AI Image Generation API call ---
            try {
                // Example: Simulate creating a placeholder image file
                // For a real implementation, you'd get image data from an API
                // and use file_put_contents($generatedImagePath, $imageDataFromApi);
                
               // Fallback: For now, we'll just log that the real API call is needed
                // and create an empty file as a placeholder.
                error_log("[AI_IMAGE_INFO] Placeholder: Actual AI image generation API call needed for job ID '{$imageJobId}'. Prompt: '{$imagePrompt}'");
                           // ... inside the try block ...
            error_log("[AI_IMAGE_INFO] Placeholder: Attempting to generate GD placeholder for job ID '{$imageJobId}'. Prompt: '{$imagePrompt}'");
            if (function_exists('imagecreatetruecolor')) {
                $width = 200; // Small placeholder
                $height = 100;
                $img = @imagecreatetruecolor($width, $height);
                if ($img) {
                    $bgColor = imagecolorallocate($img, 240, 240, 240); // Light grey
                    $textColor = imagecolorallocate($img, 50, 50, 50);   // Dark grey
                    imagefill($img, 0, 0, $bgColor);
                    imagestring($img, 3, 10, 40, "Placeholder", $textColor);
                    if (imagepng($img, $generatedImagePath)) {
                        $imageGenerationSuccess = true;
                    } else {
                        error_log("[AI_IMAGE_ERROR] GD: Failed to save PNG for job ID '{$imageJobId}'. Check path/permissions for {$generatedImagePath}");
                    }
                    imagedestroy($img);
                } else {
                     error_log("[AI_IMAGE_ERROR] GD: imagecreatetruecolor() failed for job ID '{$imageJobId}'.");
                }
            } else {
                error_log("[AI_IMAGE_INFO] GD library not available. Cannot create GD placeholder for job ID '{$imageJobId}'. No image file will be created by placeholder logic.");
                // $imageGenerationSuccess remains false
            }
            // ... rest of the try block ...

            } catch (Exception $e) {
                log_app_activity("Exception during AI image generation for job ID '{$imageJobId}': " . $e->getMessage(), "AI_IMAGE_ERROR");
                error_log("[AI_IMAGE_EXCEPTION] for job ID '{$imageJobId}': " . $e->getMessage());
            }
            // --- End of AI Image Generation API call block ---

            if ($imageGenerationSuccess) {
                log_app_activity("AI poster image generated successfully for job ID '{$imageJobId}' at '{$generatedImagePath}'. Prompt: '{$imagePrompt}'", "AI_IMAGE_SUCCESS");
                error_log("[AI_IMAGE_SUCCESS] Poster generated for job ID '{$imageJobId}'. Path: {$generatedImagePath}");
            } else {
                log_app_activity("AI poster image generation FAILED for job ID '{$imageJobId}'. Prompt: '{$imagePrompt}'", "AI_IMAGE_ERROR");
                error_log("[AI_IMAGE_ERROR] Failed to generate poster for job ID '{$imageJobId}'.");
            }
        } else {
            log_app_activity("Job poster image directory is not writable or does not exist: {$posterImageStoragePath}", "AI_IMAGE_ERROR");
            error_log("[AI_IMAGE_ERROR] Directory not writable/exists: {$posterImageStoragePath}");
        }
        // --- End AI Image Generation ---

        header('Location: dashboard.php?view=manage_jobs');
        exit();
    } else {
        // Invalid action
        log_app_activity("Invalid job posting action '$action' attempted by '$loggedInUsernameForLog'.", "JOB_POST_INVALID_ACTION");
        $_SESSION['admin_status'] = ['message' => 'Invalid job posting action.', 'type' => 'error'];
        header('Location: dashboard.php?view=post_job');
        exit();
    }

} else {
    // Not a POST request, or some other issue
    // If accessed directly, redirect to the initial post job form
    // This also handles clearing any stale review data if the user navigates away and back
    if (isset($_GET['view']) && $_GET['view'] === 'post_job' && (!isset($_GET['step']) || $_GET['step'] !== 'review')) {
        unset($_SESSION['review_job_data']); // Clear review data for a fresh form
        log_app_activity("Job post form accessed directly by '$loggedInUsernameForLog', review data cleared.", "JOB_POST_FORM_ACCESS");
    }
    // This redirect was missing in the original file if it's not a POST request.
    // It should redirect to the view that displays the form.
    header('Location: dashboard.php?view=post_job'); 
    exit();
}

?>
