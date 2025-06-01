<?php

// admin/views/manage_users_view.php - Displays User Management Interface

// This file is included by dashboard.php or fetch_content.php when the view is 'manage_users'.
// It assumes $loggedIn, $_SESSION['admin_role'], $_SESSION['admin_username'], $users (list of users) are available.
// It also assumes $_POST data might be available for pre-filling forms on validation errors.

// Ensure $users is an array, even if loadUsers failed
$users = $users ?? [];

$loggedInUserRole = $_SESSION['admin_role'] ?? 'user';
$loggedInUsername = $_SESSION['admin_username'] ?? '';

// Determine if the logged-in user can create/manage users of certain roles
$canCreateAdmin = ($loggedInUserRole === 'super_admin');
$canCreateUserGroupManager = ($loggedInUserRole === 'super_admin' || $loggedInUserRole === 'admin');
$canCreateUser = ($loggedInUserRole === 'super_admin' || $loggedInUserRole === 'admin' || $loggedInUserRole === 'user_group_manager'); // Assuming user group managers can create basic users

?>
<h2 class="view-main-title">User Manager</h2>

<?php if ($loggedInUserRole === 'super_admin' || $loggedInUserRole === 'admin'): // Only Super Admins and Admins can create users ?>
    <div class="user-form-section">
        <h4 class="section-title">Create New User</h4>
        <?php // Display status messages specific to user creation if available (handled in dashboard.php) ?>
        <form method="POST" action="user_actions.php">
            <input type="hidden" name="action" value="create_user">

            <label for="new_display_name">Display Name:</label>
            <input type="text" id="new_display_name" name="display_name" value="<?= htmlspecialchars($_POST['display_name'] ?? '') ?>" required>

             <label for="create_user_email_username">Email ID (this will be the username):</label>
            <input type="email" id="create_user_email_username" name="username" class="form-control" placeholder="user@example.com" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
        
            <label for="new_password">Password:</label>
            <input type="password" id="new_password" name="password" required>

            <label for="confirm_password">Confirm Password:</label>
            <input type="password" id="confirm_password" name="confirm_password" required>

            <label for="new_role">Role:</label>
            <select id="new_role" name="role" required>
                <option value="">-- Select Role --</option>
                <?php if ($canCreateAdmin): ?>
                    <option value="super_admin" <?= (($_POST['role'] ?? '') === 'super_admin') ? 'selected' : '' ?>>Super Admin</option>
                <?php endif; ?>
                <?php if ($canCreateUserGroupManager): ?>
                    <option value="admin" <?= (($_POST['role'] ?? '') === 'admin') ? 'selected' : '' ?>>Admin</option>
                    <option value="user_group_manager" <?= (($_POST['role'] ?? '') === 'user_group_manager') ? 'selected' : '' ?>>User Group Manager</option>
                <?php endif; ?>
                 <?php if ($canCreateUser): ?>
                    <option value="user" <?= (($_POST['role'] ?? '') === 'user' || empty($_POST['role'])) ? 'selected' : '' ?>>User</option>
                <?php endif; ?>
            </select>

            <button type="submit" class="button">Create User</button>
        </form>
    </div></div>
<?php endif; ?>

<div class="user-list-section">
    <h4 class="section-title">All Users</h4>
    <?php if (empty($users)): ?>
        <p>No users found in the system.</p>
    <?php else: ?>
        <table class="user-table">
            <thead>
                <tr>
                    <th>Email (Username)</th>
                    <th>Display Name</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <?php
                    $userUsername = htmlspecialchars($user['username'] ?? 'N/A');
                    $userDisplayName = htmlspecialchars($user['display_name'] ?? 'N/A');
                    $userRole = htmlspecialchars($user['role'] ?? 'user');
                    $userStatus = htmlspecialchars($user['status'] ?? 'unknown');
                    ?>
                    <tr>
                        <td><?= $userUsername ?></td>
                        <td><?= $userDisplayName ?></td>
                        <td><?= ucwords(str_replace('_', ' ', $userRole)) ?></td>
                        <td>
                            <span class="status-badge status-<?= strtolower($userStatus) ?>">
                                <?= ucwords(str_replace('_', ' ', $userStatus)) ?>
                            </span>
                        </td>
                        <td class="actions">
                            <?php
                            $targetUsername = $user['username'] ?? '';
                            $targetUserRole = $user['role'] ?? 'user';
                            $targetUserStatus = $user['status'] ?? 'unknown';
                            $isSelf = ($loggedInUsername === $targetUsername);

                            // --- Actions for Admin/Super Admin ---
                            if (in_array($loggedInUserRole, ['admin', 'super_admin'])) {
                                // Approve/Reject for pending users
                                if ($targetUserStatus === 'pending_approval') {
                                    echo '<form action="user_actions.php" method="POST" style="display:inline-block; margin-right: 5px;">';
                                    echo '<input type="hidden" name="action" value="approve_user">';
                                    echo '<input type="hidden" name="username_to_action" value="' . $targetUsername . '">';
                                    echo '<button type="submit" class="button success small">Approve</button>';
                                    echo '</form>';

                                    echo '<form action="user_actions.php" method="POST" style="display:inline-block;">';
                                    echo '<input type="hidden" name="action" value="reject_user">';
                                    echo '<input type="hidden" name="username_to_action" value="' . $targetUsername . '">';
                                    echo '<button type="submit" class="button danger small" onclick="return confirm(\'Are you sure you want to reject this user registration: ' . addslashes($userDisplayName) . '?\');">Reject</button>';
                                    echo '</form>';
                                }
                                // Disable for active users
                                elseif ($targetUserStatus === 'active') {
                                    if (!$isSelf && !($targetUserRole === 'super_admin' && $loggedInUserRole !== 'super_admin')) {
                                        echo '<form action="user_actions.php" method="POST" style="display:inline-block; margin-right: 5px;">';
                                        echo '<input type="hidden" name="action" value="disable_user">';
                                        echo '<input type="hidden" name="username_to_action" value="' . $targetUsername . '">';
                                        echo '<button type="submit" class="button warning small">Disable</button>';
                                        echo '</form>';
                                    }
                                }
                                // Enable for disabled users
                                elseif ($targetUserStatus === 'disabled') {
                                    if (!($targetUserRole === 'super_admin' && $loggedInUserRole !== 'super_admin')) { // Super admin check is mostly for consistency
                                        echo '<form action="user_actions.php" method="POST" style="display:inline-block; margin-right: 5px;">';
                                        echo '<input type="hidden" name="action" value="enable_user">';
                                        echo '<input type="hidden" name="username_to_action" value="' . $targetUsername . '">';
                                        echo '<button type="submit" class="button success small">Enable</button>';
                                        echo '</form>';
                                    }
                                }
                            }

                            // --- Edit and Delete Permissions (General Management) ---
                            $canEditThisUser = false;
                            $canDeleteThisUser = false;

                            if ($loggedInUserRole === 'super_admin') {
                                $canEditThisUser = true;
                                if (!$isSelf) $canDeleteThisUser = true;
                            } elseif ($loggedInUserRole === 'admin') {
                                if ($targetUserRole !== 'super_admin') {
                                    $canEditThisUser = true;
                                    if (!$isSelf && $targetUserRole !== 'admin') { // Admins cannot delete other admins
                                        $canDeleteThisUser = true;
                                    }
                                }
                            } elseif ($loggedInUserRole === 'user_group_manager') {
                                if ($targetUserRole === 'user') {
                                    $canEditThisUser = true; // UGM can edit users
                                    // $canDeleteThisUser = true; // Decide if UGM can delete users
                                }
                            }
                            // Allow self-edit (profile page is better, but for consistency)
                            if ($isSelf) $canEditThisUser = true;


                            // Display Edit button
                            if ($canEditThisUser) {
                                echo '<a href="dashboard.php?view=edit_user&username=' . urlencode($targetUsername) . '" class="button edit small" style="margin-right:5px;">Edit</a>';
                            }

                            // Display Delete button (for active or disabled users, not pending)
                            if ($canDeleteThisUser && ($targetUserStatus === 'active' || $targetUserStatus === 'disabled')) {
                                echo '<a href="user_actions.php?action=delete_user&username=' . urlencode($targetUsername) . '"
                                   onclick="return confirm(\'Are you sure you want to permanently delete user: ' . addslashes($userDisplayName) . '? This action cannot be undone.\');"
                                   class="button delete small">Delete</a>';
                            }
                            ?>
                             <?php if ($loggedInUsername === $user['username']): ?>
                                 <span style="color: #666; font-size: 0.9em;">(Your account)</span>
                             <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
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
    .section-title { /* Consistent section titles */
        margin-top: 0;
        margin-bottom: 15px;
        color: var(--text-color-light);
        font-size: 1.2em;
        font-weight: 500;
        padding-bottom: 10px;
        border-bottom: 1px solid var(--border-color);
    }

    .user-form-section, .user-list-section {
        background: var(--card-bg); /* Use theme variable */
        padding: 20px;
        border-radius: var(--border-radius); /* Use theme variable */
        box-shadow: var(--box-shadow-sm); /* Use theme variable */
        margin-bottom: 25px; /* Consistent margin */
    }

    /* Table styles - aiming for .professional-table look from header.php */
    .user-table { /* Align with .professional-table */
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
        font-size: 0.9rem;
        color: var(--text-color-light);
    }

    .user-table th, .user-table td {
        padding: .75rem 1rem; /* Match professional-table */
        border-bottom: 1px solid var(--border-color); /* Match professional-table */
        text-align: left;
        vertical-align: middle;
    }

    .user-table th {
        background-color: var(--body-bg); /* Match professional-table */
        color: var(--text-color-light); /* Match professional-table */
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        border-bottom-width: 2px; /* Match professional-table */
    }

    .user-table tr:nth-child(even) {
        /* background-color: #f9f9f9; */ /* Optional, can be removed for cleaner look if card has bg */
    }

    .user-table tr:hover {
        background-color: #f5f7f8; /* Match professional-table */
    }
    .user-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .user-table td.actions .button { /* General styling for buttons in actions column */
        margin-right: 5px; /* Adjust spacing */
        /* font-size and padding are handled by .button.small */
    }
    .user-table td.actions .button.small {
        padding: 3px 8px;
        font-size: 0.85em;
    }

    .user-table td.actions .button.edit { /* Specific style for edit button */
         background-color: var(--secondary-color); /* Use theme secondary */
         border-color: var(--secondary-color);
         color: #fff;
    }
    .user-table td.actions .button.edit:hover {
         background-color: var(--secondary-color-darker);
         border-color: var(--secondary-color-darker);
    }
     .user-table td.actions .button.delete { /* Specific style for delete button */
         background-color: var(--error-color); /* Use theme error */
         border-color: var(--error-color);
     }
     .user-table td.actions .button.delete:hover {
         background-color: #c0392b; /* Darker Alizarin from header.php */
     }
    .user-table td.actions .button.success {
        /* Inherits from global .button.button-success */
    }
    .user-table td.actions .button.success:hover {
        /* Inherits from global .button.button-success:hover */
    }
    .user-table td.actions .button.warning {
        /* Inherits from global .button.button-warning */
    }
    .user-table td.actions .button.warning:hover {
        /* Inherits from global .button.button-warning:hover */
    }
    .user-table td.actions .button.danger { /* For reject button */
        /* Inherits from global .button.button-danger */
    }
    .user-table td.actions .button.danger:hover {
        /* Inherits from global .button.button-danger:hover */
     }

    /* Form section styling */
    .user-form-section label {
        /* Inherits global form label style from header.php */
    }

    .user-form-section input[type="text"],
    .user-form-section input[type="email"], /* Added for consistency */
    .user-form-section input[type="password"],
    .user-form-section select {
        /* Inherits global form input/select styles from header.php */
    }

    .user-form-section button.button {
        /* Inherits global .button style from header.php */
        margin-top: 10px; /* Add some space above the button */
    }
    .user-form-section button.button:hover {
        /* Inherits global .button:hover style */
    }

    /* Status Badges */
    .status-badge {
        padding: 0.25em 0.6em;
        border-radius: 0.25em;
        font-size: 0.8em;
        font-weight: bold;
        color: #fff;
        text-transform: capitalize;
        display: inline-block;
    }
    .status-active {
        background-color: var(--success-color); /* Use theme variable */
    }
    .status-pending_approval {
        background-color: var(--warning-color); /* Use theme variable */
        color: #fff; /* White text for better contrast on orange */
    }
    .status-disabled {
        background-color: var(--error-color); /* Use theme variable */
    }
    .status-unknown {
        background-color: var(--secondary-color); /* Use theme variable */
    }
</style>