<?php
// admin/views/edit_user_view.php

// Ensure $userToEdit is set by fetch_content.php
if (!isset($userToEdit) || !is_array($userToEdit)) {
    echo "<p class='status-message error'>User data not available or invalid.</p>";
    return;
}

// Get logged-in user's role (should be available from fetch_content.php scope)
$loggedInUserRole = $_SESSION['admin_role'] ?? 'user';
$loggedInUsername = $_SESSION['admin_username'] ?? '';

$targetUsername = $userToEdit['username'];
$targetDisplayName = $userToEdit['display_name'] ?? '';
$targetRole = $userToEdit['role'] ?? 'user';
$targetStatus = $userToEdit['status'] ?? 'unknown'; // Get the current status


// Determine available roles for assignment based on logged-in user's role
$assignableRoles = [];
$allPossibleRoles = ['super_admin', 'admin', 'user_group_manager', 'user'];

if ($loggedInUserRole === 'super_admin') {
    $assignableRoles = $allPossibleRoles;
} elseif ($loggedInUserRole === 'admin') {
    $assignableRoles = ['admin', 'user_group_manager', 'user']; // Admins cannot create/assign super_admin
} elseif ($loggedInUserRole === 'user_group_manager') {
    $assignableRoles = ['user']; // UGMs can only assign 'user'
}

// Further restrictions:
// - Logged-in user cannot elevate another user to a role higher than their own (unless super_admin).
// - Logged-in user cannot change the role of a user who is super_admin (unless they are also super_admin).
$canChangeRole = false;
if ($loggedInUserRole === 'super_admin') {
    $canChangeRole = true; // Super admin can change any role
} elseif ($loggedInUserRole === 'admin') {
    if ($targetRole !== 'super_admin') { // Admin cannot change super_admin's role
        $canChangeRole = true;
    }
} elseif ($loggedInUserRole === 'user_group_manager') {
    if ($targetRole === 'user') { // UGM can only change 'user' role
        $canChangeRole = true;
    }
}

// Prevent editing self if this form is not for profile management
// (though profile management has its own page, this is a safeguard)
$isEditingSelf = ($loggedInUsername === $targetUsername);

?>

<div class="form-container">
    <h3>Edit User (Email: <?php echo htmlspecialchars($targetUsername); ?>)</h3>

    <form action="user_actions.php" method="POST" id="editUserForm">
        <input type="hidden" name="action" value="update_user">
        <input type="hidden" name="username_to_update" value="<?php echo htmlspecialchars($targetUsername); ?>">

        <div class="form-group">
            <label for="display_name">Display Name:</label>
            <input type="text" id="display_name" name="display_name" value="<?php echo htmlspecialchars($targetDisplayName); ?>" required>
        </div>

        <div class="form-group">
            <label for="password">New Password (optional):</label>
            <input type="password" id="password" name="password" placeholder="Leave blank to keep current password">
        </div>

        <div class="form-group">
            <label for="confirm_password">Confirm New Password:</label>
            <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm if changing password">
        </div>

        <?php if ($canChangeRole && !$isEditingSelf): // Users cannot change their own role via this form ?>
            <div class="form-group">
                <label for="role">Role:</label>
                <select id="role" name="role">
                    <?php foreach ($allPossibleRoles as $roleValue): ?>
                        <?php
                        // Determine if this role option should be enabled/disabled
                        $disabled = '';
                        if (!in_array($roleValue, $assignableRoles)) {
                            // If the role is not generally assignable by the logged-in user
                            $disabled = 'disabled';
                        }
                        // Super_admin role can only be assigned by another super_admin.
                        // An admin cannot assign 'super_admin'.
                        if ($roleValue === 'super_admin' && $loggedInUserRole !== 'super_admin') {
                            $disabled = 'disabled';
                        }
                        // If target is super_admin, only super_admin can change their role
                        if ($targetRole === 'super_admin' && $loggedInUserRole !== 'super_admin' && $roleValue !== 'super_admin') {
                            $disabled = 'disabled';
                        }

                        // If the role is the target's current role, it should not be disabled
                        // unless the logged-in user fundamentally cannot manage that role.
                        if ($roleValue === $targetRole) {
                            if ($targetRole === 'super_admin' && $loggedInUserRole !== 'super_admin') {
                                // Non-super_admin viewing super_admin's profile, role field is effectively read-only
                                $disabled = 'disabled';
                            } else {
                                $disabled = ''; // Current role should always be selectable if user has access
                            }
                        }
                        ?>
                        <option value="<?php echo htmlspecialchars($roleValue); ?>" <?php echo ($targetRole === $roleValue) ? 'selected' : ''; ?> <?php echo $disabled; ?>>
                            <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $roleValue))); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                 <?php if ($targetRole === 'super_admin' && $loggedInUserRole !== 'super_admin'): ?>
                    <small class="form-text text-muted">Super Admin role cannot be changed by non-Super Admin users.</small>
                 <?php endif; ?>
            </div>
        <?php else: ?>
            <input type="hidden" name="role" value="<?php echo htmlspecialchars($targetRole); ?>">
            <p>Role: <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $targetRole))); ?> (Cannot be changed here<?php echo $isEditingSelf ? ' for self' : ''; ?>)</p>
        <?php endif; ?>

        <button type="submit" class="button">Update User</button>
        <a href="dashboard.php?view=manage_users" class="button secondary">Cancel</a>
    </form>
    <?php
    // --- User Status Toggle Section ---
    // Show toggle button only if the logged-in user is admin or super_admin
    // And not for the user themselves
    // And admin cannot deactivate/activate a super_admin
    $canToggleStatus = false;
    if (in_array($loggedInUserRole, ['admin', 'super_admin']) && !$isEditingSelf) {
        if ($loggedInUserRole === 'super_admin') {
            $canToggleStatus = true;
        } elseif ($loggedInUserRole === 'admin' && $targetRole !== 'super_admin') {
            $canToggleStatus = true;
        }
    }

    // Only show toggle for 'active' or 'disabled' (or 'inactive') statuses.
    // 'pending_approval' is handled by approve/reject buttons elsewhere.
    if ($canToggleStatus && in_array($targetStatus, ['active', 'disabled', 'inactive'])):
        $isCurrentlyActive = ($targetStatus === 'active');
        $actionValue = $isCurrentlyActive ? 'deactivate_user_from_edit' : 'activate_user_from_edit';
        $buttonText = $isCurrentlyActive ? 'Deactivate User' : 'Activate User';
        $buttonClass = $isCurrentlyActive ? 'warning' : 'success';
    ?>
    <div class="form-container" style="margin-top: 20px; border-top: 1px solid #eee; padding-top: 20px;">
        <h4>User Account Status</h4>
        <p>Current Status: <span class="status-badge status-<?= strtolower(htmlspecialchars($targetStatus)) ?>"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $targetStatus))) ?></span></p>
        <form action="user_actions.php" method="POST" id="toggleUserStatusForm">
            <input type="hidden" name="action" value="<?= $actionValue ?>">
            <input type="hidden" name="username_to_toggle" value="<?php echo htmlspecialchars($targetUsername); ?>">
            <input type="hidden" name="redirect_to_edit" value="true"> <?php // To redirect back to this edit page ?>
            <button type="submit" class="button <?= $buttonClass ?>"><?= $buttonText ?></button>
        </form>
    </div>
    <?php elseif ($isEditingSelf): ?>
        <p style="margin-top: 20px;">Current Status: <span class="status-badge status-<?= strtolower(htmlspecialchars($targetStatus)) ?>"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $targetStatus))) ?></span> (Cannot change own status)</p>
    <?php elseif (in_array($targetStatus, ['active', 'disabled', 'inactive'])): ?>
        <p style="margin-top: 20px;">Current Status: <span class="status-badge status-<?= strtolower(htmlspecialchars($targetStatus)) ?>"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $targetStatus))) ?></span></p>
    <?php endif; ?>

</div>
</div>