// node-service/src/config/database.js
const mysql = require('mysql2/promise');

const pool = mysql.createPool({
  host: process.env.DB_HOST || '82.25.106.230',
  port: process.env.DB_PORT ? parseInt(process.env.DB_PORT) : 3306,
  user: process.env.DB_USER || 'u828453283_bulk',
  password: process.env.DB_PASSWORD !== undefined ? process.env.DB_PASSWORD : 'Sumit@787870',
  database: process.env.DB_DATABASE || 'u828453283_bulk',
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0
});

module.exports = pool;
