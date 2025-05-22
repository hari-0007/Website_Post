<?php
// admin/includes/log_helpers.php

// Define APP_LOG_FILE_PATH relative to the project root, assuming 'admin' is a subdirectory
// __DIR__ is admin/includes, so dirname(__DIR__, 2) goes up two levels to Job_Post/
if (!defined('APP_LOG_FILE_PATH')) {
    define('APP_LOG_FILE_PATH', dirname(__DIR__, 2) . '/data/app_activity.log');
}

/**
 * Logs a message to the application's custom log file.
 *
 * @param string $message The message to log.
 * @param string $level The log level (e.g., INFO, WARNING, ERROR, SECURITY).
 * @return void
 */
function log_app_activity($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    // Sanitize message to prevent log injection if message contains user input directly
    // FILTER_UNSAFE_RAW is used because we trust the source of $level and $timestamp.
    // The main concern is $message if it ever contains direct, unescaped user input.
    $sanitizedMessage = filter_var($message, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
    $logEntry = "[{$timestamp}] [{$level}] {$sanitizedMessage}" . PHP_EOL;

    $logDir = dirname(APP_LOG_FILE_PATH);
    if (!is_dir($logDir)) {
        // Attempt to create directory, suppress error if it fails (permission issues)
        // For debugging, you might temporarily remove the '@'
        // Check if directory exists after attempt, and log to system error log if creation failed
        if (!@mkdir($logDir, 0755, true) && !is_dir($logDir)) { 
            // Use PHP's built-in error_log for issues with the logging mechanism itself
            error_log("CRITICAL: log_app_activity - Failed to create log directory: " . $logDir . " - Check permissions.");
            return; // Cannot proceed if directory can't be made
        }
    }

    // Attempt to write to the log file, suppress error if it fails
    // For debugging, you might temporarily remove the '@'
    // Log to system error log if writing fails
    if (@file_put_contents(APP_LOG_FILE_PATH, $logEntry, FILE_APPEND | LOCK_EX) === false) {
        // Use PHP's built-in error_log for issues with the logging mechanism itself
        error_log("CRITICAL: log_app_activity - Failed to write to log file: " . APP_LOG_FILE_PATH . " - Check permissions and file/path validity.");
    }
}
?>
