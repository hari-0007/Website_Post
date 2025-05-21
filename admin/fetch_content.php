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

// Get the requested view from the GET parameters
$requestedView = $_GET['view'] ?? 'dashboard'; // Default to dashboard view

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

$loggedInUserRole = $_SESSION['admin_role'] ?? 'user';
$loggedInUserId = $_SESSION['admin_username'] ?? null;

// Filepath for the daily visitor counter
$visitorCounterFile = __DIR__ . '/../data/daily_visitors.json';

function getDailyVisitorData($filePath) {
    if (!file_exists($filePath)) return [];
    $visitorData = json_decode(file_get_contents($filePath), true);
    return is_array($visitorData) ? $visitorData : [];
}

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
    case 'dashboard':
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
        // --- User Performance Tracking Data ---
        // This block was previously outside the 'dashboard' case due to a misplaced 'break;'
        // Initialize performance arrays with all users
        foreach ($users as $user) {
            $username = $user['username'];
            $userPerformanceOverall[$username] = ['name' => $user['display_name'] ?? $username, 'count' => 0];
            $userPerformanceLast30Days[$username] = ['name' => $user['display_name'] ?? $username, 'count' => 0];
            $userPerformanceToday[$username] = ['name' => $user['display_name'] ?? $username, 'count' => 0];
        }

        $thirtyDaysAgoTimestamp = strtotime('-30 days midnight');
        $todayTimestamp = strtotime('today midnight');

        foreach ($allJobs as $job) {
            $postedBy = $job['posted_by_user_id'] ?? null;
            if ($postedBy && isset($userPerformanceOverall[$postedBy])) {
                // Overall count
                $userPerformanceOverall[$postedBy]['count']++;

                // Last 30 days count
                $jobTimestamp = $job['posted_on_unix_ts'] ?? 0;
                if ($jobTimestamp >= $thirtyDaysAgoTimestamp) {
                    $userPerformanceLast30Days[$postedBy]['count']++;
                }

                // Today's count
                if ($jobTimestamp >= $todayTimestamp) {
                    $userPerformanceToday[$postedBy]['count']++;
                }
            }
        }

        // Sort for leaderboard (last 30 days)
        $performanceLeaderboard = $userPerformanceLast30Days;
        uasort($performanceLeaderboard, function ($a, $b) {
            return $b['count'] - $a['count'];
        });
        $performanceLeaderboard = array_slice($performanceLeaderboard, 0, 5, true); // Top 5

        // Filter out users with 0 posts for cleaner display in lists, or keep them to show all users
        // For now, we'll keep all users in the main lists and let the view decide if it wants to filter.

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
                        $mostViewedJobs[] = ['title' => $job['title'] ?? 'Unknown Job', 'company' => $job['company'] ?? '', 'views' => $viewCount];
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
        break; // Correct position for the break statement for case 'dashboard'

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
                error_log("Admin Fetch Content Access Denied: User '" . htmlspecialchars($loggedInUserId) . "' (role: " . htmlspecialchars($loggedInUserRole) . ") attempted to load edit page for user '" . htmlspecialchars($userToEdit['username'] ?? 'N/A') . "' (role: " . htmlspecialchars($targetUserRole) . ") via AJAX.");
                exit;
            }
        } else {
            echo '<p class="status-message error">Error: No username specified for editing.</p>';
            exit;
        }
        break;

    case 'profile':
        $userToEdit = findUserByUsername($loggedInUserId, $usersFilename);
        break;

    case 'messages':
        if (!($loggedInUserRole === 'super_admin' || in_array($loggedInUserRole, $allRegionalAdminRoles))) {
            echo '<p class="status-message error">Access Denied: You do not have permission to view messages.</p>';
            error_log("Admin Fetch Content Access Denied: User '" . htmlspecialchars($loggedInUserId) . "' (role: " . htmlspecialchars($loggedInUserRole) . ") attempted to access 'messages' view via AJAX.");
            exit;
        }
        $feedbackMessages = loadFeedbackMessages($feedbackFilename);
        if (!empty($feedbackMessages)) {
            usort($feedbackMessages, function($a, $b) {
                return ($b['timestamp'] ?? 0) - ($a['timestamp'] ?? 0);
            });
        }
        break;

    case 'generate_message':
        if (!($loggedInUserRole === 'super_admin' || in_array($loggedInUserRole, $allRegionalAdminRoles))) {
            echo '<p class="status-message error">Access Denied: You do not have permission to generate posts.</p>';
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
        break;

    case 'manage_users':
        if (!($loggedInUserRole === 'super_admin' || in_array($loggedInUserRole, $allRegionalAdminRoles) || in_array($loggedInUserRole, $allRegionalManagerRoles))) {
            echo '<p class="status-message error">Access Denied: You do not have permission to manage users.</p>';
            error_log("Admin Fetch Content Access Denied: User '" . htmlspecialchars($loggedInUserId) . "' (role: " . htmlspecialchars($loggedInUserRole) . ") attempted to access 'manage_users' view via AJAX.");
            exit;
        }
         $users = loadUsers($usersFilename);
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

    default:
        echo '<p class="status-message error">Error: Invalid view specified.</p>';
        error_log("Admin Error: Invalid view requested via AJAX: " . htmlspecialchars($requestedView));
        exit;
}

$viewFileSuffix = '_view.php';
$viewFilePath = __DIR__ . '/views/' . $requestedView . $viewFileSuffix;

$allowedFetchViews = ['dashboard', 'manage_jobs', 'edit_job', 'edit_user', 'profile', 'messages', 'generate_message', 'manage_users', 'post_job', 'achievements'];

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
