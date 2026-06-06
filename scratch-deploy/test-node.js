const {Client} = require('ssh2'); 
const conn = new Client(); 
conn.on('ready', () => { 
  conn.exec('node -v', (err, stream) => { 
    stream.on('data', d => console.log(d.toString())).on('close', () => conn.end()); 
  }); 
}).connect({host: '82.25.106.230', port: 65002, username: 'u828453283', password: 'Sumit@787870'});
