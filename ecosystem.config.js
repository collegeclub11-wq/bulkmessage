module.exports = {
  apps: [
    {
      name: 'whatsapp-api-service',
      script: './index.js',
      instances: 1,
      autorestart: true,
      watch: false,
      max_memory_restart: '1G',
      env: {
        NODE_ENV: 'development',
        NODE_PORT: 3000
      },
      env_production: {
        NODE_ENV: 'production',
        NODE_PORT: 3000
      }
    },
    {
      name: 'whatsapp-worker-service',
      script: './worker.js',
      instances: 1,
      autorestart: true,
      watch: false,
      max_memory_restart: '1G',
      env: {
        NODE_ENV: 'production'
      }
    }
  ]
};
