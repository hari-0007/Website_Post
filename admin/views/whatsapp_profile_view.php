<?php
// admin/views/whatsapp_profile_view.php
// This view is dedicated to WhatsApp profile management and automation status.
?>

<div class="dashboard-content whatsapp-management-content">
    <h2 class="view-main-title">WhatsApp Automation Management</h2>

    <div class="dashboard-section whatsapp-management-section">
        <h4 class="section-title">WhatsApp Automation Status</h4>
        <div id="whatsappStatusArea">
            <p>Status: <span id="whatsappSessionStatus">Checking...</span></p>
        </div>
        <div id="whatsappQrArea" style="margin-top: 15px; text-align: center;">
            <!-- QR code or instructions will appear here -->
        </div>
        <div class="actions" style="margin-top: 15px;">
            <button id="checkWhatsappStatusBtn" class="action-button">Refresh Status</button>
            <button id="initiateWhatsappLoginBtn" class="action-button button-primary">Start Session / Scan QR</button>
            <button id="logoutWhatsappBtn" class="action-button button-danger">Logout Session</button>
        </div>
        <div class="actions" style="margin-top: 10px;">
            <button id="listWhatsappGroupsBtn" class="action-button button-secondary" disabled>List Groups</button>
            <button id="getWhatsappProfileBtn" class="action-button button-secondary" disabled style="margin-left:10px;">Show Profile Info</button>
        </div>
        <div id="whatsappGroupsArea" style="margin-top: 15px; max-height: 300px; overflow-y: auto; border: 1px solid #eee; padding: 10px; display: none;">
            <!-- Group list will appear here -->
        </div>
        <div id="whatsappProfileArea" style="margin-top: 15px; border: 1px solid #eee; padding: 10px; display: none;">
            <!-- Profile info will appear here -->
        </div>
    </div>
    <style>
        .view-main-title { /* Consistent main title for views */
            margin-top: 0;
            margin-bottom: 25px;
            color: var(--primary-color);
            font-size: 1.75em;
            font-weight: 600;
            padding-bottom: 10px;
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
        .dashboard-section.whatsapp-management-section { /* Ensure consistent section styling */
            background-color: var(--card-bg);
            padding: 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow-sm);
            border: 1px solid var(--border-color);
            margin-bottom: 20px;
        }
        .whatsapp-management-section .actions .action-button { 
            /* Inherit global .button styles */
            margin-right: 10px; 
            margin-bottom: 10px; /* Increased margin for better spacing if they wrap */
        }
        /* Add specific button theme classes if not already applied in HTML */
        .action-button.button-primary { background-color: var(--primary-color); border-color: var(--primary-color); color: #fff; }
        .action-button.button-primary:hover { background-color: var(--primary-color-darker); border-color: var(--primary-color-darker); }
        .action-button.button-danger { background-color: var(--error-color); border-color: var(--error-color); color: #fff; }
        .action-button.button-danger:hover { background-color: #c0392b; border-color: #c0392b; } /* From header.php */
        .action-button.button-secondary { background-color: var(--secondary-color); border-color: var(--secondary-color); color: #fff; }
        .action-button.button-secondary:hover { background-color: var(--secondary-color-darker); border-color: var(--secondary-color-darker); }

        #whatsappQrArea img { 
            max-width: 250px; 
            border: 1px solid var(--border-color); /* Use theme variable */
            padding: 5px; 
            border-radius: var(--border-radius); /* Use theme variable */
        }
        #whatsappSessionStatus { 
            font-weight: bold; 
            /* Color is set by JS */
        }
        #whatsappGroupsArea, #whatsappProfileArea {
            border: 1px solid var(--border-color); /* Use theme variable */
            padding: 15px; /* Increased padding */
            border-radius: var(--border-radius); /* Use theme variable */
            background-color: var(--body-bg); /* Slightly different background */
        }
        /* .info-list styles (used by JS for profile) should be global or defined here if specific */
        .info-list { list-style: none; padding-left: 0; font-size: 0.95rem; }
        .info-list li { padding: 8px 0; border-bottom: 1px dashed var(--border-color); }
        .info-list li:last-child { border-bottom: none; }
        .info-list strong { color: var(--text-color); }
    </style>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const statusSpan = document.getElementById('whatsappSessionStatus');
    const qrArea = document.getElementById('whatsappQrArea');
    const checkStatusBtn = document.getElementById('checkWhatsappStatusBtn');
    const loginBtn = document.getElementById('initiateWhatsappLoginBtn');
    const logoutBtn = document.getElementById('logoutWhatsappBtn');
    const listGroupsBtn = document.getElementById('listWhatsappGroupsBtn');
    const groupsArea = document.getElementById('whatsappGroupsArea');
    const getProfileBtn = document.getElementById('getWhatsappProfileBtn');
    const profileArea = document.getElementById('whatsappProfileArea');

    const TERMINAL_LOGIN_INSTRUCTIONS_HTML = '<p>To establish a persistent WhatsApp session, please open a terminal/command prompt, navigate to the <code>whatsapp_automation</code> directory of your project, and run: <code>node whatsapp_controller.js login</code>. Scan the QR code presented in the terminal. After successful login via terminal, click "Refresh Status" here.</p>';
    const QR_DISPLAYED_INSTRUCTIONS_HTML = '<p><strong>A QR code is displayed below for informational purposes.</strong> However, to establish the actual persistent WhatsApp session, you <strong>must</strong> run <code>node whatsapp_controller.js login</code> in a terminal, scan the QR code shown *there*, and then click "Refresh Status" here.</p>';

    function escapeHtml(unsafe) {
        if (typeof unsafe !== 'string') return '';
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function fetchAndDisplayProfileInfo() {
        if (!profileArea || !getProfileBtn) return;

        profileArea.innerHTML = '<p>Fetching profile info...</p>';
        profileArea.style.display = 'block';
        getProfileBtn.disabled = true;

        fetch('whatsapp_manager.php?action=get_profile_info', { cache: "no-store" })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.profile) {
                    let profileHtml = '<h5>WhatsApp Profile Information:</h5><ul class="info-list">';
                    profileHtml += `<li><strong>Name:</strong> ${escapeHtml(data.profile.pushname || 'N/A')}</li>`;
                    profileHtml += `<li><strong>Number (JID):</strong> ${escapeHtml(data.profile.me?._serialized || data.profile.me || 'N/A')}</li>`;
                    profileHtml += `<li><strong>Platform:</strong> ${escapeHtml(data.profile.platform || 'N/A')}</li>`;
                    profileHtml += '</ul>';
                    profileArea.innerHTML = profileHtml;
                } else {
                    profileArea.innerHTML = `<p>Could not load profile information: ${escapeHtml(data.message || 'Unknown error')}</p>`;
                }
            })
            .catch(error => {
                console.error('Error fetching WhatsApp profile info:', error);
                profileArea.innerHTML = '<p>Failed to connect to the server to fetch profile information.</p>';
            })
            .finally(() => {
                if (statusSpan.textContent === 'LOGGED_IN') {
                    getProfileBtn.disabled = false;
                }
            });
    }

    function fetchWhatsappStatus() {
        statusSpan.textContent = 'Checking...';
        qrArea.innerHTML = ''; // Clear previous QR/instructions

        fetch('whatsapp_manager.php?action=get_status', { cache: "no-store" })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    statusSpan.textContent = data.status;
                    switch (data.status) {
                        case 'LOGGED_OUT':
                            if (loginBtn) loginBtn.disabled = false;
                            if (logoutBtn) logoutBtn.disabled = true;
                            if (listGroupsBtn) listGroupsBtn.disabled = true;
                            if (getProfileBtn) getProfileBtn.disabled = true;
                            statusSpan.style.color = 'orange';
                            qrArea.innerHTML = '<p>Session is logged out. Attempting to generate QR code for login...</p>';
                            if (groupsArea) { groupsArea.style.display = 'none'; groupsArea.innerHTML = ''; }
                            if (profileArea) { profileArea.style.display = 'none'; profileArea.innerHTML = ''; }
                            // Automatically try to initiate login to get a QR code
                            if (loginBtn) loginBtn.click(); // Simulate click on the login button
                            break;
                        case 'NEEDS_SCAN': // This case will now primarily be hit if initiate_login was called and returned this status
                            if (loginBtn) loginBtn.disabled = false; // Allow manual re-trigger
                            if (logoutBtn) logoutBtn.disabled = true;
                            if (listGroupsBtn) listGroupsBtn.disabled = true;
                            if (getProfileBtn) getProfileBtn.disabled = true;
                            statusSpan.style.color = 'orange';
                            // If qrData is present from a direct get_status (less common for NEEDS_SCAN), display it
                            // More likely, the initiate_login handler will display the QR.
                            if (data.qrData) {
                                qrArea.innerHTML = `<img src="${data.qrData}" alt="WhatsApp QR Code"><br>` + QR_DISPLAYED_INSTRUCTIONS_HTML;
                            } else {
                                // If get_status returns NEEDS_SCAN without QR, it implies terminal login is needed or loginBtn should be clicked.
                                // The automatic click from LOGGED_OUT should handle this.
                                // If user manually refreshes and gets this, loginBtn is available.
                                qrArea.innerHTML = TERMINAL_LOGIN_INSTRUCTIONS_HTML;
                            }
                            if (groupsArea) { groupsArea.style.display = 'none'; groupsArea.innerHTML = ''; }
                            if (profileArea) { profileArea.style.display = 'none'; profileArea.innerHTML = ''; }
                            break;
                        case 'LOGGED_IN':
                            if (loginBtn) loginBtn.disabled = true;
                            if (logoutBtn) logoutBtn.disabled = false;
                            if (listGroupsBtn) listGroupsBtn.disabled = false;
                            if (getProfileBtn) getProfileBtn.disabled = false;
                            statusSpan.style.color = 'green';
                            qrArea.innerHTML = '<p>WhatsApp session is active.</p>';
                            fetchAndDisplayProfileInfo(); // Automatically fetch and display profile info
                            break;
                        default: // INITIALIZING, UNKNOWN_ERROR, TIMEOUT etc.
                            if (loginBtn) loginBtn.disabled = false; // Allow trying to log in if status is unknown or initializing
                            if (logoutBtn) logoutBtn.disabled = true;
                            if (listGroupsBtn) listGroupsBtn.disabled = true;
                            if (getProfileBtn) getProfileBtn.disabled = true;
                            statusSpan.style.color = 'red';
                            qrArea.innerHTML = `<p>Session status: ${escapeHtml(data.status)}. ${escapeHtml(data.message || '')}. Please refresh or check server logs.</p>`;
                            if (groupsArea) { groupsArea.style.display = 'none'; groupsArea.innerHTML = ''; }
                            if (profileArea) { profileArea.style.display = 'none'; profileArea.innerHTML = ''; }
                            break;
                    }
                } else {
                    statusSpan.textContent = data.message || 'Error fetching status.';
                    statusSpan.style.color = 'red';
                    [loginBtn, logoutBtn, listGroupsBtn, getProfileBtn].forEach(btn => { if(btn) btn.disabled = true; });
                    qrArea.innerHTML = `<p>Failed to retrieve status: ${escapeHtml(data.message || 'Unknown error')}</p>`;
                }
            })
            .catch(error => {
                console.error('Error fetching WhatsApp status:', error);
                statusSpan.textContent = 'Error connecting to status checker.';
                statusSpan.style.color = 'red';
                [loginBtn, logoutBtn, listGroupsBtn, getProfileBtn].forEach(btn => { if(btn) btn.disabled = true; });
                qrArea.innerHTML = '<p>Connection error. Please check your network and try refreshing.</p>';
            })
            .finally(() => {
                if (checkStatusBtn) checkStatusBtn.disabled = false;
            });
    }

    if (checkStatusBtn) {
        checkStatusBtn.addEventListener('click', fetchWhatsappStatus);
    }

    if (loginBtn) {
        loginBtn.addEventListener('click', function() {
            statusSpan.textContent = 'Attempting to initiate login...';
            qrArea.innerHTML = '<p>Please wait...</p>';
            loginBtn.disabled = true;
            if (checkStatusBtn) checkStatusBtn.disabled = true;

            fetch('whatsapp_manager.php?action=initiate_login', { method: 'POST', cache: 'no-store' })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (data.status === 'NEEDS_SCAN' && data.qrData) {
                            statusSpan.textContent = 'QR Code Generated (Informational)';
                            qrArea.innerHTML = `<img src="${data.qrData}" alt="WhatsApp QR Code"><br>` + QR_DISPLAYED_INSTRUCTIONS_HTML;
                        } else if (data.status === 'ALREADY_LOGGED_IN') {
                            statusSpan.textContent = 'Already Logged In';
                            qrArea.innerHTML = '<p>Session is already active. Refreshing status details...</p>';
                            fetchWhatsappStatus(); // Refresh to get full LOGGED_IN state
                        } else { // Covers AUTH_FAILURE, INIT_FAILURE, QR_ERROR, TIMEOUT from controller
                            statusSpan.textContent = data.status || 'Login Initiation Failed';
                            qrArea.innerHTML = `<p>Error: ${escapeHtml(data.message || 'Could not initiate login.')}</p>${TERMINAL_LOGIN_INSTRUCTIONS_HTML}`;
                        }
                    } else {
                        statusSpan.textContent = 'Login Initiation Error';
                        qrArea.innerHTML = `<p>Error: ${escapeHtml(data.message || 'Failed to communicate with the server.')}</p>${TERMINAL_LOGIN_INSTRUCTIONS_HTML}`;
                    }
                })
                .catch(error => {
                    console.error('Error initiating WhatsApp login:', error);
                    statusSpan.textContent = 'Login Initiation Network Error';
                    qrArea.innerHTML = `<p>Network error. Could not initiate login.</p>${TERMINAL_LOGIN_INSTRUCTIONS_HTML}`;
                })
                .finally(() => {
                    loginBtn.disabled = false;
                    if (checkStatusBtn) checkStatusBtn.disabled = false;
                });
        });
    }

    if (logoutBtn) {
        logoutBtn.addEventListener('click', function() {
            if (!confirm('Are you sure you want to logout the WhatsApp session?')) {
                return;
            }
            statusSpan.textContent = 'Logging out...';
            qrArea.innerHTML = '<p>Attempting to log out...</p>';
            [loginBtn, logoutBtn, listGroupsBtn, getProfileBtn, checkStatusBtn].forEach(btn => { if(btn) btn.disabled = true; });

            fetch('whatsapp_manager.php?action=logout', { method: 'POST' })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                         statusSpan.textContent = 'Logout Initiated';
                         qrArea.innerHTML = '<p>Logout request sent. Refreshing status...</p>';
                    } else {
                         statusSpan.textContent = 'Logout Possibly Failed';
                         qrArea.innerHTML = `<p>Logout command issue: ${escapeHtml(data.message || 'Unknown error')}. Refreshing status...</p>`;
                    }
                    setTimeout(fetchWhatsappStatus, 1500); // Give controller a moment then refresh
                })
                .catch(error => {
                    console.error('Error logging out WhatsApp:', error);
                    statusSpan.textContent = 'Logout Error';
                    qrArea.innerHTML = '<p>Error sending logout request. Refreshing status...</p>';
                    setTimeout(fetchWhatsappStatus, 1500);
                });
        });
    }

    if (listGroupsBtn && groupsArea) {
        listGroupsBtn.addEventListener('click', function() {
            groupsArea.innerHTML = '<p>Fetching groups...</p>';
            groupsArea.style.display = 'block';
            listGroupsBtn.disabled = true;
            fetch('whatsapp_manager.php?action=get_groups', { cache: "no-store" })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.groups) {
                        if (data.groups.length > 0) {
                            let groupsHtml = '<h5>WhatsApp Groups:</h5><ul>';
                            data.groups.forEach(group => {
                                groupsHtml += `<li><strong>${escapeHtml(group.name)}</strong> (ID: ${escapeHtml(group.id)}, Participants: ${group.participantsCount || 'N/A'})</li>`;
                            });
                            groupsHtml += '</ul>';
                            groupsArea.innerHTML = groupsHtml;
                        } else {
                            groupsArea.innerHTML = '<p>No groups found or you are not a participant in any group.</p>';
                        }
                    } else {
                        groupsArea.innerHTML = `<p>Error fetching groups: ${escapeHtml(data.message || 'Unknown error')}</p>`;
                    }
                })
                .catch(error => {
                    console.error('Error fetching WhatsApp groups:', error);
                    groupsArea.innerHTML = '<p>Failed to connect to the server to fetch groups.</p>';
                })
                .finally(() => {
                    if (statusSpan.textContent === 'LOGGED_IN') listGroupsBtn.disabled = false;
                });
        });
    }

    if (getProfileBtn && profileArea) {
        getProfileBtn.addEventListener('click', fetchAndDisplayProfileInfo);
    }

    // Initial status check for WhatsApp
    fetchWhatsappStatus();
});
</script>