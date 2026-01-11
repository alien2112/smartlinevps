// ============================================
// 🚖 CAPTAIN VERIFICATION & ACCESS CONTROL
// ============================================

/**
 * Verify captain access from database
 * @param {string} userId - User ID
 * @param {Function} dbQuery - Database query function
 * @returns {Promise<{verified: boolean, reason?: string, captain?: Object}>}
 */
async function verifyCaptainAccess(userId, dbQuery) {
    if (!userId || !dbQuery) {
        return { verified: false, reason: 'INVALID_PARAMS' };
    }

    try {
        const rows = await dbQuery(`
            SELECT u.id, u.user_role, d.is_verified, d.is_active, d.license_number
            FROM users u
            LEFT JOIN drivers d ON u.id = d.user_id
            WHERE u.id = ? AND u.user_role = 'driver'
        `, [userId]);

        if (rows.length === 0) {
            return { verified: false, reason: 'NOT_CAPTAIN' };
        }

        const captain = rows[0];

        if (!captain.is_verified) {
            return { verified: false, reason: 'NOT_VERIFIED' };
        }

        if (!captain.is_active) {
            return { verified: false, reason: 'INACTIVE_CAPTAIN' };
        }

        return { 
            verified: true, 
            captain: {
                id: captain.id,
                is_verified: captain.is_verified,
                is_active: captain.is_active,
                license_number: captain.license_number
            }
        };
    } catch (error) {
        console.error('[CaptainVerification] Database error:', error.message);
        return { verified: false, reason: 'DATABASE_ERROR', error: error.message };
    }
}

/**
 * Check if user should have captain access
 * @param {string} userId - User ID
 * @param {Function} dbQuery - Database query function
 * @param {Function} logSecurityEvent - Security logging function
 * @returns {Promise<boolean>}
 */
async function shouldHaveCaptainAccess(userId, dbQuery, logSecurityEvent) {
    const result = await verifyCaptainAccess(userId, dbQuery);
    
    if (!result.verified && result.reason !== 'NOT_CAPTAIN') {
        // Log access denial
        if (logSecurityEvent) {
            logSecurityEvent('captain_access_denied', { 
                userId, 
                reason: result.reason 
            });
        }
    }
    
    return result.verified;
}

module.exports = {
    verifyCaptainAccess,
    shouldHaveCaptainAccess
};




