<?php

// admin/views/generate_message_view.php - Displays the generated message output

// This file is included by dashboard.php or fetch_content.php when $requestedView is 'generate_message'.
// It assumes $whatsappMessage and potentially $telegramMessage are available.

$whatsappMessageAvailable = isset($whatsappMessage) && $whatsappMessage !== null && !empty(trim($whatsappMessage));
$telegramMessageAvailable = isset($telegramMessage) && $telegramMessage !== null && !empty(trim($telegramMessage));
?>

<?php if ($whatsappMessageAvailable): ?>
    <div class="message-output" style="margin-bottom: 30px;">
        <h3>Generated WhatsApp Channel Message</h3>
        <textarea id="whatsappMessageBox" readonly onclick="copyToClipboard(this, 'WhatsApp message')"><?= htmlspecialchars($whatsappMessage) ?></textarea>
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
        <h3>Generated Telegram Channel Message</h3>
        <textarea id="telegramMessageBox" readonly onclick="copyToClipboard(this, 'Telegram message')"><?= htmlspecialchars($telegramMessage) ?></textarea>
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
<script>
    function copyToClipboard(element, messageType = 'Message') {
        element.select();
        document.execCommand('copy');
        alert(messageType + ' copied to clipboard!');
    }
</script>
<?php endif; ?>

<?php if (!$whatsappMessageAvailable && !$telegramMessageAvailable): ?>
    <p>Could not generate message. Check logs for errors or ensure job data is available.</p>
<?php endif; ?>
