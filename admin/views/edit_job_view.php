<?php

// admin/views/edit_job_view.php - Displays the form for editing a job

// This file is included by dashboard.php when $requestedView is 'edit_job'.
// It assumes $jobToEdit and $jobId are available.
// It also assumes $_POST data might be available if there was a validation error on POST.

// Determine the data to display in the form: use $_POST if available (validation error), otherwise use $jobToEdit
// This ensures that if a user submits the form with errors, their input is preserved.
$displayData = !empty($_POST) ? $_POST : $jobToEdit;

// Ensure ID is in displayData if using $_POST (it should be passed via hidden field, but this is a safeguard)
if(empty($displayData['id']) && isset($jobId)) {
    $displayData['id'] = $jobId;
}

?>
<h3>Edit Job: <?= htmlspecialchars($displayData['title'] ?? 'N/A') ?></h3>
<form method="POST" action="job_actions.php">
    <input type="hidden" name="action" value="save_job">
    <input type="hidden" name="job_id" value="<?= htmlspecialchars($displayData['id'] ?? '') ?>">
    <input type="hidden" name="posted_on_unix_ts" value="<?= htmlspecialchars($displayData['posted_on_unix_ts'] ?? '') ?>">

    <label for="title">Job Title:</label>
    <input type="text" id="title" name="title" value="<?= htmlspecialchars($displayData['title'] ?? '') ?>" required>

    <label for="company">Company:</label>
    <input type="text" id="company" name="company" value="<?= htmlspecialchars($displayData['company'] ?? '') ?>" placeholder="Optional">

    <label for="location">Location:</label>
    <input type="text" id="location" name="location" value="<?= htmlspecialchars($displayData['location'] ?? '') ?>" placeholder="Optional">

    <label for="description">Description:</label>
    <textarea id="description" name="description" rows="6" placeholder="Optional"><?= htmlspecialchars($displayData['description'] ?? '') ?></textarea>

    <label for="type">Job Type:</label>
    <select id="type" name="type">
        <option value="Full Time" <?= (isset($displayData['type']) && $displayData['type'] === 'Full Time') ? 'selected' : '' ?>>Full Time</option>
        <option value="Part Time" <?= (isset($displayData['type']) && $displayData['type'] === 'Part Time') ? 'selected' : '' ?>>Part Time</option>
        <option value="Internship" <?= (isset($displayData['type']) && $displayData['type'] === 'Internship') ? 'selected' : '' ?>>Internship</option>
        <option value="Remote" <?= (isset($displayData['type']) && $displayData['type'] === 'Remote') ? 'selected' : '' ?>>Remote</option>
        <option value="Hybrid" <?= (isset($displayData['type']) && $displayData['type'] === 'Hybrid') ? 'selected' : '' ?>>Hybrid</option>
        <option value="Onsite" <?= (isset($displayData['type']) && $displayData['type'] === 'Onsite') ? 'selected' : '' ?>>Onsite</option>
        <option value="Developer" <?= (isset($displayData['type']) && $displayData['type'] === 'Developer') ? 'selected' : '' ?>>Developer</option>
    </select>

    <label for="experience">Experience:</label>
    <input type="text" id="experience" name="experience" value="<?= htmlspecialchars($displayData['experience'] ?? '') ?>" placeholder="Optional">

    <label for="salary">Salary:</label>
    <input type="text" id="salary" name="salary" value="<?= htmlspecialchars($displayData['salary'] ?? '') ?>" placeholder="Optional">

    <label for="posted_on">Posted Date:</label>
    <input type="text" id="posted_on" name="posted_on" value="<?= htmlspecialchars($displayData['posted_on'] ?? '') ?>" readonly>

    <label for="vacant_positions">Vacant Positions:</label>
    <input type="number" id="vacant_positions" name="vacant_positions" value="<?= htmlspecialchars($displayData['vacant_positions'] ?? 1) ?>" min="1" placeholder="Optional">

    <label for="phones">Contact Phones (comma-separated):</label>
    <input type="text" id="phones" name="phones" value="<?= htmlspecialchars($displayData['phones'] ?? '') ?>" placeholder="Optional">

    <label for="emails">Contact Emails (comma-separated):</label>
    <input type="email" id="emails" name="emails" value="<?= htmlspecialchars($displayData['emails'] ?? '') ?>" placeholder="Optional">

    <div style="margin-top: 20px;">
        <button type="submit" name="save_job_btn" class="button">Save Changes</button>
        <a href="dashboard.php?view=manage_jobs" class="button" style="background-color: #6c757d; margin-left: 10px;">Cancel</a>
    </div>
</form>

<style>
    /* Style for dropdown fields */
    select {
        width: 100%;
        padding: 10px;
        margin: 10px 0;
        border: 1px solid #ccc;
        border-radius: 5px;
        font-size: 1rem;
        background-color: #f9f9f9;
    }

    /* Style for buttons */
    .button {
        padding: 10px 20px;
        font-size: 1rem;
        color: #fff;
        background-color: #007bff;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        text-decoration: none;
    }

    .button:hover {
        background-color: #0056b3;
    }

    .button[style*="background-color: #6c757d"] {
        background-color: #6c757d;
    }

    .button[style*="background-color: #6c757d"]:hover {
        background-color: #5a6268;
    }
</style>

<script>
    document.querySelector('form').addEventListener('submit', function (event) {
        const title = document.getElementById('title').value.trim();
        const phones = document.getElementById('phones').value.trim();
        const emails = document.getElementById('emails').value.trim();

        if (!title) {
            alert('Job title is required.');
            event.preventDefault();
            return;
        }

        if (!phones && !emails) {
            alert('At least one contact method (phone or email) is required.');
            event.preventDefault();
            return;
        }
    });
</script>
