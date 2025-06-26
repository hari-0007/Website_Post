<?php
ob_start();
session_start();

// =============================================================================
// 1. CONFIGURATION & INITIALIZATION
// =============================================================================

// --- Constants ---
define('COOKIE_CONSENT_STATUS_NAME', 'cookie_consent_status');
define('USER_INTERESTS_COOKIE_NAME', 'user_job_interests');
define('USER_VIEWED_JOB_IDS_COOKIE_NAME', 'user_viewed_job_ids');
define('USER_UNIQUE_ID_COOKIE_NAME', 'user_unique_site_id');
define('JOIN_CHANNELS_POPUP_SHOWN_COOKIE_NAME', 'join_channels_popup_shown');
define('MAX_USER_INTERESTS', 5);

// --- File Paths ---
$usersFilePath = __DIR__ . '/data/users.json';
$jobsFilePath = __DIR__ . '/data/jobs.json';
$comprehensiveLogFilePath = __DIR__ . '/data/comprehensive_user_cookie_data_log.json';
$visitorCounterFile = __DIR__ . '/data/daily_visitors.json';

// --- Timezone ---
date_default_timezone_set('Asia/Kolkata');

// --- User Session ---
$isUserLoggedIn = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$userRole = $_SESSION['user_role'] ?? 'guest';
$userId = $_SESSION['user_id'] ?? null;

// =============================================================================
// 2. HELPER FUNCTIONS
// =============================================================================

/**
 * Records a page view, tracking total requests and unique visitors for the day.
 */
function recordPageView($filePath) {
    $today = date('Y-m-d');
    $visitorData = file_exists($filePath) ? json_decode(file_get_contents($filePath), true) : [];
    if (!is_array($visitorData)) $visitorData = [];

    if (!isset($visitorData[$today]) || !is_array($visitorData[$today])) {
        $visitorData[$today] = ['unique_visits' => 0, 'total_requests' => 0];
    }

    $visitorData[$today]['total_requests']++;

    if (isset($_COOKIE[COOKIE_CONSENT_STATUS_NAME]) && $_COOKIE[COOKIE_CONSENT_STATUS_NAME] === 'accepted' && !isset($_COOKIE['unique_visitor'])) {
        $visitorData[$today]['unique_visits']++;
        $visitor_cookie_options = ['expires' => time() + (24 * 60 * 60), 'path' => '/', 'samesite' => 'Lax'];
        setcookie('unique_visitor', '1', $visitor_cookie_options);
    }

    file_put_contents($filePath, json_encode($visitorData));
}

/**
 * Formats an AI summary string, converting markdown bold to <strong> and newlines to <br>.
 */
function formatAiSummary($summary) {
    if ($summary === null) return '';
    $formatted = htmlspecialchars($summary, ENT_QUOTES, 'UTF-8');
    $formatted = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $formatted);
    $formatted = nl2br($formatted);
    return $formatted;
}

/**
 * Renders the HTML for the job listings and pagination controls.
 */
function render_job_listings_and_pagination($pagedJobs, $singleJobView, $totalPages, $search, $filter, $jobType, $page, $isRecommendationsView, $isUserLoggedIn, $userRole) {
    ob_start();
?>
    <?php if(empty($pagedJobs)): ?>
        <p class="no-jobs-message">No matching jobs found for the current criteria.</p>
    <?php else: ?>
        <?php if ($singleJobView): ?>
            <a href="<?= strtok($_SERVER["REQUEST_URI"], '?') ?>" id="showAllJobsBtn" class="button">Show All Jobs</a>
        <?php endif; ?>

        <?php
        $threeMonthsAgoTimestamp = strtotime('-3 months');
        foreach ($pagedJobs as $job):
            $isExpired = ($job['posted_on_unix_ts'] ?? 0) < $threeMonthsAgoTimestamp;
        ?>
        <div class="job-card" data-job-id="<?= htmlspecialchars($job['id'] ?? '') ?>">
            <div class="animated-icons-container">
                <span class="animated-icon icon-magnify" style="top: <?= rand(5, 95) ?>%; left: <?= rand(5, 95) ?>%; animation-delay: -<?= rand(0, 10) ?>s;">🔍</span>
                <span class="animated-icon icon-briefcase" style="top: <?= rand(5, 95) ?>%; left: <?= rand(5, 95) ?>%; animation-delay: -<?= rand(0, 10) ?>s;">💼</span>
                <span class="animated-icon icon-document" style="top: <?= rand(5, 95) ?>%; left: <?= rand(5, 95) ?>%; animation-delay: -<?= rand(0, 10) ?>s;">📄</span>
                <span class="animated-icon icon-profile" style="top: <?= rand(5, 95) ?>%; left: <?= rand(5, 95) ?>%; animation-delay: -<?= rand(0, 10) ?>s;">👤</span>
                <span class="animated-icon icon-graph" style="top: <?= rand(5, 95) ?>%; left: <?= rand(5, 95) ?>%; animation-delay: -<?= rand(0, 10) ?>s;">📈</span>
            </div>

            <h3><?= htmlspecialchars($job['title'] ?? 'N/A') ?>
                <?php if (!empty($job['vacant_positions']) && $job['vacant_positions'] > 1): ?><span class="job-card-vacancies">(<?= htmlspecialchars($job['vacant_positions']) ?> vacancies)</span><?php endif; ?>
                <?php if ($isExpired): ?><span class="job-card-expired">Expired</span><?php endif; ?>
            </h3>
            <p class="job-card-company-location"><strong><?= htmlspecialchars($job['company'] ?? 'N/A') ?></strong> – <?= htmlspecialchars($job['location'] ?? 'N/A') ?></p>
            
            <p class="job-summary"><?= formatAiSummary($job['ai_summary'] ?? '') ?><?php if(strlen($job['description'] ?? '') > strlen($job['ai_summary'] ?? '')) echo "..."; ?></p>

            <div class="job-details" style="display: none;">
                <div class="formatted-description">
                    <?php 
                        $summary_content = $job['ai_summary'] ?? '';
                        $description_content = $job['description'] ?? '';

                        // Always show the AI summary if it exists and is not empty.
                        if (!empty(trim($summary_content))) {
                            echo '<div class="ai-summary-block">';
                            echo '<h5>AI-Generated Summary</h5>';
                            echo '<div>' . formatAiSummary($summary_content) . '</div>';
                            echo '</div>';
                        }

                        // Show the full description if it exists and is different from the summary.
                        if (!empty(trim($description_content)) && trim(strip_tags($description_content)) !== trim(strip_tags($summary_content))) {
                            echo '<h5>Full Description</h5>';
                            echo '<div>' . formatAiSummary($description_content) . '</div>';
                        } elseif (empty(trim($summary_content)) && empty(trim($description_content))) {
                            echo '<p>No description available for this job.</p>';
                        }
                    ?>
                </div>
                <div class="job-caution-alert-wrapper">
                    <span class="job-caution-alert" title="Important Security Advice">⚠️</span>
                </div>
            </div>

            <?php if (!empty($job['experience'])): ?>
                <p class="job-card-meta"><strong>🛠 Experience:</strong> 
                    <?php 
                        $exp = $job['experience'];
                        echo htmlspecialchars($exp);
                        if (is_numeric($exp)) { echo ' years'; }
                    ?>
                </p>
            <?php endif; ?>
            <?php if (!empty($job['salary'])): ?>
                <p class="job-card-meta"><strong>💰 Salary:</strong> <?= htmlspecialchars($job['salary']) ?></p>
            <?php endif; ?>

            <?php if (!isset($job['posted_by_id'])): ?>
                <?php if (!empty($job['phones'])): ?>
                    <p class="job-card-meta"><strong>📞 Phone:</strong>
                    <?php if ($isExpired): ?><span class="blurred-text">05X-XXX-XXXX</span>
                    <?php else: foreach (explode(',', $job['phones']) as $phone): ?><a href="tel:<?= htmlspecialchars(trim($phone)) ?>"><?= htmlspecialchars(trim($phone)) ?></a>&nbsp;<?php endforeach; endif; ?>
                    </p>
                <?php endif; ?>
                <?php if (!empty($job['emails'])): ?>
                    <p class="job-card-meta"><strong>📧 Email:</strong>
                    <?php if ($isExpired): ?><span class="blurred-text">support@jobhunt.top</span>
                    <?php else: foreach (explode(',', $job['emails']) as $email): ?><a href="mailto:<?= htmlspecialchars(trim($email)) ?>"><?= htmlspecialchars(trim($email)) ?></a>&nbsp;<?php endforeach; endif; ?>
                    </p>
                <?php endif; ?>
            <?php endif; ?>

            <small>Posted on <?= htmlspecialchars($job['posted_on'] ?? 'N/A') ?></small>

            <?php if (!$isExpired): ?>
                <div class="job-card-actions">
                    <button class="share-button job-card-action-button">Share</button>
                    <?php if ($isUserLoggedIn && $userRole === 'jobseeker' && isset($job['posted_by_id'])): ?>
                        <button class="apply-button job-card-action-button">Apply Now</button>
                    <?php elseif (!$isUserLoggedIn && isset($job['posted_by_id'])): ?>
                        <button class="apply-button job-card-action-button" data-auth-action="login">Login to Apply</button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!$singleJobView && $totalPages > 1): ?>
    <div class="pagination-container">
        <?php
        $baseQuery = "?search=" . urlencode($search) . "&filter=" . urlencode($filter) . "&type=" . urlencode($jobType) . ($isRecommendationsView ? "&recommendations=1" : "");
        if ($page > 1) {
            echo '<a href="' . $baseQuery . '&page=1" class="nav-arrow first-arrow"><span class="short-text">««</span><span class="long-text"> First</span></a>';
            echo '<a href="' . $baseQuery . '&page=' . ($page - 1) . '" class="nav-arrow prev-arrow"><span class="short-text">«</span><span class="long-text"> Previous</span></a>';
        } else {
            echo '<a href="#" class="nav-arrow first-arrow disabled"><span class="short-text">««</span><span class="long-text"> First</span></a>';
            echo '<a href="#" class="nav-arrow prev-arrow disabled"><span class="short-text">«</span><span class="long-text"> Previous</span></a>';
        }
        ?>
        <div class="page-numbers">
            <?php
                $num_links_to_show = 3;
                if ($totalPages <= $num_links_to_show) {
                    for ($i = 1; $i <= $totalPages; $i++) echo '<a href="' . $baseQuery . '&page=' . $i . '" class="' . ($i == $page ? 'current-page' : '') . '">' . $i . '</a>';
                } else {
                    echo '<a href="' . $baseQuery . '&page=1" class="' . (1 == $page ? 'current-page' : '') . '">1</a>';
                    if ($page > 2) echo '<span class="ellipsis">...</span>';
                    if ($page > 1 && $page < $totalPages) echo '<a href="' . $baseQuery . '&page=' . $page . '" class="current-page">' . $page . '</a>';
                    if ($page < $totalPages - 1) echo '<span class="ellipsis">...</span>';
                    echo '<a href="' . $baseQuery . '&page=' . $totalPages . '" class="' . ($totalPages == $page ? 'current-page' : '') . '">' . $totalPages . '</a>';
                }
            ?>
        </div>
        <?php
        if ($page < $totalPages) {
            echo '<a href="' . $baseQuery . '&page=' . ($page + 1) . '" class="nav-arrow next-arrow"><span class="long-text">Next </span><span class="short-text">»</span></a>';
            echo '<a href="' . $baseQuery . '&page=' . $totalPages . '" class="nav-arrow last-arrow"><span class="long-text">Last </span><span class="short-text">»»</span></a>';
        } else {
            echo '<a href="#" class="nav-arrow next-arrow disabled"><span class="long-text">Next </span><span class="short-text">»</span></a>';
            echo '<a href="#" class="nav-arrow last-arrow disabled"><span class="long-text">Last </span><span class="short-text">»»</span></a>';
        }
        ?>
    </div>
    <?php endif; ?>
<?php
    return ob_get_clean();
}

// =============================================================================
// 3. COOKIE & LOGGING MANAGEMENT
// =============================================================================

// --- MANAGE UNIQUE USER ID COOKIE ---
$currentUserUniqueID = $_COOKIE[USER_UNIQUE_ID_COOKIE_NAME] ?? null;
if (!$currentUserUniqueID) {
    $currentUserUniqueID = bin2hex(random_bytes(16)); // Generate a strong unique ID
    $uid_cookie_options = [
        'expires' => time() + (365 * 24 * 60 * 60), // Expires in 1 year
        'path' => '/',
        'samesite' => 'Lax',
        'httponly' => true,
    ];
    setcookie(USER_UNIQUE_ID_COOKIE_NAME, $currentUserUniqueID, $uid_cookie_options);
    $_COOKIE[USER_UNIQUE_ID_COOKIE_NAME] = $currentUserUniqueID; // Make available for current script run
}

// --- BEGIN NEW COMPREHENSIVE COOKIE DATA LOGGING ---
$comprehensiveLogFilePath = __DIR__ . '/data/comprehensive_user_cookie_data_log.json';

// Read existing comprehensive log
if (file_exists($comprehensiveLogFilePath)) {
    $existingCompLogJson = file_get_contents($comprehensiveLogFilePath);
    if ($existingCompLogJson !== false && !empty($existingCompLogJson)) {
        $decodedCompLog = json_decode($existingCompLogJson, true);
        if (is_array($decodedCompLog) && (empty($decodedCompLog) || array_keys($decodedCompLog) !== range(0, count($decodedCompLog) - 1))) { // is associative array
             $allUsersData = $decodedCompLog;
        } else {
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
        $context = stream_context_create(['http' => ['timeout' => 2]]); // 2 second timeout
        $locationJson = @file_get_contents("http://ip-api.com/json/{$ip_address}?fields=status,message,country,countryCode,region,regionName,city,zip,lat,lon,timezone,isp,org,as,query", false, $context);

        if ($locationJson !== false) {
            $locationData = json_decode($locationJson, true);
            if ($locationData && isset($locationData['status']) && $locationData['status'] === 'success') {
                // We can store the whole response or pick specific fields
                // Only add location_info if the API call was successful
                unset($locationData['status']); // No need to log the status field itself
                $currentLogEntry['location_info'] = $locationData;
            }
        }
    }
}

// Only attempt to fetch and log job interests if consent is explicitly accepted
if (isset($_COOKIE[COOKIE_CONSENT_STATUS_NAME]) && $_COOKIE[COOKIE_CONSENT_STATUS_NAME] === 'accepted') {
    $interestsFromCookieRaw = $_COOKIE[USER_INTERESTS_COOKIE_NAME] ?? '[]';
    $decodedInterestsForLog = null; // Initialize
    try {
        $decodedInterestsForLog = json_decode($interestsFromCookieRaw, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) { error_log("Exception decoding job interests cookie: " . $e->getMessage()); }
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
    file_put_contents($comprehensiveLogFilePath, json_encode($allUsersData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}
// --- END COMPREHENSIVE COOKIE DATA LOGGING ---

// Record interests if consent is given
if (isset($_COOKIE[COOKIE_CONSENT_STATUS_NAME]) && $_COOKIE[COOKIE_CONSENT_STATUS_NAME] === 'accepted') {
    $userInterests = [];
    $currentInterestsRaw = isset($_COOKIE[USER_INTERESTS_COOKIE_NAME]) ? $_COOKIE[USER_INTERESTS_COOKIE_NAME] : '[]';
    try {
        $userInterests = json_decode($currentInterestsRaw, true);
        if (!is_array($userInterests)) { // Ensure it's an array after decoding
            $userInterests = [];
        }
    } catch (JsonException $e) { $userInterests = []; error_log("Exception decoding interests cookie: " . $e->getMessage()); }

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
            'samesite' => 'Lax'
        ];
        setcookie(USER_INTERESTS_COOKIE_NAME, json_encode($userInterests), $cookie_options);
        $_COOKIE[USER_INTERESTS_COOKIE_NAME] = json_encode($userInterests); // Make available for current script run
    }
}

// =============================================================================
// 4. DATA FETCHING & PREPARATION
// =============================================================================

// --- Load Job Data ---
$phpJobsArray = []; // For PHP's internal filtering and display
$jobsForJS = [];    // For passing to JavaScript with pre-calculated Unix timestamps
$decodedJobs = []; // Holds the pristine full list of jobs from JSON

if (file_exists($jobsFilePath)) {
    $jsonData = file_get_contents($jobsFilePath);
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
     error_log("Frontend Error: Job data file not found: " . $jobsFilePath);
}

// =============================================================================
// 5. REQUEST PARAMETER & FILTERING LOGIC
// =============================================================================

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

// --- START: Logic to determine if this is a recommendations view ---
$isRecommendationsView = isset($_GET['recommendations']) && $_GET['recommendations'] === '1';

// Check if a specific job ID is requested to be expanded
$jobIdToExpandFromUrl = isset($_GET['job_id']) ? trim($_GET['job_id']) : null;
$singleJobView = false;
$jobWasFoundForSingleView = false; // Flag to indicate if the job_id in URL was valid

if ($jobIdToExpandFromUrl) {
    $tempFoundJob = null;
    // IMPORTANT: Check against $decodedJobs (the full, unfiltered list from JSON)
    if (!empty($decodedJobs)) { // Ensure $decodedJobs is populated
        foreach ($decodedJobs as $job_item) {
            if (isset($job_item['id']) && $job_item['id'] === $jobIdToExpandFromUrl) {
                $tempFoundJob = $job_item;
                break;
            }
        }
    }

    if ($tempFoundJob) {
        $phpJobsArray = [$tempFoundJob]; // Now $phpJobsArray contains only this job
        $singleJobView = true;
        $jobWasFoundForSingleView = true; // Mark that the job was found

        // If a single job is viewed, reset filter parameters for display and canonical URL generation.
        // This ensures the canonical URL is clean for the single job page.
        $search_param_raw = ''; $search_param = '';
        $type_param_raw = ''; $type_param = '';
        $filter_param_raw = 'all'; $filter_param = 'all';
        $isRecommendationsView = false; // Single job view overrides recommendations view state
    } else {
        // Job ID was specified in URL, but not found in the master list. Issue a 404.
        ob_clean(); // Clear any output that might have occurred (like session cookie headers)
        header("HTTP/1.1 404 Not Found");
        // Output a custom 404 page
        echo "<!DOCTYPE html><html lang=\"en\"><head><meta charset=\"UTF-8\"><title>404 Job Not Found</title>";
        echo "<style>body { font-family: Arial, sans-serif; text-align: center; padding-top: 50px; } h1 { color: #333; } p { color: #666; } a { color: #007bff; text-decoration: none; }</style>";
        echo "</head><body>";
        echo "<h1>404 - Job Not Found</h1>";
        echo "<p>The job you are looking for (ID: " . htmlspecialchars($jobIdToExpandFromUrl) . ") does not exist or is no longer available.</p>";
        echo "<p><a href=\"" . strtok($_SERVER["REQUEST_URI"], '?') . "\">Return to Job Listings</a></p>";
        echo "</body></html>";
        ob_end_flush(); // Send the output
            exit; // Stop script execution after sending 404 page
    }
}

// Interest-based filtering logic
// This applies ONLY if no explicit search, type, or date filters are in the URL.
// It modifies $phpJobsArray directly if conditions are met.
$appliedInterestFilter = false;
if (!$singleJobView &&
    $isRecommendationsView && // Apply interest filter ONLY if recommendations=1 is explicitly set
    isset($_COOKIE[COOKIE_CONSENT_STATUS_NAME]) && $_COOKIE[COOKIE_CONSENT_STATUS_NAME] === 'accepted') {

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
        }
    }
}

// --- Record Page View ---
recordPageView($visitorCounterFile);

// Feedback alert
if (!empty($_SESSION['feedback_alert'])) {
    $msg = $_SESSION['feedback_alert'];
    echo "<script>alert(" . json_encode($msg) . ");</script>";
    unset($_SESSION['feedback_alert']);
}

// Initialize $filteredJobs. This will be the array that gets progressively filtered.
// If interest filter was applied, $phpJobsArray is already narrowed. Otherwise, it's the full list (or single job).
$filteredJobs = $phpJobsArray; 

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
            return false; 
        }
        return isset($job['type']) && strtolower(trim((string)$job['type'])) === $jobType;
    });
}

// 2. Apply Search Filter (if search term is provided)
// This is applied *after* the type filter, to the result of the type filter.
if ($search !== '') {
    $tempJobs = [];
    foreach ($filteredJobs as $job) { // $filteredJobs here is potentially already type-filtered
        if (!is_array($job)) { 
            error_log("Skipping non-array job item in search filter: " . print_r($job, true));
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
                 if (isset($job['posted_on']) && is_string($job['posted_on'])) {
                    $parsed_ts = strtotime($job['posted_on']);
                    $jobTimestamp = ($parsed_ts === false) ? 0 : $parsed_ts;
                 } else {
                    $jobTimestamp = 0; // Still 0 if 'posted_on' is also unusable
                 }
            }
            if ($jobTimestamp > 0 && $jobTimestamp >= $cutoffDate) { // Job must be on or after the cutoff date
                $tempJobs[] = $job;
            }
        }
        $filteredJobs = $tempJobs;
    }
}

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

// =============================================================================
// 6. PAGINATION
// =============================================================================
$limit = 10;
$page = max(1, intval($_GET['page'] ?? 1)); // Page number from URL
$totalJobs = count($filteredJobs);
$totalPages = ceil($totalJobs / $limit);
$offset = ($page - 1) * $limit;
$pagedJobs = array_slice($filteredJobs, $offset, $limit);
if ($page > $totalPages && $totalJobs > 0) { // Handle invalid page number
    header("Location: ?page=$totalPages");
    exit;
}

// =============================================================================
// 7. SEO & METADATA
// =============================================================================
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$currentUrlPath = strtok($_SERVER["REQUEST_URI"], '?'); // Path without query string, e.g., /index.php

$canonicalUrlParams = [];

// 1. Single Job View (if $jobWasFoundForSingleView is true)
if ($singleJobView && $jobWasFoundForSingleView && isset($jobIdToExpandFromUrl)) {
    $canonicalUrl = $protocol . $host . $currentUrlPath . '?job_id=' . urlencode($jobIdToExpandFromUrl);
} else {
    // 2. Recommendations View
    if ($isRecommendationsView) {
        $canonicalUrlParams['recommendations'] = '1';
    }

    // 3. Filters and Search (using processed parameters for consistency)
    if (!empty($search_param)) { // $search_param is from trim(strtolower($_GET['search']))
        $canonicalUrlParams['search'] = $search_param;
    }
    if (!empty($type_param)) { // $type_param is strtolower, 'all' becomes ''
        $canonicalUrlParams['type'] = $type_param;
    }
    if ($filter_param !== 'all') { // $filter_param is validated
        $canonicalUrlParams['filter'] = $filter_param;
    }

    // 4. Pagination
    $currentPageForCanonical = isset($_GET['page']) ? intval($_GET['page']) : 1;
    if ($currentPageForCanonical > 1) { // Only add 'page' param if it's not the first page
        $canonicalUrlParams['page'] = $currentPageForCanonical;
    }

    // Sort parameters by key to ensure consistent URL regardless of $_GET order
    ksort($canonicalUrlParams);

    $canonicalQueryString = http_build_query($canonicalUrlParams);
    $canonicalUrl = $protocol . $host . $currentUrlPath . ($canonicalQueryString ? '?' . $canonicalQueryString : '');
}

$prevLink = null;
$nextLink = null;
if (!$singleJobView && $totalPages > 1) {
    $paginationBaseParamsForPrevNext = $canonicalUrlParams; // Start with params used for canonical
    unset($paginationBaseParamsForPrevNext['page']); // Remove page from base for constructing prev/next

    if ($page > 1) { // $page is the current page number from $_GET['page'] ?? 1
        $prevPageParams = $paginationBaseParamsForPrevNext;
        if ($page > 2) { $prevPageParams['page'] = $page - 1; } // Link to page 1 has no 'page' param
        $prevQueryString = http_build_query($prevPageParams);
        $prevLink = $protocol . $host . $currentUrlPath . ($prevQueryString ? '?' . $prevQueryString : '');
    }
    if ($page < $totalPages) {
        $nextPageParams = $paginationBaseParamsForPrevNext;
        $nextPageParams['page'] = $page + 1;
        $nextLink = $protocol . $host . $currentUrlPath . '?' . http_build_query($nextPageParams);
    }
}

// --- Logic for Conditional Noindex ---
$shouldNoIndexThisPage = false;
if (!$singleJobView && empty($pagedJobs) && ($search_param !== '' || $type_param !== '' || $filter_param !== 'all' || $isRecommendationsView)) {
    $shouldNoIndexThisPage = true;
}

// =============================================================================
// 8. AJAX HANDLING
// =============================================================================
$isAjaxRequest = isset($_GET['ajax']) && $_GET['ajax'] === '1';

if ($isAjaxRequest) {
    header('Content-Type: text/html');
    echo render_job_listings_and_pagination($pagedJobs, $singleJobView, $totalPages, $search, $filter, $jobType, $page, $isRecommendationsView, $isUserLoggedIn, $userRole);
    exit; 
}

// =============================================================================
// 9. VIEW (HTML OUTPUT)
// =============================================================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Top Job Hunt</title>
    
    <!-- SEO -->
    <script src="https://www.google.com/recaptcha/api.js?onload=onloadRecaptchaCallback&render=explicit" async defer></script>
    <link rel="icon" type="image/png" href="/data/images/logo.png">
    <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl) ?>" />
    <?php if ($shouldNoIndexThisPage): ?>
    <meta name="robots" content="noindex, follow" />
    <?php endif; ?>
    <?php if ($prevLink): ?>
    <link rel="prev" href="<?= htmlspecialchars($prevLink) ?>" />
    <?php endif; ?>
    <?php if ($nextLink): ?>
    <link rel="next" href="<?= htmlspecialchars($nextLink) ?>" />
    <?php endif; ?>
    
    <!-- Styles -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <!-- Mobile Navigation Menu -->
    <nav class="mobile-nav" id="mobileNav">
        <div class="mobile-nav-header">
            <h4>Menu</h4>
            <button class="close-menu-btn" aria-label="Close menu">&times;</button>
        </div>
        <div class="mobile-nav-links">
            <!-- Auth links are duplicated here for mobile -->
            <?php if ($isUserLoggedIn): ?>
                <?php if ($userRole === 'recruiter'): ?>
                    <a href="profile.php?action=post_job_form" class="button">Post Job</a>
                <?php endif; ?>
                <a href="profile.php" class="auth-link">My Profile</a>
                <a href="auth.php?action=logout" class="auth-link">Logout</a>
            <?php else: ?>
                <a href="#" class="auth-link button mobile-login-button" onclick="openAuthModal('login'); closeMobileMenu(); return false;">Login</a>
                <a href="#" class="auth-link button" onclick="openAuthModal('register'); closeMobileMenu(); return false;">Register</a>
            <?php endif; ?>
        </div>
        <div class="mobile-nav-social">
            <h4>Follow Channels</h4>
            <a href="https://whatsapp.com/channel/0029VbBMdgCI7BeBLRm1Au1I" target="_blank" class="social-link whatsapp">
                <span class="social-icon">📱</span> WhatsApp
            </a>
            <a href="https://t.me/uaejobprofessionals" target="_blank" class="social-link telegram">
                <span class="social-icon">📢</span> Telegram
            </a>
            <!-- You can add an Instagram link here if available -->
            <!-- <a href="#" target="_blank" class="social-link instagram"><span class="social-icon">📷</span> Instagram</a> -->
        </div>
    </nav>
    <div class="mobile-nav-overlay" id="mobileNavOverlay"></div>

    <div class="container">
        <header class="site-header-main">
            <div class="site-branding">
                <a href="<?= strtok($_SERVER["REQUEST_URI"], '?') ?>" class="site-logo-link" aria-label="Homepage">
                    <div class="site-logo">
                        <img src="/data/images/logo.png" alt="Job Portal Logo">
                    </div>
                </a>
                <a href="<?= strtok($_SERVER["REQUEST_URI"], '?') ?>" class="site-title-main">Job Hunt</a>
            </div>
            <div class="user-auth-links">
                <?php if ($isUserLoggedIn): ?>
                    <?php if ($userRole === 'recruiter'): ?>
                        <a href="profile.php?action=post_job_form" class="button" style="padding: 8px 12px; font-size: 0.9em; background-color: #e67e22; text-transform: uppercase;">Post Job</a>
                    <?php endif; ?>
                    <a href="profile.php" class="auth-link">My Profile</a>
                    <a href="auth.php?action=logout" class="auth-link">Logout</a>
                <?php else: ?>
                    <button onclick="openAuthModal('login')">Login</button>
                    <button onclick="openAuthModal('register')" class="button" style="padding: 8px 12px; font-size: 0.9em;">Register</button>
                <?php endif; ?>
            </div>
            <!-- Mobile Menu Toggle Button -->
            <button class="menu-toggle" id="menuToggle" aria-label="Open menu" aria-expanded="false">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </header>

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
                        <input type="text" name="search" placeholder="Search by job title, company, or location" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                        <!-- If in single job view, a search should clear the single job view -->
                        <?php if ($singleJobView): ?>
                            <!-- No need for a hidden input, just don't include job_id in the form action -->
                        <?php endif; ?>
                        <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                        <input type="hidden" name="type" value="<?= htmlspecialchars($jobType) ?>">
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
                        echo render_job_listings_and_pagination($pagedJobs, $singleJobView, $totalPages, $search, $filter, $jobType, $page, $isRecommendationsView, $isUserLoggedIn, $userRole);
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
                <a href="#" onclick="openAuthModal('login'); return false;">🔑 Login</a>
                <a href="#" onclick="openAuthModal('register'); return false;">✍️ Register</a>
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
                    <div class="star-rating-container" id="feedbackStarsContainer" style="display:none;">
                        <label class="star-rating-label">Rate your experience:</label>
                        <div class="star-rating" id="feedbackStarRating">
                            <span>&#9733;</span><span>&#9733;</span><span>&#9733;</span><span>&#9733;</span><span>&#9733;</span>
                        </div>
                    </div>
                    <input type="hidden" name="rating" id="feedbackRatingInput" value="0">
                    <textarea name="message" placeholder="Your Message" rows="3" required></textarea>
                    <div id="feedbackRecaptchaContainer" style="margin-bottom: 10px; transform:scale(0.9); transform-origin:0 0; display:none;"></div>
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
        <!-- Close button removed as per request -->
        <h4>Important Security Advice</h4>
        <p>Be cautious! Reputable employers and agencies don't charge you for a job.</p>
        <p>If an agency demands fees, always check their license and, if possible, visit them in person before paying anything.</p>
        <div id="reportJobFormContainer">
            <p id="reportedJobInfo" style="display:none;"></p> <!-- For Job Title & Company -->
            <form id="reportForm">
                <h5>Report This Job</h5>
                <label for="reportName">Your Name:</label>
                <input type="text" id="reportName" name="reportName">
                <label for="reportEmail">Your Email (for follow-up):</label>
                <input type="email" id="reportEmail" name="reportEmail">
                <label for="reportReason">Reason for Report:</label>
                <textarea id="reportReason" name="reportReason" rows="3" required placeholder="Please provide details..."></textarea>
                <div id="reportRecaptchaContainer" style="margin-top:10px; margin-bottom: 10px; transform:scale(0.9); transform-origin:0 0;"></div>
                <div id="reportStatusMessage" style="display:none; margin-top:10px;"></div>
            </form>
        </div>

        <div class="modal-actions">
            <button id="reportIssueBtn" class="report-issue-btn">Report Issue</button>
            <button id="cautionUnderstoodBtn" class="understood-btn">Understood</button>
        </div>
    </div>

        <!-- New Join Channels Popup Modal -->
    <div id="joinChannelsPopup" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="closeJoinChannelsPopup()">&times;</span>
            <h4>🚀 Stay Ahead!</h4>
            <p>Don't miss out on the latest job opportunities. Join our channels for daily updates:</p>
            <a href="https://whatsapp.com/channel/0029VbBMdgCI7BeBLRm1Au1I" target="_blank" class="join-now button" onclick="handleJoinChannelsClick()">Join WhatsApp</a>
            <a href="https://t.me/uaejobprofessionals" target="_blank" class="join-telegram button" onclick="handleJoinChannelsClick()" style="margin-left: 10px;">Join Telegram</a>
        </div>
    </div>

    <!-- Generic WhatsApp Modal (if needed elsewhere) -->
    <div id="modal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="closeWModal()">&times;</span>
            <h4>Join our WhatsApp Channel</h4>
            <p>Get job alerts directly on WhatsApp!</p>
            <a href="https://whatsapp.com/channel/0029VbBMdgCI7BeBLRm1Au1I" target="_blank" class="join-now button" onclick="closeWModal()">Join WhatsApp</a>
        </div>
    </div>
    <!-- Auth Modal for Login/Register -->
    <div id="authModal" class="modal" style="display:none;">
        <div class="modal-content auth-modal-content">
            <span class="close" onclick="closeAuthModal()">&times;</span>
            
            <div id="authLoginView">
                <h4>Login to Your Account</h4>
                <form id="loginForm">
                    <input type="email" name="email" placeholder="Email" required>
                    <input type="password" name="password" placeholder="Password" required>
                    <div id="loginErrorMessage" class="feedback-message error" style="display: none;"></div>
                    <button type="submit" class="button">Login</button>
                </form>
                <p>Don't have an account? <a href="#" onclick="showRegisterView(event)">Register here</a></p>
            </div>

            <div id="authRegisterView" style="display:none;">
                <h4>Create a New Account</h4>
                <form id="registerForm">
                    <input type="text" name="username" placeholder="Username" required>
                    <input type="email" name="email" placeholder="Email" required>
                    <input type="password" name="password" placeholder="Password" required>
                    <select name="role" required><option value="" disabled selected>I am a...</option><option value="jobseeker">Job Seeker</option><option value="recruiter">Recruiter</option></select>
                    <div id="registerErrorMessage" class="feedback-message error" style="display: none;"></div>
                    <button type="submit" class="button">Register</button>
                </form>
                <p>Already have an account? <a href="#" onclick="showLoginView(event)">Login here</a></p>
            </div>
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

    <div id="cookieConsentBanner" style="display: none;">
        <p>We use cookies to enhance your experience and show you personalized job listings. By clicking "Accept", you agree to our use of cookies. You can learn more in our <a href="privacy-policy.php" target="_blank" style="color:#7cceff; text-decoration:underline;">Privacy Policy</a>.</p>
        <button id="acceptCookieConsent" class="button">Accept</button>
    </div>

    <!-- Pass PHP data to JS -->
    <script>
        const serverData = {
            jobs: <?= json_encode($jobsForJS); ?>,
            isUserLoggedIn: <?= json_encode($isUserLoggedIn); ?>,
            userRole: '<?= $userRole ?>',
            cookieConsentName: '<?= COOKIE_CONSENT_STATUS_NAME ?>',
            viewedJobsCookieName: '<?= USER_VIEWED_JOB_IDS_COOKIE_NAME ?>',
            joinPopupCookieName: '<?= JOIN_CHANNELS_POPUP_SHOWN_COOKIE_NAME ?>',
            recaptchaSiteKey: '6LdejG0rAAAAADz6_mIuRwBirtmdojNX8ax6WBws'
        };
    </script>

    <!-- Main Application Script -->
    <script src="assets/js/main.js" defer></script>

<?php
    ob_end_flush(); // Send all buffered output to the browser
?>
</body>
</html>
