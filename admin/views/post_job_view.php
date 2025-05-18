<?php

// admin/views/post_job_view.php - Displays the form for posting a new job
// admin/views/post_job_view.php - Form for posting a new job, with review step

// This file is included by dashboard.php when $requestedView is 'post_job'.
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
    .post-job-container {
        max-width: 700px; /* Wider for more content */
        margin: 20px auto;
        padding: 20px 25px;
        background-color: #ffffff;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }

    .post-job-container h3 { /* Changed from h1 for better semantic structure within dashboard */
        text-align: center;
        color: #0056b3; /* Primary color */
        margin-top: 0;
        margin-bottom: 10px;
        font-size: 1.6rem;
    }

    .post-job-container p.form-description {
        text-align: center;
        color: #555;
        margin-bottom: 25px;
        font-size: 0.95rem;
    }
    .styled-form .form-group {
        margin-bottom: 18px;
    }
    .styled-form label {
        display: block;
        font-weight: 600;
        color: #333;
        margin-bottom: 6px;
        font-size: 0.9rem;
    }
    .styled-form input[type="text"],
    .styled-form input[type="email"],
    .styled-form input[type="number"],
    .styled-form textarea,
    .styled-form select {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #ced4da;
        border-radius: 5px;
        font-size: 0.95rem;
        color: #495057;
        background-color: #fdfdfd;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .styled-form input[type="text"]:focus,
    .styled-form input[type="email"]:focus,
    .styled-form input[type="number"]:focus,
    .styled-form textarea:focus,
    .styled-form select:focus {
        border-color: #0056b3;
        outline: none;
        box-shadow: 0 0 0 0.2rem rgba(0, 86, 179, 0.2);
        background-color: #fff;
    }
    .styled-form textarea {
        resize: vertical;
        min-height: 100px;
    }
    .styled-form .button { /* Re-using .button style if defined globally, or define here */
        padding: 10px 18px;
        background-color: #0056b3;
        color: #fff;
        border: none;
        border-radius: 5px;
        font-size: 1rem;
        font-weight: 500;
        cursor: pointer;
        transition: background-color 0.2s ease, transform 0.1s ease;
        display: inline-block; /* For proper button behavior */
        width: auto; /* Don't force full width unless intended */
    }
    .styled-form .button:hover {
        background-color: #00418a;
        transform: translateY(-1px);
    }
    .styled-form .button:active {
        background-color: #003775;
        transform: translateY(0);
    }
    .required {
        color: #dc3545; /* Red for required fields */
        font-weight: bold;
        margin-left: 2px;
    }
    /* Styles for the AI summary review section */
    .ai-summary-review-section {
        border: 1px solid #0056b3;
        padding: 15px;
        margin-top: 20px;
        margin-bottom: 20px;
        border-radius: 5px;
        background-color: #eef2f7;
    }
    .ai-summary-review-section label {
        color: #0056b3;
        font-size: 1rem;
    }
</style>

<div class="post-job-container">
    <h3><?= htmlspecialchars($pageTitle) ?></h3>
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
            <label for="description">Key Responsibilities/Details:</label>
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

        <button type="submit" class="button"><?= htmlspecialchars($submitButtonText) ?></button>
    </form>
</div>