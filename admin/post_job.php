<?php

// admin/post_job.php - Handles the job posting form submission

session_start(); // Start the session to access session variables

// Include configuration and helper functions
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/job_helpers.php';

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
            header('Location: dashboard.php?view=post_job');
            exit();
        }
        if (empty($phones) && empty($emails)) {
            $_SESSION['admin_status'] = ['message' => 'At least one contact method (phone or email) is required.', 'type' => 'error'];
            $_SESSION['form_data'] = $_POST;
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
                    if ($experienceText !== 'internship' && $experienceText !== '0' && !str_contains($experienceText, 'year')) $experienceText .= (is_numeric($experienceText) && $experienceText > 1 || $experienceText === '15+' || $experienceText === '20+') ? " years" : " year";
                    $prompt .= "- Experience Required: $experienceText\n";
                }
                if (!empty($type)) $prompt .= "- Job Type: $type\n";
                if (!empty($salary) && $salary !== '0') $prompt .= "- Salary: $salary\n";
                if ($vacant_positions > 1) $prompt .= "- Number of Vacancies: $vacant_positions\n";
                $prompt .= "- Key Responsibilities/Details: $description\n\n";
                $prompt .= "The summary should be attractive to potential candidates and provide a clear overview of the role. Focus on the most important information. Do not include contact information like emails or phone numbers, summary, job title and location in this summary.";
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
                        error_log("[AI_SUMMARY_SUCCESS] Successfully extracted summary. Length: " . strlen($generatedAiSummary) . ". Start: " . substr($generatedAiSummary, 0, 100) . "...");
                    } elseif (isset($responseData['error'])) {
                        error_log('[AI_SUMMARY_ERROR] API returned an error: ' . print_r($responseData['error'], true));
                    } else { 
                        error_log('[AI_SUMMARY_ERROR] AI summary not found in expected path in API response. Is the response structure correct? Full response: ' . print_r($responseData, true)); 
                    }
                } else { 
                    // Log the response body even for non-200, as it often contains useful error details from the API
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
        header('Location: dashboard.php?view=post_job&step=review');
        exit();

    } elseif ($action === 'final_post') { // ... rest of the final_post logic
        // --- Step 2: Final Post - Save the job with potentially edited AI summary ---
        // The $aiSummary variable is already populated from $_POST['ai_summary'] at the top.
        // All other fields ($title, $company, etc.) are also repopulated from $_POST.

        // Basic validation for final post (though most fields should be pre-filled and non-empty)
        if (empty($title) || (empty($phones) && empty($emails))) {
            $_SESSION['admin_status'] = ['message' => 'Error: Required fields are missing for final submission.', 'type' => 'error'];
            // Re-populate form data for review again if something went wrong
            $_SESSION['review_job_data'] = $_POST; // Use current POST data
            header('Location: dashboard.php?view=post_job&step=review');
            exit();
        }

        // No need for duplicate check here again, as it was done in step 1.
        // If a strict check is needed again, it could be added, but might be redundant.

        // Prepare job data for saving
        $jobData = [
            'id' => time() . '_' . rand(1000, 9999),
            'title' => $title, 'company' => $company, 'location' => $location,
            'description' => $description, // Original description
            'ai_summary' => $aiSummary,    // Potentially edited AI summary
            'experience' => $experience, 'type' => $type, 'salary' => $salary,
            'phones' => $phones, 'emails' => $emails, 'vacant_positions' => $vacant_positions,
            'posted_on' => date('Y-m-d H:i:s'),
            'posted_on_unix_ts' => time()
        ];

        $jobsFile = __DIR__ . '/../data/jobs.json';
        $allExistingJobs = file_exists($jobsFile) ? json_decode(file_get_contents($jobsFile), true) : [];
        if (!is_array($allExistingJobs)) { $allExistingJobs = []; }

        array_unshift($allExistingJobs, $jobData);
        file_put_contents($jobsFile, json_encode($allExistingJobs, JSON_PRETTY_PRINT));

        unset($_SESSION['review_job_data']); // Clear review data from session
        $_SESSION['admin_status'] = ['message' => 'Job posted successfully!', 'type' => 'success'];
        header('Location: dashboard.php?view=manage_jobs');
        exit();
    } else {
        // Invalid action
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
    }
    header('Location: dashboard.php?view=post_job');
    exit();
}

?>
                    ]
                ]
            ]
        ];

        // Initialize cURL
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);

        // Disable SSL verification (temporary fix)
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        // Execute the request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode === 200) {
            $responseData = json_decode($response, true);
            if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
                $aiSummary = $responseData['candidates'][0]['content']['parts'][0]['text'];
            } else {
                error_log('AI summary not found in response.');
            }
        } else {
            error_log("Gemini AI API error: HTTP Code $httpCode. Response: $response. cURL Error: $curlError");
        }
    } catch (Exception $e) {
        error_log('Error generating AI summary: ' . $e->getMessage());
    }


    // Save the job data (e.g., to a JSON file)
    $jobData = [
        'id' => time() . '_' . rand(1000, 9999), // Generate a unique ID
        'title' => $title,
        'company' => $company,
        'location' => $location,
        'description' => $description,
        'ai_summary' => trim($aiSummary), // Save the AI-generated summary
        'experience' => $experience,
        'type' => $type,
        'salary' => $salary,
        'phones' => $phones,
        'emails' => $emails,
        'vacant_positions' => $vacant_positions,
        'posted_on' => date('Y-m-d H:i:s'),
        'posted_on_unix_ts' => time()
    ];

    // Save to jobs.json
    // $existingJobs array is already loaded and checked
    // array_unshift($existingJobs, $jobData); // Add the new job to the beginning of the array
    // file_put_contents($jobsFile, json_encode($existingJobs, JSON_PRETTY_PRINT));
// $allExistingJobs is the full list, we add to this one
    array_unshift($allExistingJobs, $jobData); // Add the new job to the beginning of the full array
    file_put_contents($jobsFile, json_encode($allExistingJobs, JSON_PRETTY_PRINT));
 
    // Set success message and redirect to the dashboard
    $_SESSION['admin_status'] = [
        'message' => 'Job posted successfully!',
        'type' => 'success'
    ];
    header('Location: dashboard.php?view=manage_jobs');
    exit();
} else {
    // Invalid request method
    $_SESSION['admin_status'] = [
        'message' => 'Invalid job action.',
        'type' => 'error'
    ];
    header('Location: dashboard.php?view=post_job'); // Redirect if not a POST request or other issue
    exit();
}

?>
