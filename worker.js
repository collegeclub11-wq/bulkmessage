// node-service/worker.js
const CampaignWorker = require('./src/workers/CampaignWorker');
const ReconnectWorker = require('./src/workers/ReconnectWorker');

console.log('Starting standalone worker daemon process...');

const campaignWorker = new CampaignWorker();
campaignWorker.start();

// Recover sessions periodically
ReconnectWorker.recoverSessions();
setInterval(() => {
  ReconnectWorker.recoverSessions();
}, 60000);
