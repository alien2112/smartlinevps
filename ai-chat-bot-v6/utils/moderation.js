// ============================================
// 🛡️ ZERO-LATENCY MODERATION SYSTEM (Enhanced)
// ============================================

/**
 * Configuration object for the moderation system
 */
const CONFIG = {
    // Performance settings
    maxMessageLength: 5000,          // Maximum message length to process
    cacheEnabled: true,              // Enable result caching
    cacheTTL: 60000,                 // Cache TTL in milliseconds

    // Detection settings
    minWordLength: 2,                // Minimum word length for regex patterns
    enableFuzzyMatch: true,          // Enable fuzzy/loose matching
    enableLeetspeakDecode: true,     // Enable leetspeak decoding

    // Severity thresholds
    repeatViolationThreshold: 3,     // Violations before auto-escalation

    // Logging
    enableDebugLogging: false
};

// ============================================
// PROFANITY WORD LISTS
// ============================================

/**
 * English profanity list (curated, categorized)
 * Categories: general, slurs, threats, sexual
 */
const EN_PROFANITY = {
    general: [
        'fuck', 'shit', 'bitch', 'ass', 'damn', 'hell',
        'crap', 'piss', 'bastard', 'douche'
    ],
    slurs: [
        'nigger', 'nigga', 'fag', 'faggot', 'retard',
        'spic', 'kike', 'chink', 'wetback'
    ],
    sexual: [
        'cock', 'dick', 'pussy', 'cunt', 'whore',
        'slut', 'hoe', 'thot'
    ],
    insults: [
        'stupid', 'idiot', 'moron', 'dumb', 'loser',
        'pathetic', 'worthless'
    ]
};

// Flatten for backward compatibility
const EN_PROFANITY_FLAT = Object.values(EN_PROFANITY).flat();

/**
 * Egyptian Arabic profanity list (عامية مصرية) - Categorized
 */
const AR_PROFANITY = {
    // Core insults
    coreInsults: [
        'احا',
        'خول',
        'شرموط',
        'شرموطه',
        'عرص',
        'لبوه',
        'وسخ',
        'وسخه',
        'قذر',
        'قذره',
        'حيوان',
        'كلب',
        'حمار',
        'غبي',
        'تافه'
    ],

    // Family insults (very common escalation triggers)
    familyInsults: [
        'كسم',
        'كس ام',
        'كسمك',
        'كسمكوا',
        'كسمين',
        'ابن الوسخه',
        'ابن الشرموطه',
        'يلعن ابوك',
        'يلعن امك',
        'ابن كلب'
    ],

    // Sexual insults
    sexualInsults: [
        'منيك',
        'متناك',
        'متناكه',
        'زاني',
        'زانيه',
        'شاذ',
        'خنيث',
        'مخنث'
    ],

    // Threats / violence slang
    threats: [
        'اقتلك',
        'هقتلك',
        'افشخك',
        'هفشخك',
        'اكسر',
        'هكسرك',
        'ادبحك',
        'هدبحك',
        'اموتك',
        'هموتك'
    ],

    // Dismissive/contempt expressions
    contempt: [
        'اخرس',
        'انجع',
        'اطلع بره',
        'امشي',
        'روح في داهيه'
    ]
};

// Flatten for processing
const AR_PROFANITY_FLAT = Object.values(AR_PROFANITY).flat();

/**
 * Arabizi (Franco Arabic) profanity list - Extended
 * Common transliterations used in chat
 */
const ARABIZI_PROFANITY = [
    // Core insults
    'a7a', 'ahha', 'aha',           // احا (removed asterisk)
    '5awal', 'khawal', '5awel',     // خول
    'sharmot', 'sharmoota', 'sharmota', // شرموط
    '3ars', 'aars', '3rs',          // عرص
    'labwa', 'labwah',              // لبوه
    'wesekh', 'weskh', 'wisikh',    // وسخ

    // Family insults
    'kosom', 'kosomak', 'kosomik',  // كسم
    'kesom', 'kesomak',             // كسم variant
    'kos om', 'kos omak',           // كس ام
    'ibn el sharmoota',             // ابن الشرموطه
    'ibn kalb',                      // ابن كلب

    // Sexual insults
    'manyak', 'manyiek', 'manyik',   // منيك (removed asterisk)
    'metnak', 'mitnak', 'metnaak',  // متناك

    // Threats
    'ha2tlak', 'ha2tolk', 'a2tlak', // هقتلك
    'afsakhak', 'hafsa5ak'          // افشخك
];

/**
 * Evasion patterns - common tricks to bypass filters
 */
const EVASION_PATTERNS = {
    // Zero-width characters
    zeroWidth: /[\u200B\u200C\u200D\u2060\uFEFF]/g,

    // Homoglyph substitutions (visual lookalikes)
    homoglyphs: {
        'а': 'a', 'е': 'e', 'о': 'o', 'р': 'p', 'с': 'c', 'у': 'y', 'х': 'x',  // Cyrillic
        'ａ': 'a', 'ｂ': 'b', 'ｃ': 'c', 'ｄ': 'd', 'ｅ': 'e', 'ｆ': 'f',          // Fullwidth
        '𝐚': 'a', '𝐛': 'b', '𝐜': 'c', '𝐝': 'd', '𝐞': 'e', '𝐟': 'f',          // Math bold
        '𝑎': 'a', '𝑏': 'b', '𝑐': 'c', '𝑑': 'd', '𝑒': 'e', '𝑓': 'f'           // Math italic
    },

    // Repeated special characters
    repeatedSpecials: /[!@#$%^&*()_+=\-\[\]{}|;:'",.<>?/\\`~]{3,}/g
};

// ============================================
// HIGH-SEVERITY KEYWORDS (automatic escalation)
// ============================================

const AUTO_HIGH_SEVERITY = {
    en: ['fuck', 'cunt', 'bitch', 'nigger', 'nigga', 'faggot', 'retard'],
    ar: ['كسم', 'كسمك', 'اقتلك', 'ادبحك', 'هقتلك', 'هدبحك', 'ابن الشرموطه'],
    arabizi: ['kosom', 'kesom', 'kosomak', 'ha2tlak', 'ibn el sharmoota']
};

const AUTO_CRITICAL_SEVERITY = {
    en: ['kill you', 'murder you', 'bomb', 'shoot you'],
    ar: ['هفجرك', 'هقتل عيلتك', 'موت'],
    arabizi: ['bomb', 'hafagarrak']
};

// ============================================
// REGEX PATTERN STORAGE
// ============================================

let COMPILED_PATTERNS = {
    en: { all: [], high: [], critical: [] },
    ar: { all: [], high: [], critical: [] },
    arabizi: { all: [], high: [], critical: [] }
};

let PATTERNS_INITIALIZED = false;

// ============================================
// RESULT CACHE
// ============================================

const resultCache = new Map();
let cacheStats = { hits: 0, misses: 0 };

/**
 * Generate cache key from text
 */
function getCacheKey(text) {
    // Simple hash for cache key
    let hash = 0;
    for (let i = 0; i < text.length; i++) {
        const char = text.charCodeAt(i);
        hash = ((hash << 5) - hash) + char;
        hash = hash & hash; // Convert to 32-bit integer
    }
    return hash.toString(36);
}

/**
 * Get cached result
 */
function getCached(text) {
    if (!CONFIG.cacheEnabled) return null;

    const key = getCacheKey(text);
    const cached = resultCache.get(key);

    if (cached && (Date.now() - cached.timestamp) < CONFIG.cacheTTL) {
        cacheStats.hits++;
        return cached.result;
    }

    cacheStats.misses++;
    return null;
}

/**
 * Set cached result
 */
function setCache(text, result) {
    if (!CONFIG.cacheEnabled) return;

    const key = getCacheKey(text);
    resultCache.set(key, { result, timestamp: Date.now() });

    // Limit cache size
    if (resultCache.size > 10000) {
        const firstKey = resultCache.keys().next().value;
        resultCache.delete(firstKey);
    }
}

/**
 * Clear cache
 */
function clearCache() {
    resultCache.clear();
    cacheStats = { hits: 0, misses: 0 };
}

/**
 * Get cache statistics
 */
function getCacheStats() {
    return {
        ...cacheStats,
        size: resultCache.size,
        hitRate: cacheStats.hits / (cacheStats.hits + cacheStats.misses) || 0
    };
}

// ============================================
// NORMALIZATION FUNCTIONS
// ============================================

/**
 * Remove zero-width and invisible characters
 */
function removeInvisibleChars(text) {
    return text.replace(EVASION_PATTERNS.zeroWidth, '');
}

/**
 * Replace homoglyph characters with ASCII equivalents
 */
function normalizeHomoglyphs(text) {
    let result = text;
    for (const [homoglyph, replacement] of Object.entries(EVASION_PATTERNS.homoglyphs)) {
        result = result.replace(new RegExp(homoglyph, 'g'), replacement);
    }
    return result;
}

/**
 * Normalize English text for profanity detection
 * - Remove invisible characters
 * - Normalize homoglyphs
 * - Lowercase
 * - Leetspeak substitutions (0→o, 1→i, 3→e, 4→a, 5→s, 7→t, @→a, $→s)
 * - Remove punctuation to spaces (Unicode-safe)
 * - Collapse repeated letters (fuuuck → fuuck)
 * - Collapse whitespace
 */
function normalizeEnglish(s) {
    if (!s) return '';

    let text = s;

    // Remove invisible characters
    text = removeInvisibleChars(text);

    // Normalize homoglyphs
    text = normalizeHomoglyphs(text);

    // Lowercase
    text = text.toLowerCase();

    // Extended leetspeak substitutions
    if (CONFIG.enableLeetspeakDecode) {
        text = text.replace(/0/g, 'o');
        text = text.replace(/1/g, 'i');
        text = text.replace(/3/g, 'e');
        text = text.replace(/4/g, 'a');
        text = text.replace(/5/g, 's');
        text = text.replace(/7/g, 't');
        text = text.replace(/@/g, 'a');
        text = text.replace(/\$/g, 's');
        text = text.replace(/!/g, 'i');
        text = text.replace(/\|/g, 'l');
        text = text.replace(/8/g, 'b');
        text = text.replace(/9/g, 'g');
        text = text.replace(/\+/g, 't');
        text = text.replace(/ph/g, 'f');
    }

    // Remove punctuation to spaces (Unicode-safe)
    text = text.replace(/[\p{P}\p{S}]/gu, ' ');

    // Collapse repeated English letters (fuuuck → fuck)
    text = text.replace(/([a-z])\1{2,}/gi, '$1$1');

    // Collapse whitespace
    text = text.replace(/\s+/g, ' ').trim();

    return text;
}

/**
 * Normalize Arabic text for profanity detection (Egyptian-safe)
 * - Remove invisible characters
 * - Remove diacritics and tatweel
 * - Normalize alef variants (أإآ → ا)
 * - Normalize ى → ي
 * - Normalize ة → ه
 * - Normalize ؤ → و
 * - Normalize ئ → ي
 * - Punctuation to spaces (Arabic-script safe)
 * - Collapse repeated Arabic letters: احاااا → احا
 */
function normalizeArabic(s) {
    if (!s) return '';

    let text = s;

    // Remove invisible characters
    text = removeInvisibleChars(text);

    // Remove diacritics (tashkeel)
    text = text.replace(/[\u064B-\u065F\u0670]/g, '');

    // Remove tatweel (kashida)
    text = text.replace(/\u0640/g, '');

    // Normalize alef variants (أإآ → ا)
    text = text.replace(/[أإآ]/g, 'ا');

    // Normalize alef maqsura (ى → ي)
    text = text.replace(/ى/g, 'ي');

    // Normalize taa marbuta (ة → ه)
    text = text.replace(/ة/g, 'ه');

    // Normalize waw with hamza (ؤ → و)
    text = text.replace(/ؤ/g, 'و');

    // Normalize yaa with hamza (ئ → ي)
    text = text.replace(/ئ/g, 'ي');

    // Punctuation to spaces (Arabic-script safe)
    text = text.replace(/[\p{P}\p{S}]/gu, ' ');

    // Collapse repeated Arabic letters: احاااا → احا
    text = text.replace(/([\u0621-\u064A])\1{2,}/g, '$1$1');

    // Collapse whitespace
    text = text.replace(/\s+/g, ' ').trim();

    return text;
}

/**
 * Normalize Arabizi text
 * Similar to English but preserves number-letter patterns
 */
function normalizeArabizi(s) {
    if (!s) return '';

    let text = s;

    // Remove invisible characters
    text = removeInvisibleChars(text);

    // Lowercase
    text = text.toLowerCase();

    // Normalize common Arabizi variations
    text = text.replace(/3'/g, 'gh');  // 3' = غ
    text = text.replace(/7'/g, 'kh');  // 7' = خ
    text = text.replace(/'/g, "'");    // Normalize quotes

    // Remove punctuation (except numbers used in Arabizi)
    text = text.replace(/[^\w\s234567890]/g, ' ');

    // Collapse whitespace
    text = text.replace(/\s+/g, ' ').trim();

    return text;
}

/**
 * Create a loose regex that matches letters even if user inserts symbols/spaces
 * Example: f*u*c*k, f u c k, ك*س*م
 * 
 * @param {string} word - The word to create a pattern for
 * @param {Object} options - Configuration options
 * @returns {RegExp|null} Compiled regex or null if word is too short
 */
function looseWordRegex(word, options = {}) {
    const {
        allowBoundary = true,
        maxGap = 2
    } = options;

    if (!word || word.length < CONFIG.minWordLength) return null;

    // Escape special regex characters
    const escaped = word.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

    // Split into characters and join with [\W_]* (matches non-word chars including spaces/symbols)
    const chars = Array.from(escaped);
    const gapPattern = `[\\W_]{0,${maxGap}}`;
    const pattern = chars.join(gapPattern);

    // Add word boundary if requested
    const fullPattern = allowBoundary ? `(?:^|\\W)${pattern}(?:\\W|$)` : pattern;

    // Use flags: i (case-insensitive), u (Unicode)
    try {
        return new RegExp(fullPattern, 'iu');
    } catch (e) {
        console.error(`Failed to compile regex for word: ${word}`, e);
        return null;
    }
}

/**
 * Create exact match regex (faster, for common words)
 */
function exactWordRegex(word) {
    if (!word || word.length < CONFIG.minWordLength) return null;

    const escaped = word.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

    try {
        return new RegExp(`\\b${escaped}\\b`, 'iu');
    } catch (e) {
        return null;
    }
}

// ============================================
// PATTERN INITIALIZATION
// ============================================

/**
 * Initialize all regex patterns
 * Called at module load for performance
 */
function initRegexPatterns() {
    if (PATTERNS_INITIALIZED) return;

    const startTime = Date.now();

    // Compile English patterns
    COMPILED_PATTERNS.en.all = EN_PROFANITY_FLAT
        .map(word => CONFIG.enableFuzzyMatch ? looseWordRegex(word) : exactWordRegex(word))
        .filter(Boolean);

    COMPILED_PATTERNS.en.high = AUTO_HIGH_SEVERITY.en
        .map(word => looseWordRegex(word))
        .filter(Boolean);

    COMPILED_PATTERNS.en.critical = AUTO_CRITICAL_SEVERITY.en
        .map(phrase => new RegExp(phrase.replace(/\s+/g, '\\s+'), 'iu'))
        .filter(Boolean);

    // Compile Arabic patterns
    COMPILED_PATTERNS.ar.all = AR_PROFANITY_FLAT
        .map(word => CONFIG.enableFuzzyMatch ? looseWordRegex(word) : exactWordRegex(word))
        .filter(Boolean);

    COMPILED_PATTERNS.ar.high = AUTO_HIGH_SEVERITY.ar
        .map(word => looseWordRegex(word))
        .filter(Boolean);

    COMPILED_PATTERNS.ar.critical = AUTO_CRITICAL_SEVERITY.ar
        .map(phrase => looseWordRegex(phrase))
        .filter(Boolean);

    // Compile Arabizi patterns
    COMPILED_PATTERNS.arabizi.all = ARABIZI_PROFANITY
        .map(word => looseWordRegex(word))
        .filter(Boolean);

    COMPILED_PATTERNS.arabizi.high = AUTO_HIGH_SEVERITY.arabizi
        .map(word => looseWordRegex(word))
        .filter(Boolean);

    COMPILED_PATTERNS.arabizi.critical = AUTO_CRITICAL_SEVERITY.arabizi
        .map(phrase => looseWordRegex(phrase))
        .filter(Boolean);

    PATTERNS_INITIALIZED = true;

    if (CONFIG.enableDebugLogging) {
        console.log(`Patterns initialized in ${Date.now() - startTime}ms`);
        console.log(`Total patterns: EN=${COMPILED_PATTERNS.en.all.length}, AR=${COMPILED_PATTERNS.ar.all.length}, Arabizi=${COMPILED_PATTERNS.arabizi.all.length}`);
    }
}

// ============================================
// LANGUAGE DETECTION
// ============================================

/**
 * Language detection result
 * @typedef {Object} LanguageResult
 * @property {string} primary - Primary detected language (en, ar, mixed, unknown)
 * @property {number} arabicRatio - Ratio of Arabic characters
 * @property {number} latinRatio - Ratio of Latin characters
 * @property {boolean} hasArabizi - Whether Arabizi patterns detected
 * @property {number} confidence - Detection confidence (0-1)
 */

/**
 * Detect user language with confidence score
 * 
 * @param {string} text - Input text
 * @returns {LanguageResult} Detection result
 */
function detectUserLanguage(text) {
    if (!text || typeof text !== 'string') {
        return {
            primary: 'unknown',
            arabicRatio: 0,
            latinRatio: 0,
            hasArabizi: false,
            confidence: 0
        };
    }

    // Count character types
    const arabicChars = (text.match(/\p{Script=Arabic}/gu) || []).length;
    const latinChars = (text.match(/[A-Za-z]/g) || []).length;
    const totalAlpha = arabicChars + latinChars;

    // Fix: If we have Arabic chars but no Latin, it's Arabic (even for single words)
    if (arabicChars > 0 && latinChars === 0) {
        return {
            primary: 'ar',
            arabicRatio: 1.0,
            latinRatio: 0,
            hasArabizi: false,
            confidence: 1.0
        };
    }

    // Fix: If we have Latin chars but no Arabic, check for Arabizi or common unsupported languages
    if (latinChars > 0 && arabicChars === 0) {
        const arabiziPattern = /\b\w*[234567]\w*\b/g;
        const hasArabizi = arabiziPattern.test(text);

        // Check for common unsupported language patterns (French, Spanish, etc.)
        // Simple heuristic: if text contains common words from unsupported languages
        const unsupportedPatterns = [
            /\b(bonjour|salut|merci|oui|non|comment|aller)\b/i,  // French
            /\b(hola|gracias|si|no|como|estas|amigo)\b/i,        // Spanish
            /\b(guten|tag|danke|ja|nein|wie|geht)\b/i,          // German
            /\b(ciao|grazie|si|no|come|stai)\b/i                 // Italian
        ];

        const isUnsupported = unsupportedPatterns.some(pattern => pattern.test(text));

        if (isUnsupported) {
            return {
                primary: 'unknown',
                arabicRatio: 0,
                latinRatio: 1.0,
                hasArabizi: false,
                confidence: 0.7
            };
        }

        return {
            primary: hasArabizi ? 'arabizi' : 'en',
            arabicRatio: 0,
            latinRatio: 1.0,
            hasArabizi: hasArabizi,
            confidence: 1.0
        };
    }

    if (totalAlpha === 0) {
        return {
            primary: 'unknown',
            arabicRatio: 0,
            latinRatio: 0,
            hasArabizi: false,
            confidence: 0
        };
    }

    const arabicRatio = arabicChars / totalAlpha;
    const latinRatio = latinChars / totalAlpha;

    // Check for Arabizi patterns (numbers + Latin letters in specific patterns)
    const arabiziPattern = /\b\w*[234567]\w*\b/g;
    const hasArabizi = arabiziPattern.test(text) && latinRatio > 0.5;

    // Determine primary language (lower threshold for better detection of mixed/short texts)
    let primary;
    let confidence;

    if (arabicRatio >= 0.5) {
        // If Arabic is 50% or more, consider it Arabic
        primary = arabicRatio >= 0.8 ? 'ar' : 'mixed';
        confidence = arabicRatio;
    } else if (latinRatio >= 0.5) {
        primary = hasArabizi ? 'arabizi' : (latinRatio >= 0.8 ? 'en' : 'mixed');
        confidence = latinRatio;
    } else {
        primary = 'unknown';
        confidence = 0;
    }

    return {
        primary,
        arabicRatio,
        latinRatio,
        hasArabizi,
        confidence
    };
}

/**
 * Simple language detection (backward compatible)
 * @param {string} text - Input text
 * @returns {string} Language code
 */
function detectLanguageSimple(text) {
    return detectUserLanguage(text).primary;
}

// ============================================
// PROFANITY DETECTION
// ============================================

/**
 * Profanity check result
 * @typedef {Object} ProfanityResult
 * @property {string} lang - Detected language
 * @property {boolean} flagged - Whether profanity was detected
 * @property {string} severity - Severity level (none, low, medium, high, critical)
 * @property {string[]} matches - Match categories found
 * @property {Object} details - Detailed match information
 * @property {number} processingTime - Processing time in ms
 */

/**
 * Check profanity in text
 * 
 * @param {string} text - Input text to check
 * @param {Object} options - Check options
 * @param {boolean} options.includeDetails - Include detailed match info
 * @param {boolean} options.skipCache - Skip cache lookup
 * @returns {ProfanityResult}
 */
function checkProfanity(text, options = {}) {
    const startTime = performance.now ? performance.now() : Date.now();

    const {
        includeDetails = false,
        skipCache = false
    } = options;

    // Validate input
    if (!text || typeof text !== 'string') {
        return {
            lang: 'unknown',
            flagged: false,
            severity: 'none',
            matches: [],
            details: {},
            processingTime: 0
        };
    }

    // Truncate long messages
    const truncatedText = text.length > CONFIG.maxMessageLength
        ? text.substring(0, CONFIG.maxMessageLength)
        : text;

    // Check cache
    if (!skipCache) {
        const cached = getCached(truncatedText);
        if (cached) {
            return { ...cached, fromCache: true };
        }
    }

    // Initialize patterns if needed
    if (!PATTERNS_INITIALIZED) {
        initRegexPatterns();
    }

    // Detect language
    const langResult = detectUserLanguage(truncatedText);
    const lang = langResult.primary;

    // Normalize texts
    const normalizedEnglish = normalizeEnglish(truncatedText);
    const normalizedArabic = normalizeArabic(truncatedText);
    const normalizedArabizi = normalizeArabizi(truncatedText);

    const matches = [];
    const details = {
        en: [],
        ar: [],
        arabizi: []
    };

    let severity = 'none';

    // Helper to update severity
    const updateSeverity = (newSeverity) => {
        const severityOrder = ['none', 'low', 'medium', 'high', 'critical'];
        if (severityOrder.indexOf(newSeverity) > severityOrder.indexOf(severity)) {
            severity = newSeverity;
        }
    };

    // Check Arabic (if Arabic or mixed)
    if (lang === 'ar' || lang === 'mixed') {
        // Check critical severity first
        for (const regex of COMPILED_PATTERNS.ar.critical) {
            if (regex && regex.test(normalizedArabic)) {
                matches.push('ar_critical');
                details.ar.push({ type: 'critical', pattern: regex.source });
                updateSeverity('critical');
                break;
            }
        }

        // Check high severity
        if (severity !== 'critical') {
            for (const regex of COMPILED_PATTERNS.ar.high) {
                if (regex && regex.test(normalizedArabic)) {
                    matches.push('ar_high');
                    details.ar.push({ type: 'high', pattern: regex.source });
                    updateSeverity('high');
                    break;
                }
            }
        }

        // Check all patterns for medium
        if (severity === 'none') {
            for (const regex of COMPILED_PATTERNS.ar.all) {
                if (regex && regex.test(normalizedArabic)) {
                    matches.push('ar_profanity');
                    details.ar.push({ type: 'medium', pattern: regex.source });
                    updateSeverity('medium');
                    break;
                }
            }
        }
    }

    // Check English (if English, mixed, Arabizi, or unknown)
    if (['en', 'mixed', 'arabizi', 'unknown'].includes(lang)) {
        // Check critical severity
        for (const regex of COMPILED_PATTERNS.en.critical) {
            if (regex && regex.test(normalizedEnglish)) {
                matches.push('en_critical');
                details.en.push({ type: 'critical', pattern: regex.source });
                updateSeverity('critical');
                break;
            }
        }

        // Check high severity
        if (severity !== 'critical') {
            for (const regex of COMPILED_PATTERNS.en.high) {
                if (regex && regex.test(normalizedEnglish)) {
                    matches.push('en_high');
                    details.en.push({ type: 'high', pattern: regex.source });
                    updateSeverity('high');
                    break;
                }
            }
        }

        // Check all patterns
        if (severity === 'none') {
            for (const regex of COMPILED_PATTERNS.en.all) {
                if (regex && regex.test(normalizedEnglish)) {
                    matches.push('en_profanity');
                    details.en.push({ type: 'medium', pattern: regex.source });
                    updateSeverity('medium');
                    break;
                }
            }
        }
    }

    // Check Arabizi (if Arabizi, mixed, or unknown)
    if (['arabizi', 'mixed', 'unknown', 'en'].includes(lang)) {
        // Check critical severity
        for (const regex of COMPILED_PATTERNS.arabizi.critical) {
            if (regex && regex.test(normalizedArabizi)) {
                matches.push('arabizi_critical');
                details.arabizi.push({ type: 'critical', pattern: regex.source });
                updateSeverity('critical');
                break;
            }
        }

        // Check high severity
        if (severity !== 'critical') {
            for (const regex of COMPILED_PATTERNS.arabizi.high) {
                if (regex && regex.test(normalizedArabizi)) {
                    matches.push('arabizi_high');
                    details.arabizi.push({ type: 'high', pattern: regex.source });
                    updateSeverity('high');
                    break;
                }
            }
        }

        // Check all patterns
        if (severity === 'none' || severity === 'low') {
            for (const regex of COMPILED_PATTERNS.arabizi.all) {
                if (regex && regex.test(normalizedArabizi)) {
                    matches.push('arabizi_profanity');
                    details.arabizi.push({ type: 'medium', pattern: regex.source });
                    updateSeverity('medium');
                    break;
                }
            }
        }
    }

    const flagged = severity !== 'none';
    const processingTime = (performance.now ? performance.now() : Date.now()) - startTime;

    const result = {
        lang,
        flagged,
        severity,
        matches,
        details: includeDetails ? details : undefined,
        processingTime: Math.round(processingTime * 100) / 100
    };

    // Cache result
    setCache(truncatedText, result);

    return result;
}

/**
 * Quick check - simplified result for high-performance scenarios
 * @param {string} text - Input text
 * @returns {{ flagged: boolean, severity: string }}
 */
function quickCheck(text) {
    const result = checkProfanity(text);
    return {
        flagged: result.flagged,
        severity: result.severity
    };
}

/**
 * Batch check multiple texts
 * @param {string[]} texts - Array of texts to check
 * @returns {ProfanityResult[]}
 */
function batchCheck(texts) {
    if (!Array.isArray(texts)) return [];
    return texts.map(text => checkProfanity(text));
}

// ============================================
// STATISTICS AND DEBUGGING
// ============================================

/**
 * Get moderation system statistics
 */
function getStats() {
    return {
        patternsInitialized: PATTERNS_INITIALIZED,
        patternCounts: {
            en: COMPILED_PATTERNS.en.all.length,
            ar: COMPILED_PATTERNS.ar.all.length,
            arabizi: COMPILED_PATTERNS.arabizi.all.length
        },
        cache: getCacheStats(),
        config: { ...CONFIG }
    };
}

/**
 * Update configuration
 * @param {Object} newConfig - Configuration updates
 */
function updateConfig(newConfig) {
    Object.assign(CONFIG, newConfig);

    // Reinitialize patterns if fuzzy match setting changed
    if ('enableFuzzyMatch' in newConfig) {
        PATTERNS_INITIALIZED = false;
        initRegexPatterns();
    }
}

/**
 * Test a specific word against patterns (debugging)
 * @param {string} word - Word to test
 * @param {string} langGroup - Language group (en, ar, arabizi)
 * @returns {Object} Test result
 */
function testWord(word, langGroup = 'en') {
    if (!PATTERNS_INITIALIZED) initRegexPatterns();

    const patterns = COMPILED_PATTERNS[langGroup]?.all || [];
    const normalizers = {
        en: normalizeEnglish,
        ar: normalizeArabic,
        arabizi: normalizeArabizi
    };

    const normalize = normalizers[langGroup] || normalizeEnglish;
    const normalized = normalize(word);

    const matchingPatterns = patterns
        .filter(p => p && p.test(normalized))
        .map(p => p.source);

    return {
        original: word,
        normalized,
        langGroup,
        matches: matchingPatterns,
        flagged: matchingPatterns.length > 0
    };
}

// ============================================
// INITIALIZATION
// ============================================

// Initialize patterns at module load
initRegexPatterns();

// ============================================
// EXPORTS
// ============================================

module.exports = {
    // Configuration
    CONFIG,
    updateConfig,

    // Word lists (for external use/extension)
    EN_PROFANITY,
    AR_PROFANITY,
    ARABIZI_PROFANITY,

    // Normalization functions
    normalizeEnglish,
    normalizeArabic,
    normalizeArabizi,
    removeInvisibleChars,
    normalizeHomoglyphs,

    // Regex utilities
    looseWordRegex,
    exactWordRegex,

    // Language detection
    detectUserLanguage,
    detectLanguageSimple,

    // Profanity checking
    checkProfanity,
    quickCheck,
    batchCheck,

    // Cache management
    clearCache,
    getCacheStats,

    // Statistics and debugging
    getStats,
    testWord,

    // Pattern reinitialization
    initRegexPatterns
};
