// ============================================
// 🔐 ADMIN AUTHENTICATION MIDDLEWARE
// ============================================

/**
 * Admin API Key Authentication Middleware
 * Protects all /admin/* endpoints
 */
function adminAuth(req, res, next) {
    const apiKey = req.headers['x-api-key'] || req.headers['authorization']?.replace('Bearer ', '');
    const validApiKey = process.env.ADMIN_API_KEY || process.env.API_KEY;
    
    if (!validApiKey) {
        // If no API key is set in env, allow access (development mode)
        // In production, this should be set
        console.warn('⚠️  WARNING: ADMIN_API_KEY not set in environment. Admin endpoints are unprotected!');
        return next();
    }
    
    if (!apiKey || apiKey !== validApiKey) {
        return res.status(401).json({
            success: false,
            error: 'Unauthorized. Valid API key required.',
            code: 'UNAUTHORIZED'
        });
    }
    
    next();
}

module.exports = { adminAuth };


