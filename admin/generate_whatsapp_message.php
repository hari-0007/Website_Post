<?php
// admin/generate_whatsapp_message.php

// This script generates the message content for WhatsApp.
// It should have access to job data.

// Ensure this script is not accessed directly if it relies on session or other contexts.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/job_helpers.php';

$allJobs = loadJobs($jobsFilename); // Or get the specific job to post

if (empty($allJobs)) {
    echo "Could not generate WhatsApp message. Job data is empty or missing.";
    exit;
}

// Find jobs posted in the last 24 hours
$jobsLast24Hours = [];
$twentyFourHoursAgo = time() - (24 * 60 * 60);

foreach ($allJobs as $job) {
    $postedTimestamp = 0;
    if (isset($job['posted_on_unix_ts']) && is_numeric($job['posted_on_unix_ts'])) {
        $postedTimestamp = (int)$job['posted_on_unix_ts'];
    } elseif (isset($job['posted_on']) && is_string($job['posted_on']) && !empty($job['posted_on'])) {
        $parsedTime = strtotime($job['posted_on']);
        if ($parsedTime !== false) {
            $postedTimestamp = $parsedTime;
        }
    }

    if ($postedTimestamp >= $twentyFourHoursAgo) {
        $jobsLast24Hours[] = $job;
    }
}

if (empty($jobsLast24Hours)) {
    echo "No new jobs posted in the last 24 hours for WhatsApp.";
    exit;
}

// Basic WhatsApp message format (uses WhatsApp formatting like *bold*, _italic_)
echo "🎯 *Daily UAE Job Update* 🇦🇪\n\n"; // Added UAE flag emoji
echo "Total jobs posted in the last 24 hours: " . count($jobsLast24Hours) . "\n\n";

foreach ($jobsLast24Hours as $job) {
    // echo "➡ " . htmlspecialchars(string: $job['title'] ?? 'N/A') . (isset($job['company']) && !empty($job['company']) ? " at " . htmlspecialchars($job['company']) : "") . "\n";
$title = htmlspecialchars($job['title'] ?? 'N/A');
    $companyPart = "";
    if (isset($job['company']) && !empty($job['company'])) {
        $company = htmlspecialchars($job['company']);
        $companyPart = " at *" . $company . "*";
    }
    echo "➡ *" . $title . "*" . $companyPart . "\n";
}

echo "\nExplore all jobs on our website!\n";
echo "www.jobhunt.top\n";