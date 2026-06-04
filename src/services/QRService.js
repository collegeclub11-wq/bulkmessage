// node-service/src/services/QRService.js
const QRCode = require('qrcode');

class QRService {
  static async generateDataURL(text) {
    try {
      return await QRCode.toDataURL(text);
    } catch (e) {
      console.error('Failed to generate QR data URI:', e.message);
      return null;
    }
  }
}

module.exports = QRService;
