const mysql = require('mysql2/promise');
mysql.createConnection({
  host: '82.25.106.230',
  user: 'u828453283_bulk',
  password: 'Sumit@787870',
  database: 'u828453283_bulk'
}).then(async conn => {
  console.log('--- WHATSAPP SESSIONS ---');
  const [sessions] = await conn.execute("SELECT id, tenant_id, session_id, phone_number, status FROM whatsapp_sessions");
  console.log(sessions);
  
  console.log('--- RECENT CAMPAIGNS ---');
  const [campaigns] = await conn.execute("SELECT id, tenant_id, campaign_name, status, total_contacts, sent_count, failed_count, pending_count, error_details FROM bulk_campaigns ORDER BY id DESC LIMIT 5");
  console.log(campaigns);
  
  console.log('--- CAMPAIGN CONTACTS ---');
  const [contacts] = await conn.execute("SELECT id, campaign_id, contact_id, status, attempt_count, error_message FROM campaign_contacts WHERE campaign_id = 8");
  console.log(contacts);
  
  console.log('--- MESSAGE LOGS ---');
  const [logs] = await conn.execute("SELECT id, campaign_id, contact_id, status, error_message, sent_at FROM message_logs WHERE campaign_id = 8");
  console.log(logs);
  
  conn.end();
});
