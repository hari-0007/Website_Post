<?php
session_start();
// Set the default timezone to Indian Time (IST) for accurate date filtering
date_default_timezone_set('Asia/Kolkata');

// Path to your jobs data file
// Adjust the path relative to where this script is located
$filename = __DIR__ . '/data/jobs.json'; // Assuming data/ is in the same directory as index.php

$phpJobsArray = []; // For PHP's internal filtering and display
$jobsForJS = [];    // For passing to JavaScript with pre-calculated Unix timestamps

if (file_exists($filename)) {
    $jsonData = file_get_contents($filename);
    $decodedJobs = json_decode($jsonData, true);
    if ($decodedJobs === null) { // Handle malformed JSON
        $decodedJobs = [];
        error_log("Frontend Error: Error decoding jobs.json: " . json_last_error_msg());
    }
    $phpJobsArray = $decodedJobs; // Use for PHP filtering

    foreach ($decodedJobs as $job) {
        $jobCopy = $job; // Work on a copy
        // Ensure posted_on_unix_ts is available or calculate it for JS
        if (!isset($jobCopy['posted_on_unix_ts']) || !is_numeric($jobCopy['posted_on_unix_ts']) || $jobCopy['posted_on_unix_ts'] <= 0) {
            if (isset($jobCopy['posted_on']) && is_string($jobCopy['posted_on'])) {
                $unix_ts = strtotime($jobCopy['posted_on']);
                $jobCopy['posted_on_unix_ts'] = ($unix_ts === false) ? 0 : $unix_ts;
            } else {
                $jobCopy['posted_on_unix_ts'] = 0; // Default if no valid posted_on string
            }
        }
        $jobsForJS[] = $jobCopy;
    }
} else {
     error_log("Frontend Error: Job data file not found: " . $filename);
}


// Feedback alert (Remains the same)
if (!empty($_SESSION['feedback_alert'])) {
    $msg = $_SESSION['feedback_alert'];
    echo "<script>alert(" . json_encode($msg) . ");</script>";
    unset($_SESSION['feedback_alert']);
}

// Initialize $filteredJobs with all jobs from $phpJobsArray for PHP logic
$filteredJobs = $phpJobsArray;


// 1. Apply Search Filter (using $phpJobsArray - should apply to the initial array)
$search = isset($_GET['search']) ? strtolower(trim($_GET['search'])) : '';
if ($search !== '') {
    $tempJobs = [];
    // Iterate over the original $phpJobsArray for search
    foreach ($phpJobsArray as $job) {
        if (
            (isset($job['title']) && is_string($job['title']) && strpos(strtolower($job['title']), $search) !== false) ||
            (isset($job['company']) && is_string($job['company']) && strpos(strtolower($job['company']), $search) !== false) ||
            (isset($job['location']) && is_string($job['location']) && strpos(strtolower($job['location']), $search) !== false)
        ) {
            $tempJobs[] = $job;
        }
    }
    $filteredJobs = $tempJobs; // Update $filteredJobs with search results
}


// 2. Apply Date Filter (operates on the potentially search-filtered $filteredJobs)
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$currentDate = time(); // PHP current Unix timestamp (seconds)

if ($filter !== 'all') {
    $tempJobs = [];
    $daysToFilter = 0;
    if ($filter === '30') $daysToFilter = 30;
    elseif ($filter === '7') $daysToFilter = 7;
    elseif ($filter === '1') $daysToFilter = 1;

    if ($daysToFilter > 0) {
        $cutoffDate = $currentDate - ($daysToFilter * 24 * 60 * 60); // Cutoff in seconds
        // Iterate over the current $filteredJobs array (already includes search filter)
        foreach ($filteredJobs as $job) {
            // Use the consistent 'posted_on_unix_ts' if available, fallback to strtotime if needed
             $jobTimestamp = $job['posted_on_unix_ts'] ?? (isset($job['posted_on']) && is_string($job['posted_on']) ? strtotime($job['posted_on']) : 0);


            if ($jobTimestamp !== false && $jobTimestamp > 0 && $jobTimestamp >= $cutoffDate) {
                $tempJobs[] = $job;
            }
        }
        $filteredJobs = $tempJobs; // Update $filteredJobs with date results
    }
}

// --- Sorting (Jobs are already sorted newest first from how they are added to JSON) ---
// No need to explicitly sort here if the JSON file maintains newest-first order,
// and we removed array_reverse below. If the JSON file order isn't guaranteed,
// you would add a usort here to sort by 'posted_on_unix_ts' descending:
/*
usort($filteredJobs, function($a, $b) {
    $ts_a = $a['posted_on_unix_ts'] ?? (strtotime($a['posted_on'] ?? '') ?: 0);
    $ts_b = $b['posted_on_unix_ts'] ?? (strtotime($b['posted_on'] ?? '') ?: 0);
    return $ts_b - $ts_a; // Descending order (newest first)
});
*/


// Then: apply pagination
$limit = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// --- REMOVED array_reverse() HERE ---
// $reversedFilteredJobs = array_reverse($filteredJobs); // Removed this line

// Apply slice directly to $filteredJobs which is already sorted newest first
$pagedJobs = array_slice($filteredJobs, $offset, $limit);


$totalPages = ceil(count($filteredJobs) / $limit);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>UAE Job Portal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; margin:0; padding:0; background:#f9f9f9; color:#333; }
        .container { width:100%; }

        .title-card {
            background: linear-gradient(135deg, #005fa3, #005fa3);
            color:#fff;
            padding:30px 20px;
            margin-bottom:25px;
            text-align:center;
            box-shadow:0 4px 12px rgba(0,0,0,0.15);
            width: 100%;
        }
        .title-card h1 { margin:0 0 10px; font-size:28px; }
        .title-card p { font-size:16px; opacity:.95; margin-bottom:20px; }
        .title-card .join-buttons { display:inline-flex; gap:10px; flex-wrap:wrap; justify-content:center; }
        .title-card .join-buttons a { color:#fff; text-decoration:none; padding:10px 16px; border-radius:5px; background:rgba(0,0,0,0.2); transition:0.3s; }
        .title-card .join-buttons a.whatsapp { background:#25D366; }
        .title-card .join-buttons a.telegram  { background:#0088cc; }
        .title-card .join-buttons a:hover { opacity:.8; }

        .content-wrapper { display:flex; gap:20px; padding:0 20px; }

        .sidebar { width:220px; background:#fff; padding:20px; border-radius:8px; box-shadow:0 2px 6px rgba(0,0,0,0.05); flex-shrink:0; }
        .sidebar h4 {
            margin-top: 20px;
            margin-bottom: 15px;
            color: #3498db;
            font-size: 18px;
        }
        .sidebar h4:first-of-type {
            margin-top: 0;
        }
        .sidebar a { display:block; color:#333; text-decoration:none; margin:8px 0; font-weight:500; }
        .sidebar a:hover { color:#2980b9; }

        .search-bar {
          margin:0 auto 20px auto;
          padding: 0;
          max-width: 800px;
        }
        .search-bar form { display: flex; width: 100%; gap: 10px; margin-bottom:0px; }
        .search-bar input[type="text"] { flex-grow: 1; padding:10px; border:1px solid #ccc; border-radius:4px; font-size: 1rem; }
        .search-bar button { background: #005fa3; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-size: 1rem; }
        .search-bar button:hover { background: #004577; }

        main {
            flex: 1;
            min-width: 0;
        }

        /* --- Responsive Styles --- */
        @media(max-width:768px){
            .content-wrapper { flex-direction: column; padding: 0 10px; /* Adjust padding for smaller screens */ }
            .sidebar {
                display: none; /* Hide sidebar on mobile */
            }
            .search-bar {
                 margin-top: 15px; /* Adjust margin */
                 margin-bottom: 15px;
                 padding: 0 10px; /* Add horizontal padding */
                 max-width: none; /* Allow search bar to take more width */
            }
            .search-bar form { gap: 5px; } /* Reduce gap in search bar */
            .job-card { padding: 10px; /* Adjust job card padding */ }
            .site-footer .footer-container { padding: 0 10px; /* Adjust footer padding */ }
             .title-card { padding: 20px 10px; /* Adjust title card padding */ }
        }

        @media(max-width:600px){
             .button, .search-bar button {width:100%; text-align:center;}
             .search-bar form { flex-direction: column; } /* Stack search inputs vertically */
             .search-bar input[type="text"], .search-bar button { width: 100%; } /* Full width for stacked elements */
             .title-card h1 { font-size: 24px; }
             .title-card p { font-size: 14px; }
             .modal-content { width: 95%; /* Make modal slightly wider on very small screens */ }
        }
        /* --- End Responsive Styles --- */


        .job-card { background:#fff; padding:15px; border-radius:8px; margin-bottom:20px; box-shadow:0 2px 6px rgba(0,0,0,0.05); }
        .job-card h3 { margin-top:0; }
        .job-card p { margin:10px 0; }

        .button { padding:10px 16px; background:#3498db; color:#fff; text-decoration:none; border-radius:5px; transition:0.3s; border: none; cursor:pointer;}
        .button:hover { background:#2980b9; }

        .modal { position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); display:flex; align-items:center; justify-content:center; z-index:1000; }
        .modal-content { background:#fff; padding:20px; border-radius:12px; text-align:center; position:relative; box-shadow: 0 4px 15px rgba(0,0,0,0.2); max-width: 90%; width: 400px; }
        .close { position:absolute; top:10px; right:20px; cursor:pointer; font-size:24px; }
        .join-now, .join-telegram { padding:10px 20px; border-radius:8px; text-decoration:none; display:inline-block; margin-top:10px; }
        .join-now { background:#25D366; color:#fff; }
        .join-telegram { background:#0088cc; color:#fff; }

        .feedback-message { margin-top: 10px; padding: 10px; border-radius: 5px; font-weight: lighter; font-style: italic; }
        .success { color: white; background-color: #28a745; }
        .error { color: white; background-color: #dc3545; }

        .site-footer { width:100%; background: linear-gradient(135deg, #005fa3, #005fa3); color:#fff; padding:40px 20px 20px; font-size:15px; margin-top: 30px; }
        .footer-container { display:flex; flex-wrap:wrap; justify-content:space-between; gap:30px; padding:0 20px; max-width: 1200px; margin: 0 auto; }
        .footer-column { flex:1; min-width:200px; }
        .footer-column h4 { margin-bottom:15px; }
        .footer-column p, .footer-column a { color:#f0f0f0; text-decoration:none; margin:5px 0; display:block; }
        .footer-column a:hover { opacity:0.8; }
        .footer-column input, .footer-column textarea { width:100%; padding:8px; border-radius:4px; border:1px solid rgba(255,255,255,0.5); margin-bottom:10px; background:rgba(255,255,255,0.1); color:#fff; }
        .footer-column input::placeholder, .footer-column textarea::placeholder { color:#ddd; }
        .footer-bottom { text-align:center; padding-top:20px; border-top:1px solid rgba(255,255,255,0.3); margin-top:20px; font-size:13px; }

    </style>
</head>
<body>

    <div class="container">
        <div class="title-card">
            <h1>🎯 Discover the Latest Jobs in UAE</h1>
            <p>Find fresh opportunities daily from top companies across UAE. Remote, onsite, and hybrid roles available.</p>
            <div class="join-buttons">
            <a href="https://whatsapp.com/channel/0029Vb64y42FXUuXmPWKp12k" target="_blank" class="whatsapp">Join WhatsApp</a>
                <a href="https://t.me/YOUR_TELEGRAM_CHANNEL" target="_blank" class="telegram">Join Telegram</a>
            </div>
        </div>

        <div class="content-wrapper">
            <aside class="sidebar">
                <h4>Job Filters</h4>
                <a href="?search=remote&filter=<?= urlencode($filter) ?>">💻 Remote</a>
                <a href="?search=onsite&filter=<?= urlencode($filter) ?>">🏢 Onsite</a>
                <a href="?search=hybrid&filter=<?= urlencode($filter) ?>">🌐 Hybrid</a>
                <h4>Quick Filters</h4>
                <a href="?search=full-time&filter=<?= urlencode($filter) ?>">🕐 Full-Time</a>
                <a href="?search=part-time&filter=<?= urlencode($filter) ?>">⌛ Part-Time</a>
                <a href="?search=intern&filter=<?= urlencode($filter) ?>">🎓 Internships</a>
                <a href="?search=developer&filter=<?= urlencode($filter) ?>">👨‍💻 Developer</a>
                <h4>Date Posted</h4>
                <a href="?search=<?= urlencode($search) ?>&filter=all">All (<span id="countAll">0</span>)</a>
                <a href="?search=<?= urlencode($search) ?>&filter=30">Past 30 Days (<span id="count30">0</span>)</a>
                <a href="?search=<?= urlencode($search) ?>&filter=7">Past 7 Days (<span id="count7">0</span>)</a>
                <a href="?search=<?= urlencode($search) ?>&filter=1">Past 24 Hours (<span id="count1">0</span>)</a>
            </aside>

            <main>
                <div class="search-bar">
                    <form method="GET" action="">
                        <input type="text" name="search" placeholder="Search by job title, company, or location" value="<?php echo htmlspecialchars($search); ?>">
                        <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                        <button type="submit">Search</button>
                    </form>
                </div>

                <?php if(empty($pagedJobs)): ?>
                    <p style="text-align:center; padding: 20px;">No matching jobs found for the current criteria.</p>
                <?php else: ?>
                    <?php foreach($pagedJobs as $job): ?>
                    <div class="job-card">
                        <h3><?= htmlspecialchars($job['title'] ?? 'N/A') ?></h3>
                        <strong><?= htmlspecialchars($job['company'] ?? 'N/A') ?></strong> – <?= htmlspecialchars($job['location'] ?? 'N/A') ?><br>
                        <p><?= nl2br(htmlspecialchars(substr($job['description'] ?? '',0,200))) ?>…</p>
                        <?php if(!empty($job['phones'])): ?>
                            <p><strong>📞 Phone:</strong>
                                <?php foreach(explode(',',$job['phones']) as $phone): ?>
                                    <a href="tel:<?=trim($phone)?>"><?=trim($phone)?></a>&nbsp;
                                <?php endforeach; ?>
                            </p>
                        <?php endif; ?>
                        <?php if(!empty($job['emails'])): ?>
                            <p><strong>📧 Email:</strong>
                                <?php foreach(explode(',',$job['emails']) as $email): ?>
                                    <a href="mailto:<?=trim($email)?>"><?=trim($email)?></a>&nbsp;
                                <?php endforeach; ?>
                            </p>
                        <?php endif; ?>
                        <small>Posted on <?= htmlspecialchars($job['posted_on'] ?? 'N/A') ?></small><br><br>
                        <button onclick="shareJob('<?=htmlspecialchars(addslashes($job['title'] ?? 'N/A'))?>','<?=htmlspecialchars(addslashes($job['company'] ?? 'N/A'))?>')" class="button">🔗 Share</button>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if ($totalPages > 1): ?>
                <div style="text-align:center; margin: 20px 0;">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?search=<?= urlencode($search) ?>&filter=<?= urlencode($filter) ?>&page=<?= $i ?>"
                           style="margin:0 5px; padding:5px 10px; background:<?= $i == $page ? '#005fa3' : '#ddd' ?>; color:<?= $i == $page ? '#fff' : '#000' ?>; border-radius:4px; text-decoration:none;">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <footer class="site-footer">
        <div class="footer-container">
            <div class="footer-column">
                <h4>About UAE Jobs</h4>
                <p>Your go-to portal for real-time UAE opportunities—updated daily.</p>
            </div>
            <div class="footer-column">
                <h4>Explore</h4>
                <a href="admin/login.php">👤 Admin Login</a>
                <a href="?search=remote&filter=all">💻 Remote Jobs</a>
                <a href="?search=uae&filter=all">📍 UAE Jobs</a>
                <a href="mailto:support@uaejobs.com">📩 Contact Support</a>
            </div>
            <div class="footer-column">
                <h4>Follow Channels</h4>
                <a href="https://t.me/YOUR_TELEGRAM_CHANNEL" target="_blank">📢 Telegram</a>
                <a href="https://whatsapp.com/channel/0029Vb64y42FXUuXmPWKp12k" target="_blank">📱 WhatsApp</a>
            </div>
            <div class="footer-column">
                <h4>Drop Your Message</h4>
                <form id="feedbackForm">
                    <input type="text" name="name" placeholder="Your Name" required>
                    <input type="email" name="email" placeholder="Your Email" required>
                    <textarea name="message" placeholder="Your Message" rows="3" required></textarea>
                    <div id="responseMsg" class="feedback-message" style="display: none;"></div>
                    <button type="submit" class="button">Send</button>
                </form>
            </div>
        </div>
        <div class="footer-bottom">
            &copy; <?= date('Y') ?> UAE Jobs Portal. All rights reserved.
        </div>
    </footer>

    <div id="telegramModal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close" onclick="closeTelegramModal()">&times;</span>
            <h4>Join our Telegram Channel</h4>
            <p>Stay updated with the latest job postings!</p>
            <a href="https://t.me/YOUR_TELEGRAM_CHANNEL" target="_blank" class="join-telegram button">Join Telegram</a>
        </div>
    </div>

    <div id="modal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close" onclick="closeWModal()">&times;</span>
            <h4>Join our WhatsApp Channel</h4>
            <p>Get job alerts directly on WhatsApp!</p>
            <a href="https://whatsapp.com/channel/0029Vb64y42FXUuXmPWKp12k" target="_blank" class="join-now button">Join WhatsApp</a>
        </div>
    </div>

    <script>
        // Embed all job data with PHP-generated Unix timestamps for JavaScript
        const allJobDataFromPHP = <?php echo json_encode($jobsForJS); ?>;

        let allJobPostsForCounts = []; // Renamed to avoid confusion, used specifically for counts

        function openTelegramModal(){ document.getElementById('telegramModal').style.display='flex'; }
        function closeTelegramModal(){ document.getElementById('telegramModal').style.display='none'; }
        function openWModal(){ document.getElementById('modal').style.display='flex'; }
        function closeWModal(){ document.getElementById('modal').style.display='none'; }

        function shareJob(title, company) {
            const shareText = `Check out this job at ${company}: ${title}`;
            let jobUrl = window.location.href;

            if (navigator.share) {
                navigator.share({
                    title: `${title} at ${company}`,
                    text: shareText,
                    url: jobUrl
                }).catch(err => console.log('Share canceled or error:', err));
            } else {
                const fullShareMessage = `${shareText} - ${jobUrl}`;
                try {
                    navigator.clipboard.writeText(fullShareMessage).then(() => {
                        alert("Job info copied to clipboard!");
                    }).catch(err => {
                        prompt("Copy this job info to share:", fullShareMessage);
                    });
                } catch (e) {
                    prompt("Copy this job info to share:", fullShareMessage);
                }
            }
        }

        document.getElementById('feedbackForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);
            const responseBox = document.getElementById('responseMsg');

            fetch('feedback.php', {
                method: 'POST',
                body: formData
            })
            .then(res => {
                if (!res.ok) { throw new Error(`HTTP error! status: ${res.status}`); }
                return res.json();
            })
            .then(data => {
                responseBox.innerText = data.message;
                responseBox.className = 'feedback-message ' + (data.success ? 'success' : 'error');
                responseBox.style.display = 'block';
                if (data.success) {
                    form.reset();
                }
                setTimeout(() => { responseBox.style.display = 'none'; }, 5000);
            })
            .catch(err => {
                console.error("Feedback form error:", err);
                responseBox.innerText = 'An error occurred. Please try again.';
                responseBox.className = 'feedback-message error';
                responseBox.style.display = 'block';
                setTimeout(() => { responseBox.style.display = 'none'; }, 5000);
            });
        });


        function processJobDataForCounts() {
            if (Array.isArray(allJobDataFromPHP)) {
                allJobPostsForCounts = allJobDataFromPHP.map(post => {
                    let ts_ms = 0; // Timestamp in milliseconds
                    // Use the Unix timestamp (seconds) from PHP ('posted_on_unix_ts')
                    // and convert to milliseconds for JavaScript Date operations.
                    if (post && typeof post.posted_on_unix_ts === 'number' && post.posted_on_unix_ts > 0) {
                        ts_ms = post.posted_on_unix_ts * 1000;
                    } else if (post && post.posted_on_unix_ts === 0 && post.posted_on) {
                        // This means strtotime likely failed in PHP for this date string
                         console.warn(`PHP's strtotime couldn't parse date: "${post.posted_on}" for job: "${post.title || 'Unknown'}". This job won't be included in date-filtered counts.`);
                    }
                    return {
                        // Keep original post data if needed, or just what's necessary for counts
                        // For simplicity, we just need the timestamp for filtering counts.
                        // If other post data is needed by other JS functions using this array, spread post: ...post,
                        timestamp: ts_ms
                    };
                });
            } else {
                allJobPostsForCounts = [];
                console.error("Job data from PHP (allJobDataFromPHP) is not an array or is missing.");
            }
            updateSidebarCounts();
        }

        function updateSidebarCounts() {
            if (!allJobPostsForCounts || !Array.isArray(allJobPostsForCounts)) {
                document.getElementById('countAll').innerText = '0';
                document.getElementById('count30').innerText = '0';
                document.getElementById('count7').innerText = '0';
                document.getElementById('count1').innerText = '0';
                return;
            }

            const now_ms = Date.now(); // Current time in milliseconds
            const oneDay_ms = 24 * 60 * 60 * 1000; // Milliseconds in a day

            document.getElementById('countAll').innerText = allJobPostsForCounts.length;

            // Filter posts that have a valid, non-zero millisecond timestamp
            document.getElementById('count30').innerText = allJobPostsForCounts.filter(p => p.timestamp > 0 && (now_ms - p.timestamp <= 30 * oneDay_ms)).length;
            document.getElementById('count7').innerText = allJobPostsForCounts.filter(p => p.timestamp > 0 && (now_ms - p.timestamp <= 7 * oneDay_ms)).length;
            document.getElementById('count1').innerText = allJobPostsForCounts.filter(p => p.timestamp > 0 && (now_ms - p.timestamp <= oneDay_ms)).length;
        }

        window.onload = function() {
            processJobDataForCounts(); // Process the PHP-provided data
        };
    </script>
</body>
</html>
