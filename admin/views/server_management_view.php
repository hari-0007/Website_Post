<?php
// admin/views/server_management_view.php

// Assumes $serverPhpVersion, $serverSoftware, $serverOs are available from fetch_content.php
// For a dedicated page, you might fetch more detailed info.

$serverPhpVersion = $serverPhpVersion ?? phpversion();
$serverSoftware = $serverSoftware ?? ($_SERVER['SERVER_SOFTWARE'] ?? 'N/A');
$serverOsDetailed = $serverOs ?? php_uname(); // $serverOs from fetch_content for this view is more detailed
// Load Git configuration
$gitConfigFile = __DIR__ . '/../../data/git_config.php';
$gitConfig = [
    'repository_url' => '',
    'branch_name' => 'main',
    'git_username' => '',
    'git_email' => '',
];
if (file_exists($gitConfigFile)) {
    $loadedConfig = include $gitConfigFile;
    if (is_array($loadedConfig)) $gitConfig = array_merge($gitConfig, $loadedConfig);
}

// Attempt to get disk space (might be restricted)
$diskFreeSpace = 'N/A';
$diskTotalSpace = 'N/A';
$diskUsagePercentage = 'N/A';

if (function_exists('disk_free_space') && function_exists('disk_total_space')) {
    // Use a common directory like the project root or document root.
    // Using __DIR__ might point to the admin/views directory, which might not be representative.
    // Let's try the project's root directory.
    $checkPath = dirname(__DIR__, 2); // Goes up two levels from admin/views to project root

    // Suppress errors if functions are disabled or path is not accessible
    $free = @disk_free_space($checkPath);
    $total = @disk_total_space($checkPath);

    if ($free !== false && $total !== false && $total > 0) {
        $diskFreeSpace = round($free / (1024 * 1024 * 1024), 2) . ' GB';
        $diskTotalSpace = round($total / (1024 * 1024 * 1024), 2) . ' GB';
        $diskUsagePercentage = round((($total - $free) / $total) * 100, 2) . '%';
    }
}

?>

<div class="dashboard-content server-management-content">
    <h2 class="view-main-title">Server Status & Management</h2>

    <div class="dashboard-section">
        <h4 class="section-title">Server Information</h4>
        <ul class="info-list">
            <li><strong>PHP Version:</strong> <?= htmlspecialchars($serverPhpVersion) ?></li>
            <li><strong>Server Software:</strong> <?= htmlspecialchars($serverSoftware) ?></li>
            <li><strong>Operating System:</strong> <?= htmlspecialchars($serverOsDetailed) ?></li>
            <li><strong>Disk Free Space (on partition of project root):</strong> <?= htmlspecialchars($diskFreeSpace) ?></li>
            <li><strong>Disk Total Space (on partition of project root):</strong> <?= htmlspecialchars($diskTotalSpace) ?></li>
            <li><strong>Disk Usage:</strong> <?= htmlspecialchars($diskUsagePercentage) ?></li>
        </ul>
    </div>

    <div class="management-toggles">
        <!-- The WhatsApp toggle button is removed as WhatsApp now has its own page -->
        <!-- You might want a general "Server Management" page that then links to "WhatsApp Profile" and "Git Management" -->
        <!-- Or keep this as a combined page, and the dashboard link directly shows the WhatsApp part -->
        <button id="toggleGitBtn" class="button">Toggle Git Section</button>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggleGitBtn = document.getElementById('toggleGitBtn');
    const gitDetailsDiv = document.getElementById('gitManagementDetails');

    if (toggleGitBtn && gitDetailsDiv) {
        toggleGitBtn.addEventListener('click', function() {
            const isHidden = gitDetailsDiv.style.display === 'none' || gitDetailsDiv.style.display === '';
            gitDetailsDiv.style.display = isHidden ? 'block' : 'none';
        });
    }

    // Check URL parameters to focus on a section
    // This logic is removed as this view will no longer handle the 'focus=whatsapp' parameter.
});
</script>

    <div id="gitManagementDetails" style="display: none;">
        <div class="dashboard-section">
            <h4 class="section-title">Git Backup Configuration</h4>
            <form action="server_actions.php" method="POST" class="styled-form compact-form">
                <input type="hidden" name="action" value="save_git_config">

                <div class="form-group">
                    <label for="repository_url">GitHub Repository URL:</label>
                    <input type="text" id="repository_url" name="repository_url" value="<?= htmlspecialchars($gitConfig['repository_url']) ?>" placeholder="https://username:YOUR_PAT@github.com/username/repo.git OR git@github.com:username/repo.git" required>
                    <small class="form-text text-muted">
                        For HTTPS with PAT: `https://YOUR_USERNAME:YOUR_PAT@github.com/YOUR_USERNAME/YOUR_PRIVATE_REPO.git`. <br>
                        For SSH: `git@github.com:YOUR_USERNAME/YOUR_PRIVATE_REPO.git` (ensure server's SSH key is added to GitHub).<br>
                        <strong>Warning:</strong> Storing a Personal Access Token (PAT) directly in the URL can be a security risk if this configuration file is exposed. Prefer SSH keys for server-side automation.
                    </small>
                </div>

                <div class="form-group">
                    <label for="branch_name">Branch Name:</label>
                    <input type="text" id="branch_name" name="branch_name" value="<?= htmlspecialchars($gitConfig['branch_name']) ?>" placeholder="main" required>
                </div>
                
                <div class="form-group">
                    <label for="git_username">Git Commit Username:</label>
                    <input type="text" id="git_username" name="git_username" value="<?= htmlspecialchars($gitConfig['git_username']) ?>" placeholder="Your Bot Name or Username">
                    <small class="form-text text-muted">Used for `git config user.name` for commits by the backup script.</small>
                </div>

                <div class="form-group">
                    <label for="git_email">Git Commit Email:</label>
                    <input type="email" id="git_email" name="git_email" value="<?= htmlspecialchars($gitConfig['git_email']) ?>" placeholder="your-bot-email@example.com">
                    <small class="form-text text-muted">Used for `git config user.email` for commits by the backup script.</small>
                </div>

                <button type="submit" class="button primary">Save Git Configuration</button>
            </form>
        </div>

        <div class="dashboard-section">
            <h4 class="section-title">Data Management Actions</h4>
            <form action="server_actions.php" method="POST" style="display: inline-block; margin-right: 10px;">
                <input type="hidden" name="action" value="trigger_backup">
                <button type="submit" class="button button-success">Backup Data to GitHub</button>
            </form>
            <form action="server_actions.php" method="POST" style="display: inline-block;">
                <input type="hidden" name="action" value="trigger_restore">
                <button type="submit" class="button button-warning" onclick="return confirm('WARNING: This will overwrite local data with the latest backup from GitHub. This action cannot be undone. Are you absolutely sure?')">Restore Data from GitHub</button>
            </form>
            <p style="margin-top:10px;"><small>Note: Backup and Restore actions require the above Git configuration to be correctly set and server-side shell scripts (`backup_data_to_github.sh`, `restore_data_from_github.sh`) to be in place and executable.</small></p>
        </div>
    </div>

</div> <!-- This closes div.dashboard-content.server-management-content -->
<style>
    .view-main-title { /* Consistent main title for views */
        margin-top: 0;
        margin-bottom: 25px;
        color: var(--primary-color);
        font-size: 1.75em;
        font-weight: 600;
        padding-bottom: 15px;
        border-bottom: 2px solid var(--primary-color-lighter);
    }
    .section-title { /* Consistent section titles */
        margin-top: 0;
        margin-bottom: 15px;
        color: var(--text-color-light);
        font-size: 1.2em;
        font-weight: 500;
        padding-bottom: 10px;
        border-bottom: 1px solid var(--border-color);
    }
    .dashboard-section { /* Ensure consistent section styling */
        background-color: var(--card-bg);
        padding: 20px;
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow-sm);
        border: 1px solid var(--border-color);
        margin-bottom: 20px;
    }
    .info-list { 
        list-style: none; 
        padding-left: 0; 
        font-size: 0.95rem;
    }
    .info-list li { 
        padding: 8px 0; 
        border-bottom: 1px dashed var(--border-color); 
    }
    .info-list li:last-child { 
        border-bottom: none; 
    }
    .info-list li strong {
        color: var(--text-color);
        min-width: 180px; /* Align values */
        display: inline-block;
    }

    .management-toggles {
        padding: 15px 0; 
        border-bottom: 1px solid var(--border-color); 
        margin-bottom: 20px; /* Increased margin */
    }
    .management-toggles .button {
        /* Inherits global .button style */
    }

    /* Form styling within this view */
    .styled-form .form-group { /* Use .styled-form class for forms */
        margin-bottom: 1rem; /* Consistent with global */
    }
    .styled-form label {
        /* Inherits global form label style */
    }
    .styled-form input[type="text"], 
    .styled-form input[type="email"] {
        /* Inherits global form input styles */
    }
    .styled-form .form-text.text-muted { /* Style for small helper text */
        font-size: 0.85em;
        color: var(--text-muted);
        display: block;
        margin-top: 0.25rem;
    }
    .styled-form .button {
        /* Inherits global button styles */
        margin-top: 10px; /* Add some space if it's the last element in a form group */
    }
</style>