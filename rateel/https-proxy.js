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

/**
 * Validate and sanitize URL path to prevent SSRF attacks
 * Only allows paths that start with / and don't contain protocol or host
 */
function sanitizePath(urlPath) {
    // Parse the URL to extract only the path component
    try {
        const url = new URL(urlPath, `http://${LARAVEL_HOST}:${LARAVEL_PORT}`);

        // Prevent host/protocol injection - only allow same host
        if (url.hostname !== LARAVEL_HOST) {
            return null;
        }

        // Return only path and query string, not full URL
        return url.pathname + url.search;
    } catch (e) {
        // If URL parsing fails, reject the request
        return null;
    }
}

const server = https.createServer({ cert: SSL_CERT, key: SSL_KEY }, (req, res) => {
    // Sanitize the URL path to prevent SSRF
    const sanitizedPath = sanitizePath(req.url);

    if (!sanitizedPath) {
        console.error('SSRF attempt blocked:', req.url);
        res.writeHead(400);
        res.end('Bad Request: Invalid URL');
        return;
    }

    const options = {
        hostname: LARAVEL_HOST,
        port: LARAVEL_PORT,
        path: sanitizedPath,
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
