// node-service/src/services/BanPreventionService.js
const db = require('../config/database');

class BanPreventionService {
  constructor(tenantId) {
    this.tenantId = tenantId;
    this.messageHistory = [];
  }

  trackMessageHistory(message, phoneNumber) {
    this.messageHistory.push({
      message,
      phoneNumber,
      timestamp: Date.now()
    });
    // Keep only last 100 entries
    if (this.messageHistory.length > 100) {
      this.messageHistory.shift();
    }
  }

  async calculateDynamicDelay(sessionAgeDays = 30) {
    const [rows] = await db.execute(
      `SELECT COUNT(*) as count FROM message_logs 
       WHERE tenant_id = ? AND sent_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)`,
      [this.tenantId]
    );
    const hourlySent = rows[0]?.count || 0;

    // Standard variable delay (2-15 seconds) base
    let minDelay = 2000;
    let maxDelay = 8000;

    if (sessionAgeDays < 7) {
      // New account: 15-30 sec
      minDelay = 15000;
      maxDelay = 30000;
    } else if (hourlySent > 200) {
      // High velocity load: 10-20 sec
      minDelay = 10000;
      maxDelay = 20000;
    } else if (hourlySent > 50) {
      // Moderate velocity load: 5-12 sec
      minDelay = 5000;
      maxDelay = 12000;
    }

    // Return randomized delay in ms
    return Math.floor(Math.random() * (maxDelay - minDelay + 1)) + minDelay;
  }

  detectPatterns(message) {
    // Check if the exact message has been sent consecutively
    const len = this.messageHistory.length;
    if (len === 0) return false;
    const lastSent = this.messageHistory[len - 1];
    return lastSent.message === message;
  }

  rotatePhrases(message) {
    // Appends micro variations or space characters dynamically
    const variations = [
      '', 
      ' ', 
      '..', 
      ' \u200B', // zero-width space
      '\n', 
      '!'
    ];
    const randomIndex = Math.floor(Math.random() * variations.length);
    return message + variations[randomIndex];
  }

  async simulateTyping(sock, jid, durationMs = 1500) {
    try {
      await sock.sendPresenceUpdate('composing', jid);
      await new Promise(resolve => setTimeout(resolve, durationMs));
      await sock.sendPresenceUpdate('paused', jid);
    } catch (e) {
      console.warn('Simulation of presence composing failed:', e.message);
    }
  }

  async markAsRead(sock, key) {
    try {
      // 30% chance to simulate reading to mimic human habits
      if (Math.random() < 0.3) {
        await sock.readMessages([key]);
      }
    } catch (e) {
      console.warn('Read receipt marking failed:', e.message);
    }
  }

  async randomizeStatus(sock) {
    try {
      // Occasionally set presence state to offline/unavailable
      if (Math.random() < 0.1) {
        await sock.sendPresenceUpdate('unavailable');
      } else {
        await sock.sendPresenceUpdate('available');
      }
    } catch (e) {
      console.warn('Presence status randomization failed:', e.message);
    }
  }

  async getAverageLatency() {
    const [rows] = await db.execute(
      `SELECT AVG(TIMESTAMPDIFF(SECOND, sent_at, delivered_at)) as avg_latency 
       FROM message_logs 
       WHERE tenant_id = ? AND delivered_at IS NOT NULL 
       AND sent_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)`,
      [this.tenantId]
    );
    return rows[0]?.avg_latency || 0;
  }
}

module.exports = BanPreventionService;
