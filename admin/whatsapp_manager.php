<?php
error_log("[whatsapp_manager.php] Script invoked. Request Method: " . ($_SERVER['REQUEST_METHOD'] ?? 'N/A') . ". Action: " . htmlspecialchars($_REQUEST['action'] ?? 'N/A'));
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/includes/config.php'; // For APP_BASE_PATH if needed

// Increase execution time limit for this script, as Node.js script can take time
set_time_limit(150); // 150 seconds (2.5 minutes)

// Security check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login.']);
    exit;
}
$loggedInUserRole = $_SESSION['admin_role'] ?? 'user';
$allRegionalAdminRoles = ['India_Admin', 'Middle_East_Admin', 'USA_Admin', 'Europe_Admin'];
if (!($loggedInUserRole === 'super_admin' || in_array($loggedInUserRole, $allRegionalAdminRoles))) {
    echo json_encode(['success' => false, 'message' => 'Access Denied. You do not have permission.']);
    exit;
}

session_write_close();

$action = $_GET['action'] ?? $_POST['action'] ?? null;

// --- Node.js Controller Configuration ---
$nodeScriptPath = realpath(__DIR__ . '/../whatsapp_automation/whatsapp_controller.js');
$nodeControllerDirectory = realpath(__DIR__ . '/../whatsapp_automation'); // Working directory for the node script

if (!$nodeScriptPath || !$nodeControllerDirectory) {
    $missingPath = !$nodeScriptPath ? 'whatsapp_controller.js' : 'whatsapp_automation directory';
    error_log("CRITICAL ERROR: {$missingPath} not found at expected path. Check paths in whatsapp_manager.php");
    echo json_encode(['success' => false, 'message' => 'Server configuration error: WhatsApp controller components not found. Please contact administrator.']);
    exit;
}

// Helper function to execute Node.js script and get JSON output
function executeNodeScript($commandSuffix) {
    global $nodeScriptPath, $nodeControllerDirectory;
    
    // Specify the full path to your Node.js executable
    $nodeExecutable = 'C:\Program Files\nodejs\node.exe'; // Adjust if Node.js is elsewhere

    // Use escapeshellcmd for the executable and script path, and escapeshellarg for arguments
    $command = '"' . $nodeExecutable . '" ' . escapeshellcmd($nodeScriptPath) . ' ' . $commandSuffix;
    
    error_log("WhatsApp Manager DEBUG: Preparing to execute command: " . $command . " in CWD: " . $nodeControllerDirectory);

    $descriptorspec = array(
       0 => array("pipe", "r"),  // stdin
       1 => array("pipe", "w"),  // stdout (for JSON response)
       2 => array("pipe", "w")   // stderr (for logs from Node script)
    );

    $process = proc_open($command, $descriptorspec, $pipes, $nodeControllerDirectory);

    $stdout_json = '';
    $stderr_logs = '';
    $response = ['success' => false, 'message' => 'Failed to execute WhatsApp controller.']; // Default error

    if (is_resource($process)) {
       fclose($pipes[0]); // Close stdin as we're not sending anything

       $stdout_json = stream_get_contents($pipes[1]);
       fclose($pipes[1]);

       $stderr_logs = stream_get_contents($pipes[2]);
       fclose($pipes[2]);

       $return_value = proc_close($process);

       if (!empty($stderr_logs)) {
           error_log("WhatsApp Manager DEBUG (stderr from controller for '{$commandSuffix}'): " . trim($stderr_logs));
       }

       if ($return_value !== 0 && empty($stdout_json)) { // If script failed and produced no stdout JSON
$specific_error_message = "Controller script exited with error code: {$return_value}. Check server logs for full stderr output.";
           if (!empty($stderr_logs)) {
               $lower_stderr = strtolower($stderr_logs);
               if (strpos($lower_stderr, 'cannot find module') !== false || strpos($lower_stderr, 'error: module not found') !== false) {
                   $specific_error_message = "Controller script failed (exit code: {$return_value}). A Node.js module seems to be missing. Please run 'npm install' in the 'whatsapp_automation' directory. Check server logs for details.";
               } elseif (strpos($lower_stderr, 'syntaxerror') !== false) {
                   $specific_error_message = "Controller script failed (exit code: {$return_value}) due to a syntax error. Check the Node.js script and server logs for details.";
               }
           }
           $response = ['success' => false, 'message' => $specific_error_message, 'raw_stderr_preview' => substr(trim($stderr_logs ?? ''), 0, 200)];
              } elseif (!empty($stdout_json)) {
           $decodedOutput = json_decode(trim($stdout_json), true);
           if (json_last_error() === JSON_ERROR_NONE && isset($decodedOutput['success'])) {
               $response = $decodedOutput;
           } else {
               error_log("WhatsApp Manager: Failed to decode JSON or invalid response structure from controller for command suffix '{$commandSuffix}'. JSON Error: " . json_last_error_msg() . ". Raw stdout: " . trim($stdout_json));
               $response = ['success' => false, 'message' => 'Invalid JSON response from WhatsApp controller. Check server logs.', 'raw_stdout_preview' => substr(trim($stdout_json) ?? '', 0, 200)];
           }
       } elseif ($return_value === 0 && empty($stdout_json)) {
            // Script exited successfully but sent no JSON (should not happen with current Node script design)
            error_log("WhatsApp Manager: Controller script for '{$commandSuffix}' exited successfully but sent no JSON output.");
            $response = ['success' => false, 'message' => 'Controller script sent no response. Check server logs.'];
       }
    } else {
        error_log("WhatsApp Manager: Failed to open process for command: " . $command);
        $response = ['success' => false, 'message' => 'Server error: Could not initiate WhatsApp controller process.'];
    }
    return $response;
}

switch ($action) {
    case 'get_status':
        $response = executeNodeScript(escapeshellarg('get_status'));
        echo json_encode($response);
        break;

    case 'initiate_login':
        $response = executeNodeScript(escapeshellarg('login'));
        echo json_encode($response);
        break;

    case 'logout':
        $response = executeNodeScript(escapeshellarg('logout'));
        echo json_encode($response);
        break;

    case 'get_groups':
        $response = executeNodeScript(escapeshellarg('get_groups'));
        echo json_encode($response);
        break;

    case 'get_profile_info':
        // Node controller uses 'get_profile_info' as a command now
        $response = executeNodeScript(escapeshellarg('get_profile_info'));
        echo json_encode($response);
        break;

    case 'send_whatsapp_message':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method. POST required.']);
            exit;
        }
        $targetIdentifier = $_POST['target_identifier'] ?? null; // Changed from chat_id
        $messageText = $_POST['message'] ?? null;

        if (empty($targetIdentifier) || !isset($messageText)) { // Allow empty message if intended
            echo json_encode(['success' => false, 'message' => 'Missing target_identifier or message parameters.']);
            exit;
        }
        // Arguments for send_message are passed as separate shell arguments
        $commandSuffix = escapeshellarg('send_message') . ' ' . escapeshellarg($targetIdentifier) . ' ' . escapeshellarg($messageText);
        $response = executeNodeScript($commandSuffix);
        echo json_encode($response);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action specified.']);
        break;
}
exit;
?>
