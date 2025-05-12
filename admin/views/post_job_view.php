<?php

// admin/views/post_job_view.php - Displays the form for posting a new job

// This file is included by dashboard.php when $requestedView is 'post_job'.
// It assumes $formData is available (for pre-filling on validation errors).

?>
<!-- <h3>Post New Job</h3> -->
 <form method="POST" action="job_actions.php"> <input type="hidden" name="action" value="post_job"> <label for="title">Job Title:</label>
    <input type="text" id="title" name="title" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" required>

    <label for="company">Company:</label>
    <input type="text" id="company" name="company" value="<?= htmlspecialchars($_POST['company'] ?? '') ?>" required>

    <label for="location">Location:</label>
    <input type="text" id="location" name="location" value="<?= htmlspecialchars($_POST['location'] ?? '') ?>" required>

    <label for="description">Description:</label>
    <textarea id="description" name="description" rows="6" required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>

     <label for="vacant_positions">Vacant Positions (Optional, default 1):</label>
     <input type="number" id="vacant_positions" name="vacant_positions" value="<?= htmlspecialchars($_POST['vacant_positions'] ?? 1) ?>" min="1">

    <label for="phones">Contact Phones (comma-separated, Optional):</label>
    <input type="text" id="phones" name="phones" value="<?= htmlspecialchars($_POST['phones'] ?? '') ?>">

    <label for="emails">Contact Emails (comma-separated, Optional):</label>
    <input type="email" id="emails" name="emails" value="<?= htmlspecialchars($_POST['emails'] ?? '') ?>">

    <button type="submit" name="post_job_btn" class="button">Post Job</button>
</form>
