<?php

// admin/includes/job_helpers.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php'; // Ensures $jobsFilename is available

/**
 * Loads all jobs from the JSON file.
 * Ensures 'total_views_count' and 'total_shares_count' fields exist.
 *
 * @param string $filename Path to the jobs JSON file.
 * @return array An array of job objects.
 */
function loadJobs(string $filename): array {
    if (!file_exists($filename)) {
        return [];
    }
    $json = file_get_contents($filename);
    $jobs = json_decode($json, true);

    if (!is_array($jobs)) {
        error_log("Error decoding jobs JSON or not an array: $filename");
        return [];
    }

    // Ensure new fields exist with defaults and unix timestamp for sorting
    foreach ($jobs as &$job) { // Pass by reference to modify
        if (!isset($job['total_views_count'])) {
            $job['total_views_count'] = 0;
        }
        if (!isset($job['total_shares_count'])) {
            $job['total_shares_count'] = 0;
        }
        if (!isset($job['posted_on_unix_ts']) && isset($job['posted_on'])) {
            $job['posted_on_unix_ts'] = strtotime($job['posted_on']);
        } elseif (!isset($job['posted_on_unix_ts'])) {
            $job['posted_on_unix_ts'] = time(); // Fallback if no date info
        }
    }
    unset($job); // Unset reference

    return $jobs;
}

/**
 * Saves an array of jobs to the JSON file.
 *
 * @param string $filename Path to the jobs JSON file.
 * @param array $jobs Array of job objects to save.
 * @return bool|int False on failure, otherwise number of bytes written.
 */
function saveJobs(string $filename, array $jobs) {
    $dir = dirname($filename);
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
            error_log("Failed to create directory: $dir");
            return false;
        }
    }
    return file_put_contents($filename, json_encode($jobs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

/**
 * Adds a new job to the jobs list.
 * (This is a simplified example; your actual addJob function might have more parameters)
 *
 * @param array $jobData Associative array of job data.
 * @return string|false The ID of the new job, or false on failure.
 */
function addJob(array $jobData) {
    global $jobsFilename;
    $allJobs = loadJobs($jobsFilename);

    $newJob = [
        'id' => $jobData['id'] ?? uniqid('job_'),
        'title' => $jobData['title'] ?? 'Untitled Job',
        'description' => $jobData['description'] ?? '',
        'company' => $jobData['company'] ?? '',
        'posted_on' => date('Y-m-d H:i:s'),
        'posted_on_unix_ts' => time(),
        'posted_by_user_id' => $jobData['posted_by_user_id'] ?? ($_SESSION['admin_username'] ?? 'system'),
        'region' => $jobData['region'] ?? null,
        'status' => $jobData['status'] ?? 'active',
        'total_views_count' => 0, // Initialize new field
        'total_shares_count' => 0, // Initialize new field
        // Add any other fields from $jobData
    ];

    $allJobs[] = $newJob;

    if (saveJobs($jobsFilename, $allJobs)) {
        return $newJob['id'];
    }
    return false;
}

// Other job helper functions like updateJob, deleteJob, findJobById etc.
// Ensure updateJob preserves 'total_views_count' and 'total_shares_count'.
/**
 * Increments the total view count for a specific job in jobs.json.
 *
 * @param string $jobId The ID of the job to update.
 * @return bool True on success, false on failure.
 */
function incrementJobViewCountInJobsJson(string $jobId): bool {
    global $jobsFilename; // Assumes $jobsFilename is defined in config.php and included
    $allJobs = loadJobs($jobsFilename);
    $jobFound = false;

    foreach ($allJobs as &$job) { // Pass by reference
        if (isset($job['id']) && $job['id'] === $jobId) {
            $job['total_views_count'] = (isset($job['total_views_count']) ? (int)$job['total_views_count'] : 0) + 1;
            $jobFound = true;
            break;
        }
    }
    unset($job); // Unset reference

    if ($jobFound) {
        if (saveJobs($jobsFilename, $allJobs)) {
            return true;
        } else {
            error_log("Failed to save jobs.json after incrementing total_views_count for job ID: $jobId");
            return false;
        }
    } else {
        error_log("Job ID: $jobId not found in jobs.json for incrementing total_views_count.");
        return false; // Job not found
    }
}

/**
 * Increments the total share count for a specific job in jobs.json.
 *
 * @param string $jobId The ID of the job to update.
 * @return bool True on success, false on failure.
 */
function incrementJobShareCountInJobsJson(string $jobId): bool {
    global $jobsFilename;
    $allJobs = loadJobs($jobsFilename);
    $jobFound = false;

    foreach ($allJobs as &$job) {
        if (isset($job['id']) && $job['id'] === $jobId) {
            $job['total_shares_count'] = (isset($job['total_shares_count']) ? (int)$job['total_shares_count'] : 0) + 1;
            $jobFound = true;
            break;
        }
    }
    unset($job);

    if ($jobFound) {
        return saveJobs($jobsFilename, $allJobs);
    }
    error_log("Job ID: $jobId not found in jobs.json for incrementing total_shares_count.");
    return false;
}
?>