<?php
ob_start();
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
$usersFilePath = __DIR__ . '/data/users.json';
$action = $_POST['action'] ?? $_GET['action'] ?? 'login';
$error = '';
$message = '';
function readUsers($filePath) {
    if (!file_exists($filePath)) {
        return [];
    }
    $data = json_decode(file_get_contents($filePath), true);
    return is_array($data) ? $data : [];
}
function writeUsers($filePath, $users) {
    file_put_contents($filePath, json_encode($users, JSON_PRETTY_PRINT));
}
// --- LOGOUT ---
if ($action === 'logout') {
    session_unset();
    session_destroy();
    header('Location: index.php');
    exit;
}
// If user is already logged in, redirect to profile
if (isset($_SESSION['user_id'])) {
    header('Location: profile.php');
    exit;
}
// --- LOGIN & REGISTRATION LOGIC ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjax = isset($_POST['ajax']);
    if ($isAjax) {
        header('Content-Type: application/json');
    }


    $users = readUsers($usersFilePath);
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    // --- REGISTRATION ---
    if ($action === 'register') {
        $username = trim($_POST['username'] ?? '');
        $role = $_POST['role'] ?? '';
        if (empty($username) || empty($email) || empty($password) || empty($role)) {
            $error = 'All fields are required.';
            if ($isAjax) { echo json_encode(['success' => false, 'message' => $error]); exit; }
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format.';
            if ($isAjax) { echo json_encode(['success' => false, 'message' => $error]); exit; }
        } elseif (!in_array($role, ['jobseeker', 'recruiter'])) {
            $error = 'Invalid role selected.';
            if ($isAjax) { echo json_encode(['success' => false, 'message' => $error]); exit; }
        } else {
            // Check if email already exists
            $emailExists = false;
            console.log('Users array:', $users);
            foreach ($users as $user) {
                if (isset($user['email']) && $user['email'] === $email) {
                    $emailExists = true;
                    break;
                }
            }
            if ($emailExists) {
                $error = 'An account with this email already exists.';
                if ($isAjax) { echo json_encode(['success' => false, 'message' => $error]); exit; }
            } else {
                $newUser = [
                    'id' => 'user_' . time() . '_' . rand(1000, 9999),
                    'username' => htmlspecialchars($username),
                'email' => trim($email),
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'role' => $role,
                    'created_at' => date('Y-m-d H:i:s')
                ];
                $users[] = $newUser;
                writeUsers($usersFilePath, $users);

                // Automatically log in the new user
                $_SESSION['user_id'] = $newUser['id'];
                $_SESSION['user_name'] = $newUser['username'];
                $_SESSION['user_role'] = $newUser['role'];

                console.log('Registration successful. Session:', $_SESSION);
                if ($isAjax) {
                    echo json_encode(['success' => true]);
                    exit;
                } else {
                    header('Location: profile.php');
                    exit;
                }
            }
        }
    }
    // --- LOGIN ---
    elseif ($action === 'login') {
        if (empty($email) || empty($password)) {
            $error = 'Email and password are required.';
            if ($isAjax) { echo json_encode(['success' => false, 'message' => $error]); exit; }
        } else {
            $foundUser = null;
            console.log('Attempting login with email:', $email);
            foreach ($users as $user) {
                if (isset($user['email']) && $user['email'] === $email) {
                    $foundUser = $user;
                    break;

                }
            }
           if ($foundUser && isset($foundUser['password']) && password_verify($password, trim($foundUser['password']))) {
                $_SESSION['user_id'] = $foundUser['id'];
                $_SESSION['user_name'] = $foundUser['username'];
                $_SESSION['user_role'] = $foundUser['role'];
                console.log('Login successful. Session:', $_SESSION);
                if ($isAjax) {
                    echo json_encode(['success' => true]);
                    exit;
                } else {
                    header('Location: profile.php');
                    exit;
                }
            } else {
                $error = 'Invalid email or password.';
                if ($isAjax) { echo json_encode(['success' => false, 'message' => $error]); exit; }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= ucfirst($action) ?> - Job Hunt</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="/data/images/logo.png">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f7f6; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .auth-container { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        h2 { text-align: center; color: #005fa3; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #333; }
        input[type="text"], input[type="email"], input[type="password"], select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { width: 100%; padding: 12px; background-color: #005fa3; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; font-weight: bold; }
        button:hover { background-color: #004a80; }
        .message { text-align: center; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .error { background-color: #f8d7da; color: #721c24; }
        .success { background-color: #d4edda; color: #155724; }
        .switch-link { text-align: center; margin-top: 20px; }
        .switch-link a { color: #005fa3; text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>
<div class="auth-container">
    <a href="index.php" style="display: block; text-align: center; margin-bottom: 20px;"><img src="/data/images/logo.png" alt="Logo" style="width: 60px;"></a>
    <h2><?= ucfirst($action) ?></h2>
    <?php if ($error): ?><p class="message error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <?php if ($message): ?><p class="message success"><?= htmlspecialchars($message) ?></p><?php endif; ?>
    <?php if ($action === 'login'): ?>
    <form method="POST" action="auth.php?action=login">
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" autocomplete="current-password" required>
        </div>
        <button type="submit">Login</button>
    </form>
    <p class="switch-link">Don't have an account? <a href="?action=register">Register here</a></p>
    <?php elseif ($action === 'register'): ?>
    <form method="POST" action="auth.php?action=register">
        <div class="form-group">
            <label for="username">Full Name</label>
            <input type="text" id="username" name="username" required>
        </div>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" autocomplete="new-password" required>
        </div>
        <div class="form-group">
            <label for="role">I am a...</label>
            <select id="role" name="role" required>
                <option value="">-- Select Role --</option>
                <option value="jobseeker">Jobseeker</option>
                <option value="recruiter">Recruiter</option>
            </select>
        </div>
        <button type="submit">Register</button>
    </form>
    <p class="switch-link">Already have an account? <a href="?action=login">Login here</a></p>
    <?php endif; ?>
</div>
</body>
</html>
<?php
ob_end_flush();
?>