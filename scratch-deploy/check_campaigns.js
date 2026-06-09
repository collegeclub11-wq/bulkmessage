const { Client } = require('ssh2');
const conn = new Client();
conn.on('ready', () => {
  conn.exec(`mysql -h 127.0.0.1 -u u828453283_bulk -p'Sumit@787870' u828453283_bulk -e "SELECT id, campaign_name, status, error_details FROM bulk_campaigns ORDER BY id DESC LIMIT 5;"`, (err, stream) => {
    if (err) throw err;
    stream.on('close', () => conn.end())
          .on('data', data => console.log(data.toString()))
          .stderr.on('data', data => console.error(data.toString()));
  });
}).connect({
  host: '82.25.106.230',
  port: 65002,
  username: 'u828453283',
  password: 'Sumit@787870'
});
