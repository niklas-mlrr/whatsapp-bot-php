const { default: makeWASocket, useMultiFileAuthState, DisconnectReason, fetchLatestBaileysVersion, Browsers } = require('@whiskeysockets/baileys');
const { Boom } = require('@hapi/boom');
const pino = require('pino');
const config = require('./config');
const { handleMessages } = require('./messageHandler');
const logger = require('./logger');

/**
 * Establishes a connection to the WhatsApp Web service.
 * @returns {Promise<object>} The WhatsApp socket instance.
 */
async function connectToWhatsApp() {
    try {
        logger.info('Initializing WhatsApp client...');
        
        // Use file-based authentication state
        const { state, saveCreds } = await useMultiFileAuthState(config.whatsapp.authDir);
        logger.debug(`Using auth directory: ${config.whatsapp.authDir}`);

        // Get the latest Baileys version
        const { version, isLatest } = await fetchLatestBaileysVersion();
        logger.info(`Using Baileys version ${version}, isLatest: ${isLatest}`);

        // Configure the WhatsApp socket
        const sock = makeWASocket({
            browser: Browsers.macOS(config.whatsapp.clientName),
            version,
            auth: state,
            logger: pino({
                level: config.logging.level,
                transport: config.nodeEnv === 'development' ? {
                    target: 'pino-pretty',
                    options: {
                        colorize: true,
                        translateTime: 'SYS:standard',
                        ignore: 'pid,hostname',
                    },
                } : undefined,
            }),
            syncFullHistory: true,
            printQRInTerminal: true,
            markOnlineOnConnect: true,
            generateHighQualityLinkPreview: true,
            getMessage: async (key) => {
                logger.debug({ key }, 'Getting message from key');
                return null; // Return null to let Baileys handle message fetching
            },
        });

        // Event listener for connection updates
        sock.ev.on('connection.update', (update) => {
            const { connection, lastDisconnect, qr } = update;

            if (qr) {
                logger.info('QR code received, please scan with your phone');
                console.log('\n\n\nQR Code for WhatsApp Web:\n', qr, '\n\n\n');
            }

            if (connection === 'close') {
                const shouldReconnect = (lastDisconnect.error instanceof Boom)
                    ? lastDisconnect.error.output.statusCode !== DisconnectReason.loggedOut
                    : false;

                logger.warn({
                    error: lastDisconnect?.error,
                    shouldReconnect
                }, 'Connection closed');

                // Reconnect if not logged out
                if (shouldReconnect) {
                    logger.info('Reconnecting to WhatsApp...');
                    setTimeout(connectToWhatsApp, 5000); // Add delay before reconnecting
                } else if ((lastDisconnect.error instanceof Boom)?.output?.statusCode === DisconnectReason.loggedOut) {
                    logger.fatal('Device logged out. Please delete the auth directory and restart.');
                    process.exit(1);
                }
            } else if (connection === 'open') {
                logger.info('Successfully connected to WhatsApp');
            }
        });

        // Save credentials when they get updated
        sock.ev.on('creds.update', saveCreds);

        // Delegate message processing to the message handler
        sock.ev.on('messages.upsert', (m) => {
            handleMessages(sock, m);
        });

        return sock;
    } catch (error) {
        logger.error({ error }, 'Failed to initialize WhatsApp client');
        throw error; // Re-throw to allow retry logic to handle it
    }
}

module.exports = { connectToWhatsApp };