const https = require('https');

const sessions = [
  'session_20_6a23a82d06167',
  'session_20_6a23ab9d95b79',
  'session_20_6a23f0002151b',
  'session_20_6a23f0065a035',
  'session_1_6a240dd0e71c0',
  'session_2_6a249ca9b34f6'
];

function getStatus(sessionId) {
  return new Promise((resolve) => {
    https.get(`https://whatsappbackend-production-9e33.up.railway.app/status?sessionId=${sessionId}`, (res) => {
      let data = '';
      res.on('data', (chunk) => data += chunk);
      res.on('end', () => {
        try {
          resolve(JSON.parse(data));
        } catch (e) {
          resolve({ error: 'Parse error', raw: data });
        }
      });
    }).on('error', (err) => {
      resolve({ error: err.message });
    });
  });
}

(async () => {
  for (const session of sessions) {
    const status = await getStatus(session);
    console.log(`Session: ${session} ->`, status);
  }
})();
