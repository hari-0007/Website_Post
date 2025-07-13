<?php

// admin/views/post_job_view.php - Form for posting a new job, with review step

// This file is included by dashboard.php when $requestedView is 'post_job'.

// --- NEW --- Check for successful post to show share modal
$showSharePopup = false;
$shareData = [];
if (isset($_GET['posted']) && $_GET['posted'] == '1' && isset($_SESSION['show_share_popup_data'])) {
    $showSharePopup = true;
    $shareData = $_SESSION['show_share_popup_data'];
    unset($_SESSION['show_share_popup_data']); // Unset it so it doesn't show again on refresh
}
// --- END NEW ---
// It assumes $formData is available (for pre-filling on validation errors).
// Determine if we are in review mode
$isReviewMode = isset($_GET['step']) && $_GET['step'] === 'review';

// If in review mode, get data from 'review_job_data' session
// Otherwise, get data from 'form_data' (for repopulating after validation errors on initial post)
if ($isReviewMode) {
    $formData = $_SESSION['review_job_data'] ?? [];
    // Don't unset review_job_data here, it's needed if the final submission fails and needs to show review again.
    // It will be unset in post_job.php after successful final submission or if the user navigates away from review.
} else {
    $formData = $_SESSION['form_data'] ?? [];
    unset($_SESSION['form_data']); // Clear initial form data after retrieving
}

$pageTitle = $isReviewMode ? "Review and Post Job" : "Post New Job";
$submitButtonText = $isReviewMode ? "Confirm and Post Job" : "Generate Summary & Review";
$formActionValue = $isReviewMode ? "final_post" : "initial_post";

?>
<style>
    /* Styles specific to post_job_view.php, ensuring alignment with global theme */
    .view-main-title.post-job-title { /* Specific for this view's title */
        margin-top: 0;
        margin-bottom: 25px; /* Consistent bottom margin */
        color: var(--primary-color);
        font-size: 1.75em;
        font-weight: 600;
        padding-bottom: 15px; /* Consistent padding */
        border-bottom: 2px solid var(--primary-color-lighter); /* Consistent border */
        text-align: center; /* Center the main title for forms */
    }
    .post-job-container {
        max-width: 700px; /* Wider for more content */
        margin: 20px auto;
        padding: 20px 25px;
        background-color: var(--card-bg); /* Use theme variable */
        border-radius: var(--border-radius); /* Use theme variable */
        box-shadow: var(--box-shadow); /* Use theme variable for a more prominent shadow for forms */
    }

    /* .post-job-container h3 is replaced by .view-main-title */

    .post-job-container p.form-description { /* This class is commented out in HTML, but styling it just in case */
        text-align: center;
        color: var(--text-muted); /* Use theme variable */
        margin-bottom: 25px;
        font-size: 0.95rem;
    }
    .styled-form .form-group {
        margin-bottom: 18px;
    }
    .styled-form label {
        display: block;
        font-weight: 600;
        color: var(--text-color-light); /* Match global form label */
        margin-bottom: 6px;
        font-size: 0.9rem;
    }
    .styled-form input[type="text"],
    .styled-form input[type="email"],
    .styled-form input[type="number"],
    .styled-form input[type="password"], /* Added for completeness */
    .styled-form textarea,
    .styled-form select {
        /* Fully align with global form input styles from header.php */
        width: 100%;
        padding: .5rem .75rem; /* Match global */
        border: 1px solid var(--border-color); /* Match global */
        border-radius: var(--border-radius); /* Match global */
        font-size: 0.95rem;
        color: var(--text-color); /* Match global */
        background-color: #fff; /* Match global */
        transition: border-color .2s ease-in-out, box-shadow .2s ease-in-out; /* Match global */
        appearance: none; /* Match global */
        line-height: 1.5; /* Match global */
        font-weight: 400; /* Match global */
    }
    .styled-form input[type="text"]:focus,
    .styled-form input[type="email"]:focus,
    .styled-form input[type="number"]:focus,
    .styled-form input[type="password"]:focus,
    .styled-form textarea:focus,
    .styled-form select:focus {
        /* Fully align with global focus styles */
        border-color: var(--primary-color-lighter);
        outline: none;
        box-shadow: 0 0 0 .2rem var(--primary-color-lighter);
        background-color: #fff;
    }
    .styled-form textarea {
        resize: vertical;
        min-height: 100px;
    }
    /* .styled-form .button will inherit from global .button style in header.php */
    /* Ensure the button in the form has class="button" */

    .required {
        color: var(--error-color); /* Use theme error color */
        font-weight: bold;
        margin-left: 2px;
    }
    /* Styles for the AI summary review section */
    .ai-summary-review-section {
        border: 1px solid var(--primary-color-lighter); /* Use theme variable */
        padding: 15px;
        margin-top: 20px;
        margin-bottom: 20px;
        border-radius: var(--border-radius); /* Use theme variable */
        background-color: var(--info-bg); /* Use theme info background for a subtle highlight */
    }
    .ai-summary-review-section label {
        color: var(--info-text); /* Use theme info text color */
        font-size: 1rem;
    }

    /* --- Share Modal Styles --- */
    .modal {
        display: none; /* Hidden by default */
        position: fixed; /* Stay in place */
        z-index: 1050; /* Sit on top of everything */
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto; /* Enable scroll if needed */
        background-color: rgba(0,0,0,0.6); /* Darker overlay */
    }

    .modal-content {
        background-color: #fefefe;
        margin: 10% auto;
        padding: 25px 30px;
        border: 1px solid #888;
        width: 90%;
        max-width: 550px;
        border-radius: var(--border-radius);
        box-shadow: 0 5px 20px rgba(0,0,0,0.3);
        position: relative;
        animation: animatetop 0.4s;
    }

    @keyframes animatetop {
        from {top: -300px; opacity: 0}
        to {top: 0; opacity: 1}
    }

    .close-button {
        color: #aaa;
        float: right;
        font-size: 28px;
        font-weight: bold;
        line-height: 1;
        cursor: pointer;
    }

    .close-button:hover,
    .close-button:focus {
        color: black;
        text-decoration: none;
    }

    .modal-content h2 {
        text-align: center;
        color: var(--success-color);
        border-bottom: none;
        margin-top: 0;
        margin-bottom: 10px;
    }
    
    .modal-content > p {
        text-align: center;
        color: var(--text-color-light);
        margin-bottom: 20px;
    }

    .job-details-share {
        margin-top: 15px;
        padding: 15px;
        background-color: #f9f9f9;
        border-radius: 5px;
        border: 1px solid #eee;
        text-align: left;
    }

    .job-details-share h3 {
        margin-top: 0;
        color: var(--primary-color);
        font-size: 1.2em;
    }

    .job-details-share p {
        margin: 8px 0;
        font-size: 0.95rem;
        line-height: 1.4;
    }
    
    .job-details-share p strong {
        color: var(--text-color);
    }

    .share-buttons {
        text-align: center;
        margin-top: 25px;
        display: flex;
        gap: 15px;
        justify-content: center;
    }

    .share-btn {
        display: inline-block;
        padding: 10px 20px;
        border-radius: 5px;
        color: white !important; /* Override link colors */
        text-decoration: none;
        font-weight: bold;
        transition: opacity 0.3s;
        border: none;
        cursor: pointer;
    }
    .share-btn:hover {
        opacity: 0.85;
        text-decoration: none;
    }

    .share-btn.whatsapp {
        background-color: #25D366;
    }

    .share-btn.telegram {
        background-color: #0088cc;
    }

    .share-btn.copy-link {
        background-color: var(--primary-color);
        width: 100%; /* Make it a prominent, single button */
    }
</style>

<div class="post-job-container">
    <h2 class="view-main-title post-job-title"><?= htmlspecialchars($pageTitle) ?></h2>
    <!-- <p class="form-description">
        <?php if ($isReviewMode): ?>
            Please review all details below, edit the AI-generated summary if needed, and then click "Confirm and Post Job".
        <?php else: ?>
            Fill in the details below. An AI summary will be generated for your review in the next step. Fields marked with <span class="required">*</span> are mandatory.
        <?php endif; ?>
    </p> -->

    <form action="post_job.php" method="POST" id="postJobForm" class="styled-form">
        <input type="hidden" name="action" value="<?= htmlspecialchars($formActionValue) ?>">
        
        <?php if (!$isReviewMode): // Only show these fields in initial entry mode ?>
        <div class="form-group">
            <label for="title">Job Title: <span class="required">*</span></label>
            <input type="text" id="title" name="title" required value="<?= htmlspecialchars($formData['title'] ?? '') ?>">
        </div>
        
        <div class="form-group">
            <label for="company">Company Name:</label>
            <input type="text" id="company" name="company" value="<?= htmlspecialchars($formData['company'] ?? '') ?>" placeholder="Optional">
        </div>
        
        <div class="form-group">
            <label for="location">Location:</label>
            <input type="text" id="location" name="location" value="<?= htmlspecialchars($formData['location'] ?? '') ?>">
        </div>
        
        <div class="form-group">
            <label for="vacant_positions">Number of Vacant Positions:</label>
            <input type="number" id="vacant_positions" name="vacant_positions" min="1" value="<?= htmlspecialchars($formData['vacant_positions'] ?? 1) ?>">
        </div>
        
        <div class="form-group">
            <label for="experience">Experience Level:</label>
            <select id="experience" name="experience" onchange="toggleCustomExperience(this)">
                <option value="0" <?= (($formData['experience'] ?? '0') == '0') ? 'selected' : '' ?>>No Experience / Fresher</option>
                <option value="internship" <?= (($formData['experience'] ?? '') === 'internship') ? 'selected' : '' ?>>Internship</option>
                <?php for ($i = 1; $i <= 20; $i++): // Extended to 20 years ?>
                    <option value="<?= $i ?>" <?= (isset($formData['experience']) && $formData['experience'] == $i) ? 'selected' : '' ?>><?= $i ?> year<?= $i > 1 ? 's' : '' ?></option>
                <?php endfor; ?>
                <option value="20+" <?= (isset($formData['experience']) && $formData['experience'] == '20+') ? 'selected' : '' ?>>20+ years</option>
                <option value="other" <?= (isset($formData['experience']) && $formData['experience'] === 'other') ? 'selected' : '' ?>>Other (Specify)</option>
            </select>
            <input type="text" id="custom_experience" name="custom_experience" placeholder="Specify experience (e.g., 2-3 years, Project Management)" style="display:none; margin-top:10px;" value="<?= htmlspecialchars($formData['custom_experience'] ?? '') ?>">
        </div>
        
        <div class="form-group">
            <label for="type">Job Type:</label>
            <select id="type" name="type">
                <option value="Full Time" <?= (($formData['type'] ?? 'Full Time') === 'Full Time') ? 'selected' : '' ?>>Full Time</option>
                <option value="Part Time" <?= (($formData['type'] ?? '') === 'Part Time') ? 'selected' : '' ?>>Part Time</option>
                <option value="Contract" <?= (($formData['type'] ?? '') === 'Contract') ? 'selected' : '' ?>>Contract</option>
                <option value="Internship" <?= (($formData['type'] ?? '') === 'Internship') ? 'selected' : '' ?>>Internship</option>
                <option value="Remote" <?= (($formData['type'] ?? '') === 'Remote') ? 'selected' : '' ?>>Remote</option>
                <option value="Hybrid" <?= (($formData['type'] ?? '') === 'Hybrid') ? 'selected' : '' ?>>Hybrid</option>
                <option value="Onsite" <?= (($formData['type'] ?? '') === 'Onsite') ? 'selected' : '' ?>>Onsite</option>
                <option value="Developer" <?= (($formData['type'] ?? '') === 'Developer') ? 'selected' : '' ?>>Developer</option>
            </select>
        </div>
        
        <div class="form-group">
            <label for="salary">Salary:</label>
            <input type="text" id="salary" name="salary" value="<?= htmlspecialchars($formData['salary'] ?? '') ?>" placeholder="e.g., AED 5000 - 7000, or Negotiable">
        </div>
        
        <div class="form-group">
            <label for="phones">Contact Phone(s) (comma-separated): <span class="required">*</span></label>
            <input type="text" id="phones" name="phones" value="<?= htmlspecialchars($formData['phones'] ?? '') ?>">
        </div>
        
        <div class="form-group">
            <label for="emails">Contact Email(s) (comma-separated): <span class="required">*</span></label>
            <input type="text" id="emails" name="emails" value="<?= htmlspecialchars($formData['emails'] ?? '') ?>">
        </div>       
        <div class="form-group">
            <label for="description">Key Responsibilities/Details: <span class="required">*</span></label>
            <textarea id="description" name="description" rows="8" <?= $isReviewMode ? 'readonly' : '' ?>><?= htmlspecialchars($formData['description'] ?? '') ?></textarea>
            <!-- <?php if ($isReviewMode): ?>
                <small>Original description is locked during review. Edit the AI summary below.</small>
            <?php endif; ?> -->
        </div>
        <?php else: // In Review Mode, we need to pass these values as hidden fields so they are submitted ?>
            <input type="hidden" name="title" value="<?= htmlspecialchars($formData['title'] ?? '') ?>">
            <input type="hidden" name="company" value="<?= htmlspecialchars($formData['company'] ?? '') ?>">
            <input type="hidden" name="location" value="<?= htmlspecialchars($formData['location'] ?? '') ?>">
            <input type="hidden" name="vacant_positions" value="<?= htmlspecialchars($formData['vacant_positions'] ?? 1) ?>">
            <input type="hidden" name="experience" value="<?= htmlspecialchars($formData['experience'] ?? '0') ?>">
            <?php if (isset($formData['experience']) && $formData['experience'] === 'other' && isset($formData['custom_experience'])): ?>
                <input type="hidden" name="custom_experience" value="<?= htmlspecialchars($formData['custom_experience']) ?>">
            <?php endif; ?>
            <input type="hidden" name="type" value="<?= htmlspecialchars($formData['type'] ?? 'Full Time') ?>">
            <input type="hidden" name="salary" value="<?= htmlspecialchars($formData['salary'] ?? '') ?>">
            <input type="hidden" name="phones" value="<?= htmlspecialchars($formData['phones'] ?? '') ?>">
            <input type="hidden" name="emails" value="<?= htmlspecialchars($formData['emails'] ?? '') ?>">
            <input type="hidden" name="description" value="<?= htmlspecialchars($formData['description'] ?? '') ?>">
        <?php endif; ?>


        <?php if ($isReviewMode): ?>
            <div class="form-group ai-summary-review-section">
                <label for="ai_summary">AI Generated Summary (Editable): <span class="required">*</span></label>
                <textarea id="ai_summary" name="ai_summary" rows="10" required><?= htmlspecialchars($formData['ai_summary'] ?? '') ?></textarea>
            </div>
        <?php endif; ?>
        <?php if ($isReviewMode): ?><button type="button" id="regenerate-ai-summary-btn" class="button" style="margin-left: 10px;">Regenerate AI Summary</button><?php endif; ?>
        <button type="submit" class="button"><?= htmlspecialchars($submitButtonText) ?></button>
    </form>
</div>

<?php if ($showSharePopup): ?>
<!-- Share Modal -->
<div id="shareModal" class="modal">
    <div class="modal-content">
        <span class="close-button">&times;</span>
        <h2>Job Posted Successfully!</h2>
        <p>Your job has been posted. Share it with your network!</p>
        
        <div class="job-details-share">
            <?php if (!empty($shareData['title'])): ?>
                <h3><?= htmlspecialchars($shareData['title']) ?></h3>
            <?php endif; ?>

            <?php if (!empty($shareData['company'])): ?>
                <p><strong>Company:</strong> <?= htmlspecialchars($shareData['company']) ?></p>
            <?php endif; ?>

            <?php if (!empty($shareData['vacancies'])): ?>
                <p><strong>Vacancies:</strong> <?= htmlspecialchars($shareData['vacancies']) ?></p>
            <?php endif; ?>

            <?php if (!empty($shareData['experience'])): ?>
                <p><strong>Experience:</strong> <?= htmlspecialchars($shareData['experience']) ?></p>
            <?php endif; ?>

            <?php if (!empty($shareData['salary'])): ?>
                <p><strong>Salary:</strong> <?= htmlspecialchars($shareData['salary']) ?></p>
            <?php endif; ?>

            <?php if (!empty($shareData['url'])): ?>
                <p><strong>Link:</strong> <a href="<?= htmlspecialchars($shareData['url']) ?>" target="_blank"><?= htmlspecialchars($shareData['url']) ?></a></p>
            <?php endif; ?>
         </div>

        <div class="share-buttons">
            <button id="copy-share-link-btn" class="share-btn copy-link">Copy Job Details</button>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // This script block is only rendered when a job is successfully posted.
    const modal = document.getElementById('shareModal');
    const closeBtn = document.querySelector('.close-button');
    const copyBtn = document.getElementById('copy-share-link-btn');

    // PHP data safely injected into JavaScript
    const jobData = <?= json_encode($shareData) ?>;

    // Function to show the modal
    const showModal = () => {
        if (modal) modal.style.display = 'block';
    };

    // Function to hide the modal
    const hideModal = () => {
        if (modal) modal.style.display = 'none';
    };

    // Event listeners to close the modal
    closeBtn.onclick = hideModal;
    window.onclick = (event) => {
        if (event.target == modal) {
            hideModal();
        }
    };

    // --- Copy Link Logic with Fallback ---
    function showCopyFeedback(button) {
        const originalText = button.textContent;
        button.textContent = 'Copied!';
        button.style.backgroundColor = '#28a745'; // Success green
        button.disabled = true;

        setTimeout(() => {
            button.textContent = originalText;
            button.style.backgroundColor = ''; // Revert to stylesheet color
            button.disabled = false;
        }, 2000);
    }

    function fallbackCopyTextToClipboard(text, button) {
        const textArea = document.createElement("textarea");
        textArea.value = text;
        textArea.style.position = 'fixed';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        try {
            document.execCommand('copy');
            showCopyFeedback(button);
        } catch (err) {
            console.error('Fallback: Unable to copy', err);
            alert('Failed to copy link.');
        }
        document.body.removeChild(textArea);
    }

    if (copyBtn) {
        copyBtn.addEventListener('click', function() {
            if (this.disabled) return;

            // Construct the full job content string, omitting empty fields.
            let jobContentToCopy = `✨ Job Opportunity! 📢\n\n`;
            jobContentToCopy += `Title: ${jobData.title || 'N/A'}\n`;

            if (jobData.company && jobData.company.trim()) {
                jobContentToCopy += `Company: ${jobData.company.trim()}\n`;
            }

            // Extract experience and format for sharing, handling "other" and custom values
            let sharedExperience = '';
            if (jobData.experience && jobData.experience !== '0') {
                if (jobData.experience === 'other' && jobData.custom_experience && jobData.custom_experience.trim()) {
                    sharedExperience = jobData.custom_experience.trim();
                } else {
                    sharedExperience = jobData.experience; // Use the selected value directly (or it might need display text map)
                    // You might need to expand this with a map if experience values (e.g., '1') 
                    // don't directly match display text (e.g., '1 year').
                }
            }

            if (sharedExperience) {
                jobContentToCopy += `Experience: ${sharedExperience}\n`;
            }

            if (jobData.salary && jobData.salary.trim()) {
                jobContentToCopy += `Salary: ${jobData.salary.trim()}\n`;
                jobContentToCopy += `\n`;
            }

            // if (jobData.description && jobData.description.trim()) {
            //     jobContentToCopy += `*Description:*\n${jobData.description.trim()}\n\n`;
            // }

            jobContentToCopy += `Apply Here & More Info:\n${(jobData.url.replace('http://', 'www.').replace('job_detail.php', 'index.php')) || '#'}`;

            if (!navigator.clipboard) {
                fallbackCopyTextToClipboard(jobContentToCopy, this);
                return;
            }
            
            navigator.clipboard.writeText(jobContentToCopy).then(() => {
                showCopyFeedback(this);
            }).catch(err => {
                console.error('Async copy failed, trying fallback: ', err);
                fallbackCopyTextToClipboard(jobContentToCopy, this);
            });
        });
    }

    // Show the modal automatically
    showModal();
});
</script>
<?php endif; ?>

<script>
    function toggleCustomExperience(selectElement) {
        const customExperienceInput = document.getElementById('custom_experience');
        if (selectElement.value === 'other') {
            customExperienceInput.style.display = 'block';
            customExperienceInput.required = true; // Make it required if "Other" is selected
        } else {
            customExperienceInput.style.display = 'none';
            customExperienceInput.required = false; // Not required if another option is selected
            customExperienceInput.value = ''; // Clear the value if not "Other"
        }
    }

    // Initialize on page load in case the form is pre-filled with "Other"
    document.addEventListener('DOMContentLoaded', function() {
        const experienceSelect = document.getElementById('experience');
        if (experienceSelect) { // Ensure the element exists (it might not if in review mode and fields are hidden)
            toggleCustomExperience(experienceSelect);
        }
    });

    function validateForm() {
        const description = document.getElementById('description').value.trim();
        if (!description) {
            alert('Key Responsibilities/Details are required.');
            return false;
        }
        return true; // Form is valid
    }

    document.addEventListener('DOMContentLoaded', function() {
        const regenBtn = document.getElementById('regenerate-ai-summary-btn');
        const form = document.getElementById('postJobForm');

        if (regenBtn && form) {
            regenBtn.addEventListener('click', function() {
                const formData = new FormData(form);
                formData.append('action', 'regenerate_summary'); // Action for backend
                
                regenBtn.disabled = true;
                regenBtn.textContent = 'Generating...';

                window.location.reload();  // Refresh the page
            });
        }
    });

    // Add form validation before submission (if not in review mode)
    const form = document.getElementById('postJobForm');
    if (form && !<?= json_encode($isReviewMode) ?>) {
        form.addEventListener('submit', function(event) {
            if (!validateForm()) {
                event.preventDefault();
            }
        });
    }
</script>
