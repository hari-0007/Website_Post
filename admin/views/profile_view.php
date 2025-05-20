<?php

// admin/views/profile_view.php - Displays Profile Management Forms

// This file is included by dashboard.php when $requestedView is 'profile'.
// It assumes $_SESSION['admin_username'] and $_SESSION['admin_display_name'] are available.
// It also assumes $_POST data might be available if there was a validation error on POST.

?>
<h3>Manage Your Profile</h3>
<div class="profile-forms">
     <div class="profile-form-section">
         <h3>Account Information</h3>
         <p><strong>Login Email:</strong> <?= htmlspecialchars($_SESSION['admin_username'] ?? 'N/A') ?></p>
         <p>Login email cannot be changed here.</p>
     </div>

     <div class="profile-form-section">
        <h3>Change Display Name</h3>
        <form method="POST" action="profile_actions.php"> <input type="hidden" name="action" value="change_display_name"> <label for="new_display_name">New Display Name:</label>
             <input type="text" id="new_display_name" name="new_display_name" value="<?= htmlspecialchars($_POST['new_display_name'] ?? $_SESSION['admin_display_name'] ?? $_SESSION['admin_username'] ?? '') ?>" required>
             <button type="submit" name="change_display_name_btn" class="button">Update Display Name</button>
         </form>
     </div>

     <div class="profile-form-section">
         <h3>Change Password</h3>
         <form method="POST" action="profile_actions.php"> <input type="hidden" name="action" value="change_password"> <label for="current_password">Current Password:</label>
             <input type="password" id="current_password" name="current_password" required>

             <label for="new_password">New Password:</label>
             <input type="password" id="new_password" name="new_password" required>

             <label for="confirm_password">Confirm New Password:</label>
             <input type="password" id="confirm_password" name="confirm_password" required>

             <button type="submit" name="change_password_btn" class="button">Update Password</button>
         </form>
     </div>
 </div>
