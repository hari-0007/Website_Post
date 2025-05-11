<?php

// admin/includes/config.php

// Set timezone
date_default_timezone_set('Asia/Kolkata');

// File Paths (relative to the root of your application, assuming admin/ is a subdirectory)
$usersFilename = __DIR__ . '/../../data/user.json'; // Path to data/user.json
$jobsFilename = __DIR__ . '/../../data/jobs.json'; // Path to data/jobs.json
$viewCounterFile = __DIR__ . '/../../data/view_count.txt'; // Path to data/view_count.txt
$feedbackFilename = __DIR__ . '/../../data/feedback.json'; // Path to data/feedback.json


// Your website URL (used in generated message)
$siteUrl = 'http://localhost:8000/index.php'; // <--- REPLACE with your website URL

?>
