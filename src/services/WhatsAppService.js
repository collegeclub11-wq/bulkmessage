// node-service/src/services/WhatsAppService.js
const makeWASocket = require('@whiskeysockets/baileys').default;
const { useMultiFileAuthState, delay, DisconnectReason, Browsers } = require('@whiskeysockets/baileys');
const QRCode = require('qrcode');
const Pino = require('pino');
const db = require('../config/database');
const RateLimitService = require('./RateLimitService');
const BanPreventionService = require('./BanPreventionService');

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
    console.log(`Initializing WhatsApp session: ${this.sessionId} for tenant: ${this.tenantId}`);
    
    // Close existing socket if any to prevent leaks
    if (this.sock) {
      try {
        console.log(`Closing existing socket for session ${this.sessionId}`);
        this.sock.end();
        this.sock.ev.removeAllListeners();
      } catch (e) {
        console.warn('Error ending socket:', e.message);
      }
    }

    const authDir = `./auth_data/tenant_${this.tenantId}/session_${this.sessionId}`;
    const fs = require('fs');
    
    // Check if session has ever been connected
    let isPreviouslyConnected = false;
    try {
      const [rows] = await db.execute(
        'SELECT status, phone_number FROM whatsapp_sessions WHERE session_id = ? AND tenant_id = ?',
        [this.sessionId, this.tenantId]
      );
      if (rows && rows.length > 0) {
        const row = rows[0];
        if (row.phone_number && row.status !== 'pending' && row.status !== 'scanning') {
          isPreviouslyConnected = true;
        }
      }
    } catch (dbErr) {
      console.warn('Error querying session status from DB:', dbErr.message);
    }

    if (!isPreviouslyConnected) {
      console.log(`Session has no previous successful connection. Purging auth directory to start clean: ${authDir}`);
      await this.cleanAuth();
    } else {
      const credsPath = `${authDir}/creds.json`;
      if (!fs.existsSync(credsPath)) {
        console.log(`creds.json not found. Purging session auth directory to start clean: ${authDir}`);
        await this.cleanAuth();
      }
    }

    const logger = Pino({ level: 'warn' });
    const { state, saveCreds } = await useMultiFileAuthState(authDir);

    // Fetch dynamic version to avoid 405 Method Not Allowed error
    let version = [2, 3000, 1017588726];
    try {
      const { fetchLatestBaileysVersion } = require('@whiskeysockets/baileys');
      const latest = await fetchLatestBaileysVersion();
      if (latest && latest.version) {
        version = latest.version;
        console.log(`Successfully fetched latest Baileys version: ${version.join('.')}`);
      }
    } catch (e) {
      console.warn('Failed to fetch latest Baileys version, using default static fallback:', e.message);
    }

    this.sock = makeWASocket({
      auth: state,
      version: version,
      printQRInTerminal: false,
      browser: Browsers.macOS('Desktop'),
      syncFullHistory: false,
      markOnlineOnConnect: false,
      logger: logger,
      qrTimeout: 55000,
      keepAliveIntervalMs: 25000
    });

    this.sock.ev.on('creds.update', saveCreds);

    this.sock.ev.on('connection.update', async (update) => {
      const { connection, lastDisconnect, qr } = update;
      console.log(`Session ${this.sessionId} connection update: connection=${connection}, qr=${qr ? 'yes' : 'no'}`);

      // Log status update details to the database JSON field for monitoring/debugging
      try {
        const statusDetails = {
          connection: connection || null,
          qr: qr ? 'available' : 'none',
          timestamp: new Date().toISOString(),
          error: lastDisconnect?.error ? {
            message: lastDisconnect.error.message,
            code: lastDisconnect.error.code,
            statusCode: lastDisconnect?.error?.output?.statusCode,
            details: lastDisconnect.error.stack || String(lastDisconnect.error)
          } : null
        };
        await db.execute(
          'UPDATE whatsapp_sessions SET connection_status = ? WHERE session_id = ?',
          [JSON.stringify(statusDetails), this.sessionId]
        );
      } catch (dbErr) {
        console.error('Failed to update connection_status in DB:', dbErr.message);
      }

      if (qr) {
        try {
          console.log(`Generating QR code image for session ${this.sessionId}`);
          const qrImage = await QRCode.toDataURL(qr);
          await this.saveQRCode(qrImage);
        } catch (e) {
          console.error('QR code generation error:', e);
        }
      }

      if (connection === 'open') {
        this.isConnected = true;
        console.log(`Session ${this.sessionId} successfully connected to WhatsApp Web!`);
        await this.updateSessionStatus('connected');
        await this.updatePhoneNumber();
      }

      if (connection === 'close') {
        const wasConnected = this.isConnected;
        this.isConnected = false;
        const statusCode = lastDisconnect?.error?.output?.statusCode;
        console.log(`Session ${this.sessionId} connection closed. Status code: ${statusCode}, wasConnected=${wasConnected}, error:`, lastDisconnect?.error);
        
        if (statusCode === DisconnectReason.loggedOut) {
          console.log(`Session ${this.sessionId} is logged out/expired.`);
          await this.updateSessionStatus('expired');
          await this.cleanAuth();
        } else if (statusCode === DisconnectReason.restartRequired || !wasConnected) {
          console.log(`Session ${this.sessionId} disconnected during handshake or requested restart (code: ${statusCode}). Reconnecting immediately (1s)...`);
          setTimeout(() => this.initialize(), 1000);
        } else {
          console.log(`Session ${this.sessionId} disconnected (code: ${statusCode}). Scheduling reconnect in 10 seconds...`);
          await this.updateSessionStatus('disconnected');
          setTimeout(() => this.initialize(), 10000);
        }
      }
    });

    this.sock.ev.on('messages.upsert', async ({ messages }) => {
      for (const msg of messages) {
        if (msg.key.fromMe && msg.message) {
          await this.updateMessageReceipt(msg.key.id, 'sent');
        }
      }
    });

    this.sock.ev.on('messages.update', async (updates) => {
      for (const update of updates) {
        if (update.status) {
          const mappedStatus = update.status === 3 ? 'delivered' : update.status === 4 ? 'read' : 'sent';
          await this.updateMessageReceipt(update.key.id, mappedStatus);
        }
      }
    });

    return this.sock;
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
    await db.execute(
      'UPDATE whatsapp_sessions SET status = ?, last_connected = CASE WHEN ? = \'connected\' THEN NOW() ELSE last_connected END, last_disconnected = CASE WHEN ? = \'disconnected\' THEN NOW() ELSE last_disconnected END WHERE session_id = ?',
      [status, status, status, this.sessionId]
    );
  }

  async updatePhoneNumber() {
    const phone = this.sock.user?.id?.split(':')[0];
    if (phone) {
      await db.execute(
        'UPDATE whatsapp_sessions SET phone_number = ? WHERE session_id = ?',
        [phone, this.sessionId]
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
    if (!this.isConnected) {
      throw new Error('Session is not connected to WhatsApp Web');
    }

    // Rate Limiting
    await this.rateLimiter.checkLimit();

    const formattedJid = to.includes('@s.whatsapp.net') ? to : `${to}@s.whatsapp.net`;

    // Anti-Ban compositions
    await this.banPrevention.randomizeStatus(this.sock);
    await this.banPrevention.simulateTyping(this.sock, formattedJid);

    let processedMessage = message;
    if (this.banPrevention.detectPatterns(message)) {
      processedMessage = this.banPrevention.rotatePhrases(message);
    }

    let payload = { text: processedMessage };
    if (options.image) {
      try {
        const axios = require('axios');
        console.log(`Downloading campaign image: ${options.image}`);
        const response = await axios.get(options.image, {
          responseType: 'arraybuffer',
          headers: {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
          }
        });
        payload = {
          image: Buffer.from(response.data),
          caption: processedMessage
        };
      } catch (imgErr) {
        console.error('Failed to download image, falling back to text message:', imgErr.message);
        payload = { text: processedMessage };
      }
    }

    const result = await this.sock.sendMessage(formattedJid, payload);
    
    // Track history
    this.banPrevention.trackMessageHistory(processedMessage, to);

    // Rate limit log
    await this.rateLimiter.logLimitHit(this.sessionId, 'send', to);

    return result;
  }

  async cleanAuth() {
    const authDir = `./auth_data/tenant_${this.tenantId}/session_${this.sessionId}`;
    const fs = require('fs').promises;
    try {
      await fs.rm(authDir, { recursive: true, force: true });
      console.log(`Successfully purged auth folder: ${authDir}`);
    } catch (e) {
      console.warn('Error purging session folder:', e.message);
    }
  }
}

module.exports = WhatsAppService;
