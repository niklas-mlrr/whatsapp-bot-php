const { connectToWhatsApp } = require('./src/whatsappClient');

connectToWhatsApp().catch(err => {
    console.error("Unhandled Error during initial connectToWhatsApp: ", err);
    process.exit(1);
});