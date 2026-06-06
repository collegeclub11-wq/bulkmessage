const { Client } = require('ssh2');
const conn = new Client();

conn.on('ready', () => {
  console.log('Client :: ready');
  conn.exec("mysql -u u828453283_bulk -p'Sumit@787870' -e \"GRANT ALL PRIVILEGES ON u828453283_bulk.* TO 'u828453283_bulk'@'%' IDENTIFIED BY 'Sumit@787870'; FLUSH PRIVILEGES;\"", (err, stream) => {
    if (err) throw err;
    stream.on('close', (code, signal) => {
      console.log('Stream :: close :: code: ' + code + ', signal: ' + signal);
      conn.end();
    }).on('data', (data) => {
      console.log('STDOUT:\n' + data);
    }).stderr.on('data', (data) => {
      console.log('STDERR:\n' + data);
    });
  });
}).connect({
  host: '82.25.106.230',
  port: 65002,
  username: 'u828453283',
  password: 'Sumit@787870'
});
