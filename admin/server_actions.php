<?php
// admin/server_actions.php - Handles Server Management Actions

session_start();

require_once __DIR__ . '/includes/config.php'; // For log_app_activity and file paths

// Security check: Ensure user is logged in and is a super_admin or regional_admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    $_SESSION['admin_status'] = ['message' => 'Unauthorized access.', 'type' => 'error'];
    header('Location: dashboard.php?view=login');
    exit;
}

$loggedInUserRole = $_SESSION['admin_role'] ?? 'user';
$loggedInUsername = $_SESSION['admin_username'] ?? 'UnknownUser';

if (!($loggedInUserRole === 'super_admin' || in_array($loggedInUserRole, $allRegionalAdminRoles ?? []))) {
    log_app_activity("User '$loggedInUsername' (Role: '$loggedInUserRole') attempted unauthorized server action.", "SECURITY_WARNING");
    $_SESSION['admin_status'] = ['message' => 'You do not have permission to perform this action.', 'type' => 'error'];
    header('Location: dashboard.php?view=dashboard_overview');
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? null;
$gitConfigFile = __DIR__ . '/../data/git_config.php';

if ($action === 'save_git_config' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $repository_url = trim($_POST['repository_url'] ?? '');
    $branch_name = trim($_POST['branch_name'] ?? 'main');
    $git_username = trim($_POST['git_username'] ?? '');
    $git_email = trim($_POST['git_email'] ?? '');

    if (empty($repository_url) || empty($branch_name)) {
        $_SESSION['admin_status'] = ['message' => 'Repository URL and Branch Name are required.', 'type' => 'error'];
    } else {
        $configContent = "<?php\n// Git Configuration File\n// IMPORTANT: Add to .gitignore if it contains sensitive tokens.\nreturn [\n";
        $configContent .= "    'repository_url' => '" . addslashes($repository_url) . "',\n";
        $configContent .= "    'branch_name' => '" . addslashes($branch_name) . "',\n";
        $configContent .= "    'git_username' => '" . addslashes($git_username) . "',\n";
        $configContent .= "    'git_email' => '" . addslashes($git_email) . "',\n";
        $configContent .= "];\n?>";

        if (file_put_contents($gitConfigFile, $configContent) !== false) {
            $_SESSION['admin_status'] = ['message' => 'Git configuration saved successfully.', 'type' => 'success'];
            log_app_activity("Git configuration updated by '$loggedInUsername'. URL: $repository_url", "SERVER_CONFIG_UPDATE");
        } else {
            $_SESSION['admin_status'] = ['message' => 'Error saving Git configuration. Check file permissions for data/git_config.php.', 'type' => 'error'];
            log_app_activity("Failed to save Git configuration by '$loggedInUsername'. Check permissions for $gitConfigFile", "ERROR");
        }
    }
    header('Location: dashboard.php?view=server_management');
    exit;
} elseif ($action === 'trigger_backup') {
    // Placeholder for triggering the backup script
    // This would typically involve shell_exec() and ensuring the git_config.php is read by the script
    $scriptPath = "C:/Users/Public/Job_Post/scripts/backup_data_to_github.sh"; // Ensure this path is correct
    $output = shell_exec("bash " . escapeshellarg($scriptPath) . " 2>&1");
    $_SESSION['admin_status'] = ['message' => 'Backup process triggered. Output: ' . nl2br(htmlspecialchars($output)), 'type' => 'info'];
    log_app_activity("Data backup to GitHub triggered by '$loggedInUsername'. Output: " . $output, "BACKUP_TRIGGER");
    header('Location: dashboard.php?view=server_management');
    exit;
} elseif ($action === 'trigger_restore') {
    // Placeholder for triggering the restore script
    $scriptPath = "C:/Users/Public/Job_Post/scripts/restore_data_from_github.sh"; // Ensure this path is correct
    $output = shell_exec("bash " . escapeshellarg($scriptPath) . " 2>&1"); // Be very careful with user input if script takes params
    $_SESSION['admin_status'] = ['message' => 'Restore process triggered. Output: ' . nl2br(htmlspecialchars($output)), 'type' => 'info'];
    log_app_activity("Data restore from GitHub triggered by '$loggedInUsername'. Output: " . $output, "RESTORE_TRIGGER");
    header('Location: dashboard.php?view=server_management');
    exit;
}

// Default redirect if no valid action
header('Location: dashboard.php?view=server_management');
exit;
?>