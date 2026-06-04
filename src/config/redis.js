// node-service/src/config/redis.js
const { createClient } = require('redis');

let client = null;
let isConnected = false;

async function getRedisClient() {
  if (client) return client;

  const host = process.env.REDIS_HOST || '127.0.0.1';
  const port = process.env.REDIS_PORT || 6379;

  client = createClient({
    url: `redis://${host}:${port}`
  });

  client.on('error', (err) => {
    console.warn('Redis client error, falling back to local memory queue:', err.message);
    isConnected = false;
  });

  client.on('connect', () => {
    console.log('Redis client successfully connected');
    isConnected = true;
  });

  try {
    await client.connect();
  } catch (e) {
    console.warn('Could not establish Redis connection. Local fallback enabled.');
    isConnected = false;
  }

  return client;
}

function checkRedisConnection() {
  return isConnected;
}

module.exports = {
  getRedisClient,
  checkRedisConnection
};
