<?php
// admin/views/dashboard_user_info_view.php

// Assumes $userCountsByRole and $userCountsByStatus are available from fetch_content.php
$userCountsByRole = $userCountsByRole ?? [];
$userCountsByStatus = $userCountsByStatus ?? [];
// Assumes performance data is available from fetch_content.php for this view
$userPerformanceOverall = $userPerformanceOverall ?? [];
$userPerformanceLast30Days = $userPerformanceLast30Days ?? [];
$userPerformanceToday = $userPerformanceToday ?? [];
// $userPerformanceList is no longer used in this view.
$performanceLeaderboard = $performanceLeaderboard ?? [];

$loggedInUserRole = $_SESSION['admin_role'] ?? 'user'; // Needed for conditional display
$allRegionalAdminRoles = $allRegionalAdminRoles ?? []; // Expected from fetch_content.php

// Data for charts (prepared in fetch_content.php)
// Ensure these are initialized even if empty
$userRoleChartLabels = $userRoleChartLabels ?? [];
$userRoleChartData = $userRoleChartData ?? [];
$userStatusChartLabels = $userStatusChartLabels ?? [];
$userStatusChartData = $userStatusChartData ?? [];
// $userAchievementsChartLabels and $userAchievementsChartData are no longer used for this view.
// Variables for "Overall User Job Posting Performance Chart" are no longer used.

// For NEW Quarterly User Earnings Chart
$quarterlyUserEarningsLabels = $quarterlyUserEarningsLabels ?? [];
$quarterlyUserEarningsData = $quarterlyUserEarningsData ?? [];

$shouldLoadChartJsForUserInfo = !empty($userRoleChartData) || !empty($userStatusChartData) || !empty($topPostersChartData) || !empty($quarterlyUserEarningsData);
?>
<div class="dashboard-content user-info-view-content">
    <h3>User Statistics Overview</h3>

    <div class="dashboard-columns stats-summary-columns">
        <div class="dashboard-column">
            <div class="dashboard-section user-role-section">
                <h4>Users by Role</h4>
                <?php if (!empty($userCountsByRole)): ?>
                    <ul class="info-list compact-list user-stats-list">
                        <?php foreach ($userCountsByRole as $role => $count): ?>
                            <li><strong><?= htmlspecialchars(ucwords(str_replace('_', ' ', $role))) ?></strong> <span><?= htmlspecialchars($count) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if (!empty($userRoleChartLabels) && !empty($userRoleChartData)): ?>
                    <div class="chart-container compact-chart-container">
                        <canvas id="userRoleChart"></canvas>
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="no-data-message">No user role data available.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="dashboard-column">
            <div class="dashboard-section user-status-section">
                <h4>Users by Status</h4>
                <?php if (!empty($userCountsByStatus)): ?>
                    <ul class="info-list compact-list user-stats-list">
                        <?php foreach ($userCountsByStatus as $status => $count): ?>
                            <li><strong><?= htmlspecialchars(ucwords(str_replace('_', ' ', $status))) ?></strong> <span><?= htmlspecialchars($count) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                     <?php if (!empty($userStatusChartLabels) && !empty($userStatusChartData)): ?>
                    <div class="chart-container compact-chart-container">
                        <canvas id="userStatusChart"></canvas>
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="no-data-message">No user status data available.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($loggedInUserRole === 'super_admin' || in_array($loggedInUserRole, $allRegionalAdminRoles)): ?>
    <!-- "User Job Posting Performance (Jobs Posted)" list removed -->
    <!-- "Overall Job Posting Performance by User" chart removed -->
    <?php endif; ?>

    <div class="dashboard-section leaderboard-section">
        <h4>Top Posters (Last 30 Days)</h4>
        <?php if (!empty($performanceLeaderboard)): ?>
            <div class="dashboard-columns top-posters-layout">
                <div class="dashboard-column leaderboard-list-container">
                    <ol class="leaderboard-list">
                        <?php $hasPosters = false; ?>
                        <?php foreach ($performanceLeaderboard as $username => $data): ?>
                            <?php if ($data['count'] > 0): ?>
                                <?php $hasPosters = true; ?>
                                <li title="Username: <?= htmlspecialchars($username) ?>">
                                    <span class="leaderboard-name"><?= htmlspecialchars($data['name']) ?></span>
                                    <span class="leaderboard-count"><?= htmlspecialchars($data['count']) ?> jobs</span>
                                </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php if (!$hasPosters): ?>
                            <p class="no-data-message">No users with posts in the last 30 days.</p>
                        <?php endif; ?>
                    </ol>
                </div>
                <?php if (!empty($topPostersChartLabels) && !empty($topPostersChartData) && $hasPosters): ?>
                <div class="dashboard-column leaderboard-chart-container">
                    <div class="chart-container">
                        <canvas id="topPostersChart"></canvas>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p class="no-data-message">No posting activity in the last 30 days to rank.</p>
        <?php endif; ?>
    </div>

    <?php if ($loggedInUserRole === 'super_admin' || in_array($loggedInUserRole, $allRegionalAdminRoles)): ?>
    <div class="dashboard-section quarterly-user-earnings-section">
        <h4>User Earnings (Last 3 Months)</h4>
        <?php if (!empty($quarterlyUserEarningsData) && !empty($quarterlyUserEarningsLabels)): ?>
            <div class="chart-container" style="height: 400px;"> <!-- Adjust height as needed -->
                <canvas id="quarterlyUserEarningsChart"></canvas>
            </div>
        <?php else: ?>
            <p class="no-data-message">No user earnings data available for the last 3 months to display a chart.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>

<style>
    /* Styles for User Info Tab - can be moved to a central admin_styles.css */
    .user-info-view-content .dashboard-content h3, /* Tab Title */
    .user-info-view-content .dashboard-section h4, /* Section Titles */
    .user-info-view-content .performance-column h5 /* Sub-section Titles */
    {
        color: #005fa3; /* Consistent heading color */
        margin-top: 0;
    }
    .user-info-view-content .dashboard-content h3 {
        margin-bottom: 25px;
        padding-bottom: 10px;
        border-bottom: 1px solid #e0e0e0;
    }
    .user-info-view-content .dashboard-section h4 {
        margin-bottom: 15px;
        font-size: 1.1em;
    }
     .user-info-view-content .performance-column h5 {
        margin-bottom: 10px;
        font-size: 1em;
        padding-bottom: 5px;
        border-bottom: 1px dashed #eee;
    }
    .user-performance-horizontal-list {
        list-style: none;
        padding-left: 0;
        margin-top: 10px;
    }
    .user-performance-horizontal-list li {
        display: flex;
        justify-content: space-between;
        padding: 10px 5px;
        border-bottom: 1px solid #f0f0f0;
        font-size: 0.9rem;
    }
    .user-performance-horizontal-list li:last-child {
        border-bottom: none;
    }
    .user-performance-horizontal-list li.header-row {
        font-weight: bold;
        background-color: #f8f9fa;
        border-bottom: 2px solid #e0e0e0;
        padding: 12px 5px;
    }
    .user-performance-horizontal-list .user-name-col { flex: 2; text-align: left; padding-right: 10px; }
    .user-performance-horizontal-list .perf-col { flex: 1; text-align: center; min-width: 80px; }
    .user-performance-horizontal-list li.no-data-message-row p { width: 100%; text-align: center; }

    .dashboard-columns {
        display: flex;
        gap: 25px; /* Increased gap */
        flex-wrap: wrap;
        margin-bottom: 25px; /* Increased bottom margin */
    }
    .dashboard-column {
        flex: 1;
        min-width: 320px; /* Adjusted min-width */
    }

    .user-info-view-content .dashboard-section {
        background-color: #ffffff;
        padding: 20px;
        border-radius: 6px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        height: 100%; /* Make sections in columns equal height if desired */
        display: flex;
        flex-direction: column;
    }
    
    .user-stats-list {
        list-style: none;
        padding-left: 0;
        margin-bottom: 15px; /* Add margin below the list if chart follows */
    }
    .user-stats-list li {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 0; /* Increased padding */
        border-bottom: 1px solid #f0f0f0;
        font-size: 0.9rem;
    }
    .user-stats-list li:last-child {
        border-bottom: none;
    }
    .user-stats-list li strong {
        color: #333;
    }
    .user-stats-list li span {
        color: #005fa3;
        font-weight: bold;
    }

    .compact-chart-container {
        height: 220px; /* Adjusted height for pie/doughnut */
        margin-top: auto; /* Pushes chart to bottom if list is short */
        padding-top: 15px;
    }
    .user-info-view-content .chart-container { /* General chart container styling */
        padding: 0; /* Remove padding if section already has it */
        background-color: transparent; /* Make it transparent if section has bg */
        box-shadow: none;
        border: none;
    }


    .user-info-view-content .performance-list {
        list-style: none;
        padding-left: 0;
        font-size: 0.9rem;
    }
    .user-info-view-content .performance-list li {
        padding: 6px 0;
        color: #555;
    }
    .user-info-view-content .performance-list li strong {
        color: #005fa3;
    }
    
    .user-info-view-content .leaderboard-list {
        list-style-type: decimal;
        padding-left: 25px;
        margin-top: 0;
    }
    .user-info-view-content .leaderboard-list li {
        padding: 6px 0;
        font-size: 0.9rem;
        display: flex;
        justify-content: space-between;
    }
    .user-info-view-content .leaderboard-name {
        color: #333;
        font-weight: 500;
    }
    .user-info-view-content .leaderboard-count {
        color: #005fa3;
        font-weight: bold;
        margin-left: 10px;
    }
    .top-posters-layout .leaderboard-list-container {
        flex-basis: 45%; /* Adjust as needed */
        min-width: 280px;
    }
    .top-posters-layout .leaderboard-chart-container {
        flex-basis: 50%; /* Adjust as needed */
        min-width: 300px;
    }
    .top-posters-layout .chart-container {
        height: 280px; /* Adjusted height for bar chart */
    }

    .no-data-message {
        color: #777;
        font-style: italic;
        padding: 10px 0;
    }
    /* Ensure global .dashboard-section styles don't conflict too much, or override here */
</style>

<?php if ($shouldLoadChartJsForUserInfo): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const pieChartColors = [
        '#007bff', '#28a745', '#ffc107', '#dc3545', 
        '#17a2b8', '#6f42c1', '#fd7e14', '#6c757d'
    ].map(hex => hex + 'B3'); // Add alpha for 0.7 opacity

    const barChartColors = [
        'rgba(75, 192, 192, 0.7)', 'rgba(255, 159, 64, 0.7)',
        'rgba(255, 99, 132, 0.7)', 'rgba(54, 162, 235, 0.7)',
        'rgba(153, 102, 255, 0.7)'
    ];

    <?php if (!empty($userRoleChartLabels) && !empty($userRoleChartData)): ?>
    if (document.getElementById('userRoleChart')) {
        const userRoleCtx = document.getElementById('userRoleChart').getContext('2d');
        new Chart(userRoleCtx, {
            type: 'pie',
            data: {
                labels: <?= json_encode(array_map(function($role){ return ucwords(str_replace('_', ' ', $role)); }, $userRoleChartLabels)) ?>,
                datasets: [{
                    label: 'Users by Role',
                    data: <?= json_encode($userRoleChartData) ?>,
                    backgroundColor: pieChartColors,
                    borderColor: pieChartColors.map(color => color.substring(0,7)), // Solid border
                    borderWidth: 1,
                    hoverOffset: 8
                }]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false, 
                plugins: { 
                    legend: { position: 'right', labels: { boxWidth: 12, padding: 15, font: {size: 10} } },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed !== null) {
                                    label += context.parsed;
                                }
                                return label;
                            }
                        }
                    }
                } 
            }
        });
    }
    <?php endif; ?>

    <?php if (!empty($userStatusChartLabels) && !empty($userStatusChartData)): ?>
    if (document.getElementById('userStatusChart')) {
        const userStatusCtx = document.getElementById('userStatusChart').getContext('2d');
        new Chart(userStatusCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_map(function($status){ return ucwords(str_replace('_', ' ', $status)); }, $userStatusChartLabels)) ?>,
                datasets: [{
                    label: 'Users by Status',
                    data: <?= json_encode($userStatusChartData) ?>,
                    backgroundColor: pieChartColors.slice(3).concat(pieChartColors.slice(0,3)), // Rotate colors
                    borderColor: pieChartColors.slice(3).concat(pieChartColors.slice(0,3)).map(color => color.substring(0,7)),
                    borderWidth: 1,
                    hoverOffset: 8
                }]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false, 
                plugins: { 
                    legend: { position: 'right', labels: { boxWidth: 12, padding: 15, font: {size: 10} } },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed !== null) {
                                    label += context.parsed;
                                }
                                return label;
                            }
                        }
                    }
                } 
            }
        });
    }
    <?php endif; ?>

    <?php if (!empty($topPostersChartLabels) && !empty($topPostersChartData)): ?>
    if (document.getElementById('topPostersChart')) {
        const topPostersCtx = document.getElementById('topPostersChart').getContext('2d');
        new Chart(topPostersCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($topPostersChartLabels) ?>,
                datasets: [{
                    label: 'Job Posts (Last 30 Days)',
                    data: <?= json_encode($topPostersChartData) ?>,
                    backgroundColor: barChartColors,
                    borderColor: barChartColors.map(color => color.replace('0.7', '1')),
                    borderWidth: 1,
                    borderRadius: 4,
                    barPercentage: 0.7,
                    categoryPercentage: 0.8
                }]
            },
            options: {
                indexAxis: 'y', // Horizontal bar chart
                responsive: true, maintainAspectRatio: false,
                scales: { 
                    x: { beginAtZero: true, ticks: { stepSize: 1 } },
                    y: { ticks: { font: {size: 10} } }
                },
                plugins: { 
                    legend: { display: false },
                    title: { display: true, text: 'Top 5 Posters', font: {size: 12, weight: 'normal'}, padding: {bottom: 10} }
                }
            }
        });
    }
    <?php endif; ?>

    <?php if (!empty($quarterlyUserEarningsLabels) && !empty($quarterlyUserEarningsData)): ?>
    if (document.getElementById('quarterlyUserEarningsChart')) {
        const quarterlyEarningsCtx = document.getElementById('quarterlyUserEarningsChart').getContext('2d');
        new Chart(quarterlyEarningsCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($quarterlyUserEarningsLabels) ?>,
                datasets: <?= json_encode($quarterlyUserEarningsData) ?>
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: 'Earnings (Points/Currency)' }
                    },
                    x: {
                        title: { display: true, text: 'Month' }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: { display: false } // Title is in H4
                }
            }
        });
    }
    <?php endif; ?>

});
</script>
<?php endif; ?>
