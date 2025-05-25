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
$userPerformanceList = []; // New: For consolidated horizontal list
$performanceLeaderboard = [];
$mostViewedJobs = []; // This will no longer be populated from job_views.json
$mostSharedJobs = []; // This will no longer be populated from job_shares.json

// Variables for lifetime job stats from jobs.json
$totalLifetimeViews = 0;
$totalLifetimeShares = 0;
$allTimeTopViewedJobs = [];
$allTimeTopSharedJobs = [];

// Variables for monthly views/shares of jobs posted this month
$totalLifetimeViewsOfJobsPostedThisMonth = 0;
$totalLifetimeSharesOfJobsPostedThisMonth = 0;

// Variables for total page requests
$totalPageRequestsAllTime = 0;
$monthlyTotalPageRequests = 0;

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
// Variables for NEW Overall User Job Posting Performance Chart
$userOverallPerformanceChartLabels = [];
$userOverallPerformanceChartData = [];
$userOverallPerformanceChartColors = [];

// Variables for achievements chart on user_info tab
// $userAchievementsChartLabels = []; // This chart was removed from user_info_view
// $userAchievementsChartData = [];   // This chart was removed from user_info_view

// Variables for NEW Quarterly User Earnings Chart on user_info tab
$quarterlyUserEarningsLabels = [];
$quarterlyUserEarningsData = [];

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
$reportedJobsData = []; // For reported_jobs view

$loggedInUserRole = $_SESSION['admin_role'] ?? 'user';
$loggedInUserId = $_SESSION['admin_username'] ?? null;

// Log view access attempt
log_app_activity("User '$loggedInUserId' (Role: '$loggedInUserRole') attempting to access view: '$requestedView'.", "ACCESS_ATTEMPT");

// Filepath for the daily visitor counter
$visitorCounterFile = __DIR__ . '/../data/daily_visitors.json';

function getDailyVisitorData($filePath) {
    if (!file_exists($filePath)) return [];
    $rawJson = file_get_contents($filePath);
    $visitorData = json_decode($rawJson, true);
    if (!is_array($visitorData)) return [];

    $processedData = [];
    foreach ($visitorData as $date => $entry) {
        if (is_array($entry) && isset($entry['unique_visits']) && isset($entry['total_requests'])) {
            // New format, use as is
            $processedData[$date] = $entry;
        } elseif (is_numeric($entry)) {
            // Old format (assume $entry is unique_visits and also total_requests for that day)
            $processedData[$date] = ['unique_visits' => (int)$entry, 'total_requests' => (int)$entry];
            error_log("[FETCH_CONTENT_INFO] Processed old format data in daily_visitors.json for date: $date. Assumed unique_visits and total_requests = $entry");
        } else {
            // Malformed or unexpected format for this date
            error_log("[FETCH_CONTENT_WARNING] Malformed or unexpected data in daily_visitors.json for date: $date. Entry skipped. Data: " . print_r($entry, true));
            // Optionally, default to 0 for malformed entries to prevent breaking sums:
            // $processedData[$date] = ['unique_visits' => 0, 'total_requests' => 0];
        }
    }
    return $processedData;
}
$dailyVisitorData = getDailyVisitorData($visitorCounterFile);

$visitorGraphLabels = [];
$visitorGraphData = [];
$totalRequestsGraphData = []; // New: For daily total requests chart
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $visitorGraphLabels[] = date('M d', strtotime($date));
    $visitorGraphData[] = $dailyVisitorData[$date]['unique_visits'] ?? 0;
    $totalRequestsGraphData[] = $dailyVisitorData[$date]['total_requests'] ?? 0; // Populate new data array
}
$totalViews = array_sum(array_column($dailyVisitorData, 'unique_visits')); // Total unique visits
$totalPageRequestsAllTime = array_sum(array_column($dailyVisitorData, 'total_requests')); // Total page requests

$currentMonth = date('Y-m');
$monthlyVisitors = 0;
$monthlyTotalPageRequests = 0;
foreach ($dailyVisitorData as $date => $dateCounts) { // Corrected loop variable from $count to $dateCounts
    if (strpos($date, $currentMonth) === 0) {
        $monthlyVisitors += ($dateCounts['unique_visits'] ?? 0);
        $monthlyTotalPageRequests += ($dateCounts['total_requests'] ?? 0);
    }
}

// Load necessary data or perform actions based on the requested view
switch ($requestedView) {
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

        // Calculate lifetime views/shares for jobs posted this month
        // This should iterate over $allJobs, not $jobsToProcess, to get a global view for admins
        if (!empty($allJobs)) { 
            foreach ($allJobs as $job) {
                $postedTimestamp = $job['posted_on_unix_ts'] ?? (isset($job['posted_on']) ? strtotime($job['posted_on']) : 0);
                if ($postedTimestamp >= $startOfMonth) {
                    $totalLifetimeViewsOfJobsPostedThisMonth += (int)($job['total_views_count'] ?? 0);
                    $totalLifetimeSharesOfJobsPostedThisMonth += (int)($job['total_shares_count'] ?? 0);
                }
            }
        }

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
        
        // --- Lifetime Job Stats from jobs.json ---
        if (!empty($allJobs)) {
            foreach ($allJobs as $job) {
                $totalLifetimeViews += (int)($job['total_views_count'] ?? 0);
                $totalLifetimeShares += (int)($job['total_shares_count'] ?? 0);
            }

            // Prepare All-Time Top Viewed Jobs
            $tempAllTimeViewed = $allJobs;
            usort($tempAllTimeViewed, function ($a, $b) {
                return ($b['total_views_count'] ?? 0) - ($a['total_views_count'] ?? 0);
            });
            $allTimeTopViewedJobs = array_slice($tempAllTimeViewed, 0, 5);

            // Prepare All-Time Top Shared Jobs
            $tempAllTimeShared = $allJobs;
            usort($tempAllTimeShared, function ($a, $b) {
                return ($b['total_shares_count'] ?? 0) - ($a['total_shares_count'] ?? 0);
            });
            $allTimeTopSharedJobs = array_slice($tempAllTimeShared, 0, 5);
        }

        // $mostViewedJobs and $mostSharedJobs (for 30-day stats) are no longer populated here.
        // The dashboard_overview_view.php will now rely on $allTimeTopViewedJobs and $allTimeTopSharedJobs.
        error_log("[FETCH_CONTENT_INFO] dashboard_overview: Time-windowed most viewed/shared (from job_views/job_shares.json) are no longer processed.");

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

        // Take the last N points
        $cpuHistory = array_slice($cpuHistory, -$dataPointsLimit);
        $memoryHistory = array_slice($memoryHistory, -$dataPointsLimit);

        // Format for Chart.js
        foreach ($cpuHistory as $point) {
            $serverMetricsLabels[] = date('H:i', $point['timestamp']); // Use time as label
            $serverCpuData[] = $point['value'];
        }
        foreach ($memoryHistory as $point) {
             $serverMemoryData[] = $point['value'];
        }
        break; 

    case 'dashboard_service_one': // Basic Server Info
        $serverPhpVersion = phpversion();
        $serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? 'N/A';
        log_app_activity("User '$loggedInUserId' accessed 'dashboard_service_one' view.", "ACCESS_GRANTED");
        $applicationVersion = '1.0.0'; 
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

        // --- User Performance Tracking Data ---
        $allJobs = loadJobs($jobsFilename); 
        error_log("[FETCH_CONTENT_DEBUG] User Performance (User Info Tab): Users count for init: " . (is_array($users) ? count($users) : 'not an array'));
        if (!is_array($users)) {
            $users = []; // Should not happen if loadUsers works
            error_log("[FETCH_CONTENT_ERROR] User Performance (User Info Tab): \$users was not an array after loadUsers.");
        }
        // This initial loop for performance arrays is redundant due to reset and re-initialization below.
        // foreach ($users as $user) {
        //     if (!isset($user['username'])) {
        //         error_log("[FETCH_CONTENT_WARNING] User Performance (User Info Tab): User found without 'username': " . print_r($user, true));
        //         continue;
        //     }
        //     $username = $user['username']; 
        //     $lcUsername = strtolower($username); 
        //     $displayName = $user['display_name'] ?? $username;
        //     $userPerformanceOverall[$lcUsername] = ['name' => $displayName, 'count' => 0];
        //     $userPerformanceLast30Days[$lcUsername] = ['name' => $displayName, 'count' => 0];
        //     $userPerformanceToday[$lcUsername] = ['name' => $displayName, 'count' => 0];
        // }

        $thirtyDaysAgoTimestamp = strtotime('-30 days midnight');
        $todayTimestamp = strtotime('today midnight');
        error_log("[FETCH_CONTENT_DEBUG] User Performance (User Info Tab): AllJobs count: " . (is_array($allJobs) ? count($allJobs) : 'not an array'));
        
        // Ensure all performance arrays are reset for this specific view context
        $userPerformanceOverall = [];
        $userPerformanceLast30Days = [];
        $userPerformanceToday = [];
        $userPerformanceList = [];
        $performanceLeaderboard = []; // This will be rebuilt


        // Create a lookup map of lowercase usernames to their original user data for efficient access
        $lcUsersMap = [];
        foreach ($users as $user) { // $users is already loaded for this case
            if (isset($user['username'])) {
                $lcUsersMap[strtolower(trim($user['username']))] = $user; // Trim username before lowercasing for map key
            }
        }

        // Initialize performance arrays using the $lcUsersMap to ensure all registered users are included
        foreach ($lcUsersMap as $lcUsernameKey => $userData) {
            $displayName = $userData['display_name'] ?? $userData['username'];
            $userPerformanceOverall[$lcUsernameKey] = ['name' => $displayName, 'count' => 0];
            $userPerformanceLast30Days[$lcUsernameKey] = ['name' => $displayName, 'count' => 0];
            $userPerformanceToday[$lcUsernameKey] = ['name' => $displayName, 'count' => 0];
        }

        foreach ($allJobs as $job) {
            $postedByRaw = $job['posted_by_user_id'] ?? null; 
            if ($postedByRaw) {
                $lcPostedByJob = strtolower(trim($postedByRaw)); 

                if (isset($lcUsersMap[$lcPostedByJob])) {
                    $canonicalLcUsername = $lcPostedByJob; 
                    
                    if (isset($userPerformanceOverall[$canonicalLcUsername])) {
                        $userPerformanceOverall[$canonicalLcUsername]['count']++;
                        $jobTimestamp = $job['posted_on_unix_ts'] ?? 0;
                        if ($jobTimestamp >= $thirtyDaysAgoTimestamp) {
                            if (array_key_exists($canonicalLcUsername, $userPerformanceLast30Days)) {
                                $userPerformanceLast30Days[$canonicalLcUsername]['count']++;
                            } else {
                                error_log("[FETCH_CONTENT_ERROR] User '$canonicalLcUsername' missing from userPerformanceLast30Days during increment.");
                            }
                        }
                        if ($jobTimestamp >= $todayTimestamp) {
                            if (array_key_exists($canonicalLcUsername, $userPerformanceToday)) {
                                $userPerformanceToday[$canonicalLcUsername]['count']++;
                            } else {
                                error_log("[FETCH_CONTENT_ERROR] User '$canonicalLcUsername' missing from userPerformanceToday during increment.");
                            }
                        }
                    } else {
                        error_log("[FETCH_CONTENT_ERROR] User Performance (User Info Tab): User '$canonicalLcUsername' from job ID '{$job['id']}' was not pre-initialized in performance arrays. This indicates an issue with user list processing.");
                    }
                } else { 
                     error_log("[FETCH_CONTENT_WARNING] User Performance (User Info Tab): Job posted_by_user_id (raw: '$postedByRaw', lc: '$lcPostedByJob') not found in registered users list (lcUsersMap). Job ID: " . ($job['id'] ?? 'N/A'));
            }
        }
       $performanceLeaderboard = $userPerformanceLast30Days;
        $performanceLeaderboard = array_filter($performanceLeaderboard, function($user) {
            return ($user['count'] ?? 0) > 0;
        });
        uasort($performanceLeaderboard, function ($a, $b) { return ($b['count'] ?? 0) - ($a['count'] ?? 0); });

        // "User Job Posting Performance" list data ($userPerformanceList) is no longer needed for this view.
        // "Overall Job Posting Performance by User" chart data is no longer needed.
        $userPerformanceList = []; // Clear it as it's not used by this view anymore

        $colorIndexForOverallPerfChart = 0;
        // Re-use baseColors or define a new set if needed for more users
        // $baseColors is defined later for Quarterly chart, ensure it's available or define here
        $chartBaseColors = ['rgba(255, 99, 132, 0.7)', 'rgba(54, 162, 235, 0.7)', 'rgba(255, 206, 86, 0.7)', 'rgba(75, 192, 192, 0.7)', 'rgba(153, 102, 255, 0.7)', 'rgba(255, 159, 64, 0.7)', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#FF6384'];

        foreach ($userPerformanceList as $lcUsername => $data) {
            $userOverallPerformanceChartLabels[] = $data['name'];
            $userOverallPerformanceChartData[] = $data['overall'] ?? 0;
            $userOverallPerformanceChartColors[] = $chartBaseColors[$colorIndexForOverallPerfChart % count($chartBaseColors)];
            $colorIndexForOverallPerfChart++;
        }
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
        
        $topPostersChartLabels = [];
        $topPostersChartData = [];

        if (!empty($performanceLeaderboard)) {
            foreach($performanceLeaderboard as $data) {
                if (($data['count'] ?? 0) > 0) { 
                    $topPostersChartLabels[] = $data['name'];
                    $topPostersChartData[] = $data['count'];
                }
            }
        }

        // User Achievements (Points/Earnings - Last 30 Days) chart data preparation removed from here.

        // --- NEW: Quarterly User Earnings Chart Data (Last 3 Months) ---
        $rupeesPerJob = 3; // Or your defined points per job
        $currentMonthTimestampForQuarterly = strtotime('first day of this month midnight');
        $quarterlyMonthLabelsRaw = []; 
        $monthTimestamps = [];

        for ($m = 0; $m < 3; $m++) {
            $monthTimestamp = strtotime("-$m months", $currentMonthTimestampForQuarterly);
            $quarterlyMonthLabelsRaw[] = date('F Y', $monthTimestamp); 
            $monthTimestamps[date('Y-m', $monthTimestamp)] = [
                'start' => $monthTimestamp,
                'end' => strtotime('last day of this month 23:59:59', $monthTimestamp)
            ];
        }
        $quarterlyUserEarningsLabels = array_reverse($quarterlyMonthLabelsRaw); 
        $targetMonthKeysForQuarterly = array_keys($monthTimestamps); 
        $targetMonthKeysForQuarterly = array_reverse($targetMonthKeysForQuarterly); 

        $userMonthlyJobCountsForQuarterly = [];

        foreach ($allJobs as $job) {
            $postedByRaw = $job['posted_by_user_id'] ?? null;
            if (!$postedByRaw) continue;
            $lcPostedByJob = strtolower(trim($postedByRaw));

            if (!isset($lcUsersMap[$lcPostedByJob])) continue; // Skip if job poster isn't a registered user
            $canonicalLcUsername = $lcPostedByJob;

            $postedTimestamp = $job['posted_on_unix_ts'] ?? 0;
            if ($postedTimestamp === 0) continue;
            $jobMonthYear = date('Y-m', $postedTimestamp);

            if (in_array($jobMonthYear, $targetMonthKeysForQuarterly)) {
                if (!isset($userMonthlyJobCountsForQuarterly[$canonicalLcUsername])) {
                    $userMonthlyJobCountsForQuarterly[$canonicalLcUsername] = array_fill_keys($targetMonthKeysForQuarterly, 0);
                }
                $userMonthlyJobCountsForQuarterly[$canonicalLcUsername][$jobMonthYear]++;
            }
        }

        $colorIndexForQuarterlyChart = 0; 
        $baseColors = ['rgba(255, 99, 132, 0.7)', 'rgba(54, 162, 235, 0.7)', 'rgba(255, 206, 86, 0.7)', 'rgba(75, 192, 192, 0.7)', 'rgba(153, 102, 255, 0.7)', 'rgba(255, 159, 64, 0.7)'];
        
        foreach ($lcUsersMap as $lcUsernameKey => $userData) { // Iterate through all registered users via lcUsersMap
            $userDisplayName = $userData['display_name'] ?? $userData['username'];
            $userMonthlyEarnings = [];
            foreach ($targetMonthKeysForQuarterly as $monthKey) {
                $jobsCount = $userMonthlyJobCountsForQuarterly[$lcUsernameKey][$monthKey] ?? 0;
                $userMonthlyEarnings[] = $jobsCount * $rupeesPerJob;
            }
            if (array_sum($userMonthlyEarnings) > 0) {
                $userColor = $baseColors[$colorIndexForQuarterlyChart % count($baseColors)];
                $quarterlyUserEarningsData[] = [
                    'label' => $userDisplayName,
                    'data' => $userMonthlyEarnings,
                    'backgroundColor' => $userColor,
                    'borderColor' => str_replace('0.7', '1', $userColor), 
                    'borderWidth' => 1
                ];
                $colorIndexForQuarterlyChart++;
            }
        }
        break;
        }
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

        // Calculate Jobs Today and Jobs This Month for Job Stats view
        // This should be based on all jobs for this view
        $jobsTodayCount = 0;
        $jobsMonthlyCount = 0;
        $nowForJobStats = time(); // Use a distinct 'now' variable if needed, or global $now is fine
        $startOfTodayForJobStats = strtotime('today midnight');
        $startOfMonthForJobStats = strtotime('first day of this month midnight');

        if (!empty($allJobs)) {
            foreach ($allJobs as $job) {
                 $postedTimestamp = $job['posted_on_unix_ts'] ?? (isset($job['posted_on']) ? strtotime($job['posted_on']) : 0);
                 if ($postedTimestamp >= $startOfTodayForJobStats) $jobsTodayCount++;
                 if ($postedTimestamp >= $startOfMonthForJobStats) $jobsMonthlyCount++;
            }
        }
         $jobCountsByDay = array_fill(0, 30, 0);
         for ($i = 29; $i >= 0; $i--) {
             $graphLabels[] = date('M d', strtotime("-$i days"));
         }
         if (!empty($allJobs)) {
             foreach ($allJobs as $job) {
                  $postedTimestamp = $job['posted_on_unix_ts'] ?? (isset($job['posted_on']) ? strtotime($job['posted_on']) : 0);
                  if ($postedTimestamp > 0) {
                      $now = time(); 
                      $daysAgo = floor(($now - $postedTimestamp) / (24 * 60 * 60));
                      if ($daysAgo >= 0 && $daysAgo < 30) {
                          $jobCountsByDay[29 - $daysAgo]++;
                      }
                  }
             }
         }
         $graphData = $jobCountsByDay;

        if (!empty($allJobs)) {
            foreach ($allJobs as $job) {
                $totalLifetimeViews += (int)($job['total_views_count'] ?? 0); 
                $totalLifetimeShares += (int)($job['total_shares_count'] ?? 0);
            }
        }
        break;

    case 'dashboard_visitors_info':
        log_app_activity("User '$loggedInUserId' accessed 'dashboard_visitors_info' view.", "ACCESS_GRANTED");

        $searchKeywordsLogFile = __DIR__ . '/../data/search_keywords_log.json';
        if (file_exists($searchKeywordsLogFile)) {
            $keywordsJson = file_get_contents($searchKeywordsLogFile);
            $allSearchedKeywords = json_decode($keywordsJson, true);

            if (is_array($allSearchedKeywords) && !empty($allSearchedKeywords)) {
                $allSearchedKeywords = array_map('strtolower', $allSearchedKeywords);
                $keywordCounts = array_count_values($allSearchedKeywords);
                arsort($keywordCounts); 

                $topKeywords = array_slice($keywordCounts, 0, 25, true); 

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

        $allJobs = loadJobs($jobsFilename); 
        if (!empty($allJobs)) {
            $tempAllTimeViewedVisitors = $allJobs;
            usort($tempAllTimeViewedVisitors, function ($a, $b) {
                return ($b['total_views_count'] ?? 0) - ($a['total_views_count'] ?? 0);
            });
            $allTimeTopViewedJobs = array_slice($tempAllTimeViewedVisitors, 0, 10); 

            $tempAllTimeSharedVisitors = $allJobs;
            usort($tempAllTimeSharedVisitors, function ($a, $b) {
                return ($b['total_shares_count'] ?? 0) - ($a['total_shares_count'] ?? 0);
            });
            $allTimeTopSharedJobs = array_slice($tempAllTimeSharedVisitors, 0, 10); 
        }
        error_log("[FETCH_CONTENT_INFO] dashboard_visitors_info: Time-windowed most viewed/shared (from job_views/job_shares.json) are no longer processed.");
        break; 
    
    case 'dashboard_qoe':
        log_app_activity("User '$loggedInUserId' accessed 'dashboard_qoe' view.", "ACCESS_GRANTED");
        $qoeMetrics = [
            'avg_load_time' => 'N/A (Placeholder)',
            'error_rate_percent' => 'N/A (Placeholder)',
            'user_feedback_summary' => 'No QOE data available yet. (Placeholder)',
        ];

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

        for ($i = 29; $i >= 0; $i--) {
            $qoeChartLabels[] = date('M d', strtotime("-$i days"));
        }

        if (!function_exists('calculate_daily_averages')) {
            function calculate_daily_averages($metricHistory, $days = 30) {
                $dailyAverages = array_fill(0, $days, null); 
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

        if (!empty($qoeDnsResolutionData) || !empty($qoeServerResponseData)) {
            $shouldLoadChartJsForQoe = true;
        }
        break;

    case 'reported_jobs':
        if (!($loggedInUserRole === 'super_admin' || in_array($loggedInUserRole, $allRegionalAdminRoles))) {
            echo '<p class="status-message error">Access Denied: You do not have permission to view reported jobs.</p>';
            log_app_activity("User '$loggedInUserId' (Role: '$loggedInUserRole') attempted to access 'reported_jobs' view - DENIED.", "SECURITY_WARNING");
            exit;
        }
        log_app_activity("User '$loggedInUserId' accessed 'reported_jobs' view.", "ACCESS_GRANTED");

        $reportedJobsFilename = __DIR__ . '/../data/reported_jobs.json';
        $rawReports = [];
        if (file_exists($reportedJobsFilename)) {
            $jsonData = file_get_contents($reportedJobsFilename);
            if ($jsonData) {
                $rawReports = json_decode($jsonData, true);
                if (!is_array($rawReports)) $rawReports = [];
            }
        }

        // Load all jobs to get titles
        $allJobsData = loadJobs($jobsFilename);
        $jobsLookup = [];
        foreach ($allJobsData as $job) {
            if (isset($job['id'])) {
                $jobsLookup[$job['id']] = $job;
            }
        }

        foreach ($rawReports as $report) {
            $report['job_title'] = $jobsLookup[$report['job_id']]['title'] ?? 'Job Not Found or Title Missing';
            $report['job_company'] = $jobsLookup[$report['job_id']]['company'] ?? '';
            $reportedJobsData[] = $report;
        }
        // Reports are already unshifted, so they are newest first.
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
            
            $targetUserBaseRole = 'user'; // Default if function is missing or returns unexpected
            if (function_exists('getRoleParts')) {
                $roleParts = getRoleParts($targetUserRole);
                if (is_array($roleParts) && isset($roleParts['base_role'])) {
                    $targetUserBaseRole = $roleParts['base_role'];
                } else {
                    error_log("Function getRoleParts() exists but did not return the expected array structure for role: " . htmlspecialchars($targetUserRole));
                }
            } else {
                error_log("CRITICAL ERROR: Function getRoleParts() is not defined. Please check admin/includes/user_helpers.php. Defaulting targetUserBaseRole to 'user'. This may affect authorization logic.");
            }

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
        $allUsers = loadUsers($usersFilename); // Ensure $allUsers is loaded
        $rupeesPerJob = 3;
        // Clear old achievements chart data variables as they are being removed
        $achievementsChartLabels = [];
        $achievementsChartData = [];

        // --- User Earnings (Last 3 Months) Chart Data for Achievements Tab ---
        // This logic is similar to the one in 'dashboard_user_info'
        $currentMonthTimestampForQuarterly = strtotime('first day of this month midnight');
        $quarterlyMonthLabelsRaw = []; 
        $monthTimestamps = [];

        for ($m = 0; $m < 3; $m++) {
            $monthTimestamp = strtotime("-$m months", $currentMonthTimestampForQuarterly);
            $quarterlyMonthLabelsRaw[] = date('F Y', $monthTimestamp); 
            $monthTimestamps[date('Y-m', $monthTimestamp)] = [
                'start' => $monthTimestamp,
                'end' => strtotime('last day of this month 23:59:59', $monthTimestamp)
            ];
        }
        $quarterlyUserEarningsLabels = array_reverse($quarterlyMonthLabelsRaw); 
        $targetMonthKeysForQuarterly = array_keys($monthTimestamps); 
        $targetMonthKeysForQuarterly = array_reverse($targetMonthKeysForQuarterly); 

        $userMonthlyJobCountsForQuarterly = [];

        // Create a lowercase user map for matching
        $lcUsersMapForAchievements = [];
        foreach ($allUsers as $user) {
            if (isset($user['username'])) {
                $lcUsersMapForAchievements[strtolower(trim($user['username']))] = $user;
            }
        }

        foreach ($allJobs as $job) {
            $postedByRaw = $job['posted_by_user_id'] ?? null;
            if (!$postedByRaw) continue;
            $lcPostedByJob = strtolower(trim($postedByRaw));

            if (!isset($lcUsersMapForAchievements[$lcPostedByJob])) continue; 
            $canonicalLcUsername = $lcPostedByJob;

            $postedTimestamp = $job['posted_on_unix_ts'] ?? 0;
            if ($postedTimestamp === 0) continue;
            $jobMonthYear = date('Y-m', $postedTimestamp);

            if (in_array($jobMonthYear, $targetMonthKeysForQuarterly)) {
                if (!isset($userMonthlyJobCountsForQuarterly[$canonicalLcUsername])) {
                    $userMonthlyJobCountsForQuarterly[$canonicalLcUsername] = array_fill_keys($targetMonthKeysForQuarterly, 0);
                }
                $userMonthlyJobCountsForQuarterly[$canonicalLcUsername][$jobMonthYear]++;
            }
        }

        $colorIndexForQuarterlyChart = 0; 
        $baseColors = [
            'rgba(255, 99, 132, 0.7)', 'rgba(54, 162, 235, 0.7)', 'rgba(255, 206, 86, 0.7)',
            'rgba(75, 192, 192, 0.7)', 'rgba(153, 102, 255, 0.7)', 'rgba(255, 159, 64, 0.7)'
        ];
        
        $quarterlyUserEarningsData = []; // Ensure it's initialized for this case
        foreach ($lcUsersMapForAchievements as $lcUsernameKey => $userData) { 
            $userDisplayName = $userData['display_name'] ?? $userData['username'];
            $userMonthlyEarnings = [];
            foreach ($targetMonthKeysForQuarterly as $monthKey) {
                $jobsCount = $userMonthlyJobCountsForQuarterly[$lcUsernameKey][$monthKey] ?? 0;
                $userMonthlyEarnings[] = $jobsCount * $rupeesPerJob;
            }
            if (array_sum($userMonthlyEarnings) > 0) {
                $userColor = $baseColors[$colorIndexForQuarterlyChart % count($baseColors)];
                $quarterlyUserEarningsData[] = [
                    'label' => $userDisplayName,
                    'data' => $userMonthlyEarnings,
                    'backgroundColor' => $userColor,
                    'borderColor' => str_replace('0.7', '1', $userColor), 
                    'borderWidth' => 1
                ];
                $colorIndexForQuarterlyChart++;
            }
        }
        break;

    case 'server_management':
        if (!($loggedInUserRole === 'super_admin' || in_array($loggedInUserRole, $allRegionalAdminRoles))) {
            echo '<p class="status-message error">Access Denied: You do not have permission to view server management.</p>';
            log_app_activity("User '$loggedInUserId' (Role: '$loggedInUserRole') attempted to access 'server_management' view - DENIED.", "SECURITY_WARNING");
            error_log("Admin Fetch Content Access Denied: User '" . htmlspecialchars($loggedInUserId) . "' (role: " . htmlspecialchars($loggedInUserRole) . ") attempted to access 'server_management' view via AJAX.");
            exit;
        }
        $serverPhpVersion = phpversion();
        log_app_activity("User '$loggedInUserId' accessed 'server_management' view.", "ACCESS_GRANTED");
        $serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? 'N/A';
        $serverOs = php_uname(); 
        break;

    case 'whatsapp_profile':
        // No specific data needs to be pre-loaded from PHP for this view,
        // as all interactions are client-side JavaScript with whatsapp_manager.php
        log_app_activity("User '$loggedInUserId' accessed 'whatsapp_profile' view.", "ACCESS_GRANTED");
        break;

    case 'logs':
        if ($loggedInUserRole !== 'super_admin') {
            echo '<p class="status-message error">Access Denied: You do not have permission to view server logs.</p>';
            log_app_activity("User '$loggedInUserId' (Role: '$loggedInUserRole') attempted to access 'logs' view - DENIED.", "SECURITY_WARNING");
            error_log("Admin Fetch Content Access Denied: User '" . htmlspecialchars($loggedInUserId) . "' (role: " . htmlspecialchars($loggedInUserRole) . ") attempted to access 'logs' view.");
            exit;
        }
        $logFilePath = APP_LOG_FILE_PATH; 
        $linesToFetch = 100; 

        if (file_exists($logFilePath) && is_readable($logFilePath)) {
            $fileContents = file($logFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($fileContents === false) {
                $logEntries[] = "Error: Could not read log file content.";
                error_log("Admin Logs View System Error: Could not read app_activity.log content from: " . $logFilePath); 
            } else {
                $logEntries = array_slice($fileContents, -$linesToFetch);
                $logEntries = array_filter($logEntries, function($entry) {
                    return strpos($entry, "attempting to access view: 'logs'") === false &&
                           strpos($entry, "accessed 'logs' view.") === false;
                });
                if (empty($logEntries)) {
                    $logEntries[] = "Log file is empty or contains no recent entries.";
                }
            }
        } else {
            $logEntries[] = "Application log file not found or not readable at: " . htmlspecialchars($logFilePath);
            error_log("Admin Logs View System Error: Application log file (app_activity.log) not found or not readable at: " . $logFilePath);
            log_app_activity("Log file initialized.", "SYSTEM"); 
        }
        $isAjaxLogUpdate = isset($_GET['ajax']) && $_GET['ajax'] === '1';

        if ($isAjaxLogUpdate) {
            ob_clean(); 
            if (!empty($logEntries) && !(count($logEntries) === 1 && strpos($logEntries[0], 'No log entries') !== false && strpos($logEntries[0], 'error loading logs') !== false )) {
                foreach ($logEntries as $entry) {
                    echo '<div class="log-entry">' . htmlspecialchars($entry) . '</div>';
                }
            } else {
                echo '<div class="log-entry no-data-message">' . htmlspecialchars($logEntries[0] ?? 'No log entries found or log file is empty.') . '</div>';
            }
            exit; 
        }
       
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

$allowedFetchViews = ['dashboard_overview', 'dashboard_service_one', 'dashboard_user_info', 'dashboard_job_stats', 'dashboard_service_two', 'dashboard_visitors_info', 'dashboard_qoe', 'manage_jobs', 'reported_jobs', 'edit_job', 'edit_user', 'profile', 'messages', 'generate_message', 'manage_users', 'post_job', 'achievements', 'server_management', 'whatsapp_profile', 'logs'];

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
