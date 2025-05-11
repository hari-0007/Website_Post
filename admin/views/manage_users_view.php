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
<h3>User Manager</h3>

<?php if ($loggedInUserRole === 'super_admin' || $loggedInUserRole === 'admin'): // Only Super Admins and Admins can create users ?>
    <div class="user-form-section">
        <h3>Create New User</h3>
        <?php // Display status messages specific to user creation if available (handled in dashboard.php) ?>
        <form method="POST" action="user_actions.php">
            <input type="hidden" name="action" value="create_user">

            <label for="new_username">Username:</label>
            <input type="text" id="new_username" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>

            <label for="new_display_name">Display Name:</label>
            <input type="text" id="new_display_name" name="display_name" value="<?= htmlspecialchars($_POST['display_name'] ?? '') ?>" required>

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
    </div>
<?php endif; ?>

<div class="user-list-section">
    <h3>All Users</h3>
    <?php if (empty($users)): ?>
        <p>No users found in the system.</p>
    <?php else: ?>
        <table class="user-table">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Display Name</th>
                    <th>Role</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <?php
                    $userUsername = htmlspecialchars($user['username'] ?? 'N/A');
                    $userDisplayName = htmlspecialchars($user['display_name'] ?? 'N/A');
                    $userRole = htmlspecialchars($user['role'] ?? 'user');
                    ?>
                    <tr>
                        <td><?= $userUsername ?></td>
                        <td><?= $userDisplayName ?></td>
                        <td><?= ucwords(str_replace('_', ' ', $userRole)) ?></td>
                        <td class="actions">
                            <?php
                            // Determine if the logged-in user can edit/delete this user
                            $canEdit = false;
                            $canDelete = false;

                            // Super Admin can edit/delete anyone except themselves
                            if ($loggedInUserRole === 'super_admin' && $loggedInUsername !== $user['username']) {
                                $canEdit = true;
                                $canDelete = true;
                            }
                            // Admin can edit/delete User Group Managers and Users (but not Super Admins or other Admins)
                            elseif ($loggedInUserRole === 'admin' && ($user['role'] === 'user_group_manager' || $user['role'] === 'user')) {
                                $canEdit = true;
                                $canDelete = true;
                            }
                            // User Group Manager can view Users (but not edit/delete them - this requires group logic)
                            // For now, they can only view, cannot edit/delete other roles.
                             elseif ($loggedInUserRole === 'user_group_manager' && $user['role'] === 'user') {
                                 // Can view, but edit/delete links are not shown below
                             }
                            // Users cannot edit/delete anyone.

                            // Prevent deleting the currently logged-in user
                            if ($loggedInUsername === $user['username']) {
                                $canDelete = false;
                            }
                            ?>

                            <?php if ($canEdit): ?>
                                <a href="dashboard.php?view=edit_user&username=<?= urlencode($user['username'] ?? '') ?>" class="button" style="background-color: #ffc107; color: #212529;">Edit</a>
                            <?php endif; ?>

                            <?php if ($canDelete): ?>
                                <a href="user_actions.php?action=delete_user&username=<?= urlencode($user['username'] ?? '') ?>"
                                   onclick="return confirm('Are you sure you want to delete user: <?= addslashes($userUsername) ?>?');"
                                   class="button delete">Delete</a>
                            <?php endif; ?>

                            <?php if (!$canEdit && !$canDelete && $loggedInUsername !== $user['username']): ?>
                                <span style="color: #666; font-size: 0.9em;">Cannot manage</span>
                            <?php endif; ?>
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
    /* Basic styles for User Manager view */
    .user-form-section, .user-list-section {
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        margin-bottom: 20px;
    }

    .user-form-section h3, .user-list-section h3 {
        margin-top: 0;
        margin-bottom: 15px;
        color: #005fa3;
        border-bottom: 1px solid #eee;
        padding-bottom: 10px;
    }

    .user-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
    }

    .user-table th, .user-table td {
        border: 1px solid #ddd;
        padding: 10px;
        text-align: left;
    }

    .user-table th {
        background-color: #f2f2f2;
        font-weight: bold;
    }

    .user-table tr:nth-child(even) {
        background-color: #f9f9f9;
    }

    .user-table tr:hover {
        background-color: #e9e9e9;
    }

    .user-table td.actions a.button {
        margin-right: 5px; /* Adjust spacing */
        padding: 5px 10px; /* Adjust padding */
        font-size: 0.9em; /* Adjust font size */
    }

    .user-table td.actions a.edit {
         background-color: #ffc107;
         color: #212529;
    }
    .user-table td.actions a.edit:hover {
         background-color: #e0a800;
    }
     .user-table td.actions a.delete {
         background-color: #dc3545;
         color: white;
     }
     .user-table td.actions a.delete:hover {
         background-color: #c82333;
     }

    .user-form-section label {
        display: block;
        margin-bottom: 5px;
        font-weight: bold;
    }

    .user-form-section input[type="text"],
    .user-form-section input[type="password"],
    .user-form-section select {
        width: 100%;
        padding: 8px;
        margin-bottom: 15px;
        border: 1px solid #ccc;
        border-radius: 4px;
        box-sizing: border-box;
    }

    .user-form-section button.button {
        padding: 10px 20px;
        background-color: #005fa3;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 1rem;
        transition: background-color 0.3s ease;
    }
    .user-form-section button.button:hover {
        background-color: #004577;
    }
</style>