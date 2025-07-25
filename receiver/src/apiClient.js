const fetch = require('node-fetch');
const config = require('../config');
const {
    useMultiFileAuthState,
    fetchLatestBaileysVersion,
    makeWASocket,
    Browsers,
    DisconnectReason
} = require("@whiskeysockets/baileys");
const pino = require("pino");
const {Boom} = require("@hapi/boom");

function sendToPHP(payload) {
    console.log("ATTEMPTING PHP REQUEST >>>>>>>>>>>>>>>>>");

    const logPayload = { ...payload };
    if (logPayload.media) {
        logPayload.media = `[Base64 Data of ${logPayload.mimetype}, length: ${payload.media.length}]`;
    }
    console.log("Sending to PHP:", JSON.stringify(logPayload, null, 2));


    //fetch("https://abiplanung.untis-notify.de/backend/src/controller/WhatsAppWebhookController.php", {
    fetch("http://192.168.178.84:8000/api/whatsapp-webhook", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "X-Debug-Source": "whatsapp-node"
        },
        body: JSON.stringify({
            sender: payload.from,
            chat: payload.from, // or remoteJid if available
            type: payload.type,
            content: payload.body,
            sending_time: new Date().toISOString()
        })
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


module.exports = { sendToPHP };