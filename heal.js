// heal.js
// Automated self-healing diagnostic daemon for WhatsApp Bulk Sender sessions
const fs = require('fs');
const path = require('path');
const http = require('http');
const { exec } = require('child_process');

// Load environment variables manually from .env
const envFile = path.join(__dirname, '.env');
if (fs.existsSync(envFile)) {
  const content = fs.readFileSync(envFile, 'utf8');
  content.split('\n').forEach(line => {
    const trimmed = line.trim();
    if (trimmed && !trimmed.startsWith('#') && trimmed.includes('=')) {
      const [key, ...valParts] = trimmed.split('=');
      process.env[key.trim()] = valParts.join('=').trim();
    }
  });
}

// Database configuration loaded
const mysql = require('mysql2/promise');
const dbConfig = {
  host: process.env.DB_HOST || '127.0.0.1',
  port: process.env.DB_PORT ? parseInt(process.env.DB_PORT) : 3306,
  user: process.env.DB_USER || 'root',
  password: process.env.DB_PASSWORD !== undefined ? process.env.DB_PASSWORD : '',
  database: process.env.DB_DATABASE || 'whatsapp_prod'
};

const LOG_FILE = path.join(__dirname, 'heal.log');

function log(msg) {
  const timestamp = new Date().toISOString();
  const line = `[${timestamp}] ${msg}\n`;
  console.log(line.trim());
  fs.appendFileSync(LOG_FILE, line);
}

// 1. Health check local Node Service on port 3000
function checkNodeServicePort() {
  return new Promise((resolve) => {
    const port = process.env.PORT || process.env.NODE_PORT || 3000;
    const req = http.get(`http://127.0.0.1:${port}/health`, (res) => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => {
        if (res.statusCode === 200) {
          resolve(true);
        } else {
          log(`Health check returned status code: ${res.statusCode}`);
          resolve(false);
        }
      });
    });
    
    req.on('error', (err) => {
      log(`Node service port check failed: ${err.message}`);
      resolve(false);
    });
    req.setTimeout(2000, () => {
      req.destroy();
      resolve(false);
    });
  });
}

// 2. Start the Node Service if offline
function startNodeService() {
  log('Attempting to restart Node Service...');
  
  // Try using PM2 first, fallback to raw background execution
  exec('pm2 restart whatsapp-api-service || pm2 start index.js --name whatsapp-api-service', (err, stdout, stderr) => {
    if (err) {
      log('PM2 restart failed, spawning raw background node process...');
      const out = fs.openSync(path.join(__dirname, 'node-service.log'), 'a');
      const errOut = fs.openSync(path.join(__dirname, 'node-service-error.log'), 'a');
      
      const child = exec('node index.js', {
        detached: true,
        stdio: ['ignore', out, errOut]
      });
      child.unref();
      log(`Node index.js spawned in background with PID: ${child.pid}`);
    } else {
      log(`PM2 restart successful: ${stdout.trim()}`);
    }
  });
}

// 3. Scan & Heal WhatsApp Sessions
async function healSessions() {
  let connection;
  try {
    connection = await mysql.createConnection(dbConfig);
    
    // Scan for sessions stuck in 'pending' or 'scanning' for over 1 minute
    const [sessions] = await connection.execute(
      `SELECT id, tenant_id, session_id, status, last_qr_generated 
       FROM whatsapp_sessions 
       WHERE status IN ('pending', 'scanning', 'disconnected')`
    );

    for (const session of sessions) {
      const authDir = path.join(__dirname, 'auth_data', `tenant_${session.tenant_id}`, `session_${session.session_id}`);
      const credsPath = path.join(authDir, 'creds.json');
      
      log(`Diagnosing session ${session.session_id} (status: ${session.status})...`);
      
      // Clean auth if creds.json is empty or invalid
      if (fs.existsSync(credsPath)) {
        try {
          const content = fs.readFileSync(credsPath, 'utf8');
          JSON.parse(content);
        } catch (e) {
          log(`Corrupted creds.json detected at ${credsPath}. Purging auth data...`);
          fs.rmSync(authDir, { recursive: true, force: true });
        }
      }

      // Re-trigger Node initialization via HTTP call
      log(`Self-healing: Re-triggering initialization for session: ${session.session_id}`);
      await triggerSessionInit(session.tenant_id, session.session_id);
    }
  } catch (err) {
    log(`Database healing error: ${err.message}`);
  } finally {
    if (connection) await connection.end();
  }
}

function triggerSessionInit(tenantId, sessionId) {
  return new Promise((resolve) => {
    const port = process.env.PORT || process.env.NODE_PORT || 3000;
    const payload = JSON.stringify({ tenant_id: tenantId, session_id: sessionId });
    
    const req = http.request({
      hostname: '127.0.0.1',
      port: port,
      path: '/api/session/init',
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Content-Length': Buffer.byteLength(payload)
      }
    }, (res) => {
      res.resume();
      resolve(true);
    });

    req.on('error', (err) => {
      log(`Failed to send session init trigger to Node: ${err.message}`);
      resolve(false);
    });

    req.write(payload);
    req.end();
  });
}

// Main healing run
async function run() {
  log('Starting Self-Healing Diagnostics Run...');
  const isAlive = await checkNodeServicePort();
  
  if (!isAlive) {
    startNodeService();
    // Wait 5 seconds for startup before diagnosing sessions
    await new Promise(resolve => setTimeout(resolve, 5000));
  }
  
  await healSessions();
  log('Self-Healing Diagnostics completed.');
}

// Run immediately if executed directly
if (require.main === module) {
  run();
}

module.exports = { run };
