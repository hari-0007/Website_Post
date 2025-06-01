<?php
// Ensure no whitespace or output before this tag
// error_log("[DEBUG] header.php: LoggedIn: " . (isset($loggedIn) ? ($loggedIn ? 'Yes' : 'No') : 'NOT SET') . " | RequestedView: " . ($requestedView ?? 'NOT SET'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel<?= isset($loggedIn) && $loggedIn && isset($requestedView) ? ' - ' . ucwords(str_replace('_', ' ', $requestedView)) : '' ?></title>
    <style>
        /* SolarWinds-Inspired Professional Admin Panel Styles */
        :root {
            --primary-color: #0078D4; /* Professional Blue (e.g., Microsoft Blue) */
            --primary-color-darker: #005A9E;
            --primary-color-lighter: #B3D7F2; /* Lighter shade for hovers/focus */

            --secondary-color: #6c757d; /* Standard Grey */
            --secondary-color-darker: #545b62;

            --sidebar-bg: #222831; /* New Darker Sidebar BG */
            --sidebar-brand-bg: #1A202C; /* Slightly different dark for brand */
            --sidebar-link-color: #D1D5DB; /* Lighter grey for link text */
            --sidebar-link-hover-color: #ffffff;
            --sidebar-link-hover-bg: #393E46; /* Subtle hover background */
            --sidebar-link-active-color: #ffffff;
            --sidebar-link-active-bg: var(--primary-color-darker); /* Darker primary for active */
            --sidebar-icon-color: #9CA3AF; /* Slightly lighter icon color */

            --topbar-bg: #ffffff;
            --topbar-border-color: #E2E8F0; /* Light Grey Border */
            --topbar-text-color: #1A202C; /* Very Dark Grey / Off-Black */

            --body-bg: #F7FAFC; /* Very Light Grey Background */
            --card-bg: #ffffff;
            --text-color: #2D3748; /* Dark Grey for Text */
            --text-color-light: #4A5568; /* Medium Grey */
            --text-muted: #718096; /* Lighter Grey for muted text */
            --border-color: #CBD5E0; /* Standard Border Color */

            --success-color: #38A169; /* Green */
            --error-color: #E53E3E;   /* Red */
            --warning-color: #DD6B20; /* Orange */
            --info-color: var(--primary-color);

            --success-bg: #F0FFF4; --success-text: #2F855A; --success-border: #9AE6B4;
            --error-bg: #FFF5F5;   --error-text: #C53030;   --error-border: #FEB2B2;
            --warning-bg: #FFFBEB; --warning-text: #B7791F; --warning-border: #FBD38D;
            --info-bg: #EBF8FF;    --info-text: #2B6CB0;    --info-border: #90CDF4;

            --font-family-sans-serif: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji";
            --box-shadow-sm: 0 .125rem .25rem rgba(0,0,0,.075);
            --box-shadow: 0 .5rem 1rem rgba(0,0,0,.15);
            --border-radius: .375rem; /* Bootstrap-like rounded corners */
        }

        *, *::before, *::after {
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-family-sans-serif);
            margin: 0;
            background-color: var(--body-bg);
            color: var(--text-color);
            /* transition: margin-left 0.3s cubic-bezier(0.25, 0.8, 0.25, 1); */ /* No longer needed for fixed sidebar */
            font-size: 0.95rem; /* Base font size */
            line-height: 1.5; /* Slightly tighter line height for a more compact feel */
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* --- Top Bar --- */
        .admin-topbar {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 60px;
            background-color: var(--topbar-bg);
            color: var(--topbar-text-color);
            display: flex;
            align-items: center;
            padding: 0 20px; /* Consistent padding */
            box-shadow: 0 1px 3px rgba(0,0,0,0.05); /* Softer, more subtle shadow */
            border-bottom: 1px solid var(--topbar-border-color);
            z-index: 1030; /* High z-index */
        }

        /* Sidebar toggle button is removed, so this style is no longer needed */
        #sidebarToggleBtn {
            background: none;
            border: none;
            color: var(--primary-color);
            font-size: 24px; /* Adjust for better visual balance */
            line-height: 1;
            cursor: pointer;
            margin-right: 15px;
            padding: 8px; /* Make it easier to click */
            border-radius: var(--border-radius);
        }
        /* #sidebarToggleBtn:hover { 
            background-color: var(--body-bg);
        } */

        .admin-topbar-title {
            font-weight: 600;
            font-size: 1.2em; /* Slightly larger title */
            color: var(--text-color); /* Darker for better contrast on white */
        }

        .admin-topbar-user {
            margin-left: auto; /* Pushes to the right */
            font-weight: 400;
            color: var(--text-muted);
            display: flex;
            align-items: center;
        }
        .admin-topbar-user .user-icon { /* Specific class for user icon */
            margin-right: 8px;
            color: var(--primary-color);
            font-size: 1.2em;
        }

        /* --- Sidebar --- */
        .admin-sidebar {
            position: fixed;
            top: 0;
            left: 0; /* Always open */
            width: 250px;
            height: 100%;
            background-color: var(--sidebar-bg);
            color: var(--sidebar-link-color);
            box-shadow: 2px 0 6px rgba(0,0,0,0.1); /* Refined, softer shadow */
            /* transition: left 0.3s cubic-bezier(0.25, 0.8, 0.25, 1); */ /* No longer needed */
            z-index: 1020; /* Below topbar, above overlay if any */
            display: flex; /* For flex column layout */
            flex-direction: column;
        }

        .admin-sidebar-brand {
            height: 60px; /* Match topbar height for alignment */
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25em;
            font-weight: 700;
            color: #ffffff;
            background-color: var(--sidebar-brand-bg);
            text-decoration: none;
            flex-shrink: 0; /* Prevent shrinking */
            border-bottom: 1px solid var(--sidebar-link-hover-bg); /* Consistent border color */
            transition: background-color 0.2s ease;
        }
        .admin-sidebar-brand:hover {
            background-color: var(--sidebar-link-hover-bg); 
        }
        .admin-sidebar-brand .logo-icon { /* For a potential logo icon */
            margin-right: 10px;
            font-size: 1.5em;
        }

        .admin-sidebar-nav-wrapper {
            overflow-y: auto;
            flex-grow: 1;
            padding: 15px 0; /* Increased vertical padding */
        }
        
        /* Custom Scrollbar for Webkit Browsers */
        .admin-sidebar-nav-wrapper::-webkit-scrollbar { width: 6px; }
        .admin-sidebar-nav-wrapper::-webkit-scrollbar-track { background: rgba(0,0,0,0.1); } /* Slightly lighter track */
        .admin-sidebar-nav-wrapper::-webkit-scrollbar-thumb { background: var(--sidebar-link-hover-bg); border-radius: 3px; }
        .admin-sidebar-nav-wrapper::-webkit-scrollbar-thumb:hover { background: var(--sidebar-icon-color); }

        .admin-sidebar nav a {
            display: flex;
            align-items: center;
            padding: 11px 20px; /* Slightly adjusted padding for vertical balance */
            margin: 4px 10px; /* More vertical margin for better separation */
            color: var(--sidebar-link-color);
            text-decoration: none;
            font-size: 0.9rem;
            transition: background-color 0.15s ease, color 0.15s ease;
            border-radius: var(--border-radius);
            font-weight: 500;
            position: relative;
        }

        .admin-sidebar nav a:hover {
            background-color: var(--sidebar-link-hover-bg);
            color: var(--sidebar-link-hover-color);
        }

        .admin-sidebar nav a.active {
            background-color: var(--sidebar-link-active-bg);
            color: var(--sidebar-link-active-color);
            font-weight: 500; /* Slightly less bold for active, rely on bg and border */
            box-shadow: inset 4px 0 0 var(--primary-color); /* More prominent inner left border */
        }
        .admin-sidebar nav a.active:hover {
            background-color: var(--sidebar-link-active-bg); /* Keep active background on hover */
            color: var(--sidebar-link-active-color);
            box-shadow: inset 3px 0 0 var(--primary-color-lighter);
        }
        .admin-sidebar nav a.active .nav-icon {
            color: var(--sidebar-link-active-color); /* Ensure icon color matches on active */
        }

        .admin-sidebar nav a .nav-icon {
            margin-right: 12px; /* Space between icon and text */
            width: 22px; /* Fixed width for alignment */
            text-align: center;
            font-size: 1.05em; 
            color: var(--sidebar-icon-color);
            transition: color 0.2s ease;
            line-height: 1; /* Ensure icon vertical alignment */
        }
        .admin-sidebar nav a:hover .nav-icon {
            color: var(--sidebar-link-hover-color);
        }
.sidebar-submenu-container {
            padding-left: 15px; /* Indent sub-menu */
            margin-top: -2px; 
            margin-bottom: 8px; /* More space after submenu */
            background-color: rgba(0,0,0,0.25); /* Even darker for submenu block */
            border-radius: var(--border-radius);
            margin-left: 10px;
            margin-right: 10px;
            padding-top: 5px;
            padding-bottom: 5px;
        }

        .submenu-link {
            display: flex;
            align-items: center; /* Align icon and text */
            padding: 8px 15px 8px 10px; /* Adjusted padding for sub-items, less left for icon */
            margin: 2px 0; 
            color: var(--sidebar-link-color);
            text-decoration: none;
            transition: background-color 0.15s ease, color 0.15s ease;
            border-radius: calc(var(--border-radius) - 2px); /* Slightly smaller radius */
            font-weight: 400; /* Lighter than main links */
            font-size: 0.9em; /* Slightly smaller */
        }
        .submenu-link:hover {
            background-color: var(--sidebar-link-hover-bg);
            color: var(--sidebar-link-hover-color);
        }
        .submenu-link.active {
            background-color: var(--primary-color); /* Use primary color for active submenu */
            color: var(--sidebar-link-active-color);
            font-weight: 500;
        }
        .submenu-link.active:hover {
            background-color: var(--primary-color-darker); /* Darken on hover if active */
        }
        .unread-badge {
            background-color: var(--error-color);
            color: white;
            border-radius: var(--border-radius);
            padding: 0.2em 0.5em;
            font-size: 0.7em;
            margin-left: auto; /* Pushes badge to the right */
            font-weight: 700;
            line-height: 1;
        }

        /* --- Main Content Container --- */
        .container {
            max-width: 1300px; /* Wider for modern layouts */
            margin: 20px auto; /* Reduced top/bottom margin */
            background: var(--card-bg);
            padding: 20px 25px; /* Adjusted padding */
            padding-top: calc(60px + 20px); /* Account for fixed top bar */
            border-radius: var(--border-radius); /* Consistent border-radius */
            box-shadow: 0 2px 10px rgba(0,0,0,0.07); /* Softer, more modern shadow */
            /* transition: margin-left 0.3s cubic-bezier(0.25, 0.8, 0.25, 1); */ /* No longer needed */
            border: 1px solid var(--border-color);
        }

        body.sidebar-open .container {
            margin-left: calc(max(25px, (100% - 1200px)/2) + 250px);
        }

        @media (max-width: 1570px) { /* 1300px container + 250px sidebar + 20px margin */
            body.sidebar-open .container {
                margin-left: calc(250px + 20px);
            }
        }
        @media (max-width: 768px) { /* Tablet and smaller */
            .admin-sidebar {
                width: 240px; /* Slightly narrower for small screens */
                left: 0; /* Still fixed open, but media query could hide it if a toggle was re-introduced for mobile */
                /* Consider adding a toggle for mobile if screen real estate is an issue */
            }
            body.sidebar-open .container {
                 margin-left: 20px; /* Sidebar overlays content */
            }
            .admin-topbar-title {
                font-size: 1em; /* Smaller title */
            }
            .admin-topbar-user span:not(.user-icon) {
                display: none; /* Hide username text, keep icon */
            }
            .container {
                padding: 20px;
                padding-top: calc(60px + 15px);
                margin: 15px auto;
            }
        }
        /* Example: If you wanted to hide sidebar on small screens and add a toggle back for mobile */
        /* @media (max-width: 768px) {
            .admin-sidebar { left: -240px; } 
            body.sidebar-open .admin-sidebar { left: 0; } ... and so on for container margin */
        @media (max-width: 480px) { /* Mobile */
            .admin-topbar-title { display: none; } /* Hide title completely */
        }

        /* --- General Element Styling --- */
        h1, h2, h3, h4, h5, h6 {
            color: var(--text-color);
            font-weight: 600; /* Bolder headings for clarity */
            margin-top: 0;
            margin-bottom: 0.75em;
            line-height: 1.3;
        }
        h2 { /* Main section titles */
            color: var(--primary-color);
            margin-bottom: 25px;
            font-size: 1.5em;
            border-bottom: 2px solid var(--primary-color-lighter); /* Accent border */
            padding-bottom: 12px;
        }

        a {
            color: var(--primary-color);
            text-decoration: none;
            transition: color 0.15s ease-in-out;
        }
        a:hover {
            color: var(--primary-color-darker);
            text-decoration: underline;
        }
        a:focus-visible { /* Accessibility enhancement for keyboard navigation */
            outline: 2px solid var(--primary-color-lighter);
            outline-offset: 2px;
            border-radius: 2px;
            text-decoration: none; /* Avoid underline conflicting with outline */
        }


        /* Forms */
        form label {
            display: block;
            margin-bottom: .5rem;
            font-weight: 500; color: var(--text-color-light);
        }
        form input[type="text"],
        form input[type="email"],
        form input[type="number"],
        form input[type="password"],
        form textarea,
        form select { /* Common input styling */
            display: block;
            width: 100%;
            padding: .5rem .75rem; /* Bootstrap-like padding */
            font-size: 0.95rem;
            font-weight: 400;
            line-height: 1.5;
            color: var(--text-color);
            background-color: #fff;
            background-clip: padding-box;
            border: 1px solid var(--border-color);
            appearance: none; /* Remove default styling */
            border-radius: var(--border-radius);
            transition: border-color .2s ease-in-out, box-shadow .2s ease-in-out;
            margin-bottom: 1rem; /* Consistent bottom margin */
        } 
        form input:focus, form textarea:focus, form select:focus {
            border-color: var(--primary-color-lighter);
            outline: 0;
            box-shadow: 0 0 0 .2rem var(--primary-color-lighter);
        }
        form textarea {
            min-height: 120px;
            resize: vertical;
        }

        /* Buttons */
        .button, button, input[type="submit"], input[type="button"], input[type="reset"] {
            display: inline-block;
            font-weight: 500; /* Medium weight for buttons */
            color: #fff;
            text-align: center;
            vertical-align: middle;
            cursor: pointer;
            user-select: none;
            background-color: var(--primary-color);
            border: 1px solid var(--primary-color);
            padding: .5rem 1rem; /* Consistent padding */
            font-size: 0.9rem; /* Slightly smaller button text */
            line-height: 1.5;
            border-radius: var(--border-radius);
            text-decoration: none;
            transition: color .15s ease-in-out,background-color .15s ease-in-out,border-color .15s ease-in-out,box-shadow .15s ease-in-out;
        }
        .button:hover, button:hover, input[type="submit"]:hover, input[type="button"]:hover, input[type="reset"]:hover {
            background-color: var(--primary-color-darker);
            border-color: var(--primary-color-darker);
            color: #fff;
            text-decoration: none;
        }
        .button:focus, button:focus {
            outline: 0;
            box-shadow: 0 0 0 .2rem var(--primary-color-lighter);
        }
        .button.button-secondary { background-color: var(--secondary-color); border-color: var(--secondary-color); }
        .button.button-secondary:hover { background-color: var(--secondary-color-darker); border-color: var(--secondary-color-darker); }
        .button.button-danger { background-color: var(--error-color); border-color: var(--error-color); } 
        .button.button-danger:hover { background-color: #c0392b; border-color: #c0392b; } /* Darker Alizarin */
        .button.button-success { background-color: var(--success-color); border-color: var(--success-color); }
        .button.button-success:hover { background-color: #27ae60; border-color: #27ae60; } /* Darker Emerald */
        .button.button-warning { background-color: #ffc107; border-color: #ffc107; color: #212529; }
        .button.button-warning:hover { background-color: #e0a800; border-color: #d39e00; color: #212529; }


        /* Status Messages */
        .status-message {
            margin: 1rem 0;
            padding: .85rem 1.25rem; /* Slightly more padding */
            border-radius: var(--border-radius);
            font-weight: 500;
            border: 1px solid transparent;
        }
        .status-message.success { background-color: var(--success-bg); color: var(--success-text); border-color: var(--success-border); }
        .status-message.error   { background-color: var(--error-bg);   color: var(--error-text);   border-color: var(--error-border);   }
        .status-message.warning { background-color: var(--warning-bg); color: var(--warning-text); border-color: var(--warning-border); }
        .status-message.info    { background-color: var(--info-bg);    color: var(--info-text);    border-color: var(--info-border);    }

        /* Login Form Specifics (if needed, most styles are general now) */
        .login-form {
            max-width: 400px; margin: 60px auto; padding: 30px; background: var(--card-bg);
            border-radius: var(--border-radius); box-shadow: var(--box-shadow);
        }
        .login-form h2 { text-align: center; margin-bottom: 30px; font-size: 1.75em; }
        .login-form p { text-align: center; margin-top: 1rem; }

        /* Table Styles */
        .table-responsive-wrapper {
            overflow-x: auto;
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color); /* Consistent border */
            margin-top: 1.5rem; /* More space above tables */
            box-shadow: var(--box-shadow-sm);
        }
        .professional-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
            color: var(--text-color-light);
        }
        .professional-table th,
        .professional-table td {
            text-align: left;
            padding: .75rem 1rem; /* Consistent padding */
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }
        .professional-table thead th {
            background-color: var(--body-bg); /* Light grey header */
            color: var(--text-color-light); /* Slightly lighter text for header */
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.03em; /* Subtle letter spacing */
            border-bottom-width: 2px; /* Thicker bottom border for header */
            border-top: 0; /* Remove top border if table wrapper has one */
        }
        .professional-table tbody tr:hover {
            background-color: #f5f7f8; /* Very subtle hover for rows */
        }
        .professional-table tbody tr:last-child td {
            border-bottom: 0; /* No border for last row cells if table wrapper has border */
        }
        .professional-table td.actions a, .professional-table td.actions button {
            margin-right: .5rem;
            padding: .375rem .75rem; /* Smaller padding for action buttons */
            font-size: 0.85rem;
        }
        .professional-table td.actions a:last-child, .professional-table td.actions button:last-child {
            margin-right: 0;
        }
        /* Specific action button colors can use .button-warning, .button-danger etc. */

        /* Stats Cards */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.25rem; margin-bottom: 1.5rem; }
        .stat-card {
            background-color: var(--card-bg); padding: 1.25rem; border-radius: var(--border-radius);
            text-align: left; border: 1px solid var(--border-color); box-shadow: var(--box-shadow-sm);
        }
        .stat-card h4 { margin-top: 0; margin-bottom: .5rem; color: var(--text-muted); font-size: 0.85em; text-transform: uppercase; font-weight: 600; letter-spacing: 0.03em; }
        .stat-card p { margin: 0; font-size: 2em; font-weight: 600; color: var(--text-color); }

        /* Chart Container */
        .chart-container {
            margin-top: 1.5rem; padding: 1.5rem; border: 1px solid var(--border-color);
            border-radius: var(--border-radius); background-color: var(--card-bg); box-shadow: var(--box-shadow-sm);
        }
        .chart-container canvas { max-height: 320px; } /* Slightly smaller max height */

        /* Sub-navigation styles (from dashboard.php, now centralized) */
        .sub-nav {
            background-color: transparent; /* Blend with body or container bg */
            padding: 10px 15px; /* Added horizontal padding */
            margin-bottom: 25px; /* Increased margin */
            text-align: center;
            border-radius: var(--border-radius);
            border-bottom: 1px solid var(--border-color); /* Underline style */
            box-shadow: none; 
        }
        .sub-nav a {
            color: var(--primary-color);
            padding: 8px 15px; /* Adjusted padding */
            text-decoration: none;
            font-weight: 500;
            transition: background-color 0.2s ease, color 0.2s ease;
            border-radius: calc(var(--border-radius) - 2px);
            margin: 0 3px;
            display: inline-block; /* Ensure proper spacing and click area */
        }
        .sub-nav a:hover {
            background-color: var(--primary-color-lighter);
            color: var(--primary-color-darker);
            text-decoration: none; /* Remove underline on hover for sub-nav */
        }
        .sub-nav a.active {
            background-color: var(--primary-color);
            color: #ffffff;
        }

    </style>
    <?php if (isset($loggedIn) && $loggedIn && isset($requestedView) && $requestedView === 'dashboard_overview'): // Only load Chart.js for the main dashboard overview initially ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php endif; ?>
</head>
<body class="<?php echo (isset($loggedIn) && $loggedIn) ? 'sidebar-open' : ''; ?>"> <?php /* Always sidebar-open if logged in */ ?>

<?php if (isset($loggedIn) && $loggedIn): ?>
    <header class="admin-topbar" id="adminTopbar">
        <?php /* <button id="sidebarToggleBtn" title="Toggle Menu">☰</button> -- Toggle button removed */ ?>
        <div class="admin-topbar-title">Admin Panel</div>
        <div class="admin-topbar-user">
            <span class="user-icon">👤</span> <!-- Replace with a proper icon -->
            <span><?php echo htmlspecialchars($_SESSION['admin_display_name'] ?? ($_SESSION['admin_username'] ?? 'Admin')); ?></span>
        </div>
    </header>

    <aside class="admin-sidebar" id="adminSidebar">
        <a href="dashboard.php?view=dashboard_overview" class="admin-sidebar-brand">
            <!-- <span class="logo-icon">🚀</span> Replace with your logo/icon -->
            YourPanel
        </a>
        <div class="admin-sidebar-nav-wrapper">
            <nav>
                <a href="dashboard.php?view=dashboard_overview" class="<?php echo (strpos($requestedView, 'dashboard_') === 0 || $requestedView === 'dashboard') ? 'active' : ''; ?>">
                    <span class="nav-icon">📊</span> Dashboard
                </a>
                <?php
                // Check if the current view is any of the dashboard sub-views
                $isAnyDashboardViewActive = (strpos($requestedView, 'dashboard_') === 0 || $requestedView === 'dashboard');
                
                if ($isAnyDashboardViewActive):
                    // Define dashboard sub-menu items
                    $dashboardSubMenus = [
                        'dashboard_overview'      => 'Overview',
                        'dashboard_service_one'   => 'Service Info',
                        'dashboard_user_info'     => 'User Stats',
                        'dashboard_job_stats'     => 'Job Stats',
                        'dashboard_service_two'   => 'Server Metrics',
                        'dashboard_visitors_info' => 'Visitors Info',
                        'dashboard_qoe'           => 'QOE'
                    ];
                    // Determine the currently active sub-view for highlighting
                    $currentActiveSubView = ($requestedView === 'dashboard') ? 'dashboard_overview' : $requestedView;
                ?>
                    <div class="sidebar-submenu-container">
                        <?php foreach ($dashboardSubMenus as $viewKey => $viewName): ?>
                            <a href="dashboard.php?view=<?php echo $viewKey; ?>" class="submenu-link <?php echo ($currentActiveSubView === $viewKey) ? 'active' : ''; ?>">
                                <span class="nav-icon sub-icon" style="font-size: 0.8em; width: auto; margin-right: 8px;">↳</span> <?php echo $viewName; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <a href="dashboard.php?view=messages" class="<?php echo ($requestedView === 'messages') ? 'active' : ''; ?>">
                    <span class="nav-icon">✉️</span> Messages
                    <?php 
                    // Use a fixed count of 13 for messages as requested, or fallback to dynamic count
                    // $displayMessagesCount = $unreadMessagesCount ?? 13; // Use dynamic if available, else 13
                    $displayMessagesCount = 13; // Hardcoded as per a previous request
                    if (isset($unreadMessagesCount) && $unreadMessagesCount > 0) { // If dynamic is available and >0, prefer it
                        $displayMessagesCount = $unreadMessagesCount;
                    }

                    if ($displayMessagesCount > 0): 
                    ?>
                        <span class="unread-badge"><?php echo $displayMessagesCount; ?></span>
                    <?php endif; ?>
                </a>
                <a href="dashboard.php?view=manage_jobs" class="<?php echo ($requestedView === 'manage_jobs' || $requestedView === 'edit_job') ? 'active' : ''; ?>">
                    <span class="nav-icon">💼</span> Manage Jobs
                </a>
                 <a href="dashboard.php?view=post_job" class="<?php echo ($requestedView === 'post_job') ? 'active' : ''; ?>">
                    <span class="nav-icon">➕</span> Post New Job
                </a>
                <a href="dashboard.php?view=reported_jobs" class="<?php echo ($requestedView === 'reported_jobs') ? 'active' : ''; ?>">
                    <span class="nav-icon">🚩</span> Reported Jobs
                </a>
                <a href="dashboard.php?view=manage_users" class="<?php echo ($requestedView === 'manage_users' || $requestedView === 'edit_user') ? 'active' : ''; ?>">
                    <span class="nav-icon">👥</span> User Manager
                </a>
                <a href="dashboard.php?view=achievements" class="<?php echo ($requestedView === 'achievements') ? 'active' : ''; ?>">
                    <span class="nav-icon">🏆</span> Achievements
                </a>
                <a href="dashboard.php?view=generate_message" class="<?php echo ($requestedView === 'generate_message') ? 'active' : ''; ?>">
                    <span class="nav-icon">📝</span> Generate Post
                </a>
                <a href="dashboard.php?view=logs" class="<?php echo ($requestedView === 'logs') ? 'active' : ''; ?>">
                    <span class="nav-icon">📜</span> Logs
                </a>
                <a href="dashboard.php?view=server_management" class="<?php echo ($requestedView === 'server_management') ? 'active' : ''; ?>">
                    <span class="nav-icon">⚙️</span> Server Management
                </a>
                <a href="dashboard.php?view=whatsapp_profile" class="<?php echo ($requestedView === 'whatsapp_profile') ? 'active' : ''; ?>">
                    <span class="nav-icon">📱</span> WhatsApp Profile
                </a>
                 <a href="dashboard.php?view=profile" class="<?php echo ($requestedView === 'profile') ? 'active' : ''; ?>">
                    <span class="nav-icon">👤</span> Manage Profile
                </a>
                <!-- <a href="dashboard.php?view=settings" class="<?php echo ($requestedView === 'settings') ? 'active' : ''; ?>">
                    <span class="nav-icon">🔧</span> Settings
                </a> -->
                <a href="auth.php?action=logout"> <!-- This link should not be handled by AJAX nav if it's a full redirect -->
                    <span class="nav-icon">🚪</span> Logout
                </a>
            </nav>
        </div>
    </aside>
<?php endif; ?>

<?php
// The main <div class="container"> and <div id="main-content">
// are expected to be opened in dashboard.php if the user is logged in,
// and then closed in footer.php.
// This header.php file sets up the surrounding layout (topbar, sidebar).
?>
