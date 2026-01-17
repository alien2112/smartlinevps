// ============================================
// 🌍 LANGUAGE MANAGER (V3.2 Enhanced)
// Production-Ready with Redis Support & Translation
// ============================================

const { detectUserLanguage } = require('./moderation');

// Configuration Constants
const CONFIG = {
  LOCK_THRESHOLD: 5,                    // Messages to keep language locked
  COOLDOWN_THRESHOLD: 3,                // Messages in cooldown after lock expires (NEW)
  SWITCH_CONFIDENCE_THRESHOLD: 0.9,     // Confidence needed to auto-switch
  EXPLICIT_SWITCH_CONFIDENCE: 0.95,     // Higher threshold for explicit commands
  SESSION_TTL: 30 * 60 * 1000,          // 30 minutes session TTL
  MAX_SESSIONS: 100000,                 // Maximum sessions in memory
  CLEANUP_INTERVAL: 5 * 60 * 1000,      // Cleanup every 5 minutes
  ARABIZI_CONSECUTIVE_THRESHOLD: 3,     // Ask for clarification after N arabizi messages
  LANGUAGE_HISTORY_SIZE: 10,            // Track last N language detections
  ENFORCEMENT_MAX_RETRIES: 1,           // Max retries for LLM regeneration
};

// Supported Languages
const SUPPORTED_LANGUAGES = {
  en: { name: 'English', nativeName: 'English', rtl: false },
  ar: { name: 'Arabic', nativeName: 'العربية', rtl: true }
};

// Explicit language switch commands
const LANGUAGE_SWITCH_COMMANDS = {
  en: [
    /^(switch|change|speak|talk|use)\s*(to|in)?\s*(english|en|انجليزي|انجلش)/i,
    /^english\s*(please|pls)?$/i,
    /^en$/i,
    /^(i\s*(want|prefer|need)\s*(to\s*)?(speak|talk|use)\s*english)/i
  ],
  ar: [
    /^(switch|change|speak|talk|use)\s*(to|in)?\s*(arabic|ar|عربي|عربى)/i,
    /^(غير|حول|اتكلم|كلمني)\s*(ل|الى|ب)?\s*(عربي|العربي|العربية)/i,
    /^عربي$/i,
    /^ar$/i,
    /^(عايز|أريد)\s*(اتكلم|أتكلم)?\s*(عربي|بالعربي)/i
  ]
};

// Common phrases that indicate language preference
const LANGUAGE_INDICATORS = {
  en: [
    'please', 'thanks', 'thank you', 'help', 'need', 'want', 'book', 'ride',
    'where', 'when', 'how', 'what', 'can', 'could', 'would', 'sorry'
  ],
  ar: [
    'من فضلك', 'شكرا', 'مساعدة', 'عايز', 'محتاج', 'فين', 'امتى', 'ازاي',
    'ايه', 'ممكن', 'لو سمحت', 'احجز', 'رحلة', 'وصلني'
  ]
};

/**
 * Session data structure
 * @typedef {Object} LanguageSession
 * @property {string} language - Current language code
 * @property {number} lockCounter - Messages remaining in lock
 * @property {number} cooldownCounter - Messages remaining in cooldown (NEW)
 * @property {string|null} lastDetected - Last detected language
 * @property {number} arabiziCount - Consecutive arabizi messages
 * @property {string|null} arabiziPreference - User's choice for Arabizi responses ('en' | 'ar' | null) (NEW)
 * @property {Array<{lang: string, confidence: number, timestamp: number}>} history - Detection history
 * @property {number} createdAt - Session creation timestamp
 * @property {number} updatedAt - Last update timestamp
 * @property {boolean} explicitlySet - Whether user explicitly chose language
 * @property {Object} stats - Usage statistics
 */

/**
 * Language determination result
 * @typedef {Object} LanguageResult
 * @property {string} targetLang - Target language code ('en' | 'ar')
 * @property {string} reason - Reason for selection
 * @property {boolean} isArabizi - Whether input was Arabizi
 * @property {boolean} shouldAskClarification - Whether to ask user preference
 * @property {number} confidence - Confidence score
 * @property {Object} metadata - Additional metadata
 */

class LanguageManager {
  constructor() {
    // In-memory session storage (In production, use Redis)
    this.sessions = new Map();

    // Statistics
    this.stats = {
      totalDetections: 0,
      languageSwitches: 0,
      arabiziDetections: 0,
      explicitSwitches: 0,
      clarificationRequests: 0
    };

    // Start cleanup interval
    this._startCleanupInterval();
  }

  /**
   * Initialize with Redis (optional, for production)
   * @param {Object} redisClient - Redis client instance
   */
  initRedis(redisClient) {
    this.redis = redisClient;
    console.log('✅ LanguageManager: Redis integration enabled');
  }

  /**
   * Get or create session for user
   * @param {string} userId 
   * @param {Object} userPreferences 
   * @returns {LanguageSession}
   */
  async _getSession(userId, userPreferences = {}) {
    // Try Redis first if available
    if (this.redis) {
      try {
        const cached = await this.redis.get(`lang:session:${userId}`);
        if (cached) {
          return JSON.parse(cached);
        }
      } catch (e) {
        console.error('[LanguageManager] Redis get error:', e.message);
      }
    }

    // Check memory cache
    const cached = this.sessions.get(userId);
    if (cached && Date.now() - cached.updatedAt < CONFIG.SESSION_TTL) {
      return cached;
    }

    // Create new session
    const session = {
      language: userPreferences.preferred_language || 'ar', // Default to Arabic for Egyptian market
      lockCounter: 0,
      cooldownCounter: 0, // NEW
      lastDetected: null,
      arabiziCount: 0,
      arabiziPreference: userPreferences.arabizi_preference || null, // NEW
      history: [],
      createdAt: Date.now(),
      updatedAt: Date.now(),
      explicitlySet: !!userPreferences.preferred_language,
      stats: {
        messagesProcessed: 0,
        languageChanges: 0,
        arabiziMessages: 0,
        responseValidationFailures: 0 // NEW
      }
    };

    await this._saveSession(userId, session);
    return session;
  }

  /**
   * Save session to storage
   * @param {string} userId 
   * @param {LanguageSession} session 
   */
  async _saveSession(userId, session) {
    session.updatedAt = Date.now();

    // Save to Redis if available
    if (this.redis) {
      try {
        await this.redis.setex(
          `lang:session:${userId}`,
          Math.floor(CONFIG.SESSION_TTL / 1000),
          JSON.stringify(session)
        );
      } catch (e) {
        console.error('[LanguageManager] Redis set error:', e.message);
      }
    }

    // Always save to memory as fallback
    if (this.sessions.size >= CONFIG.MAX_SESSIONS) {
      // Remove oldest session
      const oldest = this._findOldestSession();
      if (oldest) this.sessions.delete(oldest);
    }
    this.sessions.set(userId, session);
  }

  /**
   * Check for explicit language switch commands
   * @param {string} message 
   * @returns {{detected: boolean, language: string|null}}
   */
  _detectExplicitSwitch(message) {
    const normalizedMessage = message.trim().toLowerCase();

    for (const [lang, patterns] of Object.entries(LANGUAGE_SWITCH_COMMANDS)) {
      for (const pattern of patterns) {
        if (pattern.test(normalizedMessage)) {
          return { detected: true, language: lang };
        }
      }
    }

    return { detected: false, language: null };
  }

  /**
   * Analyze language indicators in message
   * @param {string} message 
   * @returns {{en: number, ar: number}}
   */
  _analyzeIndicators(message) {
    const lowerMessage = message.toLowerCase();
    const scores = { en: 0, ar: 0 };

    for (const indicator of LANGUAGE_INDICATORS.en) {
      if (lowerMessage.includes(indicator)) scores.en++;
    }

    for (const indicator of LANGUAGE_INDICATORS.ar) {
      if (lowerMessage.includes(indicator)) scores.ar++;
    }

    return scores;
  }

  /**
   * Add detection to history
   * @param {LanguageSession} session 
   * @param {string} lang 
   * @param {number} confidence 
   */
  _addToHistory(session, lang, confidence) {
    session.history.push({
      lang,
      confidence,
      timestamp: Date.now()
    });

    // Keep only recent history
    if (session.history.length > CONFIG.LANGUAGE_HISTORY_SIZE) {
      session.history.shift();
    }
  }

  /**
   * Analyze history for language trends
   * @param {LanguageSession} session 
   * @returns {{dominantLang: string|null, consistency: number}}
   */
  _analyzeHistory(session) {
    if (session.history.length < 3) {
      return { dominantLang: null, consistency: 0 };
    }

    const langCounts = { en: 0, ar: 0 };
    const recentHistory = session.history.slice(-5); // Last 5 detections

    for (const entry of recentHistory) {
      if (entry.lang === 'en' || entry.lang === 'ar') {
        langCounts[entry.lang]++;
      }
    }

    const total = langCounts.en + langCounts.ar;
    if (total === 0) return { dominantLang: null, consistency: 0 };

    const dominantLang = langCounts.en > langCounts.ar ? 'en' : 'ar';
    const consistency = Math.max(langCounts.en, langCounts.ar) / total;

    return { dominantLang, consistency };
  }

  /**
   * Main method: Determine target language for response
   * @param {string} userId - User ID
   * @param {string} message - User message
   * @param {Object} userPreferences - Stored user preferences
   * @returns {Promise<LanguageResult>}
   */
  async determineTargetLanguage(userId, message, userPreferences = {}) {
    this.stats.totalDetections++;

    // Get or create session
    const session = await this._getSession(userId, userPreferences);
    session.stats.messagesProcessed++;

    // 1. Check for explicit language switch command (HIGHEST PRIORITY)
    const explicitSwitch = this._detectExplicitSwitch(message);
    if (explicitSwitch.detected) {
      this.stats.explicitSwitches++;
      session.language = explicitSwitch.language;
      session.lockCounter = CONFIG.LOCK_THRESHOLD;
      session.cooldownCounter = 0; // Reset cooldown on explicit switch
      session.explicitlySet = true;
      session.arabiziCount = 0;
      session.stats.languageChanges++;

      await this._saveSession(userId, session);

      return {
        targetLang: explicitSwitch.language,
        reason: 'explicit_command',
        isArabizi: false,
        shouldAskClarification: false,
        confidence: 1.0,
        metadata: {
          command: message,
          previousLang: session.lastDetected
        }
      };
    }

    // 2. Detect language from message
    const detection = detectUserLanguage(message);
    this._addToHistory(session, detection.primary, detection.confidence);

    // 3. Handle Arabizi (Franco-Arabic)
    if (detection.primary === 'arabizi') {
      this.stats.arabiziDetections++;
      session.arabiziCount++;
      session.stats.arabiziMessages++;

      // Determine target based on preferences or history
      let targetLang = 'en'; // Default to English for Arabizi (professional)
      let shouldAsk = false;

      // If user has explicit Arabizi preference, honor it
      if (session.arabiziPreference) {
        targetLang = session.arabiziPreference;
      } else if (userPreferences.preferred_language) {
        targetLang = userPreferences.preferred_language;
      } else if (session.explicitlySet) {
        targetLang = session.language;
      } else if (session.arabiziCount >= CONFIG.ARABIZI_CONSECUTIVE_THRESHOLD) {
        // After several arabizi messages without preference, ask ONCE
        shouldAsk = true;
        this.stats.clarificationRequests++;
      }

      // Analyze history for hints
      const historyAnalysis = this._analyzeHistory(session);
      if (historyAnalysis.dominantLang && historyAnalysis.consistency > 0.7) {
        targetLang = historyAnalysis.dominantLang;
        shouldAsk = false;
      }

      session.lastDetected = 'arabizi';
      session.lockCounter = Math.max(session.lockCounter - 1, 0);
      await this._saveSession(userId, session);

      return {
        targetLang,
        reason: 'arabizi_detected',
        isArabizi: true,
        shouldAskClarification: shouldAsk,
        confidence: detection.confidence,
        metadata: {
          consecutiveArabiziCount: session.arabiziCount,
          historyAnalysis
        }
      };
    }

    // Reset arabizi count when clear language detected
    if (detection.primary === 'en' || detection.primary === 'ar') {
      session.arabiziCount = 0;
    }

    const detectedLang = (detection.primary === 'mixed' || detection.primary === 'unknown')
      ? null
      : detection.primary;

    // 4. Sticky Session Logic - Locked Mode
    if (session.lockCounter > 0) {
      // Check for strong signal to override lock
      if (detectedLang &&
        detectedLang !== session.language &&
        detection.confidence >= CONFIG.EXPLICIT_SWITCH_CONFIDENCE) {

        // Very strong signal - allow switch even in locked mode
        this.stats.languageSwitches++;
        session.language = detectedLang;
        session.lockCounter = CONFIG.LOCK_THRESHOLD;
        session.cooldownCounter = 0; // Reset cooldown on explicit switch
        session.lastDetected = detectedLang;
        session.stats.languageChanges++;

        await this._saveSession(userId, session);

        return {
          targetLang: detectedLang,
          reason: 'strong_signal_override',
          isArabizi: false,
          shouldAskClarification: false,
          confidence: detection.confidence,
          metadata: {
            previousLang: session.language,
            lockOverridden: true
          }
        };
      }

      // Stay locked
      session.lockCounter--;
      session.lastDetected = detectedLang || session.lastDetected;
      await this._saveSession(userId, session);

      return {
        targetLang: session.language,
        reason: 'session_locked',
        isArabizi: false,
        shouldAskClarification: false,
        confidence: detection.confidence,
        metadata: {
          lockRemaining: session.lockCounter,
          detectedButIgnored: detectedLang
        }
      };
    }

    // 4.5. Cooldown Mode (after lock expires)
    if (session.cooldownCounter > 0) {
      // During cooldown, only allow switch with very high confidence or explicit command
      if (detectedLang &&
        detectedLang !== session.language &&
        (detection.confidence >= CONFIG.EXPLICIT_SWITCH_CONFIDENCE || explicitSwitch.detected)) {

        // Strong signal during cooldown - allow switch
        this.stats.languageSwitches++;
        session.language = detectedLang;
        session.lockCounter = CONFIG.LOCK_THRESHOLD;
        session.cooldownCounter = 0;
        session.lastDetected = detectedLang;
        session.stats.languageChanges++;

        await this._saveSession(userId, session);

        return {
          targetLang: detectedLang,
          reason: 'cooldown_override',
          isArabizi: false,
          shouldAskClarification: false,
          confidence: detection.confidence,
          metadata: {
            previousLang: session.language,
            cooldownOverridden: true
          }
        };
      }

      // Stay in cooldown
      session.cooldownCounter--;
      session.lastDetected = detectedLang || session.lastDetected;
      await this._saveSession(userId, session);

      return {
        targetLang: session.language,
        reason: 'cooldown_active',
        isArabizi: false,
        shouldAskClarification: false,
        confidence: detection.confidence,
        metadata: {
          cooldownRemaining: session.cooldownCounter,
          detectedButIgnored: detectedLang
        }
      };
    }

    // 5. Unlocked Mode - Allow switching
    if (detectedLang && detection.confidence > 0.75) {
      if (detectedLang !== session.language) {
        // Language switch detected
        this.stats.languageSwitches++;
        session.language = detectedLang;
        session.lockCounter = CONFIG.LOCK_THRESHOLD;
        session.cooldownCounter = 0; // Reset cooldown on switch
        session.lastDetected = detectedLang;
        session.stats.languageChanges++;

        await this._saveSession(userId, session);

        return {
          targetLang: detectedLang,
          reason: 'detected_switch',
          isArabizi: false,
          shouldAskClarification: false,
          confidence: detection.confidence,
          metadata: {
            previousLang: session.language,
            switchedFrom: session.language,
            switchedTo: detectedLang
          }
        };
      }

      // Same language, reinforce lock
      session.lockCounter = Math.min(session.lockCounter + 1, CONFIG.LOCK_THRESHOLD);
    }

    // Handle lock expiration - enter cooldown
    if (session.lockCounter === 0 && session.cooldownCounter === 0 && session.lockCounter === 0) {
      // Lock just expired, start cooldown if language was recently switched
      const recentSwitch = session.history.slice(-3).some(h =>
        h.lang !== session.language && h.confidence > 0.7
      );
      if (recentSwitch) {
        session.cooldownCounter = CONFIG.COOLDOWN_THRESHOLD;
      }
    }

    // 6. Fallback to existing session language
    session.lastDetected = detectedLang || session.lastDetected;
    await this._saveSession(userId, session);

    return {
      targetLang: session.language,
      reason: 'sticky_default',
      isArabizi: false,
      shouldAskClarification: false,
      confidence: detection.confidence,
      metadata: {
        sessionLanguage: session.language,
        detectedLanguage: detectedLang
      }
    };
  }

  /**
   * Force set language for user (admin or explicit setting)
   * @param {string} userId 
   * @param {string} language 
   * @returns {Promise<boolean>}
   */
  async setLanguage(userId, language) {
    if (!SUPPORTED_LANGUAGES[language]) {
      return false;
    }

    const session = await this._getSession(userId, {});
    session.language = language;
    session.lockCounter = CONFIG.LOCK_THRESHOLD * 2; // Extra lock for explicit setting
    session.explicitlySet = true;
    session.arabiziCount = 0;
    session.stats.languageChanges++;

    await this._saveSession(userId, session);
    return true;
  }

  /**
   * Get language info for display
   * @param {string} langCode 
   * @returns {Object}
   */
  getLanguageInfo(langCode) {
    return SUPPORTED_LANGUAGES[langCode] || SUPPORTED_LANGUAGES.en;
  }

  /**
   * Check if language is RTL
   * @param {string} langCode 
   * @returns {boolean}
   */
  isRTL(langCode) {
    return SUPPORTED_LANGUAGES[langCode]?.rtl || false;
  }

  /**
   * Generate clarification message asking user preference
   * @param {string} currentLang 
   * @returns {Object}
   */
  generateClarificationMessage(currentLang = 'ar') {
    return {
      en: {
        message: "I noticed you're using Franco-Arabic (Arabizi). Would you prefer me to respond in English or Arabic?",
        quick_replies: ['English', 'العربية (Arabic)']
      },
      ar: {
        message: "لاحظت إنك بتستخدم فرانكو. تحب أرد عليك بالإنجليزي ولا العربي؟",
        quick_replies: ['English', 'العربية']
      }
    }[currentLang] || {
      message: "Would you prefer English or Arabic? / تفضل إنجليزي ولا عربي؟",
      quick_replies: ['English', 'العربية']
    };
  }

  /**
   * Enforcement cascade priority order
   */
  static ENFORCEMENT_CASCADE = [
    { method: 'validate', description: 'Check if response matches target language' },
    { method: 'regenerate', description: 'Re-call LLM with stronger prompt', maxRetries: 1 },
    { method: 'translate', description: 'Use translation API', service: 'google' },
    { method: 'fallback', description: 'Use pre-written response template' }
  ];

  /**
   * Get language instruction for LLM prompt
   * @param {string} targetLang - Target language code
   * @returns {string}
   */
  getLanguageInstruction(targetLang) {
    const instructions = {
      en: `CRITICAL LANGUAGE REQUIREMENT:
- You MUST respond ONLY in English
- Do NOT use ANY Arabic words or script (العربية)
- Do NOT use Arabizi (Arabic written in Latin letters like "ahlan", "shokran")
- Do NOT mix English and Arabic
- This is MANDATORY - violating this will cause errors
- Every word in your response MUST be English`,
      ar: `متطلبات اللغة الحتمية:
- يجب أن ترد بالعربية فقط
- لا تستخدم أي كلمات إنجليزية
- لا تستخدم الفرانكو أو الأرابيزي
- كل كلمة في ردك يجب أن تكون بالعربية
- استخدم اللهجة المصرية
- هذا إلزامي ولا يمكن تجاهله`
    };

    return instructions[targetLang] || instructions.en;
  }

  /**
   * Validate LLM prompt includes language instruction
   * @param {string} prompt - System prompt
   * @param {string} targetLang - Target language
   * @returns {{valid: boolean, hasInstruction: boolean}}
   */
  validateLLMPrompt(prompt, targetLang) {
    const instruction = this.getLanguageInstruction(targetLang);
    const hasInstruction = prompt.includes(instruction) ||
      prompt.toLowerCase().includes('must respond only') ||
      prompt.toLowerCase().includes('do not mix languages');

    return {
      valid: hasInstruction,
      hasInstruction,
      suggestedPrompt: hasInstruction ? prompt : `${prompt}\n\n${instruction}`
    };
  }

  /**
   * Enforce response language with cascade fallback
   * @param {string} text - Response text to enforce
   * @param {string} targetLang - Target language
   * @param {Object} options - Options for enforcement
   * @param {Function} options.regenerateFn - Function to regenerate response (optional)
   * @param {Function} options.translateFn - Function to translate text (optional)
   * @param {Function} options.fallbackFn - Function to get fallback message (optional)
   * @returns {Promise<{success: boolean, text: string, method: string, metadata: Object}>}
   */
  async enforceResponseLanguage(text, targetLang, options = {}) {
    const { regenerateFn, translateFn, fallbackFn } = options;

    // Step 1: Validate
    const validation = this.validateResponseLanguage(text, targetLang);
    if (validation.valid) {
      return {
        success: true,
        text,
        method: 'validate',
        metadata: { validation }
      };
    }

    // Step 2: Regenerate (if function provided)
    if (regenerateFn && CONFIG.ENFORCEMENT_MAX_RETRIES > 0) {
      try {
        const regenerated = await regenerateFn();
        const regenValidation = this.validateResponseLanguage(regenerated, targetLang);
        if (regenValidation.valid) {
          return {
            success: true,
            text: regenerated,
            method: 'regenerate',
            metadata: {
              originalValidation: validation,
              regeneratedValidation: regenValidation
            }
          };
        }
      } catch (error) {
        console.warn('[LanguageManager] Regeneration failed:', error.message);
      }
    }

    // Step 3: Translate (if function provided)
    if (translateFn) {
      try {
        const translated = await translateFn(text, targetLang);
        const transValidation = this.validateResponseLanguage(translated, targetLang);
        if (transValidation.valid) {
          return {
            success: true,
            text: translated,
            method: 'translate',
            metadata: {
              originalValidation: validation,
              translatedValidation: transValidation
            }
          };
        }
      } catch (error) {
        console.warn('[LanguageManager] Translation failed:', error.message);
      }
    }

    // Step 4: Fallback (if function provided)
    if (fallbackFn) {
      try {
        const fallback = await fallbackFn(targetLang);
        return {
          success: true,
          text: fallback,
          method: 'fallback',
          metadata: {
            originalValidation: validation,
            originalText: text
          }
        };
      } catch (error) {
        console.warn('[LanguageManager] Fallback failed:', error.message);
      }
    }

    // All methods failed - return original with warning
    return {
      success: false,
      text, // Return original text
      method: 'none',
      metadata: {
        validation,
        warning: 'Language enforcement failed, using original response'
      }
    };
  }

  /**
   * Handle Arabizi preference selection
   * @param {string} userId - User ID
   * @param {string} preference - 'en' or 'ar'
   * @returns {Promise<boolean>}
   */
  async setArabiziPreference(userId, preference) {
    if (!['en', 'ar'].includes(preference)) {
      return false;
    }

    const session = await this._getSession(userId, {});
    session.arabiziPreference = preference;
    session.arabiziCount = 0; // Reset count
    session.explicitlySet = true;

    await this._saveSession(userId, session);
    return true;
  }

  /**
   * Enforce output language consistency (placeholder for translation integration)
   * @param {string} text - Text to potentially translate
   * @param {string} targetCode - Target language code
   * @param {string} sourceCode - Source language code (optional)
   * @returns {Promise<string>}
   */
  async enforce(text, targetCode, sourceCode = null) {
    // Placeholder for translation service integration
    // In production, integrate with Google Translate, DeepL, or similar

    // For now, just return the text as-is
    // TODO: Implement actual translation when service is configured

    return text;
  }

  /**
   * Validate that response matches target language
   * @param {string} text 
   * @param {string} expectedLang 
   * @returns {{valid: boolean, detectedLang: string, confidence: number}}
   */
  validateResponseLanguage(text, expectedLang) {
    const detection = detectUserLanguage(text);
    const detectedLang = detection.primary;

    // Handle arabizi as matching Arabic context
    if (detectedLang === 'arabizi' && expectedLang === 'ar') {
      return { valid: true, detectedLang: 'arabizi', confidence: detection.confidence };
    }

    // Mixed content is acceptable if it contains the target language
    if (detectedLang === 'mixed') {
      return { valid: true, detectedLang: 'mixed', confidence: detection.confidence };
    }

    return {
      valid: detectedLang === expectedLang,
      detectedLang,
      confidence: detection.confidence
    };
  }

  /**
   * Get session statistics for user
   * @param {string} userId 
   * @returns {Promise<Object>}
   */
  async getUserStats(userId) {
    const session = await this._getSession(userId, {});
    return {
      currentLanguage: session.language,
      explicitlySet: session.explicitlySet,
      lockRemaining: session.lockCounter,
      arabiziCount: session.arabiziCount,
      sessionAge: Date.now() - session.createdAt,
      stats: session.stats,
      recentHistory: session.history.slice(-5)
    };
  }

  /**
   * Get global statistics
   * @returns {Object}
   */
  getStats() {
    return {
      ...this.stats,
      activeSessions: this.sessions.size,
      maxSessions: CONFIG.MAX_SESSIONS
    };
  }

  /**
   * Clear session for user
   * @param {string} userId 
   */
  async clearSession(userId) {
    this.sessions.delete(userId);

    if (this.redis) {
      try {
        await this.redis.del(`lang:session:${userId}`);
      } catch (e) {
        console.error('[LanguageManager] Redis del error:', e.message);
      }
    }
  }

  /**
   * Find oldest session for eviction
   * @returns {string|null}
   */
  _findOldestSession() {
    let oldest = null;
    let oldestTime = Date.now();

    for (const [userId, session] of this.sessions.entries()) {
      if (session.updatedAt < oldestTime) {
        oldestTime = session.updatedAt;
        oldest = userId;
      }
    }

    return oldest;
  }

  /**
   * Start periodic cleanup of expired sessions
   */
  _startCleanupInterval() {
    setInterval(() => {
      const now = Date.now();
      let cleaned = 0;

      for (const [userId, session] of this.sessions.entries()) {
        if (now - session.updatedAt > CONFIG.SESSION_TTL) {
          this.sessions.delete(userId);
          cleaned++;
        }
      }

      if (cleaned > 0) {
        console.log(`[LanguageManager] Cleaned ${cleaned} expired sessions`);
      }
    }, CONFIG.CLEANUP_INTERVAL);
  }

  /**
   * Graceful shutdown
   */
  async shutdown() {
    // Persist important sessions to Redis if available
    if (this.redis) {
      console.log('[LanguageManager] Persisting sessions before shutdown...');
      const promises = [];

      for (const [userId, session] of this.sessions.entries()) {
        if (session.explicitlySet) { // Only persist explicitly set preferences
          promises.push(
            this.redis.setex(
              `lang:session:${userId}`,
              86400, // 24 hours
              JSON.stringify(session)
            ).catch(e => console.error(`Failed to persist session ${userId}:`, e.message))
          );
        }
      }

      await Promise.all(promises);
    }

    this.sessions.clear();
    console.log('[LanguageManager] Shutdown complete');
  }
}

// Export singleton instance
const instance = new LanguageManager();

module.exports = instance;
module.exports.LanguageManager = LanguageManager;
module.exports.SUPPORTED_LANGUAGES = SUPPORTED_LANGUAGES;
module.exports.CONFIG = CONFIG;