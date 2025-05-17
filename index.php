<?php
session_start();

define('COOKIE_CONSENT_STATUS_NAME', 'cookie_consent_status');
define('USER_INTERESTS_COOKIE_NAME', 'user_job_interests');
define('USER_UNIQUE_ID_COOKIE_NAME', 'user_unique_site_id'); // New cookie for unique user ID
define('MAX_USER_INTERESTS', 5); // Store up to 5 recent interests

// --- MANAGE UNIQUE USER ID COOKIE ---
$currentUserUniqueID = $_COOKIE[USER_UNIQUE_ID_COOKIE_NAME] ?? null;
if (!$currentUserUniqueID) {
    $currentUserUniqueID = bin2hex(random_bytes(16)); // Generate a strong unique ID
    $uid_cookie_options = [
        'expires' => time() + (365 * 24 * 60 * 60), // Expires in 1 year
        'path' => '/',
        'samesite' => 'Lax',
        // 'secure' => true, // If using HTTPS
        // 'httponly' => true, // This ID might be needed by JS if you extend functionality
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

$currentSearch = $_GET['search'] ?? null;
if ($currentSearch !== null && $currentSearch !== '') $currentLogEntry['current_search_query'] = $currentSearch;

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
        $decodedInterests = json_decode($currentInterestsRaw, true);
        if (is_array($decodedInterests)) {
            $userInterests = $decodedInterests;
        }
    } catch (Exception $e) { /* Malformed cookie, start fresh */ $userInterests = []; }

    $interestToRecord = null;
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

// --- START: Force reset of filters on page load, unless a specific job is being expanded OR IT'S AN AJAX REQUEST ---
// We need to check for ajax flag here specifically for this reset logic,
// as the main $isAjaxRequest variable is defined much later.
$isAjaxRequestForResetCheck = isset($_GET['ajax']) && $_GET['ajax'] === '1';
$forceResetFilters = !isset($_GET['job_id']) && !$isAjaxRequestForResetCheck; 

if ($forceResetFilters) {
    error_log("[DEBUG] Forcing reset of filters for non-AJAX, non-expanded view. URL: " . $_SERVER['REQUEST_URI']);
    $_GET['search'] = ''; // Effectively clear search from URL for this load
    $_GET['filter'] = 'all'; // Effectively clear date filter from URL
    $_GET['type'] = '';   // Effectively clear type filter from URL
    // $_GET['page'] is not reset here, typically defaults to 1 if not present.
}
// --- END: Force reset of filters ---

// Check if a specific job ID is requested to be expanded
$jobIdToExpandFromUrl = isset($_GET['job_id']) ? trim($_GET['job_id']) : null;
$singleJobView = false;

if ($jobIdToExpandFromUrl) {
    $foundJob = null;
    foreach ($phpJobsArray as $job) {
        if (isset($job['id']) && $job['id'] === $jobIdToExpandFromUrl) {
            $foundJob = $job;
            break;
        }
    }
    $phpJobsArray = $foundJob ? [$foundJob] : []; // If found, phpJobsArray now contains only this job
    $singleJobView = (bool)$foundJob;
}

// Interest-based filtering logic (before other filters if no explicit GET filters are set)
$appliedInterestFilter = false;
if (!$singleJobView &&
    empty($_GET['search']) && empty($_GET['type']) && empty($_GET['filter']) && // No explicit filters in URL
    isset($_COOKIE[COOKIE_CONSENT_STATUS_NAME]) && $_COOKIE[COOKIE_CONSENT_STATUS_NAME] === 'accepted') {

    $userInterestsCookieVal = isset($_COOKIE[USER_INTERESTS_COOKIE_NAME]) ? $_COOKIE[USER_INTERESTS_COOKIE_NAME] : '[]';
    $userStoredInterests = json_decode($userInterestsCookieVal, true);

    if (is_array($userStoredInterests) && !empty($userStoredInterests)) {
        $tempInterestJobs = [];
        foreach ($phpJobsArray as $job) { // Filter the original full list
            if (!is_array($job)) { // Defensive check for each job item
                error_log("Skipping non-array job item in interest filter: " . print_r($job, true));
                continue;
            }
            $jobMatchesInterest = false;
            // Explicitly cast job fields to string for safer comparison
            $jobTitle = (string)($job['title'] ?? '');
            $jobCompany = (string)($job['company'] ?? '');
            $jobLocation = (string)($job['location'] ?? '');
            $jobTypeData = (string)($job['type'] ?? '');
            $jobSummary = (string)($job['ai_summary'] ?? '');

            foreach ($userStoredInterests as $interest) {
                $interest = strtolower(trim($interest));
                if ( // Use the explicitly cast string variables
                    (stripos(strtolower($jobTitle), $interest) !== false) ||
                    (stripos(strtolower($jobCompany), $interest) !== false) ||
                    (stripos(strtolower($jobLocation), $interest) !== false) ||
                    (stripos(strtolower($jobTypeData), $interest) !== false) ||
                    (stripos(strtolower($jobSummary), $interest) !== false)
                ) {
                    $jobMatchesInterest = true;
                    break; // Job matches one interest, no need to check others for this job
                }
            }
            if ($jobMatchesInterest) {
                $tempInterestJobs[] = $job;
            }
        }
        if (!empty($tempInterestJobs)) {
            $phpJobsArray = $tempInterestJobs; // Update the main array to be filtered further
            $appliedInterestFilter = true;
        }
    }
}

// Filepath for the daily visitor counter
$visitorCounterFile = __DIR__ . '/data/daily_visitors.json';

// Function to increment the visitor count for the current day
function incrementDailyVisitorCounter($filePath) {
    // Get today's date
    $today = date('Y-m-d');

    // Read the current data
    if (!file_exists($filePath)) {
        $visitorData = [];
    } else {
        $visitorData = json_decode(file_get_contents($filePath), true);
        if (!is_array($visitorData)) {
            $visitorData = [];
        }
    }

    // Increment the count for today
    if (!isset($visitorData[$today])) {
        $visitorData[$today] = 0;
    }
    $visitorData[$today]++;

    // Save the updated data back to the file
    file_put_contents($filePath, json_encode($visitorData));

    return $visitorData[$today];
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

    // Increment the daily visitor counter
    incrementDailyVisitorCounter($visitorCounterFile);
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


// Initialize $filteredJobs with all jobs from $phpJobsArray for PHP logic
// $phpJobsArray might already be filtered by interests at this point
$filteredJobs = $phpJobsArray; // This will be either all jobs or just the single job if $jobIdToExpandFromUrl was set

// Filter jobs based on job type (if explicitly set in URL)
$jobType = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : '';
if ($jobType !== '' && $jobType !== 'all') { // Apply type filter if a specific type is chosen
    $filteredJobs = array_filter($filteredJobs, function ($job) use ($jobType) {
        if (!is_array($job)) { // Ensure $job is an array before accessing keys
            error_log("Filtering out non-array job item in type filter: " . print_r($job, true));
            return false; 
        }

        // More robust check for the 'type' field
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


// Apply search and date filters only if not in single job view
if (!$singleJobView) {
    // 1. Apply Search Filter (if explicitly set in URL)
    $search = isset($_GET['search']) ? strtolower(trim($_GET['search'])) : '';
    if ($search !== '') {
        $tempJobs = [];
        foreach ($filteredJobs as $job) {
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

    // 2. Apply Date Filter (if explicitly set in URL)
    $filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
    $currentDate = time();

    if ($filter !== 'all') {
        $tempJobs = [];
        $daysToFilter = 0;
        if ($filter === '30') $daysToFilter = 30;
        elseif ($filter === '7') $daysToFilter = 7;
        elseif ($filter === '1') $daysToFilter = 1;

        if ($daysToFilter > 0) {
            $cutoffDate = $currentDate - ($daysToFilter * 24 * 60 * 60);
            foreach ($filteredJobs as $job) {
                if (!is_array($job)) { 
                    error_log("Skipping non-array job item in date filter: " . print_r($job, true));
                    continue;
                }
                $jobTimestamp = $job['posted_on_unix_ts'] ?? (isset($job['posted_on']) && is_string($job['posted_on']) ? strtotime($job['posted_on']) : 0);
                if ($jobTimestamp !== false && $jobTimestamp > 0 && $jobTimestamp >= $cutoffDate) {
                    $tempJobs[] = $job;
                }
            }
            $filteredJobs = $tempJobs;
        }
    }
    error_log("After date filter ('{$filter}'): " . count($filteredJobs) . " jobs.");

} else { 
    $search = '';
    $filter = 'all';
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
$page = max(1, intval($_GET['page'] ?? 1));
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
function render_job_listings_and_pagination($pagedJobs, $singleJobView, $totalPages, $search, $filter, $jobType, $page) {
    ob_start(); 
?>
    <?php if(empty($pagedJobs)): ?>
        <p class="no-jobs-message">No matching jobs found for the current criteria.</p>
    <?php else: ?>
        <?php if ($singleJobView): ?>
            <a href="<?= strtok($_SERVER["REQUEST_URI"], '?') ?>" id="showAllJobsBtn" class="button">Show All Jobs</a>
        <?php endif; ?>

        <?php foreach ($pagedJobs as $job): ?>
        <div class="job-card" onclick="toggleJobDetails(this)" data-job-id="<?= htmlspecialchars($job['id'] ?? '') ?>">
            <h3>
                <?= htmlspecialchars($job['title'] ?? 'N/A') ?>
                <?php if (!empty($job['vacant_positions']) && $job['vacant_positions'] > 1): ?>
                    <span style="font-size: 0.8rem; color: #777; font-weight: normal; margin-left: 10px;">
                        (<?= htmlspecialchars($job['vacant_positions']) ?> vacancies)
                    </span>
                <?php endif; ?>
            </h3>
            <strong><?= htmlspecialchars($job['company'] ?? 'N/A') ?></strong> – <?= htmlspecialchars($job['location'] ?? 'N/A') ?><br>
            
            <p class="job-summary" style="margin-top: 5px; margin-bottom: 10px;"><?= formatAiSummary(substr($job['ai_summary'] ?? '', 0, 300)) ?><?php if(strlen($job['ai_summary'] ?? '') > 300) echo "..."; ?></p>

            <div class="job-details" style="display: none;">
                <div class="formatted-summary">
                    <?= formatAiSummary($job['ai_summary'] ?? 'N/A') ?>
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
                <?php foreach (explode(',', $job['phones']) as $phone): ?>
                    <a href="tel:<?= trim($phone) ?>"><?= trim($phone) ?></a>&nbsp;
                <?php endforeach; ?>
            </p>
            <?php endif; ?>
            <?php if (!empty($job['emails'])): ?>
            <p style="margin-bottom: 15px;"><strong>📧 Email:</strong>
                <?php foreach (explode(',', $job['emails']) as $email): ?>
                    <a href="mailto:<?= trim($email) ?>"><?= trim($email) ?></a>&nbsp;
                <?php endforeach; ?>
            </p>
            <?php endif; ?>
            
            <small>Posted on <?= htmlspecialchars($job['posted_on'] ?? 'N/A') ?></small><br>
 <button style="margin-top: 10px;" class="share-button" onclick="shareJob('<?= htmlspecialchars($job['id'] ?? '') ?>', '<?= htmlspecialchars($job['title'] ?? '') ?>', '<?= htmlspecialchars($job['company'] ?? '') ?>'); event.stopPropagation();">Share</button>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!$singleJobView && $totalPages > 1): ?>
    <div class="pagination-container">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?search=<?= urlencode($search) ?>&filter=<?= urlencode($filter) ?>&type=<?= urlencode($jobType) ?>&page=<?= $i ?>"
               class="<?= $i == $page ? 'current-page' : '' ?>">
                <?= $i ?>
            </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
<?php
    return ob_get_clean(); 
}

$isAjaxRequest = isset($_GET['ajax']) && $_GET['ajax'] === '1';
error_log("[REQUEST_INFO] URL: " . $_SERVER['REQUEST_URI'] . " | Is AJAX: " . ($isAjaxRequest ? "Yes" : "No"));
error_log("[REQUEST_INFO] Filter Params: type='{$jobType}', search='{$search}', filter='{$filter}', page='{$page}'");
error_log("[REQUEST_INFO] Total filtered jobs (before pagination): " . count($filteredJobs) . ". Total pages: " . $totalPages);
error_log("[REQUEST_INFO] Jobs for paged view (before AJAX check): " . count($pagedJobs) . " jobs. SingleView: " . ($singleJobView ? 'Yes':'No'));

if ($isAjaxRequest) {
    error_log("[AJAX_RESPONSE] Preparing AJAX response. Paged jobs: " . count($pagedJobs) . ". TotalPages: " . $totalPages . ". Current Page: " . $page . ". First job (if any): " . ($pagedJobs[0]['title'] ?? 'N/A'));
    echo render_job_listings_and_pagination($pagedJobs, $singleJobView, $totalPages, $search, $filter, $jobType, $page);
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
        body { font-family: Arial, sans-serif; margin:0; padding:0; background:#f9f9f9; color:#333; }
        .container { width:100%; }

        .title-card {
            background: #005fa3; /* Solid professional blue */
            color:#fff;
            padding:50px 20px; /* Increased padding for better spacing */
            margin-bottom:25px;
            text-align:center;
            box-shadow:0 2px 8px rgba(0,0,0,0.1); /* Softer, cleaner shadow */
            width: 100%;
        }
        .title-card h1 {
            margin:0 0 15px;
            font-size:30px; /* Slightly larger for more impact */
            color: #fff; /* Ensure heading is white */
            font-weight: 600; /* Maintain existing weight or adjust if needed for clarity */
        }
        .title-card p {
            font-size:17px; /* Slightly larger subtitle */
            color: #e0e0e0; /* Lighter white for subtitle, clear but distinct */
            margin-bottom:25px;
            opacity: 0.9;
        }
        .title-card .join-buttons { display:inline-flex; gap:10px; flex-wrap:wrap; justify-content:center; }
        .title-card .join-buttons a {
            color:#fff; text-decoration:none; padding:12px 20px; /* More padding for buttons */
            border-radius:6px; /* Slightly more rounded */
            background:rgba(255,255,255,0.15); /* Subtle background */
            border: 1px solid rgba(255,255,255,0.25); /* Subtle border */
            transition: background-color 0.3s, border-color 0.3s;
            font-weight: 500; /* Clearer button text */
        }
        .title-card .join-buttons a.whatsapp { background:#25D366; }
        .title-card .join-buttons a.telegram { background:#0088cc; } /* Corrected class name */
        .title-card .join-buttons a:hover { background:rgba(255,255,255,0.25); border-color: rgba(255,255,255,0.4); }

        .content-wrapper { display:flex; gap:20px; padding:0 20px; }

        .sidebar { width:220px; background:#fff; padding:20px; border-radius:8px; box-shadow:0 2px 6px rgba(0,0,0,0.05); flex-shrink:0; }
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
          padding: 0;
          max-width: 800px;
        }
        .search-bar form { display: flex; width: 100%; gap: 10px; margin-bottom:0px; }
        .search-bar input[type="text"] { flex-grow: 1; padding:10px; border:1px solid #ccc; border-radius:4px; font-size: 1rem; }
        .search-bar button { background: #005fa3; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-size: 1rem; }
        .search-bar button:hover { background: #004577; }

        main {
            flex: 1;
            min-width: 0;
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
            .search-bar {
                 margin-top: 15px; /* Adjust margin */
                 margin-bottom: 15px;
                 padding: 0 10px; /* Add horizontal padding */
                 max-width: none; /* Allow search bar to take more width */
            }
            .search-bar form { gap: 5px; } /* Reduce gap in search bar */
            .job-card { padding: 10px; /* Adjust job card padding */ }
            .site-footer .footer-container { padding: 0 10px; /* Adjust footer padding */ }
            .mobile-filters {
                display: flex; /* Show and use flex for layout */
            }
             .title-card { padding: 20px 10px; /* Adjust title card padding */ }
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
            background: #fff;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .job-card:hover {
            background-color: #f9f9f9;
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

        .share-button {
            background-color: #007bff;
            color: #fff;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 5px;
            display: inline-block;
        }
        .share-button:hover {
            background-color: #0056b3;
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
            text-align: center;
            margin: 20px 0; /* As per your example's container style */
            /* font-size will be inherited or can be set if needed, example uses default */
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

        @keyframes fadeInModal {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
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
</head>
<body>

    <div class="container">
        <div class="title-card">
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
                <a href="?type=all&filter=<?= urlencode($filter) ?>&search=<?= urlencode($search) ?>">📋 All Jobs (<span data-count-id="countAll">0</span>)</a>
                <a href="?type=remote&filter=<?= urlencode($filter) ?>&search=<?= urlencode($search) ?>">💻 Remote (<span data-count-id="countRemote">0</span>)</a>
                <a href="?type=onsite&filter=<?= urlencode($filter) ?>&search=<?= urlencode($search) ?>">🏢 Onsite (<span data-count-id="countOnsite">0</span>)</a>
                <a href="?type=hybrid&filter=<?= urlencode($filter) ?>&search=<?= urlencode($search) ?>">🌐 Hybrid (<span data-count-id="countHybrid">0</span>)</a>
                <h4>Quick Filters</h4>
                <a href="?type=full time&filter=<?= urlencode($filter) ?>&search=<?= urlencode($search) ?>">🕐 Full-Time (<span data-count-id="countFullTime">0</span>)</a>
                <a href="?type=part time&filter=<?= urlencode($filter) ?>&search=<?= urlencode($search) ?>">⌛ Part-Time (<span data-count-id="countPartTime">0</span>)</a>
                <a href="?type=internship&filter=<?= urlencode($filter) ?>&search=<?= urlencode($search) ?>">🎓 Internships (<span data-count-id="countInternship">0</span>)</a>
                <a href="?type=developer&filter=<?= urlencode($filter) ?>&search=<?= urlencode($search) ?>">👨‍💻 Developer (<span data-count-id="countDeveloper">0</span>)</a>
                <h4>Date Posted</h4>
                <a href="?filter=all&type=<?= urlencode($jobType) ?>&search=<?= urlencode($search) ?>">All Time (<span data-count-id="countAllTime">0</span>)</a>
                <a href="?filter=30&type=<?= urlencode($jobType) ?>&search=<?= urlencode($search) ?>">Past 30 Days (<span data-count-id="count30">0</span>)</a>
                <a href="?filter=7&type=<?= urlencode($jobType) ?>&search=<?= urlencode($search) ?>">Past 7 Days (<span data-count-id="count7">0</span>)</a>
                <a href="?filter=1&type=<?= urlencode($jobType) ?>&search=<?= urlencode($search) ?>">Past 24 Hours (<span data-count-id="count1">0</span>)</a>
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
                        <input type="text" name="search" placeholder="Search by job title, company, or location" value="<?php echo htmlspecialchars($search); ?>">
                        <!-- If in single job view, a search should clear the single job view -->
                        <?php if ($singleJobView): ?>
                            <!-- No need for a hidden input, just don't include job_id in the form action -->
                        <?php endif; ?>
                        <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                        <input type="hidden" name="type" value="<?php echo htmlspecialchars($jobType); ?>"> <!-- Preserve type filter on search -->
                        <button type="submit">Search</button>
                    </form>
                </div>
                
                <!-- Mobile Filters - Hidden on desktop, shown on mobile -->
                <div class="mobile-filters">
                    <a href="?type=all&filter=all&search=">🌟 Recommendations</a>
                    <a href="?type=all&filter=<?= urlencode($filter) ?>&search=<?= urlencode($search) ?>">All Jobs (<span data-count-id="countAll">0</span>)</a>
                    <a href="?filter=1&type=<?= urlencode($jobType) ?>&search=<?= urlencode($search) ?>">Past 24 Hours (<span data-count-id="count1">0</span>)</a>
                    <a href="?filter=7&type=<?= urlencode($jobType) ?>&search=<?= urlencode($search) ?>">Past 7 Days (<span data-count-id="count7">0</span>)</a>
                    <a href="?filter=30&type=<?= urlencode($jobType) ?>&search=<?= urlencode($search) ?>">Past 30 Days (<span data-count-id="count30">0</span>)</a>
                    <a href="?filter=all&type=<?= urlencode($jobType) ?>&search=<?= urlencode($search) ?>">All Time (<span data-count-id="countAllTime">0</span>)</a>
                    <a href="?type=remote&filter=<?= urlencode($filter) ?>&search=<?= urlencode($search) ?>">Remote (<span data-count-id="countRemote">0</span>)</a>
                    <a href="?type=onsite&filter=<?= urlencode($filter) ?>&search=<?= urlencode($search) ?>">Onsite (<span data-count-id="countOnsite">0</span>)</a>
                    <a href="?type=hybrid&filter=<?= urlencode($filter) ?>&search=<?= urlencode($search) ?>">Hybrid (<span data-count-id="countHybrid">0</span>)</a>
                    <a href="?type=full time&filter=<?= urlencode($filter) ?>&search=<?= urlencode($search) ?>">Full-Time (<span data-count-id="countFullTime">0</span>)</a>
                    <a href="?type=part time&filter=<?= urlencode($filter) ?>&search=<?= urlencode($search) ?>">Part-Time (<span data-count-id="countPartTime">0</span>)</a>
                    <a href="?type=internship&filter=<?= urlencode($filter) ?>&search=<?= urlencode($search) ?>">Internships (<span data-count-id="countInternship">0</span>)</a>
                    <a href="?type=developer&filter=<?= urlencode($filter) ?>&search=<?= urlencode($search) ?>">Developer (<span data-count-id="countDeveloper">0</span>)</a>
                </div>

                <div id="job-listings-container">
                    <?php 
                        // For non-AJAX requests, render the initial content
                        echo render_job_listings_and_pagination($pagedJobs, $singleJobView, $totalPages, $search, $filter, $jobType, $page);
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
                <a href="admin/dashboard.php" target="_blank">👤 Admin Login</a>
                <a href="?search=remote&filter=all">💻 Remote Jobs</a>
                <a href="?search=uae&filter=all">📍 UAE Jobs</a>
                <a href="mailto:support@uaejobs.com">📩 Contact Support</a>
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

        // --- Professional Share Modal Logic ---
        const shareModalElement = document.getElementById('jobShareModal');
        const shareModalJobTitleElement = shareModalElement.querySelector('.share-modal-job-title');
        const shareModalCloseButton = shareModalElement.querySelector('.share-modal-close-button');

        let currentJobUrlForModal = '';
        let currentJobTitleForModal = '';
        let currentJobCompanyForModal = '';

        function openProfessionalShareModal() {
            if (shareModalElement) {
                shareModalJobTitleElement.textContent = `${currentJobTitleForModal} at ${currentJobCompanyForModal}`;
                shareModalElement.style.display = 'flex';
            }
        }

        function closeProfessionalShareModal() {
            if (shareModalElement) {
                shareModalElement.style.display = 'none';
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
            const baseUrl = window.location.origin + window.location.pathname;
            currentJobUrlForModal = `${baseUrl}?job_id=${encodeURIComponent(jobId)}`;
            currentJobTitleForModal = title;
            currentJobCompanyForModal = company;
            
            const shareText = `Check out this job: ${currentJobTitleForModal} at ${currentJobCompanyForModal}`;
            const encodedUrl = encodeURIComponent(currentJobUrlForModal);
            const encodedShareText = encodeURIComponent(shareText);
            const encodedJobTitle = encodeURIComponent(`${currentJobTitleForModal} at ${currentJobCompanyForModal}`);

            document.getElementById('copyJobLinkButton').onclick = function() {
                navigator.clipboard.writeText(currentJobUrlForModal).then(() => {
                    alert('Job link copied to clipboard!');
                    closeProfessionalShareModal();
                }).catch(err => alert('Failed to copy link.'));
            };
            document.getElementById('shareViaWhatsApp').href = `https://wa.me/?text=${encodedShareText}%20${encodedUrl}`;
            document.getElementById('shareViaLinkedIn').href = `https://www.linkedin.com/shareArticle?mini=true&url=${encodedUrl}&title=${encodedJobTitle}&summary=${encodedShareText}`;
            document.getElementById('shareViaEmail').href = `mailto:?subject=${encodedJobTitle}&body=${encodedShareText}%0A%0A${currentJobUrlForModal}`;
            
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
                    history.pushState({}, '', url); // Use the original URL for history
                    
                    // Re-attach event listeners to new pagination links if any
                    attachAjaxToPagination();
                    // You might need to re-initialize other JS that depends on the new content here
                    // e.g., if job cards have specific JS behaviors.
                    // The toggleJobDetails is an inline onclick, so it should still work.
                    // The shareJob is also inline onclick.
                    // Scroll to the top of the job listings container
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
                const params = new URLSearchParams(formData);
                const actionUrl = searchForm.action || window.location.pathname; // Default to current path if action is empty
                const searchUrl = `${actionUrl}?${params.toString()}`;
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
        
        // When the page loads, if there's a 'search' URL parameter,
        // it means a search was just performed. Clear the visual input.
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const searchInput = document.querySelector('.search-bar input[name="search"]');
            
            // If a search parameter exists in the URL (meaning a search was just performed and page reloaded)
            // and the search input field exists on the page.
            if (urlParams.has('search') && searchInput) {
                searchInput.value = ''; // Clear the displayed value in the input box
            }

            // Initial attachment of event listeners for AJAX navigation
            attachAjaxToFilterLinks();
            attachAjaxToPagination();
        });

    </script>
</body>
</html>
