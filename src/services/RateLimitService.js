// node-service/src/services/RateLimitService.js
const db = require('../config/database');

class RateLimitService {
  constructor(tenantId) {
    this.tenantId = tenantId;
    this.limits = null;
  }

  async loadLimits() {
    const [rows] = await db.execute(
      'SELECT rate_limit_per_minute, rate_limit_per_hour, rate_limit_per_day FROM tenants WHERE id = ?',
      [this.tenantId]
    );
    if (rows && rows.length > 0) {
      this.limits = rows[0];
    }
  }

  async checkLimit() {
    if (!this.limits) {
      await this.loadLimits();
    }
    if (!this.limits) return true;

    // Check last minute limit
    const minCount = await this.getMessageCount(1);
    if (minCount >= this.limits.rate_limit_per_minute) {
      throw new Error(`Rate limit exceeded: ${this.limits.rate_limit_per_minute} messages/min`);
    }

    // Check last hour limit
    const hourCount = await this.getMessageCount(60);
    if (hourCount >= this.limits.rate_limit_per_hour) {
      throw new Error(`Rate limit exceeded: ${this.limits.rate_limit_per_hour} messages/hour`);
    }

    // Check daily limit
    const dayCount = await this.getDailyCount();
    if (dayCount >= this.limits.rate_limit_per_day) {
      throw new Error(`Rate limit exceeded: ${this.limits.rate_limit_per_day} messages/day`);
    }

    return true;
  }

  async getMessageCount(minutes) {
    const [rows] = await db.execute(
      'SELECT COUNT(*) as count FROM message_logs WHERE tenant_id = ? AND sent_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)',
      [this.tenantId, minutes]
    );
    return rows[0].count;
  }

  async getDailyCount() {
    const [rows] = await db.execute(
      'SELECT COUNT(*) as count FROM message_logs WHERE tenant_id = ? AND DATE(sent_at) = CURDATE()',
      [this.tenantId]
    );
    return rows[0].count;
  }

  async logLimitHit(sessionId, action, phoneNumber) {
    await db.execute(
      'INSERT INTO rate_limit_logs (tenant_id, session_id, action, phone_number) VALUES (?, ?, ?, ?)',
      [this.tenantId, sessionId, action, phoneNumber]
    );
  }
}

module.exports = RateLimitService;
