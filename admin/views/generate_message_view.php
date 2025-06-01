<?php

// admin/views/generate_message_view.php - Displays the generated message output

// This file is included by dashboard.php or fetch_content.php when $requestedView is 'generate_message'.
// It assumes $whatsappMessage and potentially $telegramMessage are available.

$whatsappMessageAvailable = isset($whatsappMessage) && $whatsappMessage !== null && !empty(trim($whatsappMessage));
$telegramMessageAvailable = isset($telegramMessage) && $telegramMessage !== null && !empty(trim($telegramMessage));
?>
<h2 class="view-main-title">Generated Post Content</h2>

<?php if ($whatsappMessageAvailable): ?>
    <div class="message-output" style="margin-bottom: 30px;">
        <h4 class="section-title-minor">WhatsApp Channel Message</h4>
        <textarea id="whatsappMessageBox" readonly onclick="copyToClipboard(this, 'WhatsApp message')" class="generated-message-box"><?= htmlspecialchars($whatsappMessage) ?></textarea>
        <div class="note">
            <p><strong>Instructions for WhatsApp:</strong></p>
            <ol>
                <li>Click on the text box above to copy the message.</li>
                <li>Open your WhatsApp app or WhatsApp Web/Desktop.</li>
                <li>Go to your WhatsApp Channel.</li>
                <li>Paste the copied text into a new post.</li>
                <li>Send the post.</li>
            </ol>
            <p>Note: Direct automation of posting to WhatsApp Channels via API is not supported by WhatsApp.</p>
        </div>
    </div>
<?php endif; ?>

<?php if ($telegramMessageAvailable): ?>
    <div class="message-output">
        <h4 class="section-title-minor">Telegram Channel Message</h4>
        <textarea id="telegramMessageBox" readonly onclick="copyToClipboard(this, 'Telegram message')" class="generated-message-box"><?= htmlspecialchars($telegramMessage) ?></textarea>
        <div class="note">
            <p><strong>Instructions for Telegram:</strong></p>
            <ol>
                <li>Click on the text box above to copy the message.</li>
                <li>Open your Telegram app or Telegram Desktop/Web.</li>
                <li>Go to your Telegram Channel.</li>
                <li>Paste the copied text into a new message.</li>
                <li>Send the message.</li>
            </ol>
        </div>
    </div>
<?php endif; ?>

<?php if ($whatsappMessageAvailable || $telegramMessageAvailable): ?>
<style>
    .view-main-title { /* Consistent main title for views */
        margin-top: 0;
        margin-bottom: 25px;
        color: var(--primary-color);
        font-size: 1.75em;
        font-weight: 600;
        padding-bottom: 15px;
        border-bottom: 2px solid var(--primary-color-lighter);
    }
    .section-title-minor { /* For sub-headings like "WhatsApp Channel Message" */
        font-size: 1.2em; /* Slightly smaller than .section-title */
        color: var(--text-color-light);
        margin-top: 0; /* Reset if within a section that already has margin */
        margin-bottom: 0.75rem;
        font-weight: 500;
    }
    .message-output {
        background-color: var(--card-bg); /* Use theme variable */
        padding: 20px;
        border-radius: var(--border-radius); /* Use theme variable */
        box-shadow: var(--box-shadow-sm); /* Use theme variable */
        border: 1px solid var(--border-color); /* Use theme variable */
    }
    .generated-message-box { /* Style for the textareas */
        width: 100%;
        min-height: 150px; /* Good default height */
        padding: .5rem .75rem; /* Match global form input style */
        border: 1px solid var(--border-color); /* Use theme variable */
        border-radius: var(--border-radius); /* Use theme variable */
        font-family: var(--font-family-sans-serif); /* Consistent font */
        font-size: 0.95rem;
        line-height: 1.5;
        background-color: var(--body-bg); /* Slightly different background for readonly */
        cursor: pointer; /* Indicate it's clickable for copy */
        resize: vertical;
    }
    .note {
        margin-top: 15px;
        font-size: 0.9em;
        color: var(--text-muted); /* Use theme variable */
        background-color: var(--info-bg); /* Use theme variable for a subtle highlight */
        padding: 10px 15px;
        border-radius: var(--border-radius); /* Use theme variable */
        border: 1px solid var(--info-border); /* Use theme variable */
    }
    .note ol {
        padding-left: 20px;
        margin-top: 5px;
    }
    .note li {
        margin-bottom: 5px;
    }
</style>
<script>
    function copyToClipboard(element, messageType = 'Message') {
        element.select();
        document.execCommand('copy');
        alert(messageType + ' copied to clipboard!');
    }
</script>
<?php endif; ?>

<?php if (!$whatsappMessageAvailable && !$telegramMessageAvailable): ?>
    <p class="no-data-message">Could not generate message. Check logs for errors or ensure job data is available.</p>
<?php endif; ?>
