/**
 * PM2 Ecosystem Configuration
 * Production deployment for SmartLine AI Chatbot V3
 */

module.exports = {
    apps: [
        {
            name: 'smartline-chatbot',
            script: './chat.js',
            instances: 1,  // Single instance - chatbot doesn't need clustering
            exec_mode: 'fork',
            watch: false,
            max_memory_restart: '300M',
            env: {
                NODE_ENV: 'development',
                PORT: 3001
            },
            env_production: {
                NODE_ENV: 'production',
                PORT: 3001
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
