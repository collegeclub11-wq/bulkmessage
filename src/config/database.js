// node-service/src/config/database.js
const mysql = require('mysql2/promise');
const fs = require('fs');
const path = require('path');

// Manually load environment variables from root .env if present
const envPath = path.join(__dirname, '../../.env');
if (fs.existsSync(envPath)) {
  const envContent = fs.readFileSync(envPath, 'utf8');
  envContent.split('\n').forEach(line => {
    const trimmed = line.trim();
    if (trimmed && !trimmed.startsWith('#') && trimmed.includes('=')) {
      const [key, ...valueParts] = trimmed.split('=');
      const value = valueParts.join('=').trim();
      process.env[key.trim()] = value;
    }
  });
}

const pool = mysql.createPool({
  host: process.env.DB_HOST || '82.25.106.230',
  port: process.env.DB_PORT ? parseInt(process.env.DB_PORT) : 3306,
  user: process.env.DB_USER || 'u828453283_bulk',
  password: process.env.DB_PASSWORD !== undefined ? process.env.DB_PASSWORD : 'Sumit@787870',
  database: process.env.DB_DATABASE || 'u828453283_bulk',
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0,
  timezone: '+05:30'
});

module.exports = pool;
