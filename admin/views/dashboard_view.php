<?php

// admin/views/dashboard_view.php - Displays the main dashboard content

// This file is included by dashboard.php or fetch_content.php when the view is 'dashboard'.
// It assumes variables like $loggedInUserRole, $allJobs, $feedbackMessages, $users,
// $jobsTodayCount, $jobsMonthlyCount, $graphLabels, $graphData (for jobs),
// $totalViews, $monthlyVisitors, $visitorGraphLabels, $visitorGraphData (for visitors),
// and potentially $filteredUsersCountForUGM are available.

// Ensure necessary variables are initialized if they weren't set by fetch_content.php (fallback)
$loggedInUserRole = $loggedInUserRole ?? $_SESSION['admin_role'] ?? 'user';
$allJobs = $allJobs ?? []; // This will be the full list for admins, filtered for others if done in fetch_content
$feedbackMessages = $feedbackMessages ?? [];
$users = $users ?? []; // Full list of users
$jobsTodayCount = $jobsTodayCount ?? 0; // Potentially filtered by user
$jobsMonthlyCount = $jobsMonthlyCount ?? 0; // Potentially filtered by user
$graphLabels = $graphLabels ?? []; // For job posts chart, potentially filtered by user
$graphData = $graphData ?? []; // For job posts chart, potentially filtered by user

// Visitor stats - only relevant for admin/super_admin
$totalViews = $totalViews ?? 0;
$monthlyVisitors = $monthlyVisitors ?? 0;
$visitorGraphLabels = $visitorGraphLabels ?? [];
$visitorGraphData = $visitorGraphData ?? [];

// New data from fetch_content.php
$userCountsByRole = $userCountsByRole ?? [];
$userCountsByStatus = $userCountsByStatus ?? [];
$recentJobs = $recentJobs ?? [];
$recentMessages = $recentMessages ?? [];
$recentPendingUsers = $recentPendingUsers ?? [];

$userPerformanceOverall = $userPerformanceOverall ?? [];
$userPerformanceLast30Days = $userPerformanceLast30Days ?? [];
$userPerformanceToday = $userPerformanceToday ?? [];
$performanceLeaderboard = $performanceLeaderboard ?? [];
$mostViewedJobs = $mostViewedJobs ?? [];


// Counts for display
// Global counts for admin/super_admin
$displayTotalJobsCount = count($allJobs); // Global count
$displayTotalMessagesCount = count($feedbackMessages); // Global count
$displayTotalUsersCount = count($users); // Global count
$loggedInUserObject = findUserByUsername($_SESSION['admin_username'] ?? '', $usersFilename);
// For user_group_manager, the 'Total Users' card shows a filtered count
if ($loggedInUserRole === 'user_group_manager') {
    $displayTotalUsersCount = $filteredUsersCountForUGM ?? 0; // Use the pre-calculated filtered count
}

// For regular 'user', their job-specific stats ($jobsTodayCount, $jobsMonthlyCount) are already filtered.
// They won't see global total jobs, total messages, or total users cards.

?>

<div class="dashboard-content">
    <h3>Dashboard Overview</h3>
    <?php
    // Add a note if the job stats are specific to the logged-in user
    if (!in_array($loggedInUserRole, ['super_admin', 'admin'])) {
        echo "<p class='user-specific-stats-note'><em>Displaying job statistics for posts made by you.</em></p>";
    }
    ?>

    <div class="stats-grid">
        <?php if ($loggedInUserRole === 'super_admin' || in_array($loggedInUserRole, $allRegionalAdminRoles)): ?>
            <div class="stat-card">
                <h4>Total Jobs (All)</h4>
                <p><?= htmlspecialchars($displayTotalJobsCount) ?></p>
            </div>
        <?php endif; ?>

        <div class="stat-card">
            <h4>Jobs Posted Today <?php if (!($loggedInUserRole === 'super_admin' || in_array($loggedInUserRole, $allRegionalAdminRoles))) echo "(By You)"; ?></h4>
            <p><?= htmlspecialchars($jobsTodayCount) ?></p>
        </div>

        <div class="stat-card">
            <h4>Jobs Posted This Month <?php if (!($loggedInUserRole === 'super_admin' || in_array($loggedInUserRole, $allRegionalAdminRoles))) echo "(By You)"; ?></h4>
            <p><?= htmlspecialchars($jobsMonthlyCount) ?></p>
        </div>

        <?php if ($loggedInUserRole === 'super_admin' || in_array($loggedInUserRole, $allRegionalAdminRoles)): ?>
            <div class="stat-card">
                <h4>Total Messages</h4>
                <p><?= htmlspecialchars($displayTotalMessagesCount) ?></p>
            </div>
        <?php endif; ?>

        <?php if ($loggedInUserRole === 'super_admin' || in_array($loggedInUserRole, $allRegionalAdminRoles) || in_array($loggedInUserRole, $allRegionalManagerRoles)): ?>
            <div class="stat-card">
                <h4>Total Users <?php
                                if ($loggedInUserRole === 'super_admin') echo "(All)";
                                elseif (in_array($loggedInUserRole, $allRegionalAdminRoles) || in_array($loggedInUserRole, $allRegionalManagerRoles)) echo "(Managed by You/Team)";
                               ?>
                </h4>
                <p><?= htmlspecialchars($displayTotalUsersCount) ?></p>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($loggedInUserRole === 'super_admin' || in_array($loggedInUserRole, $allRegionalAdminRoles)): ?>
    <div class="stats-grid">
        <?php if (!empty($userCountsByStatus['pending_approval'])): ?>
        <div class="stat-card status-pending">
            <h4>Users Pending Approval</h4>
            <p><?= htmlspecialchars($userCountsByStatus['pending_approval']) ?></p>
        </div>
        <?php endif; ?>
        <?php if (!empty($userCountsByStatus['active'])): ?>
        <div class="stat-card status-active">
            <h4>Active Users</h4>
            <p><?= htmlspecialchars($userCountsByStatus['active']) ?></p>
        </div>
        <?php endif; ?>
        <?php if (!empty($userCountsByStatus['disabled'])): ?>
        <div class="stat-card status-disabled">
            <h4>Disabled Users</h4>
            <p><?= htmlspecialchars($userCountsByStatus['disabled']) ?></p>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>


    <?php if ($loggedInUserRole === 'super_admin' || in_array($loggedInUserRole, $allRegionalAdminRoles)): ?>
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

        <div class="chart-container">
            <h3>Daily Visitors Last 30 Days</h3>
            <canvas id="visitorsChart"></canvas>
        </div>
    <?php endif; // End visitor stats section ?>


    <div class="chart-container">
        <h3>Daily Job Posts Last 30 Days <?php if (!($loggedInUserRole === 'super_admin' || in_array($loggedInUserRole, $allRegionalAdminRoles))) echo "(By You)"; ?></h3>
        <canvas id="jobPostsChart"></canvas>
    </div>

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

        <?php if ($loggedInUserRole === 'super_admin' || in_array($loggedInUserRole, $allRegionalAdminRoles)): ?>
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
                            <li><?= htmlspecialchars($user['display_name'] ?? 'N/A') ?> (<?= htmlspecialchars($user['username'] ?? 'N/A') ?>) - Role: <?= htmlspecialchars(ucwords(str_replace('_',' ',$user['role'] ?? 'N/A'))) ?></li>
                        <?php endforeach; ?>
                    </ul>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <?php if (($loggedInUserRole === 'super_admin' || in_array($loggedInUserRole, $allRegionalAdminRoles))): ?>
    <div class="dashboard-section most-viewed-jobs-section">
        <h4>Most Viewed Jobs (Card Expands)</h4>
        <?php if (!empty($mostViewedJobs)): ?>
            <ol class="leaderboard-list">
                <?php foreach ($mostViewedJobs as $job): ?>
                    <li>
                        <span class="leaderboard-name">
                            <?= htmlspecialchars($job['title']) ?>
                            <?php if(!empty($job['company'])): ?>
                                <small style="color: #555;">at <?= htmlspecialchars($job['company']) ?></small>
                            <?php endif; ?>
                        </span>
                        <span class="leaderboard-count"><?= htmlspecialchars($job['views']) ?> views</span>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php else: ?>
            <p>No job view data available yet.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($loggedInUserRole === 'super_admin' || in_array($loggedInUserRole, $allRegionalAdminRoles)): ?>
    <div class="dashboard-section user-performance-section">
        <h4>User Job Posting Performance</h4>
        <div class="performance-columns">
            <div class="performance-column">
                <h5>Today's Posts</h5>
                <?php if (!empty($userPerformanceToday)): ?>
                    <ul class="performance-list">
                        <?php $usersWithPostsToday = array_filter($userPerformanceToday, function($data){ return $data['count'] > 0; }); ?>
                        <?php if (!empty($usersWithPostsToday)): ?>
                            <?php foreach ($usersWithPostsToday as $username => $data): ?>
                                <li><?= htmlspecialchars($data['name']) ?>: <strong><?= htmlspecialchars($data['count']) ?></strong></li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li>No jobs posted today.</li>
                        <?php endif; ?>
                    </ul>
                <?php else: ?>
                    <p>No data.</p>
                <?php endif; ?>
            </div>
            <div class="performance-column">
                <h5>Last 30 Days</h5>
                <?php if (!empty($userPerformanceLast30Days)): ?>
                    <ul class="performance-list">
                         <?php $usersWithPosts30Days = array_filter($userPerformanceLast30Days, function($data){ return $data['count'] > 0; }); ?>
                        <?php if (!empty($usersWithPosts30Days)): ?>
                            <?php foreach ($usersWithPosts30Days as $username => $data): ?>
                                <li><?= htmlspecialchars($data['name']) ?>: <strong><?= htmlspecialchars($data['count']) ?></strong></li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li>No jobs posted in the last 30 days.</li>
                        <?php endif; ?>
                    </ul>
                <?php else: ?>
                    <p>No data.</p>
                <?php endif; ?>
            </div>
            <div class="performance-column">
                <h5>Overall Posts</h5>
                 <?php if (!empty($userPerformanceOverall)): ?>
                    <ul class="performance-list">
                        <?php $usersWithPostsOverall = array_filter($userPerformanceOverall, function($data){ return $data['count'] > 0; }); ?>
                        <?php if (!empty($usersWithPostsOverall)): ?>
                            <?php foreach ($usersWithPostsOverall as $username => $data): ?>
                                <li><?= htmlspecialchars($data['name']) ?>: <strong><?= htmlspecialchars($data['count']) ?></strong></li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li>No jobs posted overall.</li>
                        <?php endif; ?>
                    </ul>
                <?php else: ?>
                    <p>No data.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="dashboard-section leaderboard-section">
        <h4>Top Posters (Last 30 Days)</h4>
        <?php if (!empty($performanceLeaderboard)): ?>
            <ol class="leaderboard-list">
                <?php foreach ($performanceLeaderboard as $username => $data): ?>
                    <?php if ($data['count'] > 0): // Only show users who posted at least one job ?>
                        <li>
                            <span class="leaderboard-name"><?= htmlspecialchars($data['name']) ?></span>
                            <span class="leaderboard-count"><?= htmlspecialchars($data['count']) ?> jobs</span>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ol>
        <?php else: ?>
            <p>No posting activity in the last 30 days to rank.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php if ($loggedInUserRole === 'super_admin' || in_array($loggedInUserRole, $allRegionalAdminRoles)): // Include visitor chart script only if stats are shown ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Data for the visitors chart (prepared in fetch_content.php)
    const visitorGraphLabels = <?= json_encode($visitorGraphLabels) ?>;
    const visitorGraphData = <?= json_encode($visitorGraphData) ?>;

    const visitorsCtx = document.getElementById('visitorsChart').getContext('2d');
    new Chart(visitorsCtx, {
        type: 'line', // or 'bar'
        data: {
            labels: visitorGraphLabels,
            datasets: [{
                label: 'Daily Visitors',
                data: visitorGraphData,
                backgroundColor: 'rgba(0, 123, 255, 0.5)', // Blue with transparency
                borderColor: 'rgba(0, 123, 255, 1)', // Solid blue
                borderWidth: 1,
                fill: true, // Fill area under the line
                tension: 0.1 // Slight curve
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false, // Allow chart to resize freely
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1 // Ensure whole numbers for counts
                    }
                },
                x: {
                    // Optional: configure x-axis if needed
                }
            },
            plugins: {
                legend: {
                    display: true // Show dataset label
                },
                title: {
                    display: false, // Title is in the h3 above
                    text: 'Daily Visitors Last 30 Days'
                }
            }
        }
    });
</script>
<?php endif; // End visitor chart script ?>

<script>
    // Data for the job posts chart (prepared in fetch_content.php)
    // This script block should always be present as all roles see the job posts chart
    // If Chart.js is only loaded conditionally above, ensure it's loaded if this chart is shown.
    // For simplicity, assuming Chart.js is loaded if any chart is shown.
    // If not, you might need to load Chart.js here if it wasn't loaded for the visitor chart.
    if (typeof Chart === 'undefined' && (document.getElementById('jobPostsChart'))) {
        const chartJsScript = document.createElement('script');
        chartJsScript.src = 'https://cdn.jsdelivr.net/npm/chart.js';
        document.head.appendChild(chartJsScript);
        chartJsScript.onload = function() {
            initializeJobPostsChart();
        };
    } else if (document.getElementById('jobPostsChart')) {
        initializeJobPostsChart();
    }

    function initializeJobPostsChart() {
        const jobGraphLabels = <?= json_encode($graphLabels) ?>;
        const jobGraphData = <?= json_encode($graphData) ?>;

        const jobPostsCtx = document.getElementById('jobPostsChart').getContext('2d');
        new Chart(jobPostsCtx, {
            type: 'bar', // Bar chart for job posts
            data: {
                labels: jobGraphLabels,
                datasets: [{
                    label: 'Daily Job Posts',
                    data: jobGraphData,
                    backgroundColor: 'rgba(40, 167, 69, 0.7)', // Green with transparency
                    borderColor: 'rgba(40, 167, 69, 1)', // Solid green
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false, // Allow chart to resize freely
                scales: {
                    y: {
                        beginAtZero: true,
                         ticks: {
                            stepSize: 1 // Ensure whole numbers for counts
                        }
                    },
                     x: {
                        // Optional: configure x-axis if needed
                    }
                },
                 plugins: {
                    legend: {
                        display: true // Show dataset label
                    },
                    title: {
                        display: false, // Title is in the h3 above
                        text: 'Daily Job Posts Last 30 Days'
                    }
                }
            }
        });
    }
</script>

<style>
    /* Add or ensure these styles are in your admin_styles.css or embedded here */
    .dashboard-content h3 {
        margin-top: 0;
        margin-bottom: 20px;
        color: #005fa3;
    }

    .user-specific-stats-note {
        font-style: italic;
        color: #555;
        margin-bottom: 15px;
        font-size: 0.9em;
    }
    
    .dashboard-section {
        background-color: #fdfdfd;
        padding: 15px;
        border: 1px solid #e7e7e7;
        border-radius: 5px;
        margin-bottom: 20px;
    }
    .dashboard-section h4 {
        margin-top: 0;
        margin-bottom: 10px;
        color: #005fa3;
        font-size: 1.1rem;
    }
    .compact-list, .activity-list {
        list-style: none;
        padding-left: 0;
    }
    .compact-list li, .activity-list li {
        padding: 5px 0;
        border-bottom: 1px dashed #eee;
        font-size: 0.9rem;
    }
    .compact-list li:last-child, .activity-list li:last-child {
        border-bottom: none;
    }
    .activity-list small {
        display: block;
        color: #777;
        font-size: 0.8em;
    }
    .dashboard-columns {
        display: flex;
        gap: 20px;
        flex-wrap: wrap; /* Allow wrapping on smaller screens */
    }
    .dashboard-column {
        flex: 1;
        min-width: 300px; /* Minimum width before wrapping */
    }
    .user-performance-section h4, .leaderboard-section h4, .most-viewed-jobs-section h4 {
        margin-bottom: 15px;
        border-bottom: 1px solid #eee;
        padding-bottom: 8px;
    }
    .performance-columns {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
    }
    .performance-column {
        flex: 1;
        min-width: 200px; /* Adjust as needed */
        background: #f9f9f9;
        padding: 10px;
        border-radius: 4px;
    }
    .performance-column h5 {
        margin-top: 0;
        margin-bottom: 8px;
        font-size: 1rem;
        color: #333;
    }
    .performance-list {
        list-style: none;
        padding-left: 0;
        font-size: 0.85rem;
    }
    .performance-list li {
        padding: 3px 0;
    }
    .leaderboard-list { padding-left: 20px; }
    .leaderboard-list li { margin-bottom: 5px; font-size: 0.9rem; }
    .leaderboard-name { font-weight: bold; }
    .leaderboard-count { float: right; color: #007bff; }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); /* Responsive grid */
        gap: 20px;
        margin-bottom: 20px;
    }

    .stat-card {
        padding: 15px;
        background-color: #f9f9f9;
        border: 1px solid #ddd;
        border-radius: 5px;
        text-align: center;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    .stat-card.status-pending { background-color: #fff8e1; border-left: 5px solid #ffc107; }
    .stat-card.status-pending p { color: #c77c00; }
    .stat-card.status-active { background-color: #e8f5e9; border-left: 5px solid #4caf50; }
    .stat-card.status-active p { color: #388e3c; }
    .stat-card.status-disabled { background-color: #fce4ec; border-left: 5px solid #e91e63; }
    .stat-card.status-disabled p { color: #ad1457; }

    .stat-card h4 {
    }

    .stat-card h4 {
        margin-top: 0;
        margin-bottom: 10px;
        font-size: 1rem; /* Slightly smaller font */
        color: #555;
    }

    .stat-card p {
        font-size: 1.8rem; /* Larger font for the number */
        font-weight: bold;
        color: #007bff; /* Primary color */
        margin: 0;
    }

    .chart-container {
        margin-top: 20px;
        padding: 15px;
        background-color: #f9f9f9;
        border: 1px solid #ddd;
        border-radius: 5px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }

    .chart-container h3 {
        margin-top: 0;
        margin-bottom: 15px;
        font-size: 1.2rem; /* Smaller title for charts */
        color: #333;
        border-bottom: 1px solid #eee;
        padding-bottom: 10px;
    }

    /* Ensure canvas has a defined height if maintainAspectRatio is false */
    #visitorsChart, #jobPostsChart {
        height: 300px; /* Example height */
    }

</style>
