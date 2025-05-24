// c:\Users\Public\Job_Post\whatsapp_automation\send_daily_channel_post.js

const { Client, LocalAuth } = require('whatsapp-web.js');
const qrcode = require('qrcode-terminal');
const { exec } = require('child_process');
const path = require('path');

// --- Configuration ---
// IMPORTANT: Replace with the actual ID of your WhatsApp Channel.
// You might need to run a separate script or manually inspect network requests
// or use client.getChats() once logged in to find your channel's ID.
// It usually ends with '@broadcast' for channels.
const TARGET_CHANNEL_ID = '120363402063897201@newsletter'; // e.g., '1234567890@broadcast'
const TARGET_CHANNEL_NAME = 'UAE Jobs 🎓'; // Keep this for fallback, but prioritize ID

// Path to your PHP executable and the message generation script
const PHP_EXECUTABLE_PATH = 'php'; // Or full path like 'C:\\php\\php.exe'
const PHP_SCRIPT_PATH = path.resolve(__dirname, '..', 'admin', 'generate_whatsapp_message.php');
// --- End Configuration ---

console.log(`[${new Date().toISOString()}] Starting WhatsApp daily post script...`);
console.log(`PHP script path: ${PHP_SCRIPT_PATH}`);

// Use LocalAuth to save session and avoid scanning QR code every time
const client = new Client({
    authStrategy: new LocalAuth({ 
        clientId: "channelposter",
        dataPath: path.resolve(__dirname, '.wwebjs_auth_data') // Explicit data path for session
    }),
    wwebVersion: '2.2412.54', // Specify a recent, known stable WhatsApp Web version
    puppeteer: {
        headless: true, // Run headless for automation
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
            '--disable-accelerated-2d-canvas', // Usually good to keep disabled
            '--no-first-run',
            '--no-zygote',
            '--disable-gpu',
            '--disable-extensions',
            '--disable-component-extensions-with-background-pages', // Further disable extensions
            '--window-size=1920,1080' // Sometimes helps with rendering issues
        ],
    }
});

client.on('qr', (qr) => {
    console.log(`[${new Date().toISOString()}] QR Code Received, scan please!`);
    qrcode.generate(qr, { small: true });
});

client.on('authenticated', () => {
    console.log(`[${new Date().toISOString()}] Authenticated successfully!`);
});

client.on('auth_failure', msg => {
    console.error(`[${new Date().toISOString()}] ERROR: Authentication failed!`, msg);
    process.exit(1); // Exit if authentication fails
});

client.on('ready', async () => {
    console.log(`[${new Date().toISOString()}] WhatsApp Client is ready!`);

    // --- Code to list all accessible chats/channels ---
    try {
        console.log(`[${new Date().toISOString()}] Fetching all chats to identify channels...`);
        const chats = await client.getChats();
        console.log(`[${new Date().toISOString()}] Found ${chats.length} total chats/contacts.`);
        
        let potentialChannels = 0;
        console.log(`\n--- Accessible Chats (Groups/Channels) ---`);
        chats.forEach(chat => {
            // Channels are typically groups. Some versions of the library might have an explicit isChannel property.
            if (chat.isGroup || chat.isChannel) {
                potentialChannels++;
                console.log(
                    `Name: "${chat.name}", ID: ${chat.id._serialized}, IsGroup: ${chat.isGroup}` +
                    (typeof chat.isChannel !== 'undefined' ? `, IsChannel: ${chat.isChannel}` : '') +
                    (chat.isReadOnly ? `, IsReadOnly: ${chat.isReadOnly}` : '') // ReadOnly is often true for channels you don't own
                );
            }
        });

        if (potentialChannels === 0) {
            console.log(`\n[INFO] No groups or channels found in the accessible chat list for this WhatsApp account.`);
        } else {
            console.log(`\n[INFO] Listed ${potentialChannels} potential groups/channels above. Look for "UAE Jobs 🎓" to get its exact ID and properties.`);
        }

    } catch (err) {
        console.error(`[${new Date().toISOString()}] Error fetching or processing chats: `, err);
    } finally {
        console.log(`[${new Date().toISOString()}] Chat listing finished. Destroying client...`);
        await client.destroy();
        process.exit(0); // Exit after listing
    }
    // --- End of chat listing code ---

    // Original message sending logic is now bypassed by the process.exit(0) above.
    // Keep it here for when you revert this debugging change.
    /*
    if (!TARGET_CHANNEL_ID || TARGET_CHANNEL_ID === 'YOUR_CHANNEL_ID@broadcast') {
        console.error(`[${new Date().toISOString()}] ERROR: TARGET_CHANNEL_ID is not configured correctly. Please set it in the script.`);
        await client.destroy();
        process.exit(1);
    }

    // Execute the PHP script to get the message content
    console.log(`[${new Date().toISOString()}] Executing PHP script to generate message...`);
    exec(`${PHP_EXECUTABLE_PATH} "${PHP_SCRIPT_PATH}"`, async (error, stdout, stderr) => {
        if (error) {
            console.error(`[${new Date().toISOString()}] Error executing PHP script: ${error.message}`);
            console.error(`[${new Date().toISOString()}] PHP stderr: ${stderr}`);
            await client.destroy();
            process.exit(1);
            return;
        }
        if (stderr) {
            console.warn(`[${new Date().toISOString()}] PHP script produced stderr (may or may not be an issue): ${stderr}`);
        }

        const messageContent = stdout.trim();
        console.log(`[${new Date().toISOString()}] Message content received from PHP script (length: ${messageContent.length}).`);

        if (!messageContent || messageContent.includes("Could not generate") || messageContent.includes("No new jobs")) {
            console.log(`[${new Date().toISOString()}] No new jobs or error in message generation. Message: "${messageContent}". Not sending.`);
            await client.destroy();
            process.exit(0);
            return;
        }

        try {
            console.log(`[${new Date().toISOString()}] Attempting to find channel by name: "${TARGET_CHANNEL_NAME}"`);
            const chats = await client.getChats();
            let targetChat = chats.find(chat => chat.name === TARGET_CHANNEL_NAME && (chat.isGroup || chat.isChannel)); // Channels are often isGroup=true, check isChannel if available

            // Fallback to ID if name search fails and ID is provided and different from the placeholder
            if (!targetChat && TARGET_CHANNEL_ID && TARGET_CHANNEL_ID !== 'YOUR_CHANNEL_ID@broadcast' && TARGET_CHANNEL_ID !== '120363402063897201@newsletter') { // Avoid using the potentially incorrect ID if name search failed
                console.log(`[${new Date().toISOString()}] Channel not found by name. Trying by configured ID: ${TARGET_CHANNEL_ID}`);
                try {
                    targetChat = await client.getChatById(TARGET_CHANNEL_ID);
                    if (targetChat && !(targetChat.isGroup || targetChat.isChannel)) {
                        console.warn(`[${new Date().toISOString()}] Chat found by ID ${TARGET_CHANNEL_ID} but it's not a group/channel. Name: ${targetChat.name}`);
                        targetChat = null; // Invalidate if not a group/channel
                    }
                } catch (idError) {
                    console.log(`[${new Date().toISOString()}] Error fetching chat by ID ${TARGET_CHANNEL_ID}: ${idError.message}`);
                    targetChat = null;
                }
            }

            if (!targetChat) {
                console.error(`[${new Date().toISOString()}] ERROR: Channel with name "${TARGET_CHANNEL_NAME}" or ID "${TARGET_CHANNEL_ID}" not found or is not accessible.`);
                console.log(`[${new Date().toISOString()}] Listing available chats (name, ID, isGroup, isChannel) to help identify the target:`);
                chats.slice(0, 15).forEach(c => console.log(`  - Name: "${c.name}", ID: ${c.id._serialized}, IsGroup: ${c.isGroup}, IsChannel: ${c.isChannel}`)); // Log more chats
                await client.destroy();
                process.exit(1);
                return;
            }
            const actualChannelIdToSend = targetChat.id._serialized; 
            console.log(`[${new Date().toISOString()}] Channel found: Name: "${targetChat.name}", ID: ${actualChannelIdToSend}. Sending message...`);

            await client.sendMessage(actualChannelIdToSend, messageContent);
            console.log(`[${new Date().toISOString()}] Message sent successfully to channel ${actualChannelIdToSend}!`);

        } catch (err) {
            console.error(`[${new Date().toISOString()}] Error during chat interaction or message sending: `, err);
        } finally {
            // Close the client connection after sending the message
            console.log(`[${new Date().toISOString()}] Destroying client...`);
            await client.destroy();
            console.log(`[${new Date().toISOString()}] Client destroyed. Script finished.`);
            process.exit(0);
        }
    });
    */
});

client.on('disconnected', (reason) => {
    console.log(`[${new Date().toISOString()}] Client was logged out:`, reason);
    // You might want to remove the session data so it prompts for QR again next time
    // fs.rmSync('./.wwebjs_auth', { recursive: true, force: true }); // Be careful with this
    process.exit(1);
});

// Add a small delay before initializing, just in case it helps with resource contention
setTimeout(() => {
    console.log(`[${new Date().toISOString()}] Initializing WhatsApp client after a short delay...`);
    client.initialize().catch(err => {
        console.error(`[${new Date().toISOString()}] Client initialization error:`, err);
        // Attempt to provide more details from Puppeteer if possible
        if (client.pupBrowser && client.pupBrowser.process()) {
            console.error(`[${new Date().toISOString()}] Puppeteer browser PID: ${client.pupBrowser.process().pid}`);
        }
        process.exit(1);
    });
}, 2000); // 2-second delay

// Handle script termination gracefully
process.on('SIGINT', async () => {
    console.log(`[${new Date().toISOString()}] (SIGINT) Shutting down client...`);
    if (client) {
        await client.destroy();
    }
    process.exit(0);
});
