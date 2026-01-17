// ============================================
// 🎯 QUICK REPLIES & SMART OPTIONS V3.4
// Makes chatbot easy to use - minimal typing!
// ============================================

/**
 * Quick reply configurations for all scenarios
 * Customer should rarely need to type - just tap options!
 */

const QUICK_REPLIES = {
    // Main menu - first interaction
    MAIN_MENU: {
        ar: ['🚗 احجز رحلة', '📍 تتبع رحلتي', '📋 رحلاتي السابقة', '🎧 مساعدة'],
        en: ['🚗 Book Ride', '📍 Track My Ride', '📋 My Trips', '🎧 Help']
    },
    
    // After greeting
    GREETING: {
        ar: ['🚗 احجز رحلة', '📋 حالة رحلتي', '💰 رصيدي', '🎧 مساعدة'],
        en: ['🚗 Book Ride', '📋 Trip Status', '💰 My Balance', '🎧 Help']
    },
    
    // Booking flow - pickup
    PICKUP_OPTIONS: {
        ar: ['📍 موقعي الحالي', '🏠 البيت', '🏢 الشغل', '✏️ مكان آخر'],
        en: ['📍 Current Location', '🏠 Home', '🏢 Work', '✏️ Other Place']
    },
    
    // Booking flow - popular destinations
    DESTINATION_SUGGESTIONS: {
        ar: ['🏠 البيت', '🏢 الشغل', '🛒 المول', '✈️ المطار', '✏️ مكان آخر'],
        en: ['🏠 Home', '🏢 Work', '🛒 Mall', '✈️ Airport', '✏️ Other Place']
    },
    
    // Vehicle selection
    VEHICLE_TYPES: {
        ar: ['🚗 اقتصادي', '🚙 كومفورت', '🚘 بريميوم'],
        en: ['🚗 Economy', '🚙 Comfort', '🚘 Premium']
    },
    
    // Booking confirmation
    CONFIRM_BOOKING: {
        ar: ['✅ تأكيد الحجز', '🔄 تغيير السيارة', '❌ إلغاء'],
        en: ['✅ Confirm', '🔄 Change Vehicle', '❌ Cancel']
    },
    
    // During active trip
    ACTIVE_TRIP: {
        ar: ['📍 فين الكابتن؟', '📞 اتصل بالكابتن', '🛑 إلغاء الرحلة', '⚠️ مشكلة'],
        en: ['📍 Where\'s driver?', '📞 Call Driver', '🛑 Cancel Trip', '⚠️ Problem']
    },
    
    // Trip tracking
    TRIP_TRACKING: {
        ar: ['⏱️ فاضل كام؟', '📞 اتصل بالكابتن', '📍 شارك موقعي', '🛑 إلغاء'],
        en: ['⏱️ ETA?', '📞 Call Driver', '📍 Share Location', '🛑 Cancel']
    },
    
    // Cancel confirmation
    CANCEL_CONFIRM: {
        ar: ['✅ نعم، إلغاء', '❌ لا، استمر'],
        en: ['✅ Yes, Cancel', '❌ No, Continue']
    },
    
    // Issue reporting - categories
    ISSUE_CATEGORIES: {
        ar: ['🚗 مشكلة في السيارة', '👨‍✈️ مشكلة مع الكابتن', '💰 مشكلة في السعر', '⚙️ مشكلة تقنية', '🚨 طوارئ'],
        en: ['🚗 Vehicle Issue', '👨‍✈️ Captain Issue', '💰 Pricing Issue', '⚙️ Technical Issue', '🚨 Emergency']
    },
    
    // Vehicle issues
    VEHICLE_ISSUES: {
        ar: ['🧹 نظافة السيارة', '❄️ التكييف لا يعمل', '⚠️ مخاوف أمان', '🚗 سيارة مختلفة'],
        en: ['🧹 Cleanliness', '❄️ AC Not Working', '⚠️ Safety Concern', '🚗 Different Vehicle']
    },
    
    // Captain issues
    CAPTAIN_ISSUES: {
        ar: ['😤 سلوك غير لائق', '🚗 قيادة غير آمنة', '📵 الكابتن لا يرد', '🗺️ مسار خاطئ', '❌ طلب مني الإلغاء', '💵 طلب دفع نقدي'],
        en: ['😤 Rude Behavior', '🚗 Unsafe Driving', '📵 Not Responding', '🗺️ Wrong Route', '❌ Asked to Cancel', '💵 Asked for Cash']
    },
    
    // Pricing issues
    PRICING_ISSUES: {
        ar: ['💸 تم تحصيل مبلغ زائد', '❌ السعر خاطئ', '🎟️ الخصم لم يُطبق'],
        en: ['💸 Overcharged', '❌ Wrong Fare', '🎟️ Promo Not Applied']
    },
    
    // Technical issues
    TECHNICAL_ISSUES: {
        ar: ['📱 التطبيق يتوقف', '💳 فشل الدفع', '📍 مشكلة في الموقع', '🚫 لا أستطيع الحجز'],
        en: ['📱 App Crashes', '💳 Payment Failed', '📍 GPS Issue', '🚫 Cannot Book']
    },
    
    // Emergency types
    EMERGENCY_TYPES: {
        ar: ['🚨 حادث', '⚠️ تهديد/خطر', '🏥 حالة طبية'],
        en: ['🚨 Accident', '⚠️ Threat/Danger', '🏥 Medical Emergency']
    },
    
    // Manipulation detected
    MANIPULATION_OPTIONS: {
        ar: ['📝 إبلاغ عن الكابتن', '🔄 استمر في الرحلة', '🎧 تواصل مع الدعم'],
        en: ['📝 Report Captain', '🔄 Continue Trip', '🎧 Contact Support']
    },
    
    // Evidence options
    EVIDENCE_OPTIONS: {
        ar: ['📸 لدي صورة', '🎙️ لدي تسجيل', '📝 وصف فقط', '❌ إلغاء البلاغ'],
        en: ['📸 I Have Image', '🎙️ I Have Recording', '📝 Describe Only', '❌ Cancel Report']
    },
    
    // After report submitted
    AFTER_REPORT: {
        ar: ['🏠 القائمة الرئيسية', '🚗 احجز رحلة جديدة', '🎧 تواصل مع الدعم'],
        en: ['🏠 Main Menu', '🚗 Book New Ride', '🎧 Contact Support']
    },
    
    // Rating options
    RATING_OPTIONS: {
        ar: ['⭐', '⭐⭐', '⭐⭐⭐', '⭐⭐⭐⭐', '⭐⭐⭐⭐⭐'],
        en: ['⭐', '⭐⭐', '⭐⭐⭐', '⭐⭐⭐⭐', '⭐⭐⭐⭐⭐']
    },
    
    // After low rating
    LOW_RATING_FOLLOWUP: {
        ar: ['👨‍✈️ مشكلة مع الكابتن', '🚗 مشكلة في السيارة', '💰 مشكلة في السعر', '✅ لا شكرا'],
        en: ['👨‍✈️ Captain Issue', '🚗 Vehicle Issue', '💰 Pricing Issue', '✅ No Thanks']
    },
    
    // Promo code
    PROMO_OPTIONS: {
        ar: ['🎟️ لدي كود', '❌ لا، احجز بدون كود'],
        en: ['🎟️ I Have Code', '❌ No, Book Without Code']
    },
    
    // Schedule ride
    SCHEDULE_OPTIONS: {
        ar: ['⏰ بعد ساعة', '🌅 بكرة الصبح', '🌆 بكرة بالليل', '📅 وقت آخر'],
        en: ['⏰ In 1 Hour', '🌅 Tomorrow Morning', '🌆 Tomorrow Evening', '📅 Other Time']
    },
    
    // Help menu
    HELP_OPTIONS: {
        ar: ['📋 مشكلة في رحلة', '💰 مشكلة في الدفع', '📱 مشكلة في التطبيق', '🎧 كلمني موظف'],
        en: ['📋 Trip Issue', '💰 Payment Issue', '📱 App Issue', '🎧 Talk to Agent']
    },
    
    // Yes/No simple
    YES_NO: {
        ar: ['✅ نعم', '❌ لا'],
        en: ['✅ Yes', '❌ No']
    },
    
    // Continue or cancel
    CONTINUE_CANCEL: {
        ar: ['▶️ استمر', '❌ إلغاء'],
        en: ['▶️ Continue', '❌ Cancel']
    },
    
    // Back to menu
    BACK_TO_MENU: {
        ar: ['🏠 القائمة الرئيسية'],
        en: ['🏠 Main Menu']
    }
};

/**
 * Get quick replies for a specific scenario
 * @param {string} scenario - Scenario key
 * @param {string} language - Language code ('ar' or 'en')
 * @returns {string[]} - Array of quick reply options
 */
function getQuickReplies(scenario, language = 'ar') {
    const replies = QUICK_REPLIES[scenario];
    if (!replies) {
        return QUICK_REPLIES.MAIN_MENU[language] || QUICK_REPLIES.MAIN_MENU.ar;
    }
    return replies[language] || replies.ar;
}

/**
 * Get smart suggestions based on context
 * @param {Object} context - Current context (state, user history, etc.)
 * @param {string} language - Language code
 * @returns {string[]} - Smart suggestions
 */
function getSmartSuggestions(context, language = 'ar') {
    const { state, hasActiveTrip, lastTrip, favorites } = context;
    
    // During active trip
    if (hasActiveTrip) {
        return getQuickReplies('ACTIVE_TRIP', language);
    }
    
    // Based on current state
    switch (state) {
        case 'START':
            return getQuickReplies('MAIN_MENU', language);
        case 'AWAITING_PICKUP':
            // Add favorites if available
            if (favorites && favorites.length > 0) {
                const favOptions = favorites.slice(0, 3).map(f => `📍 ${f.name}`);
                return [...favOptions, language === 'ar' ? '✏️ مكان آخر' : '✏️ Other Place'];
            }
            return getQuickReplies('PICKUP_OPTIONS', language);
        case 'AWAITING_DESTINATION':
            if (favorites && favorites.length > 0) {
                const favOptions = favorites.slice(0, 3).map(f => `📍 ${f.name}`);
                return [...favOptions, language === 'ar' ? '✏️ مكان آخر' : '✏️ Other Place'];
            }
            return getQuickReplies('DESTINATION_SUGGESTIONS', language);
        case 'AWAITING_RIDE_TYPE':
            return getQuickReplies('VEHICLE_TYPES', language);
        case 'AWAITING_CONFIRMATION':
            return getQuickReplies('CONFIRM_BOOKING', language);
        case 'TRIP_ACTIVE':
            return getQuickReplies('ACTIVE_TRIP', language);
        case 'AWAITING_CANCEL_CONFIRM':
            return getQuickReplies('CANCEL_CONFIRM', language);
        case 'AWAITING_ISSUE_CATEGORY':
            return getQuickReplies('ISSUE_CATEGORIES', language);
        case 'AWAITING_RATING':
            return getQuickReplies('RATING_OPTIONS', language);
        default:
            return getQuickReplies('MAIN_MENU', language);
    }
}

/**
 * Format response with quick replies
 * @param {string} message - Response message
 * @param {string[]} quickReplies - Quick reply options
 * @param {Object} options - Additional options
 * @returns {Object} - Formatted response
 */
function formatResponseWithOptions(message, quickReplies, options = {}) {
    return {
        message,
        quick_replies: quickReplies,
        action: options.action || 'none',
        data: options.data || {},
        ui_hint: options.ui_hint || null
    };
}

/**
 * Get numbered options for selection
 * @param {string[]} options - Options array
 * @param {string} language - Language code
 * @returns {string} - Formatted numbered list
 */
function formatNumberedOptions(options, language = 'ar') {
    const header = language === 'ar' ? '👆 اختر رقم:' : '👆 Choose number:';
    const numbered = options.map((opt, i) => `${i + 1}. ${opt}`).join('\n');
    return `${header}\n\n${numbered}`;
}

module.exports = {
    QUICK_REPLIES,
    getQuickReplies,
    getSmartSuggestions,
    formatResponseWithOptions,
    formatNumberedOptions
};

