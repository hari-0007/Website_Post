<?php

// admin/post_job.php - Handles the job posting form submission in a two-step process.

session_start(); // Start the session to access session variables

// Include configuration and helper functions
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/job_helpers.php';

$loggedInUsernameForLog = $_SESSION['admin_username'] ?? 'UnknownAdmin'; // For logging

// Check if the form was submitted via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'initial_post'; // Differentiate actions

    // --- Retrieve and sanitize form inputs (common for both steps) ---
    $title = trim($_POST['title'] ?? '');
    $company = trim($_POST['company'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $experienceValue = trim($_POST['experience'] ?? '0'); // Raw value from select
    $custom_experience = trim($_POST['custom_experience'] ?? ''); // Custom experience text

    $experience = $experienceValue; // Use the select value by default
    if ($experience === 'other' && !empty($custom_experience)) {
        $experience = $custom_experience; // Use the custom input value
    }
    $type = trim($_POST['type'] ?? 'Full Time'); // Default to Full Time
    $salary = trim($_POST['salary'] ?? ''); // Allow empty salary
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
       
        // Store all data in session for the review step
        $_SESSION['review_job_data'] = [
            'title' => $title, 'company' => $company, 'location' => $location,
            'description' => $description, 
            'experience' => $experienceValue, // Store the raw select value
            'custom_experience' => $custom_experience, // Store custom experience separately
            'type' => $type,
            'salary' => $salary, 'phones' => $phones, 'emails' => $emails,
            'vacant_positions' => $vacant_positions,
            'ai_summary' => trim($generatedAiSummary)
        ];

        // Redirect to the post_job view, which will now be in "review mode"
        log_app_activity("Job post by '$loggedInUsernameForLog' (Title: '$title') ready for review.", "JOB_POST_REVIEW_READY");
        header('Location: dashboard.php?view=post_job&step=review');
        exit();

    } elseif ($action === 'final_post') {
        // --- Step 2: Final Post - Save the job with potentially edited AI summary ---
        if (empty($title) || (empty($phones) && empty($emails))) {
            $_SESSION['admin_status'] = ['message' => 'Error: Required fields are missing for final submission.', 'type' => 'error'];
            log_app_activity("Final job post by '$loggedInUsernameForLog' (Title: '$title') failed: Required fields missing.", "JOB_POST_FINAL_VALIDATION_ERROR");
            $_SESSION['review_job_data'] = $_POST;
            header('Location: dashboard.php?view=post_job&step=review');
            exit();
        }

        // Prepare job data for saving
        $jobData = [
            'id' => time() . '_' . rand(1000, 9999),
            'title' => $title, 'company' => $company, 'location' => $location,
            'description' => $description, // Original description
            'ai_summary' => $aiSummary,    // Potentially edited AI summary
            'experience' => $experience, 'type' => $type, 'salary' => $salary,
            'phones' => $phones, 'emails' => $emails, 'vacant_positions' => $vacant_positions,
            'posted_on' => date('Y-m-d H:i:s'),
            'posted_on_unix_ts' => time(),
            'posted_by_user_id' => $_SESSION['admin_username'] ?? null,
            'total_views_count' => 0,
            'total_shares_count' => 0
        ];

        $jobsFile = __DIR__ . '/../data/jobs.json';
        $allExistingJobs = file_exists($jobsFile) ? json_decode(file_get_contents($jobsFile), true) : [];
        if (!is_array($allExistingJobs)) { $allExistingJobs = []; }

        array_unshift($allExistingJobs, $jobData);
        file_put_contents($jobsFile, json_encode($allExistingJobs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        unset($_SESSION['review_job_data']); // Clear review data from session
        log_app_activity("Job ID '{$jobData['id']}' (Title: '$title') posted successfully by '$loggedInUsernameForLog'.", "JOB_POST_SUCCESS");

        // --- Prepare data for the share popup ---
        $shareData = [
            'title' => $jobData['title'],
            'company' => $jobData['company'],
            'vacancies' => $jobData['vacant_positions'],
            'experience' => $jobData['experience'],
            'salary' => $jobData['salary'],
            'description' => $jobData['ai_summary'],
            'url' => rtrim(APP_BASE_URL, '/') . '/job_detail.php?job_id=' . $jobData['id']
        ];
        $_SESSION['show_share_popup_data'] = $shareData;
        $successMessage = 'Job posted successfully! You can now share the opening.';

        // --- AI Image Generation ---
        $posterImageStoragePath = __DIR__ . '/../data/job_posters/';
        if (!is_dir($posterImageStoragePath)) {
            if (!mkdir($posterImageStoragePath, 0777, true)) {
                log_app_activity("Failed to create job poster image directory: {$posterImageStoragePath}", "AI_IMAGE_ERROR");
            }
        }

        if (is_dir($posterImageStoragePath) && is_writable($posterImageStoragePath)) {
            $imageJobId = $jobData['id'];
            $generatedImagePath = $posterImageStoragePath . $imageJobId . '.png';
            $imageGenerationSuccess = false;

            if (function_exists('imagecreatetruecolor')) {
                $width = 200; $height = 100;
                $img = @imagecreatetruecolor($width, $height);
                if ($img) {
                    $bgColor = imagecolorallocate($img, 240, 240, 240);
                    $textColor = imagecolorallocate($img, 50, 50, 50);
                    imagefill($img, 0, 0, $bgColor);
                    imagestring($img, 3, 10, 40, "Placeholder", $textColor);
                    if (imagepng($img, $generatedImagePath)) {
                        $imageGenerationSuccess = true;
                    }
                    imagedestroy($img);
                }
            } else {
                log_app_activity("GD library not available. Cannot create placeholder image for job ID '{$imageJobId}'.", "AI_IMAGE_WARNING");
                $successMessage .= ' (Note: Poster image not generated; GD library missing.)';
            }

            if ($imageGenerationSuccess) {
                log_app_activity("Placeholder poster image generated for job ID '{$imageJobId}'.", "AI_IMAGE_SUCCESS");
            } elseif (function_exists('imagecreatetruecolor')) {
                log_app_activity("Placeholder poster image generation FAILED for job ID '{$imageJobId}'.", "AI_IMAGE_ERROR");
            }
        } else {
            log_app_activity("Job poster image directory is not writable or does not exist: {$posterImageStoragePath}", "AI_IMAGE_ERROR");
            $successMessage .= ' (Warning: Could not save poster image, directory is not writable.)';
        }
        // --- End AI Image Generation ---

        // Set the final status message
        $_SESSION['admin_status'] = ['message' => $successMessage, 'type' => 'success'];

        // Redirect to the post job page to show the share modal
        header('Location: dashboard.php?view=post_job&posted=1');
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
    if (isset($_GET['view']) && $_GET['view'] === 'post_job' && (!isset($_GET['step']) || $_GET['step'] !== 'review')) {
        unset($_SESSION['review_job_data']); // Clear review data for a fresh form
        log_app_activity("Job post form accessed directly by '$loggedInUsernameForLog', review data cleared.", "JOB_POST_FORM_ACCESS");
    }
    header('Location: dashboard.php?view=post_job'); 
    exit();
}

?>
