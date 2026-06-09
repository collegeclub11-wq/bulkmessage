const { Client } = require('ssh2'); 
const conn = new Client(); 
conn.on('ready', () => { 
  conn.exec('mysql -u u828453283_bulk -pSumit@787870 u828453283_bulk -e "SELECT id, contact_id, status, error_message FROM campaign_contacts ORDER BY id DESC LIMIT 5;"', (err, stream) => { 
    if (err) throw err; 
    stream.on('close', () => { conn.end(); })
          .on('data', (data) => { console.log(data.toString()); })
          .stderr.on('data', (data) => { console.log('STDERR: ' + data); }); 
  }); 
}).connect({ 
  host: '82.25.106.230', 
  port: 22, 
  username: 'u828453283', 
  password: 'Sumit@123_()_+{}' 
});
