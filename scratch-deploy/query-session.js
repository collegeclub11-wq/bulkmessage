const mysql = require('mysql2/promise');
mysql.createConnection({
  host: '82.25.106.230',
  user: 'u828453283_bulk',
  password: 'Sumit@787870',
  database: 'u828453283_bulk'
}).then(async conn => {
  console.log('--- TENANTS ---');
  const [tenants] = await conn.execute("SELECT * FROM tenants WHERE id = 1");
  console.log(tenants);
  conn.end();
});
