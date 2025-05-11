<?php

// admin/includes/job_helpers.php

/**
 * Loads job data from the JSON file.
 *
 * @param string $filename The path to the job data file.
 * @return array An array of job objects.
 */
function loadJobs($filename) {
    // Cast to string to prevent "Passing null" deprecation warning if $filename is null
    $filename = (string) $filename;

    if (!file_exists($filename)) {
        error_log("Admin Error: Job data file not found: " . $filename); // Log error with path
        return []; // Return empty array if file doesn't exist
    }

    $jsonData = file_get_contents($filename);
    if ($jsonData === false) {
        error_log("Admin Error: Could not read job data file: " . $filename); // Log error with path
        return []; // Return empty array on read error
    }

    $jobs = json_decode($jsonData, true);
    if ($jobs === null) { // Handle malformed JSON
        error_log("Admin Error: Error decoding jobs.json: " . json_last_error_msg() . " in file: " . $filename); // Log JSON error with path
        return []; // Return empty array on decode error
    }

    if (!is_array($jobs)) {
         error_log("Admin Error: Job data is not an array in file: " . $filename); // Log error with path
         return []; // Return empty array if data is not an array
    }

    return $jobs;
}

/**
 * Saves job data to the JSON file.
 *
 * @param array $jobs The array of job objects to save.
 * @param string $filename The path to the job data file.
 * @return bool True on success, false on failure.
 */
function saveJobs($jobs, $filename) {
     // Cast to string to prevent potential issues if $filename is not a string
    $filename = (string) $filename;

    $jsonData = json_encode($jobs, JSON_PRETTY_PRINT);
    if ($jsonData === false) {
         error_log("Admin Error: Could not encode job data to JSON: " . json_last_error_msg());
         return false;
    }
    // Use LOCK_EX to prevent concurrent writes from corrupting the file
    if (file_put_contents($filename, $jsonData, LOCK_EX) === false) {
        error_log("Admin Error: Could not write job data to file: " . $filename); // Log write error with path
        return false;
    }

    // Check if file was actually written and has content (optional but good practice)
    if (filesize($filename) === 0 && !empty($jobs)) {
         error_log("Admin Error: Wrote to job data file, but it appears empty: " . $filename); // Log empty file after write
         return false; // Consider it a failure if data should have been written
    }


    return true; // Save successful
}

?>