// ============================================
// 🛡️ DEGRADATION POLICIES
// ============================================

/**
 * Defines fallback behavior when enhancement systems fail
 */
const DEGRADATION_POLICY = {
    language_manager_fail: 'use_detected_language',
    classifier_fail: 'use_regex_only',
    personalization_fail: 'skip_personalization',
    state_guard_fail: 'reset_to_start',
    ml_moderation_fail: 'use_rule_based_only',
    translation_fail: 'use_original_response',
    llm_fail: 'use_fallback_message'
};

/**
 * Query budget limits
 */
const QUERY_BUDGET = {
    chat_request: 8,  // Max queries per chat request
    admin_request: 15, // Admin can have more
    location_submit: 5
};

/**
 * Performance budgets (milliseconds)
 */
const PERFORMANCE_BUDGET = {
    language_enforcement: 10,
    hybrid_classifier: 20,
    personalization: 15,
    ml_moderation: 50,
    state_processing: 30,
    total_p99_non_llm: 500
};

/**
 * Track query count for current request
 */
class QueryTracker {
    constructor() {
        this.counts = new Map();
    }
    
    /**
     * Increment query count for request
     */
    increment(requestId, endpoint = 'chat') {
        const key = `${requestId}:${endpoint}`;
        const current = this.counts.get(key) || 0;
        this.counts.set(key, current + 1);
        return current + 1;
    }
    
    /**
     * Get query count for request
     */
    getCount(requestId, endpoint = 'chat') {
        const key = `${requestId}:${endpoint}`;
        return this.counts.get(key) || 0;
    }
    
    /**
     * Check if query budget exceeded
     */
    isBudgetExceeded(requestId, endpoint = 'chat') {
        const count = this.getCount(requestId, endpoint);
        const budget = QUERY_BUDGET[endpoint] || QUERY_BUDGET.chat_request;
        return count >= budget;
    }
    
    /**
     * Clear tracking for request
     */
    clear(requestId, endpoint = 'chat') {
        const key = `${requestId}:${endpoint}`;
        this.counts.delete(key);
    }
}

const queryTracker = new QueryTracker();

/**
 * Apply degradation policy
 * @param {string} component - Component that failed
 * @param {Error} error - Error that occurred
 * @param {Object} context - Context for fallback
 * @returns {Object} Fallback result
 */
function applyDegradation(component, error, context = {}) {
    const policy = DEGRADATION_POLICY[component];
    
    if (!policy) {
        console.error(`[Degradation] No policy for component: ${component}`);
        return { success: false, error: 'NO_POLICY' };
    }
    
    console.warn(`[Degradation] Applying policy for ${component}: ${policy}`, {
        error: error.message,
        context: Object.keys(context)
    });
    
    switch (policy) {
        case 'use_detected_language':
            return {
                success: true,
                language: context.detectedLang || 'ar',
                fallback: true
            };
            
        case 'use_regex_only':
            return {
                success: true,
                useRegex: true,
                fallback: true
            };
            
        case 'skip_personalization':
            return {
                success: true,
                skipPersonalization: true,
                fallback: true
            };
            
        case 'reset_to_start':
            return {
                success: true,
                state: 'START',
                data: {},
                fallback: true
            };
            
        case 'use_rule_based_only':
            return {
                success: true,
                useRuleBased: true,
                fallback: true
            };
            
        case 'use_original_response':
            return {
                success: true,
                useOriginal: true,
                fallback: true
            };
            
        case 'use_fallback_message':
            return {
                success: true,
                message: context.fallbackMessage || 'Sorry, I encountered an error. Please try again.',
                fallback: true
            };
            
        default:
            return {
                success: false,
                error: 'UNKNOWN_POLICY'
            };
    }
}

module.exports = {
    DEGRADATION_POLICY,
    QUERY_BUDGET,
    PERFORMANCE_BUDGET,
    QueryTracker,
    queryTracker,
    applyDegradation
};






