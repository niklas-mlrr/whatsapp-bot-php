const { downloadMediaMessage } = require('@whiskeysockets/baileys');
const pino = require('pino');
const { sendToPHP } = require('./apiClient');

/**
 * Verarbeitet eingehende Nachrichten-Events von Baileys.
 * @param {import('@whiskeysockets/baileys').WASocket} sock - Die Socket-Instanz.
 * @param {import('@whiskeysockets/baileys').BaileysEventMap['messages.upsert']} m - Das Nachrichten-Event-Objekt.
 */
async function handleMessages(sock, m) {
    console.log('Nachrichten-Event empfangen:', JSON.stringify(m, undefined, 2));

    for (const msg of m.messages) {
        // Ignoriere eigene Nachrichten und fokussiere dich auf Benachrichtigungen
        if (!msg.key.fromMe && m.type === 'notify') {
            const remoteJid = msg.key.remoteJid;
            const isGroup = remoteJid?.endsWith('@g.us');
            console.log(`Nachricht von ${remoteJid} (${isGroup ? 'Gruppe' : 'Benutzer'}):`);

            // 1. Reine Textnachrichten
            if (msg.message?.conversation) {
                console.log(`  Text: ${msg.message.conversation}`);
                sendToPHP({
                    from: remoteJid,
                    type: 'text',
                    body: msg.message.conversation
                });
            }
            // 2. Erweiterte Textnachrichten (z.B. bei Antworten)
            else if (msg.message?.extendedTextMessage) {
                console.log(`  Erweiterter Text: ${msg.message.extendedTextMessage.text}`);
                sendToPHP({
                    from: remoteJid,
                    type: 'text',
                    body: msg.message.extendedTextMessage.text
                });
            }
            // 3. Bildnachrichten
            else if (msg.message?.imageMessage) {
                console.log('  Bild empfangen. Lade Medium herunter...');

                try {
                    const buffer = await downloadMediaMessage(
                        msg,
                        'buffer',
                        {},
                        {
                            logger: pino(),
                            reuploadRequest: sock.updateMediaMessage
                        }
                    );

                    console.log('  Medium heruntergeladen, Größe:', buffer.length);
                    const base64Image = buffer.toString('base64');
                    const caption = msg.message.imageMessage.caption || '';
                    const mimetype = msg.message.imageMessage.mimetype;

                    sendToPHP({
                        from: remoteJid,
                        type: 'image',
                        body: caption,
                        media: base64Image,
                        mimetype: mimetype
                    });

                } catch (error) {
                    console.error("Fehler beim Herunterladen des Bildes:", error);
                }
            }
        }
    }
}

module.exports = { handleMessages };