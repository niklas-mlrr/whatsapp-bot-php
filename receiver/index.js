const { connectToWhatsApp } = require('./src/whatsappClient');
const express = require('express');
const bodyParser = require('body-parser');
const axios = require('axios');
const fs = require('fs');
const { promisify } = require('util');
const stream = require('stream');

const pipeline = promisify(stream.pipeline);

let sockInstance = null;
let isConnected = false;

async function start() {
    sockInstance = await connectToWhatsApp();

    // Listen for connection updates and always use the latest socket instance
    if (sockInstance.ev && sockInstance.ev.on) {
        sockInstance.ev.on('connection.update', (update) => {
            if (update.connection === 'open') {
                isConnected = true;
                console.log('Socket reconnected and updated.');
            } else if (update.connection === 'close') {
                isConnected = false;
                console.log('Socket connection closed.');
            }
        });
    }

    const app = express();
    app.use(bodyParser.json({ limit: '10mb' }));

    app.post('/send-message', async (req, res) => {
        console.log('Received send-message request');
        if (!sockInstance) {
            console.log('sockInstance is not initialized');
            return res.status(500).json({ error: 'WhatsApp socket not initialized' });
        }
        console.log('isConnected:', isConnected);
        if (!isConnected) {
            console.log('WhatsApp socket not connected');
            return res.status(500).json({ error: 'WhatsApp socket not connected' });
        }
        const { chat, type, content, media, mimetype } = req.body;
        try {
            if (type === 'text') {
                await sockInstance.sendMessage(chat, { text: content });
            } else if (type === 'image' && media) {
                try {
                    // Check if media is a URL or base64 data
                    if (media.startsWith('http')) {
                        // Download the image from the URL
                        const response = await axios({
                            method: 'GET',
                            url: media,
                            responseType: 'arraybuffer'
                        });
                        
                        // Get the actual MIME type from response headers if not provided
                        const actualMimetype = mimetype || response.headers['content-type'] || 'image/jpeg';
                        
                        // Send the image to WhatsApp
                        await sockInstance.sendMessage(
                            chat, 
                            { 
                                image: response.data, 
                                mimetype: actualMimetype,
                                caption: content || '' 
                            }
                        );
                    } else if (media.startsWith('data:')) {
                        // Handle base64 data URL
                        const matches = media.match(/^data:([A-Za-z-+\/]+);base64,(.+)$/);
                        if (!matches || matches.length !== 3) {
                            throw new Error('Invalid base64 image data');
                        }
                        
                        const buffer = Buffer.from(matches[2], 'base64');
                        await sockInstance.sendMessage(
                            chat, 
                            { 
                                image: buffer, 
                                mimetype: matches[1],
                                caption: content || '' 
                            }
                        );
                    } else {
                        throw new Error('Unsupported media format');
                    }
                } catch (error) {
                    console.error('Error processing image:', error);
                    throw new Error(`Failed to process image: ${error.message}`);
                }
            } else {
                return res.status(400).json({ error: 'Unsupported message type or missing fields' });
            }
            res.json({ status: 'sent' });
        } catch (err) {
            console.error('Failed to send message:', err);
            res.status(500).json({ error: 'Failed to send message', details: err.message });
        }
    });

    const PORT = process.env.PORT || 3000;
    app.listen(PORT, () => {
        console.log(`Express server listening on port ${PORT}`);
    });
}

// Prevent multiple Node processes (simple lock file approach)
const lockFile = './whatsapp-bot.lock';
if (fs.existsSync(lockFile)) {
    console.error('Another instance of the receiver is already running. Exiting.');
    process.exit(1);
} else {
    fs.writeFileSync(lockFile, process.pid.toString());
    process.on('exit', () => {
        if (fs.existsSync(lockFile)) fs.unlinkSync(lockFile);
    });
    process.on('SIGINT', () => process.exit(0));
    process.on('SIGTERM', () => process.exit(0));
}

start().catch(err => {
    console.error("Unhandled Error during initial connectToWhatsApp: ", err);
    process.exit(1);
});