// node-service/src/workers/ReconnectWorker.js
const db = require('../config/database');
const WhatsAppService = require('../services/WhatsAppService');
const { activeSessions } = require('../controllers/SessionController');

class ReconnectWorker {
  static async recoverSessions() {
    console.log('ReconnectWorker scan started: recovering disconnected or pending sessions...');
    try {
      // Find sessions registered as connecting, connected, pending, scanning, or disconnected
      const [sessions] = await db.execute(
        'SELECT tenant_id, session_id FROM whatsapp_sessions WHERE status IN (\'connecting\', \'connected\', \'disconnected\', \'pending\', \'scanning\')'
      );

      for (const row of sessions) {
        const key = `${row.tenant_id}_${row.session_id}`;
        if (!activeSessions.has(key)) {
          console.log(`Recovering session: ${row.session_id} for tenant ${row.tenant_id}`);
          const service = new WhatsAppService(row.tenant_id, row.session_id);
          activeSessions.set(key, service);
          
          service.initialize().catch(err => {
            console.error(`Automatic recovery failed for ${row.session_id}:`, err.message);
            activeSessions.delete(key);
          });
        }
      }
    } catch (e) {
      console.error('ReconnectWorker error:', e.message);
    }
  }
}

module.exports = ReconnectWorker;
