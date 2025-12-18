/**
 * Simple HTTPS Proxy for Laravel Development
 * Run with: node https-proxy.js
 */

const https = require('https');
const http = require('http');
const fs = require('fs');
const path = require('path');

const SSL_CERT = fs.readFileSync(path.join(__dirname, 'ssl', 'cert.pem'));
const SSL_KEY = fs.readFileSync(path.join(__dirname, 'ssl', 'key.pem'));

const LARAVEL_HOST = '127.0.0.1';
const LARAVEL_PORT = 8000;  // Laravel runs internally on 8000
const HTTPS_PORT = 8080;    // HTTPS proxy runs on 8080
const BIND_IP = '192.168.8.158';

const server = https.createServer({ cert: SSL_CERT, key: SSL_KEY }, (req, res) => {
    const options = {
        hostname: LARAVEL_HOST,
        port: LARAVEL_PORT,
        path: req.url,
        method: req.method,
        headers: {
            ...req.headers,
            host: `${LARAVEL_HOST}:${LARAVEL_PORT}`
        }
    };

    const proxyReq = http.request(options, (proxyRes) => {
        res.writeHead(proxyRes.statusCode, proxyRes.headers);
        proxyRes.pipe(res);
    });

    proxyReq.on('error', (err) => {
        console.error('Proxy error:', err.message);
        res.writeHead(502);
        res.end('Bad Gateway');
    });

    req.pipe(proxyReq);
});

server.listen(HTTPS_PORT, BIND_IP, () => {
    console.log(`🔐 HTTPS Proxy running at https://${BIND_IP}:${HTTPS_PORT}`);
    console.log(`   Forwarding to http://${LARAVEL_HOST}:${LARAVEL_PORT}`);
    console.log('');
    console.log('   Make sure Laravel is running:');
    console.log(`   php artisan serve --host=${LARAVEL_HOST} --port=${LARAVEL_PORT}`);
});
