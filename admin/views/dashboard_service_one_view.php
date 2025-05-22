<?php
// admin/views/dashboard_service_one_view.php

// Assumes $serverPhpVersion, $serverSoftware, $serverOs are available from fetch_content.php

$serverPhpVersion = $serverPhpVersion ?? 'N/A';
$serverSoftware = $serverSoftware ?? 'N/A';
$applicationVersion = $applicationVersion ?? 'N/A'; // Default if not set

$serverOs = $serverOs ?? 'N/A';
?>
<div class="dashboard-content">
    <h3>Service Information</h3>
    <div class="dashboard-section server-info-section">
        <ul class="info-list">
            <li><strong>PHP Version:</strong> <?= htmlspecialchars($serverPhpVersion) ?></li>
            <li><strong>Application Version:</strong> <?= htmlspecialchars($applicationVersion) ?></li>
            <li><strong>Server Software:</strong> <?= htmlspecialchars($serverSoftware) ?></li>
            <li><strong>Operating System:</strong> <?= htmlspecialchars($serverOs) ?></li>
        </ul>
    </div>
</div>