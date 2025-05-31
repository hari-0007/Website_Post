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
<?php
// Helper functions for star rating - PHP side
function get_emotion_score_php($emotion_label) {
    $scores = [
        'very_negative' => -2, 'problem_report' => -1.8, 'angry' => -2,
        'sad' => -1.5, 'disappointed' => -1.5, 'fear_anxiety' => -1.2,
        'urgent' => -0.5, // Urgent might not be strictly negative in terms of sentiment for rating
        'disagreement' => -1,
        'confused' => -0.5,
        'neutral' => 0, 'question_help' => 0, 'apology' => 0, 'greeting' => 0, 'farewell' => 0, 'surprise_shock' => 0,
        'suggestion_idea' => 0.5,
        'positive' => 1, 'agreement' => 1,
        'grateful' => 1.5, 'laughing' => 1.5,
        'very_positive' => 2, 'celebration' => 2,
    ];
    return $scores[strtolower(trim($emotion_label ?? 'neutral'))] ?? 0;
}

function calculate_star_rating_php($average_score) {
    if ($average_score <= -1.5) return 1;
    if ($average_score <= -0.75) return 2;
    if ($average_score < 0.75) return 3;
    if ($average_score < 1.5) return 4;
    return 5;
}

function render_stars_php($rating) {
    $starsHtml = '<span class="star-rating" title="Avg. Rating: ' . round($rating,0) . '/5">';
    for ($i = 1; $i <= 5; $i++) {
        $starsHtml .= ($i <= $rating) ? '⭐' : '☆';
    }
    $starsHtml .= '</span>';
    return $starsHtml;
}

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

                // Calculate average score and star rating for the conversation
                $totalScore = 0;
                $messageCountForRating = 0;
                $conversationStarRatingHtml = render_stars_php(3); // Default to 3 stars

                if (!empty($groupData['messages'])) {
                    foreach ($groupData['messages'] as $conv_msg_for_rating) {
                        $emotionLabelForScore = $conv_msg_for_rating['ai_analysis']['emotion_label'] ?? 'neutral';
                        $totalScore += get_emotion_score_php($emotionLabelForScore);
                        $messageCountForRating++;
                    }
                    if ($messageCountForRating > 0) {
                        $averageScore = $totalScore / $messageCountForRating;
                        $starRatingValue = calculate_star_rating_php($averageScore);
                        $conversationStarRatingHtml = render_stars_php($starRatingValue);
                    } else {
                         $conversationStarRatingHtml = render_stars_php(3); // Default if no messages somehow
                    }
                }
            ?>
             <div class="email-group card <?php echo $conversationIsFlagged ? 'conversation-is-flagged' : ''; echo $allRead ? ' read' : ' unread'; ?>" data-email="<?php echo htmlspecialchars($email); ?>" id="conv-card-<?php echo $cardIdSuffix; ?>">
                 <div class="card-header" onclick="toggleConversation('<?php echo $cardIdSuffix; // Use the generated ID for toggling ?>', this)" style="cursor: pointer;">
                    <div class="header-content">
                        <h5 class="email-title">Conversation with: <?php echo htmlspecialchars($email); ?> <span class="conversation-rating"><?php echo $conversationStarRatingHtml; ?></span></h5>
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
                                        <span class="message-emotion-emoji" title="<?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $currentMessageEmotionLabel))); ?>"><?php echo htmlspecialchars($currentMessageEmoji); ?></span>
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
                            
                            <?php if (isset($message['commands']) && !empty($message['commands'])): ?>
                                <div class="message-item-commands-display">
                                    <strong>Commands:</strong>
                                    <ul>
                                        <?php foreach ($message['commands'] as $command): ?>
                                            <li><?php echo htmlspecialchars($command); ?></li>
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
    .star-rating {
        font-size: 1em; /* Adjust size as needed */
        margin-left: 8px;
        color: #ffc107; /* Gold color for stars */
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
    var feedbackMessagesData = <?php echo json_encode($allMessagesFlat ?? []); ?>;
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
            const lastElement = messageItemElement.children[messageItemElement.children.length -1]; // find a suitable place to insert
            if (lastElement) { // Simplified: always try to insert after the last known element if no specific placeholder
                 lastElement.insertAdjacentElement('afterend', commandsDisplayDiv);
            } else { // Fallback if no children, though unlikely for a message item
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

<script>
let lastKnownMessageTimestamp = <?php
    $initialLatestTimestamp = 0;
    if (!empty($allMessagesFlat)) { // $allMessagesFlat should be available from your PHP
        foreach($allMessagesFlat as $msg) {
            if (isset($msg['timestamp']) && $msg['timestamp'] > $initialLatestTimestamp) {
                $initialLatestTimestamp = $msg['timestamp'];
            }
        }
    }
    // Fallback to current time if no messages, or 0 if you prefer to fetch all on first poll
    echo $initialLatestTimestamp > 0 ? $initialLatestTimestamp : 0;
?>;

const pollingInterval = 10000; // Poll every 10 seconds (10000 milliseconds)
let isPollingActive = true; // Control polling

// Helper function to escape HTML characters
function escapeHTML(str) {
    if (typeof str !== 'string') return str;
    return str.replace(/[&<>"']/g, function (match) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[match];
    });
}

// Helper function to mimic PHP's ucfirst
function ucfirst(str) {
    if (typeof str !== 'string' || str.length === 0) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
}
// Helper function to mimic PHP's nl2br
function nl2br(str) {
    if (typeof str !== 'string') return '';
    return str.replace(/(?:\r\n|\r|\n)/g, '<br>');
}

// Placeholder for MD5 function (if you need to create new conversation cards dynamically)
// You can use a library like blueimp-md5: <script src="https://cdn.jsdelivr.net/npm/blueimp-md5@2.19.0/js/md5.min.js">

// Or implement a simple one if your card IDs don't strictly need cryptographic MD5.
// For now, we'll focus on updating existing cards.

function generateCardIdSuffix(email) {
    // This is a very simple hash, not a true MD5. Replace with a proper one if needed.
    let hash = 0;
    for (let i = 0; i < email.length; i++) {
        const char = email.charCodeAt(i);
        hash = ((hash << 5) - hash) + char;
        hash |= 0; // Convert to 32bit integer
    }
    return 'js' + Math.abs(hash).toString(16); // Prefix with 'js' to avoid conflicts if PHP md5 is different
}

// Helper functions for star rating - JavaScript side
function getEmotionScoreJS(emotionLabel) {
    const scores = {
        'very_negative': -2, 'problem_report': -1.8, 'angry': -2,
        'sad': -1.5, 'disappointed': -1.5, 'fear_anxiety': -1.2,
        'urgent': -0.5,
        'disagreement': -1,
        'confused': -0.5,
        'neutral': 0, 'question_help': 0, 'apology': 0, 'greeting': 0, 'farewell': 0, 'surprise_shock': 0,
        'suggestion_idea': 0.5,
        'positive': 1, 'agreement': 1,
        'grateful': 1.5, 'laughing': 1.5,
        'very_positive': 2, 'celebration': 2
    };
    return scores[(emotionLabel || 'neutral').toLowerCase().trim()] || 0;
}

function calculateStarRatingJS(averageScore) {
    if (averageScore <= -1.5) return 1;
    if (averageScore <= -0.75) return 2;
    if (averageScore < 0.75) return 3;
    if (averageScore < 1.5) return 4;
    return 5;
}

function renderStarsJS(rating) {
    let starsHtml = `<span class="star-rating" title="Avg. Rating: ${Math.round(rating)}/5">`;
    for (let i = 1; i <= 5; i++) {
        starsHtml += (i <= rating) ? '⭐' : '☆';
    }
    starsHtml += '</span>';
    return starsHtml;
}
// End Helper functions for star rating - JS side


function renderNewConversationCardHTML(email, groupData) {
    const cardIdSuffix = generateCardIdSuffix(email); // Use the same JS-based ID generation
    const latestMessageInGroup = groupData.messages && groupData.messages.length > 0 ? groupData.messages[0] : null;
    let latestEmoji = '😐';
    if (latestMessageInGroup) {
        latestEmoji = latestMessageInGroup.ai_analysis && latestMessageInGroup.ai_analysis.emoji 
                      ? latestMessageInGroup.ai_analysis.emoji 
                      : (latestMessageInGroup.detected_emotion_emoji || '😐');
    }

    let conversationStarRatingHtml = renderStarsJS(3); // Default
    if (groupData.messages && groupData.messages.length > 0) {
        let totalScore = 0;
        groupData.messages.forEach(msg => {
            const emotionLabelForScore = msg.ai_analysis && msg.ai_analysis.emotion_label ? msg.ai_analysis.emotion_label : 'neutral';
            totalScore += getEmotionScoreJS(emotionLabelForScore);
        });
        const averageScore = totalScore / groupData.messages.length;
        const starRatingValue = calculateStarRatingJS(averageScore);
        conversationStarRatingHtml = renderStarsJS(starRatingValue);
    }


    let messagesHtml = '';
    if (groupData.messages && groupData.messages.length > 0) {
        // Ensure messages are sorted newest first for rendering order
        groupData.messages.sort((a, b) => (b.timestamp || 0) - (a.timestamp || 0));
        groupData.messages.forEach(message => {
            messagesHtml += renderNewMessageItemHTML(message);
        });
    }

    let associatedNamesHtml = '';
    if (groupData.names && groupData.names.length > 0) {
        associatedNamesHtml = `<small class="text-muted">(Associated Names: ${escapeHTML(groupData.names.join(', '))})</small>`;
    }

    // New conversations are typically unread and not flagged by default
    return `
        <div class="email-group card unread" data-email="${escapeHTML(email)}" id="conv-card-${cardIdSuffix}">
            <div class="card-header" onclick="toggleConversation('${cardIdSuffix}', this)" style="cursor: pointer;">
                <div class="header-content">
                    <h5 class="email-title">Conversation with: ${escapeHTML(email)} <span class="conversation-rating">${conversationStarRatingHtml}</span></h5>
                    ${associatedNamesHtml}
                </div>
                <div class="header-actions">
                    <span class="message-count">Total Messages: ${groupData.messages ? groupData.messages.length : 0}</span>
                    <span class="last-message">Last Message: ${groupData.latest_timestamp ? new Date(groupData.latest_timestamp * 1000).toLocaleString() : 'N/A'}</span>
                </div>
            </div>
            <div class="card-body conversation" id="conversation-${cardIdSuffix}" style="display: none;">
                <ul class="messages-list">${messagesHtml}</ul>
            </div>
        </div>`;
}

function renderNewMessageItemHTML(message) {
    const readStatusClass = (message.read ? 'message-read' : 'message-unread');
    const flaggedClass = (message.flagged ? 'message-is-flagged' : '');
    const emotionEmoji = message.ai_analysis && message.ai_analysis.emoji ? message.ai_analysis.emoji : (message.detected_emotion_emoji || '😐');
        const emotionLabel = message.ai_analysis && message.ai_analysis.emotion_label ? message.ai_analysis.emotion_label.replace(/_/g, ' ') : 'neutral'; // Keep for emoji title
    const appBaseUrl = '<?php echo APP_BASE_URL; ?>'; // Get from PHP

    let stickerHtml = '';

    let commandsHtml = '';
    if (message.commands && message.commands.length > 0) {
        commandsHtml = '<div class="message-item-commands-display"><strong>Commands:</strong><ul>';
        message.commands.forEach(cmd => {
            commandsHtml += `<li>${escapeHTML(cmd)}</li>`;
        });
        commandsHtml += '</ul></div>';
    }

    return `
        <li class="feedback-message-item ${readStatusClass} ${flaggedClass}" data-message-id="${escapeHTML(message.id || '')}" onclick="openMessageModal('${escapeHTML(message.id || '')}', this)" style="cursor:pointer;">
            <div class="message-header">
                <div class="message-info">
                    <span class="message-from"><strong>From:</strong> ${escapeHTML(message.name || 'N/A')}
                        <span class="message-emotion-emoji" title="${escapeHTML(ucfirst(emotionLabel))}">${escapeHTML(emotionEmoji)}</span>
                    </span>
                    <span class="message-date"><strong>Date:</strong> ${message.timestamp ? new Date(message.timestamp * 1000).toLocaleString() : 'N/A'}</span>
                </div>
                <div class="message-actions">
                    <span class="status-badge ${message.read ? 'status-read' : 'status-unread'}">
                        ${message.read ? 'Read' : 'Unread'}
                    </span>
                    <button class="flag-button ${message.flagged ? 'is-flagged-btn' : ''}" onclick="event.stopPropagation(); toggleFlagJs('${escapeHTML(message.id || '')}', this, '${escapeHTML(message.email || '')}')">
                        ${message.flagged ? 'Unflag' : 'Flag'}
                    </button>
                </div>
            </div>
            <div class="message-content">
                ${nl2br(escapeHTML(message.message ? message.message.substring(0, 150) : ''))}${message.message && message.message.length > 150 ? '...' : ''}
            </div>
            ${commandsHtml}
        </li>`;
}

function fetchNewMessages() {
    if (!isPollingActive) return;

    fetch(`get_new_feedback.php?last_timestamp=${lastKnownMessageTimestamp}`, { cache: "no-store" })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success && data.new_messages_grouped && Object.keys(data.new_messages_grouped).length > 0) {
                const messagesContainer = document.querySelector('.messages-container');
                if (!messagesContainer) {
                    console.error("'.messages-container' not found in DOM.");
                    return;
                }

                // Sort email groups by latest_timestamp (newest conversation first)
                // The PHP already sorts groups, but if JS needs to re-sort or handle order:
                const emailGroups = Object.entries(data.new_messages_grouped)
                    .sort(([,a], [,b]) => (b.latest_timestamp || 0) - (a.latest_timestamp || 0));

                emailGroups.forEach(([email, groupData]) => {
                    // const cardIdSuffix = generateCardIdSuffix(email); // Old method, inconsistent with PHP's md5
                    // let conversationCard = document.getElementById(`conv-card-${cardIdSuffix}`); // Old lookup
                    let conversationCard = document.querySelector(`.email-group.card[data-email="${escapeHTML(email)}"]`); // New, more robust lookup
                    let messagesListUl;

                    if (!conversationCard) {
                        // --- Dynamically create and prepend the new conversation card ---
                        console.log("New conversation detected for:", email, "- Dynamically creating card.");
                        const newCardHtml = renderNewConversationCardHTML(email, groupData);
                        messagesContainer.insertAdjacentHTML('afterbegin', newCardHtml); // Prepend new card
                        
                        // Add new messages to the global JS data store anyway
                        groupData.messages.forEach(newMessage => {
                            if (!feedbackMessagesData.find(m => m.id === newMessage.id)) {
                                feedbackMessagesData.unshift(newMessage); // Add to start (newest)
                            }
                        });                        
                        // The new card is added, subsequent logic for updating existing cards will be skipped for this iteration.
                        // The overall unread count will be updated at the end of fetchNewMessages.
                        // No 'return' here, let it proceed to update lastKnownMessageTimestamp etc.
                    }

                    // --- Adding messages to an existing conversation card ---
                    messagesListUl = conversationCard.querySelector('.messages-list');
                    if (messagesListUl) {
                        // Sort messages within this group (newest first) before prepending
                        groupData.messages.sort((a, b) => (b.timestamp || 0) - (a.timestamp || 0));
                        
                        let newMessagesAddedToThisCard = 0;
                        groupData.messages.forEach(newMessage => {
                            // Add to global JS data store if not already present
                            if (!feedbackMessagesData.find(m => m.id === newMessage.id)) {
                                feedbackMessagesData.unshift(newMessage); // Add to start (newest)
                            }

                            // Check if message already in DOM to prevent duplicates
                            if (!messagesListUl.querySelector(`.feedback-message-item[data-message-id="${newMessage.id}"]`)) {
                                const messageHtml = renderNewMessageItemHTML(newMessage);
                                messagesListUl.insertAdjacentHTML('afterbegin', messageHtml); // Prepend new message
                                newMessagesAddedToThisCard++;
                            }
                        });

                        if (newMessagesAddedToThisCard > 0) {
                            // Update card header
                            const headerContent = conversationCard.querySelector('.card-header .header-content');
                            const headerActions = conversationCard.querySelector('.card-header .header-actions');
                            if (headerContent && headerActions) {
                                const currentTotal = parseInt(headerActions.querySelector('.message-count').textContent.match(/\d+/)[0]) || 0;
                                headerActions.querySelector('.message-count').textContent = `Total Messages: ${currentTotal + newMessagesAddedToThisCard}`;
                                
                                // Update last message time (use the latest from the fetched groupData)
                                if (groupData.latest_timestamp) {
                                     headerActions.querySelector('.last-message').textContent = `Last Message: ${new Date(groupData.latest_timestamp * 1000).toLocaleString()}`;
                                }

                                // Update conversation star rating
                                let totalScore = 0;
                                let msgCountForRating = 0;
                                feedbackMessagesData.forEach(msg => {
                                    if (msg.email && msg.email.toLowerCase().trim() === email.toLowerCase().trim()) {
                                        const emotionLabelForScore = msg.ai_analysis && msg.ai_analysis.emotion_label ? msg.ai_analysis.emotion_label : 'neutral';
                                        totalScore += getEmotionScoreJS(emotionLabelForScore);
                                        msgCountForRating++;
                                    }
                                });
                                const averageScore = msgCountForRating > 0 ? totalScore / msgCountForRating : 0;
                                const starRatingValue = calculateStarRatingJS(averageScore);
                                const ratingSpan = headerContent.querySelector('.conversation-rating'); // Changed from .conversation-emoji
                                if(ratingSpan) {
                                    ratingSpan.innerHTML = renderStarsJS(starRatingValue);
                                }
                            }

                            // Update overall read/unread status of the card
                            // A new message makes the card unread unless it was explicitly marked read by another action
                            let allMessagesInConvRead = true;
                            const allMessageItemsInCard = messagesListUl.querySelectorAll('.feedback-message-item');
                            allMessageItemsInCard.forEach(itemLi => {
                                if (itemLi.classList.contains('message-unread')) {
                                    allMessagesInConvRead = false;
                                }
                            });
                            // More robust: check feedbackMessagesData for this email
                            allMessagesInConvRead = true; // Re-evaluate based on the master data
                            feedbackMessagesData.forEach(msg => {
                                if (msg.email && msg.email.toLowerCase().trim() === email.toLowerCase().trim() && !msg.read) {
                                    allMessagesInConvRead = false;
                                }
                            });

                            conversationCard.classList.toggle('read', allMessagesInConvRead);
                            conversationCard.classList.toggle('unread', !allMessagesInConvRead);

                            // If the conversation card was for this email, move it to the top of messagesContainer
                            if (messagesContainer.firstChild !== conversationCard) {
                                messagesContainer.prepend(conversationCard);
                            }
                        }
                    }
                });
                // Update the timestamp for the next poll
                if (data.latest_overall_timestamp > lastKnownMessageTimestamp) {
                    lastKnownMessageTimestamp = data.latest_overall_timestamp;
                }
                // Optional: Update overall unread count in the main navigation
                updateOverallUnreadCount();
            }
        })
        .catch(error => {
            console.error('Error fetching new messages:', error);
            // Optionally, display a subtle error to the user or stop polling on repeated errors
        })
        .finally(() => {
            if (isPollingActive) {
                setTimeout(fetchNewMessages, pollingInterval); // Schedule next poll
            }
        });
}

function updateOverallUnreadCount() {
    // This function would find the unread count badge in your main navigation
    // and update it based on the `feedbackMessagesData` array.
    let unreadCount = 0;
    feedbackMessagesData.forEach(msg => {
        if (!msg.read) {
            unreadCount++;
        }
    });
    const unreadBadgeElement = document.querySelector('.admin-nav a[href="?view=messages"] .unread-badge');
    if (unreadBadgeElement) {
        unreadBadgeElement.textContent = unreadCount > 0 ? unreadCount : '';
        unreadBadgeElement.style.display = unreadCount > 0 ? 'inline-block' : 'none';
    }
}


// Start polling when the page is ready
document.addEventListener('DOMContentLoaded', function() {
    if (typeof fetchNewMessages === "function") {
        setTimeout(fetchNewMessages, pollingInterval); // Start the polling
        console.log("Live message polling started.");
    }

    // Initial update of overall unread count based on page load
    updateOverallUnreadCount();
});

// Call this function if you want to stop polling, e.g., on page unload or if user logs out via AJAX
function stopMessagePolling() {
    isPollingActive = false;
    console.log("Live message polling stopped.");
}

// Example: window.addEventListener('beforeunload', stopMessagePolling);

</script>
<?php
ob_end_flush(); // Send the buffered output
?>
