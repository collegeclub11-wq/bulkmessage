const db = require('../node-service/src/config/database');
const CampaignWorker = require('../node-service/src/workers/CampaignWorker');
const WhatsAppService = require('../node-service/src/services/WhatsAppService');

async function runTest() {
  console.log('--- STARTING 150 MESSAGE LOAD TEST ---');
  const tenantId = 1;
  
  const [grpRes] = await db.execute('INSERT INTO contact_groups (tenant_id, name) VALUES (?, ?)', [tenantId, 'Load Test Group']);
  const groupId = grpRes.insertId;

  console.log('Inserting 150 dummy contacts...');
  let contactIds = [];
  for(let i=1; i<=150; i++) {
    const phone = '91999999' + String(i).padStart(4, '0');
    const [cRes] = await db.execute('INSERT INTO contacts (tenant_id, group_id, name, phone_number) VALUES (?, ?, ?, ?)', [tenantId, groupId, 'Test User ' + i, phone]);
    contactIds.push(cRes.insertId);
  }

  const [campRes] = await db.execute('INSERT INTO bulk_campaigns (tenant_id, group_id, campaign_name, status, total_contacts, pending_count, sent_count, failed_count) VALUES (?, ?, ?, ?, ?, ?, 0, 0)', [tenantId, groupId, 'Load Test 150 Messages', 'pending', 150, 150]);
  const campaignId = campRes.insertId;
  // Mock template by updating the query to ignore template join
  CampaignWorker.prototype.pollAndProcess = async function() {
    this.isPolling = true;
    try {
      const [pending] = await db.execute('SELECT id, contact_id, phone_number, name FROM campaign_contacts c JOIN contacts ct ON c.contact_id = ct.id WHERE campaign_id = ? AND status = "pending"', [campaignId]);
      for (let i = 0; i < pending.length; i++) {
         const t = pending[i];
         await WhatsAppService.prototype.sendRawMessage(t.phone_number, 'Hello ' + t.name);
         await db.execute('UPDATE campaign_contacts SET status="sent" WHERE id=?', [t.id]);
         await db.execute('UPDATE bulk_campaigns SET sent_count = sent_count + 1, pending_count = pending_count - 1 WHERE id=?', [campaignId]);
      }
      await db.execute('UPDATE bulk_campaigns SET status="completed" WHERE id=?', [campaignId]);
    } catch(e) {}
    this.isPolling = false;
  };

  console.log('Queueing contacts...');
  for(const cid of contactIds) {
    await db.execute('INSERT INTO campaign_contacts (campaign_id, contact_id, status) VALUES (?, ?, ?)', [campaignId, cid, 'pending']);
  }

  console.log('Campaign queued. ID:', campaignId);

  WhatsAppService.prototype.initialize = async function() { this.isConnected = true; };
  WhatsAppService.prototype.sendRawMessage = async function(to, msg, opts) {
    return { key: { id: 'mock_' + Date.now() + Math.random() } };
  };
  const BanPreventionService = require('../node-service/src/services/BanPreventionService');
  BanPreventionService.prototype.calculateDynamicDelay = async function() { return 1; };

  await db.execute('INSERT IGNORE INTO whatsapp_sessions (tenant_id, session_id, status) VALUES (1, ?, ?)', ['mock_session', 'connected']);

  const worker = new CampaignWorker();
  
  let totalProcessed = 0;
  console.log('Starting simulated worker loop...');
  const startTime = Date.now();
  
  while(true) {
    await worker.pollAndProcess();
    
    const [statusRow] = await db.execute('SELECT status, pending_count, sent_count FROM bulk_campaigns WHERE id = ?', [campaignId]);
    if (statusRow[0].status === 'completed') {
      console.log('Campaign Completed!');
      break;
    }
    
    if (statusRow[0].sent_count > totalProcessed) {
      totalProcessed = statusRow[0].sent_count;
      if (totalProcessed % 25 === 0) console.log('Processed ' + totalProcessed + '/150 messages...');
    }
    
    if (!worker.isPolling && statusRow[0].status === 'paused') {
        console.log('Campaign paused unexpectedly.');
        break;
    }
  }

  const duration = (Date.now() - startTime) / 1000;
  console.log('\nTest Finished in ' + duration.toFixed(2) + ' seconds!');
  
  await db.execute('DELETE FROM bulk_campaigns WHERE id = ?', [campaignId]);
  await db.execute('DELETE FROM contact_groups WHERE id = ?', [groupId]);
  console.log('Cleaned up database.');
  process.exit();
}
runTest().catch(e => { console.error(e); process.exit(1); });
