// node-service/src/controllers/SessionController.js
const WhatsAppService = require('../services/WhatsAppService');

const activeSessions = new Map();

class SessionController {
  static getActiveSession(tenantId, sessionId) {
    const key = `${tenantId}_${sessionId}`;
    return activeSessions.get(key);
  }

  static async initSession(req, res) {
    const { tenant_id, session_id } = req.body;
    console.log(`initSession request received for tenant: ${tenant_id}, session: ${session_id}`);

    if (!tenant_id || !session_id) {
      console.warn('Missing tenant_id or session_id parameter context');
      return res.status(400).json({ error: 'Missing tenant_id or session_id parameter context' });
    }

    const key = `${tenant_id}_${session_id}`;
    if (activeSessions.has(key)) {
      const existing = activeSessions.get(key);
      if (existing.isConnected) {
        console.log(`Session already in Map list and is connected: ${key}`);
        return res.json({ message: 'Session is already starting or active', session_id });
      } else {
        console.log(`Session ${key} exists in Map but is not connected. Closing and clearing to re-init.`);
        try {
          if (existing.sock) {
            existing.sock.end();
            existing.sock.ev.removeAllListeners();
          }
        } catch (e) {
          console.warn('Error closing existing socket in Map reset:', e.message);
        }
        activeSessions.delete(key);
      }
    }

    console.log(`Creating new WhatsAppService instance for key: ${key}`);
    const service = new WhatsAppService(tenant_id, session_id);
    activeSessions.set(key, service);

    try {
      // Start Baileys initialization asynchronously
      service.initialize().catch(err => {
        console.error(`Session failure in async init for ${key}:`, err);
        activeSessions.delete(key);
      });

      return res.json({ message: 'Session bootstrap initiated', session_id });
    } catch (e) {
      console.error(`InitSession exception for ${key}:`, e);
      activeSessions.delete(key);
      return res.status(500).json({ error: 'Failed to initialize session: ' + e.message });
    }
  }

  static async getStatus(req, res) {
    const { tenant_id, session_id } = req.query;
    const key = `${tenant_id}_${session_id}`;
    const session = activeSessions.get(key);

    if (!session) {
      return res.status(404).json({ status: 'not_initialized' });
    }

    return res.json({
      status: session.isConnected ? 'connected' : 'connecting',
      queueLength: session.messageQueue.length
    });
  }
}

module.exports = {
  SessionController,
  activeSessions
};
