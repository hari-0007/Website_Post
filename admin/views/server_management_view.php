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
    <h3>Server Status & Management</h3>

    <div class="dashboard-section">
        <h4>Server Information</h4>
        <ul class="info-list">
            <li><strong>PHP Version:</strong> <?= htmlspecialchars($serverPhpVersion) ?></li>
            <li><strong>Server Software:</strong> <?= htmlspecialchars($serverSoftware) ?></li>
            <li><strong>Operating System:</strong> <?= htmlspecialchars($serverOsDetailed) ?></li>
            <li><strong>Disk Free Space (on partition of project root):</strong> <?= htmlspecialchars($diskFreeSpace) ?></li>
            <li><strong>Disk Total Space (on partition of project root):</strong> <?= htmlspecialchars($diskTotalSpace) ?></li>
            <li><strong>Disk Usage:</strong> <?= htmlspecialchars($diskUsagePercentage) ?></li>
        </ul>
    </div>

    <div class="dashboard-section">
        <h4>Git Backup Configuration</h4>
        <form action="server_actions.php" method="POST" class="form-container compact-form">
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
        <h4>Data Management Actions</h4>
        <form action="server_actions.php" method="POST" style="display: inline-block; margin-right: 10px;">
            <input type="hidden" name="action" value="trigger_backup">
            <button type="submit" class="button success">Backup Data to GitHub</button>
        </form>
        <form action="server_actions.php" method="POST" style="display: inline-block;">
            <input type="hidden" name="action" value="trigger_restore">
            <button type="submit" class="button warning" onclick="return confirm('WARNING: This will overwrite local data with the latest backup from GitHub. This action cannot be undone. Are you absolutely sure?')">Restore Data from GitHub</button>
        </form>
        <p style="margin-top:10px;"><small>Note: Backup and Restore actions require the above Git configuration to be correctly set and server-side shell scripts (`backup_data_to_github.sh`, `restore_data_from_github.sh`) to be in place and executable.</small></p>
  
    </div>

</div>
<style>
.info-list { list-style: none; padding-left: 0; }
.info-list li { padding: 5px 0; border-bottom: 1px dashed #eee; }
.info-list li:last-child { border-bottom: none; }
.compact-form .form-group {
    margin-bottom: 15px;
}
.compact-form label {
    font-weight: bold;
    display: block;
    margin-bottom: 5px;
}
.compact-form input[type="text"], .compact-form input[type="email"] {
    width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;
}
</style>