const { Client } = require('ssh2'); 
const conn = new Client(); 
conn.on('ready', () => { 
  conn.exec('mysql -u u828453283_bulk -p"Sumit@787870" u828453283_bulk -e "SELECT session_id, status FROM whatsapp_sessions"', (err, stream) => { 
    if (err) throw err; 
    stream.on('close', () => conn.end()).on('data', (d) => console.log(String(d))).stderr.on('data', (d) => console.log(String(d))); 
  }); 
}).connect({host: '82.25.106.230', port: 65002, username: 'u828453283', password: 'Sumit@787870'});
