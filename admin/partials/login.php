<?php
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Login</title>
</head>
<body>
    <h1>Login</h1>
    <?php
    session_start();
    $loginError = $_SESSION['admin_status']['message'] ?? '';
    unset($_SESSION['admin_status']); // Clear the status message
    ?>
    <?php if ($loginError): ?>
        <p style="color: red;"><?php echo htmlspecialchars($loginError); ?></p>
    <?php endif; ?>
    <form method="POST" action="auth.php?action=login">
        <label for="username">Username:</label>
        <input type="text" name="username" id="username" required>
        <br>
        <label for="password">Password:</label>
        <input type="password" name="password" id="password" required>
        <br>
        <button type="submit">Login</button>
    </form>
</body>
</html>
