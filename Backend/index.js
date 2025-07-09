const { default: makeWASocket, useMultiFileAuthState, DisconnectReason, fetchLatestBaileysVersion } = require('@whiskeysockets/baileys');
const { Boom } = require('@hapi/boom');
const pino = require('pino'); // Optional: for logging
const fetch = require('node-fetch'); // for sending messages to PHP endpoint

function sendToPHP(messageText, fromJid) {
    console.log("ATTEMPTING PHP REQUEST >>>>>>>>>>>>>>>>>");
    console.log(`Sending to PHP: From=${fromJid}, Message=${messageText}`);
    
    const data = {
        from: fromJid,
        message: messageText
    };
    
    console.log("Request payload:", JSON.stringify(data));
    
    fetch("https://abiplanung.untis-notify.de/whatsapp_receiver.php", {
        method: "POST",
        headers: { 
            "Content-Type": "application/json",
            "X-Debug-Source": "whatsapp-node" 
        },
        body: JSON.stringify(data)
    }).then(async res => {
        console.log("PHP SERVER RESPONSE >>>>>>>>>>>>>>>>>");
        console.log(`Status: ${res.status} ${res.statusText}`);
        
        try {
            // Try to get response text
            const responseText = await res.text();
            console.log("Response from PHP server:", responseText);
        } catch (textError) {
            console.log("Could not read response body:", textError.message);
        }
        
        console.log("END OF PHP REQUEST >>>>>>>>>>>>>>>>>");
    }).catch(err => {
        console.error("ERROR SENDING TO PHP >>>>>>>>>>>>>>>>>");
        console.error(`Error type: ${err.name}`);
        console.error(`Error message: ${err.message}`);
        console.error(`Stack trace: ${err.stack}`);
        console.error("END OF ERROR >>>>>>>>>>>>>>>>>");
    });
}



async function connectToWhatsApp() {
    // Use file-based authentication state
    const { state, saveCreds } = await useMultiFileAuthState('baileys_auth_info');

    // Fetch the latest Baileys version
    const { version, isLatest } = await fetchLatestBaileysVersion();
    console.log(`Using Baileys version ${version}, isLatest: ${isLatest}`);

    const sock = makeWASocket({
        browser: ['Ubuntu', 'Chrome', '22.04'],
        version,
        auth: state,
        logger: pino({ level: 'debug' }), // Keep debug level for now
    });

    // Handle connection updates
    sock.ev.on('connection.update', (update) => {
        const { connection, lastDisconnect } = update;

        if (connection === 'close') {
            const statusCode = (lastDisconnect.error instanceof Boom)?.output?.statusCode;
            const shouldReconnect = lastDisconnect?.error ? statusCode !== DisconnectReason.loggedOut : false;

            console.error('Connection closed due to ', lastDisconnect?.error, ', shouldReconnect: ', shouldReconnect);

            if (statusCode === DisconnectReason.restartRequired) {
                console.log('Restart required — reconnecting...');
                setTimeout(() => connectToWhatsApp(), 1000); // automatische Wiederverbindung nach 1 Sekunde
                return;
            }

            if (shouldReconnect) {
                console.log('Connection closed unexpectedly. Please check logs and manually restart the script.');
                process.exit(1);
            } else {
                console.log('Connection closed. Not reconnecting (Reason: Logged Out or No Error).');
                if (statusCode === DisconnectReason.loggedOut) {
                    console.log('Device was logged out. Please delete baileys_auth_info and restart.');
                    process.exit(1);
                }
            }
        } else if (connection === 'open') {
            console.log('WhatsApp connection opened successfully!');
        }
    });


    // Save credentials whenever they change
    sock.ev.on('creds.update', saveCreds);

    // Handle incoming messages
    sock.ev.on('messages.upsert', async (m) => {
        console.log('Received message:', JSON.stringify(m, undefined, 2));

        // Example: Log message content if it's a text message
        m.messages.forEach(msg => {
            // Check if remoteJid exists and ends with @g.us for group messages
            const isGroup = msg.key.remoteJid?.endsWith('@g.us');
            // Basic check to avoid processing status updates or messages from self
            if (!msg.key.fromMe && m.type === 'notify') {
                console.log(`Message from ${msg.key.remoteJid} (${isGroup ? 'Group' : 'User'}):`);
                if (msg.message?.conversation) {
                    console.log(`  Text: ${msg.message.conversation}`);
                    // Send message to PHP endpoint
                    sendToPHP(msg.message.conversation, msg.key.remoteJid);
                } else if (msg.message?.extendedTextMessage) {
                    console.log(`  Extended Text: ${msg.message.extendedTextMessage.text}`);
                    // Send extended text messages to PHP endpoint too
                    sendToPHP(msg.message.extendedTextMessage.text, msg.key.remoteJid);
                }
                // Add more conditions here to handle other message types (images, videos, etc.)
            }
        });
    });

    return sock;
}



// Run the connection function
connectToWhatsApp().catch(err => {
    console.error("Unhandled Error during initial connectToWhatsApp: ", err);
    process.exit(1); // Exit if the initial connection fails critically
});
