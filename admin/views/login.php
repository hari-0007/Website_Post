<?php

// admin/views/login.php - Displays Login, Register, Forgot Password Forms

// This file is included by dashboard.php when the user is not logged in.
// It assumes $loginError, $registerMessage, $forgotPasswordMessage, $statusClass are available.
?>

<?php if ($requestedAction === 'register_form'): // Display Register Form
    require_once __DIR__ . '/../partials/footer.php';
    ?>
    
    <div class="register-form">
        <h2>Register New User</h2>
        <?php if ($registerMessage): ?>
            <p class="status-message <?= $statusClass ?>"><?= htmlspecialchars($registerMessage) ?></p>
        <?php endif; ?>
        <form method="POST" action="auth.php"> <input type="hidden" name="action" value="register"> <label for="new_username">Username:</label>
             <input type="text" id="new_username" name="new_username" value="<?= htmlspecialchars($_POST['new_username'] ?? '') ?>" required autofocus>

             <label for="new_display_name">Display Name:</label>
             <input type="text" id="new_display_name" name="new_display_name" value="<?= htmlspecialchars($_POST['new_display_name'] ?? '') ?>" required>

            <label for="new_password">Password:</label>
            <input type="password" id="new_password" name="new_password" required>

            <label for="confirm_password">Confirm Password:</label>
            <input type="password" id="confirm_password" name="confirm_password" required>

            <button type="submit" name="register_btn">Register</button>
        </form>
         <p><a href="dashboard.php">Back to Login</a></p>
    </div>

<?php elseif ($requestedAction === 'forgot_password_form'): // Display Forgot Password Form ?>
    <div class="forgot-password-form">
         <h2>Forgot Password</h2>
         <?php if ($forgotPasswordMessage): ?>
             <p class="status-message <?= $statusClass ?>"><?= htmlspecialchars($forgotPasswordMessage) ?></p>
         <?php endif; ?>
         <p>Enter your email address to receive a password reset link.</p>
         <form method="POST" action="auth.php"> <input type="hidden" name="action" value="forgot_password"> <label for="email">Email:</label>
             <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
             <button type="submit" name="forgot_password_btn">Send Reset Link</button>
         </form>
         <p><a href="dashboard.php">Back to Login</a></p>
    </div>

<?php else: // Default: Display Login Form ?>
    <div class="login-form">
        <h2>Admin Login</h2>
        <?php if ($loginError): // Display login error only if it exists ?>
            <p class="login-error"><?= htmlspecialchars($loginError) ?></p>
        <?php endif; ?>
        <form method="POST" action="auth.php"> <input type="hidden" name="action" value="login"> <label for="username">Username:</label>
            <input type="text" id="username" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autofocus>

            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>

            <button type="submit" name="login_btn">Login</button>
        </form>
         <p><a href="dashboard.php?action=register_form">Register New User</a></p>
         <p><a href="dashboard.php?action=forgot_password_form">Forgot Password?</a></p>
    </div>
<?php endif; ?>
