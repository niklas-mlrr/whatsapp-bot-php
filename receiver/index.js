const { connectToWhatsApp } = require('./src/whatsappClient');
const express = require('express');
const bodyParser = require('body-parser');
let sockInstance = null;

const app = express();
app.use(bodyParser.json({ limit: '10mb' }));

app.post('/send-message', async (req, res) => {
    if (!sockInstance) {
        return res.status(500).json({ error: 'WhatsApp socket not initialized' });
    }
    const { chat, type, content, media, mimetype } = req.body;
    try {
        if (type === 'text') {
            await sockInstance.sendMessage(chat, { text: content });
        } else if (type === 'image' && media && mimetype) {
            const buffer = Buffer.from(media, 'base64');
            await sockInstance.sendMessage(chat, { image: buffer, mimetype, caption: content || '' });
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

connectToWhatsApp().then(sock => {
    sockInstance = sock;
}).catch(err => {
    console.error("Unhandled Error during initial connectToWhatsApp: ", err);
    process.exit(1);
});