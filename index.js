// node-service/index.js
const express = require('express');
const http = require('http');
const { Server } = require('socket.io');
const { SessionController } = require('./src/controllers/SessionController');
const MessageController = require('./src/controllers/MessageController');
const CampaignWorker = require('./src/workers/CampaignWorker');
const ReconnectWorker = require('./src/workers/ReconnectWorker');
const db = require('./src/config/database');

const app = express();
app.use(express.json());

// Enable CORS
app.use((req, res, next) => {
  res.header('Access-Control-Allow-Origin', '*');
  res.header('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept');
  next();
});

// HTTP REST Endpoints
app.post('/api/session/init', SessionController.initSession);
app.get('/api/session/status', SessionController.getStatus);
app.post('/api/message/send', MessageController.send);

// Health check
app.get('/health', async (req, res) => {
  try {
    const [dbResult] = await db.query('SELECT DATABASE() as db, USER() as user');
    const dbName = dbResult[0]?.db || 'unknown';
    const dbUser = dbResult[0]?.user || 'unknown';
    
    let sessionCount = 0;
    try {
      const [sessions] = await db.query('SELECT COUNT(*) as count FROM whatsapp_sessions');
      sessionCount = sessions[0]?.count || 0;
    } catch (err) {
      sessionCount = 'error: ' + err.message;
    }

    let baileysVersion = 'unknown';
    try {
      baileysVersion = require('@whiskeysockets/baileys/package.json').version;
    } catch (err) {}
    
    let waVersion = 'unknown';
    try {
      const { fetchLatestBaileysVersion } = require('@whiskeysockets/baileys');
      const latest = await fetchLatestBaileysVersion();
      waVersion = latest.version.join('.');
    } catch (err) {
      waVersion = 'error: ' + err.message;
    }

    // Safely extract pool host
    let dbHost = 'unknown';
    if (db.pool && db.pool.config && db.pool.config.connectionConfig) {
      dbHost = db.pool.config.connectionConfig.host;
    } else if (db.config && db.config.connectionConfig) {
      dbHost = db.config.connectionConfig.host;
    }

    res.json({ 
      status: 'UP', 
      database: 'connected',
      dbHost,
      dbName,
      dbUser,
      sessionCount,
      baileysVersion,
      waVersion
    });
  } catch (e) {
    res.status(500).json({ status: 'DOWN', database: e.message });
  }
});

const PORT = process.env.PORT || process.env.NODE_PORT || 3000;
const server = http.createServer(app);

// WebSocket Setup
const io = new Server(server, {
  cors: {
    origin: '*',
    methods: ['GET', 'POST']
  }
});

io.on('connection', (socket) => {
  console.log('Socket client connected:', socket.id);
  
  socket.on('join_session', (room) => {
    socket.join(room);
    console.log(`Socket joined room: ${room}`);
  });
  
  socket.on('disconnect', () => {
    console.log('Socket client disconnected:', socket.id);
  });
});

// Attach io to global context for socket notifications
global.io = io;

server.listen(PORT, () => {
  console.log(`Node messaging service listening on port ${PORT}`);
  
  // Start Campaign Background polling process
  const campaignWorker = new CampaignWorker();
  campaignWorker.start();

  // Boot restore sessions
  ReconnectWorker.recoverSessions();

  // Start Self-Healing diagnostics loop (every 30 seconds)
  const selfHeal = require('./heal');
  setInterval(() => {
    selfHeal.run().catch(e => console.error('Self-healing error:', e.message));
  }, 30000);
});
