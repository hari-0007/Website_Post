<?php

// admin/views/login.php - Displays Login, Register, Forgot Password Forms

// This file is included by dashboard.php when the user is not logged in.
// It assumes $loginError, $registerMessage, $forgotPasswordMessage, $statusClass are available.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f9;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .login-form, .register-form, .forgot-password-form {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }
        h2 {
            margin-bottom: 20px;
            font-size: 24px;
            text-align: center;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }
        input {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        button {
            width: 100%;
            padding: 10px;
            background-color: #007bff;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #0056b3;
        }
        .status-message {
            margin-bottom: 15px;
            padding: 10px;
            border-radius: 4px;
            text-align: center;
        }
        .status-message.error {
            background-color: #f8d7da;
            color: #721c24;
        }
        .status-message.success {
            background-color: #d4edda;
            color: #155724;
        }
        .login-error {
            color: #d9534f;
            text-align: center;
            margin-bottom: 15px;
        }
        p {
            text-align: center;
        }
        a {
            color: #007bff;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <?php if ($requestedAction === 'register_form'): // Display Register Form ?>
        <div class="register-form">
            <h2>Register New User</h2>
            <?php if ($registerMessage): ?>
                <p class="status-message <?= $statusClass ?>"><?= htmlspecialchars($registerMessage) ?></p>
            <?php endif; ?>
            <form method="POST" action="auth.php">
                <input type="hidden" name="action" value="register">
                <label for="new_username">Username:</label>
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
            <form method="POST" action="auth.php">
                <input type="hidden" name="action" value="forgot_password">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
                <button type="submit" name="forgot_password_btn">Send Reset Link</button>
            </form>
            <p><a href="dashboard.php">Back to Login</a></p>
        </div>
    <?php else: // Default: Display Login Form ?>
        <div class="login-form">
            <h2>Admin Login</h2>
            <?php
            // Display any status messages
            if (!empty($_SESSION['admin_status'])) {
                $status = $_SESSION['admin_status'];
                echo '<div class="status-message ' . htmlspecialchars($status['type']) . '">' . htmlspecialchars($status['message']) . '</div>';
                unset($_SESSION['admin_status']); // Clear the status message after displaying it
            }
            ?>
            <?php if ($loginError): // Display login error only if it exists ?>
                <p class="login-error"><?= htmlspecialchars($loginError) ?></p>
            <?php endif; ?>
            <form method="POST" action="auth.php?action=login">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required>
                
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
                
                <button type="submit">Login</button>
            </form>
            <p><a href="dashboard.php?action=register_form">Register New User</a></p>
            <p><a href="dashboard.php?action=forgot_password_form">Forgot Password?</a></p>
        </div>
    <?php endif; ?>
</body>
</html>
