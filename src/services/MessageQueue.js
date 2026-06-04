// node-service/src/services/MessageQueue.js
const { getRedisClient, checkRedisConnection } = require('../config/redis');

class MessageQueue {
  constructor() {
    this.localQueue = [];
  }

  async enqueue(queueName, job) {
    if (checkRedisConnection()) {
      const client = await getRedisClient();
      await client.rPush(queueName, JSON.stringify(job));
    } else {
      this.localQueue.push(job);
    }
  }

  async dequeue(queueName) {
    if (checkRedisConnection()) {
      const client = await getRedisClient();
      const data = await client.lPop(queueName);
      return data ? JSON.parse(data) : null;
    } else {
      return this.localQueue.shift() || null;
    }
  }

  async getLength(queueName) {
    if (checkRedisConnection()) {
      const client = await getRedisClient();
      return await client.lLen(queueName);
    } else {
      return this.localQueue.length;
    }
  }
}

module.exports = new MessageQueue();
