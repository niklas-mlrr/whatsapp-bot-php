const { default: makeWASocket, useMultiFileAuthState, DisconnectReason, fetchLatestBaileysVersion, Browsers, downloadMediaMessage } = require('@whiskeysockets/baileys');
const { Boom } = require('@hapi/boom');
const pino = require('pino');
const fetch = require('node-fetch');

function sendToPHP(payload) {
    console.log("ATTEMPTING PHP REQUEST >>>>>>>>>>>>>>>>>");

    const logPayload = { ...payload };
    if (logPayload.media) {
        logPayload.media = `[Base64 Data of ${logPayload.mimetype}, length: ${payload.media.length}]`;
    }
    console.log("Sending to PHP:", JSON.stringify(logPayload, null, 2));


    fetch("https://abiplanung.untis-notify.de/Backend/Controller/WhatsAppWebhookController.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "X-Debug-Source": "whatsapp-node"
        },
        body: JSON.stringify(payload)
    }).then(async res => {
        console.log("PHP SERVER RESPONSE >>>>>>>>>>>>>>>>>");
        console.log(`Status: ${res.status} ${res.statusText}`);

        try {
            const responseText = await res.text();
            console.log("Response from PHP server:", responseText);
        } catch (textError) {
            console.log("Could not read response body:", textError.message);
        }

        console.log("END OF PHP REQUEST >>>>>>>>>>>>>>>>>");
    }).catch(err => {
        console.error("ERROR SENDING TO PHP >>>>>>>>>>>>>>>>>");
        console.error(err);
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
        browser: Browsers.macOS('Desktop'),
        version,
        auth: state,
        logger: pino({ level: 'debug' }),
        syncFullHistory: false,
    });

    // Handle connection updates
    sock.ev.on('connection.update', (update) => {
        const { connection, lastDisconnect, qr } = update;

        if(qr) {
            console.log('\n\n\nQR code received, please scan:\n', qr, '\n\n\n');
        }

        if (connection === 'close') {
            const shouldReconnect = (lastDisconnect.error instanceof Boom)
                ? lastDisconnect.error.output.statusCode !== DisconnectReason.loggedOut
                : false;

            console.error('Connection closed due to ', lastDisconnect?.error, ', shouldReconnect: ', shouldReconnect);

            // Reconnect if the error is not a logout
            if (shouldReconnect) {
                console.log('Reconnecting...');
                connectToWhatsApp();
            } else if ((lastDisconnect.error instanceof Boom)?.output?.statusCode === DisconnectReason.loggedOut) {
                 console.log('Device was logged out. Please delete baileys_auth_info and restart.');
                 process.exit(1);
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

        for (const msg of m.messages) {
            const isGroup = msg.key.remoteJid?.endsWith('@g.us');
            if (!msg.key.fromMe && m.type === 'notify') {
                console.log(`Message from ${msg.key.remoteJid} (${isGroup ? 'Group' : 'User'}):`);

                if (msg.message?.conversation) {
                    console.log(`  Text: ${msg.message.conversation}`);
                    sendToPHP({
                        from: msg.key.remoteJid,
                        type: 'text',
                        body: msg.message.conversation
                    });

                } else if (msg.message?.extendedTextMessage) {
                    console.log(`  Extended Text: ${msg.message.extendedTextMessage.text}`);
                    sendToPHP({
                        from: msg.key.remoteJid,
                        type: 'text',
                        body: msg.message.extendedTextMessage.text
                    });

                } else if (msg.message?.imageMessage) {
                    console.log('  Image received. Downloading full image...');

                    const buffer = await downloadMediaMessage(
                        msg,
                        'buffer',
                        {},
                        {
                            logger: pino(),
                            reuploadRequest: sock.updateMediaMessage
                        }
                    );

                    console.log('  Full image downloaded, size:', buffer.length);

                    const base64Image = buffer.toString('base64');
                    const caption = msg.message.imageMessage.caption || '';

                    sendToPHP({
                        from: msg.key.remoteJid,
                        type: 'image',
                        body: caption,
                        media: base64Image,
                        mimetype: msg.message.imageMessage.mimetype // z.B. 'image/jpeg'
                    });
                }
            }
        }
    });

    return sock;
}



// Run the connection function
connectToWhatsApp().catch(err => {
    console.error("Unhandled Error during initial connectToWhatsApp: ", err);
    process.exit(1); // Exit if the initial connection fails critically
});