// node-service/src/controllers/MessageController.js
const { activeSessions } = require('./SessionController');

class MessageController {
  static async send(req, res) {
    const { tenant_id, session_id, to, message, options } = req.body;

    if (!tenant_id || !session_id || !to || !message) {
      return res.status(400).json({ error: 'Missing tenant_id, session_id, to or message content' });
    }

    const key = `${tenant_id}_${session_id}`;
    const session = activeSessions.get(key);

    if (!session || !session.isConnected) {
      return res.status(400).json({ error: 'Selected WhatsApp session is not online' });
    }

    try {
      const result = await session.sendRawMessage(to, message, options || {});
      return res.json({ success: true, messageId: result.key.id });
    } catch (e) {
      return res.status(500).json({ error: 'Failed to send message: ' + e.message });
    }
  }
}

module.exports = MessageController;
//
