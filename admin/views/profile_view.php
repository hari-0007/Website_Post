<?php

// admin/views/profile_view.php - Displays Profile Management Forms

// This file is included by dashboard.php when $requestedView is 'profile'.
// It assumes $_SESSION['admin_username'] and $_SESSION['admin_display_name'] are available.
// It also assumes $_POST data might be available if there was a validation error on POST.

?>
<h2 class="view-main-title">Manage Your Profile</h2>

<div class="profile-container">
     <div class="profile-section">
         <h4 class="section-title-minor">Account Information</h4>
         <div class="info-group">
            <label>Login Email (Username):</label>
            <span><?= htmlspecialchars($_SESSION['admin_username'] ?? 'N/A') ?></span>
         </div>
         <p class="text-muted"><small>Login email cannot be changed directly.</small></p>
     </div>

     <div class="profile-section">
        <h4 class="section-title-minor">Change Display Name</h4>
        <form method="POST" action="profile_actions.php" class="styled-form">
            <input type="hidden" name="action" value="change_display_name">
            <div class="form-group">
                <label for="new_display_name">New Display Name:</label>
                <input type="text" id="new_display_name" name="new_display_name" value="<?= htmlspecialchars($_POST['new_display_name'] ?? $_SESSION['admin_display_name'] ?? $_SESSION['admin_username'] ?? '') ?>" required>
            </div>
             <button type="submit" name="change_display_name_btn" class="button">Update Display Name</button>
         </form>
     </div>

     <div class="profile-section">
         <h4 class="section-title-minor">Change Password</h4>
         <form method="POST" action="profile_actions.php" class="styled-form">
            <input type="hidden" name="action" value="change_password">
            <div class="form-group">
                <label for="current_password">Current Password:</label>
                <input type="password" id="current_password" name="current_password" required>
            </div>
            <div class="form-group">
                <label for="new_password">New Password:</label>
                <input type="password" id="new_password" name="new_password" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm New Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
             <button type="submit" name="change_password_btn" class="button">Update Password</button>
         </form>
     </div>
 </div>

<style>
    .view-main-title { /* Consistent main title for views */
        margin-top: 0;
        margin-bottom: 25px;
        color: var(--primary-color);
        font-size: 1.75em;
        font-weight: 600;
        padding-bottom: 15px;
        border-bottom: 2px solid var(--primary-color-lighter);
    }
    .profile-container { /* Similar to .post-job-container or .form-container */
        max-width: 650px;
        margin: 20px auto;
    }
    .profile-section { /* Similar to .dashboard-section or .user-form-section */
        background-color: var(--card-bg);
        padding: 20px 25px;
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow-sm);
        border: 1px solid var(--border-color);
        margin-bottom: 25px;
    }
    .section-title-minor { /* For sub-headings like "Account Information" */
        font-size: 1.2em; /* Slightly smaller than .section-title */
        color: var(--text-color-light);
        margin-top: 0;
        margin-bottom: 1rem; /* Consistent spacing */
        font-weight: 500;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid var(--border-color);
    }
    .info-group {
        margin-bottom: 0.75rem;
    }
    .info-group label {
        font-weight: 600;
        color: var(--text-color-light);
        display: block;
        margin-bottom: 0.25rem;
    }
    .info-group span {
        color: var(--text-color);
    }
    .text-muted { /* Global style from header.php should cover this */
        color: var(--text-muted);
    }
    /* .styled-form and its elements (label, input, button) will inherit global styles */
    .styled-form .form-group { margin-bottom: 1rem; }
</style>
