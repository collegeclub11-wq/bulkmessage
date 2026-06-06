const https = require('https');

https.get('https://bulkmessage-production-4108.up.railway.app/health', (res) => {
  console.log('Status Code:', res.statusCode);
  let data = '';
  res.on('data', (chunk) => data += chunk);
  res.on('end', () => {
    console.log('Response Body:', data);
  });
}).on('error', (err) => {
  console.error('Error:', err.message);
});
