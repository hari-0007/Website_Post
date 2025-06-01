<?php
// admin/views/dashboard_service_one_view.php

// Assumes $serverPhpVersion, $serverSoftware, $serverOs are available from fetch_content.php

$serverPhpVersion = $serverPhpVersion ?? 'N/A';
$serverSoftware = $serverSoftware ?? 'N/A';
$applicationVersion = $applicationVersion ?? 'N/A'; // Default if not set

$serverOs = $serverOs ?? 'N/A';
?>
<div class="dashboard-content">
    <h2 class="view-main-title">Service Information</h2>
    <div class="dashboard-section server-info-section">
        <ul class="info-list styled-info-list">
            <li><strong>PHP Version:</strong> <?= htmlspecialchars($serverPhpVersion) ?></li>
            <li><strong>Application Version:</strong> <?= htmlspecialchars($applicationVersion) ?></li>
            <li><strong>Server Software:</strong> <?= htmlspecialchars($serverSoftware) ?></li>
            <li><strong>Operating System:</strong> <?= htmlspecialchars($serverOs) ?></li>
        </ul>
    </div>
</div>

<style>
    .view-main-title {
        margin-top: 0;
        margin-bottom: 25px;
        color: var(--primary-color);
        font-size: 1.75em;
        font-weight: 600;
        padding-bottom: 15px;
        border-bottom: 2px solid var(--primary-color-lighter);
    }
    /* .dashboard-section is assumed to be styled globally or via dashboard_overview_view.php */
    .styled-info-list {
        list-style: none;
        padding-left: 0;
    }
    .styled-info-list li {
        padding: 10px 5px; /* Increased padding */
        border-bottom: 1px dashed var(--border-color);
        font-size: 1rem; /* Slightly larger font */
    }
    .styled-info-list li:last-child {
        border-bottom: none;
    }
    .styled-info-list li strong {
        color: var(--text-color); /* Emphasize the label */
        min-width: 180px; /* Align values */
        display: inline-block;
    }
</style>