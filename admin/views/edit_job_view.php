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
 <form method="POST" action="job_actions.php"> <input type="hidden" name="action" value="save_job"> <input type="hidden" name="job_id" value="<?= htmlspecialchars($displayData['id'] ?? '') ?>">
     <input type="hidden" name="posted_on" value="<?= htmlspecialchars($displayData['posted_on'] ?? '') ?>">
     <input type="hidden" name="posted_on_unix_ts" value="<?= htmlspecialchars($displayData['posted_on_unix_ts'] ?? '') ?>">


    <label for="title">Job Title:</label>
    <input type="text" id="title" name="title" value="<?= htmlspecialchars($displayData['title'] ?? '') ?>" required>

    <label for="company">Company:</label>
    <input type="text" id="company" name="company" value="<?= htmlspecialchars($displayData['company'] ?? '') ?>" required>

    <label for="location">Location:</label>
    <input type="text" id="location" name="location" value="<?= htmlspecialchars($displayData['location'] ?? '') ?>" required>

    <label for="description">Description:</label>
    <textarea id="description" name="description" rows="6" required><?= htmlspecialchars($displayData['description'] ?? '') ?></textarea>

     <label for="vacant_positions">Vacant Positions (Optional, default 1):</label>
     <input type="number" id="vacant_positions" name="vacant_positions" value="<?= htmlspecialchars($displayData['vacant_positions'] ?? 1) ?>" min="1">

    <label for="phones">Contact Phones (comma-separated, Optional):</label>
    <input type="text" id="phones" name="phones" value="<?= htmlspecialchars($displayData['phones'] ?? '') ?>">

    <label for="emails">Contact Emails (comma-separated, Optional):</label>
    <input type="email" id="emails" name="emails" value="<?= htmlspecialchars($displayData['emails'] ?? '') ?>">

    <div style="margin-top: 20px;">
        <button type="submit" name="save_job_btn" class="button">Save Changes</button>
        <a href="dashboard.php?view=manage_jobs" class="button" style="background-color: #6c757d; margin-left: 10px;">Cancel</a>
    </div>
</form>
