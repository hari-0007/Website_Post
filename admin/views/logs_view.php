<?php
// admin/views/logs_view.php

// Assumes $logEntries is available from fetch_content.php
$logEntries = $logEntries ?? ["No log entries to display or an error occurred while loading logs."];
$logFilePathForDisplay = defined('APP_LOG_FILE_PATH') ? APP_LOG_FILE_PATH : 'Application Log';

?>
<div class="dashboard-content logs-view-content">
    <h2 class="view-main-title">Application Log Viewer</h2>
    <!-- <p class="log-info">
        Displaying the last <?= count($logEntries) ?> entries from <code><?= htmlspecialchars($logFilePathForDisplay) ?></code>.
        Auto-refreshes every 10 seconds.
    </p> -->
    
    <div id="log-container" class="log-container">
        <?php if (!empty($logEntries) && !(count($logEntries) === 1 && (strpos($logEntries[0], 'No log entries') !== false || strpos($logEntries[0], 'error loading logs') !== false || strpos($logEntries[0], 'Application log file not found') !== false) )): ?>
            <?php foreach ($logEntries as $entry): ?>
                <div class="log-entry"><?= htmlspecialchars($entry) ?></div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="log-entry no-data-message"><?= htmlspecialchars($logEntries[0] ?? 'No log entries found or log file is empty.') ?></div>
        <?php endif; ?>
    </div>
</div>

<style>
    .view-main-title { /* Consistent main title for views */
        margin-top: 0;
        margin-bottom: 25px;
        color: var(--primary-color);
        font-size: 1.75em;
        font-weight: 600;
        padding-bottom: 10px;
        border-bottom: 2px solid var(--primary-color-lighter);
    }

    .log-info code {
        background-color: #f0f0f0;
        padding: 2px 4px;
        border-radius: 3px;
        font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
    }
    .log-info {
        font-size: 0.9em;
        color: #555;
        margin-bottom: 15px;
    }
    .log-container {
        background-color: var(--sidebar-brand-bg); /* Use a dark theme color */
        color: var(--sidebar-link-color); /* Use a light theme color for text */
        font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
        font-size: 0.85em;
        line-height: 1.4;
        padding: 15px;
        border-radius: var(--border-radius); /* Use theme variable */
        height: 65vh; /* Increased height */
        overflow-y: auto; /* Scroll for overflow */
        border: 1px solid var(--sidebar-bg); /* Use a related dark theme color for border */
        box-shadow: inset 0 0 8px rgba(0,0,0,0.25); /* Slightly softer inset shadow */
    }
    .log-entry {
        padding: 1px 0; /* Reduced padding for more compact view */
        white-space: pre-wrap; /* Preserve whitespace and wrap lines */
        word-break: break-all; /* Break long words/strings */
        border-bottom: 1px dotted var(--sidebar-link-hover-bg); /* Use a theme color for separator */
    }
    .log-entry:last-child {
        border-bottom: none;
    }
    .log-entry.no-data-message {
        color: var(--text-muted); /* Use theme variable */
        font-style: italic;
        border-bottom: none;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const logContainer = document.getElementById('log-container');
    let logInterval;
    let isFetching = false; // Flag to prevent multiple simultaneous fetches

    function fetchLogs() {
        if (isFetching) {
            return; // Don't fetch if a fetch is already in progress
        }
        isFetching = true;

        fetch('fetch_content.php?view=logs&ajax=1&_=' + new Date().getTime()) // Cache buster
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.statusText);
                }
                return response.text();
            })
            .then(html => {
                // The HTML now contains only the div.log-entry elements
                logContainer.innerHTML = html; 
                logContainer.scrollTop = logContainer.scrollHeight; // Scroll to bottom
            })
            .catch(error => {
                console.error('Error fetching logs:', error);
                // Optionally display an error message in the log container
                // logContainer.innerHTML = '<div class="log-entry no-data-message">Error updating logs. Please check console.</div>';
            })
            .finally(() => {
                isFetching = false; // Reset flag
            });
    }

    // Auto-refresh interval
    if (logContainer) { // Only set interval if the container exists
        logInterval = setInterval(fetchLogs, 10000); // Refresh every 10 seconds
    }
});
</script>
