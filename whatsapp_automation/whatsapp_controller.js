// c:\Users\Public\Job_Post\whatsapp_automation\whatsapp_controller.js
const { Client, LocalAuth, MessageMedia } = require('whatsapp-web.js'); // Added MessageMedia if you plan to send media later
const qrcode = require('qrcode-terminal');
const path =require('path');
const fs = require('fs-extra');

// --- Constants ---
const SESSION_DATA_PATH = path.join(__dirname, '.wwebjs_auth_session');
const INITIALIZE_TIMEOUT_MS = 120000; // 120 seconds for client initialization

const COMMANDS = {
    LOGIN: 'login',
    LOGOUT: 'logout',
    GET_PROFILE: 'getProfile', // Kept for direct use, get_profile_info is preferred by frontend
    GET_PROFILE_INFO: 'get_profile_info', // For frontend compatibility
    GET_STATUS: 'get_status',
    GET_GROUPS: 'get_groups',
    SEND_MESSAGE: 'send_message',
};

// --- Logger ---
const logger = {
    info: (...args) => console.error(`[INFO] ${new Date().toISOString()}`, ...args.map(arg => (arg instanceof Error ? arg.message : String(arg)))),
    error: (...args) => console.error(`[ERROR] ${new Date().toISOString()}`, ...args.map(arg => (arg instanceof Error ? arg.message : String(arg)))),
    fatal: (...args) => console.error(`[FATAL] ${new Date().toISOString()}`, ...args.map(arg => (arg instanceof Error ? arg.message : String(arg)))),
};

// --- JSON Response Function ---
const sendJsonResponse = (data) => {
    if (!process.stdout.writableEnded) {
        process.stdout.write(JSON.stringify(data) + '\n');
    } else {
        logger.error("Attempted to write to closed stdout:", JSON.stringify(data));
    }
};

// --- Timeout Helper ---
const withTimeout = (promise, ms, timeoutError = new Error(`Operation timed out after ${ms}ms`)) => {
    const timeout = new Promise((_, reject) =>
        setTimeout(() => reject(timeoutError), ms)
    );
    return Promise.race([promise, timeout]);
};

const client = new Client({
    authStrategy: new LocalAuth({
        dataPath: SESSION_DATA_PATH
    }),
    puppeteer: {
        headless: true,
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
            '--disable-accelerated-2d-canvas',
            '--no-first-run',
            '--no-zygote',
            '--disable-gpu',
        ],
    }
});

// --- Safe Exit ---
let isExiting = false;
const safeExit = async (code = 0) => {
    if (isExiting) {
        logger.info(`SafeExit called but already exiting with code ${code}.`);
        return;
    }
    isExiting = true;
    logger.info(`Preparing to exit with code ${code}.`);

    if (client && typeof client.destroy === 'function') {
        if (client.pupBrowser) {
            try {
                logger.info('Attempting to destroy client instance...');
                await client.destroy();
                logger.info('Client instance destroyed successfully.');
            } catch (e) {
                logger.error('Error destroying client instance:', e.message);
            }
        } else {
            logger.info('Client instance was not fully initialized or already destroyed, skipping destroy.');
        }
    } else {
        logger.info('Client instance not available for destruction.');
    }

    logger.info(`Exiting process with code ${code}.`);
    process.exit(code);
};

client.on('qr', (qr) => {
    const command = process.argv[2];
    logger.info('QR Code Received. Scan it with WhatsApp on your phone (printed to terminal where this script runs).');
    qrcode.generate(qr, { small: true });
    // If the command is 'login', the frontend might expect a status update or QR data.
    // The current design relies on the 'ready' event for success or terminal for QR.
    // For direct QR data to frontend via 'login' command, this would need adjustment.
    // However, the PHP manager calls 'login' and then 'get_status', so this is okay.
    // The frontend also has specific instructions for terminal login.
});

client.on('authenticated', () => {
    logger.info('Client is authenticated! Session saved/updated.');
});

client.on('auth_failure', async (msg) => {
    logger.error('Authentication failed:', msg);
    const command = process.argv[2];
    if (Object.values(COMMANDS).includes(command)) {
        sendJsonResponse({ success: false, message: `Authentication failed: ${msg}`, needsLogin: true });
        await safeExit(1);
    }
});

client.on('ready', async () => {
    logger.info('WhatsApp client is ready!');
    const command = process.argv[2];
    const arg1 = process.argv[3]; // For chat_id
    const arg2 = process.argv[4]; // For message_text

    // This log is crucial to see if the 'ready' event handler is entered.
    logger.info(`[onReady] Event fired. Current command from process.argv[2]: ${command}`);

    if (!command) {
        logger.error("[onReady] No command identified from process.argv[2] inside ready handler. This is unexpected.");
        // Potentially exit or handle, but usually main() would catch no command earlier.
    }
    if (command === COMMANDS.LOGIN) {
        let profileInfo = {};
        try {
            profileInfo = {
                name: client.info.pushname,
                number: client.info.wid.user,
                platform: client.info.platform,
                me: client.info.wid // For frontend compatibility
            };
        } catch (e) {
            logger.error("Could not retrieve basic client info on ready:", e.message);
        }
        sendJsonResponse({
            success: true,
            message: "Login successful and session saved.",
            dataPath: SESSION_DATA_PATH,
            profile: profileInfo,
            status: "LOGGED_IN" // Explicitly send status
        });
        await safeExit(0);
    } else if (command === COMMANDS.GET_PROFILE || command === COMMANDS.GET_PROFILE_INFO) {
        logger.info(`[onReady] Processing command: ${command}. Client info available: ${!!client.info}, client.info.wid available: ${!!(client.info && client.info.wid)}`);
        if (!client.info || !client.info.wid) {
            logger.error('[onReady] Critical: client.info or client.info.wid is not available on ready event for get_profile_info. This should not happen.');
            sendJsonResponse({ success: false, message: "Internal error: Client info not available on ready." });
            await safeExit(1);
            return;
        }
        try {
            const profilePicUrl = await client.getProfilePicUrl(client.info.wid._serialized).catch(e => {
                logger.error("Could not fetch profile picture URL:", e.message);
                return null;
            });
            sendJsonResponse({
                success: true,
                profile: {
                    pushname: client.info.pushname, // Keep 'name' for consistency if other parts use it
                    number: client.info.wid.user,
                    platform: client.info.platform,
                    me: client.info.wid, // For frontend: data.profile.me._serialized
                    profilePicUrl: profilePicUrl
                }
            });
        } catch (profileError) {
            logger.error('Failed to get profile details:', profileError.message);
            sendJsonResponse({
                success: false,
                message: `Client is ready, but failed to fetch profile details: ${profileError.message}`,
            });
        }
        await safeExit(0);
    } else if (command === COMMANDS.GET_STATUS) {
        sendJsonResponse({
            success: true,
            status: "LOGGED_IN", // If client is ready, it's logged in
            message: "Client is logged in and ready."
        });
        await safeExit(0);
    } else if (command === COMMANDS.GET_GROUPS) {
        try {
            const chats = await client.getChats();
            const groups = chats.filter(chat => chat.isGroup).map(group => ({
                id: group.id._serialized,
                name: group.name,
                participantsCount: group.participants ? group.participants.length : 'N/A', // participant data might not always be full
                isReadOnly: group.isReadOnly,
            }));
            sendJsonResponse({ success: true, groups: groups });
        } catch (e) {
            logger.error('Failed to get groups:', e.message);
            sendJsonResponse({ success: false, message: `Failed to retrieve groups: ${e.message}` });
        }
        await safeExit(0);
    } else if (command === COMMANDS.SEND_MESSAGE) {
        const targetIdentifier = arg1; // This can be a chatId or a groupName
        const messageText = arg2;

        if (!targetIdentifier || !messageText) {
            sendJsonResponse({ success: false, message: "Missing targetIdentifier or messageText for send_message command." });
            await safeExit(1);
            return;
        }

        try {
            let finalChatId = targetIdentifier;
            // Check if targetIdentifier looks like a group name rather than a direct chatId
            // (basic check, assumes chatIds will contain '@g.us' or '@c.us')
            if (!targetIdentifier.includes('@g.us') && !targetIdentifier.includes('@c.us')) {
                logger.info(`Target '${targetIdentifier}' looks like a group name. Attempting to find group ID.`);
                const chats = await client.getChats();
                const targetGroup = chats.find(chat => chat.isGroup && chat.name && chat.name.toLowerCase() === targetIdentifier.toLowerCase());

                if (targetGroup) {
                    finalChatId = targetGroup.id._serialized;
                    logger.info(`Found group '${targetIdentifier}' with ID: ${finalChatId}`);
                } else {
                    logger.error(`Group named '${targetIdentifier}' not found.`);
                    sendJsonResponse({ success: false, message: `Group named '${targetIdentifier}' not found.` });
                    await safeExit(1);
                    return;
                }
            }

            await client.sendMessage(finalChatId, messageText);
            sendJsonResponse({ success: true, message: `Message sent to ${finalChatId} (target: ${targetIdentifier}).` });
        } catch (e) {
            logger.error(`Failed to send message to target '${targetIdentifier}':`, e.message);
            sendJsonResponse({ success: false, message: `Failed to send message: ${e.message}` });
        }
        await safeExit(0);
    }
});

client.on('disconnected', (reason) => {
    logger.info('Client was logged out:', reason);
    // This event is crucial. If a command is running and this happens,
    // the script might exit before the command completes or send an error.
    // For short-lived commands, safeExit usually handles it.
});

const initializeClientWithTimeout = async () => {
    try {
        await withTimeout(client.initialize(), INITIALIZE_TIMEOUT_MS);
    } catch (err) {
        throw err; // Rethrow to be caught by the command-specific catch block
    }
};

const main = async () => {
    const command = process.argv[2];
    const arg1 = process.argv[3]; // For chat_id or other params
    const arg2 = process.argv[4]; // For message_text or other params

    if (!command) {
        const supportedCommands = Object.values(COMMANDS).join(', ');
        logger.info(`No command specified. Supported: ${supportedCommands}`);
        sendJsonResponse({ success: false, message: `No command specified. Supported commands: ${supportedCommands}.` });
        await safeExit(1);
        return;
    }

    logger.info(`Starting whatsapp_controller.js for action: ${command}`);

    if (command === COMMANDS.LOGOUT) {
        logger.info('Attempting to logout and clear session data...');
        try {
            // Attempt to destroy client if it was somehow initialized
            if (client && typeof client.destroy === 'function' && client.pupBrowser) {
                await client.destroy();
                logger.info('Client instance destroyed for logout.');
            }
            // Clear session data
            const sessionExists = await fs.pathExists(SESSION_DATA_PATH);
            if (sessionExists) {
                await fs.remove(SESSION_DATA_PATH);
                logger.info('Session data cleared successfully.');
                sendJsonResponse({ success: true, message: "Logout successful and session data cleared." });
            } else {
                logger.info('No session data found to clear.');
                sendJsonResponse({ success: true, message: "No active session found to logout." });
            }
        } catch (e) {
            logger.error('Error during logout process:', e.message);
            sendJsonResponse({ success: false, message: `Error during logout: ${e.message}` });
            await safeExit(1);
            return; // ensure exit after error
        }
        await safeExit(0);
        return; // ensure exit after successful logout
    }


    // For commands requiring an active session or initialization
    if (command === COMMANDS.LOGIN || command === COMMANDS.GET_PROFILE || command === COMMANDS.GET_PROFILE_INFO || command === COMMANDS.GET_STATUS || command === COMMANDS.GET_GROUPS || command === COMMANDS.SEND_MESSAGE) {
        if (command === COMMANDS.LOGIN) {
            logger.info(`Initializing client for login. Session will be stored at: ${SESSION_DATA_PATH}`);
            // Optional: Aggressively clear previous session for a fresh login attempt
            // if (await fs.pathExists(SESSION_DATA_PATH)) {
            //     logger.info('Clearing previous session data for login attempt...');
            //     await fs.remove(SESSION_DATA_PATH);
            // }
        } else { // For other commands, check if session exists first
            logger.info(`Attempting to ${command}. Checking session at: ${SESSION_DATA_PATH}`);
            try {
                const sessionExists = await fs.pathExists(SESSION_DATA_PATH);
                if (!sessionExists || (await fs.readdir(SESSION_DATA_PATH)).length === 0) {
                    logger.info('Session data path does not exist or is empty. Not logged in.');
                    const status = command === COMMANDS.GET_STATUS ? "NOT_LOGGED_IN" : undefined; // Frontend expects uppercase
                    sendJsonResponse({ success: false, status, message: "Not logged in. Please login first.", needsLogin: true });
                    await safeExit(0);
                    return;
                }
            } catch (fsError) {
                logger.error(`Error checking session directory for ${command}:`, fsError.message);
                sendJsonResponse({ success: false, status: "ERROR", message: `Error accessing session data: ${fsError.message}` }); // Frontend expects uppercase
                await safeExit(1);
                return;
            }
        }

        try {
            await initializeClientWithTimeout();
            // Success responses are handled in the 'ready' event for most commands
            // For SEND_MESSAGE, it's handled directly in 'ready' if client was already ready,
            // or after initialization if it wasn't.
        } catch (err) {
            logger.error(`Client.initialize() FAILED for ${command}:`, err.message);
            const errorMessage = err.message.toLowerCase();
            const isSessionError = errorMessage.includes('unable to retrieve wastartupinfo') ||
                                   errorMessage.includes('session not found') ||
                                   errorMessage.includes('restore session') ||
                                   errorMessage.includes('navigation failed because browser has disconnected') ||
                                   errorMessage.includes('timed out');
            const status = command === COMMANDS.GET_STATUS ? (isSessionError ? "SESSION_INVALID" : "INIT_FAILED") : undefined; // Frontend expects uppercase
            sendJsonResponse({ success: false, status, message: `Failed to initialize for ${command}: ${err.message}`, needsLogin: isSessionError });
            await safeExit(1);
        }
    } else {
        const supportedCommands = Object.values(COMMANDS).join(', ');
        logger.info(`Unknown command: '${command}'. Supported: ${supportedCommands}`);
        sendJsonResponse({ success: false, message: `Unknown command: '${command}'. Supported commands: ${supportedCommands}.` });
        await safeExit(1);
    }
};

// --- Global Error Handlers & Process Signals ---
process.on('unhandledRejection', async (reason, promise) => {
    const reasonMsg = reason instanceof Error ? reason.message : String(reason);
    logger.fatal('Unhandled Rejection at:', promise, 'reason:', reasonMsg);
    if (!isExiting) {
        sendJsonResponse({ success: false, message: `Unhandled server error: ${reasonMsg}` });
        await safeExit(1);
    }
});

process.on('uncaughtException', async (error) => {
    logger.fatal('Uncaught Exception:', error.message, error.stack);
    if (!isExiting) {
        sendJsonResponse({ success: false, message: `Uncaught server exception: ${error.message}` });
        await safeExit(1);
    }
});

process.on('SIGINT', async () => {
    logger.info('SIGINT received. Shutting down...');
    await safeExit(0);
});
process.on('SIGTERM', async () => {
    logger.info('SIGTERM received. Shutting down...');
    await safeExit(0);
});

main().catch(async e => {
    logger.fatal("Critical error in main execution: ", e.message, e.stack);
    if (!isExiting) {
        sendJsonResponse({ success: false, message: `Critical error in script execution: ${e.message}` });
        await safeExit(1);
    }
});
