// node-service/src/services/WhatsAppService.js
const axios = require('axios');
const db = require('../config/database');
const RateLimitService = require('./RateLimitService');
const BanPreventionService = require('./BanPreventionService');

const BOT_URL = 'https://whatsappbackend-production-9e33.up.railway.app';

class WhatsAppService {
  constructor(tenantId, sessionId) {
    this.tenantId = tenantId;
    this.sessionId = sessionId;
    this.sock = null;
    this.isConnected = false;
    this.rateLimiter = new RateLimitService(tenantId);
    this.banPrevention = new BanPreventionService(tenantId);
    this.messageQueue = [];
    this.isProcessing = false;
  }

  async initialize() {
    console.log(`Checking WhatsApp session status via working bot: ${this.sessionId}`);
    try {
      const response = await axios.get(`${BOT_URL}/status?sessionId=${this.sessionId}`);
      const status = response.data.status;
      this.isConnected = (status === 'connected');
      
      // Update database status
      await this.updateSessionStatus(status);
    } catch (e) {
      console.error(`Failed to check session status for ${this.sessionId}:`, e.message);
      this.isConnected = false;
      await this.updateSessionStatus('disconnected');
    }
  }

  async saveQRCode(qrImage) {
    await db.execute(
      `UPDATE whatsapp_sessions 
       SET qr_code = ?, status = 'scanning', last_qr_generated = NOW() 
       WHERE session_id = ? AND tenant_id = ?`,
      [qrImage, this.sessionId, this.tenantId]
    );
  }

  async updateSessionStatus(status) {
    if (status === 'connected') {
      await db.execute(
        'UPDATE whatsapp_sessions SET status = ?, last_connected = NOW() WHERE session_id = ?',
        [status, this.sessionId]
      );
    } else if (status === 'disconnected') {
      await db.execute(
        'UPDATE whatsapp_sessions SET status = ?, last_disconnected = NOW() WHERE session_id = ?',
        [status, this.sessionId]
      );
    } else {
      await db.execute(
        'UPDATE whatsapp_sessions SET status = ? WHERE session_id = ?',
        [status, this.sessionId]
      );
    }
  }

  async updateMessageReceipt(msgId, status) {
    const timeField = status === 'delivered' ? 'delivered_at' : (status === 'read' ? 'read_at' : null);
    if (timeField) {
      await db.execute(
        `UPDATE campaign_contacts SET status = ?, ${timeField} = NOW() WHERE message_id = ?`,
        [status, msgId]
      );
      await db.execute(
        `UPDATE message_logs SET status = ?, ${timeField} = NOW() WHERE whatsapp_message_id = ?`,
        [status, msgId]
      );
    } else {
      await db.execute(
        'UPDATE campaign_contacts SET status = ?, sent_at = NOW() WHERE message_id = ?',
        [status, msgId]
      );
      await db.execute(
        'UPDATE message_logs SET status = ?, sent_at = NOW() WHERE whatsapp_message_id = ?',
        [status, msgId]
      );
    }
  }

  async sendRawMessage(to, message, options = {}) {
    // Rate Limiting
    await this.rateLimiter.checkLimit();

    const formattedJid = to.includes('@s.whatsapp.net') ? to.split('@')[0] : to;

    let processedMessage = message;
    if (this.banPrevention.detectPatterns(message)) {
      processedMessage = this.banPrevention.rotatePhrases(message);
    }

    // Call working bot send endpoint
    console.log(`Forwarding message send to external bot for session ${this.sessionId}`);
    const payload = {
      sessionId: this.sessionId,
      number: formattedJid,
      message: processedMessage
    };

    if (options.image) {
      payload.image = options.image;
    }

    let response;
    try {
      response = await axios.post(`${BOT_URL}/send`, payload);
    } catch (err) {
      if (err.response && err.response.data && err.response.data.error) {
        throw new Error(`Bot Error: ${err.response.data.error}`);
      }
      throw new Error(err.message);
    }

    if (response.data.status !== 'success') {
      throw new Error(response.data.error || 'Failed to send message via external bot');
    }

    const mockResult = {
      key: {
        id: response.data.messageId || 'external_' + Date.now()
      }
    };
    
    // Track history
    this.banPrevention.trackMessageHistory(processedMessage, to);

    // Rate limit log
    await this.rateLimiter.logLimitHit(this.sessionId, 'send', to);

    return mockResult;
  }

  async cleanAuth() {
    try {
      await axios.post(`${BOT_URL}/restart`, { sessionId: this.sessionId });
    } catch (e) {
      console.warn('Failed to call restart on external bot:', e.message);
    }
  }
}

module.exports = WhatsAppService;
