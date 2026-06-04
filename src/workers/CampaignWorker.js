// node-service/src/workers/CampaignWorker.js
const db = require('../config/database');
const { activeSessions } = require('../controllers/SessionController');

class CampaignWorker {
  constructor() {
    this.isPolling = false;
  }

  start() {
    setInterval(() => {
      this.pollAndProcess().catch(err => console.error('CampaignWorker iteration failed:', err.message));
    }, 10000); // Poll database every 10 seconds
    console.log('CampaignWorker background worker initialized.');
  }

  async pollAndProcess() {
    if (this.isPolling) return;
    this.isPolling = true;

    try {
      // Find one campaign that is pending or processing
      const [campaigns] = await db.execute(
        `SELECT bc.*, t.message as template_message, t.image_url 
         FROM bulk_campaigns bc 
         LEFT JOIN templates t ON bc.template_id = t.id 
         WHERE bc.status IN ('pending', 'processing') 
         ORDER BY bc.priority DESC, bc.id ASC LIMIT 1`
      );

      if (campaigns.length === 0) {
        this.isPolling = false;
        return;
      }

      const campaign = campaigns[0];

      // Retrieve first active connected WhatsApp session for this tenant
      const [sessions] = await db.execute(
        'SELECT session_id FROM whatsapp_sessions WHERE tenant_id = ? AND status = \'connected\' LIMIT 1',
        [campaign.tenant_id]
      );

      if (sessions.length === 0) {
        console.warn(`No connected session found for tenant ${campaign.tenant_id}. Campaign ${campaign.id} paused.`);
        await db.execute('UPDATE bulk_campaigns SET status = \'paused\', error_details = \'No connected WhatsApp session found\' WHERE id = ?', [campaign.id]);
        this.isPolling = false;
        return;
      }

      const sessionId = sessions[0].session_id;
      const key = `${campaign.tenant_id}_${sessionId}`;
      const session = activeSessions.get(key);

      if (!session || !session.isConnected) {
        console.warn(`Session ${sessionId} is registered as connected but active class instance is offline. Re-initializing...`);
        this.isPolling = false;
        return;
      }

      // Update campaign status to processing
      if (campaign.status === 'pending') {
        await db.execute('UPDATE bulk_campaigns SET status = \'processing\', start_time = NOW() WHERE id = ?', [campaign.id]);
      }

      // Load next pending contact for this campaign
      const [targets] = await db.execute(
        `SELECT cc.id as mapping_id, cc.attempt_count, c.id as contact_id, c.phone_number, c.name, c.custom_fields 
         FROM campaign_contacts cc 
         JOIN contacts c ON cc.contact_id = c.id 
         WHERE cc.campaign_id = ? AND cc.status = 'pending' LIMIT 1`,
        [campaign.id]
      );

      if (targets.length === 0) {
        // Campaign fully completed
        await db.execute('UPDATE bulk_campaigns SET status = \'completed\', end_time = NOW() WHERE id = ?', [campaign.id]);
        this.isPolling = false;
        return;
      }

      const target = targets[0];

      // Enforce Tenant scoping check
      const [isBlocked] = await db.execute(
        'SELECT 1 FROM blocklist WHERE tenant_id = ? AND phone_number = ?',
        [campaign.tenant_id, target.phone_number]
      );

      if (isBlocked.length > 0) {
        await db.execute('UPDATE campaign_contacts SET status = \'failed\', error_message = \'Phone number is blocklisted\' WHERE id = ?', [target.mapping_id]);
        await this.incrementCampaignCounts(campaign.id, false);
        this.isPolling = false;
        return;
      }

      // Build message string and interpolate variables
      let personalizedMsg = campaign.template_message;
      personalizedMsg = personalizedMsg.replace(/\{\{name\}\}/g, target.name || '');
      personalizedMsg = personalizedMsg.replace(/\{\{phone\}\}/g, target.phone_number);

      const customFields = typeof target.custom_fields === 'string' ? JSON.parse(target.custom_fields || '{}') : (target.custom_fields || {});
      for (const [vKey, vVal] of Object.entries(customFields)) {
        const regex = new RegExp(`\\{\\{${vKey}\\}\\}`, 'g');
        personalizedMsg = personalizedMsg.replace(regex, vVal || '');
      }

      // Calculate delay before sending
      const delayMs = await session.banPrevention.calculateDynamicDelay();
      console.log(`Pacing Campaign ${campaign.id}: waiting ${delayMs}ms before dispatch to ${target.phone_number}`);
      await new Promise(resolve => setTimeout(resolve, delayMs));

      try {
        const result = await session.sendRawMessage(target.phone_number, personalizedMsg, { image: campaign.image_url });
        
        // Update campaign contact status
        await db.execute(
          'UPDATE campaign_contacts SET status = \'sent\', attempt_count = ?, sent_at = NOW(), message_id = ? WHERE id = ?',
          [target.attempt_count + 1, result.key.id, target.mapping_id]
        );

        // Log record
        await db.execute(
          `INSERT INTO message_logs (tenant_id, campaign_id, contact_id, session_id, phone_number, message_content, status, whatsapp_message_id, sent_at) 
           VALUES (?, ?, ?, ?, ?, ?, 'sent', ?, NOW())`,
          [campaign.tenant_id, campaign.id, target.contact_id, sessionId, target.phone_number, personalizedMsg, result.key.id]
        );

        await this.incrementCampaignCounts(campaign.id, true);

      } catch (sendErr) {
        console.error(`Campaign message transmission failed for ${target.phone_number}:`, sendErr.message);

        await db.execute(
          'UPDATE campaign_contacts SET status = \'failed\', attempt_count = ?, error_message = ? WHERE id = ?',
          [target.attempt_count + 1, sendErr.message, target.mapping_id]
        );

        await db.execute(
          `INSERT INTO message_logs (tenant_id, campaign_id, contact_id, session_id, phone_number, message_content, status, error_message, sent_at) 
           VALUES (?, ?, ?, ?, ?, ?, 'failed', ?, NOW())`,
          [campaign.tenant_id, campaign.id, target.contact_id, sessionId, target.phone_number, personalizedMsg, sendErr.message]
        );

        await this.incrementCampaignCounts(campaign.id, false);
      }

    } catch (e) {
      console.error('Critical failure in CampaignWorker loop:', e.message);
    }

    this.isPolling = false;
  }

  async incrementCampaignCounts(campaignId, isSuccess) {
    const field = isSuccess ? 'sent_count' : 'failed_count';
    await db.execute(
      `UPDATE bulk_campaigns 
       SET ${field} = ${field} + 1, 
           pending_count = CASE WHEN pending_count > 0 THEN pending_count - 1 ELSE 0 END 
       WHERE id = ?`,
      [campaignId]
    );
  }
}

module.exports = CampaignWorker;
