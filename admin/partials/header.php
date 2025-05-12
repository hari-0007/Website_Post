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

// admin/partials/header.php

// This file contains the opening HTML tags, head, and the main admin navigation.
// It assumes $loggedIn, $requestedView, $_SESSION['admin_display_name'] are available from dashboard.php
// It now also assumes $_SESSION['admin_role'] might be available for privilege checks.

// Include config for siteUrl in title, although not strictly necessary here
// require_once __DIR__ . '/../includes/config.php'; // Uncomment if you need config vars here

// Get the user's role from the session, default to a low privilege if not set
// $loggedIn and $requestedView are expected to be set by the including file (dashboard.php)
// $userRole = $_SESSION['admin_role'] ?? 'user'; // This was in the original, ensure $loggedIn is primary for conditional logic if this is used for display

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard<?= isset($loggedIn) && $loggedIn ? ' - ' . ucwords(str_replace('_', ' ', $requestedView)) : ' - Login' ?></title>
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
         /* Style for the Profile dropdown container */
        .profile-dropdown {
            position: relative;
            display: inline-block;
            margin-left: auto; /* Pushes dropdown to the right */
        }

        /* Style for the Profile link/button */
        .profile-dropdown > a, .admin-nav a {
            text-decoration: none;
            color: #fff;
            font-weight: bold;
            padding: 5px 10px;
            border-radius: 4px;
            transition: background-color 0.3s ease;
            cursor: pointer; /* Indicate it's clickable */
        }

        .profile-dropdown > a:hover, .admin-nav a:hover {
            background-color: #004577;
        }

        /* Style for the dropdown content (hidden by default) */
        .profile-dropdown-content {
            display: none;
            position: absolute;
            background-color: #f9f9f9;
            min-width: 160px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            z-index: 1;
            border-radius: 4px;
            overflow: hidden; /* Ensure border-radius is applied to children */
            top: 100%; /* Position below the profile link */
            right: 0; /* Align dropdown to the right of the profile link */
             margin-top: 5px; /* Small gap between link and dropdown */
        }

        /* Style for dropdown links */
        .profile-dropdown-content a {
            color: #333;
            padding: 10px 15px;
            text-decoration: none;
            display: block;
            font-weight: normal; /* Reset font-weight from nav link */
            transition: background-color 0.2s ease;
        }

        .profile-dropdown-content a:hover {
            background-color: #e2e2e2;
        }

        /* Show the dropdown menu on hover */
        .profile-dropdown:hover .profile-dropdown-content {
            display: block;
        }

         .admin-nav a.button {
             background-color: #dc3545;
             color: white;
             padding: 8px 12px;
         }
         .admin-nav a.button:hover {
             background-color: #c82333;
         }

         /* Shared form styles (used in views) */
         form label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        form input[type="text"],
        form input[type="email"],
        form input[type="number"],
         form input[type="password"],
        form textarea {
            width: 100%;
            padding: 8px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
         form input[type="number"] {
             width: auto;
         }
         /* Button style is covered by .button class */


         /* Styles for Status Messages (used in dashboard.php) */
         .status-message {
            /* Ensure this is placed correctly in dashboard.php, outside .admin-nav if messages are global */
            margin: 15px 0; /* Adjusted margin if it's inside the container but before main content */
            padding: 10px;
            border-radius: 5px;
            font-weight: bold;
         }
         .status-message.success {
             background-color: #d4edda;
             color: #155724;
             border-color: #c3e6cb;
         }
         .status-message.error {
             background-color: #f8d7da;
             color: #721c24;
             border-color: #f5c6cb;
         }
          .status-message.warning {
              background-color: #fff3cd;
              color: #856404;
              border-color: #ffeeba;
          }
           .status-message.info {
               background-color: #d1ecf1;
               color: #0c5460;
               border-color: #bee5eb;
           }


         .main-content {
             margin-top: 20px;
         }

         /* Login, Register, Forgot Password form styles (used in views/login.php) */
         .login-form, .register-form, .forgot-password-form { /* Corrected class name */
            max-width: 400px;
            margin: 50px auto;
            padding: 30px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
         }
          .login-form h2, .register-form h2, .forgot-password-form h2 { /* Corrected class name */
              text-align: center;
              margin-bottom: 30px;
              color: #005fa3;
          }
          .login-form p, .register-form p, .forgot-password-form p { /* Corrected class name */
               text-align: center;
               margin-top: 15px;
           }
           .login-form p a, .register-form p a, .forgot-password-form p a { /* Corrected class name */
               color: #005fa3;
               text-decoration: none;
           }
           .login-form p a:hover, .register-form p a:hover, .forgot-password-form p a:hover { /* Corrected class name */
               text-decoration: underline;
           }
            /* Specific input styles for login forms */
            .login-form input[type="text"],
            .login-form input[type="password"],
            .register-form input[type="text"],
            .register-form input[type="password"],
            .register-form input[type="email"],
            .forgot-password-form input[type="email"] { /* Corrected class name */
                 width: 100%;
                 padding: 10px;
                 margin-bottom: 20px;
                 border: 1px solid #ccc;
                 border-radius: 4px;
                 box-sizing: border-box;
            }
             .login-form button, .register-form button, .forgot-password-form button { /* Corrected class name */
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
             .login-form button:hover, .register-form button:hover, .forgot-password-form button:hover { /* Corrected class name */
                 background-color: #004577;
             }
              .login-error { /* Specific style for login error */
                  color: #dc3545;
                  margin-bottom: 15px;
                  text-align: center;
              }


         /* Styles for Stats Grid (used in views/dashboard_view.php) */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background-color: #e9e9e9;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
        }
        .stat-card h4 {
            margin-top: 0;
            margin-bottom: 5px;
            color: #005fa3;
        }
        .stat-card p {
            margin: 0;
            font-size: 1.5em;
            font-weight: bold;
        }

        /* Styles for Chart (used in views/dashboard_view.php) */
        .chart-container {
            margin-top: 20px;
             padding-top: 20px;
             border-top: 1px solid #eee;
        }
         .chart-container canvas {
             max-height: 400px; /* Limit chart height */
         }

         /* Styles for Message Output (used in views/generate_message_view.php) */
         .message-output {
             margin-top: 20px;
             border: 1px solid #ccc;
             padding: 15px;
             background-color: #e9e9e9;
             border-radius: 5px;
         }
         .message-output textarea {
             width: 100%;
             min-height: 200px;
             padding: 10px;
             border: 1px solid #ccc;
             border-radius: 4px;
             font-family: Consolas, Monaco, 'Andale Mono', 'Ubuntu Mono', monospace;
             resize: vertical;
             box-sizing: border-box;
         }
         .note {
             margin-top: 15px;
             padding: 10px;
             border: 1px dashed #005fa3;
             background-color: #eef;
             border-radius: 5px;
             font-size: 0.9em;
         }


         /* Styles for Job Table (used in views/manage_jobs_view.php) */
         .job-table {
             width: 100%;
             border-collapse: collapse;
             margin-top: 20px;
         }
         .job-table th, .job-table td {
             border: 1px solid #ddd;
             padding: 10px;
             text-align: left;
         }
         .job-table th {
             background-color: #f2f2f2;
             font-weight: bold;
         }
         .job-table tr:nth-child(even) {
             background-color: #f9f9f9;
         }
         .job-table tr:hover {
             background-color: #e9e9e9;
         }
         .job-table td.actions a {
             margin-right: 10px;
             text-decoration: none;
             padding: 5px 10px;
             border-radius: 4px;
             display: inline-block;
         }
         .job-table td.actions a.edit {
             background-color: #ffc107;
             color: #212529;
         }
         .job-table td.actions a.edit:hover {
             background-color: #e0a800;
         }
          .job-table td.actions a.delete {
             background-color: #dc3545;
             color: white;
         }
         .job-table td.actions a.delete:hover {
             background-color: #c82333;
         }
         .no-jobs {
             text-align: center;
             margin-top: 20px;
         }

/* Add these styles within the <style> block in header.php */
.status-area {
        padding: 10px 20px; /* Padding matches container/main-content horizontal padding */
        margin: 10px auto; /* Center and add space above/below */
        width: 100%; /* Full width */
        max-width: 960px; /* Max width to align with container */
        box-sizing: border-box; /* Include padding in width */
        border-radius: 4px;
        font-weight: bold;
        text-align: center;
        opacity: 1;
        transition: opacity 0.5s ease-in-out;
    }

    .status-area.hidden {
        opacity: 0;
        height: 0;
        overflow: hidden;
        margin-top: 0;
        margin-bottom: 0;
        padding-top: 0;
        padding-bottom: 0;
    }

    .status-area.success {
        background-color: #d4edda; /* Light green */
        color: #155724; /* Dark green */
        border: 1px solid #c3e6cb;
    }

    .status-area.error {
        background-color: #f8d7da; /* Light red */
        color: #721c24; /* Dark red */
        border: 1px solid #f5c6cb;
    }

    .status-area.warning {
        background-color: #fff3cd; /* Light yellow */
        color: #856404; /* Dark yellow */
        border: 1px solid #ffeeba;
    }

     .status-area.info {
         background-color: #d1ecf1; /* Light blue */
         color: #0c5460; /* Dark blue */
         border: 1px solid #bee5eb;
     }


         /* Profile View Specific Styles (used in views/profile_view.php) */
         .profile-forms {
             display: flex;
             flex-direction: column;
             gap: 30px;
         }
         .profile-form-section {
             background: #fff;
             padding: 20px;
             border-radius: 8px;
             box-shadow: 0 2px 5px rgba(0,0,0,0.05);
         }
          .profile-form-section h3 {
              margin-top: 0;
               margin-bottom: 15px;
               color: #005fa3;
               border-bottom: 1px solid #eee;
               padding-bottom: 10px;
          }
           .profile-form-section p strong {
               display: inline-block;
               width: 120px; /* Fixed width for label */
           }
    </style>
    <?php if (isset($loggedIn) && $loggedIn && isset($requestedView) && $requestedView === 'dashboard'): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php endif; ?>
</head>
<body>

<?php
// REMOVED the problematic if/else block that tried to require_once 'views/login.php'
// The dashboard.php file is responsible for including the login view when appropriate.
?>
