<?php
// admin/includes/view_helpers.php

/**
 * Gets the top N most viewed jobs posted within the last X days.
 *
 * @param string $jobViewsFilePath Path to the job_views.json file.
 * @param string $jobsDataFilePath Path to the jobs.json file.
 * @param int $daysLimit Number of past days to consider for job posting date.
 * @param int $topN Number of top jobs to return.
 * @return array An array of top viewed jobs, each with 'title', 'company', and 'views'.
 */
function getMostViewedJobs(string $jobViewsFilePath, string $jobsDataFilePath, int $daysLimit = 30, int $topN = 10): array {
    if (!file_exists($jobViewsFilePath) || !file_exists($jobsDataFilePath)) {
        error_log("getMostViewedJobs: Missing job_views.json or jobs.json file.");
        return [];
    }

    $jobViewsJson = file_get_contents($jobViewsFilePath);
    $jobViewsData = $jobViewsJson ? json_decode($jobViewsJson, true) : [];
    if (!is_array($jobViewsData)) $jobViewsData = [];

    $jobsJson = file_get_contents($jobsDataFilePath);
    $allJobsData = $jobsJson ? json_decode($jobsJson, true) : [];
    if (!is_array($allJobsData)) $allJobsData = [];

    // Create a lookup for job details by ID for efficiency
    $jobsLookup = [];
    foreach ($allJobsData as $job) {
        if (isset($job['id'])) {
            $jobsLookup[$job['id']] = $job;
        }
    }

    $cutoffTimestamp = time() - ($daysLimit * 24 * 60 * 60);
    $eligibleJobs = [];

    foreach ($jobViewsData as $jobId => $views) {
        if (isset($jobsLookup[$jobId])) {
            $jobDetails = $jobsLookup[$jobId];
            // Ensure posted_on_unix_ts exists and is valid
            $postedTimestamp = $jobDetails['posted_on_unix_ts'] ?? 0;
            if (!is_numeric($postedTimestamp) || $postedTimestamp <= 0) {
                // Fallback to parsing 'posted_on' if 'posted_on_unix_ts' is missing/invalid
                $postedTimestamp = isset($jobDetails['posted_on']) ? strtotime($jobDetails['posted_on']) : 0;
            }

            if ($postedTimestamp && $postedTimestamp >= $cutoffTimestamp) {
                $eligibleJobs[] = [
                    'id' => $jobId,
                    'title' => $jobDetails['title'] ?? 'N/A',
                    'company' => $jobDetails['company'] ?? '', // Optional
                    'views' => (int)$views,
                ];
            }
        }
    }

    usort($eligibleJobs, fn($a, $b) => $b['views'] <=> $a['views']);

    return array_slice($eligibleJobs, 0, $topN);
}


/**
 * Gets the top N most shared jobs posted within the last X days.
 *
 * @param string $jobSharesFilePath Path to the job_shares.json file.
 * @param string $jobsDataFilePath Path to the jobs.json file.
 * @param int $daysLimit Number of past days to consider for job posting date.
 * @param int $topN Number of top jobs to return.
 * @return array An array of top shared jobs, each with 'id', 'title', 'company', and 'shares'.
 */
function getMostSharedJobs(string $jobSharesFilePath, string $jobsDataFilePath, int $daysLimit = 30, int $topN = 10): array {
    if (!file_exists($jobSharesFilePath) || !file_exists($jobsDataFilePath)) {
        error_log("getMostSharedJobs: Missing job_shares.json or jobs.json file.");
        return [];
    }

    $jobSharesJson = file_get_contents($jobSharesFilePath);
    $jobSharesData = $jobSharesJson ? json_decode($jobSharesJson, true) : [];
    if (!is_array($jobSharesData)) $jobSharesData = [];

    $jobsJson = file_get_contents($jobsDataFilePath);
    $allJobsData = $jobsJson ? json_decode($jobsJson, true) : [];
    if (!is_array($allJobsData)) $allJobsData = [];

    // Create a lookup for job details by ID for efficiency
    $jobsLookup = [];
    foreach ($allJobsData as $job) {
        if (isset($job['id'])) {
            $jobsLookup[$job['id']] = $job;
        }
    }

    $cutoffTimestamp = time() - ($daysLimit * 24 * 60 * 60);
    $eligibleJobs = [];

    foreach ($jobSharesData as $jobId => $shares) {
        if (isset($jobsLookup[$jobId])) {
            $jobDetails = $jobsLookup[$jobId];
            $postedTimestamp = $jobDetails['posted_on_unix_ts'] ?? 0;
            if (!is_numeric($postedTimestamp) || $postedTimestamp <= 0) {
                $postedTimestamp = isset($jobDetails['posted_on']) ? strtotime($jobDetails['posted_on']) : 0;
            }

            if ($postedTimestamp && $postedTimestamp >= $cutoffTimestamp) {
                $eligibleJobs[] = [
                    'id' => $jobId,
                    'title' => $jobDetails['title'] ?? 'N/A',
                    'company' => $jobDetails['company'] ?? '',
                    'shares' => (int)$shares, // Changed 'views' to 'shares'
                ];
            }
        }
    }

    usort($eligibleJobs, fn($a, $b) => $b['shares'] <=> $a['shares']); // Sort by shares

    return array_slice($eligibleJobs, 0, $topN);
}