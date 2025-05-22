<?php

// admin/fetch_content.php - Fetches content for a specific view via AJAX

// Start output buffering to prevent premature output
ob_start();

// Ensure no whitespace or output before session_start()
if (session_status() === PHP_SESSION_NONE) {
    session_start(); // Start session only if not already started
}

// Include configuration and helper functions
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/user_helpers.php'; // For loadUsers, saveUsers, findUserByUsername, getRoleParts
require_once __DIR__ . '/includes/job_helpers.php';
require_once __DIR__ . '/includes/feedback_helpers.php';
require_once __DIR__ . '/includes/user_manager_helpers.php'; // For createUser, deleteUser, updateUser
require_once __DIR__ . '/includes/stats_helpers.php'; // For stats functions
require_once __DIR__ . '/includes/view_helpers.php'; // For most viewed/shared jobs helper

// Check if the user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    if (!headers_sent()) {
        http_response_code(401); // Unauthorized
        header('Location: dashboard.php?view=login');
    }
    exit;
}

// --- Role Definitions ---
$allRegionalAdminRoles = ['India_Admin', 'Middle_East_Admin', 'USA_Admin', 'Europe_Admin'];
$allRegionalManagerRoles = ['India_Manager', 'Middle_East_Manager', 'USA_Manager', 'Europe_Manager'];
$allRegionalUserRoles = ['India_User', 'Middle_East_User', 'USA_User', 'Europe_User'];

// Get the requested view from the GET parameters.
// If 'dashboard' is requested, it implies 'dashboard_overview'.
$originalGetView = $_GET['view'] ?? null; // Store original for logging
$rawRequestedView = $originalGetView ?? 'dashboard_overview'; // Default to dashboard_overview if not set

if (empty(trim($rawRequestedView)) || $rawRequestedView === 'dashboard') { // Check for empty or 'dashboard'
    $requestedView = 'dashboard_overview';
} else {
    $requestedView = $rawRequestedView;
}
error_log("[FETCH_CONTENT_DEBUG] fetch_content.php - Original \$_GET['view']: '" . ($originalGetView ?? 'NOT_SET') . "'. Processed \$requestedView: '" . $requestedView . "'.");

// Initialize variables that might be needed by the views
$allJobs = [];
$feedbackMessages = [];
$totalViews = 0;
$jobsTodayCount = 0;
$jobsMonthlyCount = 0;
$graphLabels = []; // For job posts chart
$graphData = [];   // For job posts chart
$users = [];
$jobToEdit = null;
$userToEdit = null;
$whatsappMessage = null;
$telegramMessage = null;
$filteredUsersCountForManager = 0;

// New variables for enhanced dashboard
$userCountsByRole = [];
$userCountsByStatus = [];
$recentJobs = [];
$recentMessages = [];
$recentPendingUsers = [];

// New variables for achievements view
$achievementsChartData = [];
$achievementsChartLabels = [];

// New variables for user performance tracking
$userPerformanceOverall = [];
$userPerformanceLast30Days = [];
$userPerformanceToday = [];
$performanceLeaderboard = [];
$mostViewedJobs = []; // New variable for most viewed jobs
$mostSharedJobs = []; // New variable for most shared jobs

// Variables for server status
$serverPhpVersion = '';
$serverSoftware = '';
$serverOs = '';

// Variables for server monitoring charts
$serverMetricsLabels = [];
$serverCpuData = [];
$serverMemoryData = [];

// Variables for user info charts
$userRoleChartLabels = [];
$userRoleChartData = [];
$userStatusChartLabels = [];
$userStatusChartData = [];
$topPostersChartLabels = [];
$topPostersChartData = [];

// Variables for achievements chart on user_info tab
$userAchievementsChartLabels = [];
$userAchievementsChartData = [];

// Variables for Top Searched Keywords chart
$topSearchedKeywordsLabels = [];
$topSearchedKeywordsData = [];

// Variables for QOE charts
$qoeChartLabels = []; // Common labels for QOE charts (last 30 days)
$qoeDnsResolutionData = [];
$qoeServerResponseData = [];
// Add more QOE data arrays here as needed
$shouldLoadChartJsForQoe = false; // Flag to load Chart.js

// Variables for logs view
$logEntries = [];

$loggedInUserRole = $_SESSION['admin_role'] ?? 'user';
$loggedInUserId = $_SESSION['admin_username'] ?? null;

// Log view access attempt
log_app_activity("User '$loggedInUserId' (Role: '$loggedInUserRole') attempting to access view: '$requestedView'.", "ACCESS_ATTEMPT");

// Filepath for the daily visitor counter
$visitorCounterFile = __DIR__ . '/../data/daily_visitors.json';

function getDailyVisitorData($filePath) { if (!file_exists($filePath)) return []; $visitorData = json_decode(file_get_contents($filePath), true); return is_array($visitorData) ? $visitorData : []; }

$dailyVisitorData = getDailyVisitorData($visitorCounterFile);

$visitorGraphLabels = [];
$visitorGraphData = [];
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $visitorGraphLabels[] = date('M d', strtotime($date));
    $visitorGraphData[] = $dailyVisitorData[$date] ?? 0;
}
$totalViews = array_sum($dailyVisitorData);
$currentMonth = date('Y-m');
$monthlyVisitors = 0;
foreach ($dailyVisitorData as $date => $count) {
    if (strpos($date, $currentMonth) === 0) $monthlyVisitors += $count;
}

// Load necessary data or perform actions based on the requested view
switch ($requestedView) {
    // ... (other cases remain the same, ensure they have log_app_activity calls if they perform actions) ...
    case 'dashboard_overview':
        $allJobs = loadJobs($jobsFilename);
        $feedbackMessages = loadFeedbackMessages($feedbackFilename);
        $users = loadUsers($usersFilename);
        $loggedInUserObject = findUserByUsername($loggedInUserId, $usersFilename);

        if (in_array($loggedInUserRole, $allRegionalManagerRoles)) {
            $managerRegion = $loggedInUserObject['region'] ?? null;
            if ($managerRegion) {
                foreach ($users as $user) {
                    if (getRoleParts($user['role'] ?? '')['base_role'] === 'User' &&
                        ($user['region'] ?? null) === $managerRegion &&
                        ($user['reports_to_manager_username'] ?? null) === $loggedInUserId) {
                        $filteredUsersCountForManager++;
                    }
                }
            }
        }

        foreach ($users as $user) {
            $role = $user['role'] ?? 'unknown_role';
            $status = $user['status'] ?? 'unknown_status';
            $userCountsByRole[$role] = ($userCountsByRole[$role] ?? 0) + 1;
            $userCountsByStatus[$status] = ($userCountsByStatus[$status] ?? 0) + 1;
        }
        arsort($userCountsByRole);

        $sortedJobs = $allJobs;
        usort($sortedJobs, function ($a, $b) {
            return ($b['posted_on_unix_ts'] ?? 0) - ($a['posted_on_unix_ts'] ?? 0);
        });
        $recentJobs = array_slice($sortedJobs, 0, 5);

        if ($loggedInUserRole === 'super_admin' || in_array($loggedInUserRole, $allRegionalAdminRoles)) {
            $unreadMessages = array_filter($feedbackMessages, function ($msg) {
                return !($msg['read'] ?? true);
            });
            usort($unreadMessages, function ($a, $b) {
                return ($b['timestamp'] ?? 0) - ($a['timestamp'] ?? 0);
            });
            $recentMessages = array_slice($unreadMessages, 0, 5);
        }

        $pendingUsers = array_filter($users, function ($user) {
            return ($user['status'] ?? '') === 'pending_approval';
        });
        $recentPendingUsers = array_slice($pendingUsers, 0, 5);

        $jobsToProcess = $allJobs;
        if (!($loggedInUserRole === 'super_admin' || in_array($loggedInUserRole, $allRegionalAdminRoles))) {
            $jobsToProcess = array_filter($allJobs, function ($job) use ($loggedInUserId) {
                return isset($job['posted_by_user_id']) && $job['posted_by_user_id'] === $loggedInUserId;
            });
        }

        $jobsTodayCount = 0;
        $jobsMonthlyCount = 0;
        $now = time();
        $startOfToday = strtotime('today midnight');
        $startOfMonth = strtotime('first day of this month midnight');

        if (!empty($jobsToProcess)) {
            foreach ($jobsToProcess as $job) {
                 $postedTimestamp = $job['posted_on_unix_ts'] ?? (isset($job['posted_on']) ? strtotime($job['posted_on']) : 0);
                 if ($postedTimestamp >= $startOfToday) $jobsTodayCount++;
                 if ($postedTimestamp >= $startOfMonth) $jobsMonthlyCount++;
            }
        }

         $jobCountsByDay = array_fill(0, 30, 0);
         $graphLabels = [];
         for ($i = 29; $i >= 0; $i--) {
             $graphLabels[] = date('M d', strtotime("-$i days"));
         }
         if (!empty($jobsToProcess)) {
             foreach ($jobsToProcess as $job) {
                  $postedTimestamp = $job['posted_on_unix_ts'] ?? (isset($job['posted_on']) ? strtotime($job['posted_on']) : 0);
                  if ($postedTimestamp > 0) {
                      $daysAgo = floor(($now - $postedTimestamp) / (24 * 60 * 60));
                      if ($daysAgo >= 0 && $daysAgo < 30) {
                          $jobCountsByDay[29 - $daysAgo]++;
                      }
                  }
             }
         }
         $graphData = $jobCountsByDay;
        
        // --- Most Viewed Jobs Data ---
        $jobViewsFilename = __DIR__ . '/../data/job_views.json'; // Path relative to fetch_content.php
        $jobViewsData = []; // Initialize
        error_log("[FETCH_CONTENT_DEBUG] Attempting to load job views data from: " . $jobViewsFilename);

        if (file_exists($jobViewsFilename)) {
            $jobViewsJson = file_get_contents($jobViewsFilename);
            if ($jobViewsJson !== false) {
                $jobViewsData = json_decode($jobViewsJson, true);
                if (!is_array($jobViewsData)) {
                    error_log("[FETCH_CONTENT_ERROR] job_views.json was not a valid array after decoding. Content: " . $jobViewsJson);
                    $jobViewsData = [];
                }
            } else {
                error_log("[FETCH_CONTENT_ERROR] Could not read job_views.json, though it exists.");
            }
        } else {
            error_log("[FETCH_CONTENT_INFO] job_views.json does not exist at " . $jobViewsFilename);
        }
        error_log("[FETCH_CONTENT_DEBUG] Loaded jobViewsData count: " . count($jobViewsData));
        error_log("[FETCH_CONTENT_DEBUG] Loaded allJobs count: " . count($allJobs)); // $allJobs is loaded earlier in the dashboard case

        if (!empty($jobViewsData) && !empty($allJobs)) {
            arsort($jobViewsData); // Sort by view count descending
            $topJobIdsAndCounts = array_slice($jobViewsData, 0, 5, true); // Get top 5 job IDs and their counts
            error_log("[FETCH_CONTENT_DEBUG] Top Job IDs and Counts from job_views.json: " . print_r($topJobIdsAndCounts, true));
            
            $anyMatchFound = false; // Flag to check if at least one job ID matched
            // Fetch job titles for these IDs
            foreach ($topJobIdsAndCounts as $jobId => $viewCount) {
                $foundJobInAllJobs = false;
                foreach ($allJobs as $job) { // $allJobs should be available from the dashboard case
                    if (($job['id'] ?? '') === $jobId) {
                        $mostViewedJobs[] = [
                            'id' => $jobId, // Ensure job ID is included
                            'title' => $job['title'] ?? 'Unknown Job',
                            'company' => $job['company'] ?? '',
                            'views' => $viewCount
                        ];
                        $foundJobInAllJobs = true;
                        $anyMatchFound = true; // A match was found
                        break;
                    }
                }
                if (!$foundJobInAllJobs) {
                    error_log("[FETCH_CONTENT_WARNING] Job ID '" . $jobId . "' (views: " . $viewCount . ") from job_views.json was not found in allJobs list.");
                }
            }
            if (!$anyMatchFound && !empty($topJobIdsAndCounts)) { // If we processed top IDs but found no matches at all
                $sampleJobIdsFromAllJobs = [];
                $limit = 0;
                foreach($allJobs as $job) {
                    if ($limit < 5) {
                        $sampleJobIdsFromAllJobs[] = $job['id'] ?? 'NO_ID_FIELD_IN_JOBS_JSON';
                        $limit++;
                    } else {
                        break;
                    }
                }
                error_log("[FETCH_CONTENT_INFO] No matches found between job_views.json IDs and allJobs IDs. Sample IDs from job_views.json (top 5): " . print_r(array_keys($topJobIdsAndCounts), true) . " Sample IDs from allJobs (first 5): " . print_r($sampleJobIdsFromAllJobs, true));
            }
        } else {
            if (empty($jobViewsData)) error_log("[FETCH_CONTENT_INFO] jobViewsData is empty. Cannot process most viewed jobs.");
            if (empty($allJobs)) error_log("[FETCH_CONTENT_INFO] allJobs is empty. Cannot process most viewed jobs.");
        }
        
        error_log("[FETCH_CONTENT_DEBUG] Final mostViewedJobs count for dashboard: " . count($mostViewedJobs));

        // --- Most Shared Jobs Data (Last 30 Days, Top 5) ---
        $jobSharesFilename = __DIR__ . '/../data/job_shares.json'; // Path for job shares data
        // The view dashboard_overview_view.php expects top 5 for the last 30 days.
        // $allJobs is already loaded in this case.
        $mostSharedJobs = getMostSharedJobs($jobSharesFilename, $jobsFilename, 30, 5);
        error_log("[FETCH_CONTENT_DEBUG] Final mostSharedJobs count for dashboard_overview: " . count($mostSharedJobs));

        // Gather basic server info for the main dashboard display
        $serverPhpVersion = phpversion();
        $serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? 'N/A';
        $serverOs = php_uname('s') . ' ' . php_uname('r'); // e.g., "Windows NT 10.0" or "Linux 5.4.0-72-generic"

        // --- Server Monitoring Chart Data ---
        // Assuming a file like data/server_metrics_history.json exists and is populated
        $serverMetricsFilename = __DIR__ . '/../data/server_metrics_history.json';
        $serverMetricsHistory = [];

        if (file_exists($serverMetricsFilename)) {
            $metricsJson = file_get_contents($serverMetricsFilename);
            if ($metricsJson !== false) {
                $serverMetricsHistory = json_decode($metricsJson, true);
                if (!is_array($serverMetricsHistory)) {
                    $serverMetricsHistory = [];
                }
            }
        }

        // Prepare data for charts (e.g., last 24 hours or last 100 data points)
        // This example takes the last 100 data points for simplicity
        $dataPointsLimit = 100;

        $cpuHistory = $serverMetricsHistory['cpu_usage'] ?? [];
        $memoryHistory = $serverMetricsHistory['memory_usage_percent'] ?? [];
        // Add disk usage history if available in your JSON
        // $diskHistory = $serverMetricsHistory['disk_usage_percent'] ?? [];

        // Take the last N points
        $cpuHistory = array_slice($cpuHistory, -$dataPointsLimit);
        $memoryHistory = array_slice($memoryHistory, -$dataPointsLimit);
        // $diskHistory = array_slice($diskHistory, -$dataPointsLimit);

        // Format for Chart.js
        foreach ($cpuHistory as $point) {
            $serverMetricsLabels[] = date('H:i', $point['timestamp']); // Use time as label
            $serverCpuData[] = $point['value'];
        }
        foreach ($memoryHistory as $point) {
             // Ensure labels match the CPU data points if they have different timestamps
             // A more robust approach would align data points by timestamp
             $serverMemoryData[] = $point['value'];
        }
        break; // Correct position for the break statement for case 'dashboard'

    case 'dashboard_service_one': // Basic Server Info
        // Gather basic server info
        $serverPhpVersion = phpversion();
        $serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? 'N/A';
        log_app_activity("User '$loggedInUserId' accessed 'dashboard_service_one' view.", "ACCESS_GRANTED");
        $applicationVersion = '1.0.0'; // Define your application version here
        $serverOs = php_uname('s') . ' ' . php_uname('r');
        break;

    case 'dashboard_user_info': // User Statistics
        $users = loadUsers($usersFilename);
        foreach ($users as $user) {
            $role = $user['role'] ?? 'unknown_role';
            $status = $user['status'] ?? 'unknown_status';
            $userCountsByRole[$role] = ($userCountsByRole[$role] ?? 0) + 1;
            $userCountsByStatus[$status] = ($userCountsByStatus[$status] ?? 0) + 1;
            }
        arsort($userCountsByRole);
        log_app_activity("User '$loggedInUserId' accessed 'dashboard_user_info' view.", "ACCESS_GRANTED");

        // --- User Performance Tracking Data (Moved here) ---
        $allJobs = loadJobs($jobsFilename); // Load jobs if not already loaded for this view
        error_log("[FETCH_CONTENT_DEBUG] User Performance (User Info Tab): Users count for init: " . (is_array($users) ? count($users) : 'not an array'));
        if (!is_array($users)) {
            $users = [];
            error_log("[FETCH_CONTENT_ERROR] User Performance (User Info Tab): \$users was not an array.");
        }
        foreach ($users as $user) {
            if (!isset($user['username'])) {
                error_log("[FETCH_CONTENT_WARNING] User Performance (User Info Tab): User found without 'username': " . print_r($user, true));
                continue;
            }
                        $username = $user['username'];
            $lcUsername = strtolower($username); // Use lowercase username as key
            $displayName = $user['display_name'] ?? $username;

            $userPerformanceOverall[$lcUsername] = ['name' => $displayName, 'count' => 0];
            $userPerformanceLast30Days[$lcUsername] = ['name' => $displayName, 'count' => 0];
            $userPerformanceToday[$lcUsername] = ['name' => $displayName, 'count' => 0];
    }

        $thirtyDaysAgoTimestamp = strtotime('-30 days midnight');
        $todayTimestamp = strtotime('today midnight');
        error_log("[FETCH_CONTENT_DEBUG] User Performance (User Info Tab): AllJobs count: " . (is_array($allJobs) ? count($allJobs) : 'not an array'));
        
        foreach ($allJobs as $job) {
        $postedByRaw = $job['posted_by_user_id'] ?? null; // Original value from job data
        if ($postedByRaw) {
            $lcPostedBy = strtolower($postedByRaw); // Convert to lowercase for lookup

            if (isset($userPerformanceOverall[$lcPostedBy])) {
                $userPerformanceOverall[$lcPostedBy]['count']++;
                $jobTimestamp = $job['posted_on_unix_ts'] ?? 0;
                if ($jobTimestamp >= $thirtyDaysAgoTimestamp) {
                    if (isset($userPerformanceLast30Days[$lcPostedBy])) { // Ensure key exists
                         $userPerformanceLast30Days[$lcPostedBy]['count']++;
                    }
                }
                if ($jobTimestamp >= $todayTimestamp) {
                    if (isset($userPerformanceToday[$lcPostedBy])) { // Ensure key exists
                        $userPerformanceToday[$lcPostedBy]['count']++;
                    }
                }
            } elseif ($postedByRaw) { // Log if $postedByRaw was set but not found after lowercasing
                 error_log("[FETCH_CONTENT_WARNING] User Performance (User Info Tab): Job posted_by_user_id (raw: '$postedByRaw', lc: '$lcPostedBy') not found in user lookup. Job ID: " . ($job['id'] ?? 'N/A'));
        }
        
        }
       $performanceLeaderboard = $userPerformanceLast30Days;
        uasort($performanceLeaderboard, function ($a, $b) { return $b['count'] - $a['count']; });
        $performanceLeaderboard = array_slice($performanceLeaderboard, 0, 5, true); // Top 5

        // Prepare data for charts in user_info_view
        if (!empty($userCountsByRole)) {
            $userRoleChartLabels = array_keys($userCountsByRole);
            $userRoleChartData = array_values($userCountsByRole);
        }
        if (!empty($userCountsByStatus)) {
            $userStatusChartLabels = array_keys($userCountsByStatus);
            $userStatusChartData = array_values($userCountsByStatus);
        }
        if (!empty($performanceLeaderboard)) {
            foreach($performanceLeaderboard as $data) {
                if ($data['count'] > 0) { // Only include users with posts in the chart
                    $topPostersChartLabels[] = $data['name'];
                    $topPostersChartData[] = $data['count'];
                }
            }
        }

        // --- Achievements Chart Data for User Info Tab ---
        // This logic is similar to the 'achievements' case
        // $allJobs and $users are already loaded in this 'dashboard_user_info' case.
        $userJobCountsByDate = [];
        $rupeesPerJob = 3; // Or your defined points per job

        // Prepare labels for the last 30 days for the chart
        for ($i = 29; $i >= 0; $i--) {
            $userAchievementsChartLabels[] = date('M d', strtotime("-$i days"));
        }

        // Aggregate job counts per user per day
        foreach ($allJobs as $job) {
            $postedByUserId = $job['posted_by_user_id'] ?? null;
            if (!$postedByUserId) continue;

            $postedTimestamp = $job['posted_on_unix_ts'] ?? (isset($job['posted_on']) ? strtotime($job['posted_on']) : 0);
            if ($postedTimestamp === 0) continue;

            $jobDate = date('Y-m-d', $postedTimestamp);

            if (!isset($userJobCountsByDate[$postedByUserId])) $userJobCountsByDate[$postedByUserId] = [];
            if (!isset($userJobCountsByDate[$postedByUserId][$jobDate])) $userJobCountsByDate[$postedByUserId][$jobDate] = 0;
            $userJobCountsByDate[$postedByUserId][$jobDate]++;
        }

        // Prepare datasets for Chart.js
        $userDisplayNames = array_column($users, 'display_name', 'username');
        $colorIndex = 0;
        $baseColors = ['rgba(255, 99, 132, 0.7)', 'rgba(54, 162, 235, 0.7)', 'rgba(255, 206, 86, 0.7)', 'rgba(75, 192, 192, 0.7)', 'rgba(153, 102, 255, 0.7)', 'rgba(255, 159, 64, 0.7)'];

        foreach ($userJobCountsByDate as $userId => $dates) {
            $userDisplayName = $userDisplayNames[$userId] ?? $userId;
            $userDataPoints = [];
            foreach ($userAchievementsChartLabels as $labelDate) {
                $checkDate = date('Y-m-d', strtotime($labelDate . " " . date("Y")));
                $jobsCount = $dates[$checkDate] ?? 0;
                $userDataPoints[] = $jobsCount * $rupeesPerJob;
            }
            $userAchievementsChartData[] = ['label' => $userDisplayName, 'data' => $userDataPoints, 'borderColor' => $baseColors[$colorIndex % count($baseColors)], 'backgroundColor' => $baseColors[$colorIndex % count($baseColors)], 'fill' => false, 'tension' => 0.1];
            $colorIndex++;
        }
    }
    
        break;

    case 'dashboard_service_two': // Server Monitoring Charts
        $serverMetricsFilename = __DIR__ . '/../data/server_metrics_history.json';
        $serverMetricsHistory = [];
        if (file_exists($serverMetricsFilename)) {
            $metricsJson = file_get_contents($serverMetricsFilename);
            if ($metricsJson !== false) {
                $serverMetricsHistory = json_decode($metricsJson, true);
                if (!is_array($serverMetricsHistory)) $serverMetricsHistory = [];
            }
        }
        $dataPointsLimit = 100;
        $cpuHistory = array_slice($serverMetricsHistory['cpu_usage'] ?? [], -$dataPointsLimit);
        $memoryHistory = array_slice($serverMetricsHistory['memory_usage_percent'] ?? [], -$dataPointsLimit);
        foreach ($cpuHistory as $point) {
            $serverMetricsLabels[] = date('H:i', $point['timestamp']);
            $serverCpuData[] = $point['value'];
        }
        foreach ($memoryHistory as $point) $serverMemoryData[] = $point['value'];
        log_app_activity("User '$loggedInUserId' accessed 'dashboard_service_two' view.", "ACCESS_GRANTED");
        break;

    case 'dashboard_job_stats':
        $allJobs = loadJobs($jobsFilename);
        log_app_activity("User '$loggedInUserId' accessed 'dashboard_job_stats' view.", "ACCESS_GRANTED");

        // Calculate Jobs Today and Jobs This Month (Global for this view)
         $jobCountsByDay = array_fill(0, 30, 0);
         for ($i = 29; $i >= 0; $i--) {
             $graphLabels[] = date('M d', strtotime("-$i days"));
         }
         if (!empty($allJobs)) {
             foreach ($allJobs as $job) {
                  $postedTimestamp = $job['posted_on_unix_ts'] ?? (isset($job['posted_on']) ? strtotime($job['posted_on']) : 0);
                  if ($postedTimestamp > 0) {
                      $now = time(); // Define $now here as it's used in the loop
                      $daysAgo = floor(($now - $postedTimestamp) / (24 * 60 * 60));
                      if ($daysAgo >= 0 && $daysAgo < 30) {
                          $jobCountsByDay[29 - $daysAgo]++;
                      }
                  }
             }
         }
         $graphData = $jobCountsByDay;

        // Note: Most Viewed and Most Shared Jobs have been moved to 'dashboard_visitors_info'
        break;
    case 'dashboard_visitors_info':
        // This data is already prepared by the general logic at the top of fetch_content.php
        // $dailyVisitorData, $visitorGraphLabels, $visitorGraphData, $totalViews, $monthlyVisitors
        // are available.
        log_app_activity("User '$loggedInUserId' accessed 'dashboard_visitors_info' view.", "ACCESS_GRANTED");

        // --- Top Searched Keywords Data ---
        $searchKeywordsLogFile = __DIR__ . '/../data/search_keywords_log.json';
        if (file_exists($searchKeywordsLogFile)) {
            $keywordsJson = file_get_contents($searchKeywordsLogFile);
            $allSearchedKeywords = json_decode($keywordsJson, true);

            if (is_array($allSearchedKeywords) && !empty($allSearchedKeywords)) {
                // Convert all keywords to lowercase for consistent counting
                $allSearchedKeywords = array_map('strtolower', $allSearchedKeywords);
                $keywordCounts = array_count_values($allSearchedKeywords);
                arsort($keywordCounts); // Sort by count descending

                $topKeywords = array_slice($keywordCounts, 0, 25, true); // Get top 25

                foreach ($topKeywords as $keyword => $count) {
                    $topSearchedKeywordsLabels[] = $keyword;
                    $topSearchedKeywordsData[] = $count;
                }
            } else {
                log_app_activity("Search keywords log file ('$searchKeywordsLogFile') is empty or not valid JSON.", "DATA_WARNING");
            }
        } else {
            log_app_activity("Search keywords log file ('$searchKeywordsLogFile') not found.", "DATA_WARNING");
        }

        // --- Most Viewed & Shared Jobs Data for Visitors Info Tab (Top 10, Last 30 Days) ---
        $allJobs = loadJobs($jobsFilename); // Load all jobs
        $jobViewsFilename = __DIR__ . '/../data/job_views.json';
        $jobSharesFilename = __DIR__ . '/../data/job_shares.json';

        $mostViewedJobs = getMostViewedJobs($jobViewsFilename, $jobsFilename, 30, 10);
        $mostSharedJobs = getMostSharedJobs($jobSharesFilename, $jobsFilename, 30, 10);
        break;

    case 'dashboard_qoe':
        // Placeholder for Quality of Experience data
        // You would load or calculate QOE metrics here.
        // For example:
        // $averagePageLoadTime = getAveragePageLoadTime(); // Fictional function
        // $errorRate = getErrorRate(); // Fictional function
        // $userSatisfactionScores = loadUserSatisfactionScores(); // Fictional function
        log_app_activity("User '$loggedInUserId' accessed 'dashboard_qoe' view.", "ACCESS_GRANTED");
        // For now, we'll just ensure the view file can be included.
        // You can pass placeholder variables if needed by the view.
        $qoeMetrics = [
            'avg_load_time' => 'N/A (Placeholder)',
            'error_rate_percent' => 'N/A (Placeholder)',
            'user_feedback_summary' => 'No QOE data available yet. (Placeholder)',
        ];

        // --- QOE Chart Data Preparation (DNS Resolution, Server Response) ---
        $qoeMetricsFile = __DIR__ . '/../data/qoe_metrics_history.json';
        $qoeMetricsHistory = [];

        if (file_exists($qoeMetricsFile)) {
            $qoeJson = file_get_contents($qoeMetricsFile);
            if ($qoeJson !== false) {
                $qoeMetricsHistory = json_decode($qoeJson, true);
                if (!is_array($qoeMetricsHistory)) $qoeMetricsHistory = [];
            }
        } else {
            log_app_activity("QOE metrics history file not found: $qoeMetricsFile", "DATA_WARNING");
        }

        // Prepare labels for the last 30 days
        for ($i = 29; $i >= 0; $i--) {
            $qoeChartLabels[] = date('M d', strtotime("-$i days"));
        }

        // Helper function to process metrics for daily averages
        // (Could be moved to a helper file if used elsewhere)
        if (!function_exists('calculate_daily_averages')) {
            function calculate_daily_averages($metricHistory, $days = 30) {
                $dailyAverages = array_fill(0, $days, null); // Initialize with null for days with no data
                $dailyData = [];
                $thirtyDaysAgo = strtotime("-$days days midnight");

                foreach ($metricHistory as $entry) {
                    $timestamp = $entry['timestamp'] ?? 0;
                    $value = $entry['value'] ?? null;

                    if ($timestamp >= $thirtyDaysAgo && $value !== null) {
                        $date = date('Y-m-d', $timestamp);
                        if (!isset($dailyData[$date])) {
                            $dailyData[$date] = ['sum' => 0, 'count' => 0];
                        }
                        $dailyData[$date]['sum'] += $value;
                        $dailyData[$date]['count']++;
                    }
                }

                for ($i = 0; $i < $days; $i++) {
                    $dateKey = date('Y-m-d', strtotime("-" . ($days - 1 - $i) . " days"));
                    if (isset($dailyData[$dateKey]) && $dailyData[$dateKey]['count'] > 0) {
                        $dailyAverages[$i] = round($dailyData[$dateKey]['sum'] / $dailyData[$dateKey]['count'], 2);
                    }
                }
                return $dailyAverages;
            }
        }

        $dnsHistory = $qoeMetricsHistory['dns_resolution_time_ms'] ?? [];
        $serverResponseHistory = $qoeMetricsHistory['server_response_time_ms'] ?? [];

        $qoeDnsResolutionData = calculate_daily_averages($dnsHistory, 30);
        $qoeServerResponseData = calculate_daily_averages($serverResponseHistory, 30);

        // Set flag to load Chart.js if data is available for any chart
        if (!empty($qoeDnsResolutionData) || !empty($qoeServerResponseData)) {
            $shouldLoadChartJsForQoe = true;
        }

        // Most Viewed Jobs table has been removed from the QOE dashboard.
        // $mostViewedJobs will remain empty or not be used by the dashboard_qoe_view.php
        break;

    case 'manage_jobs':
    case 'edit_job':
        $allJobs = loadJobs($jobsFilename);
        if ($requestedView === 'edit_job' && isset($_GET['id'])) {
            $jobId = $_GET['id'];
            $jobToEdit = null;
            if (!empty($allJobs)) {
                foreach ($allJobs as $job) {
                    if (isset($job['id']) && (string)$job['id'] === (string)$jobId) {
                        $jobToEdit = $job;
                        break;
                    }
                }
            }
            if ($jobToEdit === null) {
                 echo '<p class="status-message error">Error: Job not found for editing.</p>';
                 log_app_activity("User '$loggedInUserId' attempted to edit non-existent job ID '$jobId'.", "CONTENT_ERROR");
                 error_log("Admin Edit Job Error: Job ID not found for editing via AJAX: " . ($jobId ?? 'N/A'));
                 exit;
            }
        }
        break;

    case 'edit_user':
        $usernameToEdit = $_GET['username'] ?? null;
        if ($usernameToEdit) {
            $userToEdit = findUserByUsername($usernameToEdit, $usersFilename);
            if (!$userToEdit) {
                log_app_activity("User '$loggedInUserId' attempted to edit non-existent user '$usernameToEdit'.", "CONTENT_ERROR");
                echo '<p class="status-message error">Error: User not found for editing.</p>';
                error_log("Admin Fetch Content Error: User '" . htmlspecialchars($usernameToEdit) . "' not found for editing via AJAX.");
                exit;
            }

            $targetUserRole = $userToEdit['role'] ?? 'user';
            $loggedInUserObject = findUserByUsername($loggedInUserId, $usersFilename);
            $loggedInUserRegion = $loggedInUserObject['region'] ?? null;
            $targetUserRegion = $userToEdit['region'] ?? null;
            $targetUserBaseRole = getRoleParts($targetUserRole)['base_role'];

            $canAccessView = false;
            if ($loggedInUserRole === 'super_admin') {
                $canAccessView = true;
            } elseif (in_array($loggedInUserRole, $allRegionalAdminRoles)) {
                if (in_array($userToEdit['role'], $allRegionalAdminRoles)) {
                    $canAccessView = true;
                } elseif ($targetUserRegion === $loggedInUserRegion && ($targetUserBaseRole === 'Manager' || $targetUserBaseRole === 'User') &&
                           (($userToEdit['reports_to_admin_username'] ?? null) === $loggedInUserId ||
                            ($userToEdit['reports_to_manager_username'] && (findUserByUsername($userToEdit['reports_to_manager_username'], $usersFilename)['reports_to_admin_username'] ?? null) === $loggedInUserId))) {
                    $canAccessView = true;
                }
            } elseif (in_array($loggedInUserRole, $allRegionalManagerRoles)) {
                if ($targetUserBaseRole === 'User' &&
                    ($userToEdit['reports_to_manager_username'] ?? null) === $loggedInUserId &&
                    $targetUserRegion === $loggedInUserRegion) {
                    $canAccessView = true;
                }
            }

            if (!$canAccessView) {
                echo '<p class="status-message error">Access Denied: You do not have permission to view the edit page for this user.</p>';
                log_app_activity("User '$loggedInUserId' (Role: '$loggedInUserRole') attempt to edit user '$usernameToEdit' (Role: '$targetUserRole') - DENIED.", "SECURITY_WARNING");
                error_log("Admin Fetch Content Access Denied: User '" . htmlspecialchars($loggedInUserId) . "' (role: " . htmlspecialchars($loggedInUserRole) . ") attempted to load edit page for user '" . htmlspecialchars($userToEdit['username'] ?? 'N/A') . "' (role: " . htmlspecialchars($targetUserRole) . ") via AJAX.");
                exit;
            }
        } else {
            log_app_activity("User '$loggedInUserId' attempted to edit user but no username was specified.", "CONTENT_ERROR");
            echo '<p class="status-message error">Error: No username specified for editing.</p>';
            exit;
        }
        break;

    case 'profile':
        $userToEdit = findUserByUsername($loggedInUserId, $usersFilename);
        log_app_activity("User '$loggedInUserId' accessed 'profile' view.", "ACCESS_GRANTED");
        break;

    case 'messages':
        if (!($loggedInUserRole === 'super_admin' || in_array($loggedInUserRole, $allRegionalAdminRoles))) {
            echo '<p class="status-message error">Access Denied: You do not have permission to view messages.</p>';
            log_app_activity("User '$loggedInUserId' (Role: '$loggedInUserRole') attempted to access 'messages' view - DENIED.", "SECURITY_WARNING");
            error_log("Admin Fetch Content Access Denied: User '" . htmlspecialchars($loggedInUserId) . "' (role: " . htmlspecialchars($loggedInUserRole) . ") attempted to access 'messages' view via AJAX.");
            exit;
        }
        $feedbackMessages = loadFeedbackMessages($feedbackFilename);
        if (!empty($feedbackMessages)) {
            usort($feedbackMessages, function($a, $b) {
                return ($b['timestamp'] ?? 0) - ($a['timestamp'] ?? 0);
            });
        }
        log_app_activity("User '$loggedInUserId' accessed 'messages' view.", "ACCESS_GRANTED");
        break;

    case 'generate_message':
        if (!($loggedInUserRole === 'super_admin' || in_array($loggedInUserRole, $allRegionalAdminRoles))) {
            echo '<p class="status-message error">Access Denied: You do not have permission to generate posts.</p>';
            log_app_activity("User '$loggedInUserId' (Role: '$loggedInUserRole') attempted to access 'generate_message' view - DENIED.", "SECURITY_WARNING");
            error_log("Admin Fetch Content Access Denied: User '" . htmlspecialchars($loggedInUserId) . "' (role: " . htmlspecialchars($loggedInUserRole) . ") attempted to access 'generate_message' view via AJAX.");
            exit;
        }
        
        $whatsappMessage = null;
        ob_start();
        if (file_exists(__DIR__ . '/generate_whatsapp_message.php')) {
            require __DIR__ . '/generate_whatsapp_message.php';
        }
        $whatsappMessageContent = ob_get_clean();
        if (!empty(trim($whatsappMessageContent)) && strpos(strtolower($whatsappMessageContent), 'error') === false && strpos(strtolower($whatsappMessageContent), 'could not generate') === false) {
            $whatsappMessage = $whatsappMessageContent;
        }

        $telegramMessage = null;
        ob_start();
        if (file_exists(__DIR__ . '/generate_telegram_message.php')) {
            require __DIR__ . '/generate_telegram_message.php';
        }
        $telegramMessageContent = ob_get_clean();
        if (!empty(trim($telegramMessageContent)) && strpos(strtolower($telegramMessageContent), 'error') === false && strpos(strtolower($telegramMessageContent), 'could not generate') === false) {
            $telegramMessage = $telegramMessageContent;
        }
        log_app_activity("User '$loggedInUserId' accessed 'generate_message' view. WhatsApp generated: " . (!empty($whatsappMessage) ? 'Yes' : 'No') . ". Telegram generated: " . (!empty($telegramMessage) ? 'Yes' : 'No') . ".", "ACCESS_GRANTED");
        break;

    case 'manage_users':
        if (!($loggedInUserRole === 'super_admin' || in_array($loggedInUserRole, $allRegionalAdminRoles) || in_array($loggedInUserRole, $allRegionalManagerRoles))) {
            echo '<p class="status-message error">Access Denied: You do not have permission to manage users.</p>';
            log_app_activity("User '$loggedInUserId' (Role: '$loggedInUserRole') attempted to access 'manage_users' view - DENIED.", "SECURITY_WARNING");
            error_log("Admin Fetch Content Access Denied: User '" . htmlspecialchars($loggedInUserId) . "' (role: " . htmlspecialchars($loggedInUserRole) . ") attempted to access 'manage_users' view via AJAX.");
            exit;
        }
         $users = loadUsers($usersFilename);
         log_app_activity("User '$loggedInUserId' accessed 'manage_users' view.", "ACCESS_GRANTED");
         break;

    case 'post_job':
        break;

    case 'achievements':
        $allJobs = loadJobs($jobsFilename);
        $allUsers = loadUsers($usersFilename);
        $userJobCountsByDate = [];
        $rupeesPerJob = 3;

        for ($i = 29; $i >= 0; $i--) {
            $achievementsChartLabels[] = date('M d', strtotime("-$i days"));
        }

        foreach ($allJobs as $job) {
            $postedByUserId = $job['posted_by_user_id'] ?? null;
            if (!$postedByUserId) continue;

            $postedTimestamp = $job['posted_on_unix_ts'] ?? (isset($job['posted_on']) ? strtotime($job['posted_on']) : 0);
            if ($postedTimestamp === 0) continue;

            $jobDate = date('Y-m-d', $postedTimestamp);

            if (!isset($userJobCountsByDate[$postedByUserId])) {
                $userJobCountsByDate[$postedByUserId] = [];
            }
            if (!isset($userJobCountsByDate[$postedByUserId][$jobDate])) {
                $userJobCountsByDate[$postedByUserId][$jobDate] = 0;
            }
            $userJobCountsByDate[$postedByUserId][$jobDate]++;
        }

        $userDisplayNames = array_column($allUsers, 'display_name', 'username');
        $colorIndex = 0;
        $baseColors = [
            'rgba(255, 99, 132, 0.7)', 'rgba(54, 162, 235, 0.7)', 'rgba(255, 206, 86, 0.7)',
            'rgba(75, 192, 192, 0.7)', 'rgba(153, 102, 255, 0.7)', 'rgba(255, 159, 64, 0.7)'
        ];

        foreach ($userJobCountsByDate as $userId => $dates) {
            $userDisplayName = $userDisplayNames[$userId] ?? $userId;
            $userDataPoints = [];
            foreach ($achievementsChartLabels as $labelDate) {
                $checkDate = date('Y-m-d', strtotime($labelDate . " " . date("Y"))); // Add current year for correct strtotime parsing
                $jobsCount = $dates[$checkDate] ?? 0;
                $userDataPoints[] = $jobsCount * $rupeesPerJob;
            }
            $achievementsChartData[] = [
                'label' => $userDisplayName,
                'data' => $userDataPoints,
                'borderColor' => $baseColors[$colorIndex % count($baseColors)],
                'backgroundColor' => $baseColors[$colorIndex % count($baseColors)],
                'fill' => false,
                'tension' => 0.1
            ];
            $colorIndex++;
        }
        break;

    case 'server_management':
        if (!($loggedInUserRole === 'super_admin' || in_array($loggedInUserRole, $allRegionalAdminRoles))) {
            echo '<p class="status-message error">Access Denied: You do not have permission to view server management.</p>';
            log_app_activity("User '$loggedInUserId' (Role: '$loggedInUserRole') attempted to access 'server_management' view - DENIED.", "SECURITY_WARNING");
            error_log("Admin Fetch Content Access Denied: User '" . htmlspecialchars($loggedInUserId) . "' (role: " . htmlspecialchars($loggedInUserRole) . ") attempted to access 'server_management' view via AJAX.");
            exit;
        }
        // Data for the dedicated server management page
        $serverPhpVersion = phpversion();
        log_app_activity("User '$loggedInUserId' accessed 'server_management' view.", "ACCESS_GRANTED");
        $serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? 'N/A';
        $serverOs = php_uname(); // More detailed OS info
        // You can add more detailed checks here like disk space, load averages (if applicable and safe)
        break;    
    case 'logs':
        if ($loggedInUserRole !== 'super_admin') {
            echo '<p class="status-message error">Access Denied: You do not have permission to view server logs.</p>';
            log_app_activity("User '$loggedInUserId' (Role: '$loggedInUserRole') attempted to access 'logs' view - DENIED.", "SECURITY_WARNING");
            error_log("Admin Fetch Content Access Denied: User '" . htmlspecialchars($loggedInUserId) . "' (role: " . htmlspecialchars($loggedInUserRole) . ") attempted to access 'logs' view.");
            exit;
        }
        // Use the application-specific log file defined in log_helpers.php (via config.php)
        $logFilePath = APP_LOG_FILE_PATH; 
        $linesToFetch = 100; // Number of recent log lines to display

        if (file_exists($logFilePath) && is_readable($logFilePath)) {
            // Efficiently read last N lines (can be complex for very large files)
            // A simpler approach for moderately sized files:
            $fileContents = file($logFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($fileContents === false) {
                $logEntries[] = "Error: Could not read log file content.";
                // Log this error to the PHP system error log, not the app log itself
                error_log("Admin Logs View System Error: Could not read app_activity.log content from: " . $logFilePath); 
            } else {
                $logEntries = array_slice($fileContents, -$linesToFetch);
                // Filter out log entries related to accessing or attempting to access the 'logs' view itself
                $logEntries = array_filter($logEntries, function($entry) {
                    return strpos($entry, "attempting to access view: 'logs'") === false &&
                           strpos($entry, "accessed 'logs' view.") === false;
                });
                if (empty($logEntries)) {
                    // If filtering results in an empty array, provide a message.
                    $logEntries[] = "Log file is empty or contains no recent entries.";
                }
            }
        } else {
            $logEntries[] = "Application log file not found or not readable at: " . htmlspecialchars($logFilePath);
            // Log this error to the PHP system error log
            error_log("Admin Logs View System Error: Application log file (app_activity.log) not found or not readable at: " . $logFilePath);
            // Attempt to create it if it doesn't exist, so it's there for next time
            log_app_activity("Log file initialized.", "SYSTEM"); 
        }
          // Check if this is an AJAX request specifically for updating logs
        $isAjaxLogUpdate = isset($_GET['ajax']) && $_GET['ajax'] === '1';

        if ($isAjaxLogUpdate) {
            // For AJAX updates, clean any previous buffer from fetch_content.php itself,
            // then output only the log entries.
            ob_clean(); 
            if (!empty($logEntries) && !(count($logEntries) === 1 && strpos($logEntries[0], 'No log entries') !== false && strpos($logEntries[0], 'error loading logs') !== false )) {
                foreach ($logEntries as $entry) {
                    echo '<div class="log-entry">' . htmlspecialchars($entry) . '</div>';
                }
            } else {
                // Output the specific message if logEntries contains an error or "no entries" message
                echo '<div class="log-entry no-data-message">' . htmlspecialchars($logEntries[0] ?? 'No log entries found or log file is empty.') . '</div>';
            }
            exit; // Important: Stop further processing for AJAX log updates
        }
        // For non-AJAX (initial load), $logEntries will be passed to logs_view.php
        // and the full logs_view.php will be included by the main logic at the end of this script.
       
        log_app_activity("User '$loggedInUserId' accessed 'logs' view.", "ACCESS_GRANTED");
        break;

    default:
        echo '<p class="status-message error">Error: Invalid view specified.</p>';
        $isAjaxRequest = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');
        error_log("Admin Error: Invalid view requested in fetch_content.php. View: '" . htmlspecialchars($requestedView) . "'. Is AJAX: " . ($isAjaxRequest ? 'Yes' : 'No'));
        log_app_activity("User '$loggedInUserId' attempted to access invalid view: '$requestedView'.", "INVALID_VIEW_ERROR");
        exit;
}

$viewFileSuffix = '_view.php';
$viewFilePath = __DIR__ . '/views/' . $requestedView . $viewFileSuffix;

$allowedFetchViews = ['dashboard_overview', 'dashboard_service_one', 'dashboard_user_info', 'dashboard_job_stats', 'dashboard_service_two', 'dashboard_visitors_info', 'dashboard_qoe', 'manage_jobs', 'edit_job', 'edit_user', 'profile', 'messages', 'generate_message', 'manage_users', 'post_job', 'achievements', 'server_management', 'logs'];

if (!in_array($requestedView, $allowedFetchViews)) {
     echo '<p class="status-message error">Error: Access denied or view not available.</p>';
     error_log("Admin Error: fetch_content.php - View '" . htmlspecialchars($requestedView) . "' not in \$allowedFetchViews. Path attempted: " . htmlspecialchars($viewFilePath));
} elseif (file_exists($viewFilePath)) {
    require $viewFilePath;
} else {
    echo '<p class="status-message error">Error: View file not found.</p>';
    error_log("Admin Error: fetch_content.php - Requested view file NOT FOUND: " . htmlspecialchars($viewFilePath) . " (Actual \$requestedView value: '" . htmlspecialchars($requestedView) . "')");
}

$viewContent = ob_get_clean();
echo $viewContent;
exit;

?>
