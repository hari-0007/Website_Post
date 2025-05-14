<?php

// admin/views/generate_message_view.php - Displays the generated message output

// This file is included by dashboard.php when $requestedAction is 'generate_message'.
// It assumes $whatsappMessage is available.

?>
<?php if ($whatsappMessage !== null): ?>
    <div class="message-output">
        <h3>Generated WhatsApp Channel Message</h3>
        <textarea id="whatsappMessageBox" readonly onclick="copyToClipboard(this)"><?= htmlspecialchars($whatsappMessage) ?></textarea>
        <div class="note">
            <p><strong>Instructions:</strong></p>
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
    <script>
        function copyToClipboard(element) {
            element.select();
            document.execCommand('copy');
            alert('Message copied to clipboard!');
        }
    </script>
<?php else: ?>
    <p>Could not generate message. Check logs for errors.</p>
<?php endif; ?>
