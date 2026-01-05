// ============================================
// 🤖 ML MODERATION DATA COLLECTION (Phase 1)
// ============================================

/**
 * Collect training data for ML moderation
 * Phase 1: Log only, don't block
 */
class MLModerationCollector {
    constructor() {
        this.stats = {
            dataCollected: 0,
            errors: 0
        };
    }

    /**
     * Hash message for deduplication
     * @param {string} message 
     * @returns {string}
     */
    hashMessage(message) {
        const crypto = require('crypto');
        return crypto.createHash('sha256').update(message.toLowerCase().trim()).digest('hex');
    }

    /**
     * Normalize message for ML training
     * @param {string} message 
     * @returns {string}
     */
    normalizeForML(message) {
        if (!message) return '';
        
        // Remove PII patterns (phone numbers, emails)
        let normalized = message
            .replace(/\b\d{10,}\b/g, '[PHONE]')
            .replace(/\b[\w.-]+@[\w.-]+\.\w+\b/g, '[EMAIL]')
            .toLowerCase()
            .trim();
        
        return normalized;
    }

    /**
     * Collect moderation training data
     * @param {string} message - Original message
     * @param {Object} ruleResult - Rule-based moderation result
     * @param {string} userId - User ID
     * @param {Function} dbExecute - Database execute function
     * @param {Function} getUserContext - Function to get user context (optional)
     * @returns {Promise<boolean>}
     */
    async collectTrainingData(message, ruleResult, userId, dbExecute, getUserContext = null) {
        try {
            const messageHash = this.hashMessage(message);
            const normalized = this.normalizeForML(message);
            
            // Get user context if function provided
            let userContext = null;
            if (getUserContext) {
                try {
                    userContext = await getUserContext(userId);
                } catch (e) {
                    console.warn('[MLModeration] Failed to get user context:', e.message);
                }
            }

            // Insert into training data table
            await dbExecute(`
                INSERT INTO moderation_training_data 
                (message_hash, message_normalized, rule_result, severity, user_context, user_id, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                    updated_at = NOW(),
                    collection_count = collection_count + 1
            `, [
                messageHash,
                normalized,
                JSON.stringify(ruleResult),
                ruleResult.severity || 'none',
                userContext ? JSON.stringify(userContext) : null,
                userId
            ]);

            this.stats.dataCollected++;
            return true;
        } catch (error) {
            this.stats.errors++;
            console.error('[MLModeration] Failed to collect training data:', error.message);
            return false;
        }
    }

    /**
     * Get collection statistics
     * @returns {Object}
     */
    getStats() {
        return { ...this.stats };
    }
}

// Export singleton
const collector = new MLModerationCollector();

module.exports = collector;
module.exports.MLModerationCollector = MLModerationCollector;

