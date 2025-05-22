<?php
// admin/includes/stats_helpers.php

/**
 * Calculates job counts for today and this month.
 *
 * @param array $jobs Array of job objects/arrays.
 * @param string|null $userId Optional. If provided, counts jobs for this specific user.
 * @return array ['today' => int, 'month' => int]
 */
function calculateJobCounts(array $jobs, ?string $userId = null): array {
    $todayCount = 0;
    $monthCount = 0;
    $now = time();
    $startOfToday = strtotime('today midnight');
    $startOfMonth = strtotime('first day of this month midnight');

    foreach ($jobs as $job) {
        if ($userId && ($job['posted_by_user_id'] ?? null) !== $userId) {
            continue; // Skip if filtering by user and job doesn't match
        }

        // Prioritize unix_ts, fallback to parsing posted_on
        $postedTimestamp = $job['posted_on_unix_ts'] ?? 0;
        if (!is_numeric($postedTimestamp) || $postedTimestamp <= 0) {
            $postedTimestamp = isset($job['posted_on']) ? strtotime($job['posted_on']) : 0;
        }

        if ($postedTimestamp >= $startOfToday) {
            $todayCount++;
        }
        if ($postedTimestamp >= $startOfMonth) {
            $monthCount++;
        }
    }
    return ['today' => $todayCount, 'month' => $monthCount];
}

/**
 * Prepares data for a daily job posts chart (last 30 days).
 *
 * @param array $jobs Array of job objects/arrays.
 * @param string|null $userId Optional. If provided, prepares data for this specific user.
 * @return array ['labels' => array, 'data' => array]
 */
function prepareJobPostsChartData(array $jobs, ?string $userId = null): array {
    $jobCountsByDay = array_fill(0, 30, 0);
    $graphLabels = [];
    $now = time();

    for ($i = 29; $i >= 0; $i--) {
        $graphLabels[] = date('M d', strtotime("-$i days"));
    }

    foreach ($jobs as $job) {
        if ($userId && ($job['posted_by_user_id'] ?? null) !== $userId) {
            continue; // Skip if filtering by user
        }

        // Prioritize unix_ts, fallback to parsing posted_on
        $postedTimestamp = $job['posted_on_unix_ts'] ?? 0;
        if (!is_numeric($postedTimestamp) || $postedTimestamp <= 0) {
            $postedTimestamp = isset($job['posted_on']) ? strtotime($job['posted_on']) : 0;
        }
        if ($postedTimestamp > 0) {
            $daysAgo = floor(($now - $postedTimestamp) / (24 * 60 * 60));
            if ($daysAgo >= 0 && $daysAgo < 30) {
                $jobCountsByDay[29 - $daysAgo]++;
            }
        }
    }
    return ['labels' => $graphLabels, 'data' => $jobCountsByDay];
}

/**
 * Calculates user job posting performance.
 *
 * @param array $allJobs Array of all job objects.
 * @param array $allUsers Array of all user objects.
 * @return array [
 *     'overall' => array,
 *     'last30days' => array,
 *     'today' => array,
 *     'leaderboard' => array // Top 5 from last 30 days
 * ]
 */
function calculateUserPerformance(array $allJobs, array $allUsers): array {
    $performance = [
        'overall' => [],
        'last30days' => [],
        'today' => [],
        'leaderboard' => []
    ];

    if (empty($allUsers) || empty($allJobs)) {
        return $performance;
    }

    // Initialize performance arrays for all users
    foreach ($allUsers as $user) {
        if (!isset($user['username']) || empty(trim($user['username']))) {
            // Log or handle users without a username if necessary
            continue;
        }
        $username = $user['username'];
        $displayName = $user['display_name'] ?? $username;
        $performance['overall'][$username] = ['name' => $displayName, 'count' => 0];
        $performance['last30days'][$username] = ['name' => $displayName, 'count' => 0];
        $performance['today'][$username] = ['name' => $displayName, 'count' => 0];
    }

    $thirtyDaysAgoTimestamp = strtotime('-30 days midnight');
    $todayTimestamp = strtotime('today midnight');

    foreach ($allJobs as $job) {
        $postedBy = $job['posted_by_user_id'] ?? null;
        if ($postedBy && isset($performance['overall'][$postedBy])) {
            $performance['overall'][$postedBy]['count']++;
            
            // Prioritize unix_ts, fallback to parsing posted_on
            $jobTimestamp = $job['posted_on_unix_ts'] ?? 0;
            if (!is_numeric($jobTimestamp) || $jobTimestamp <= 0) {
                $jobTimestamp = isset($job['posted_on']) ? strtotime($job['posted_on']) : 0;
            }

            if ($jobTimestamp >= $thirtyDaysAgoTimestamp) {
                $performance['last30days'][$postedBy]['count']++;
            }
            if ($jobTimestamp >= $todayTimestamp) {
                $performance['today'][$postedBy]['count']++;
            }
        }
    }

    // Create leaderboard from last 30 days performance
    $leaderboardData = $performance['last30days'];
    uasort($leaderboardData, function ($a, $b) {
        return $b['count'] <=> $a['count'];
    });
    $performance['leaderboard'] = array_slice($leaderboardData, 0, 5, true); // Top 5

    return $performance;
}

/**
 * Prepares daily visitor chart data.
 *
 * @param array $dailyVisitorData Associative array [date => count].
 * @return array ['labels' => array, 'data' => array]
 */
function prepareVisitorChartData(array $dailyVisitorData): array {
    $visitorGraphLabels = [];
    $visitorGraphData = [];
    for ($i = 29; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $visitorGraphLabels[] = date('M d', strtotime($date));
        $visitorGraphData[] = $dailyVisitorData[$date] ?? 0;
    }
    return ['labels' => $visitorGraphLabels, 'data' => $visitorGraphData];
}

?>