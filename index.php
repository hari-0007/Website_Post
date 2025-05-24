<?php
session_start();

define('COOKIE_CONSENT_STATUS_NAME', 'cookie_consent_status');
define('USER_INTERESTS_COOKIE_NAME', 'user_job_interests');
define('USER_VIEWED_JOB_IDS_COOKIE_NAME', 'user_viewed_job_ids'); // New cookie for viewed job IDs
define('USER_UNIQUE_ID_COOKIE_NAME', 'user_unique_site_id'); // New cookie for unique user ID
define('MAX_USER_INTERESTS', 5); // Store up to 5 recent interests

// At the top of index.php
$default_cookie_options = [
    'path' => '/',
    'samesite' => 'Lax',
    'secure' => true, // Uncomment if always HTTPS
    'httponly' => false, // Default, as some cookies are read by JS
];

// When setting unique_visitor cookie:
setcookie('unique_visitor', '1', array_merge($default_cookie_options, ['expires' => time() + (24 * 60 * 60)]));

// When setting USER_INTERESTS_COOKIE_NAME:
setcookie(USER_INTERESTS_COOKIE_NAME, json_encode($userInterests), array_merge($default_cookie_options, ['expires' => time() + (30 * 24 * 60 * 60)]));


// --- MANAGE UNIQUE USER ID COOKIE ---
$currentUserUniqueID = $_COOKIE[USER_UNIQUE_ID_COOKIE_NAME] ?? null;
if (!$currentUserUniqueID) {
    $currentUserUniqueID = bin2hex(random_bytes(16)); // Generate a strong unique ID
    $uid_cookie_options = [
        'expires' => time() + (365 * 24 * 60 * 60), // Expires in 1 year
        'path' => '/',
        'samesite' => 'Lax',
        'secure' => true, // If using HTTPS
        'httponly' => true, // This ID might be needed by JS if you extend functionality
    ];
    setcookie(USER_UNIQUE_ID_COOKIE_NAME, $currentUserUniqueID, $uid_cookie_options);
    $_COOKIE[USER_UNIQUE_ID_COOKIE_NAME] = $currentUserUniqueID; // Make available for current script run
}

// --- BEGIN NEW COMPREHENSIVE COOKIE DATA LOGGING ---
$comprehensiveLogFilePath = __DIR__ . '/data/comprehensive_user_cookie_data_log.json';
$allUsersData = []; // Will store an associative array: user_id => user_data

// Read existing comprehensive log
if (file_exists($comprehensiveLogFilePath)) {
    $existingCompLogJson = file_get_contents($comprehensiveLogFilePath);
    if ($existingCompLogJson !== false && !empty($existingCompLogJson)) {
        $decodedCompLog = json_decode($existingCompLogJson, true);
        // Expecting an associative array (JSON object)
        if (is_array($decodedCompLog) && (empty($decodedCompLog) || array_keys($decodedCompLog) !== range(0, count($decodedCompLog) - 1))) {
            $allUsersData = $decodedCompLog;
        } else {
            error_log("Error decoding comprehensive_user_cookie_data_log.json or it's not an associative array. Starting fresh. Content: " . $existingCompLogJson);
            // If it's an old-style array of logs, we might want to discard or attempt migration. For now, start fresh.
            $allUsersData = [];
        }
    }
}

$currentLogEntry = [
    'last_updated_timestamp' => date('Y-m-d H:i:s')
];

// Conditionally add fields if they have data
$consentStatus = $_COOKIE[COOKIE_CONSENT_STATUS_NAME] ?? null;
if ($consentStatus !== null) $currentLogEntry['consent_status'] = $consentStatus;

$usernameFromCookie = $_COOKIE['username_cookie'] ?? null;
if ($usernameFromCookie !== null) $currentLogEntry['username'] = $usernameFromCookie;

$emailFromCookie = $_COOKIE['email_cookie'] ?? null;
if ($emailFromCookie !== null) $currentLogEntry['email'] = $emailFromCookie;

$phoneFromCookie = $_COOKIE['phone_cookie'] ?? null;
if ($phoneFromCookie !== null) $currentLogEntry['phone'] = $phoneFromCookie;

if (isset($_COOKIE['unique_visitor'])) $currentLogEntry['unique_visitor_cookie_present'] = true;

// Use raw $_GET['search'] for logging current search query, not the processed $search_param
$rawSearchQueryForLog = $_GET['search'] ?? null;
if ($rawSearchQueryForLog !== null && $rawSearchQueryForLog !== '') $currentLogEntry['current_search_query'] = $rawSearchQueryForLog;


if (isset($_SERVER['HTTP_USER_AGENT'])) $currentLogEntry['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
if (isset($_SERVER['REMOTE_ADDR'])) $currentLogEntry['ip_address_hash'] = md5($_SERVER['REMOTE_ADDR']);

// --- FETCH LOCATION INFO BASED ON IP ---
if (isset($_SERVER['REMOTE_ADDR'])) {
    $ip_address = $_SERVER['REMOTE_ADDR'];
    // Avoid lookups for local IPs like 127.0.0.1 or ::1 as they won't give meaningful geo-info
    if ($ip_address !== '127.0.0.1' && $ip_address !== '::1') {
        // Use a timeout for the API call to prevent long hangs
        $context = stream_context_create(['http' => ['timeout' => 3]]); // 3 second timeout
        $locationJson = @file_get_contents("http://ip-api.com/json/{$ip_address}?fields=status,message,country,countryCode,region,regionName,city,zip,lat,lon,timezone,isp,org,as,query", false, $context);

        if ($locationJson !== false) {
            $locationData = json_decode($locationJson, true);
            if ($locationData && isset($locationData['status']) && $locationData['status'] === 'success') {
                // We can store the whole response or pick specific fields
                // Only add location_info if the API call was successful
                $currentLogEntry['location_info'] = []; // Initialize to ensure it's an array if we add to it
                if (isset($locationData['query'])) $currentLogEntry['location_info']['query_ip'] = $locationData['query'];
                if (isset($locationData['country'])) $currentLogEntry['location_info']['country'] = $locationData['country'];
                if (isset($locationData['countryCode'])) $currentLogEntry['location_info']['countryCode'] = $locationData['countryCode'];
                if (isset($locationData['regionName'])) $currentLogEntry['location_info']['regionName'] = $locationData['regionName'];
                if (isset($locationData['city'])) $currentLogEntry['location_info']['city'] = $locationData['city'];
                if (isset($locationData['timezone'])) $currentLogEntry['location_info']['timezone'] = $locationData['timezone'];
                if (isset($locationData['isp'])) $currentLogEntry['location_info']['isp'] = $locationData['isp'];
                
                // If after all checks, location_info is still empty, don't add the key.
                if (empty($currentLogEntry['location_info'])) unset($currentLogEntry['location_info']);
            }
            // If API status is not 'success', or $locationJson is false, 'location_info' key is not added.
        }
        // If IP is local, 'location_info' key is not added.
    }
}

// Only attempt to fetch and log job interests if consent is explicitly accepted
if (isset($_COOKIE[COOKIE_CONSENT_STATUS_NAME]) && $_COOKIE[COOKIE_CONSENT_STATUS_NAME] === 'accepted') {
    $interestsFromCookieRaw = $_COOKIE[USER_INTERESTS_COOKIE_NAME] ?? '[]';
    $decodedInterestsForLog = null; // Initialize
    try {
        $decodedInterestsForLog = json_decode($interestsFromCookieRaw, true);
    } catch (Exception $e) {
        // $decodedInterestsForLog remains null or could be an error from json_decode if not caught by try-catch
        error_log("Exception decoding job interests cookie: " . $e->getMessage());
    }
    // Only add job_interests if it's a non-empty array
    if (is_array($decodedInterestsForLog) && !empty($decodedInterestsForLog)) {
        $currentLogEntry['job_interests'] = $decodedInterestsForLog;
    }
}

// Check if we have at least one of the key personal identifiers before logging
$hasKeyPersonalData = false;
if (!empty($currentLogEntry['username']) || 
    !empty($currentLogEntry['email']) || 
    !empty($currentLogEntry['phone']) ||
    !empty($currentLogEntry['location_info'])) { // location_info is only set if valid and non-empty
    $hasKeyPersonalData = true;
}

if ($hasKeyPersonalData) {
    // To add/update the current user's data at the "top" (conceptually)
    $updatedAllUsersData = [$currentUserUniqueID => $currentLogEntry]; // New entry first
    foreach ($allUsersData as $uid => $data) {
        if ($uid !== $currentUserUniqueID) { // Add old entries, excluding the one we just updated/added
            $updatedAllUsersData[$uid] = $data;
        }
    }
    $allUsersData = $updatedAllUsersData; // Replace old data with reordered data
    // error_log("[DEBUG] Data for user {$currentUserUniqueID} before saving: " . print_r($currentLogEntry, true)); 
    file_put_contents($comprehensiveLogFilePath, json_encode($allUsersData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
} else {
    // error_log("[DEBUG] Skipping log for user {$currentUserUniqueID} due to missing key personal data. Entry: " . print_r($currentLogEntry, true));
}
// --- END NEW COMPREHENSIVE COOKIE DATA LOGGING ---

// Record interests if consent is given
if (isset($_COOKIE[COOKIE_CONSENT_STATUS_NAME]) && $_COOKIE[COOKIE_CONSENT_STATUS_NAME] === 'accepted') {
    $userInterests = [];
    $currentInterestsRaw = isset($_COOKIE[USER_INTERESTS_COOKIE_NAME]) ? $_COOKIE[USER_INTERESTS_COOKIE_NAME] : '[]';
    try {
        $userInterests = json_decode($currentInterestsRaw, true);
        if (!is_array($userInterests)) { // Ensure it's an array after decoding
            $userInterests = [];
        }
    } catch (Exception $e) { $userInterests = []; error_log("Exception decoding interests cookie: " . $e->getMessage()); }

    // Determine what to record as an interest
    $interestToRecord = null;
    // Use the raw $_GET['search'] for recording interest, not the processed $search_param
    if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
        $interestToRecord = strtolower(trim($_GET['search']));
    } elseif (isset($_GET['type']) && !empty($_GET['type']) && strtolower($_GET['type']) !== 'all') {
        // Only record specific types, not "all"
        $interestToRecord = strtolower(trim($_GET['type']));
    }

    if ($interestToRecord) {
        // Remove the interest if it already exists to move it to the front (most recent)
        $userInterests = array_filter($userInterests, function($i) use ($interestToRecord) { return $i !== $interestToRecord; });
        array_unshift($userInterests, $interestToRecord); // Add new interest to the beginning
        $userInterests = array_slice($userInterests, 0, MAX_USER_INTERESTS); // Keep only the most recent N interests        
        $cookie_options = [
            'expires' => time() + (30 * 24 * 60 * 60), // 30 days
            'path' => '/',
            'samesite' => 'Lax' // PHP 7.3+
            // 'secure' => true, // If using HTTPS, uncomment this
            // 'httponly' => true, // If the cookie should not be accessible via JavaScript
        ];
        setcookie(USER_INTERESTS_COOKIE_NAME, json_encode($userInterests), $cookie_options);
        $_COOKIE[USER_INTERESTS_COOKIE_NAME] = json_encode($userInterests); // Make available for current script run
    }
}

// Set the default timezone to Indian Time (IST) for accurate date filtering
date_default_timezone_set('Asia/Kolkata');

// Path to your jobs data file
// Adjust the path relative to where this script is located
$filename = __DIR__ . '/data/jobs.json'; // Assuming data/ is in the same directory as index.php

$phpJobsArray = []; // For PHP's internal filtering and display
$jobsForJS = [];    // For passing to JavaScript with pre-calculated Unix timestamps
$decodedJobs = []; // Holds the pristine full list of jobs from JSON

if (file_exists($filename)) {
    $jsonData = file_get_contents($filename);
    $decodedJobs = json_decode($jsonData, true);
    if ($decodedJobs === null) { // Handle malformed JSON
        $decodedJobs = [];
        error_log("Frontend Error: Error decoding jobs.json: " . json_last_error_msg());
    }
    $phpJobsArray = $decodedJobs; // Use for PHP filtering
    if (!is_array($phpJobsArray)) {
        error_log("Frontend Error: decodedJobs from jobs.json is not an array. Forcing to empty array. Original data: " . print_r($decodedJobs, true));
        $phpJobsArray = [];
    }

    foreach ($decodedJobs as $job) {
        if (!is_array($job)) { // Ensure each item being processed is an array
            error_log("Skipping non-array item when preparing jobsForJS: " . print_r($job, true));
            continue;
        }
        $jobCopy = $job; // Work on a copy
        // Ensure posted_on_unix_ts is available or calculate it for JS
        if (!isset($jobCopy['posted_on_unix_ts']) || !is_numeric($jobCopy['posted_on_unix_ts']) || $jobCopy['posted_on_unix_ts'] <= 0) {
            if (isset($jobCopy['posted_on']) && is_string($jobCopy['posted_on'])) {
                $unix_ts = strtotime($jobCopy['posted_on']);
                $jobCopy['posted_on_unix_ts'] = ($unix_ts === false) ? 0 : $unix_ts;
            } else {
                $jobCopy['posted_on_unix_ts'] = 0; // Default if no valid posted_on string
            }
        }
        $jobsForJS[] = $jobCopy;
    }
} else {
     error_log("Frontend Error: Job data file not found: " . $filename);
}

// --- START: Logic to determine filter parameters ---
// This section determines the actual filter values to be used for querying and display.
$isAjaxRequestForResetCheck = isset($_GET['ajax']) && $_GET['ajax'] === '1';

// Date filter parameter
$filter_param_raw = $_GET['filter'] ?? 'all'; // Default to 'all' if not set
$filter_param = trim($filter_param_raw);
if ($filter_param === '' || !in_array($filter_param, ['1', '7', '30', 'all'])) { // Validate and default
    $filter_param = 'all';
}

// Job type parameter
$type_param_raw = $_GET['type'] ?? ''; // Default to empty (all types) if not set
$type_param = strtolower(trim($type_param_raw));
if ($type_param === 'all') { // Explicitly treat 'all' as empty string for "all types"
    $type_param = '';
}

// Initialize search parameter.
$search_param_raw = $_GET['search'] ?? ''; // Default to empty if not set
$search_param = trim($search_param_raw);
// Note: The JS for search submission correctly resets other filters.
// For direct URL loads or non-JS scenarios, $search_param will be used as is.

// Log the raw parameters received from GET for debugging
// error_log("[DEBUG] Raw GET params: search='{$_GET['search']}', filter='{$_GET['filter']}', type='{$_GET['type']}'");
// error_log("[DEBUG] Initialized params: search_param='{$search_param}', filter_param='{$filter_param}', type_param='{$type_param}'");
// --- END: Logic to determine filter parameters ---

// --- START: Logic to determine if this is a recommendations view ---
$isRecommendationsView = isset($_GET['recommendations']) && $_GET['recommendations'] === '1';

// Check if a specific job ID is requested to be expanded
$jobIdToExpandFromUrl = isset($_GET['job_id']) ? trim($_GET['job_id']) : null;
$singleJobView = false;

if ($jobIdToExpandFromUrl) {
    $foundJob = null;
    foreach ($phpJobsArray as $job) { // $phpJobsArray is currently the full list from $decodedJobs
        if (isset($job['id']) && $job['id'] === $jobIdToExpandFromUrl) {
            $foundJob = $job;
            break;
        }
    }
    $phpJobsArray = $foundJob ? [$foundJob] : []; // If found, phpJobsArray now contains only this job
    $singleJobView = (bool)$foundJob;
}

// Interest-based filtering logic
// This applies ONLY if no explicit search, type, or date filters are in the URL.
// It modifies $phpJobsArray directly if conditions are met.
$appliedInterestFilter = false;
if (!$singleJobView &&
    $isRecommendationsView && // Apply interest filter ONLY if recommendations=1 is explicitly set
    isset($_COOKIE[COOKIE_CONSENT_STATUS_NAME]) && $_COOKIE[COOKIE_CONSENT_STATUS_NAME] === 'accepted') {

    error_log("[DEBUG] Applying interest-based filtering.");
      $userWeightedInterests = []; // Key: interest term (lowercase), Value: weight

    // 1. Primary Interests from USER_INTERESTS_COOKIE_NAME (search terms, type filters)
    if (isset($_COOKIE[USER_INTERESTS_COOKIE_NAME])) {
        $primaryInterestTerms = json_decode($_COOKIE[USER_INTERESTS_COOKIE_NAME], true);
        if (is_array($primaryInterestTerms)) {
            foreach ($primaryInterestTerms as $term) {
                $processedTerm = strtolower(trim($term));
                if (!empty($processedTerm)) {
                    // Assign higher weight, ensure it takes precedence if term already exists with lower weight
                    $userWeightedInterests[$processedTerm] = max($userWeightedInterests[$processedTerm] ?? 0, 3); 
                }
            }
        }
    }

    // 2. Secondary Interests from USER_VIEWED_JOB_IDS_COOKIE
    if (isset($_COOKIE[USER_VIEWED_JOB_IDS_COOKIE_NAME])) {
        $viewedJobIds = json_decode($_COOKIE[USER_VIEWED_JOB_IDS_COOKIE_NAME], true);
        if (is_array($viewedJobIds) && !empty($decodedJobs)) { // $decodedJobs has all job details
            $jobsByIdLookup = [];
            foreach($decodedJobs as $jobEntry) { // Build a quick lookup map
                if (isset($jobEntry['id'])) {
                    $jobsByIdLookup[$jobEntry['id']] = $jobEntry;
                }
            }

            foreach (array_slice($viewedJobIds, 0, 5) as $jobId) { // Consider last 5 viewed jobs
                if (isset($jobsByIdLookup[$jobId])) {
                    $viewedJob = $jobsByIdLookup[$jobId];
                    // Add job title as an interest term (can be split into words if needed, but full title is simpler)
                    if (!empty($viewedJob['title'])) {
                        $titleTerm = strtolower(trim($viewedJob['title']));
                        if (!empty($titleTerm)) {
                             // Assign lower weight, only if not already a primary interest or if current weight is lower
                            $userWeightedInterests[$titleTerm] = max($userWeightedInterests[$titleTerm] ?? 0, 2);
                        }
                    }
                    // Add job type as an interest term
                    if (!empty($viewedJob['type'])) {
                        $typeTerm = strtolower(trim($viewedJob['type']));
                         if (!empty($typeTerm)) {
                            $userWeightedInterests[$typeTerm] = max($userWeightedInterests[$typeTerm] ?? 0, 1);
                        }
                    }
                }
            }
        }
    }
    
    error_log("[DEBUG] User Weighted Interests: " . print_r($userWeightedInterests, true));

    if (!empty($userWeightedInterests)) {
        $recommendedJobsWithScores = [];
       
        // IMPORTANT: Interest filter should operate on the *original full list* of jobs.
        $fullJobListForInterestFilter = $decodedJobs; // $decodedJobs is the pristine list from JSON.

        foreach ($fullJobListForInterestFilter as $job) {
            if (!is_array($job)) { 
                error_log("Skipping non-array job item in interest filter: " . print_r($job, true));
                continue;
            }
            $matchScore = 0;
            // Explicitly cast job fields to string and lowercase for safer comparison
            $jobTitle = strtolower((string)($job['title'] ?? ''));
            $jobCompany = strtolower((string)($job['company'] ?? ''));
            $jobLocation = strtolower((string)($job['location'] ?? ''));
            $jobTypeData = strtolower((string)($job['type'] ?? ''));
            $jobSummary = strtolower((string)($job['ai_summary'] ?? ''));
            
            // $matchScore = 0;
            // $matchedInterestsDetails = []; // For potential debugging or advanced scoring

           $jobDescription = strtolower((string)($job['description'] ?? '')); // Add description

            foreach ($userWeightedInterests as $interestTerm => $weight) {
              

                if ( 
                    (!empty($jobTitle) && stripos($jobTitle, $interestTerm) !== false) || 
                    (!empty($jobCompany) && stripos($jobCompany, $interestTerm) !== false) ||
                    (!empty($jobLocation) && stripos($jobLocation, $interestTerm) !== false) ||
                    (!empty($jobTypeData) && stripos($jobTypeData, $interestTerm) !== false) ||
                    (!empty($jobSummary) && stripos($jobSummary, $interestTerm) !== false) ||
                    (!empty($jobDescription) && stripos($jobDescription, $interestTerm) !== false)
                ) {
                    $matchScore += $weight;
                }
            }

            if ($matchScore > 0) {
                $job['recommendation_score'] = $matchScore;
                $recommendedJobsWithScores[] = $job;
            }
        }

        if (!empty($recommendedJobsWithScores)) {
            $appliedInterestFilter = true;
// Sort by recommendation score (descending), then by original job posting date (descending)
            usort($recommendedJobsWithScores, function ($a, $b) {
                $scoreA = $a['recommendation_score'] ?? 0;
                $scoreB = $b['recommendation_score'] ?? 0;
                if ($scoreA == $scoreB) {
                    return ($b['posted_on_unix_ts'] ?? 0) <=> ($a['posted_on_unix_ts'] ?? 0);
                }
                return $scoreB <=> $scoreA; // Higher score first
            });
            $phpJobsArray = $recommendedJobsWithScores; // Update $phpJobsArray with scored and sorted recommendations
            error_log("[DEBUG] Interest filter applied with scoring. Recommended job count: " . count($phpJobsArray));
               }
    }
}

// Filepath for the daily visitor counter
$visitorCounterFile = __DIR__ . '/data/daily_visitors.json';

// Function to record page view (unique visitor and total request)
function recordPageView($filePath) {
    // Get today's date
    $today = date('Y-m-d');
    $isNewUniqueVisitor = false;

    // Read the current data
    if (!file_exists($filePath)) {
        $visitorData = [];
    } else {
        $rawJson = file_get_contents($filePath);
        $visitorData = json_decode($rawJson, true);
        if (!is_array($visitorData)) $visitorData = [];
    }

    // Ensure today's entry is an array, converting old scalar values if necessary
    if (!isset($visitorData[$today]) || !is_array($visitorData[$today])) {
        $old_scalar_value = null;
        if (isset($visitorData[$today]) && is_numeric($visitorData[$today])) {
            $old_scalar_value = (int)$visitorData[$today];
        }
        // Initialize with new structure. If old value existed, it's effectively reset/upgraded.
        $visitorData[$today] = ['unique_visits' => 0, 'total_requests' => 0];
        if ($old_scalar_value !== null) {
            // If you want to preserve the old scalar as the initial unique_visit and total_request count for that day:
            // $visitorData[$today]['unique_visits'] = $old_scalar_value;
            // $visitorData[$today]['total_requests'] = $old_scalar_value;
            error_log("Upgraded scalar visitor data for date {$today} to new array structure.");
        }
    }

    // Increment total requests for today
    $visitorData[$today]['total_requests'] = ($visitorData[$today]['total_requests'] ?? 0) + 1;

    // Check for unique visitor (if consent given and cookie not set)
    if (isset($_COOKIE[COOKIE_CONSENT_STATUS_NAME]) && $_COOKIE[COOKIE_CONSENT_STATUS_NAME] === 'accepted' && !isset($_COOKIE['unique_visitor'])) {
        $visitorData[$today]['unique_visits'] = ($visitorData[$today]['unique_visits'] ?? 0) + 1;
        $isNewUniqueVisitor = true;
    }

    // Save the updated data back to the file
    file_put_contents($filePath, json_encode($visitorData));
    return $isNewUniqueVisitor; // Return true if it was a new unique visitor
}

// Check if the visitor is unique using a cookie (and has consented)
if (isset($_COOKIE[COOKIE_CONSENT_STATUS_NAME]) && $_COOKIE[COOKIE_CONSENT_STATUS_NAME] === 'accepted' && !isset($_COOKIE['unique_visitor'])) {
    $visitor_cookie_options = [
        'expires' => time() + (24 * 60 * 60), // 24 hours
        'path' => '/',
        'samesite' => 'Lax'
        // 'secure' => true, // If using HTTPS
        // 'httponly' => true,
    ];
    setcookie('unique_visitor', '1', $visitor_cookie_options);

    // Record the page view (this will handle unique visitor increment if applicable)
    recordPageView($visitorCounterFile);
} else {
    // If not a new unique visitor (or consent not given/cookie already set), still record the page request
    recordPageView($visitorCounterFile);
}

// Feedback alert
if (!empty($_SESSION['feedback_alert'])) {
    $msg = $_SESSION['feedback_alert'];
    echo "<script>alert(" . json_encode($msg) . ");</script>";
    unset($_SESSION['feedback_alert']);
}

// Info message for personalized listings
if ($appliedInterestFilter && empty($_SESSION['feedback_alert'])) { // Avoid overwriting feedback alert
    // $_SESSION['info_message'] = "Showing jobs based on your recent activity. Use filters or search to see all jobs.";
}


// Initialize $filteredJobs. This will be the array that gets progressively filtered.
// If interest filter was applied, $phpJobsArray is already narrowed. Otherwise, it's the full list (or single job).
$filteredJobs = $phpJobsArray; 
error_log("[DEBUG] Initial count for \$filteredJobs (before explicit filters): " . count($filteredJobs));


// These are the final variables used for filtering and display.
// They are derived from the _param variables determined earlier.
$jobType = strtolower($type_param);
$search = strtolower($search_param);
$filter = $filter_param;

// Apply explicit filters (type, search, date)
// These filters are applied sequentially to $filteredJobs.

// 1. Apply Job Type Filter (if a specific type is chosen)
if ($jobType !== '') { // Note: $jobType is already '' if 'all' was passed
    $filteredJobs = array_filter($filteredJobs, function ($job) use ($jobType) {
        if (!is_array($job)) { 
            error_log("Filtering out non-array job item in type filter: " . print_r($job, true));
            return false; 
        }
        if (!array_key_exists('type', $job) || $job['type'] === null) {
            return false; 
        }
        $currentJobTypeVal = $job['type'];
        if (!is_scalar($currentJobTypeVal)) {
            error_log("[TYPE FILTER DEBUG] Job 'type' is not scalar. Type: " . gettype($currentJobTypeVal) . ". Value: " . print_r($currentJobTypeVal, true) . ". ID/Title: " . ($job['id'] ?? $job['title'] ?? 'N/A'));
            return false;
        }
        return strtolower(trim((string)$currentJobTypeVal)) === $jobType;
    });
}
error_log("After type filter ('{$jobType}'): " . count($filteredJobs) . " jobs.");

// 2. Apply Search Filter (if search term is provided)
// This is applied *after* the type filter, to the result of the type filter.
if ($search !== '') {
    $tempJobs = [];
    foreach ($filteredJobs as $job) { // $filteredJobs here is potentially already type-filtered
        if (!is_array($job)) { 
            error_log("Skipping non-array job item in search filter: " . print_r($job, true));
            continue;
        }
        $jobTitle = (string)($job['title'] ?? '');
        $jobCompany = (string)($job['company'] ?? '');
        $jobLocation = (string)($job['location'] ?? '');
        if (
            (stripos(strtolower($jobTitle), $search) !== false) ||
            (stripos(strtolower($jobCompany), $search) !== false) ||
            (stripos(strtolower($jobLocation), $search) !== false)
        ) {
            $tempJobs[] = $job;
        }
    }
    $filteredJobs = $tempJobs;
}
error_log("After search filter ('{$search}'): " . count($filteredJobs) . " jobs.");

// 3. Apply Date Filter (if not 'all')
// This is applied *after* type and search filters.
if ($filter !== 'all') {
    $currentDate = time();
    $tempJobs = [];
    $daysToFilter = 0;
    if ($filter === '30') $daysToFilter = 30;
    elseif ($filter === '7') $daysToFilter = 7;
    elseif ($filter === '1') $daysToFilter = 1;

    if ($daysToFilter > 0) {
        // Specific logging for 7-day filter
        if ($filter === '7') {
            error_log("[7_DAY_FILTER_DEBUG] Current Time (Unix): " . $currentDate . " (" . date('Y-m-d H:i:s', $currentDate) . ")");
        }

        $cutoffDate = $currentDate - ($daysToFilter * 24 * 60 * 60);
        foreach ($filteredJobs as $job) { // $filteredJobs here is potentially type- AND search-filtered
            if (!is_array($job)) { 
                error_log("Skipping non-array job item in date filter: " . print_r($job, true));
                continue;
            }
            // Ensure 'posted_on_unix_ts' exists and is a valid positive number.
            // This field should have been reliably populated when $jobsForJS and $phpJobsArray were created.
            $jobTimestamp = $job['posted_on_unix_ts'] ?? 0; 
            if (!is_numeric($jobTimestamp) || $jobTimestamp <= 0) {
                 // This block is a fallback, but ideally posted_on_unix_ts is always correct.
                 error_log("[DATE_FILTER_WARN] Job ID '{$job['id']}' (Title: '{$job['title']}') missing or invalid 'posted_on_unix_ts'. Original value: " . ($job['posted_on_unix_ts'] ?? 'NOT SET') . ". Attempting to parse '{$job['posted_on']}'.");
                 if (isset($job['posted_on']) && is_string($job['posted_on'])) {
                    $parsed_ts = strtotime($job['posted_on']);
                    $jobTimestamp = ($parsed_ts === false) ? 0 : $parsed_ts;
                 } else {
                    $jobTimestamp = 0; // Still 0 if 'posted_on' is also unusable
                 }
            }
            
            if ($filter === '7') { // Log details only when the 7-day filter is active
                error_log("[7_DAY_FILTER_DEBUG] Job ID '{$job['id']}' (Title: '{$job['title']}') - JobTS: {$jobTimestamp} (" . ($jobTimestamp > 0 ? date('Y-m-d H:i:s', $jobTimestamp) : 'Invalid/Zero') . ", Original: '{$job['posted_on']}') vs CutoffTS: {$cutoffDate} (" . date('Y-m-d H:i:s', $cutoffDate) . ") - Included: " . (($jobTimestamp > 0 && $jobTimestamp >= $cutoffDate) ? 'Yes' : 'No'));
            }
            if ($jobTimestamp > 0 && $jobTimestamp >= $cutoffDate) { // Job must be on or after the cutoff date
                $tempJobs[] = $job;
            }
        }
        $filteredJobs = $tempJobs;
    }
}
error_log("After date filter ('{$filter}'): " . count($filteredJobs) . " jobs.");

// If it's a single job view, the $filteredJobs array should ideally contain only that job.
// The above filters might empty it if the single job doesn't match, which is okay.
// The $singleJobView flag primarily controls the display (e.g., "Show All Jobs" button).
if ($singleJobView) {
    // If we are in single job view, the $search, $filter, $jobType for display/links should reflect no active filtering
    // This ensures that if the user clicks "Show All Jobs", they go to a non-filtered list.
    // And the search box value is correctly empty.
    $search = ''; // This variable is used for the value attribute of the search input
    $filter = 'all';
    $jobType = ''; // This will be used for HTML output value for search input and hidden fields
} elseif (!$isAjaxRequestForResetCheck) { // If it's an initial page load/refresh (not AJAX)
    // Clear the search term that would be displayed in the input box
    $search = '';
}


// Sort jobs by posted date (descending)
usort($filteredJobs, function ($a, $b) {
    if (!is_array($a) || !is_array($b)) {
        error_log("Non-array item encountered in usort. A: " . print_r($a, true) . " B: " . print_r($b, true));
        if (is_array($a)) return -1; 
        if (is_array($b)) return 1;  
        return 0; 
    }
    return ($b['posted_on_unix_ts'] ?? 0) <=> ($a['posted_on_unix_ts'] ?? 0);
});

// Then: apply pagination
$limit = 10;
$page = max(1, intval($_GET['page'] ?? 1)); // Page number from URL
$totalJobs = count($filteredJobs);
$totalPages = ceil($totalJobs / $limit);
$offset = ($page - 1) * $limit;
$pagedJobs = array_slice($filteredJobs, $offset, $limit);

function formatAiSummary($summary) {
    $formatted = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $summary);
    $formatted = nl2br($formatted);
    return $formatted;
}
?>

<?php
// Start of the function to render job listings and pagination
function render_job_listings_and_pagination($pagedJobs, $singleJobView, $totalPages, $search, $filter, $jobType, $page, $isRecommendationsView = false) {
    ob_start(); 
?>
    <?php if(empty($pagedJobs)): ?>
        <p class="no-jobs-message">No matching jobs found for the current criteria.</p>
    <?php else: ?>
        <?php if ($singleJobView): ?>
            <a href="<?= strtok($_SERVER["REQUEST_URI"], '?') ?>" id="showAllJobsBtn" class="button">Show All Jobs</a>
        <?php endif; ?>

        <?php
        // Calculate the timestamp for 3 months ago once, before the loop
        $threeMonthsAgoTimestamp = strtotime('-3 months');
        foreach ($pagedJobs as $job):
            // Determine if the current job is expired for this iteration
            $isExpired = ($job['posted_on_unix_ts'] ?? 0) < $threeMonthsAgoTimestamp;
            ?>
        <div class="job-card" onclick="toggleJobDetails(this)" data-job-id="<?= htmlspecialchars($job['id'] ?? '') ?>">
            <!-- 2D Animated Icons for Job Card -->
            <div class="animated-icons-container">
                <span class="animated-icon icon-magnify" style="top: <?= rand(5, 95) ?>%; left: <?= rand(5, 95) ?>%; animation-delay: -<?= rand(0, 10) ?>s;">🔍</span>
                <span class="animated-icon icon-briefcase" style="top: <?= rand(5, 95) ?>%; left: <?= rand(5, 95) ?>%; animation-delay: -<?= rand(0, 10) ?>s;">💼</span>
                <span class="animated-icon icon-document" style="top: <?= rand(5, 95) ?>%; left: <?= rand(5, 95) ?>%; animation-delay: -<?= rand(0, 10) ?>s;">📄</span>
                <span class="animated-icon icon-profile" style="top: <?= rand(5, 95) ?>%; left: <?= rand(5, 95) ?>%; animation-delay: -<?= rand(0, 10) ?>s;">👤</span>
            </div>
            <!-- Job Card Title with Shadow -->
            <style>
                .job-card h3 { text-shadow: 1px 1px 3px rgba(0,0,0,0.15); }
            </style>
            <h3>
                <?= htmlspecialchars($job['title'] ?? 'N/A') ?>
                <?php if (!empty($job['vacant_positions']) && $job['vacant_positions'] > 1): ?>
                    <span style="font-size: 0.8rem; color: #777; font-weight: normal; margin-left: 10px; white-space: nowrap;">
                        (<?= htmlspecialchars($job['vacant_positions']) ?> vacancies)
                        </span>
                <?php endif; ?>
                <?php if ($isExpired): ?>
                    <span style="font-size: 0.8rem; color: #dc3545; font-weight: bold; margin-left: 10px; background-color: #f8d7da; padding: 2px 6px; border-radius: 4px;">
                        Expired
                    </span>
                <?php endif; ?>
            </h3>
            <p class="job-card-company-location" style="position: relative; z-index: 2;"> <!-- Ensure text is above icons -->
            <strong><?= htmlspecialchars($job['company'] ?? 'N/A') ?></strong> – <?= htmlspecialchars($job['location'] ?? 'N/A') ?><br>
            
            <p class="job-summary" style="margin-top: 5px; margin-bottom: 10px;"><?= formatAiSummary(substr($job['ai_summary'] ?? '', 0, 300)) ?><?php if(strlen($job['ai_summary'] ?? '') > 300) echo "..."; ?></p>

            <div class="job-details" style="display: none;">
                <div class="formatted-summary">
                    <?= formatAiSummary($job['ai_summary']?? 'N/A') ?>
                </div>
<div style="text-align: right; margin-top: 8px;"> <!-- Wrapper for right alignment -->
                    <span class="job-caution-alert" title="Important Security Advice" onclick="showJobCautionAlert(event)">⚠️</span>
                </div>
                        </div>
            
            <?php if (!empty($job['experience'])): ?>
                <p style="margin: 10px 0;">
                    <strong>🛠 Experience:</strong> 
                    <?php if (strtolower($job['experience']) === 'fresher' || strtolower($job['experience']) === 'internship'): ?>
                        <?= htmlspecialchars($job['experience']) ?>
                    <?php else: ?>
                        <?= htmlspecialchars($job['experience']) ?> years
                    <?php endif; ?>
                </p>
            <?php endif; ?>

            <?php if (!empty($job['salary'])): ?>
                <p style="margin: 10px 0;"><strong>💰 Salary:</strong> <?= htmlspecialchars($job['salary']) ?></p>
            <?php endif; ?>

            <?php if (!empty($job['phones'])): ?>
                <p style="margin: 10px 0;"><strong>📞 Phone:</strong>
                <?php if ($isExpired): ?>
                    <span class="blurred-text">05X-XXX-XXXX</span>
                    <?php else: ?>
                        <?php foreach (explode(',', $job['phones']) as $phone): ?>
                            <a href="tel:<?= htmlspecialchars(trim($phone)) ?>"><?= htmlspecialchars(trim($phone)) ?></a>&nbsp;
                        <?php endforeach; ?>
                <?php endif; ?>
                </p>

                <?php endif; ?>

            <?php if (!empty($job['emails'])): // Always show Email label if emails existed ?>
                <p style="margin-bottom: 15px;"><strong>📧 Email:</strong>
                <?php if ($isExpired): ?>
                    <span class="blurred-text">support@jobhunt.top</span>
                <?php else: ?>
                    <?php foreach (explode(',', $job['emails']) as $email): ?>
                        <a href="mailto:<?= htmlspecialchars(trim($email)) ?>"><?= htmlspecialchars(trim($email)) ?></a>&nbsp;
                    <?php endforeach; ?>
                <?php endif; ?>
                </p>
            <?php endif; ?>
            
            <small>Posted on <?= htmlspecialchars($job['posted_on'] ?? 'N/A') ?></small><br>
            <?php // Share button and Caution Alert can be on the same line or separate
            // We'll place the caution alert after the share button or directly after "Posted on" if no share button
            ?>
            

            <?php if (!$isExpired): ?>
                <button style="margin-top: 10px; margin-right: 10px;" class="share-button" onclick="shareJob('<?= htmlspecialchars($job['id'] ?? '') ?>', '<?= htmlspecialchars($job['title'] ?? '') ?>', '<?= htmlspecialchars($job['company'] ?? '') ?>'); event.stopPropagation();">Share</button>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!$singleJobView && $totalPages > 1): ?>
    <div class="pagination-container">
        <?php
        // Previous Page Link
        $baseQueryForPagination = "?search=" . urlencode($search) . "&filter=" . urlencode($filter) . "&type=" . urlencode($jobType);
        if ($isRecommendationsView) {
            $baseQueryForPagination .= "&recommendations=1";
        }

        // First Page Link
        if ($page > 1) {
            echo '<a href="' . $baseQueryForPagination . '&page=1" class="nav-arrow first-arrow"><span class="short-text">««</span><span class="long-text"> First</span></a>';
        } else {
            echo '<a href="#" class="nav-arrow first-arrow disabled"><span class="short-text">««</span><span class="long-text"> First</span></a>';
        }

        // Previous Page Link
        if ($page > 1) {
            echo '<a href="' . $baseQueryForPagination . '&page=' . ($page - 1) . '" class="nav-arrow prev-arrow"><span class="short-text">«</span><span class="long-text"> Previous</span></a>';
        } else {
            echo '<a href="#" class="nav-arrow prev-arrow disabled"><span class="short-text">«</span><span class="long-text"> Previous</span></a>';
        }

        ?>
        <div class="page-numbers">
            <?php
                // Standard Pagination Logic
                // Show fewer links by default to better suit mobile, will also affect desktop
                // but desktop usually has more space.
                $num_links_to_show = 3; // Max links to show (e.g., 1 ... current ... total)
                $num_central_links = 1; // Number of links around current page (just current page itself)

                if ($totalPages <= $num_links_to_show) {
                    // If total pages are few, show all pages
                    for ($i = 1; $i <= $totalPages; $i++) {
                        echo '<a href="' . $baseQueryForPagination . '&page=' . $i . '" class="' . ($i == $page ? 'current-page' : '') . '">' . $i . '</a>';
                    }
                } else {
                    // If total pages are many, show a window around the current page

                    // Calculate start and end of the central window (pages between 1 and $totalPages)
                    $start_central = $page - floor($num_central_links / 2);
                    $end_central = $page + ceil($num_central_links / 2) - 1;

                    // Adjust window if it goes out of bounds (needs to be within 2 and $totalPages-1)
                    if ($start_central < 2) {
                        $end_central = min($totalPages - 1, $end_central + (2 - $start_central));
                        $start_central = 2;
                    }
                    if ($end_central > $totalPages - 1) {
                        $start_central = max(2, $start_central - ($end_central - ($totalPages - 1)));
                        $end_central = $totalPages - 1;
                    }
                     // Ensure start_central is not greater than end_central if totalPages is very small (e.g. 3,4)
                    if ($start_central > $end_central) {
                        $start_central = $end_central; // Can lead to a single page in center or empty if totalPages is 2
                    }

                    // Show page 1
                    echo '<a href="' . $baseQueryForPagination . '&page=1" class="' . (1 == $page ? 'current-page' : '') . '">1</a>';

                    // Show ellipsis after page 1 if the central block doesn't start immediately after page 1
                    if ($start_central > 2) {
                        echo '<span class="ellipsis">...</span>';
                    }

                    // Show central pages
                    for ($i = $start_central; $i <= $end_central; $i++) {
                         echo '<a href="' . $baseQueryForPagination . '&page=' . $i . '" class="' . ($i == $page ? 'current-page' : '') . '">' . $i . '</a>';
                    }

                    // Show ellipsis before the last page if the central block doesn't end immediately before the last page
                    if ($end_central < $totalPages - 1) {
                        echo '<span class="ellipsis">...</span>';
                    }

                    // Show the last page (if totalPages > 1)
                    // The central loop never prints totalPages, so we always print it here if totalPages > 1
                    if ($totalPages > 1) {
                         echo '<a href="' . $baseQueryForPagination . '&page=' . $totalPages . '" class="' . ($totalPages == $page ? 'current-page' : '') . '">' . $totalPages . '</a>';
                    }
                }
            ?>

        </div>

        <?php
        // Next Page Link
        if ($page < $totalPages) {
            echo '<a href="' . $baseQueryForPagination . '&page=' . ($page + 1) . '" class="nav-arrow next-arrow"><span class="long-text">Next </span><span class="short-text">»</span></a>';
        } else {
            echo '<a href="#" class="nav-arrow next-arrow disabled"><span class="long-text">Next </span><span class="short-text">»</span></a>';
        }

        // Last Page Link
        if ($page < $totalPages) {
            echo '<a href="' . $baseQueryForPagination . '&page=' . $totalPages . '" class="nav-arrow last-arrow"><span class="long-text">Last </span><span class="short-text">»»</span></a>';
        } else {
            echo '<a href="#" class="nav-arrow last-arrow disabled"><span class="long-text">Last </span><span class="short-text">»»</span></a>';
        }
        ?>
    </div> <!-- closes pagination-container -->
    <?php endif; ?> <!-- closes if (!$singleJobView && $totalPages > 1) -->
<?php
    return ob_get_clean(); 
}

$isAjaxRequest = isset($_GET['ajax']) && $_GET['ajax'] === '1';
error_log("[REQUEST_INFO] URL: " . $_SERVER['REQUEST_URI'] . " | Is AJAX: " . ($isAjaxRequest ? "Yes" : "No"));
error_log("[REQUEST_INFO] Effective Filter Params for this request: type='{$jobType}', search='{$search}', filter='{$filter}', page='{$page}', recommendations='".($isRecommendationsView ? '1':'0')."'");
error_log("[REQUEST_INFO] Total filtered jobs (before pagination): " . count($filteredJobs) . ". Total pages: " . $totalPages);
error_log("[REQUEST_INFO] Jobs for paged view (before AJAX check): " . count($pagedJobs) . " jobs. SingleView: " . ($singleJobView ? 'Yes':'No'));

if ($isAjaxRequest) {
    error_log("[AJAX_RESPONSE] Preparing AJAX response. Paged jobs: " . count($pagedJobs) . ". TotalPages: " . $totalPages . ". Current Page: " . $page . ". First job (if any): " . ($pagedJobs[0]['title'] ?? 'N/A'));
    echo render_job_listings_and_pagination($pagedJobs, $singleJobView, $totalPages, $search, $filter, $jobType, $page, $isRecommendationsView);
    exit; 
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>UAE Job Portal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; margin:0; padding:0; background:var(--body-bg); color:#333; }
        .container { width:100%; }
        .job-card.job-card-active {
    box-shadow: 0 0 25px rgba(0, 0, 0, 0.15);  /* Secondary shadow for depth */
    transform: scale(1.01) translateY(-3px) translateZ(10px); 
    background: linear-gradient(to bottom, rgba(255,255,255,0.2) 0%, rgba(255,255,255,0) 30%), #fff; /* Brighter gloss when active */
    border-left-color: #005fa3; /* Highlight color */
}

        /* Styles for different bold job titles */
        .job-title-bold-style-1 {
            font-weight: 700; /* Standard bold */
        }
        .job-title-bold-style-2 {
            font-weight: 800; /* Extra bold - might need a font that supports this weight */
            /* Or, for more distinctness if font weights are limited: */
            /* font-family: 'Impact', Haettenschweiler, 'Arial Narrow Bold', sans-serif; */
        }

        .title-card {
            background: #ffffff; /* White background */
            background-color: var(--body-bg); /* New: Very light cool blue/off-white */
            color: #2c3e50;
            padding: 60px 20px; /* Increased padding */
            margin-bottom: 20px; /* Increased margin */
            text-align:center;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.07); /* Slightly softer, more diffused shadow */
            width: 100%;
            position: relative; /* For potential pseudo-elements or overlays */
            overflow: hidden;
            z-index: 1; /* Lower z-index than main content */
            transition: opacity 0.3s ease-out, transform 0.3s ease-out; /* Add transform to transition */
            transform-origin: center bottom; /* Set transform origin for potential rotation */
            border-radius: 12px; /* Add some rounded corners for a softer look */
        }
        .title-card h1 {
            margin:0 0 20px; /* Increased bottom margin */
            font-size: 40px; /* Slightly larger */
            color: #005fa3; /* Ensure heading is white */
            font-weight: 700; /* Maintain existing weight or adjust if needed for clarity */
            letter-spacing: -0.5px;
            position: relative; /* To ensure it's above animated icons if they overlap */
            z-index: 2;
            text-shadow: 0 1px 3px rgba(0,0,0,0.1); /* Subtle text shadow */
        }
        .title-card p {
            font-size: 19px; /* Slightly larger */
            color: #4a5568; /* Slightly softer dark grey */
            margin-bottom: 35px; /* More space */
            opacity: 0.95;
            max-width: 700px; /* Constrain width of paragraph for better line length */
            margin-left: auto;
            margin-right: auto;
            line-height: 1.6;
            position: relative; /* To ensure it's above animated icons */
            z-index: 2;
            line-height: 1.7; /* Increased line height */
        }
        .title-card .join-buttons {
            display:inline-flex;
            gap:12px; /* Slightly more gap */
            flex-wrap:wrap;
            justify-content:center;
            position: relative; /* To ensure it's above animated icons */
            z-index: 2;
        }

        .title-card .join-buttons a {
            color:#fff; text-decoration:none; padding:12px 22px; /* More padding for buttons */
            border-radius:6px; /* Slightly more rounded */
            transition: background-color 0.3s, border-color 0.3s;
            font-weight: 500; /* Clearer button text */
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .title-card .join-buttons a.whatsapp { background:#25D366; }
        .title-card .join-buttons a.telegram { background:#0088cc; } /* Corrected class name */
        .title-card .join-buttons a:hover {
            opacity:.85; /* Slightly more noticeable opacity change */
            transform: translateY(-2px); /* Slightly more lift */
            box-shadow: 0 4px 10px rgba(0,0,0,0.12); /* Enhanced shadow on hover */
        }

        /* 2D Animation Styles */
        .animated-icons-container {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 1; /* Behind text content */
            pointer-events: none; /* So icons don't interfere with clicks */
        }
        .animated-icon {
            position: absolute;
            font-size: 20px; /* Adjust size of icons */
            color: #005fa3; /* Primary blue for icons */
            opacity: 0.12; /* Default opacity slightly reduced for subtlety with new bg */
            animation: float 15s infinite ease-in-out alternate;
        }

        .animated-icon.icon-magnify { top: 15%; left: 10%; animation-duration: 17s; font-size: 32px; opacity: 0.15;} /* opacity adjusted */
        .animated-icon.icon-briefcase { top: 60%; left: 85%; animation-duration: 14s; transform: rotate(-15deg); opacity: 0.12;} /* opacity adjusted */
        .animated-icon.icon-document { top: 75%; left: 20%; animation-duration: 16s; opacity: 0.08; } /* opacity adjusted */
        .animated-icon.icon-profile { top: 20%; left: 70%; animation-duration: 18s; font-size: 30px; transform: rotate(10deg); opacity: 0.1; } /* opacity adjusted */
        .animated-icon.icon-graph { top: 40%; left: 40%; animation-duration: 13s; opacity: 0.07; font-size: 36px; } /* opacity adjusted */

        @keyframes float {
            0% {
                transform: translateY(0px) translateX(0px) rotate(0deg);
            }
            50% {
                transform: translateY(-15px) translateX(10px) rotate(5deg);
            }
            100% {
                transform: translateY(5px) translateX(-10px) rotate(-5deg);
            }
        }

        .content-wrapper { display:flex; gap:20px; padding:0 20px; }

        .sidebar { width:220px; background:var(--body-bg); padding:20px; border-radius:8px; box-shadow:0 2px 6px rgba(0,0,0,0.05); flex-shrink:0; }
        .sidebar h4 {
            margin-top: 20px;
            margin-bottom: 15px;
            color: #3498db;
            font-size: 18px;
        }
        .sidebar h4:first-of-type {
            margin-top: 0;
        }
        .sidebar a { display:block; color:#333; text-decoration:none; margin:8px 0; font-weight:500; }
        .sidebar a:hover { color:#2980b9; }

        .search-bar {
          margin:0 auto 20px auto;
          /* padding: 0; */ /* Kept as is, form will have padding */
          max-width: 800px;
        }
        .search-bar form { 
            display: flex; 
            width: 100%; /* Form takes full width of .search-bar */
            /* gap: 10px; */ /* No longer needed as button is removed */
            margin-bottom:0px; 
            background-color: var(--card-bg); /* Using card background for the form */
            padding: 0; /* Remove padding from form, input will handle it */
            border-radius: var(--border-radius, 8px); /* Use defined border-radius or a default */
            box-shadow: 0 2px 5px rgba(0,0,0,0.06); /* Softer shadow */
        }
        .search-bar input[type="text"] { 
            flex-grow: 1; 
            padding:10px 15px; /* Adjusted padding for a sleeker look */
            border:1px solid var(--border-color, #ced4da); 
            border-radius:inherit; /* Inherit border-radius from the form for a unified look */
            font-size: 1rem; 
            color: var(--text-color, #212529);
            background-color: #fff; /* Ensure input background is white */
            transition: border-color 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
            width: 100%; /* Ensure input takes full width of the form */
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%236c757d' viewBox='0 0 16 16'%3E%3Cpath d='M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 15px center;
            padding-right: 40px; /* Add padding to prevent text from overlapping the icon */
        }
        .search-bar input[type="text"]:focus {
            border-color: var(--primary-color, #005fa3); 
            box-shadow: 0 0 0 0.2rem rgba(0, 95, 163, 0.25); /* Focus shadow using primary color */
            outline: none;
        }

        .sidebar a.active-filter, a.active-filter {
    background-color: #005fa3; /* Your primary color */
    color: white;
    font-weight: bold;
    border-radius: 4px;
    padding-left: 10px; 
    padding-right: 10px;
}
.mobile-filters a.active-filter { /* Ensure mobile active filters also get the background */
    background-color: #005fa3 !important; /* Your primary color, use !important if needed to override */
    color: #fff !important; /* Ensure text is white */
    border-color: #005fa3; /* Match background or use a slightly darker shade like #004a80 */
}


        main {
            flex: 1;
            min-width: 0;
            background-color: var(--body-bg); /* Ensure main content has a background */
            position: relative; /* Needed for z-index stacking */
            z-index: 2;
        }

        /* Mobile Filters Specific Styles */
        .mobile-filters {
            display: none; /* Hidden by default */
            margin-left: -10px; /* Counteract .content-wrapper's left padding */
                margin-right: -10px; /* Counteract .content-wrapper's right padding */
                border-radius: 0;
            position: -webkit-sticky; /* For Safari */
            position: sticky;
            top: 0; /* Stick to the top of the viewport */
            z-index: 999; /* Ensure it's above other scrollable content in main */
            /* --- End Sticky behavior --- */
            gap: 8px; /* Space between filter buttons */
            padding: 10px; /* Padding around the filter bar */
            margin-bottom: 15px; /* Space below the filters */
            background-color: #fff; /* Crucial for sticky to not be transparent */
            border-radius: 6px; /* Optional: rounded corners */
            box-shadow: 0 2px 5px rgba(0,0,0,0.1); /* Slightly more pronounced shadow for sticky */
            overflow-x: auto; /* Enable horizontal scrolling */
            white-space: nowrap; /* Prevent items from wrapping to the next line */
        }

        .mobile-filters a {
            display: inline-block;
            padding: 5px 12px; /* Adjusted padding for a sleeker look */
            font-size: 12px;   /* Slightly smaller font */
            color: #495057;    /* Softer dark grey for text */
            background-color: #f8f9fa; /* Very light, clean grey */
            border: 1px solid #dee2e6; /* Standard light border */
            border-radius: 15px; /* Pill shape for a modern, professional look */
            text-decoration: none;
            transition: background-color 0.2s ease-in-out, color 0.2s ease-in-out, border-color 0.2s ease-in-out; /* Smooth transitions */
            line-height: 1.4; /* Helps with vertical text alignment */
            margin-bottom: 4px; /* Add a little space if they wrap, though less likely now */
        }

        .mobile-filters a:hover {
            background-color: #e9ecef; /* A bit darker on hover */
            border-color: #ced4da;     /* Slightly darker border on hover */
            color: #212529;            /* Darker text on hover for better contrast */
        }


        /* --- Responsive Styles --- */
        @media(max-width:768px){
            .content-wrapper { flex-direction: column; padding: 0 10px; /* Adjust padding for smaller screens */ }
            .sidebar {
                display: none; /* Hide sidebar on mobile */
            }
            .pagination-container {
                display: flex; /* Ensure it's a flex container */
                justify-content: flex-start; /* Align items to the start for horizontal scrolling */
                flex-wrap: nowrap;       /* Prevent wrapping to multiple lines */
                overflow-x: auto;        /* Allow horizontal scrolling if content overflows */
                padding-bottom: 8px;     /* Add some space for the scrollbar if it appears */
                align-items: center; /* Vertically align all direct children */
            }
            .pagination-container .page-numbers {
                margin: 0 5px; /* Less margin for numbers on mobile */
            }
            .pagination-container a { /* Affects page numbers and arrows on mobile */
                padding: 5px 10px; /* Reduce horizontal padding */
                margin: 0 2px;     /* Reduce horizontal margin */
                flex-shrink: 0;    /* Prevent links themselves from shrinking */
                white-space: nowrap; /* Ensure text within links doesn't wrap */

            }
            .search-bar {
                 margin-top: 15px; /* Adjust margin */
                 margin-bottom: 15px;
                 padding: 0 10px; /* Add horizontal padding */
                 /* max-width: 100%; */ /* .search-bar itself doesn't need max-width here */
            }
            .search-bar form {
                width: 100%; /* Ensure the form takes full width of .search-bar */
            }
            .search-bar input[type="text"], .search-bar button {
                padding: 10px 15px; /* Adjust padding for mobile */
                font-size: 0.95rem;
                background-position: right 10px center; /* Adjust icon position for mobile */
                padding-right: 35px; /* Adjust padding for icon on mobile */
            }
            .search-bar form { gap: 5px; }
            .job-card {
                padding: 10px; /* Adjust job card padding */
                box-shadow: 0 0 18px rgba(0, 0, 0, 0.10); /* More projected shadow for mobile */
            }
            .site-footer .footer-container { padding: 0 10px; /* Adjust footer padding */ }
            .mobile-filters {
                display: flex; /* Show and use flex for layout */
            }
             .title-card { padding: 20px 10px; /* Adjust title card padding */ }
             .job-card .share-button-container {
                margin-top: 15px;    /* Adjusted top margin for mobile */
                margin-bottom: 15px; /* Adjusted bottom margin for mobile */
            }
            .share-button {
                display: block;         /* Change to block to take full width */
                width: 100%;            /* Occupy full width of its container */
                box-sizing: border-box; /* Ensures padding is included within the 100% width */
                padding: 14px 15px;     /* Adjusted padding for mobile: more vertical, balanced horizontal */
                font-size: 1rem;        /* Slightly larger font for better readability and tap target on mobile */
                /* The text inside the button will naturally center. Explicit text-align: center; can be added if needed. */
            }
            .job-card.job-card-active {
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.20); /* Enhanced shadow for active mobile highlight */
                transform: translateY(-4px); /* Slightly more lift for active mobile card */
            }
        }

        @media(max-width:600px){
             .button, .search-bar button {width:100%; text-align:center;}
             .search-bar form { flex-direction: column; } /* Stack search inputs vertically */
             .search-bar input[type="text"], .search-bar button { width: 100%; } /* Full width for stacked elements */
             .title-card h1 { font-size: 24px; }
             .title-card p { font-size: 14px; }
             .modal-content { width: 95%; /* Make modal slightly wider on very small screens */ }
        }
        /* --- End Responsive Styles --- */

        .job-card {
            background: var(--body-bg);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px; /* Existing margin */
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08); /* Softer, slightly more pronounced shadow */
            cursor: pointer;
            transition: box-shadow 0.2s ease-in-out, transform 0.2s ease-in-out;
            position: relative; /* Needed for absolute positioning of animated icons */
            border-left: 4px solid transparent;
        }
        .job-card:hover { /* Slightly less Z lift for a more subtle hover */
            /* Slightly less Z lift for a more subtle hover */
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.12); /* Enhanced uniform shadow on hover */
            background: linear-gradient(to bottom, rgba(255,255,255,0.15) 0%, rgba(255,255,255,0) 40%), 
                        #fcfdff;
        }
        .job-card p {
            margin: 5px 0;
        }
        .job-card a {
            color: #007bff;
            text-decoration: none;
        }
        .job-card a:hover {
            text-decoration: underline;
        }
        .job-details {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
        }

        .job-summary {
            display: -webkit-box;
            -webkit-line-clamp: 5; /* Limit to 5 lines */
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .button { padding:10px 16px; background:#3498db; color:#fff; text-decoration:none; border-radius:5px; transition:0.3s; border: none; cursor:pointer;}
        .button:hover { background:#2980b9; }

        .modal { position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); display:flex; align-items:center; justify-content:center; z-index:1000; }
        .modal-content { background:#fff; padding:20px; border-radius:12px; text-align:center; position:relative; box-shadow: 0 4px 15px rgba(0,0,0,0.2); max-width: 90%; width: 400px; }
        .close { position:absolute; top:10px; right:20px; cursor:pointer; font-size:24px; }
        .join-now, .join-telegram { padding:10px 20px; border-radius:8px; text-decoration:none; display:inline-block; margin-top:10px; }
        .join-now { background:#25D366; color:#fff; }
        .join-telegram { background:#0088cc; color:#fff; }

        .feedback-message { margin-top: 10px; padding: 10px; border-radius: 5px; font-weight: lighter; font-style: italic; }
        .success { color: white; background-color: #28a745; }
        .error { color: white; background-color: #dc3545; }

        .site-footer {
            width:100%;
            background: #2c3e50; /* Dark, neutral professional background */
            color:#bdc3c7; /* Default light grey text for readability */
            padding:50px 20px 20px; /* Increased top padding */
            font-size: 0.9rem; /* Slightly smaller base font for footer */
            margin-top: 40px; /* More space above footer */
        }
        .footer-container { display:flex; flex-wrap:wrap; justify-content:space-between; gap:30px; padding:0 20px; max-width: 1200px; margin: 0 auto; }
        .footer-column { flex:1; min-width:200px; }
        .footer-column h4 {
            margin-bottom:18px; /* Adjusted margin */
            color: #fff; /* White headings for contrast */
            border-bottom: 1px solid #4a5a6a; /* Subtle separator */
            padding-bottom: 10px;
            font-size: 1.05em; /* Relative to footer base font */
        }
        .footer-column p { color:#bdc3c7; margin:8px 0; line-height: 1.6; }
        .footer-column a { color:#bdc3c7; text-decoration:none; margin:8px 0; display:block; transition: color 0.2s; }
        .footer-column a:hover { color:#fff; } /* Brighter link hover */
        .footer-column input, .footer-column textarea { width:100%; padding:10px; border-radius:5px; border:1px solid #4a5a6a; margin-bottom:10px; background:rgba(255,255,255,0.05); color:#fff; font-size:0.9em; }
        .footer-column input::placeholder, .footer-column textarea::placeholder { color:#95a5a6; }
        .footer-bottom { text-align:center; padding-top:25px; border-top:1px solid #4a5a6a; margin-top:25px; font-size:0.85em; color: #95a5a6; }

            .job-card .share-button-container { /* New container for centering */
            text-align: center;
            width: 100%;
            margin-top: 20px; /* Increased space above the button */
            margin-bottom: 10px; /* Space below the button */
        }

        .share-button {
            background-color: #005fa3; /* Primary theme blue, corrected hex */
            color: #fff;
            border: none;
            padding: 12px 28px;
            border-radius: 6px;
            cursor: pointer;
            display: inline-block;
            font-size: 0.95rem; /* Slightly larger font */
            font-weight: 500; /* Medium font weight */
            text-transform: uppercase; /* Makes it look more like a button */
            letter-spacing: 0.5px; /* Adds a bit of spacing between letters */
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2); /* Enhanced shadow for a more projected look */
            transition: background-color 0.2s ease-in-out, transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }
        .share-button:hover, .share-button:focus {
            background-color: #004a80; /* Darker shade on hover/focus */
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.25); /* Further enhanced shadow on hover/focus */
            transform: translateY(-3px); /* Slightly more pronounced lift effect */
            outline: none;
        }

        .caution-message-modal .close-caution-modal:hover {
            color: #777;
        }
        .caution-message-modal .modal-actions {
            text-align: right;
            margin-top: 20px;
        }
        .caution-message-modal .modal-actions button {
            padding: 8px 15px; background-color: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer;
        }
        .caution-message-modal.is-opening {
            animation: fadeInScaleUp 0.3s ease-out forwards;
        }
        .caution-message-modal.is-closing {
            animation: fadeOutScaleDown 0.3s ease-out forwards;
        }

        .caution-message-modal .modal-actions button.report-issue-btn {
            background-color: #dc3545; /* Red for report */
            color: white;
        }
        .caution-message-modal .modal-actions button.understood-btn,
        .caution-message-modal .modal-actions button.submit-report-btn {
            background-color: #007bff; /* Blue for primary actions */
            color: white;
        }
        .caution-message-modal .report-form-container {
            display: none; /* Hidden by default */
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        .caution-message-modal .report-form-container label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            font-size: 0.9em;
            color: #444;
        }
        .caution-message-modal .report-form-container input[type="text"],
        .caution-message-modal .report-form-container input[type="email"],
        .caution-message-modal .report-form-container textarea {
            width: 100%;
            padding: 8px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 0.9em;
        }
        .caution-message-modal .report-form-container textarea {
            resize: vertical;
            min-height: 60px;
        }
        .caution-message-modal #reportStatusMessage {
            margin-top: 10px;
            font-size: 0.9em;
        }
        .caution-message-modal #reportedJobInfo {
            font-style: italic;
            color: #555;
            margin-bottom: 10px;
            font-size: 0.9em;
        }

        /* Shake Animation Keyframes */
        @keyframes shakeAttentionPeriodic {
            /* Shake happens in the first 0.5s of the 1.5s cycle */
            0%, 33.33%, 100% { /* Start, end of shake, and end of cycle (rest) */
                transform: translateX(0) rotate(0deg);
            }
            3%, 27% { /* Small shake left */
                transform: translateX(-2px) rotate(-2deg);

            }
            6%, 24% { /* Small shake right */
                transform: translateX(2px) rotate(2deg);
           }
            9%, 21% { /* Medium shake left */
                transform: translateX(-3px) rotate(-3deg);
            }
            12%, 18% { /* Medium shake right */
                transform: translateX(3px) rotate(3deg);
            }
            15% { /* Peak shake */
                transform: translateX(-2px) rotate(-2deg);
            }
            /* From 33.33% to 100% is the rest period */
        }

        /* Cookie Consent Banner Styles */
        #cookieConsentBanner {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background: rgba(0,0,0,0.9);
            color: white;
            padding: 20px;
            text-align: center;
            z-index: 1001; /* Ensure it's above other content */
            box-shadow: 0 -2px 10px rgba(0,0,0,0.2);
            font-size: 14px;
        }
        #cookieConsentBanner p {
            margin: 0 0 15px;
            line-height: 1.6;
        }
        #cookieConsentBanner .button { /* Re-use existing .button style or define specific */
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            cursor: pointer;
            border: none;
            font-size: 14px;
        }
        #cookieConsentBanner #acceptCookieConsent {
            background: #28a745; /* Green for accept */
            color: white;
            margin-right: 10px;
        }
        /* Optional: Decline button style if you add one */
        /*
        #cookieConsentBanner #declineCookieConsent {
            background: #dc3545; // Red for decline
            color: white;
        }
        */
        /* Pagination Styles */
        .pagination-container {
            display: flex; /* Use flexbox for alignment */
            justify-content: center; /* Default to center alignment */
            align-items: center;
            margin: 20px 0; /* As per your example's container style */
            /* font-size will be inherited or can be set if needed, example uses default */
            flex-wrap: wrap; /* Spread out on mobile */
            }

        .pagination-container a {
            display: inline-block;
            padding: 5px 15px; /* As per your example */
            margin: 0 4px;     /* Increased side margin from 5px to 8px */
            background-color: #ddd; /* Default for non-current pages from your example */
            color: #000;       /* Default for non-current pages from your example */
            text-decoration: none;
            border-radius: 4px;
            /* No specific border defined in example, so background color acts as fill */
            /* No transition defined in example, for a direct style change */
            line-height: 1.4;
            /* No box-shadow in example */
        }
        .pagination-container a:hover,
        .pagination-container a:focus { /* Added focus state for accessibility */
            /* Example doesn't specify hover, so we can add a subtle one or leave as is */
            /* For a subtle hover, you might slightly darken #ddd, e.g., #ccc */
            background-color: #ccc; 
            color: #000;
            text-decoration: none; /* Ensure no underline on focus */
        }
        .pagination-container a.current-page {
            background-color: #005fa3; /* Your primary blue for current page */
            color: white;
            border-color: #005fa3;
            font-weight: bold;
             cursor: default; /* Indicate it's not clickable / already active */
            /* No box-shadow in example for current page */
        }
        .pagination-container a.current-page:hover,
        .pagination-container a.current-page:focus {
             /* Prevent hover/focus style change for current page */
            background-color: #005fa3; /* As per your example for current page */
            color: white;
            /* border-color will be inherited from .current-page */
        }
        .pagination-container .first-arrow { order: 1; }
        .page-content-blur {
        filter: blur(5px);
        transition: filter 0.3s ease-out;
        /* pointer-events: none; */ /* Optional: if you want to disable interaction with blurred content */
    }
        .blurred-text {
            filter: blur(4px);
            pointer-events: none; /* Optional: makes the blurred text not interactive */
            user-select: none; /* Optional: prevents text selection */
        }
        .job-caution-alert {
            display: inline-block; /* Keep it inline but allow margins/padding */
            padding: 5px 8px; /* Add some padding to make it look more like a button */
            background-color: #fff; /* Base color for the button */
            color: white; /* Text color for the emoji/icon */
            border-radius: 5px; /* Rounded corners */
            box-shadow: 0 2px 4px rgba(0,0,0,0.2), 0 1px 2px rgba(0,0,0,0.1) inset; /* 3D shadow effect */
            font-size: 1.1em;
            cursor: pointer;
            text-align: center;
            line-height: 1;
            transition: transform 0.1s ease-out, box-shadow 0.1s ease-out; /* For click effect */
            /* Animation: name duration timing-function delay iteration-count direction fill-mode play-state */
            animation: shakeAttentionPeriodic 2s ease-in-out infinite; 
        }
        .job-caution-alert:active { /* Simple pressed effect */
            transform: translateY(1px);
            box-shadow: 0 1px 2px rgba(0,0,0,0.25), 0 0px 1px rgba(0,0,0,0.15) inset;

        }
        .job-caution-alert:hover {
            color: #e0a800; /* Darker on hover */
                animation: shakeAttention 0.8s cubic-bezier(.36,.07,.19,.97) 1; /* Shake once on hover */

        }
        .caution-message-modal {
            display: none; /* Hidden by default */
            position: fixed;
            z-index: 1010; /* Above job cards, below main share modal if any */
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            width: 90%;
            max-width: 400px; /* Slightly narrower for a cleaner look */
            background-color: #ffffff; /* White background */
            color: #333; 
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            text-align: left;
            /* animation: fadeInScaleUp 0.3s ease-out forwards; */
            font-size: 0.95em;
        }
        .caution-message-modal h4 {
            margin-top: 0;
            color: #d9534f; /* Warning red for title */
            font-size: 1.1em;
            margin-bottom: 15px;
        }
        .caution-message-modal p { margin-bottom: 10px; line-height: 1.5; color: #555; }

        .caution-message-modal .close-caution-modal {
            float: right;
            font-size: 1.5rem;
            font-weight: bold;
            line-height: 1;
            color: #aaa;
            text-shadow: 0 1px 0 #fff;
            opacity: 0.7;
            cursor: pointer;
            border: none;
            background: transparent;
        }
        .pagination-container .prev-arrow { order: 2; }
        .pagination-container .page-numbers { order: 3; }
        .pagination-container .next-arrow { order: 4; }
        .pagination-container .last-arrow { order: 5; }

        .pagination-container a.nav-arrow {
            font-weight: bold;
            padding: 5px 10px; /* Slightly less horizontal padding for arrows */
        }
        .pagination-container a.disabled { /* Style for disabled Prev/Next links */
            color: #aaa;
            background-color: #eee;
            cursor: default;
            pointer-events: none; /* Make them unclickable */
        }
        .pagination-container .page-numbers { /* Container for just the page numbers */
            margin: 0 10px; /* Space around the number block */
             order: 2; /* Explicitly set order for mobile */
             }
            /* Mobile specific display for pagination numbers and ellipses */
            .page-link-desktop-only, .ellipsis-desktop-only {
                display: none !important; /* Hide desktop specific elements */
            }
            .page-link-mobile-only, .ellipsis-mobile-only {
                display: inline-block !important; /* Show mobile specific elements */
            }

            .pagination-container a.nav-arrow .long-text { display: none; } /* Hide long text on mobile */
            .pagination-container a.nav-arrow .short-text { display: inline; } /* Show short text on mobile */
             /* Classes for responsive pagination numbers */
        /* Removed old mobile/desktop specific page number display logic */
        .page-link-desktop-only, .ellipsis-desktop-only {
            /* display: inline-block; */ /* No longer used by PHP logic */
        }
        .page-link-mobile-only, .ellipsis-mobile-only {
            /* display: none; */ /* No longer used by PHP logic */
        }
        /* Style for "Page X of Y" display */
        .page-numbers .current-page-display {
            display: inline-block; /* Align better with sibling <a> tags */
            margin-left: 15px;     /* Space it from the last page number or ellipsis */
            padding: 5px 0;        /* Match vertical padding of <a> tags for alignment */
            color: #333;           /* Match general text color */
            font-size: 0.95em;     /* Slightly smaller but readable */
            white-space: nowrap;   /* Prevent "Page X of Y" from wrapping itself */
        }
        /* Style for ellipsis */
        .pagination-container .page-numbers .ellipsis {
            display: inline-block;
            padding: 5px 4px; /* Less padding than numbers */
            margin: 0 4px;    /* Match link margin */
            color: #555;       /* A neutral color */
        }
            .pagination-container a.nav-arrow {
                padding: 5px 8px;
        }

        /* Professional Share Modal Styles */
        .share-modal-overlay {
            display: none; /* Hidden by default */
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6); /* Darker, more professional overlay */
            z-index: 1005; /* Ensure it's on top */
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .share-modal-content {
            background-color: #fff;
            padding: 25px 30px; /* More padding */
            border-radius: 10px; /* Slightly more rounded */
            width: 100%;
            max-width: 480px; /* A bit wider for better spacing */
            box-shadow: 0 8px 25px rgba(0,0,0,0.15); /* Softer, more diffused shadow */
            position: relative;
            text-align: left; /* Default text alignment */
            animation: fadeInModal 0.3s ease-out;
        }

        @keyframes fadeOutScaleDown {
            from {
                opacity: 1;
                transform: translate(-50%, -50%) scale(1);
            }
            to {
                opacity: 0;
                transform: translate(-50%, -50%) scale(0.95);
            }
        }


        @keyframes fadeInModal {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Keyframes for Modal Animation */
        @keyframes fadeInScaleUp {
            from {
                opacity: 0;
                transform: translate(-50%, -50%) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translate(-50%, -50%) scale(1);
            }
        }
        .share-modal-close-button {
            position: absolute;
            top: 10px;
            right: 15px;
            background: transparent;
            border: none;
            font-size: 28px;
            line-height: 1;
            color: #888;
            cursor: pointer;
            padding: 5px;
        }
        .share-modal-close-button:hover {
            color: #333;
        }
        .share-modal-title {
            font-size: 1.4em;
            color: #333;
            margin-top: 0;
            margin-bottom: 8px;
            text-align: center;
        }
        .share-modal-job-title {
            font-size: 0.95em;
            color: #555;
            margin-bottom: 25px;
            text-align: center;
            font-style: italic;
        }
        .share-modal-options {
            display: grid;
            grid-template-columns: 1fr; /* Single column for now, can be 1fr 1fr for two columns */
            gap: 12px;
        }
        .share-option-button {
            display: flex; /* For aligning icon and text */
            align-items: center;
            justify-content: center; /* Center content if icons are uniform width */
            padding: 12px 15px;
            border-radius: 6px;
            text-decoration: none;
            color: #fff;
            font-size: 1em;
            border: none;
            cursor: pointer;
            transition: opacity 0.2s ease-in-out;
        }
        .share-option-button:hover {
            opacity: 0.85;
        }
        .share-option-button .share-icon {
            margin-right: 10px; /* Space between icon and text */
            font-size: 1.2em; /* Make icon slightly larger */
        }
        /* Specific Platform Colors - Add more as needed */
        .share-option-button.copy-link { background-color: #6c757d; } /* Bootstrap secondary grey */
        .share-option-button.whatsapp { background-color: #25D366; }
        .share-option-button.linkedin { background-color: #0077b5; }
        .share-option-button.email { background-color: #7f8c8d; } /* A neutral email color */

        /* AJAX Loading and Transition Styles */
        #job-listings-container.loading-content {
            opacity: 0.5; /* Dim the content while loading new data */
            transition: opacity 0.2s ease-in-out;
            position: relative; /* For spinner positioning */
        }

        #job-listings-container .loading-spinner {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            border: 5px solid #f3f3f3; /* Light grey */
            border-top: 5px solid #005fa3; /* Blue */
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            z-index: 10; /* Ensure spinner is above dimmed content */
        }

        @media(max-width:600px){
            #cookieConsentBanner { padding: 15px; }
            #cookieConsentBanner p { font-size: 13px; margin-bottom: 10px; }
            #cookieConsentBanner .button { width: auto; padding: 8px 15px; font-size: 13px; }
        }
    </style>
    <!-- Ensure the 3D background div doesn't interfere if not used, or styles it minimally -->
    <style>
        #title-card-3d-background {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0; /* Behind animated icons and text */
            pointer-events: none; /* If it's purely decorative and shouldn't capture mouse events */
        }
        /* Ensure the canvas created by Three.js fills the container */
        #title-card-3d-background canvas {
            display: block; /* Removes extra space below canvas */
            width: 100% !important; /* Override Three.js inline styles if necessary */
            height: 100% !important; /* Override Three.js inline styles if necessary */
        }
    </style>
</head>
<body>

    <div class="container">
        <div class="title-card">
            <!-- Layer 0: Potential 3D Background -->
            <div id="title-card-3d-background" class="title-card-animation-bg">
                <!-- The 3D scene will be rendered here by Three.js -->
            </div>
            <!-- Layer 1: 2D Animated Icons -->
            <div class="animated-icons-container">
                <!-- Replace emojis with actual font icons (e.g., Font Awesome) or SVGs -->
                <span class="animated-icon icon-magnify">🔍</span>
                <span class="animated-icon icon-briefcase">💼</span>
                <span class="animated-icon icon-document">📄</span>
                <span class="animated-icon icon-profile">👤</span>
                <span class="animated-icon icon-graph">📈</span>
            </div>
            <!-- Layer 2: Content -->
            <h1>🎯 Discover the Latest Jobs in UAE</h1>
            <p>Find fresh opportunities daily from top companies across UAE. Remote, onsite, and hybrid roles available.</p>
            <div class="join-buttons">
            <a href="https://whatsapp.com/channel/0029VbBMdgCI7BeBLRm1Au1I" target="_blank" class="whatsapp">Join WhatsApp</a>
                <a href="https://t.me/uaejobprofessionals" target="_blank" class="telegram">Join Telegram</a>
            </div>
        </div>

        <div class="content-wrapper">
            <aside class="sidebar">
                <h4>Job Filters</h4>
                <a href="?type=&filter=all&search=" class="<?= ($jobType === '' && $filter === 'all' && $search === '') ? 'active-filter' : '' ?>">📋 All Jobs (<span data-count-id="countAll">0</span>)</a>
                <a href="?type=remote&filter=<?= urlencode($filter) ?>&search=<?= urlencode($search) ?>" class="<?= ($jobType === 'remote') ? 'active-filter' : '' ?>">💻 Remote (<span data-count-id="countRemote">0</span>)</a>
                <a href="?type=onsite&filter=<?= urlencode($filter) ?>&search=<?= urlencode($search) ?>" class="<?= ($jobType === 'onsite') ? 'active-filter' : '' ?>">🏢 Onsite (<span data-count-id="countOnsite">0</span>)</a>
                <a href="?type=hybrid&filter=<?= urlencode($filter) ?>&search=<?= urlencode($search) ?>" class="<?= ($jobType === 'hybrid') ? 'active-filter' : '' ?>">🌐 Hybrid (<span data-count-id="countHybrid">0</span>)</a>
                <h4>Quick Filters</h4>
                <a href="?type=full time&filter=<?= urlencode($filter) ?>&search=<?= urlencode($search) ?>" class="<?= ($jobType === 'full time') ? 'active-filter' : '' ?>">🕐 Full-Time (<span data-count-id="countFullTime">0</span>)</a>
                <a href="?type=part time&filter=<?= urlencode($filter) ?>&search=<?= urlencode($search) ?>" class="<?= ($jobType === 'part time') ? 'active-filter' : '' ?>">⌛ Part-Time (<span data-count-id="countPartTime">0</span>)</a>
                <a href="?type=internship&filter=<?= urlencode($filter) ?>&search=<?= urlencode($search) ?>" class="<?= ($jobType === 'internship') ? 'active-filter' : '' ?>">🎓 Internships (<span data-count-id="countInternship">0</span>)</a>
                <a href="?type=developer&filter=<?= urlencode($filter) ?>&search=<?= urlencode($search) ?>" class="<?= ($jobType === 'developer') ? 'active-filter' : '' ?>">👨‍💻 Developer (<span data-count-id="countDeveloper">0</span>)</a>
                <h4>Date Posted</h4>
                <a href="?filter=30&type=<?= urlencode($jobType) ?>&search=<?= urlencode($search) ?>" class="<?= ($filter === '30') ? 'active-filter' : '' ?>">Past 30 Days (<span data-count-id="count30">0</span>)</a>
                <a href="?filter=7&type=<?= urlencode($jobType) ?>&search=<?= urlencode($search) ?>" class="<?= ($filter === '7') ? 'active-filter' : '' ?>">Past 7 Days (<span data-count-id="count7">0</span>)</a>
                <a href="?filter=1&type=<?= urlencode($jobType) ?>&search=<?= urlencode($search) ?>" class="<?= ($filter === '1') ? 'active-filter' : '' ?>">Past 24 Hours (<span data-count-id="count1">0</span>)</a>
            </aside>

            <main>
                <?php
                if (!empty($_SESSION['info_message'])) {
                    echo "<p style='text-align:center; padding: 10px; background-color: #e7f3fe; border: 1px solid #005fa3; color: #005fa3; border-radius: 5px; margin-bottom:15px;'>" . htmlspecialchars($_SESSION['info_message']) . "</p>";
                    unset($_SESSION['info_message']);
                }
                ?>
                <div class="search-bar">
                    <form method="GET" action="">
                        <input type="text" name="search" placeholder="Search by job title, company, or location" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                        <!-- If in single job view, a search should clear the single job view -->
                        <?php if ($singleJobView): ?>
                            <!-- No need for a hidden input, just don't include job_id in the form action -->
                        <?php endif; ?>
                        <!-- These hidden inputs are not strictly necessary if JS always constructs the full URL for search,
                             but they don't harm and can be useful if JS is disabled or for other form submissions.
                             However, for search bar submissions, JS will override these. -->
                        <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                        <input type="hidden" name="type" value="<?php echo htmlspecialchars($jobType); ?>">
                        <!-- <button type="submit">Search</button> --> <!-- Button Removed -->
                    </form>
                </div>
                
                <!-- Mobile Filters - Hidden on desktop, shown on mobile -->
                <div class="mobile-filters">
                    <a href="?recommendations=1&type=&filter=all&search=" class="<?= ($isRecommendationsView) ? 'active-filter' : '' ?>">🌟 Recommendations</a>
                    <a href="?type=&filter=all&search=" class="<?= ($jobType === '' && $filter === 'all' && $search === '' && !$isRecommendationsView) ? 'active-filter' : '' ?>">All Jobs (<span data-count-id="countAll">0</span>)</a>
                    <a href="?filter=1&type=<?= urlencode($jobType) ?>&search=<?= urlencode($search) ?>" class="<?= ($filter === '1') ? 'active-filter' : '' ?>">Past 24 Hours (<span data-count-id="count1">0</span>)</a>
                    <a href="?filter=7&type=<?= urlencode($jobType) ?>&search=<?= urlencode($search) ?>" class="<?= ($filter === '7') ? 'active-filter' : '' ?>">Past 7 Days (<span data-count-id="count7">0</span>)</a>
                    <a href="?filter=30&type=<?= urlencode($jobType) ?>&search=<?= urlencode($search) ?>" class="<?= ($filter === '30') ? 'active-filter' : '' ?>">Past 30 Days (<span data-count-id="count30">0</span>)</a>
                    <a href="?type=remote&filter=<?= urlencode($filter) ?>&search=<?= urlencode($search) ?>" class="<?= ($jobType === 'remote') ? 'active-filter' : '' ?>">Remote (<span data-count-id="countRemote">0</span>)</a>
                    <a href="?type=onsite&filter=<?= urlencode($filter) ?>&search=<?= urlencode($search) ?>" class="<?= ($jobType === 'onsite') ? 'active-filter' : '' ?>">Onsite (<span data-count-id="countOnsite">0</span>)</a>
                    <a href="?type=hybrid&filter=<?= urlencode($filter) ?>&search=<?= urlencode($search) ?>" class="<?= ($jobType === 'hybrid') ? 'active-filter' : '' ?>">Hybrid (<span data-count-id="countHybrid">0</span>)</a>
                    <a href="?type=full time&filter=<?= urlencode($filter) ?>&search=<?= urlencode($search) ?>" class="<?= ($jobType === 'full time') ? 'active-filter' : '' ?>">Full-Time (<span data-count-id="countFullTime">0</span>)</a>
                    <a href="?type=part time&filter=<?= urlencode($filter) ?>&search=<?= urlencode($search) ?>" class="<?= ($jobType === 'part time') ? 'active-filter' : '' ?>">Part-Time (<span data-count-id="countPartTime">0</span>)</a>
                    <a href="?type=internship&filter=<?= urlencode($filter) ?>&search=<?= urlencode($search) ?>" class="<?= ($jobType === 'internship') ? 'active-filter' : '' ?>">Internships (<span data-count-id="countInternship">0</span>)</a>
                    <a href="?type=developer&filter=<?= urlencode($filter) ?>&search=<?= urlencode($search) ?>" class="<?= ($jobType === 'developer') ? 'active-filter' : '' ?>">Developer (<span data-count-id="countDeveloper">0</span>)</a>
                </div>

                <div id="job-listings-container">
                    <?php 
                        // For non-AJAX requests, render the initial content
                        echo render_job_listings_and_pagination($pagedJobs, $singleJobView, $totalPages, $search, $filter, $jobType, $page, $isRecommendationsView);
                    ?>
                </div>
            </main>
        </div>
    </div>

    <footer class="site-footer">
        <div class="footer-container">
            <div class="footer-column">
                <h4>About UAE Jobs</h4>
                <p>Your go-to portal for real-time UAE opportunities—updated daily.</p>
            </div>
            <div class="footer-column">
                <h4>Explore</h4>
                <a href="?search=remote&filter=all">💻 Remote Jobs</a>
                <a href="?search=uae&filter=all">📍 UAE Jobs</a>
                <a href="contact-us.php">📩 Contact Us</a> 
                <a href="admin/dashboard.php" target="_blank">👤 Admin Login</a>
            </div>
            <div class="footer-column">
                <h4>Information</h4>
                <a href="about-us.php">🌟 About Us</a>
                <a href="privacy-policy.php">🔒 Privacy Policy</a>
                <a href="terms-of-service.php">📜 Terms of Service</a>
            </div>
            <div class="footer-column">
                <h4>Follow Channels</h4>
                <a href="https://t.me/uaejobprofessionals" target="_blank">📢 Telegram</a>
                <a href="https://whatsapp.com/channel/0029VbBMdgCI7BeBLRm1Au1I" target="_blank">📱 WhatsApp</a>
            </div>
            <div class="footer-column">
                <h4>Drop Your Message</h4>
                <form id="feedbackForm">
                    <input type="text" name="name" placeholder="Your Name" required>
                    <input type="email" name="email" placeholder="Your Email" required>
                    <textarea name="message" placeholder="Your Message" rows="3" required></textarea>
                    <div id="responseMsg" class="feedback-message" style="display: none;"></div>
                    <button type="submit" class="button">Send</button>
                </form>
            </div>
        </div>
        <div class="footer-bottom">
            &copy; <?= date('Y') ?> UAE Jobs Portal. All rights reserved.
        </div>
    </footer>

    <!-- Caution Message Modal HTML -->
    <div id="jobCautionModal" class="caution-message-modal">
        <button class="close-caution-modal" onclick="closeJobCautionAlert()">&times;</button>
        <h4>Important Security Advice</h4>
        <p>Be cautious! Reputable employers and agencies don't charge you for a job.</p>
        <p>If an agency demands fees, always check their license and, if possible, visit them in person before paying anything.</p>
        <div id="reportJobFormContainer" class="report-form-container">
            <p id="reportedJobInfo" style="display:none;"></p> <!-- For Job Title & Company -->
            <h5>Report This Job</h5>
            <label for="reportName">Your Name:</label>
            <input type="text" id="reportName" name="reportName">
            <label for="reportEmail">Your Email (for follow-up):</label>
            <input type="email" id="reportEmail" name="reportEmail">
            <label for="reportReason">Reason for Report:</label>
            <textarea id="reportReason" name="reportReason" rows="3" required placeholder="Please provide details..."></textarea>
            <div id="reportStatusMessage" style="display:none; margin-top:10px;"></div>
        </div>

<div class="modal-actions">
<button id="reportIssueBtn" class="report-issue-btn" onclick="toggleReportForm()">Report Issue</button>
            <button id="cautionUnderstoodBtn" class="understood-btn" onclick="closeJobCautionAlert()">Understood</button>
                </div>
    </div>

    <div id="telegramModal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close" onclick="closeTelegramModal()">&times;</span>
            <h4>Join our Telegram Channel</h4>
            <p>Stay updated with the latest job postings!</p>
            <a href="https://t.me/uaejobprofessionals" target="_blank" class="join-telegram button">Join Telegram</a>
        </div>
    </div>

    <div id="modal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close" onclick="closeWModal()">&times;</span>
            <h4>Join our WhatsApp Channel</h4>
            <p>Get job alerts directly on WhatsApp!</p>
            <a href="https://whatsapp.com/channel/0029VbBMdgCI7BeBLRm1Au1I" target="_blank" class="join-now button">Join WhatsApp</a>
        </div>
    </div>

    <!-- Share Modal HTML - Professional Look -->
    <div id="jobShareModal" class="share-modal-overlay">
        <div class="share-modal-content">
            <button class="share-modal-close-button" aria-label="Close share dialog">&times;</button>
            <h3 class="share-modal-title">Share this Job Opportunity</h3>
            <p class="share-modal-job-title">Job Title Will Appear Here</p>
            <div class="share-modal-options">
                <button id="copyJobLinkButton" class="share-option-button copy-link">
                    <span class="share-icon">🔗</span> Copy Link
                </button>
                <div id="copyLinkFeedback" style="text-align: center; margin-top: 10px; font-size: 0.9em; min-height: 1.2em;"></div> <!-- Feedback area -->
                <a id="shareViaWhatsApp" href="#" target="_blank" class="share-option-button whatsapp">
                    <span class="share-icon">📱</span> WhatsApp
                </a>
                <a id="shareViaLinkedIn" href="#" target="_blank" class="share-option-button linkedin">
                    <span class="share-icon">💼</span> LinkedIn
                </a>
                <a id="shareViaEmail" href="#" class="share-option-button email">
                    <span class="share-icon">✉️</span> Email
                </a>
                <!-- Add more platform buttons as needed -->
            </div>
        </div>
    </div>

    <div id="cookieConsentBanner" style="display:none;">
        <p>We use cookies to enhance your experience and show you personalized job listings. By clicking "Accept", you agree to our use of cookies. You can learn more in our <a href="privacy-policy.php" target="_blank" style="color:#7cceff; text-decoration:underline;">Privacy Policy</a>.</p>
        <button id="acceptCookieConsent" class="button">Accept</button>
        <!-- <button id="declineCookieConsent" class="button">Decline</button> -->
    </div>

    <script>
        // Embed all job data with PHP-generated Unix timestamps for JavaScript
        const allJobDataFromPHP = <?php echo json_encode($jobsForJS); ?>;

        let allJobPostsForCounts = []; // Renamed to avoid confusion, used specifically for counts

        // Cookie helper functions
        function setCookie(name, value, days) {
            let expires = "";
            if (days) {
                const date = new Date();
                date.setTime(date.getTime() + (days*24*60*60*1000));
                expires = "; expires=" + date.toUTCString();
            }
            // Added SameSite=Lax for modern browser compatibility and security
            document.cookie = name + "=" + (value || "")  + expires + "; path=/; SameSite=Lax";
        }

        function getCookie(name) {
            const nameEQ = name + "=";
            const ca = document.cookie.split(';');
            for(let i=0; i < ca.length; i++) {
                let c = ca[i];
                while (c.charAt(0)==' ') c = c.substring(1,c.length);
                if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
            }
            return null;
        }

        function openTelegramModal(){ document.getElementById('telegramModal').style.display='flex'; }
        function closeTelegramModal(){ document.getElementById('telegramModal').style.display='none'; }
        function openWModal(){ document.getElementById('modal').style.display='flex'; }
        function closeWModal(){ document.getElementById('modal').style.display='none'; }

        // --- Job Caution Alert Modal Logic ---
        const cautionModal = document.getElementById('jobCautionModal');
        const reportJobFormContainer = document.getElementById('reportJobFormContainer');
        const reportIssueBtn = document.getElementById('reportIssueBtn');
        const cautionUnderstoodBtn = document.getElementById('cautionUnderstoodBtn');
        const reportNameField = document.getElementById('reportName');
        const reportEmailField = document.getElementById('reportEmail');
        const reportReasonField = document.getElementById('reportReason');
        const reportStatusMessage = document.getElementById('reportStatusMessage');
        const reportedJobInfoElement = document.getElementById('reportedJobInfo');
        const mainPageContainer = document.querySelector('.container'); // Or document.body
        let currentJobIdForReport = null; // To store the job ID when caution is clicked

        function showJobCautionAlert(event) {
            event.stopPropagation(); // Prevent job card click-to-expand
            const jobCard = event.target.closest('.job-card');
            if (jobCard) {
                currentJobIdForReport = jobCard.dataset.jobId;
            } else {
                currentJobIdForReport = null; // Should not happen if icon is on card
            }
            resetReportForm(); // Reset form to initial state when modal opens
            
            if (cautionModal) {
                if (mainPageContainer) mainPageContainer.classList.add('page-content-blur');
                    cautionModal.style.display = 'block';
            }
        }
        function toggleReportForm() {
            if (reportJobFormContainer.style.display === 'none' || reportJobFormContainer.style.display === '') {
                reportJobFormContainer.style.display = 'block';
                // Display job title and company
                if (currentJobIdForReport && reportedJobInfoElement) {
                    const job = allJobDataFromPHP.find(j => j.id === currentJobIdForReport);
                    if (job) {
                        reportedJobInfoElement.textContent = `Reporting job: "${job.title || 'N/A'}" at "${job.company || 'N/A'}"`;
                        reportedJobInfoElement.style.display = 'block';
                    } else {
                        reportedJobInfoElement.textContent = `Reporting job ID: ${currentJobIdForReport}`;
                        reportedJobInfoElement.style.display = 'block';
                    }
                }
                reportIssueBtn.textContent = 'Submit Report';
                reportIssueBtn.onclick = submitJobReport; // Change action
                cautionUnderstoodBtn.textContent = 'Cancel'; // Change "Understood" to "Cancel"
                reportReasonField.focus();
            } else {
                // This case is now handled by submitJobReport or Cancel (resetReportForm)
            }
        }

        function submitJobReport() {
            const name = reportNameField.value.trim();
            const email = reportEmailField.value.trim();
            const reason = reportReasonField.value.trim();

            if (!reason) {
                reportStatusMessage.textContent = 'Please provide a reason for the report.';
                reportStatusMessage.style.color = 'red';
                reportStatusMessage.style.display = 'block';
                return;
            }

            const reportData = {
                job_id: currentJobIdForReport,
                reporter_name: name,
                reporter_email: email,
                reason: reason
            };

            reportStatusMessage.textContent = 'Submitting report...';
            reportStatusMessage.style.color = '#007bff'; // Blue for processing
            reportStatusMessage.style.display = 'block';

            fetch('report_job.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(reportData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    reportStatusMessage.textContent = 'Thank you! Your report has been submitted.';
                    reportStatusMessage.style.color = 'green';
                    reportJobFormContainer.style.display = 'none'; // Hide form
                    reportIssueBtn.style.display = 'none'; // Hide submit button
                    cautionUnderstoodBtn.textContent = 'Close'; 
                    cautionUnderstoodBtn.onclick = closeJobCautionAlert;
                } else {
                    reportStatusMessage.textContent = data.message || 'Failed to submit report. Please try again.';
                    reportStatusMessage.style.color = 'red';
                }
            })
            .catch(error => {
                console.error('Error submitting report:', error);
                reportStatusMessage.textContent = 'An error occurred while submitting your report.';
                reportStatusMessage.style.color = 'red';
            });
        }

        function resetReportForm() {
            if(reportJobFormContainer) reportJobFormContainer.style.display = 'none';
            if(reportIssueBtn) {
                reportIssueBtn.textContent = 'Report Issue';
                reportIssueBtn.onclick = toggleReportForm;
                reportIssueBtn.style.display = 'inline-block';
            }
            if(cautionUnderstoodBtn) {
                cautionUnderstoodBtn.textContent = 'Understood';
                cautionUnderstoodBtn.style.display = 'inline-block';
            }
            if(reportNameField) reportNameField.value = '';
            if(reportEmailField) reportEmailField.value = '';
            if(reportReasonField) reportReasonField.value = '';
            if(reportStatusMessage) {
                reportStatusMessage.textContent = '';
                reportStatusMessage.style.display = 'none';
                }
            if(reportedJobInfoElement) {
                reportedJobInfoElement.style.display = 'none';

            }
        }

        function closeJobCautionAlert() {
            if (cautionModal) {
                cautionModal.classList.remove('is-opening');
                cautionModal.classList.add('is-closing');
                // Listen for the end of the closing animation
                cautionModal.addEventListener('animationend', function handleAnimationEnd() {
                    cautionModal.style.display = 'none'; // Hide after animation
                    cautionModal.classList.remove('is-closing'); // Reset class
                    
                    if (mainPageContainer) mainPageContainer.classList.remove('page-content-blur');
                    resetReportForm(); // Always reset form state when modal closes

                    // Important: Remove the event listener to prevent it from firing multiple times
                    cautionModal.removeEventListener('animationend', handleAnimationEnd);
                }, { once: true }); // { once: true } ensures the listener is auto-removed after firing

            }
        }
        const shareModalElement = document.getElementById('jobShareModal');
        let shareModalJobTitleElement = null; // Initialize to null
        let shareModalCloseButton = null; // Initialize to null
        let copyFeedbackElement = null;

        if (shareModalElement) { // Only try to query children if the parent exists
            shareModalJobTitleElement = shareModalElement.querySelector('.share-modal-job-title');
            shareModalCloseButton = shareModalElement.querySelector('.share-modal-close-button');
            copyFeedbackElement = shareModalElement.querySelector('#copyLinkFeedback'); // Get the feedback element
        } else {
            console.error("Share Modal Element with ID 'jobShareModal' not found in the DOM!");
        }
        
        let currentJobUrlForModal = '';
        let currentJobTitleForModal = '';
        let currentJobCompanyForModal = '';

        function openProfessionalShareModal() {
            if (shareModalElement) {
                if (shareModalJobTitleElement) {
                    shareModalJobTitleElement.textContent = `${currentJobTitleForModal} at ${currentJobCompanyForModal}`;
                } else {
                    console.error("Share modal job title element (.share-modal-job-title) not found inside the modal!");
                }
                if (copyFeedbackElement) { // Clear previous feedback when modal opens
                    copyFeedbackElement.innerHTML = '';
                    copyFeedbackElement.style.color = ''; // Reset color
                }
                shareModalElement.style.display = 'flex';
            } else {
                console.error("Cannot open share modal: The main modal element ('jobShareModal') was not found.");
            }
        }
        function closeProfessionalShareModal() {
            if (shareModalElement) {
                shareModalElement.style.display = 'none';
                if (copyFeedbackElement) { // Clear feedback when modal closes
                    copyFeedbackElement.innerHTML = '';
                }
            }
        }

        if (shareModalCloseButton) {
            shareModalCloseButton.addEventListener('click', closeProfessionalShareModal);
        }
        // Close modal if user clicks on the overlay
        if (shareModalElement) {
            shareModalElement.addEventListener('click', function(event) {
                if (event.target === shareModalElement) {
                    closeProfessionalShareModal();
                }
            });
        }

        function shareJob(jobId, title, company) {
            // console.log(`shareJob called - ID: ${jobId}, Title: ${title}, Company: ${company}`);
            // Ensure title and company are strings, even if passed as null/undefined from PHP (though ?? '' should prevent this)
            const jobTitleStr = String(title || ''); 
            const jobCompanyStr = String(company || '');

            console.log(`shareJob called - ID: ${jobId}, Title: ${jobTitleStr}, Company: ${jobCompanyStr}`);

            if (!jobId || String(jobId).trim() === '') { // Check if jobId is null, undefined, or an empty/whitespace string
                const errorMsgBase = `Sorry, this job cannot be shared as its ID is missing.`;
                const jobIdentifierForMsg = jobTitleStr ? ` (Job: '${jobTitleStr}')` : '';
                console.error(`ShareJob Error: Job ID is missing or empty. Cannot generate share link for job titled: '${jobTitleStr}'. Please ensure all jobs in your data source (e.g., jobs.json) have a valid 'id'.`);
                alert(`${errorMsgBase}${jobIdentifierForMsg}. Please check the job data.`);
                return; // Stop further execution for this share attempt
            }
            const baseUrl = window.location.origin + window.location.pathname;

            // Increment share count
            fetch('increment_job_share.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ job_id: jobId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Share count incremented for job ID:', jobId);
                } else {
                    console.error('Failed to increment share count:', data.message);
                }
            })
            .catch(error => {
                console.error('Error sending share increment request:', error);
            });

            currentJobUrlForModal = `${baseUrl}?job_id=${encodeURIComponent(jobId)}`;
            // currentJobTitleForModal = title;
            // currentJobCompanyForModal = company;
            currentJobTitleForModal = jobTitleStr; // Use the processed string
            currentJobCompanyForModal = jobCompanyStr; // Use the processed string
            
            const shareText = `Check out this job: ${currentJobTitleForModal} at ${currentJobCompanyForModal}`;
            const encodedMainUrlForSharingPlatforms = encodeURIComponent(currentJobUrlForModal); // Renamed for clarity
            const encodedShareText = encodeURIComponent(shareText);
            const encodedJobTitle = encodeURIComponent(`${currentJobTitleForModal} at ${currentJobCompanyForModal}`);

            const copyButton = document.getElementById('copyJobLinkButton');
            if (copyButton && copyFeedbackElement) { // Ensure elements exist
                copyButton.onclick = function() {
                    // Check if the clipboard API and the writeText method are available
                    if (!navigator.clipboard || typeof navigator.clipboard.writeText !== 'function') {
                        let initialErrorMsg = 'Clipboard API is not fully available. ';
                        if (window.location.protocol !== 'https:') {
                            initialErrorMsg += 'This site is not on HTTPS, which is often required for clipboard access. ';
                        }
                        initialErrorMsg += 'Please copy manually:';
                        copyFeedbackElement.innerHTML = `${initialErrorMsg}<br><input type="text" value="${currentJobUrlForModal}" readonly onclick="this.select();" style="width:100%; margin-top:5px; padding: 5px; border: 1px solid #ccc; box-sizing: border-box;">`;
                        copyFeedbackElement.style.color = 'red';
                        console.error('Clipboard API (navigator.clipboard.writeText) not available or not a function. Protocol: ' + window.location.protocol);
                        return;
                    }

                    navigator.clipboard.writeText(currentJobUrlForModal).then(() => {
                        copyFeedbackElement.textContent = 'Link copied to clipboard!';
                        copyFeedbackElement.style.color = 'green';
                        console.log('Job link copied to clipboard.');
                        // Optionally close modal after a short delay
                        setTimeout(() => {
                            closeProfessionalShareModal();
                        }, 1500);
                    }).catch(err => {
                        console.error('Failed to copy link:', err);
                        let errorMessage = 'Failed to copy link. ';
                        if (window.location.protocol !== 'https:') {
                            errorMessage += 'This often happens on non-HTTPS sites. ';
                        }
                        errorMessage += 'Please copy manually:';
                        copyFeedbackElement.innerHTML = `${errorMessage}<br><input type="text" value="${currentJobUrlForModal}" readonly onclick="this.select();" style="width:100%; margin-top:5px; padding: 5px; border: 1px solid #ccc; box-sizing: border-box;">`;
                        copyFeedbackElement.style.color = 'red';
                    });
                };
            } else {
                // Fallback if copyButton or copyFeedbackElement is not found (should not happen with correct HTML)
                if (copyButton) {
                    copyButton.onclick = function() {
                        alert("Copy button setup error. Please try copying the link from your browser's address bar after opening the job.");
                    };
                }
                console.error("Could not set up copy button listener. copyButton or copyFeedbackElement not found.");
            };


            document.getElementById('shareViaWhatsApp').href = `https://wa.me/?text=${encodedShareText}%20${encodedMainUrlForSharingPlatforms}`;
            document.getElementById('shareViaLinkedIn').href = `https://www.linkedin.com/shareArticle?mini=true&url=${encodedMainUrlForSharingPlatforms}&title=${encodedJobTitle}&summary=${encodedShareText}`;
            
            // For email, ensure the URL itself is also encoded for the body part
            const encodedUrlForEmailBody = encodeURIComponent(currentJobUrlForModal);
            console.log(`[Share Email] currentJobUrlForModal: ${currentJobUrlForModal}, encoded for email body: ${encodedUrlForEmailBody}`); // DEBUG
            document.getElementById('shareViaEmail').href = `mailto:?subject=${encodedJobTitle}&body=${encodedShareText}%0A%0A${encodedUrlForEmailBody}`;
            openProfessionalShareModal();
        }


         document.getElementById('feedbackForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);
            const responseBox = document.getElementById('responseMsg');
            console.log("Feedback form submitted. FormData:", formData); // Log form data

            fetch('feedback.php', {
                method: 'POST',
                body: formData
            })
            .then(res => {
                console.log("Feedback response status:", res.status); // Log response status
                if (!res.ok) { throw new Error(`HTTP error! status: ${res.status}`); }
                return res.json();
            })
            .then(data => {
                console.log("Feedback response data:", data); // Log parsed JSON data
                responseBox.innerText = data.message;
                responseBox.className = 'feedback-message ' + (data.success ? 'success' : 'error');
                responseBox.style.display = 'block';
                if (data.success) {
                    form.reset();
                }
                setTimeout(() => { responseBox.style.display = 'none'; }, 5000);
            })
            .catch(err => {
                console.error("Feedback form error:", err);
                responseBox.innerText = 'An error occurred. Please try again.';
                responseBox.className = 'feedback-message error';
                responseBox.style.display = 'block';
                setTimeout(() => { responseBox.style.display = 'none'; }, 5000);
            });
        });



        function processJobDataForCounts() {
            console.log("[Counts] allJobDataFromPHP:", allJobDataFromPHP); // DEBUG
            if (Array.isArray(allJobDataFromPHP)) {
                allJobPostsForCounts = allJobDataFromPHP.map(post => {
                    let ts_ms = 0; // Timestamp in milliseconds
                    // Use the Unix timestamp (seconds) from PHP ('posted_on_unix_ts')
                    // and convert to milliseconds for JavaScript Date operations.
                    if (post && typeof post.posted_on_unix_ts === 'number' && post.posted_on_unix_ts > 0) {
                        ts_ms = post.posted_on_unix_ts * 1000;
                    } else if (post && post.posted_on && (!post.posted_on_unix_ts || post.posted_on_unix_ts <= 0)) { // Ensure post.posted_on exists
                        // This means strtotime likely failed in PHP for this date string
                         console.warn(`PHP's strtotime couldn't parse date: "${post.posted_on}" for job: "${post.title || 'Unknown'}". This job won't be included in date-filtered counts.`);
                    }
                    return {
                        // Keep original post data if needed, or just what's necessary for counts
                        // We need 'type' for type-based filters now.
                        // Ensure 'type' is always a string and lowercase for consistent filtering.
                        type: (post && typeof post.type === 'string') ? post.type.toLowerCase() : '',
                        // For simplicity, we just need the timestamp for filtering counts.
                        // If other post data is needed by other JS functions using this array, spread post: ...post,
                        timestamp: ts_ms
                    };
                });
            } else {
                allJobPostsForCounts = [];
                console.error("Job data from PHP (allJobDataFromPHP) is not an array or is missing.");
            }
            updateSidebarCounts();
            console.log("[Counts] Processed allJobPostsForCounts:", allJobPostsForCounts); // DEBUG
        }

        function updateSidebarCounts() {
            if (!allJobPostsForCounts || !Array.isArray(allJobPostsForCounts)) {
                console.warn("[Counts] allJobPostsForCounts is not an array or is empty. Setting counts to 0."); // DEBUG
                // Set all counts to 0 if data is not available
                const countIds = ['countAll', 'countRemote', 'countOnsite', 'countHybrid', 
                                  'countFullTime', 'countPartTime', 'countInternship', 'countDeveloper', 'countAllTime',
                                  'count30', 'count7', 'count1'];
                countIds.forEach(id => {
                    // Use querySelectorAll for data-count-id to update both sidebar and mobile filters
                    const elements = document.querySelectorAll(`[data-count-id="${id}"]`);
                     elements.forEach(el => {
                        if (el) el.innerText = '0';
                    });
                });
                return;
            }

            const now_ms = Date.now(); // Current time in milliseconds
            const oneDay_ms = 24 * 60 * 60 * 1000; // Milliseconds in a day

            // Helper function to safely update innerText for elements matching data-count-id
            const setCount = (id, count) => {
                const elements = document.querySelectorAll(`[data-count-id="${id}"]`);
                elements.forEach(el => {
                    if (el) el.innerText = count;
                });
            };

            console.log("[Counts] Updating sidebar counts. Total posts for counts:", allJobPostsForCounts.length); // DEBUG
            // Total count
            setCount('countAll', allJobPostsForCounts.length);
            setCount('countAllTime', allJobPostsForCounts.length); // For the "All Time" date filter

            // Type-based counts
            setCount('countRemote', allJobPostsForCounts.filter(p => p.type === 'remote').length);
            setCount('countOnsite', allJobPostsForCounts.filter(p => p.type === 'onsite').length);
            setCount('countHybrid', allJobPostsForCounts.filter(p => p.type === 'hybrid').length);
            setCount('countFullTime', allJobPostsForCounts.filter(p => p.type === 'full time').length); // Ensure 'full time' matches the value in your job data
            setCount('countPartTime', allJobPostsForCounts.filter(p => p.type === 'part time').length); // Ensure 'part time' matches
            setCount('countInternship', allJobPostsForCounts.filter(p => p.type === 'internship').length);
            setCount('countDeveloper', allJobPostsForCounts.filter(p => p.type === 'developer').length);

            // Date-based counts (filter posts that have a valid, non-zero millisecond timestamp)
            const validDatePosts = allJobPostsForCounts.filter(p => p.timestamp > 0);
            setCount('count30', validDatePosts.filter(p => (now_ms - p.timestamp <= 30 * oneDay_ms)).length);
            setCount('count7', validDatePosts.filter(p => (now_ms - p.timestamp <= 7 * oneDay_ms)).length);
            setCount('count1', validDatePosts.filter(p => (now_ms - p.timestamp <= oneDay_ms)).length);
            console.log("[Counts] Sidebar counts updated."); // DEBUG
        }

        function toggleJobDetails(jobCard) {
            const details = jobCard.querySelector('.job-details');
            const summary = jobCard.querySelector('.job-summary');

            if (details && summary) {
                // If the details are currently hidden, show them and hide the summary.
                // Otherwise, hide details and show summary (standard toggle behavior).
                if (details.style.display === 'none' || details.style.display === '') {
                    // Close all other job cards first
                    const allJobCards = document.querySelectorAll('.job-card');
                    allJobCards.forEach(card => {
                        if (card !== jobCard) { // Don't collapse the one we are about to open
                            const otherDetails = card.querySelector('.job-details');
                            const otherSummary = card.querySelector('.job-summary');
                            if (otherDetails && otherSummary) {
                                otherDetails.style.display = 'none';
                                otherSummary.style.display = 'block'; // Or 'flex', 'grid' etc. if that's its default
                            }
                        }
                    });
                    // Now expand the clicked one
                    details.style.display = 'block';
                    summary.style.display = 'none';
                    // Scroll the top of the job card into view
                    jobCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); 

                    // After scrolling, adjust for sticky header if on mobile and card is expanding
                    // We use a timeout to allow the initial scrollIntoView to finish
                    setTimeout(() => {
                        if (window.innerWidth <= 768) { // Assuming 768px is your mobile breakpoint
                            const mobileFiltersBar = document.querySelector('.mobile-filters');
                            if (mobileFiltersBar && getComputedStyle(mobileFiltersBar).position === 'sticky') {
                                const stickyHeaderHeight = mobileFiltersBar.offsetHeight;
                                const jobCardCurrentTop = jobCard.getBoundingClientRect().top;
                                const desiredTopPosition = stickyHeaderHeight + 15; // 15px buffer

                                if (jobCardCurrentTop < desiredTopPosition) {
                                    window.scrollBy({ top: jobCardCurrentTop - desiredTopPosition, behavior: 'smooth' });
                                }
                            }
                        }
                    }, 350); // Adjust timeout if needed, depends on scrollIntoView's 'smooth' duration
                    // Increment view count if expanding
                    const jobId = jobCard.dataset.jobId;
                    if (jobId) {
                        fetch('increment_job_view.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({ job_id: jobId })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                console.log('View count incremented for job ID:', jobId);
                            } else {
                                console.error('Failed to increment view count:', data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error sending view increment request:', error);
                        });
                        // New: Store viewed job ID in cookie if consent given
                        if (getCookie(CONSENT_COOKIE_NAME_JS) === 'accepted') {
                            const viewedJobIdsCookieName = '<?php echo USER_VIEWED_JOB_IDS_COOKIE_NAME; ?>';
                            let viewedJobIds = [];
                            const existingViewedCookie = getCookie(viewedJobIdsCookieName);
                            if (existingViewedCookie) {
                                try {
                                    viewedJobIds = JSON.parse(existingViewedCookie);
                                    if (!Array.isArray(viewedJobIds)) viewedJobIds = [];
                                } catch (e) { viewedJobIds = []; }
                            }
                            // Add new ID to the front, remove duplicates, limit size
                            viewedJobIds = [jobId, ...viewedJobIds.filter(id => id !== jobId)].slice(0, 10); // Keep last 10
                            setCookie(viewedJobIdsCookieName, JSON.stringify(viewedJobIds), 30); // Store for 30 days
                            console.log('Updated viewed jobs cookie:', viewedJobIds);
                        }
                    }
                    
                } else {
                    details.style.display = 'none';
                    summary.style.display = 'block'; // Or 'flex', 'grid' etc.
                    // Scroll the top of the job card into view even when collapsing
                    jobCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

                    // After scrolling, adjust for sticky header if on mobile and card is collapsing
                    // We use a timeout to allow the initial scrollIntoView to finish
                    setTimeout(() => {
                        if (window.innerWidth <= 768) { // Assuming 768px is your mobile breakpoint
                            const mobileFiltersBar = document.querySelector('.mobile-filters');
                            if (mobileFiltersBar && getComputedStyle(mobileFiltersBar).position === 'sticky') {
                                const stickyHeaderHeight = mobileFiltersBar.offsetHeight;
                                const jobCardCurrentTop = jobCard.getBoundingClientRect().top;
                                const desiredTopPosition = stickyHeaderHeight + 15; // 15px buffer

                                if (jobCardCurrentTop < desiredTopPosition) {
                                    window.scrollBy({ top: jobCardCurrentTop - desiredTopPosition, behavior: 'smooth' });
                                }
                            }
                        }
                    }, 350); // Adjust timeout if needed
                }
            }
        }

        function expandJobFromUrl() {
            console.log('[DEBUG] expandJobFromUrl called');
            const urlParams = new URLSearchParams(window.location.search);
            const jobIdToExpand = urlParams.get('job_id');
            console.log('[DEBUG] Job ID to expand from URL (JS):', jobIdToExpand);

            if (jobIdToExpand) {
                // PHP should have already filtered to this single job.
                // This JS is now just to ensure it's expanded.
                const jobCardToExpand = document.querySelector(`.job-card[data-job-id="${jobIdToExpand}"]`);

                if (jobCardToExpand) {
                    console.log('[DEBUG] Found job card to expand:', jobCardToExpand);

                    // Expand its details
                    const details = jobCardToExpand.querySelector('.job-details');
                    const summary = jobCardToExpand.querySelector('.job-summary');
                    if (details && summary) {
                        // Since PHP ensures only this job is on the page when job_id is set,
                        // we can directly manipulate its display properties.
                        // No need to loop and hide others as they shouldn't be there.

                        details.style.display = 'block';
                        summary.style.display = 'none';
                        console.log('[DEBUG] Job card expanded.');
                    } else {
                        console.log('[DEBUG] Could not find .job-details or .job-summary within the target card.');
                    }
                    // Scroll to the job card, or more specifically, its title
                    const titleElement = jobCardToExpand.querySelector('h3');
                    if (titleElement) {
                        titleElement.scrollIntoView({ behavior: 'smooth', block: 'start' }); // 'start' aligns top of title with top of viewport
                    } else {
                        jobCardToExpand.scrollIntoView({ behavior: 'smooth', block: 'center' }); // Fallback to card
                    }
                } else {
                    console.log('[DEBUG] Job card with ID', jobIdToExpand, 'not found on page (JS check). This might be okay if PHP handled it.');
                }
            }
        }

        const CONSENT_COOKIE_NAME_JS = '<?php echo COOKIE_CONSENT_STATUS_NAME; ?>'; // Use PHP constant

        window.onload = function() {
            processJobDataForCounts(); // Process the PHP-provided data
            expandJobFromUrl(); // Check if a job needs to be expanded on load

            // Cookie Consent Logic
            const cookieBanner = document.getElementById('cookieConsentBanner');
            const acceptBtn = document.getElementById('acceptCookieConsent');
            // const declineBtn = document.getElementById('declineCookieConsent'); // If you add a decline button

            if (!getCookie(CONSENT_COOKIE_NAME_JS) && cookieBanner) {
                cookieBanner.style.display = 'block';
            }

            if (acceptBtn) {
                acceptBtn.addEventListener('click', function() {
                    setCookie(CONSENT_COOKIE_NAME_JS, 'accepted', 365); // Consent for 1 year
                    if (cookieBanner) cookieBanner.style.display = 'none';
                    // Optional: Reload to apply PHP logic immediately if needed,
                    // or to ensure visitor counter updates if it's the first consent.
                    // window.location.reload(); 
                });
            }

            /* // Optional: Decline button logic
            if (declineBtn) {
                declineBtn.addEventListener('click', function() {
                    setCookie(CONSENT_COOKIE_NAME_JS, 'declined', 7); // Remember decline for 7 days
                    if (cookieBanner) cookieBanner.style.display = 'none';
                });
            }
            */
        };
        // Sticky mobile filters interaction with footer
        const mobileFilters = document.querySelector('.mobile-filters');
        const siteFooter = document.querySelector('.site-footer');

        if (mobileFilters && siteFooter) {
            window.addEventListener('scroll', function() {
                // Check if mobile filters are visible (it's display:flex on mobile)
                if (getComputedStyle(mobileFilters).display !== 'none') {
                    const footerTop = siteFooter.getBoundingClientRect().top;
                    const viewportHeight = window.innerHeight;
                    const mobileFiltersHeight = mobileFilters.offsetHeight;

                    // When the top of the footer is within a certain range from the bottom of the filters
                    // (e.g., when footer is less than filter height + some buffer away from bottom of viewport)
                    // This means the filters are "approaching" or "overlapping" the footer area from the top.
                    if (footerTop < viewportHeight && footerTop > (viewportHeight - mobileFiltersHeight - 100)) { // 100px buffer
                        // Option 1: Reduce opacity
                        // mobileFilters.style.opacity = '0.3';
                        // Option 2: Hide it (might be too abrupt)
                        // mobileFilters.style.visibility = 'hidden';
                    } else {
                        // mobileFilters.style.opacity = '1';
                        // mobileFilters.style.visibility = 'visible';
                    }
                    // A more common behavior is that sticky elements are just pushed by the footer.
                    // The pure CSS `position:sticky` will achieve this if the footer is part of the same scroll container.
                    // The JS above is for more custom interaction like fading/hiding.
                    // For now, let's rely on the natural push of sticky content.
                    // If you want it to truly disappear *before* being pushed, the logic above can be enabled.
                }
            });
        }
        
        // --- AJAX for Search and Filters ---
        function handleAjaxNavigation(event, url) {
            event.preventDefault(); // Stop default link behavior or form submission

            const ajaxUrl = new URL(url, window.location.origin);
            ajaxUrl.searchParams.set('ajax', '1'); // Add our AJAX flag

            // Optional: Show a loading indicator
            const listingsContainer = document.getElementById('job-listings-container');
            let spinner = null;
            if (listingsContainer) {
                listingsContainer.classList.add('loading-content');
                spinner = document.createElement('div');
                spinner.className = 'loading-spinner';
                listingsContainer.appendChild(spinner);
                // listingsContainer.innerHTML = '<p style="text-align:center; padding:20px;">Loading jobs...</p>'; // Old loading message
            }

            fetch(ajaxUrl.toString())
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok: ' + response.statusText);
                    }
                    // Small delay to make the transition feel less abrupt if the network is very fast
                    // return new Promise(resolve => setTimeout(() => resolve(response.text()), 200)); 
                    return response.text();
                })
                .then(html => {
                    console.log("AJAX response HTML:", html); // DEBUG: Log the HTML received
                    if (listingsContainer) {
                        listingsContainer.innerHTML = html;
                    }
                    // Content is replaced, remove loading state
                    if (listingsContainer) listingsContainer.classList.remove('loading-content');
                    // The spinner is removed because innerHTML was replaced. If we appended, we'd need spinner.remove().

                    console.log("Listings container updated."); // DEBUG: Confirm update attempt
                    // Update browser history and URL bar
                    // The 'url' parameter is either an absolute URL from a link's href
                    // or a root-relative path (e.g., /index.php?search=...) from the search form.
                    // Commented out to prevent URL change as per user request:
                    // history.pushState({}, '', url);
                    
                    // Re-attach event listeners to new pagination links if any
                    attachAjaxToPagination();
                    // You might need to re-initialize other JS that depends on the new content here
                    // e.g., if job cards have specific JS behaviors.
                    // The toggleJobDetails is an inline onclick, so it should still work.
                    // The shareJob is also inline onclick.
                    // Scroll to the top of the job listings container
                    
                    updateActiveFilterLinks(ajaxUrl.toString()); // Update active filter links

                    // Clear the search input field if the current navigation was a search
                    // This is now handled by the fact that on refresh, PHP sets $search to '' for filtering,
                    // but the input field's value attribute directly uses $_GET['search'] ?? ''
                    // If we want to clear it *after an AJAX search completes*, this logic is still valid.
                    // For the "refresh shows all jobs" requirement, the PHP change is key.
                    // Let's keep this JS clear for after an AJAX search, as per previous request.

                    // The 'url' variable here holds the URL that was used for navigation (e.g., from a link or search form)
                    const navigatedUrlParams = new URLSearchParams(new URL(url, window.location.origin).search);
                    if (navigatedUrlParams.has('search') && navigatedUrlParams.get('search') !== '') {
                        const searchInput = document.querySelector('.search-bar input[name="search"]');
                        if (searchInput) searchInput.value = '';
                    }
                    // Use a short timeout to ensure the DOM has updated and heights are calculated
                    setTimeout(() => {
                        if (listingsContainer) {
                            let scrollTargetY = 0;
                            let currentElement = listingsContainer;
                            while(currentElement && !isNaN(currentElement.offsetTop)) {
                                scrollTargetY += currentElement.offsetTop - currentElement.scrollTop;
                                currentElement = currentElement.offsetParent;
                            }

                            let stickyHeaderHeight = 0;
                            const mobileFiltersBar = document.querySelector('.mobile-filters');
                            if (window.innerWidth <= 768 && mobileFiltersBar && getComputedStyle(mobileFiltersBar).position === 'sticky') {
                                stickyHeaderHeight = mobileFiltersBar.offsetHeight;
                            }
                            // Add similar check for desktop sticky header if applicable

                            console.log(`Scrolling to: ${scrollTargetY} - ${stickyHeaderHeight} - 15 (buffer)`);
                            window.scrollTo({ top: scrollTargetY - stickyHeaderHeight - 15, behavior: 'smooth' }); // 15px buffer
                        }
                    }
                    , 50); // 50ms delay, adjust if needed
                 })
                .catch(error => {
                    console.error('Error fetching jobs:', error);
                    if (listingsContainer) {
                        listingsContainer.classList.remove('loading-content'); // Remove loading state on error too
                        if (spinner) spinner.remove(); // Ensure spinner is removed
                        listingsContainer.innerHTML = '<p style="text-align:center; color:red;">Error loading jobs. Please try again.</p>';
                        console.log("Error message set in listings container."); // DEBUG
                    }
                });
        }

        // Attach to search form
        const searchForm = document.querySelector('.search-bar form');
        if (searchForm) {
            searchForm.addEventListener('submit', function(event) {
                const formData = new FormData(searchForm);
                const currentSearchTerm = formData.get('search'); // Get the new search term
                
                // For a search bar submission, we want to search the total list.
                // This means resetting other filters (type, date) to their 'all' state
                // and resetting pagination to page 1.
                const params = new URLSearchParams();
                params.set('search', currentSearchTerm);
                params.set('filter', 'all'); // Reset date filter to 'all'
                params.set('type', '');      // Reset type filter to empty (which PHP treats as 'all types')
                params.set('page', '1');     // Reset to page 1 for a new search

                // Construct URL based on current pathname and new params to avoid appending to old query string
                const searchUrl = window.location.pathname + '?' + params.toString();
                handleAjaxNavigation(event, searchUrl);
            });
        }

        // Attach to filter links (sidebar and mobile)
        function attachAjaxToFilterLinks() {
            document.querySelectorAll('.sidebar a, .mobile-filters a').forEach(link => {
                // Remove old listener before adding new one to prevent duplicates if this function is called multiple times
                link.removeEventListener('click', filterLinkHandler); 
                link.addEventListener('click', filterLinkHandler);
            });
        }
        function filterLinkHandler(event) {
            handleAjaxNavigation(event, this.href);
        }

        // Attach to pagination links (needs to be re-attached if pagination is re-rendered)
        function attachAjaxToPagination() {
            document.querySelectorAll('.pagination-container a').forEach(link => {
                 // Remove old listener
                link.removeEventListener('click', paginationLinkHandler);
                link.addEventListener('click', paginationLinkHandler);
            });
        }
        function paginationLinkHandler(event) {
            handleAjaxNavigation(event, this.href);
        }
        
        function updateActiveFilterLinks(currentUrl) {
            const url = new URL(currentUrl, window.location.origin);
            const params = url.searchParams;

            const currentType = params.get('type') || ''; // Default to empty string if not present
            const currentFilter = params.get('filter') || 'all'; // Default to 'all' for date
            const currentSearch = params.get('search') || '';
            const currentRecommendations = params.get('recommendations') === '1';

            document.querySelectorAll('.sidebar a, .mobile-filters a').forEach(link => {
                link.classList.remove('active-filter');
                const linkUrl = new URL(link.href);
                const linkParams = linkUrl.searchParams;

                const linkType = linkParams.get('type') || '';
                const linkFilter = linkParams.get('filter') || 'all';
                const linkSearch = linkParams.get('search') || '';
                const linkRecommendations = linkParams.get('recommendations') === '1';

                let isActive = false;

                if (linkRecommendations) {
                    if (currentRecommendations) isActive = true;
                } else if (currentRecommendations) { // If current view is recommendations, other links are not active
                    isActive = false;
                }
                // "All Jobs" link logic (handles cases where it's the default view)
                else if (linkType === '' && linkFilter === 'all' && linkSearch === '' && !linkRecommendations) {
                    if (currentType === '' && currentFilter === 'all' && currentSearch === '' && !currentRecommendations) {
                        // This is the true "All Jobs" or default view
                        isActive = true;
                    }
                }
                // Specific filter link matching
                else {
                    // For specific filters, a link is active if its main distinguishing parameter matches the current URL's parameter.
                    // We need to be careful not to activate multiple links incorrectly.
                    // Example: If current URL is ?type=remote&filter=7
                    // - Link for "?type=remote" should be active.
                    // - Link for "?filter=7" should be active.

                    let linkIsForType = linkParams.has('type') && linkType !== '';
                    let linkIsForDate = linkParams.has('filter') // Date filters might not have type='' explicitly
                                        && linkFilter !== '' && linkFilter !== 'all' 
                                        && !linkIsForType; // Prioritize type if link sets both

                    if (linkIsForType && linkType === currentType) {
                        isActive = true;
                    } else if (linkIsForDate && linkFilter === currentFilter) {
                        isActive = true;
                    }
                    // More complex logic might be needed if links combine multiple filters that must all match
                    // For now, this prioritizes the main filter the link represents.
                }

                if (isActive) {
                    link.classList.add('active-filter');
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            // const urlParams = new URLSearchParams(window.location.search);
            // const searchInput = document.querySelector('.search-bar input[name="search"]');
            // The PHP value attribute on the search input now correctly handles displaying the search term.
            // No need for JS to clear or set it on initial load.

            // Initial attachment of event listeners for AJAX navigation
            attachAjaxToFilterLinks();
            attachAjaxToPagination();
            updateActiveFilterLinks(window.location.href); // Set active filters on initial page load
        });

    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script>
        // --- Three.js Background Animation for Title Card: Rotating Icosahedron ---
        let scene, camera, renderer, icosahedronMesh;
        const container = document.getElementById('title-card-3d-background');

        function initThreeJS() {
            if (!container) {
                console.warn('Three.js: Container #title-card-3d-background not found.');
                return;
            }

            // Scene
            scene = new THREE.Scene();

            // Camera
            camera = new THREE.PerspectiveCamera(75, container.offsetWidth / container.offsetHeight, 0.1, 1000);
            camera.position.z = 30; // Adjusted for better particle visibility

            // Renderer
            renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true }); // alpha:true for transparent background
            renderer.setSize(container.offsetWidth, container.offsetHeight);
            renderer.setPixelRatio(window.devicePixelRatio);
            // renderer.setClearColor(0xf7faff, 1); // Match title card background if not using alpha
            container.appendChild(renderer.domElement);

            // Create an Icosahedron
            const geometry = new THREE.IcosahedronGeometry(10, 0); // Radius 10, detail 0
            const material = new THREE.MeshBasicMaterial({
                color: 0x60a5fa, // A nice blue, similar to your theme accents
                wireframe: true,
                transparent: true,
                opacity: 0.5 // Make it somewhat transparent
            });

            icosahedronMesh = new THREE.Mesh(geometry, material);
            scene.add(icosahedronMesh);

            // Optional: Add a subtle ambient light if you ever use non-basic materials
            // const ambientLight = new THREE.AmbientLight(0xffffff, 0.5);
            // scene.add(ambientLight);

            // Optional: Add a point light for more dynamic shading with other materials
            // const pointLight = new THREE.PointLight(0xffffff, 0.8);
            // pointLight.position.set(5, 15, 25);
            // scene.add(pointLight);

            // Event Listener
            window.addEventListener('resize', onWindowResize, false);

            animateThreeJS();
        }

        function animateThreeJS() {
            requestAnimationFrame(animateThreeJS);

            if (icosahedronMesh) {
                // Subtle rotation
                icosahedronMesh.rotation.x += 0.001;
                icosahedronMesh.rotation.y += 0.0015;
            }
            
            if (renderer && scene && camera) {
                 renderer.render(scene, camera);
            }
        }

        function onWindowResize() {
            if (container && camera && renderer) {
                camera.aspect = container.offsetWidth / container.offsetHeight;
                camera.updateProjectionMatrix();
                renderer.setSize(container.offsetWidth, container.offsetHeight);
            }
        }

        // Initialize Three.js scene on DOMContentLoaded or window.onload
        // Using DOMContentLoaded for potentially faster initialization
        document.addEventListener('DOMContentLoaded', function() {
            // The other DOMContentLoaded listener for search input clearing is already there.
            // We can add to it or have a separate one. For clarity, a separate call here is fine.
            initThreeJS();
        });

        // --- Title Card Fade Out on Scroll ---
        const titleCard = document.querySelector('.title-card');
        const mainContentStart = document.querySelector('main'); // Or a more specific element marking start of main content

        if (titleCard && mainContentStart) {
            window.addEventListener('scroll', function() {
                const titleCardHeight = titleCard.offsetHeight;
                // Get the top position of the main content area relative to the viewport
                const mainContentTop = mainContentStart.getBoundingClientRect().top;
                
                // Start fading when the top of main content is about to overlap the title card
                // Let's say we start fading when main content is 100px above the bottom of the title card
                // and fully faded when main content top is at the title card's top.
                // This means the fade happens over the height of the title card.

                // Calculate how much of the title card is "covered" by the scroll
                // A simpler approach: start fading when main content is near the top of the viewport
                // and fully fade out as it scrolls further up.
                
                let opacity = 1;
                // Start fading when the top of the title card is about to go off-screen
                // and be fully faded when, say, half of it is off-screen.
                const titleCardTop = titleCard.getBoundingClientRect().top;
                const fadeStartOffset = 0; // Start fading immediately as it scrolls up
                const fadeDistance = titleCardHeight * 0.7; // Fade out over 70% of its height (adjust as needed)
                const maxTranslateY = 50; // Max vertical movement in pixels (adjust as needed)
                // const maxRotateX = 5; // Max tilt in degrees (adjust as needed, requires perspective on parent)

                if (titleCardTop < fadeStartOffset) {
                    opacity = Math.max(0, 1 - (Math.abs(titleCardTop - fadeStartOffset) / fadeDistance));
                    const translateY = Math.min(maxTranslateY, (Math.abs(titleCardTop - fadeStartOffset) / fadeDistance) * maxTranslateY);
                    titleCard.style.transform = `translateY(${translateY}px)`;
                }
                titleCard.style.opacity = opacity;
            });
        }
    </script>
</body>
</html>
