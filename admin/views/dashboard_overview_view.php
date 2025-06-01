<?php

// admin/views/dashboard_overview_view.php - Displays the main dashboard overview content

// This file is included by fetch_content.php when the view is 'dashboard_overview'.

// Ensure necessary variables are initialized if they weren't set by fetch_content.php (fallback)
$loggedInUserRole = $loggedInUserRole ?? $_SESSION['admin_role'] ?? 'user';
$allJobs = $allJobs ?? [];
$feedbackMessages = $feedbackMessages ?? [];
$users = $users ?? [];
$jobsTodayCount = $jobsTodayCount ?? 0;
$jobsMonthlyCount = $jobsMonthlyCount ?? 0;
$graphLabels = $graphLabels ?? [];
$graphData = $graphData ?? [];

$totalViews = $totalViews ?? 0;
$monthlyVisitors = $monthlyVisitors ?? 0;
$visitorGraphLabels = $visitorGraphLabels ?? [];
$visitorGraphData = $visitorGraphData ?? [];

$userCountsByRole = $userCountsByRole ?? [];
$userCountsByStatus = $userCountsByStatus ?? [];
$recentJobs = $recentJobs ?? [];
$recentMessages = $recentMessages ?? [];
$recentPendingUsers = $recentPendingUsers ?? [];

// User performance data is now primarily in the 'User Stats' tab, but keep placeholders if overview needs a summary
$userPerformanceOverall = $userPerformanceOverall ?? [];
$userPerformanceLast30Days = $userPerformanceLast30Days ?? [];
$userPerformanceToday = $userPerformanceToday ?? [];
$performanceLeaderboard = $performanceLeaderboard ?? [];
// $mostViewedJobs and $mostSharedJobs for 30-day stats are no longer used here.

// Lifetime stats from jobs.json
$totalLifetimeViews = $totalLifetimeViews ?? 0;
$totalLifetimeShares = $totalLifetimeShares ?? 0;
$allTimeTopViewedJobs = $allTimeTopViewedJobs ?? [];
$allTimeTopSharedJobs = $allTimeTopSharedJobs ?? [];

// Monthly views/shares for jobs posted this month
$totalLifetimeViewsOfJobsPostedThisMonth = $totalLifetimeViewsOfJobsPostedThisMonth ?? 0;
$totalLifetimeSharesOfJobsPostedThisMonth = $totalLifetimeSharesOfJobsPostedThisMonth ?? 0;

// Total page loads (from daily_visitors.json, prepared in fetch_content.php)
$totalPageRequestsAllTime = $totalPageRequestsAllTime ?? 0; // Already used for visitor stats, but good to ensure it's here
$monthlyTotalPageRequests = $monthlyTotalPageRequests ?? 0;

// Server info is now in its own tab, but keep placeholders if overview needs a summary
$serverPhpVersion = $serverPhpVersion ?? 'N/A';
$serverSoftware = $serverSoftware ?? 'N/A';
$serverOs = $serverOs ?? 'N/A';
$serverMetricsLabels = $serverMetricsLabels ?? []; // For server monitoring charts if shown on overview
$serverCpuData = $serverCpuData ?? [];
$serverMemoryData = $serverMemoryData ?? [];


// Counts for display
$displayTotalJobsCount = count($allJobs);
$displayTotalUsersCount = count($users);
if ($loggedInUserRole === 'user_group_manager' && isset($filteredUsersCountForUGM)) { // From context
    $displayTotalUsersCount = $filteredUsersCountForUGM;
}
$displayTotalMessagesCount = count($feedbackMessages);

// Role groups ($allRegionalAdminRoles, $allRegionalManagerRoles) are expected to be in scope
// from fetch_content.php

?>

<div class="dashboard-content">
    <h2 class="dashboard-main-title">Dashboard Overview</h2> <!-- Changed from h3 to h2 for semantic hierarchy, styled below -->
    <?php
    if (!($loggedInUserRole === 'super_admin' || in_array($loggedInUserRole, $allRegionalAdminRoles ?? []))) {
        echo "<p class='user-specific-stats-note'><em>Displaying job statistics for posts made by you.</em></p>";
    }
    ?>

    <div class="stats-grid">
        <?php if ($loggedInUserRole === 'super_admin' || in_array($loggedInUserRole, $allRegionalAdminRoles ?? [])): ?>
            <div class="stat-card">
                <h4>Total Jobs (All)</h4>
                <p><?= htmlspecialchars($displayTotalJobsCount) ?></p>
            </div>
        <?php endif; ?>

        <div class="stat-card">
            <h4>Jobs Posted Today <?php if (!($loggedInUserRole === 'super_admin' || in_array($loggedInUserRole, $allRegionalAdminRoles ?? []))) echo "(By You)"; ?></h4>
            <p><?= htmlspecialchars($jobsTodayCount) ?></p>
        </div>

        <div class="stat-card">
            <h4>Jobs Posted This Month <?php if (!($loggedInUserRole === 'super_admin' || in_array($loggedInUserRole, $allRegionalAdminRoles ?? []))) echo "(By You)"; ?></h4>
            <p><?= htmlspecialchars($jobsMonthlyCount) ?></p>
        </div>

        <?php if ($loggedInUserRole === 'super_admin' || in_array($loggedInUserRole, $allRegionalAdminRoles ?? [])): ?>
            <div class="stat-card">
                <h4>Total Messages</h4>
                <p><?= htmlspecialchars($displayTotalMessagesCount) ?></p>
            </div>
        <?php endif; ?>

        <?php if ($loggedInUserRole === 'super_admin' || in_array($loggedInUserRole, $allRegionalAdminRoles ?? []) || in_array($loggedInUserRole, $allRegionalManagerRoles ?? [])): ?>
            <div class="stat-card">
                <h4>Total Users <?php
                                if ($loggedInUserRole === 'super_admin') echo "(All)";
                                elseif (in_array($loggedInUserRole, $allRegionalAdminRoles ?? []) || in_array($loggedInUserRole, $allRegionalManagerRoles ?? [])) echo "(Managed by You/Team)";
                               ?>
                </h4>
                <p><?= htmlspecialchars($displayTotalUsersCount) ?></p>
            </div>
        <?php endif; ?>
        <?php if ($loggedInUserRole === 'super_admin' || in_array($loggedInUserRole, $allRegionalAdminRoles ?? [])): ?>
            <div class="stat-card">
                <h4>Total Lifetime Views (All Jobs)</h4>
                <p><?= htmlspecialchars($totalLifetimeViews) ?></p>
            </div>
            <div class="stat-card">
                <h4>Total Lifetime Shares (All Jobs)</h4>
                <p><?= htmlspecialchars($totalLifetimeShares) ?></p>
            </div>
            <div class="stat-card">
                <h4>Total Views This Month (Jobs Posted This Month)</h4>
                <p><?= htmlspecialchars($totalLifetimeViewsOfJobsPostedThisMonth) ?></p>
            </div>
            <div class="stat-card">
                <h4>Total Shares This Month (Jobs Posted This Month)</h4>
                <p><?= htmlspecialchars($totalLifetimeSharesOfJobsPostedThisMonth) ?></p>
            </div>
            <div class="stat-card">
                <h4>Total Page Loads This Month</h4>
                <p><?= htmlspecialchars($monthlyTotalPageRequests) ?></p>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($loggedInUserRole === 'super_admin' || in_array($loggedInUserRole, $allRegionalAdminRoles ?? [])): ?>
    <div class="stats-grid">
        <?php if (isset($userCountsByStatus['pending_approval']) && $userCountsByStatus['pending_approval'] > 0): ?>
        <div class="stat-card status-pending">
            <h4>Users Pending Approval</h4>
            <p><?= htmlspecialchars($userCountsByStatus['pending_approval']) ?></p>
        </div>
        <?php endif; ?>
        <?php if (isset($userCountsByStatus['active']) && $userCountsByStatus['active'] > 0): ?>
        <div class="stat-card status-active">
            <h4>Active Users</h4>
            <p><?= htmlspecialchars($userCountsByStatus['active']) ?></p>
        </div>
        <?php endif; ?>
        <?php if (isset($userCountsByStatus['disabled']) && $userCountsByStatus['disabled'] > 0): ?>
        <div class="stat-card status-disabled">
            <h4>Disabled Users</h4>
            <p><?= htmlspecialchars($userCountsByStatus['disabled']) ?></p>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>


    <?php if ($loggedInUserRole === 'super_admin' || in_array($loggedInUserRole, $allRegionalAdminRoles ?? [])): ?>
        <div class="stats-grid">
            <div class="stat-card">
                <h4>Total Unique Visitors</h4>
                <p><?= htmlspecialchars($totalViews) ?></p>
            </div>
             <div class="stat-card">
                <h4>Monthly Unique Visitors</h4>
                <p><?= htmlspecialchars($monthlyVisitors) ?></p>
            </div>
        </div>

        <?php if (!empty($visitorGraphLabels) && !empty($visitorGraphData)): ?>
        <div class="chart-container">
            <h3>Daily Visitors Last 30 Days</h3>
            <canvas id="visitorsChart"></canvas>
        </div>
        <?php endif; ?>
    <?php endif; // End visitor stats section ?>


    <?php if (!empty($graphLabels) && !empty($graphData)): ?>
    <div class="chart-container">
        <h3>Daily Job Posts Last 30 Days <?php if (!($loggedInUserRole === 'super_admin' || in_array($loggedInUserRole, $allRegionalAdminRoles ?? []))) echo "(By You)"; ?></h3>
        <canvas id="jobPostsChart"></canvas>
    </div>
    <?php endif; ?>

    <div class="dashboard-columns">
        <?php if (!empty($recentJobs)): ?>
        <div class="dashboard-column">
            <div class="dashboard-section">
                <h4>Recent Jobs</h4>
                    <ul class="activity-list">
                        <?php foreach ($recentJobs as $job): ?>
                            <li>
                                <strong><?= htmlspecialchars($job['title'] ?? 'N/A') ?></strong>
                                <?php if(!empty($job['company'])): ?> at <?= htmlspecialchars($job['company']) ?><?php endif; ?>
                                <small>(Posted: <?= htmlspecialchars(date('M d, Y', $job['posted_on_unix_ts'] ?? time())) ?>)</small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($loggedInUserRole === 'super_admin' || in_array($loggedInUserRole, $allRegionalAdminRoles ?? [])): ?>
        <?php if (!empty($recentMessages) || !empty($recentPendingUsers)): ?>
        <div class="dashboard-column">
            <?php if (!empty($recentMessages)): ?>
            <div class="dashboard-section">
                <h4>Recent Unread Messages</h4>
                     <ul class="activity-list">
                        <?php foreach ($recentMessages as $message): ?>
                            <li>
                                From: <strong><?= htmlspecialchars($message['name'] ?? 'N/A') ?></strong> (<?= htmlspecialchars($message['email'] ?? 'N/A') ?>)
                                <small><?= htmlspecialchars(substr($message['message'] ?? '', 0, 50)) ?>...</small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
            </div>
            <?php endif; ?>
            <?php if (!empty($recentPendingUsers)): ?>
            <div class="dashboard-section">
                <h4>Recent Pending Users</h4>
                    <ul class="activity-list">
                        <?php foreach ($recentPendingUsers as $user): ?>
                            <li>
                                <strong><?= htmlspecialchars($user['display_name'] ?? 'N/A') ?></strong> (<?= htmlspecialchars($user['username'] ?? 'N/A') ?>)
                                <small>Role: <?= htmlspecialchars(ucwords(str_replace('_',' ',$user['role'] ?? 'N/A'))) ?></small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    <?php if ($loggedInUserRole === 'super_admin' || in_array($loggedInUserRole, $allRegionalAdminRoles ?? [])): ?>
    <div class="stats-columns-container" style="display: flex; gap: 20px; flex-wrap: wrap; margin-top: 20px;">
        <div class="stats-column" style="width: 100%;">
            <?php if (!empty($allTimeTopViewedJobs)): ?>
            <div class="dashboard-section alltime-most-viewed-jobs-section">
                <h4>All-Time Top 5 Viewed Jobs</h4>
                    <ol class="leaderboard-list">
                        <?php foreach ($allTimeTopViewedJobs as $job): ?>
                            <li>
                                <div class="leaderboard-item-main-content">
                                    <span class="leaderboard-name">
                                        <?= htmlspecialchars($job['title'] ?? 'N/A') ?>
                                        <?php if(!empty($job['company'])): ?>
                                            <small>at <?= htmlspecialchars($job['company']) ?></small>
                                        <?php endif; ?>
                                    </span>
                                    <span class="leaderboard-count"><?= htmlspecialchars($job['total_views_count'] ?? 0) ?> views</span>
                                </div>
                                <?php if (!empty($job['id'])): ?>
                                <a href="../index.php?job_id=<?= htmlspecialchars($job['id']) ?>" target="_blank" class="leaderboard-action-button">View Job</a>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ol>
            </div>
            <?php else: ?>
            <div class="dashboard-section alltime-most-viewed-jobs-section">
                <h4>All-Time Top 5 Viewed Jobs</h4><p class="no-data-message">No job view data available.</p></div>
            <?php endif; ?>
        </div>
        <div class="stats-column" style="width: 100%;">
            <?php if (!empty($allTimeTopSharedJobs)): ?>
            <div class="dashboard-section alltime-most-shared-jobs-section">
                <h4>All-Time Top 5 Shared Jobs</h4>
                    <ol class="leaderboard-list">
                        <?php foreach ($allTimeTopSharedJobs as $job): ?>
                            <li>
                                <div class="leaderboard-item-main-content">
                                    <span class="leaderboard-name">
                                        <?= htmlspecialchars($job['title'] ?? 'N/A') ?>
                                        <?php if(!empty($job['company'])): ?>
                                            <small>at <?= htmlspecialchars($job['company']) ?></small>
                                        <?php endif; ?>
                                    </span>
                                    <span class="leaderboard-count"><?= htmlspecialchars($job['total_shares_count'] ?? 0) ?> shares</span>
                                </div>
                                <?php if (!empty($job['id'])): ?>
                                <a href="../index.php?job_id=<?= htmlspecialchars($job['id']) ?>" target="_blank" class="leaderboard-action-button">View Job</a>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ol>
            </div>
            <?php else: ?>
            <div class="dashboard-section alltime-most-shared-jobs-section">
                <h4>All-Time Top 5 Shared Jobs</h4><p class="no-data-message">No job share data available.</p></div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php /* User Performance, Server Info, and Server Monitoring Charts are now in their own tabs */ ?>

</div>

<?php
// Determine if Chart.js needs to be loaded for this specific overview page
// Only Visitors and Job Posts charts are on the overview page now.
$shouldLoadChartJsForOverview = false;
if (($loggedInUserRole === 'super_admin' || in_array($loggedInUserRole, $allRegionalAdminRoles ?? [])) && !empty($visitorGraphLabels) && !empty($visitorGraphData)) {
    $shouldLoadChartJsForOverview = true;
}
if (!empty($graphLabels) && !empty($graphData)) { // Job posts chart is for all roles with data
    $shouldLoadChartJsForOverview = true;
}

if ($shouldLoadChartJsForOverview): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        <?php if (($loggedInUserRole === 'super_admin' || in_array($loggedInUserRole, $allRegionalAdminRoles ?? [])) && !empty($visitorGraphLabels) && !empty($visitorGraphData)): ?>
        // Initialize Visitors Chart
        if (document.getElementById('visitorsChart')) {
            const visitorGraphLabels = <?= json_encode($visitorGraphLabels) ?>;
            const visitorGraphData = <?= json_encode($visitorGraphData) ?>;
            const visitorsCtx = document.getElementById('visitorsChart').getContext('2d');
            new Chart(visitorsCtx, {
                type: 'line',
                data: {
                    labels: visitorGraphLabels,
                    datasets: [{
                        label: 'Daily Visitors',
                        data: visitorGraphData,
                        backgroundColor: 'rgba(0, 123, 255, 0.5)',
                        borderColor: 'rgba(0, 123, 255, 1)',
                        borderWidth: 1,
                        fill: true,
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: { beginAtZero: true, ticks: { stepSize: 1 } },
                        x: {}
                    },
                    plugins: {
                        legend: { display: true },
                        title: { display: false }
                    }
                }
            });
        }
        <?php endif; ?>

        <?php if (!empty($graphLabels) && !empty($graphData)): // Job posts chart data check ?>
        // Initialize Job Posts Chart
        if (document.getElementById('jobPostsChart')) {
            const jobGraphLabels = <?= json_encode($graphLabels) ?>;
            const jobGraphData = <?= json_encode($graphData) ?>;
            const jobPostsCtx = document.getElementById('jobPostsChart').getContext('2d');
            new Chart(jobPostsCtx, {
                type: 'bar',
                data: {
                    labels: jobGraphLabels,
                    datasets: [{
                        label: 'Daily Job Posts',
                        data: jobGraphData,
                        backgroundColor: 'rgba(40, 167, 69, 0.7)',
                        borderColor: 'rgba(40, 167, 69, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: { beginAtZero: true, ticks: { stepSize: 1 } },
                        x: {} // Optional: configure x-axis if needed
                    },
                    plugins: {
                        legend: { display: true }, // Show dataset label
                        title: {
                            display: false, // Title is in the h3 above
                            text: 'Daily Job Posts Last 30 Days'
                        }
                    }
                }
            });
        }
        <?php endif; ?>
    });
</script>
<?php endif; // End $shouldLoadChartJsForOverview ?>

<style>
    /* Add or ensure these styles are in your admin_styles.css or embedded here */
    .dashboard-main-title { /* Styles for the main H2 title of the overview page */
        margin-top: 0;
        margin-bottom: 25px; /* Consistent with h2 global style */
        color: var(--primary-color); /* Use theme primary color */
        font-size: 1.75em; /* Slightly larger for main page title */
        font-weight: 600;
        padding-bottom: 15px; /* Consistent padding */
        border-bottom: 2px solid var(--primary-color-lighter); /* Consistent border */
    }
    .user-specific-stats-note {
        font-style: italic;
        color: #555;
        margin-bottom: 15px;
        font-size: 0.9em;
    }
    
    .dashboard-section {
        background-color: var(--card-bg); 
        padding: 20px; 
        border: 1px solid var(--border-color); 
        border-radius: var(--border-radius); 
        box-shadow: 0 2px 4px rgba(0,0,0,0.05); /* Subtle shadow for depth */
        margin-bottom: 20px;
    }
    .dashboard-section h4 {
        margin-top: 0;
        margin-bottom: 10px;
        color: var(--text-color-light); /* Use a lighter text color for section headers */
        font-size: 1.1rem; /* Consistent font size for section titles */
        font-weight: 500; 
        padding-bottom: 10px; /* Add padding below title */
        border-bottom: 1px solid #f0f0f0; /* Light border for separation */
        margin-bottom: 15px; /* Space between title and list */
    }
    .compact-list, .activity-list, .info-list {
        list-style: none; /* Remove default list styling */
        padding-left: 0;
    }
    .compact-list li, .activity-list li, .info-list li {
        padding: 5px 0;
        border-bottom: 1px dashed var(--border-color); /* Use theme border color */
        font-size: 0.95rem; /* Slightly larger for readability */
    }
    .activity-list li {
        padding: 10px 5px; /* Increased vertical padding, small horizontal */
        border-bottom: 1px solid #f0f0f0; /* Lighter, solid border */
        line-height: 1.4; /* Improved line spacing */
        transition: background-color 0.2s ease-in-out; /* Smooth hover transition */
    }
    .activity-list li:hover {
        background-color: var(--body-bg); /* Subtle hover background using theme color */
    }
    .compact-list li:last-child, .activity-list li:last-child, .info-list li:last-child {
        border-bottom: none;
    }
    .activity-list small {
        display: block;
        color: #555; /* Slightly darker for better contrast */
        font-size: 0.8em; /* Standard small text */
        margin-top: 4px; /* Space below the main text line */
    }
    .dashboard-columns {
        display: flex;
        gap: 25px; /* Increased gap */
        flex-wrap: wrap;
        margin-top: 20px; /* Consistent space above these columns */
    }
    .dashboard-column {
        flex: 1;
        min-width: 300px; /* Minimum width before wrapping */
    }
    .activity-list li strong {
        color: var(--text-color); /* Use theme text color */
        font-weight: 500; /* Ensure good emphasis */
    }
    .user-performance-section h4, .leaderboard-section h4, .most-viewed-jobs-section h4, .most-shared-jobs-section h4, .server-info-section h4, .server-monitoring-charts h4 {
        margin-bottom: 15px;
        border-bottom: 1px solid #eee;
        padding-bottom: 10px; /* Increased padding */
        margin-top: 0;
    }
    .most-viewed-jobs-table-section{
            margin-top: 0;
    }

    .performance-columns {
        display: flex;
        gap: 15px; /* Slightly reduced gap within performance columns */
        flex-wrap: wrap; 
    }
    .performance-column {
        flex: 1;
        min-width: 200px; /* Adjust as needed */
        background: var(--body-bg); /* Use theme body background */
        padding: 10px;
        border-radius: var(--border-radius);
    }
    .performance-column h5 {
        margin-top: 0;
        margin-bottom: 8px;
        font-size: 0.95rem; /* Slightly smaller for sub-sections */
        color: var(--text-color-light); /* Softer color */
        font-weight: 500;
    }
    .performance-list {
        list-style: none;
        padding-left: 0;
        font-size: 0.85rem;
    }

    .performance-list li {
        padding: 3px 0;
    }

    /* Enhanced Styles for Leaderboard-like lists (Most Viewed/Shared Jobs) */
    .leaderboard-list { 
        list-style-type: decimal;
        padding-left: 30px; /* More space for list numbers */
        margin-top: 0; /* h4 margin-bottom provides top spacing */
        margin-bottom: 0; /* Remove default bottom margin from ol */
        padding-top: 0;
    }

    .leaderboard-list li {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 5px 12px 0; /* Vertical padding, right padding for content, no left (handled by number) */
        border-bottom: 1px solid var(--border-color); /* Use theme border color */
        font-size: 0.95rem;
        transition: background-color 0.2s ease-in-out;
        line-height: 1.4;
    }
    
    .leaderboard-item-main-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-grow: 1;
        margin-right: 15px; /* Space between text group and button */
    }

    .leaderboard-list li:last-child {
        border-bottom: none;
    }

    .leaderboard-list li:hover {
        background-color: #f8f9fa; /* Light hover effect */
        /* Or use var(--body-bg) for consistency if desired */
    }

    .leaderboard-name {
        flex-grow: 1;
        margin-right: 10px; /* Space between name and count badge */
        color: var(--text-color); 
        font-weight: 500;
    }

    .leaderboard-name small {
        display: block; /* Company on a new line */
        font-size: 0.85em;
        color: var(--text-muted); /* Muted color for company */
        margin-top: 4px;
        font-weight: 400; /* Normal weight for company */
    }
    .leaderboard-count {
        font-size: 0.88em; /* Slightly smaller, clear text */
        font-weight: 600;
        color: #005fa3; /* Theme primary blue */
        background-color: #e6f0f7; /* Light blue background for the badge */
        padding: 5px 10px;
        border-radius: 16px; /* Pill shape */
        min-width: 75px; /* Min width for consistency */
        text-align: center;
        white-space: nowrap; /* Prevent "views" or "shares" from wrapping */
    }

    .leaderboard-action-button {
        padding: 6px 12px;
        font-size: 0.85em;
        color: #fff;
        background-color: var(--primary-color); /* Use theme primary color */
        border: none;
        border-radius: var(--border-radius);
        text-decoration: none;
        text-align: center;
        cursor: pointer;
        transition: background-color 0.2s ease-in-out;
        white-space: nowrap;
        flex-shrink: 0; /* Prevent button from shrinking */
    }    
    .leaderboard-action-button:hover {
        background-color: #0056b3; /* Darker shade on hover */
        color: #fff;
    }

    /* General Stats Grid and Cards */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); /* Responsive grid */
        gap: 20px;
        margin-bottom: 20px;
    }

    .stat-card {
        padding: 15px;
        background-color: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius);
        text-align: center;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    .stat-card.status-pending { background-color: var(--warning-bg); border-left: 4px solid var(--warning-color); }
    .stat-card.status-pending p { color: var(--warning-text); }
    .stat-card.status-active { background-color: var(--success-bg); border-left: 4px solid var(--success-color); }
    .stat-card.status-active p { color: var(--success-text); }
    .stat-card.status-disabled { background-color: var(--error-bg); border-left: 4px solid var(--error-color); } /* Using error for disabled for strong visual cue */
    .stat-card.status-disabled p { color: var(--error-text); }

    .stat-card h4 {
        margin-top: 0;
        margin-bottom: 10px;
        font-size: 1rem; 
        color: var(--text-muted); /* Use muted text for stat card titles */
    }

    .stat-card p {
        font-size: 1.8rem; /* Larger font for the number */
        font-weight: bold;
        color: var(--text-color); /* Use standard text color for numbers, status cards have colored text */
        margin: 0;
    }

    .chart-container {
        margin-top: 20px;
        padding: 15px;
        background-color: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: 6px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    /* Specific style for chart containers within the server monitoring section */
    .server-monitoring-charts .chart-container {
        margin-top: 0; /* Remove top margin if already in a section */
    }

    .chart-container h3 {
        margin-top: 0;
        margin-bottom: 15px;
        font-size: 1.2em; /* Consistent with other sub-section titles */
        color: var(--text-color-light);
        border-bottom: 1px solid var(--border-color);
        padding-bottom: 10px;
        font-weight: 500;
    }

    /* Ensure canvas has a defined height if maintainAspectRatio is false */
    #visitorsChart, #jobPostsChart, #serverCpuChart, #serverMemoryChart {
        height: 280px !important; /* Slightly reduced default chart height */
        width: 100% !important;
    }
    .no-data-message {
        color: #777;
        font-style: italic;
        padding: 10px 0;
    }
</style>
