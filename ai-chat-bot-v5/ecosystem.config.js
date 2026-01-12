/**
 * PM2 Ecosystem Configuration
 * Production deployment for SmartLine AI Chatbot V3
 */

module.exports = {
    apps: [
        {
            name: 'smartline-chatbot',
            script: './chat.js',
            instances: 1,  // Single instance - state is in-memory
            exec_mode: 'fork',
            watch: false,
            max_memory_restart: '300M',

            // Environment variables
            env: {
                NODE_ENV: 'development',
                PORT: 3001
            },
            env_production: {
                NODE_ENV: 'production',
                PORT: 3001
            },

            // Logging
            error_file: './logs/pm2-error.log',
            out_file: './logs/pm2-out.log',
            log_date_format: 'YYYY-MM-DD HH:mm:ss Z',
            merge_logs: true,

            // Restart behavior
            autorestart: true,
            max_restarts: 10,
            min_uptime: '10s',
            restart_delay: 4000, // Wait 4s between restarts

            // Timeouts
            listen_timeout: 8000,
            kill_timeout: 5000,

            // Graceful shutdown
            shutdown_with_message: true,
            wait_ready: true,

            // Health monitoring
            exp_backoff_restart_delay: 100,

            // Cron restart (optional - restart daily at 4am for memory cleanup)
            // cron_restart: '0 4 * * *',

            // Source maps for better error traces
            source_map_support: true
        }
    ],

    // PM2 deploy configuration (optional)
    deploy: {
        production: {
            user: 'www-data',
            host: 'your-server.com',
            ref: 'origin/main',
            repo: 'git@github.com:yourrepo/smartline.git',
            path: '/var/www/laravel/smartlinevps/ai-chat-bot-v3',
            'pre-deploy-local': '',
            'post-deploy': 'npm install && pm2 reload ecosystem.config.js --env production',
            'pre-setup': ''
        }
    }
};
