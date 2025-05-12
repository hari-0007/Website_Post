<?php

// admin/partials/header.php

session_start(); // Start the session to access session variables

// Check if the user is logged in
$loggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// Redirect to login page if the user is not logged in
if (!$loggedIn) {
    // Redirect to the login page if not already on it
    if (!isset($_GET['view']) || $_GET['view'] !== 'login') {
        header('Location: dashboard.php?view=login');
        exit();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard<?= isset($loggedIn) && $loggedIn ? ' - ' . ucwords(str_replace('_', ' ', $_GET['view'] ?? 'dashboard')) : ' - Login' ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            background-color: #f4f4f4;
            color: #333;
        }
        .container {
            max-width: 1000px; /* Wider container for table/chart */
            margin: 20px auto;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        h2 {
            color: #005fa3;
            margin-top: 0;
            margin-bottom: 20px;
        }
        .admin-nav {
            background-color: #005fa3;
            padding: 10px 20px;
            margin: -20px -20px 20px -20px; /* Adjust to align with container padding */
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
            display: flex;
            justify-content: flex-start;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .admin-nav a {
            text-decoration: none;
            color: #fff;
            font-weight: bold;
            padding: 5px 10px;
            border-radius: 4px;
            transition: background-color 0.3s ease;
        }
        .admin-nav a:hover {
            background-color: #004577;
        }
        .admin-nav a.button {
            background-color: #dc3545;
            color: white;
            padding: 8px 12px;
        }
        .admin-nav a.button:hover {
            background-color: #c82333;
        }
        .main-content {
            margin-top: 20px;
        }
        .login-form {
            max-width: 400px;
            margin: 50px auto;
            padding: 30px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .login-form h2 {
            text-align: center;
            margin-bottom: 30px;
            color: #005fa3;
        }
        .login-form p {
            text-align: center;
            margin-top: 15px;
        }
        .login-form p a {
            color: #005fa3;
            text-decoration: none;
        }
        .login-form p a:hover {
            text-decoration: underline;
        }
        .login-form input[type="text"],
        .login-form input[type="password"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 20px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .login-form button {
            width: 100%;
            padding: 10px;
            background-color: #005fa3;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            transition: background-color 0.3s ease;
        }
        .login-form button:hover {
            background-color: #004577;
        }
        .login-error {
            color: #dc3545;
            margin-bottom: 15px;
            text-align: center;
        }
    </style>
    <?php if (isset($loggedIn) && $loggedIn && isset($_GET['view']) && $_GET['view'] === 'dashboard'): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php endif; ?>
</head>
<body>
