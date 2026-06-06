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
    await db.query('SELECT 1');
    res.json({ status: 'UP', database: 'connected' });
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
