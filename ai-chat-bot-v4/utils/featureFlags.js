// ============================================
// 🚩 FEATURE FLAGS SYSTEM
// ============================================

/**
 * Feature flags configuration
 * Controls rollout of new features
 */
const FEATURE_FLAGS = {
    LANGUAGE_ENFORCEMENT: {
        enabled: process.env.FF_LANGUAGE_ENFORCEMENT === 'true',
        rolloutPercent: parseInt(process.env.FF_LANGUAGE_ROLLOUT) || 0
    },
    HYBRID_CLASSIFIER: {
        enabled: process.env.FF_HYBRID_CLASSIFIER === 'true',
        l3Enabled: process.env.FF_L3_ENABLED !== 'false', // Default true
        rolloutPercent: parseInt(process.env.FF_CLASSIFIER_ROLLOUT) || 100
    },
    CAPTAIN_FLOW_V2: {
        enabled: process.env.FF_CAPTAIN_V2 === 'true',
        allowedUserIds: process.env.FF_CAPTAIN_V2_USERS?.split(',') || []
    },
    ML_MODERATION: {
        enabled: process.env.FF_ML_MODERATION === 'true',
        logOnly: process.env.FF_ML_LOG_ONLY !== 'false', // Default true (Phase 1)
        rolloutPercent: parseInt(process.env.FF_ML_ROLLOUT) || 0
    },
    PERSONALIZATION_V2: {
        enabled: process.env.FF_PERSONALIZATION_V2 === 'true',
        rolloutPercent: parseInt(process.env.FF_PERSONALIZATION_ROLLOUT) || 100
    },
    STATE_VERSIONING_V2: {
        enabled: process.env.FF_STATE_V2 === 'true',
        rolloutPercent: 100 // Always enabled if flag is true
    }
};

/**
 * Simple hash function for consistent user-based rollout
 */
function hashUserId(userId) {
    let hash = 0;
    if (!userId) return 0;
    for (let i = 0; i < userId.length; i++) {
        const char = userId.charCodeAt(i);
        hash = ((hash << 5) - hash) + char;
        hash = hash & hash; // Convert to 32-bit integer
    }
    return Math.abs(hash);
}

/**
 * Check if a feature is enabled for a specific user
 * @param {string} flagName - Feature flag name
 * @param {string} userId - User ID (optional)
 * @returns {boolean}
 */
function isFeatureEnabled(flagName, userId = null) {
    const config = FEATURE_FLAGS[flagName];
    
    if (!config) {
        console.warn(`[FeatureFlags] Unknown flag: ${flagName}`);
        return false;
    }
    
    // Check if feature is enabled at all
    if (!config.enabled) {
        return false;
    }
    
    // User-specific allowlist (for testing)
    if (config.allowedUserIds && config.allowedUserIds.length > 0) {
        if (userId && config.allowedUserIds.includes(userId)) {
            return true;
        }
        // If allowlist exists but user not in it, disable
        if (userId) {
            return false;
        }
    }
    
    // Percentage rollout
    if (config.rolloutPercent !== undefined && userId) {
        const hash = hashUserId(userId);
        return (hash % 100) < config.rolloutPercent;
    }
    
    // If no rollout percent specified, return enabled status
    return config.enabled;
}

/**
 * Get feature flag configuration
 * @param {string} flagName - Feature flag name
 * @returns {Object|null}
 */
function getFeatureConfig(flagName) {
    return FEATURE_FLAGS[flagName] || null;
}

/**
 * Get all feature flags status (for admin/debugging)
 * @param {string} userId - Optional user ID to check user-specific status
 * @returns {Object}
 */
function getAllFlagsStatus(userId = null) {
    const status = {};
    
    for (const [flagName, config] of Object.entries(FEATURE_FLAGS)) {
        status[flagName] = {
            enabled: config.enabled,
            userEnabled: userId ? isFeatureEnabled(flagName, userId) : null,
            config: {
                rolloutPercent: config.rolloutPercent,
                allowedUserIds: config.allowedUserIds?.length || 0
            }
        };
    }
    
    return status;
}

module.exports = {
    FEATURE_FLAGS,
    isFeatureEnabled,
    getFeatureConfig,
    getAllFlagsStatus,
    hashUserId
};




