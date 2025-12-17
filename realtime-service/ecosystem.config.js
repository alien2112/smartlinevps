/**
 * PM2 Ecosystem Configuration
 * Production deployment configuration for SmartLine Real-time Service
 */

module.exports = {
  apps: [
    {
      name: 'smartline-realtime',
      script: './src/server.js',
      instances: process.env.WORKER_PROCESSES || 2,
      exec_mode: 'cluster',
      watch: false,
      max_memory_restart: '500M',
      env: {
        NODE_ENV: 'development',
        PORT: 3000
      },
      env_production: {
        NODE_ENV: 'production',
        PORT: 3000
      },
      error_file: './logs/pm2-error.log',
      out_file: './logs/pm2-out.log',
      log_date_format: 'YYYY-MM-DD HH:mm:ss Z',
      merge_logs: true,
      autorestart: true,
      max_restarts: 10,
      min_uptime: '10s',
      listen_timeout: 5000,
      kill_timeout: 5000
    }
  ]
};
