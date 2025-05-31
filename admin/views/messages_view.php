<?php
// filepath: c:\Users\Public\Job_Post\admin\views\messages_view.php

// admin/views/messages_view.php - Displays Feedback Messages in a Conversational View

// Start output buffering at the very beginning of the file
ob_start();

// Session check should be handled by dashboard.php before including this view.
// However, keeping it here as a safeguard if this file were ever accessed directly.
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: dashboard.php?view=login&error=session_expired');
    exit;
}

// feedback_helpers.php (containing loadFeedbackMessages and group_feedback_by_email)
// should be included by dashboard.php or fetch_content.php.
$feedbackFilename = __DIR__ . '/../../data/feedback.json'; // Path to feedback data
$allMessagesFlat = loadFeedbackMessages($feedbackFilename); // Load the flat array of all messages

// Handle marking messages as read/unread
if (isset($_GET['action'], $_GET['message_id'])) {
    $messageIdToToggle = $_GET['message_id'];
    $action = $_GET['action'];

    if ($action === 'mark_read' || $action === 'mark_unread') {
        $markAsRead = ($action === 'mark_read');
        $messageFoundAndToggled = false;
        foreach ($allMessagesFlat as &$msg) { // Iterate by reference
            if (isset($msg['id']) && $msg['id'] === $messageIdToToggle) {
                $msg['read'] = $markAsRead;
                $messageFoundAndToggled = true;
                break;
            }
        }
        unset($msg); // Unset reference

        if ($messageFoundAndToggled && saveFeedbackMessages($allMessagesFlat, $feedbackFilename)) {
            $_SESSION['admin_status'] = ['message' => 'Message status updated.', 'type' => 'success'];
        } else { $_SESSION['admin_status'] = ['message' => 'Failed to update message status.', 'type' => 'error']; }
        header('Location: dashboard.php?view=messages');
        exit;
    }
}

// $allMessagesFlat is already loaded and sorted (if needed, sorting is done by group_feedback_by_email)

// Group the flat messages by email for display
$grouped_feedback = group_feedback_by_email($allMessagesFlat);

// The $allMessagesFlat variable is kept as it's used by the JavaScript modal
// to look up message details by ID.
?>

<div class="view-container">
    <h3>Feedback Conversations</h3>

    <!-- Search Messages Option -->
    <div class="search-container">
        <input type="text" id="searchMessages" placeholder="Search messages by email or name..." onkeyup="searchConversations()">
    </div>

    <?php if (empty($grouped_feedback)): ?>
        <p>No feedback messages found.</p>
    <?php else: ?>
        <div class="messages-container">
        <?php foreach ($grouped_feedback as $email => $groupData): ?>
            <?php // Calculate $conversationIsFlagged and $allRead for the card
                $conversationIsFlagged = false;
                if (isset($groupData['messages']) && is_array($groupData['messages'])) {
                    foreach ($groupData['messages'] as $convMsg) {
                        if (isset($convMsg['flagged']) && $convMsg['flagged']) {
                            $conversationIsFlagged = true;
                            break;
                        }
                    }
                }

                $allRead = true;
                if (isset($groupData['messages']) && is_array($groupData['messages'])) {
                    foreach ($groupData['messages'] as $message) {
                        if (!(isset($message['read']) && $message['read'])) {
                            $allRead = false; // Found an unread message
                            break; // No need to check further
                        }
                    }
                }
                // Create a more robust ID for the card, especially if emails can have special characters
                $cardIdSuffix = htmlspecialchars(md5($email)); // Using md5 of email for a consistent ID

                // Get emotion emoji from the latest message in the group
                $latestMessageEmotionEmoji = '😐'; // Default neutral emoji
                // $latestMessageStickerId = null;
                if (!empty($groupData['messages'])) {
                    $latestMessageInGroup = $groupData['messages'][0]; // Messages are sorted newest first
                    if (isset($latestMessageInGroup['ai_analysis']['emoji']) && !empty($latestMessageInGroup['ai_analysis']['emoji'])) {
                        $latestMessageEmotionEmoji = htmlspecialchars($latestMessageInGroup['ai_analysis']['emoji']);
                    } elseif (isset($latestMessageInGroup['detected_emotion_emoji']) && !empty($latestMessageInGroup['detected_emotion_emoji'])) { // Fallback
                        $latestMessageEmotionEmoji = htmlspecialchars($latestMessageInGroup['detected_emotion_emoji']);
                    }
                }
            ?>
             <div class="email-group card <?php echo $conversationIsFlagged ? 'conversation-is-flagged' : ''; echo $allRead ? ' read' : ' unread'; ?>" data-email="<?php echo htmlspecialchars($email); ?>" id="conv-card-<?php echo $cardIdSuffix; ?>">
                 <div class="card-header" onclick="toggleConversation('<?php echo $cardIdSuffix; // Use the generated ID for toggling ?>', this)" style="cursor: pointer;">
                    <div class="header-content">
                        <h5 class="email-title">Conversation with: <?php echo htmlspecialchars($email); ?> <span class="conversation-emoji"><?= $latestMessageEmotionEmoji ?></span></h5>
                         <?php if (!empty($groupData['names'])): ?>
                            <small class="text-muted">(Associated Names: <?php echo htmlspecialchars(implode(', ', array_unique($groupData['names']))); ?>)</small>
                        <?php endif; ?>
                    </div>
                    <div class="header-actions">
                        <span class="message-count">Total Messages: <?php echo count($groupData['messages']); ?></span>
                        <span class="last-message">Last Message: <?php echo isset($groupData['latest_timestamp']) && $groupData['latest_timestamp'] > 0 ? date('Y-m-d H:i:s', $groupData['latest_timestamp']) : 'N/A'; ?></span>
                    </div>
                </div>
                <div class="card-body conversation" id="conversation-<?php echo $cardIdSuffix; // Use the generated ID here too ?>" style="display: none;">
                    <ul class="messages-list">
                    <?php foreach ($groupData['messages'] as $message): ?>
                        <?php
                            // Determine emoji and sticker for each message
                            // $currentMessageEmoji = $message['ai_analysis']['emoji'] ?? ($message['detected_emotion_emoji'] ?? '😐');
                             $currentMessageEmoji = '😐'; // Default
                            if (isset($message['ai_analysis']['emoji']) && !empty($message['ai_analysis']['emoji'])) {
                                $currentMessageEmoji = $message['ai_analysis']['emoji'];
                            } elseif (isset($message['detected_emotion_emoji']) && !empty($message['detected_emotion_emoji'])) {
                                // Fallback to older field if ai_analysis.emoji is missing
                                $currentMessageEmoji = $message['detected_emotion_emoji'];
                            }
                            $currentMessageStickerId = $message['ai_analysis']['sticker_id'] ?? null;
                            $currentMessageEmotionLabel = $message['ai_analysis']['emotion_label'] ?? 'neutral';
                        ?>
                        <li class="feedback-message-item <?php echo (isset($message['read']) && $message['read']) ? 'message-read' : 'message-unread'; echo (isset($message['flagged']) && $message['flagged']) ? ' message-is-flagged' : ''; ?>" data-message-id="<?php echo htmlspecialchars($message['id'] ?? ''); ?>" onclick="openMessageModal('<?php echo htmlspecialchars($message['id'] ?? ''); ?>', this)" style="cursor:pointer;">
                            <div class="message-header">
                                <div class="message-info">
                                    <span class="message-from"><strong>From:</strong> <?php echo htmlspecialchars($message['name'] ?? 'N/A'); ?>
                                        <span class="message-emotion-emoji" title="<?= htmlspecialchars(ucfirst(str_replace('_', ' ', $currentMessageEmotionLabel))) ?>"><?= htmlspecialchars($currentMessageEmoji) ?></span>
                                    </span>
                                    <span class="message-date"><strong>Date:</strong> <?php echo isset($message['timestamp']) ? date('Y-m-d H:i:s', $message['timestamp']) : 'N/A'; ?></span>
                                </div>
                                <div class="message-actions">
                                    <span class="status-badge <?php echo (isset($message['read']) && $message['read']) ? 'status-read' : 'status-unread'; ?>">
                                        <?php echo (isset($message['read']) && $message['read']) ? 'Read' : 'Unread'; ?>
                                    </span>
                                   <button class="flag-button <?php echo (isset($message['flagged']) && $message['flagged']) ? 'is-flagged-btn' : ''; ?>" onclick="event.stopPropagation(); toggleFlagJs('<?php echo htmlspecialchars($message['id'] ?? ''); ?>', this, '<?php echo htmlspecialchars($email); // Pass raw email for JS logic ?>')" >
                                        <?php echo (isset($message['flagged']) && $message['flagged']) ? 'Unflag' : 'Flag'; ?>
                                    </button>
                                 </div>
                             </div>
                             <div class="message-content">
                                <?php echo nl2br(htmlspecialchars(substr($message['message'] ?? '', 0, 150))) . (strlen($message['message'] ?? '') > 150 ? '...' : ''); ?>
                            </div>
                            <?php if ($currentMessageStickerId): ?>
                                <div class="message-sticker-placeholder" title="Sticker: <?= htmlspecialchars($currentMessageEmotionLabel) ?>">
                                    <img src="<?= APP_BASE_URL ?>admin/assets/images/stickers/<?= htmlspecialchars($currentMessageStickerId) ?>" alt="<?= htmlspecialchars($currentMessageEmotionLabel) ?> sticker" class="message-sticker-image">
                                </div>
                            <?php endif; ?>
                            <?php if (isset($message['commands']) && !empty($message['commands'])): ?>
                                <div class="message-item-commands-display">
                                    <strong>Commands:</strong>
                                    <ul>
                                        <?php foreach ($message['commands'] as $command): ?>
                                            <li><?= htmlspecialchars($command) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endforeach; ?>
        </div>

        <!-- Message Detail Modal HTML -->
        <div id="messageDetailModal" class="modal" style="display: none;">
            <div class="modal-content">
                <h3>Message Details</h3>
                <div id="modalStatusArea"></div>
                <div class="message-details-content">
                    <p><strong>From:</strong> <span id="detailName"></span> &lt;<span id="detailEmail"></span>&gt;</p>
                    <p><strong>Received On:</strong> <span id="detailTimestamp"></span></p>
                    <div class="message-body-container">
                        <h4>Message:</h4>
                        <p id="detailMessage" class="message-body"></p>
                    </div>
                    <div class="message-commands-container">
                        <h4>Commands:</h4>
                        <ul id="detailCommandsList">
                            <!-- Commands will be populated here by JS -->
                        </ul>
                        <div class="add-command-form">
                            <input type="text" id="newCommandInput" placeholder="Enter new command">
                            <button id="addCommandBtn" class="button">Add Command</button>
                        </div>
                    </div>
                </div>
                <div class="modal-actions">
                    <a id="replyLink" href="#" class="button" style="background-color: #007bff;">Reply via Email</a>
                    <button class="button" style="background-color: #6c757d;" onclick="closeMessageModal()">Close</button>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
    /* Styles for Conversation View */
    .view-container {
        padding: 20px;
    }

    /* Style for the search container */
    .search-container {
        margin-bottom: 15px;
    }

    .search-container input[type="text"] {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        box-sizing: border-box; /* Ensures padding doesn't affect width */
    }

    .messages-container {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .email-group.card {
        border: 1px solid #ddd;
        border-radius: 5px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        transition: box-shadow 0.3s ease;
    }

    .email-group.card.conversation-is-flagged .card-header {
        background-color: #fff3cd; /* Light yellow for flagged conversation header */
    }

    .email-group.card:hover {
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    }

    .card-header {
        background-color: #f7f7f7;
        padding: 15px;
        border-bottom: 1px solid #ddd;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .card-header.active { /* Style for when conversation is expanded */
        background-color: #e9ecef; /* Slightly darker to indicate active state */
    }

    .header-content {
        display: flex;
        flex-direction: column;
    }
    .header-content .text-muted {
        font-size: 0.85em;
        color: #6c757d;
    }

    .email-title {
        font-size: 1.1rem;
        font-weight: bold;
        margin: 0;
        color: #333;
    }
    .email-group.card.read .email-title { /* If all messages in group are read */
        font-weight: normal;
        color: #555;
    }
    .email-group.card.unread .email-title { /* If at least one message is unread */
        font-weight: bold;
        color: #005fa3; /* Highlight unread conversations */
    }
    .conversation-emoji {
        font-size: 1em; /* Adjust size as needed */
        margin-left: 8px;
    }


    .header-actions {
        display: flex;
        gap: 15px;
        font-size: 0.9rem;
        color: #777;
    }

    .card-body {
        padding: 15px;
    }

    .messages-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .feedback-message-item {
        border: 1px solid #e0e0e0;
        background-color: #fff;
        padding: 15px;
        margin-bottom: 10px;
        border-radius: 5px;
        transition: background-color 0.2s ease, border-left-color 0.3s ease;
    }

    .feedback-message-item.message-is-flagged {
        border-left-color: red !important; /* Make flagged messages have a red left border */
        background-color: #ffebee; /* Light red background for flagged message */
    }

    .feedback-message-item:hover {
        background-color: #f9f9f9;
    }

    .feedback-message-item.message-unread {
        border-left: 4px solid #007bff;
    }
    .feedback-message-item.message-unread .message-from strong {
        font-weight: bold; /* Ensure unread message sender is bold */
    }


    .feedback-message-item.message-read {
        border-left: 4px solid #6c757d;
    }
    .feedback-message-item.message-read .message-from strong {
        font-weight: normal; /* Normal weight for read messages */
        color: #555;
    }
    .feedback-message-item.message-read .message-date {
        color: #888;
    }


    .message-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }

    .message-info {
        display: flex;
        flex-direction: column;
    }

    .message-from {
        /* font-weight: bold; */ /* Handled by .message-read/.message-unread */
        color: #333;
    }

    .message-date {
        font-size: 0.85rem;
        color: #777;
    }
    .message-emotion-emoji {
        font-size: 0.9em; /* Slightly smaller than text */
        margin-left: 5px;
        display: inline-block; /* Ensures it flows with text */
        cursor: help; /* Indicate that hovering shows more info (title attribute) */
    }
    .message-sticker-placeholder {
        margin-top: 8px;
        text-align: left; /* Or center, depending on desired layout */
    }
    .message-sticker-image {
        max-width: 60px; /* Adjust sticker size as needed */
        max-height: 60px;
        border: 1px solid #eee;
        border-radius: 4px;
        padding: 2px;
        background-color: #f9f9f9;
                object-fit: contain; /* Ensures sticker aspect ratio is maintained */

    }

    .message-actions {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .message-content {
        background-color: #f8f9fa;
        padding: 10px;
        border-radius: 5px;
        white-space: pre-wrap;
        word-wrap: break-word;
        font-size: 0.95em;
        margin-top: 10px;
        max-height: 120px;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .status-badge {
        padding: .4em .6em;
        font-size: 0.8em;
        font-weight: 700;
        line-height: 1;
        text-align: center;
        white-space: nowrap;
        vertical-align: baseline;
        border-radius: .25rem;
    }

    .status-unread {
        color: #fff;
        background-color: #007bff;
    }

    .status-read {
        color: #fff;
        background-color: #6c757d;
    }

    .action-link {
        margin-left: 10px;
        font-size: 0.85em;
    }

    .flag-button {
        padding: 3px 8px;
        font-size: 0.8em;
        border: 1px solid #ccc;
        background-color: #f0f0f0;
        cursor: pointer;
        border-radius: 3px;
    }
    .flag-button.is-flagged-btn {
        background-color: #ffc107; /* Yellow for flagged */
        border-color: #ffc107;
        color: #333;
    }

    /* Modal Styles */
    .modal {
        position: fixed;
        z-index: 1050;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0, 0, 0, 0.4);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .modal-content {
        background-color: #fff;
        padding: 20px;
        border-radius: 10px; /* Slightly more rounded */
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        width: 90%;
        max-width: 700px;
        max-height: 90vh;
        overflow-y: auto;
        display: flex; /* Use flex for better internal layout */
        flex-direction: column; /* Stack content vertically */
    }

    .modal-content h3 {
        margin-top: 0;
        color: #0056b3;
        border-bottom: 1px solid #eee;
        padding-bottom: 10px;
    }
    .message-details-content {
        padding: 15px 0; /* Add some padding */
        font-size: 0.95rem;
        line-height: 1.6;
        color: #333;
        flex-grow: 1; /* Allow this section to take available space */
    }
    .message-details-content p {
        margin-bottom: 12px; /* Consistent spacing for paragraphs */
    }
    .message-details-content strong {
        color: #0056b3; /* Highlight labels */
    }
    .message-body-container {
        margin-top: 15px;
        padding: 15px;
        background-color: #f9f9f9; /* Light background for message body */
        border-radius: 6px;
    }

    .modal-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 20px;
    }
    .modal-actions .button {
        padding: 8px 15px;
        font-size: 0.85rem;
    }
    .modal-actions .button[style*="background-color: #007bff;"] { /* Reply button */
        background-color: #007bff !important; /* Ensure specificity */
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .modal-content {
            width: 95%;
        }

        .modal-actions {
            flex-direction: column;
            gap: 10px;
        }

        .modal-actions .button {
            width: 100%;
        }
        .card-header {
            flex-direction: column;
            align-items: flex-start;
        }
        .header-actions {
            margin-top: 10px;
            width: 100%;
            justify-content: space-between;
        }
    }

    /* Additional styles for modal status area if needed */
    .modal-status-message {
        padding: 10px;
        margin-bottom: 15px;
        border-radius: 4px;
        font-size: 0.9em;
        text-align: center;
    }
    .modal-status-message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb;}
    .modal-status-message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;}


    /* Style for collapsed conversations */
    .conversation {
        display: none;
    }
    .message-item-commands-display {
        margin-top: 8px;
        padding-top: 8px;
        border-top: 1px dashed #eee;
        font-size: 0.85em;
        color: #555;
    }
    .message-item-commands-display strong {
        color: #333;
    }
    .message-item-commands-display ul {
        list-style: disc; margin-left: 20px; padding-left: 0; margin-top: 3px; margin-bottom: 0;
    }

    .message-commands-container {
        margin-top: 15px;
        padding-top: 10px;
        border-top: 1px solid #eee;
    }

    .message-commands-container h4 {
        margin-bottom: 5px;
        font-size: 0.95em;
        color: #333;
        margin-top: 0; /* Reset top margin for h4 inside this container */
    }

    #detailCommandsList {
        list-style: none; /* Remove default bullets */
        padding-left: 0;
        font-size: 0.9em;
    }

    #detailCommandsList li {
        background-color: #f0f0f0; /* Light grey background for command items */
        padding: 6px 10px;
        margin-bottom: 5px; /* Space between command items */
        border-radius: 4px;
        color: #444;
    }

    .add-command-form { margin-top: 10px; display: flex; gap: 10px; }
    .add-command-form input[type="text"] { flex-grow: 1; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
    .add-command-form button {
        padding: 8px 12px;
        background-color: #28a745; /* Green for "Add" */
        color: white;
    }
    .add-command-form button:hover {
        background-color: #218838;
    }
</style>
 

<script>
    var feedbackMessagesData = <?= json_encode($allMessagesFlat ?? []); ?>;
    const modalStatusArea = document.getElementById('modalStatusArea');

    function displayModalStatus(message, type) {
        if (modalStatusArea) {
            modalStatusArea.innerHTML = '';
            const statusMessageElement = document.createElement('p');
            statusMessageElement.classList.add('modal-status-message', type);
            statusMessageElement.innerText = message;
            modalStatusArea.appendChild(statusMessageElement);
        } else {
            console.error('Could not find modal status area element.');
        }
    }

    function openMessageModal(messageId, listItemElement) {
        const message = feedbackMessagesData.find(msg => msg.id === messageId);
        const modal = document.getElementById('messageDetailModal');
        
        if (modalStatusArea) modalStatusArea.innerHTML = '';

        if (message) {
            document.getElementById('detailName').innerText = message.name || 'N/A';
            document.getElementById('detailEmail').innerText = message.email || 'N/A';
            document.getElementById('detailTimestamp').innerText = message.timestamp ? new Date(message.timestamp * 1000).toLocaleString() : 'N/A';
            document.getElementById('detailMessage').innerText = message.message || 'No message content.';
            
            const replyLink = document.getElementById('replyLink');
            if (message.email) {
                replyLink.href = `mailto:${encodeURIComponent(message.email)}?subject=${encodeURIComponent('Reply to your feedback')}&body=${encodeURIComponent('\n\n---\nOriginal Message:\n' + (message.message || ''))}`;
                replyLink.style.display = 'inline-block';
            } else {
                replyLink.style.display = 'none';
            }

            // Populate commands list
            populateCommandsList(message.commands || []);

            // Setup "Add Command" button
            const addCommandButton = document.getElementById('addCommandBtn');
            if (addCommandButton) {
                // Clone and replace to remove old event listeners, ensuring only one is active
                const newAddCommandButton = addCommandButton.cloneNode(true);
                addCommandButton.parentNode.replaceChild(newAddCommandButton, addCommandButton);
                
                newAddCommandButton.onclick = function() { 
                    handleAddCommand(message.id); // Pass the current message's ID
                };
            }

            if (modal) modal.style.display = 'flex';

            if (!message.read) {
                fetch('message_actions.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                    body: `action=mark_read&message_id=${encodeURIComponent(message.id)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('Message marked as read:', data.message);
                        if (listItemElement) {
                            listItemElement.classList.remove('message-unread');
                            listItemElement.classList.add('message-read');
                            const statusBadge = listItemElement.querySelector('.status-badge');
                            if (statusBadge) {
                                statusBadge.classList.remove('status-unread');
                                statusBadge.classList.add('status-read');
                                statusBadge.textContent = 'Read';
                            }
                        }
                        const msgInJsData = feedbackMessagesData.find(m => m.id === message.id);
                        if (msgInJsData) msgInJsData.read = true;

                        // Check if all messages in this conversation are now read
                        const conversationCard = listItemElement.closest('.email-group.card');
                        if (conversationCard) {
                            const email = conversationCard.dataset.email;
                            let allMessagesInConvRead = true;
                            feedbackMessagesData.forEach(msg => {
                                if (msg.email && msg.email.toLowerCase().trim() === email.toLowerCase().trim() && !msg.read) {
                                    allMessagesInConvRead = false;
                                }
                            });
                            if (allMessagesInConvRead) {
                                conversationCard.classList.remove('unread');
                                conversationCard.classList.add('read');
                            }
                        }

                    } else {
                        let errorDetail = data.message || 'Unknown error';
                        displayModalStatus('Failed to mark as read: ' + errorDetail + (errorDetail === 'Message not found.' ? ' The message list might be outdated. Please try refreshing the page.' : ''), 'error');
                    }
                })
                .catch(error => {
                    console.error('Error marking message as read:', error);
                    displayModalStatus('An error occurred while marking as read.', 'error');
                });
            }
        } else {
            displayModalStatus('Error: Message data not found.', 'error');
            if (modal) modal.style.display = 'flex';
        }
    }

    function closeMessageModal() {
        const modal = document.getElementById('messageDetailModal');
        if (modal) modal.style.display = 'none';
        if (modalStatusArea) modalStatusArea.innerHTML = '';
    }

    function toggleFlagJs(messageId, buttonElement, emailKey) { // emailKey is the raw email
        fetch('message_actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: `action=toggle_flag&message_id=${encodeURIComponent(messageId)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const isNowFlagged = data.flagged;
                buttonElement.textContent = isNowFlagged ? 'Unflag' : 'Flag';
                buttonElement.classList.toggle('is-flagged-btn', isNowFlagged);

                const messageItem = buttonElement.closest('.feedback-message-item');
                if (messageItem) {
                    messageItem.classList.toggle('message-is-flagged', isNowFlagged);
                }

                const msgInJsData = feedbackMessagesData.find(m => m.id === messageId);
                if (msgInJsData) msgInJsData.flagged = isNowFlagged;

                const conversationCard = buttonElement.closest('.email-group.card');
                if (conversationCard) {
                    let conversationHasFlaggedMessages = false;
                    feedbackMessagesData.forEach(msg => {
                        // Match by the emailKey passed to the function
                        if (msg.email && msg.email.toLowerCase().trim() === emailKey.toLowerCase().trim() && msg.flagged) {
                            conversationHasFlaggedMessages = true;
                        }
                    });
                    conversationCard.classList.toggle('conversation-is-flagged', conversationHasFlaggedMessages);
                }
            } else {
                alert('Failed to toggle flag: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error toggling flag:', error);
            alert('An error occurred while toggling flag.');
        });
    }

    function populateCommandsList(commandsArray) {
        const commandsListUl = document.getElementById('detailCommandsList');
        if (!commandsListUl) return;
        commandsListUl.innerHTML = ''; // Clear existing
        if (commandsArray && commandsArray.length > 0) {
            commandsArray.forEach(cmd => {
                const li = document.createElement('li');
                li.textContent = cmd;
                commandsListUl.appendChild(li);
            });
        } else {
            const li = document.createElement('li');
            li.textContent = 'No commands yet.';
            commandsListUl.appendChild(li);
        }
    }

    function updateMessageItemCommandsDisplay(messageId, commandsArray) {
        const messageItemElement = document.querySelector(`.feedback-message-item[data-message-id="${messageId}"]`);
        if (!messageItemElement) return;

        let commandsDisplayDiv = messageItemElement.querySelector('.message-item-commands-display');

        // If there are no commands and the display div exists, remove it
        if ((!commandsArray || commandsArray.length === 0) && commandsDisplayDiv) {
            commandsDisplayDiv.remove();
            return;
        }

        // If there are no commands and no div, do nothing
        if ((!commandsArray || commandsArray.length === 0) && !commandsDisplayDiv) {
            return;
        }

        // If there are commands but the div doesn't exist, create it
        if (commandsArray && commandsArray.length > 0 && !commandsDisplayDiv) {
            commandsDisplayDiv = document.createElement('div');
            commandsDisplayDiv.className = 'message-item-commands-display';
            
            const strongTag = document.createElement('strong');
            strongTag.textContent = 'Commands:';
            commandsDisplayDiv.appendChild(strongTag);
            
            const ul = document.createElement('ul');
            commandsDisplayDiv.appendChild(ul);

            // Append to message item (e.g., before the sticker placeholder or at the end)
            const stickerPlaceholder = messageItemElement.querySelector('.message-sticker-placeholder');
            const lastElement = messageItemElement.children[messageItemElement.children.length -1]; // find a suitable place to insert
            if (stickerPlaceholder) {
                messageItemElement.insertBefore(commandsDisplayDiv, stickerPlaceholder);
            } else if (lastElement) {
                 lastElement.insertAdjacentElement('afterend', commandsDisplayDiv);
            }
             else {
                messageItemElement.appendChild(commandsDisplayDiv);
            }
        }

        const ul = commandsDisplayDiv.querySelector('ul');
        if (!ul) return; 

        ul.innerHTML = ''; // Clear existing commands

        if (commandsArray && commandsArray.length > 0) {
            commandsArray.forEach(cmdText => {
                const li = document.createElement('li');
                li.textContent = cmdText; // Ensure cmdText is used
                ul.appendChild(li);
            });
            commandsDisplayDiv.style.display = ''; // Ensure it's visible
        } else {
            // This case should be handled by the removal logic at the top
            if (commandsDisplayDiv) commandsDisplayDiv.style.display = 'none';
        }
    }

    function handleAddCommand(messageId) {
        const newCommandInput = document.getElementById('newCommandInput');
        const commandText = newCommandInput.value.trim();

        if (!commandText) {
            alert('Please enter a command.');
            return;
        }

        fetch('message_actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: `action=add_command&message_id=${encodeURIComponent(messageId)}&command_text=${encodeURIComponent(commandText)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayModalStatus('Command added successfully!', 'success');
                newCommandInput.value = ''; // Clear input

                // Update the master JS data source
                const msgInJsData = feedbackMessagesData.find(m => m.id === messageId);
                if (msgInJsData) {
                    msgInJsData.commands = data.commands; 
                }
                // Update the list in the modal
                populateCommandsList(data.commands);
                // Update the command display in the main message item view
                updateMessageItemCommandsDisplay(messageId, data.commands);
            } else {
                displayModalStatus('Failed to add command: ' + (data.message || 'Unknown error'), 'error');
            }
        })
        .catch(error => {
            console.error('Error adding command:', error);
            displayModalStatus('An error occurred while adding command.', 'error');
        });
    }
    
    const messageDetailModalGlobal = document.getElementById('messageDetailModal');
    if (messageDetailModalGlobal) {
        messageDetailModalGlobal.addEventListener('click', (event) => {
            if (event.target === messageDetailModalGlobal) closeMessageModal();
        });
    }
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && messageDetailModalGlobal && messageDetailModalGlobal.style.display === 'flex') {
            closeMessageModal();
        }
    });

    function toggleConversation(cardIdSuffix, headerElement) { // cardIdSuffix is now md5(email) or similar
        const conversationBody = document.getElementById('conversation-' + cardIdSuffix);
        if (conversationBody) {
            const isHidden = conversationBody.style.display === 'none' || conversationBody.style.display === '';
            conversationBody.style.display = isHidden ? 'block' : 'none';
            if (headerElement) {
                headerElement.classList.toggle('active', isHidden);
            }
        }
    }

    function searchConversations() {
        var input, filter, messagesContainer, emailGroups, i, emailTitle, namesElement, emailTxt, namesTxt;
        input = document.getElementById("searchMessages");
        filter = input.value.toUpperCase().trim();
        messagesContainer = document.querySelector(".messages-container");
        emailGroups = messagesContainer.getElementsByClassName("email-group");

        for (i = 0; i < emailGroups.length; i++) {
            emailTitle = emailGroups[i].querySelector(".email-title");
            namesElement = emailGroups[i].querySelector(".text-muted"); // For associated names

            emailTxt = emailTitle.textContent || emailTitle.innerText;
            namesTxt = namesElement ? (namesElement.textContent || namesElement.innerText) : "";

            if (emailTxt.toUpperCase().indexOf(filter) > -1 || namesTxt.toUpperCase().indexOf(filter) > -1) {
                emailGroups[i].style.display = "";
            } else {
                emailGroups[i].style.display = "none";
            }
        }
    }
</script>
<?php
ob_end_flush();
?>
