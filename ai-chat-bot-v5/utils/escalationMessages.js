// ============================================
// 🚨 ESCALATION MESSAGES (Enhanced)
// ============================================

/**
 * Escalation severity levels
 */
const SEVERITY_LEVELS = {
    LOW: 'low',
    MEDIUM: 'medium',
    HIGH: 'high',
    CRITICAL: 'critical'
};

/**
 * Escalation action types
 */
const ESCALATION_ACTIONS = {
    WARN: 'warn',
    THROTTLE: 'throttle',
    ESCALATE: 'escalate',
    BLOCK: 'block'
};

/**
 * Message templates for different scenarios
 */
const MESSAGE_TEMPLATES = {
    en: {
        low: {
            message: 'Please keep the conversation respectful. We\'re here to help you.',
            action: ESCALATION_ACTIONS.WARN
        },
        medium: {
            message: 'Please use respectful language. If this continues, you will be connected to human support.',
            action: ESCALATION_ACTIONS.WARN
        },
        high: {
            message: 'Sorry, we cannot accept this type of language. You have been connected to human support.',
            action: ESCALATION_ACTIONS.ESCALATE
        },
        critical: {
            message: 'Your session has been terminated due to policy violations. A supervisor will review this conversation.',
            action: ESCALATION_ACTIONS.BLOCK
        },
        timeout: {
            message: 'You have been temporarily restricted due to repeated violations. Please try again in {minutes} minutes.',
            action: ESCALATION_ACTIONS.THROTTLE
        },
        cooldown: {
            message: 'Let\'s take a moment. How can I help you today?',
            action: ESCALATION_ACTIONS.WARN
        },
        farewell: {
            message: 'Thank you for contacting us. Have a great day!',
            action: null
        }
    },
    ar: {
        low: {
            message: 'من فضلك حافظ على حوار محترم. إحنا هنا عشان نساعدك.',
            action: ESCALATION_ACTIONS.WARN
        },
        medium: {
            message: 'من فضلك استخدم لغة محترمة. إذا استمرت المشكلة، سنقوم بتحويلك للدعم البشري.',
            action: ESCALATION_ACTIONS.WARN
        },
        high: {
            message: 'عذراً، لا يمكننا قبول هذا النوع من اللغة. تم تحويلك للدعم البشري.',
            action: ESCALATION_ACTIONS.ESCALATE
        },
        critical: {
            message: 'تم إنهاء جلستك بسبب مخالفة السياسات. سيقوم مشرف بمراجعة هذه المحادثة.',
            action: ESCALATION_ACTIONS.BLOCK
        },
        timeout: {
            message: 'تم تقييدك مؤقتاً بسبب المخالفات المتكررة. من فضلك حاول تاني بعد {minutes} دقيقة.',
            action: ESCALATION_ACTIONS.THROTTLE
        },
        cooldown: {
            message: 'خلينا ناخد لحظة. إزاي أقدر أساعدك النهاردة؟',
            action: ESCALATION_ACTIONS.WARN
        },
        farewell: {
            message: 'شكراً لتواصلك معنا. يوم سعيد!',
            action: null
        }
    }
};

/**
 * Escalation context - contains metadata about the escalation
 * @typedef {Object} EscalationContext
 * @property {string} userId - User identifier
 * @property {string} sessionId - Session identifier
 * @property {number} violationCount - Number of violations in session
 * @property {number} timestamp - Unix timestamp
 * @property {string} originalMessage - The triggering message
 * @property {string[]} matchedPatterns - Patterns that triggered escalation
 */

/**
 * Escalation result object
 * @typedef {Object} EscalationResult
 * @property {string} message - The reply message
 * @property {string} action - The action to take
 * @property {string} severity - The severity level
 * @property {boolean} requiresHumanReview - Whether human review is needed
 * @property {Object} metadata - Additional metadata
 */

/**
 * Generate escalation reply based on language, severity, and context
 * Replies are ONLY in English or Egyptian Arabic (عامية مصرية)
 * 
 * @param {string} lang - Language code ('en' or 'ar')
 * @param {string} severity - Severity level (low, medium, high, critical)
 * @param {Object} options - Additional options
 * @param {number} options.violationCount - Number of previous violations
 * @param {number} options.timeoutMinutes - Minutes for timeout message
 * @param {string} options.messageType - Override message type (timeout, cooldown, farewell)
 * @returns {EscalationResult}
 */
function escalationReply(lang = 'en', severity = 'medium', options = {}) {
    const {
        violationCount = 0,
        timeoutMinutes = 5,
        messageType = null
    } = options;

    // Validate and normalize language
    const validLang = lang === 'ar' ? 'ar' : 'en';
    
    // Validate severity
    const validSeverity = Object.values(SEVERITY_LEVELS).includes(severity) 
        ? severity 
        : SEVERITY_LEVELS.MEDIUM;

    // Determine effective severity based on violation count
    let effectiveSeverity = validSeverity;
    if (violationCount >= 3 && effectiveSeverity !== SEVERITY_LEVELS.CRITICAL) {
        effectiveSeverity = SEVERITY_LEVELS.HIGH;
    }
    if (violationCount >= 5) {
        effectiveSeverity = SEVERITY_LEVELS.CRITICAL;
    }

    // Get message template
    const templateKey = messageType || effectiveSeverity;
    const template = MESSAGE_TEMPLATES[validLang][templateKey] || MESSAGE_TEMPLATES[validLang].medium;
    
    // Process message with variables
    let message = template.message;
    if (message.includes('{minutes}')) {
        message = message.replace('{minutes}', timeoutMinutes.toString());
    }

    // Determine if human review is required
    const requiresHumanReview = [SEVERITY_LEVELS.HIGH, SEVERITY_LEVELS.CRITICAL].includes(effectiveSeverity);

    return {
        message,
        action: template.action,
        severity: effectiveSeverity,
        requiresHumanReview,
        metadata: {
            lang: validLang,
            originalSeverity: validSeverity,
            violationCount,
            timestamp: Date.now()
        }
    };
}

/**
 * Generate a simple string reply (backward compatible)
 * @param {string} lang - Language code
 * @param {string} severity - Severity level
 * @returns {string}
 */
function escalationReplySimple(lang = 'en', severity = 'medium') {
    return escalationReply(lang, severity).message;
}

/**
 * Language guard reply - helps user choose a supported language
 * Can be bilingual (English + Egyptian Arabic) to help the user choose
 * 
 * @param {Object} options - Configuration options
 * @param {boolean} options.bilingual - Return bilingual message (default: true)
 * @param {string} options.detectedLang - Detected unsupported language code
 * @param {boolean} options.friendly - Use friendlier tone
 * @returns {Object} Language guard response
 */
function languageGuardReply(options = {}) {
    const {
        bilingual = true,
        detectedLang = null,
        friendly = true
    } = options;

    const messages = {
        en: {
            standard: 'Please use English or Egyptian Arabic only.',
            friendly: 'We currently support English and Egyptian Arabic. Could you please use one of these languages?'
        },
        ar: {
            standard: 'من فضلك استخدم الإنجليزية أو العربية المصرية فقط.',
            friendly: 'إحنا حالياً بندعم الإنجليزي والعربي المصري. ممكن تستخدم واحدة منهم؟'
        }
    };

    const enMsg = friendly ? messages.en.friendly : messages.en.standard;
    const arMsg = friendly ? messages.ar.friendly : messages.ar.standard;

    const message = bilingual 
        ? `${enMsg} / ${arMsg}`
        : enMsg;

    return {
        message,
        supportedLanguages: ['en', 'ar'],
        detectedLanguage: detectedLang,
        isBilingual: bilingual
    };
}

/**
 * Get a contextual de-escalation message
 * Used to calm situations before they escalate further
 * 
 * @param {string} lang - Language code
 * @param {string} context - Context type (frustration, confusion, anger)
 * @returns {string}
 */
function deEscalationReply(lang = 'en', context = 'frustration') {
    const templates = {
        en: {
            frustration: 'I understand this might be frustrating. Let me help you resolve this step by step.',
            confusion: 'I can see this might be confusing. Let me explain it more clearly.',
            anger: 'I hear you, and I want to help. Let\'s work through this together.',
            general: 'I\'m here to help. What can I do for you?'
        },
        ar: {
            frustration: 'أنا فاهم إن ده ممكن يكون محبط. خليني أساعدك نحل الموضوع ده خطوة خطوة.',
            confusion: 'أنا شايف إن ده ممكن يكون مش واضح. خليني أشرحلك أوضح.',
            anger: 'أنا سامعك، وعايز أساعدك. خلينا نحل ده سوا.',
            general: 'أنا هنا عشان أساعدك. إزاي أقدر أساعدك؟'
        }
    };

    const validLang = lang === 'ar' ? 'ar' : 'en';
    const validContext = templates[validLang][context] ? context : 'general';

    return templates[validLang][validContext];
}

/**
 * Generate escalation notification for agents/supervisors
 * 
 * @param {EscalationContext} context - Escalation context
 * @returns {Object} Agent notification object
 */
function generateAgentNotification(context) {
    const {
        userId = 'unknown',
        sessionId = 'unknown',
        violationCount = 0,
        timestamp = Date.now(),
        originalMessage = '',
        matchedPatterns = []
    } = context;

    const severityEmoji = {
        low: '🟡',
        medium: '🟠',
        high: '🔴',
        critical: '⛔'
    };

    const severity = violationCount >= 5 ? 'critical' 
        : violationCount >= 3 ? 'high' 
        : violationCount >= 1 ? 'medium' 
        : 'low';

    return {
        title: `${severityEmoji[severity]} Escalation Alert`,
        severity,
        userId,
        sessionId,
        violationCount,
        timestamp: new Date(timestamp).toISOString(),
        triggerMessage: originalMessage.substring(0, 200), // Truncate for safety
        matchedPatterns,
        priority: severity === 'critical' ? 1 : severity === 'high' ? 2 : 3,
        requiresImmediateAction: ['high', 'critical'].includes(severity)
    };
}

module.exports = {
    // Constants
    SEVERITY_LEVELS,
    ESCALATION_ACTIONS,
    MESSAGE_TEMPLATES,
    
    // Main functions
    escalationReply,
    escalationReplySimple,
    languageGuardReply,
    deEscalationReply,
    generateAgentNotification
};
