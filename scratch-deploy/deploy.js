const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
const { Client } = require('ssh2');

const remoteConfig = {
  host: '82.25.106.230',
  port: 65002,
  username: 'u828453283',
  password: 'Sumit@787870'
};

const dbConfig = {
  host: '127.0.0.1',
  user: 'u828453283_bulk',
  database: 'u828453283_bulk',
  password: 'Sumit@787870'
};

const localRoot = path.dirname(__dirname);
const buildDir = path.join(__dirname, 'build');
const zipFile = path.join(__dirname, 'deploy.zip');

// Recursive copy helper
function copyDirSync(src, dest) {
  fs.mkdirSync(dest, { recursive: true });
  const entries = fs.readdirSync(src, { withFileTypes: true });

  for (let entry of entries) {
    const srcPath = path.join(src, entry.name);
    const destPath = path.join(dest, entry.name);

    if (entry.isDirectory()) {
      if (entry.name === 'node_modules' || entry.name === '.git' || entry.name === 'scratch-deploy') continue;
      copyDirSync(srcPath, destPath);
    } else {
      fs.copyFileSync(srcPath, destPath);
    }
  }
}

console.log('Step 1: Preparing local build directory...');
if (fs.existsSync(buildDir)) {
  fs.rmSync(buildDir, { recursive: true, force: true });
}
fs.mkdirSync(buildDir, { recursive: true });

// Copy frontend contents directly to build root
console.log('Copying frontend assets...');
copyDirSync(path.join(localRoot, 'frontend'), buildDir);

// Copy backend-php folder to build/backend-php
console.log('Copying backend-php files...');
copyDirSync(path.join(localRoot, 'backend-php'), path.join(buildDir, 'backend-php'));

// Write remote .env file for production PHP
console.log('Writing production .env configuration...');
const productionEnv = `DB_HOST=${dbConfig.host}
DB_PORT=3306
DB_DATABASE=${dbConfig.database}
DB_USER=${dbConfig.user}
DB_PASSWORD=${dbConfig.password}
SECRET_KEY=demo_super_secret_jwt_key_default_value_123_abc
NODE_HOST=https://bulkmessage-production-4108.up.railway.app
NODE_PORT=
`;
fs.writeFileSync(path.join(buildDir, '.env'), productionEnv);

// Create deployment zip
console.log('Step 2: Zipping build files...');
if (fs.existsSync(zipFile)) {
  fs.unlinkSync(zipFile);
}

try {
  execSync(`powershell -Command "Compress-Archive -Path '${buildDir}\\*' -DestinationPath '${zipFile}' -Force"`);
  console.log('Zip file created successfully at:', zipFile);
} catch (zipErr) {
  console.error('Failed to create zip file:', zipErr.message);
  process.exit(1);
}

// Establish SSH connection
console.log('Step 3: Connecting to Hostinger server via SSH...');
const conn = new Client();

conn.on('ready', () => {
  console.log('SSH connection established successfully.');
  
  conn.sftp((err, sftp) => {
    if (err) throw err;
    
    const remotePath = '/home/u828453283/domains/bulk.tezikaro.com/public_html';
    
    console.log('SFTP session opened. Uploading files...');
    
    // Upload files
    let uploadCount = 0;
    const filesToUpload = [
      { local: zipFile, remote: `${remotePath}/deploy.zip` },
      { local: path.join(localRoot, 'database', 'schema.sql'), remote: `${remotePath}/schema.sql` },
      { local: path.join(localRoot, 'database', 'seeds', 'demo_data.sql'), remote: `${remotePath}/demo_data.sql` }
    ];
    
    filesToUpload.forEach(file => {
      console.log(`Uploading ${path.basename(file.local)} to ${file.remote}...`);
      sftp.fastPut(file.local, file.remote, {}, (uploadErr) => {
        if (uploadErr) throw uploadErr;
        console.log(`Finished uploading ${path.basename(file.local)}.`);
        
        uploadCount++;
        if (uploadCount === filesToUpload.length) {
          console.log('All files uploaded successfully.');
          runRemoteCommands();
        }
      });
    });
  });
  
  function runRemoteCommands() {
    console.log('Step 4: Executing remote database and file operations...');
    const remotePath = '/home/u828453283/domains/bulk.tezikaro.com/public_html';
    
    // Database credentials and unzip execution
    const commands = [
      `mysql -u ${dbConfig.user} -p'${dbConfig.password}' ${dbConfig.database} < ${remotePath}/schema.sql`,
      `mysql -u ${dbConfig.user} -p'${dbConfig.password}' ${dbConfig.database} < ${remotePath}/demo_data.sql`,
      `unzip -o ${remotePath}/deploy.zip -d ${remotePath}/`,
      `rm -f ${remotePath}/deploy.zip ${remotePath}/schema.sql ${remotePath}/demo_data.sql ${remotePath}/default.php`
    ];
    
    const fullCommand = commands.join(' && ');
    
    conn.exec(fullCommand, (cmdErr, stream) => {
      if (cmdErr) throw cmdErr;
      
      stream.on('close', (code, signal) => {
        console.log(`Commands completed with exit code: ${code}`);
        conn.end();
        console.log('🚀 DEPLOYMENT COMPLETED SUCCESSFULLY!');
      }).on('data', (data) => {
        console.log('STDOUT: ' + data);
      }).stderr.on('data', (data) => {
        console.log('STDERR: ' + data);
      });
    });
  }
}).on('error', (connErr) => {
  console.error('SSH Connection failed:', connErr.message);
}).connect(remoteConfig);
