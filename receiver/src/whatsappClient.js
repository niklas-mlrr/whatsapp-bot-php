const { default: makeWASocket, useMultiFileAuthState, DisconnectReason, fetchLatestBaileysVersion, Browsers } = require('@whiskeysockets/baileys');
const { Boom } = require('@hapi/boom');
const pino = require('pino');
const { handleMessages } = require('./messageHandler');

/**
 * Stellt die Verbindung zum WhatsApp-Socket her und richtet die Event-Listener ein.
 */
async function connectToWhatsApp() {
    // Nutzt einen dateibasierten Authentifizierungsstatus, um die Sitzung zu speichern
    const { state, saveCreds } = await useMultiFileAuthState('baileys_auth_info');

    // Holt die neueste Baileys-Version
    const { version, isLatest } = await fetchLatestBaileysVersion();
    console.log(`Using Baileys version ${version}, isLatest: ${isLatest}`);

    // Erstellt den Socket
    const sock = makeWASocket({
        browser: Browsers.macOS('Desktop'),
        version,
        auth: state,
        logger: pino({ level: 'debug' }),
        syncFullHistory: true,
    });

    // Event-Listener für Verbindungs-Updates
    sock.ev.on('connection.update', (update) => {
        const { connection, lastDisconnect, qr } = update;

        if (qr) {
            console.log('\n\n\nQR-Code empfangen, bitte scannen:\n', qr, '\n\n\n');
        }

        if (connection === 'close') {
            const shouldReconnect = (lastDisconnect.error instanceof Boom)
                ? lastDisconnect.error.output.statusCode !== DisconnectReason.loggedOut
                : false;

            console.error('Verbindung geschlossen wegen ', lastDisconnect?.error, ', Wiederverbindung: ', shouldReconnect);

            // Stellt die Verbindung wieder her, falls es kein "loggedOut"-Fehler war
            if (shouldReconnect) {
                console.log('Verbinde erneut...');
                connectToWhatsApp();
            } else if ((lastDisconnect.error instanceof Boom)?.output?.statusCode === DisconnectReason.loggedOut) {
                console.log('Gerät wurde ausgeloggt. Bitte lösche den Ordner "baileys_auth_info" und starte neu.');
                process.exit(1);
            }
        } else if (connection === 'open') {
            console.log('WhatsApp-Verbindung erfolgreich geöffnet!');
        }
    });

    // Speichert die Anmeldeinformationen, wenn sie sich ändern
    sock.ev.on('creds.update', saveCreds);

    // Delegiert die Nachrichtenverarbeitung an den messageHandler
    // Wir übergeben 'sock', damit der Handler bei Bedarf darauf zugreifen kann (z.B. für reuploadRequest)
    sock.ev.on('messages.upsert', (m) => {
        handleMessages(sock, m);
    });

    return sock;
}

module.exports = { connectToWhatsApp };