// ============================================
// ✅ INPUT VALIDATION (Production-Ready)
// ============================================

const { body, param, query, validationResult } = require('express-validator');

/**
 * Validation error handler middleware
 */
const handleValidationErrors = (req, res, next) => {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
        return res.status(400).json({
            success: false,
            error: 'Validation failed',
            code: 'VALIDATION_ERROR',
            details: errors.array().map(e => ({
                field: e.path,
                message: e.msg,
                value: e.value ? '[REDACTED]' : undefined
            }))
        });
    }
    next();
};

/**
 * Chat endpoint validation
 */
const validateChatRequest = [
    body('user_id')
        .exists().withMessage('user_id is required')
        .isString().withMessage('user_id must be a string')
        .trim()
        .isLength({ min: 1, max: 100 }).withMessage('user_id must be 1-100 characters'),
    
    body('message')
        .exists().withMessage('message is required')
        .isString().withMessage('message must be a string')
        .trim()
        .isLength({ min: 1, max: 500 }).withMessage('message must be 1-500 characters'),
    
    body('user_type')
        .optional()
        .isIn(['customer', 'captain', 'guest']).withMessage('user_type must be customer, captain, or guest'),
    
    body('language')
        .optional()
        .isIn(['en', 'ar', 'auto']).withMessage('language must be en, ar, or auto'),
    
    body('session_id')
        .optional()
        .isString()
        .isLength({ max: 100 }).withMessage('session_id must be max 100 characters'),

    body('location')
        .optional()
        .isObject().withMessage('location must be an object'),
    
    body('location.lat')
        .optional()
        .isFloat({ min: -90, max: 90 }).withMessage('latitude must be between -90 and 90'),
    
    body('location.lng')
        .optional()
        .isFloat({ min: -180, max: 180 }).withMessage('longitude must be between -180 and 180'),

    handleValidationErrors
];

/**
 * Location submission validation
 */
const validateLocationSubmit = [
    body('user_id')
        .exists().withMessage('user_id is required')
        .isString()
        .trim()
        .isLength({ min: 1, max: 100 }),
    
    body('location')
        .exists().withMessage('location is required')
        .isObject().withMessage('location must be an object'),
    
    body('location.lat')
        .exists().withMessage('latitude is required')
        .isFloat({ min: 22, max: 32 }).withMessage('latitude must be within Egypt (22-32)'),
    
    body('location.lng')
        .exists().withMessage('longitude is required')
        .isFloat({ min: 24, max: 37 }).withMessage('longitude must be within Egypt (24-37)'),
    
    body('location.address')
        .optional()
        .isString()
        .isLength({ max: 500 }).withMessage('address must be max 500 characters'),
    
    body('type')
        .exists().withMessage('type is required')
        .isIn(['pickup', 'destination']).withMessage('type must be pickup or destination'),

    handleValidationErrors
];

/**
 * Admin clear memory validation
 */
const validateAdminClearMemory = [
    body('user_id')
        .exists().withMessage('user_id is required')
        .isString()
        .trim()
        .isLength({ min: 1, max: 100 }),

    handleValidationErrors
];

/**
 * Admin user state validation
 */
const validateAdminUserState = [
    param('id')
        .exists().withMessage('user id is required')
        .isString()
        .trim()
        .isLength({ min: 1, max: 100 }),

    handleValidationErrors
];

/**
 * Admin set user type validation
 */
const validateAdminSetUserType = [
    body('user_id')
        .exists().withMessage('user_id is required')
        .isString()
        .trim()
        .isLength({ min: 1, max: 100 }),
    
    body('user_type')
        .exists().withMessage('user_type is required')
        .isIn(['customer', 'captain']).withMessage('user_type must be customer or captain'),

    handleValidationErrors
];

/**
 * Sanitize user input (additional security layer)
 */
const sanitizeInput = (req, res, next) => {
    if (req.body) {
        // Remove any potential XSS in string fields
        for (const [key, value] of Object.entries(req.body)) {
            if (typeof value === 'string') {
                // Basic sanitization - remove script tags and encode special chars
                req.body[key] = value
                    .replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '')
                    .replace(/[<>]/g, char => char === '<' ? '&lt;' : '&gt;');
            }
        }
    }
    next();
};

/**
 * Rate limit key generator
 */
const getRateLimitKey = (req) => {
    // Prefer user_id for rate limiting if available
    if (req.body?.user_id) {
        return `user:${req.body.user_id}`;
    }
    // Fallback to IP
    return `ip:${req.ip || req.connection.remoteAddress}`;
};

module.exports = {
    validateChatRequest,
    validateLocationSubmit,
    validateAdminClearMemory,
    validateAdminUserState,
    validateAdminSetUserType,
    sanitizeInput,
    getRateLimitKey,
    handleValidationErrors
};