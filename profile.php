<?php
ob_start();
session_start();
date_default_timezone_set('Asia/Kolkata'); // Ensure consistent timezone for timestamps

// Include the configuration file to get access to constants like APP_BASE_URL
require_once __DIR__ . '/admin/includes/config.php';

// Redirect to login page if the user is not logged in.
if (!isset($_SESSION['user_id'])) {
    // header('Location: auth.php?action=login');
    exit;
}

// --- User Information ---
$userName = $_SESSION['user_name'] ?? 'User';
$userRole = $_SESSION['user_role'] ?? 'guest'; // Default to 'guest' if not set
$userId = $_SESSION['user_id'] ?? null;

// --- Helper Function to read and decode JSON files ---
function readJsonFile($filePath) {
    if (!file_exists($filePath)) { // Check if file exists
        error_log("readJsonFile: File not found at " . $filePath);
        return []; // Return empty array if file doesn't exist
    }
    $fileContent = file_get_contents($filePath);
    if ($fileContent === false) { // Check if file_get_contents failed
        error_log("readJsonFile: Failed to read file content from " . $filePath);
        return [];
    }
    if (empty($fileContent)) { // Handle empty file case
        error_log("readJsonFile: File is empty: " . $filePath);
        return [];
    }
    $data = json_decode($fileContent, true);
    // Check for JSON errors and ensure it's an array
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        error_log("readJsonFile: JSON decode error for " . $filePath . ": " . json_last_error_msg());
        return []; // Return empty array on JSON error or if not an array
    }
    return $data; // Return decoded data
}

function writeJsonFile($filePath, $data) {
    // Encode data to JSON with pretty printing and unescaped slashes for readability
    $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($jsonData === false) {
        error_log("writeJsonFile: JSON encoding failed for " . $filePath . ": " . json_last_error_msg());
        return false; // Indicate failure
    }
    // Write data to file with exclusive lock to prevent race conditions
    if (file_put_contents($filePath, $jsonData, LOCK_EX) === false) {
        error_log("writeJsonFile: Failed to write to file " . $filePath);
        return false; // Indicate failure
    }
    return true; // Indicate success
}

// --- Action Handling ---
$action = $_GET['action'] ?? '';
$statusMessage = '';
$statusType = '';


// Check for status messages passed via GET after a redirect
if (isset($_GET['status_message'], $_GET['status_type'])) {
    $statusMessage = htmlspecialchars($_GET['status_message']);
    $statusType = htmlspecialchars($_GET['status_type']);
}

// Handle POST requests for recruiters (e.g., posting a new job)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $userRole === 'recruiter') {
    if ($action === 'post_job') {
        // Sanitize and validate input
        $title = trim($_POST['title'] ?? '');
        $company = trim($_POST['company'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $type = htmlspecialchars(trim($_POST['type'] ?? ''));
        $experience = htmlspecialchars(trim($_POST['experience'] ?? ''));
        $salary = htmlspecialchars(trim($_POST['salary'] ?? ''));
        $phones = htmlspecialchars(trim($_POST['phones'] ?? ''));
        $emails = htmlspecialchars(trim($_POST['emails'] ?? ''));
        $vacant_positions = intval($_POST['vacant_positions'] ?? 1);

        // Server-side validation
        if (empty($title) || empty($company) || empty($location) || empty($description)) {
            $statusMessage = 'Job Title, Company, Location, and Description are required.';
            $statusType = 'error';
        } else {
            $jobsFilePath = __DIR__ . '/data/jobs.json';
            $allJobs = readJsonFile($jobsFilePath); // Read existing jobs

            // Generate a unique ID for the new job
            $newJobId = 'job_' . uniqid() . '_' . time();
            $currentTimestamp = time();

            // Construct the new job array
            $newJob = [
                'id' => $newJobId,
                'title' => htmlspecialchars($title),
                'company' => htmlspecialchars($company),
                'location' => htmlspecialchars($location),
                'description' => htmlspecialchars($description),
                // Generate a simple AI summary from the description
                'ai_summary' => substr(htmlspecialchars($description), 0, 300) . (strlen($description) > 300 ? '...' : ''),
                'type' => $type,
                'experience' => $experience,
                'salary' => $salary,
                'phones' => $phones,
                'emails' => $emails,
                'vacant_positions' => $vacant_positions,
                'posted_on' => date('Y-m-d H:i:s', $currentTimestamp),
                'posted_on_unix_ts' => $currentTimestamp,
                'posted_by_id' => $userId, // Link to the recruiter's user ID
                'views' => 0, // Initialize views
                'shares' => 0 // Initialize shares
            ];

            array_unshift($allJobs, $newJob); // Add the new job to the beginning of the array
            
            if (writeJsonFile($jobsFilePath, $allJobs)) { // Attempt to write to file
                $statusMessage = 'Job posted successfully!';
                $statusType = 'success';
            } else {
                $statusMessage = 'Failed to save job post. Please try again.';
                $statusType = 'error';
            }
            
            // Redirect to the profile page with status message to prevent form resubmission
            header('Location: profile.php?status_message=' . urlencode($statusMessage) . '&status_type=' . urlencode($statusType) . '&action=my_posted_jobs');
            exit; // Terminate script execution after redirect
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $userRole === 'jobseeker') {
    if ($action === 'update_jobseeker_profile') {
        // Retrieve and sanitize basic information from POST
        $phone = htmlspecialchars(trim($_POST['phone'] ?? ''));
        $address = htmlspecialchars(trim($_POST['address'] ?? ''));
        $linkedin_profile = htmlspecialchars(trim($_POST['linkedin_profile'] ?? ''));
        $skills = htmlspecialchars(trim($_POST['skills'] ?? '')); // Assuming comma-separated for now
        $experience_summary = htmlspecialchars(trim($_POST['experience_summary'] ?? ''));

        // Load all users to find and update the current jobseeker's profile
        $usersFilePath = __DIR__ . '/data/users.json';
        $allUsers = readJsonFile($usersFilePath);
        $userFound = false;

        $profileUpdateSuccess = false; // Flag to track if basic profile data was updated
        foreach ($allUsers as &$user) { // Use reference to modify the array directly
            if ($user['id'] === $userId) {
                $user['phone'] = $phone;
                $user['address'] = $address;
                $user['linkedin_profile'] = $linkedin_profile;
                $user['skills'] = array_map('trim', explode(',', $skills)); // Store skills as an array
                $user['experience_summary'] = $experience_summary;
                $userFound = true;
                $profileUpdateSuccess = true; // Assume basic profile update is successful

                // Handle resume upload
                if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) { // File was successfully uploaded
                    $fileTmpPath = $_FILES['resume']['tmp_name'];
                    $fileName = $_FILES['resume']['name'];
                    $fileSize = $_FILES['resume']['size'];
                    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                    $allowedExtensions = ['pdf', 'doc', 'docx'];
                    $maxFileSize = 5 * 1024 * 1024; // 5 MB

                    if (!in_array($fileExt, $allowedExtensions)) { // Invalid file type
                        $statusMessage = 'Profile updated, but resume upload failed: Invalid file type. Only PDF, DOC, DOCX are allowed.';
                        $statusType = 'error';
                        error_log("Resume upload failed for user {$userId}: Invalid file type '{$fileExt}'.");
                    } elseif ($fileSize > $maxFileSize) { // File too large
                        $statusMessage = 'Profile updated, but resume upload failed: File size exceeds ' . ($maxFileSize / (1024 * 1024)) . 'MB limit.';
                        $statusType = 'error';
                        error_log("Resume upload failed for user {$userId}: File size '{$fileSize}' exceeds limit.");
                    } else {
                        $newFileName = 'resume_' . $userId . '_' . time() . '.' . $fileExt;
                        $uploadDir = __DIR__ . '/data/resumes/';
                        // Ensure upload directory exists and is writable
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true); // Create recursively with 0755 permissions
                        }
                        // Check if directory is writable
                        if (!is_writable($uploadDir)) {
                            $statusMessage = 'Profile updated, but resume upload failed: Upload directory is not writable. Contact administrator.';
                            $statusType = 'error';
                            error_log("Resume upload failed for user {$userId}: Upload directory not writable: {$uploadDir}");
                        } else if (move_uploaded_file($fileTmpPath, $uploadPath)) {
                            $user['resume_path'] = 'data/resumes/' . $newFileName; // Store relative path
                            $statusMessage = 'Profile updated and resume uploaded successfully!';
                            $statusType = 'success';
                            error_log("Resume uploaded successfully for user {$userId} to {$uploadPath}.");
                        } else {
                            $statusMessage = 'Profile updated, but resume upload failed: Could not move uploaded file. Check server logs for details.';
                            $statusType = 'error';
                            error_log("Resume upload failed for user {$userId}: move_uploaded_file failed. Temp: {$fileTmpPath}, Dest: {$uploadPath}. PHP error: " . error_get_last()['message'] ?? 'Unknown error');
                        }
                    }
                } elseif (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_NO_FILE) {
                    // No file was selected for upload. This is not an error, just means no resume update.
                    // The $profileUpdateSuccess flag will determine the message.
                    if ($profileUpdateSuccess) {
                        $statusMessage = 'Profile updated successfully!';
                        $statusType = 'success';
                    }
                } elseif (isset($_FILES['resume']) && $_FILES['resume']['error'] !== UPLOAD_ERR_OK) { // An actual PHP upload error occurred
                    $uploadErrorCode = $_FILES['resume']['error'];
                    $errorMessage = 'An unexpected error occurred during upload.';
                    switch ($uploadErrorCode) {
                        case UPLOAD_ERR_INI_SIZE:
                            $errorMessage = 'The uploaded file exceeds the upload_max_filesize directive in php.ini.';
                            break;
                        case UPLOAD_ERR_FORM_SIZE:
                            $errorMessage = 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.';
                            break;
                        case UPLOAD_ERR_PARTIAL:
                            $errorMessage = 'The uploaded file was only partially uploaded.';
                            break;
                        case UPLOAD_ERR_NO_TMP_DIR:
                            $errorMessage = 'Missing a temporary folder.';
                            break;
                        case UPLOAD_ERR_CANT_WRITE:
                            $errorMessage = 'Failed to write file to disk.';
                            break;
                        case UPLOAD_ERR_EXTENSION:
                            $errorMessage = 'A PHP extension stopped the file upload.';
                            break;
                        // UPLOAD_ERR_NO_FILE (4) is handled by the previous elseif
                        default:
                            $errorMessage = 'An unknown error occurred during upload.';
                            break;
                    }
                    $statusMessage = 'Profile updated, but resume upload failed: ' . $errorMessage . ' (Error code: ' . $uploadErrorCode . ')';
                    $statusType = 'error';
                    error_log("Resume upload failed for user {$userId}: PHP upload error code {$uploadErrorCode} - {$errorMessage}");
                } else { // This else block catches cases where $_FILES['resume'] might not be set at all, or other unexpected scenarios
                    if ($profileUpdateSuccess) {
                        $statusMessage = 'Profile updated successfully!';
                        $statusType = 'success';
                    }
                }
                break; // Exit foreach loop after finding and updating user
            }
        }
        // Save updated users data regardless of resume upload success/failure, as basic info might have changed
        if (!writeJsonFile($usersFilePath, $allUsers)) {
            $statusMessage = 'Failed to save profile data. Please try again.';
            $statusType = 'error';
            error_log("Failed to write users.json for user {$userId} during profile update.");
        }

        header('Location: profile.php?status_message=' . urlencode($statusMessage) . '&status_type=' . urlencode($statusType) . '&action=my_profile_info');
        exit;
    }
}

// --- Data Loading and Pre-processing for Efficiency ---
$allJobs = readJsonFile(__DIR__ . '/data/jobs.json'); // Load all job data
$allApplications = readJsonFile(__DIR__ . '/data/applications.json'); // Load all application data
$allUsers = readJsonFile(__DIR__ . '/data/users.json'); // Load all user data

// Create lookup tables for faster data retrieval by ID
$jobsById = array_column($allJobs, null, 'id'); // Map job IDs to their full job data
$usersById = array_column($allUsers, null, 'id'); // Map user IDs to their full user data

// --- Role-Specific Data Preparation ---
$jobseekerData = [];
$recruiterData = [];

if ($userRole === 'jobseeker') {
    // Filter applications to only those made by the current jobseeker
    $myApplications = array_filter($allApplications, function($app) use ($userId) {
        return isset($app['user_id']) && $app['user_id'] === $userId;
    });

    // For each application, retrieve the full job details
    foreach ($myApplications as $application) {
        $jobId = $application['job_id'] ?? null;
        if ($jobId && isset($jobsById[$jobId])) {
            $jobDetails = $jobsById[$jobId];
            // Add the application timestamp to the job details for display
            $jobDetails['applied_on'] = $application['timestamp'] ?? time();
            $jobseekerData[] = $jobDetails;
        }
    }
    // Sort the jobseeker's applied jobs by application date, most recent first
    usort($jobseekerData, function($a, $b) {
        return ($b['applied_on'] ?? 0) <=> ($a['applied_on'] ?? 0);
    });

} elseif ($userRole === 'recruiter') {
    // Filter jobs to only those posted by the current recruiter
    $myPostedJobs = array_filter($allJobs, function($job) use ($userId) {
        return isset($job['posted_by_id']) && $job['posted_by_id'] === $userId;
    });

    // Pre-group all applications by job_id for efficient lookup
    $applicationsByJobId = [];
    foreach ($allApplications as $app) {
        if (isset($app['job_id'])) {
            $applicationsByJobId[$app['job_id']][] = $app;
        }
    }

    // For each job posted by the recruiter, find its applicants
    foreach ($myPostedJobs as $job) {
        $jobId = $job['id'];
        $job['applicants'] = []; // Initialize applicants array for this job
        if (isset($applicationsByJobId[$jobId])) {
            foreach ($applicationsByJobId[$jobId] as $application) {
                $applicantId = $application['user_id'] ?? null;
                if ($applicantId && isset($usersById[$applicantId])) {
                    $applicantInfo = $usersById[$applicantId]; // Get full applicant user data
                    $job['applicants'][] = [
                        'name' => $applicantInfo['username'] ?? 'Unknown Applicant',
                        'email' => $applicantInfo['email'] ?? 'N/A',
                        'applied_on' => $application['timestamp'] ?? time()
                    ];
                }
            }
        }
        $recruiterData[] = $job; // Add the job with its applicants to recruiterData
    }
    // Sort the recruiter's posted jobs by their posted date, most recent first
    usort($recruiterData, function($a, $b) {
        return ($b['posted_on_unix_ts'] ?? 0) <=> ($a['posted_on_unix_ts'] ?? 0);
    });
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= ucfirst($userRole) ?> Profile - Job Hunt</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="/data/images/logo.png">
    <style>
        :root {
            --primary-color: #005fa3;
            --secondary-color: #e67e22;
            --light-gray: #f4f7f6;
            --border-color: #e0e0e0;
            --card-bg: #ffffff;
            --text-color: #333;
            --text-light: #666;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: var(--light-gray);
            color: var(--text-color);
            margin: 0;
            padding: 0;
            line-height: 1.6;
        }
        .header {
            background: var(--card-bg);
            padding: 15px 40px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
        }
        .header .logo {
            font-size: 1.5em;
            font-weight: bold;
            color: var(--primary-color);
            text-decoration: none;
        }
        .header .nav-links a {
            text-decoration: none;
            color: var(--primary-color);
            font-weight: 500;
            margin-left: 25px;
            transition: color 0.2s;
        }
        .header .nav-links a:hover {
            color: var(--secondary-color);
        }
        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        h1, h2 {
            color: var(--primary-color);
            border-bottom: 2px solid var(--secondary-color);
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .welcome-message {
            font-size: 1.5em;
            margin-bottom: 40px;
            color: var(--text-light);
        }
        .welcome-message strong {
            color: var(--primary-color);
        }
        .profile-section {
            background: var(--card-bg);
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.07);
            margin-bottom: 30px;
        }
        .job-list {
            list-style: none;
            padding: 0;
        }
        .job-item {
            border: 1px solid var(--border-color);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            transition: box-shadow 0.2s, transform 0.2s;
        }
        .job-item:hover {
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transform: translateY(-3px);
        }
        .job-item h3 {
            margin: 0 0 10px;
            font-size: 1.2em;
        }
        .job-item h3 a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
        }
        .job-item h3 a:hover {
            text-decoration: underline;
        }
        .job-meta {
            color: var(--text-light);
            font-size: 0.9em;
            margin-bottom: 10px;
        }
        .job-meta span {
            margin-right: 15px;
        }
        .applicant-list {
            margin-top: 20px;
            border-top: 1px dashed #ccc;
            padding-top: 20px;
        }
        .applicant-list h4 {
            margin-top: 0;
            margin-bottom: 15px;
            color: var(--text-color);
        }
        .applicant-list ul {
            list-style-type: none;
            padding-left: 0;
        }
        .applicant-list li {
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .applicant-list li:last-child {
            border-bottom: none;
        }
        .no-data-message {
            text-align: center;
            padding: 40px;
            background-color: #fafafa;
            border-radius: 8px;
            color: var(--text-light);
        }
        .no-data-message a {
            color: var(--secondary-color);
            font-weight: bold;
        }
        .button-post-job {
            display: inline-block;
            padding: 12px 25px;
            background-color: var(--secondary-color);
            color: white;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            transition: background-color 0.2s;
            margin-bottom: 20px;
        }
        .button-post-job:hover {
            background-color: #d35400;
        }
        /* Form Specific Styles */
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: var(--text-color);
        }
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="number"],
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            font-size: 1em;
            box-sizing: border-box; /* Include padding and border in the element's total width and height */
        }
        .form-group textarea {
            resize: vertical; /* Allow vertical resizing */
        }
        .status-message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: bold;
            text-align: center;
        }
        .status-message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #badbcc;
        }
        .status-message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        /* --- New Share Button Styles --- */
        .job-share-actions {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #f0f0f0;
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap; /* Allow buttons to wrap on smaller screens */
        }
        .share-button {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 4px;
            color: white !important;
            text-decoration: none;
            font-size: 0.85em;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        .share-button:hover {
            opacity: 0.85;
        }
        .share-button.whatsapp { background-color: #25D366; }
        .share-button.telegram { background-color: #0088cc; }
        .share-button.copy-link { background-color: #777; }
    </style>
</head>
<body>

<header class="header">
    <a href="index.php" class="logo">Job Hunt</a>
    <div class="nav-links">
        <a href="index.php">Back to Listings</a>
        <!-- <a href="auth.php?action=logout">Logout</a> -->
    </div>
</header>

<div class="container">
    <p class="welcome-message">Welcome back, <strong><?= htmlspecialchars($userName) ?></strong>!</p>

    <?php // Display status messages (success/error) ?>
    <?php if ($statusMessage): ?>
        <div class="status-message <?= $statusType ?>">
            <?= htmlspecialchars($statusMessage) ?>
        </div>
    <?php endif; ?>

    <?php // Jobseeker Profile Section ?>
    <?php if ($userRole === 'jobseeker'): ?>
    <div class="profile-section">
        <h2>My Applied Jobs</h2>
        <?php if (empty($jobseekerData)): ?>
            <p class="no-data-message">You haven't applied for any jobs yet. <a href="index.php">Find jobs now!</a></p>
        <?php else: ?>
            <ul class="job-list">
                <?php foreach ($jobseekerData as $job): ?>
                <li class="job-item">
                    <h3><a href="index.php?job_id=<?= htmlspecialchars($job['id']) ?>" target="_blank"><?= htmlspecialchars($job['title']) ?></a></h3>
                    <div class="job-meta">
                        <span><strong>Company:</strong> <?= htmlspecialchars($job['company']) ?></span>
                        <span><strong>Location:</strong> <?= htmlspecialchars($job['location']) ?></span>
                    </div>
                    <p><strong>Applied on:</strong> <?= date('F j, Y', $job['applied_on']) ?></p>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div class="profile-section">
        <h2>My Profile Information</h2>
        <?php
        // Get current jobseeker's data for pre-filling the form
        $currentJobseeker = $usersById[$userId] ?? [];
        $currentPhone = $currentJobseeker['phone'] ?? '';
        $currentAddress = $currentJobseeker['address'] ?? '';
        $currentLinkedin = $currentJobseeker['linkedin_profile'] ?? '';
        $currentResumePath = $currentJobseeker['resume_path'] ?? '';
        $currentSkills = implode(', ', $currentJobseeker['skills'] ?? []);
        $currentExperienceSummary = $currentJobseeker['experience_summary'] ?? '';
        ?>
        <form method="POST" action="profile.php?action=update_jobseeker_profile" enctype="multipart/form-data">
            <div class="form-group">
                <label for="username">Name:</label>
                <input type="text" id="username" name="username" value="<?= htmlspecialchars($userName) ?>" readonly>
            </div>
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($currentJobseeker['email'] ?? '') ?>" readonly>
            </div>
            <div class="form-group">
                <label for="phone">Phone Number:</label>
                <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($currentPhone) ?>">
            </div>
            <div class="form-group">
                <label for="address">Address:</label>
                <input type="text" id="address" name="address" value="<?= htmlspecialchars($currentAddress) ?>">
            </div>
            <div class="form-group">
                <label for="linkedin_profile">LinkedIn Profile URL:</label>
                <input type="text" id="linkedin_profile" name="linkedin_profile" value="<?= htmlspecialchars($currentLinkedin) ?>">
            </div>
            <div class="form-group">
                <label for="skills">Skills (comma-separated):</label>
                <input type="text" id="skills" name="skills" value="<?= htmlspecialchars($currentSkills) ?>">
            </div>
            <div class="form-group">
                <label for="experience_summary">Experience Summary:</label>
                <textarea id="experience_summary" name="experience_summary" rows="4"><?= htmlspecialchars($currentExperienceSummary) ?></textarea>
            </div>
            <div class="form-group">
                <label for="resume">Upload Resume (PDF, DOC, DOCX - Max 5MB):</label>
                <input type="file" id="resume" name="resume" accept=".pdf,.doc,.docx">
                <?php if (!empty($currentResumePath)): ?>
                    <p style="margin-top: 5px; font-size: 0.9em;">
                        Current Resume: <a href="<?= htmlspecialchars($currentResumePath) ?>" target="_blank">View Current Resume</a>
                        <span style="color: #666;"> (Upload new to replace)</span>
                    </p>
                <?php else: ?>
                    <p style="margin-top: 5px; font-size: 0.9em; color: #666;">No resume uploaded yet.</p>
                <?php endif; ?>
            </div>
            <button type="submit" class="button-post-job" style="background-color: var(--primary-color);">Update Profile</button>
        </form>
    </div>
    <?php endif; ?>

    <?php // Recruiter Profile Section ?>
    <?php if ($userRole === 'recruiter'): ?>
    <div class="profile-section">
        <?php // Display job posting form if action is 'post_job_form' ?>
        <?php if ($action === 'post_job_form'): ?>
        <h2>Post a New Job</h2>
        <form method="POST" action="profile.php?action=post_job">
            <div class="form-group">
                <label for="title">Job Title:</label>
                <input type="text" id="title" name="title" required>
            </div>
            <div class="form-group">
                <label for="company">Company:</label>
                <input type="text" id="company" name="company" required>
            </div>
            <div class="form-group">
                <label for="location">Location:</label>
                <input type="text" id="location" name="location" required>
            </div>
            <div class="form-group">
                <label for="description">Description:</label>
                <textarea id="description" name="description" rows="5" required></textarea>
            </div>
            <div class="form-group">
                <label for="type">Job Type:</label>
                <select id="type" name="type">
                    <option value="Full-time">Full-time</option>
                    <option value="Part-time">Part-time</option>
                    <option value="Remote">Remote</option>
                    <option value="Onsite">Onsite</option>
                    <option value="Hybrid">Hybrid</option>
                    <option value="Internship">Internship</option>
                </select>
            </div>
            <div class="form-group">
                <label for="experience">Experience (e.g., Fresher, 2-5 years):</label>
                <input type="text" id="experience" name="experience">
            </div>
            <div class="form-group">
                <label for="salary">Salary (e.g., AED 5,000 - 8,000):</label>
                <input type="text" id="salary" name="salary">
            </div>
            <div class="form-group">
                <label for="phones">Phone Numbers (comma-separated):</label>
                <input type="text" id="phones" name="phones">
            </div>
            <div class="form-group">
                <label for="emails">Email Addresses (comma-separated):</label>
                <input type="email" id="emails" name="emails">
            </div>
            <div class="form-group">
                <label for="vacant_positions">Vacant Positions:</label>
                <input type="number" id="vacant_positions" name="vacant_positions" value="1" min="1">
            </div>
            <button type="submit" class="button-post-job">Submit Job Post</button>
        </form>
        <?php // Display recruiter's posted jobs if action is not 'post_job_form' ?>
        <?php else: ?>
        <h2>My Posted Jobs</h2>
        <a href="profile.php?action=post_job_form" class="button-post-job">
            Post a New Job
        </a>
        <?php if (empty($recruiterData)): ?>
            <p class="no-data-message">You haven't posted any jobs yet.</p>
        <?php else: ?>
            <ul class="job-list">
                <?php foreach ($recruiterData as $job): ?>
                <li class="job-item">
                    <h3><a href="index.php?job_id=<?= htmlspecialchars($job['id']) ?>" target="_blank"><?= htmlspecialchars($job['title']) ?></a></h3>
                    <div class="job-meta">
                        <span><strong>Status:</strong> Active</span>
                        <span><strong>Posted on:</strong> <?= htmlspecialchars($job['posted_on']) ?></span>
                    </div>
                    
                    <?php
                        // --- New: Prepare share links for each job ---
                        $jobShareUrl = rtrim(APP_BASE_URL, '/') . '/index.php?job_id=' . htmlspecialchars($job['id']);
                        $shareMessage = "*Job Opportunity: " . htmlspecialchars($job['title']) . "* at " . htmlspecialchars($job['company']) . ".\n\n" .
                                     "Find out more and apply here:\n" . $jobShareUrl;
                        $whatsappLink = "https://api.whatsapp.com/send?text=" . urlencode($shareMessage);
                        $telegramLink = "https://t.me/share/url?url=" . urlencode($jobShareUrl) . "&text=" . urlencode($shareMessage);
                    ?>
                    <div class="job-share-actions">
                        <strong>Share:</strong>
                        <a href="<?= $whatsappLink ?>" class="share-button whatsapp" target="_blank" rel="noopener noreferrer">WhatsApp</a>
                        <a href="<?= $telegramLink ?>" class="share-button telegram" target="_blank" rel="noopener noreferrer">Telegram</a>
                        <button class="share-button copy-link" data-url="<?= $jobShareUrl ?>">Copy Link</button>
                    </div>

                    <div class="applicant-list">
                        <h4>Applicants (<?= count($job['applicants']) ?>)</h4>
                        <?php if (empty($job['applicants'])): ?>
                            <p>No applicants yet for this position.</p>
                        <?php else: ?>
                            <ul>
                                <?php foreach ($job['applicants'] as $applicant): ?>
                                    <li>
                                        <strong><?= htmlspecialchars($applicant['name']) ?></strong> applied on <?= date('M d, Y', $applicant['applied_on']) ?>
                                        <br> Email: <a href="mailto:<?= htmlspecialchars($applicant['email']) ?>"><?= htmlspecialchars($applicant['email']) ?></a>
                                        <?php if (!empty($usersById[$applicant['user_id']]['phone'] ?? '')): ?>
                                            <br> Phone: <a href="tel:<?= htmlspecialchars($usersById[$applicant['user_id']]['phone']) ?>"><?= htmlspecialchars($usersById[$applicant['user_id']]['phone']) ?></a>
                                        <?php endif; ?>
                                        <?php if (!empty($usersById[$applicant['user_id']]['linkedin_profile'] ?? '')): ?>
                                            <br> LinkedIn: <a href="<?= htmlspecialchars($usersById[$applicant['user_id']]['linkedin_profile']) ?>" target="_blank">Profile</a>
                                        <?php endif; ?>
                                        <?php if (!empty($usersById[$applicant['user_id']]['resume_path'] ?? '')): ?>
                                            <br> Resume: <a href="<?= htmlspecialchars($usersById[$applicant['user_id']]['resume_path']) ?>" target="_blank">Download</a>
                                        <?php endif; ?>
                                        <?php if (!empty($usersById[$applicant['user_id']]['address'] ?? '')): ?>
                                            <br> Address: <?= htmlspecialchars($usersById[$applicant['user_id']]['address']) ?>
                                        <?php endif; ?>
                                        <?php if (!empty($usersById[$applicant['user_id']]['skills'] ?? '')): ?>
                                            <br> Skills: <?= htmlspecialchars(implode(', ', $usersById[$applicant['user_id']]['skills'])) ?>
                                        <?php endif; ?>
                                        <?php if (!empty($usersById[$applicant['user_id']]['experience_summary'] ?? '')): ?>
                                            <br> Experience Summary: <?= htmlspecialchars($usersById[$applicant['user_id']]['experience_summary']) ?>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php endif; // This closes the `else` for the `if ($action === 'post_job_form')` ?>
    </div>
    <?php endif; ?>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- JavaScript for "Copy Link" buttons with fallback for non-secure contexts ---

    function fallbackCopyTextToClipboard(text, button) {
        const textArea = document.createElement("textarea");
        textArea.value = text;
        
        // Avoid scrolling to bottom
        textArea.style.top = "0";
        textArea.style.left = "0";
        textArea.style.position = "fixed";

        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();

        try {
            document.execCommand('copy');
            const originalText = button.textContent;
            button.textContent = 'Copied!';
            button.style.backgroundColor = '#28a745'; // Green feedback
            setTimeout(() => {
                button.textContent = originalText;
                button.style.backgroundColor = '#777'; // Revert color
            }, 2000);
        } catch (err) {
            console.error('Fallback: Unable to copy', err);
            alert('Failed to copy link.');
        }

        document.body.removeChild(textArea);
    }

    const copyButtons = document.querySelectorAll('.copy-link');
    copyButtons.forEach(button => {
        button.addEventListener('click', function() {
            const urlToCopy = this.getAttribute('data-url');

            if (!navigator.clipboard) {
                fallbackCopyTextToClipboard(urlToCopy, this);
                return;
            }
            // If navigator.clipboard is available, use it
            navigator.clipboard.writeText(urlToCopy).catch(err => {
                console.error('Async copy failed, trying fallback: ', err);
                fallbackCopyTextToClipboard(urlToCopy, this); // Try fallback on error
            });;
        });
    });
});
</script>
</body>
</html>
<?php
ob_end_flush();
?>
<!-- End of profile.php -->
