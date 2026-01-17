// ============================================
// 💬 SMART RESPONSES V3.4.1
// Pre-built responses for all scenarios
// Fast, consistent, minimal typing for customer
// ============================================

const { getQuickReplies } = require('./quickReplies');

/**
 * All pre-built responses organized by scenario
 * Each response has: message, quick_replies, action, data
 */
const RESPONSES = {
    // ============================================
    // 👋 GREETINGS & MAIN MENU
    // ============================================
    GREETING: {
        ar: {
            message: '👋 أهلاً بك في سمارت لاين!\n\nكيف أقدر أساعدك؟',
            quick_replies: ['🚗 احجز رحلة', '📍 تتبع رحلتي', '📋 رحلاتي السابقة', '🎧 مساعدة']
        },
        en: {
            message: '👋 Welcome to SmartLine!\n\nHow can I help you?',
            quick_replies: ['🚗 Book Ride', '📍 Track My Ride', '📋 My Trips', '🎧 Help']
        }
    },
    
    MAIN_MENU: {
        ar: {
            message: '🏠 القائمة الرئيسية\n\nاختر ما تريد:',
            quick_replies: ['🚗 احجز رحلة', '📍 تتبع رحلتي', '💰 رصيدي', '🎧 مساعدة']
        },
        en: {
            message: '🏠 Main Menu\n\nChoose an option:',
            quick_replies: ['🚗 Book Ride', '📍 Track My Ride', '💰 My Balance', '🎧 Help']
        }
    },
    
    // ============================================
    // 🚗 BOOKING FLOW
    // ============================================
    ASK_PICKUP: {
        ar: {
            message: '📍 من فين عايز تتحرك؟',
            quick_replies: ['📍 موقعي الحالي', '🏠 البيت', '🏢 الشغل', '✏️ مكان آخر']
        },
        en: {
            message: '📍 Where do you want to be picked up?',
            quick_replies: ['📍 Current Location', '🏠 Home', '🏢 Work', '✏️ Other Place']
        }
    },
    
    ASK_DESTINATION: {
        ar: {
            message: '📍 عايز تروح فين؟',
            quick_replies: ['🏠 البيت', '🏢 الشغل', '🛒 المول', '✈️ المطار', '✏️ مكان آخر']
        },
        en: {
            message: '📍 Where do you want to go?',
            quick_replies: ['🏠 Home', '🏢 Work', '🛒 Mall', '✈️ Airport', '✏️ Other Place']
        }
    },
    
    ASK_VEHICLE: {
        ar: {
            message: '🚗 اختر نوع السيارة:',
            quick_replies: ['🚗 اقتصادي', '🚙 كومفورت', '🚘 بريميوم']
        },
        en: {
            message: '🚗 Select vehicle type:',
            quick_replies: ['🚗 Economy', '🚙 Comfort', '🚘 Premium']
        }
    },
    
    BOOKING_CONFIRMED: {
        ar: {
            message: '✅ تم تأكيد الحجز!\n\n🔍 جاري البحث عن كابتن قريب منك...',
            quick_replies: ['📍 فين الكابتن؟', '❌ إلغاء الرحلة']
        },
        en: {
            message: '✅ Booking confirmed!\n\n🔍 Searching for a nearby driver...',
            quick_replies: ['📍 Where\'s driver?', '❌ Cancel Trip']
        }
    },
    
    CAPTAIN_FOUND: {
        ar: {
            message: '🎉 تم العثور على كابتن!\n\nالكابتن في الطريق إليك.',
            quick_replies: ['📍 فين الكابتن؟', '📞 اتصل بالكابتن', '❌ إلغاء']
        },
        en: {
            message: '🎉 Driver found!\n\nDriver is on the way.',
            quick_replies: ['📍 Where\'s driver?', '📞 Call Driver', '❌ Cancel']
        }
    },
    
    CAPTAIN_ARRIVED: {
        ar: {
            message: '📍 الكابتن وصل!\n\nالكابتن في انتظارك.',
            quick_replies: ['✅ أنا جاي', '📞 اتصل بالكابتن', '❌ إلغاء']
        },
        en: {
            message: '📍 Driver arrived!\n\nDriver is waiting for you.',
            quick_replies: ['✅ On my way', '📞 Call Driver', '❌ Cancel']
        }
    },
    
    // ============================================
    // 🚗 ACTIVE TRIP
    // ============================================
    TRIP_STARTED: {
        ar: {
            message: '🚗 الرحلة بدأت!\n\nاستمتع برحلتك.',
            quick_replies: ['📍 فين أنا؟', '🔄 تغيير الوجهة', '➕ إضافة وقفة', '⚠️ مشكلة']
        },
        en: {
            message: '🚗 Trip started!\n\nEnjoy your ride.',
            quick_replies: ['📍 Where am I?', '🔄 Change Destination', '➕ Add Stop', '⚠️ Problem']
        }
    },
    
    TRIP_COMPLETED: {
        ar: {
            message: '✅ وصلت بالسلامة!\n\nشكراً لاستخدامك سمارت لاين.',
            quick_replies: ['⭐⭐⭐⭐⭐', '⭐⭐⭐⭐', '⭐⭐⭐', '⭐⭐', '⭐']
        },
        en: {
            message: '✅ You\'ve arrived safely!\n\nThank you for using SmartLine.',
            quick_replies: ['⭐⭐⭐⭐⭐', '⭐⭐⭐⭐', '⭐⭐⭐', '⭐⭐', '⭐']
        }
    },
    
    // ============================================
    // ❌ CANCELLATION
    // ============================================
    CANCEL_CONFIRM: {
        ar: {
            message: '⚠️ هل أنت متأكد من إلغاء الرحلة؟',
            quick_replies: ['✅ نعم، إلغاء', '❌ لا، استمر']
        },
        en: {
            message: '⚠️ Are you sure you want to cancel?',
            quick_replies: ['✅ Yes, Cancel', '❌ No, Continue']
        }
    },
    
    CANCEL_REASON: {
        ar: {
            message: '📝 ليه بتلغي الرحلة؟',
            quick_replies: ['⏰ الكابتن متأخر', '🔄 غيرت رأيي', '💰 السعر عالي', '👨‍✈️ الكابتن طلب مني', '❓ سبب آخر']
        },
        en: {
            message: '📝 Why are you canceling?',
            quick_replies: ['⏰ Driver late', '🔄 Changed mind', '💰 Price too high', '👨‍✈️ Driver asked me', '❓ Other reason']
        }
    },
    
    CANCELLED: {
        ar: {
            message: '❌ تم إلغاء الرحلة.\n\nنتمنى نشوفك قريب!',
            quick_replies: ['🚗 احجز رحلة جديدة', '🏠 القائمة الرئيسية']
        },
        en: {
            message: '❌ Trip cancelled.\n\nHope to see you soon!',
            quick_replies: ['🚗 Book New Ride', '🏠 Main Menu']
        }
    },
    
    // ============================================
    // ⚠️ ISSUE REPORTING
    // ============================================
    ISSUE_START: {
        ar: {
            message: '😔 آسفين على أي مشكلة.\n\nاختر نوع المشكلة:',
            quick_replies: ['🚗 مشكلة في السيارة', '👨‍✈️ مشكلة مع الكابتن', '💰 مشكلة في السعر', '⚙️ مشكلة تقنية', '🚨 طوارئ']
        },
        en: {
            message: '😔 Sorry for any inconvenience.\n\nSelect issue type:',
            quick_replies: ['🚗 Vehicle Issue', '👨‍✈️ Captain Issue', '💰 Pricing Issue', '⚙️ Technical Issue', '🚨 Emergency']
        }
    },
    
    ISSUE_VEHICLE: {
        ar: {
            message: '🚗 مشكلة في السيارة\n\nاختر التفاصيل:',
            quick_replies: ['🧹 نظافة السيارة', '❄️ التكييف', '⚠️ مخاوف أمان', '🚗 سيارة مختلفة', '❌ إلغاء']
        },
        en: {
            message: '🚗 Vehicle Issue\n\nSelect details:',
            quick_replies: ['🧹 Cleanliness', '❄️ AC Issue', '⚠️ Safety Concern', '🚗 Different Vehicle', '❌ Cancel']
        }
    },
    
    ISSUE_CAPTAIN: {
        ar: {
            message: '👨‍✈️ مشكلة مع الكابتن\n\nاختر التفاصيل:',
            quick_replies: ['😤 سلوك غير لائق', '🚗 قيادة غير آمنة', '📵 لا يرد', '🗺️ مسار خاطئ', '❌ طلب مني الإلغاء', '💵 طلب دفع نقدي']
        },
        en: {
            message: '👨‍✈️ Captain Issue\n\nSelect details:',
            quick_replies: ['😤 Rude Behavior', '🚗 Unsafe Driving', '📵 Not Responding', '🗺️ Wrong Route', '❌ Asked to Cancel', '💵 Asked for Cash']
        }
    },
    
    ISSUE_PRICING: {
        ar: {
            message: '💰 مشكلة في السعر\n\nاختر التفاصيل:',
            quick_replies: ['💸 مبلغ زائد', '❌ سعر خاطئ', '🎟️ الخصم لم يُطبق', '❌ إلغاء']
        },
        en: {
            message: '💰 Pricing Issue\n\nSelect details:',
            quick_replies: ['💸 Overcharged', '❌ Wrong Fare', '🎟️ Promo Not Applied', '❌ Cancel']
        }
    },
    
    ISSUE_TECHNICAL: {
        ar: {
            message: '⚙️ مشكلة تقنية\n\nاختر التفاصيل:',
            quick_replies: ['📱 التطبيق يتوقف', '💳 فشل الدفع', '📍 مشكلة الموقع', '🚫 لا أستطيع الحجز', '❌ إلغاء']
        },
        en: {
            message: '⚙️ Technical Issue\n\nSelect details:',
            quick_replies: ['📱 App Crashes', '💳 Payment Failed', '📍 GPS Issue', '🚫 Cannot Book', '❌ Cancel']
        }
    },
    
    ISSUE_SUBMITTED: {
        ar: {
            message: '✅ تم استلام بلاغك!\n\nفريقنا هيتواصل معاك قريباً.',
            quick_replies: ['🏠 القائمة الرئيسية', '🚗 احجز رحلة جديدة']
        },
        en: {
            message: '✅ Report received!\n\nOur team will contact you soon.',
            quick_replies: ['🏠 Main Menu', '🚗 Book New Ride']
        }
    },
    
    // ============================================
    // 🚨 EMERGENCY
    // ============================================
    EMERGENCY_TYPES: {
        ar: {
            message: '🚨 حالة طوارئ!\n\nاختر نوع الطوارئ:',
            quick_replies: ['🚨 حادث', '⚠️ تهديد/خطر', '🏥 حالة طبية']
        },
        en: {
            message: '🚨 Emergency!\n\nSelect emergency type:',
            quick_replies: ['🚨 Accident', '⚠️ Threat/Danger', '🏥 Medical Emergency']
        }
    },
    
    EMERGENCY_RECEIVED: {
        ar: {
            message: '🚨 تم استلام بلاغ الطوارئ!\n\nفريق الدعم سيتواصل معك فوراً.\n\n📞 للطوارئ الفورية اتصل: 123',
            quick_replies: ['📞 اتصل بالدعم', '🏠 القائمة الرئيسية']
        },
        en: {
            message: '🚨 Emergency report received!\n\nSupport team will contact you immediately.\n\n📞 For immediate emergency call: 123',
            quick_replies: ['📞 Call Support', '🏠 Main Menu']
        }
    },
    
    // ============================================
    // ⚠️ MANIPULATION DETECTED
    // ============================================
    MANIPULATION_DETECTED: {
        ar: {
            message: '⚠️ نأسف لسماع ذلك!\n\nسمارت لاين تمنع الكباتن من:\n❌ طلب إلغاء الرحلة\n❌ طلب الدفع خارج التطبيق\n\n🛡️ أجرتك محمية.\n\nهل تريد الإبلاغ؟',
            quick_replies: ['📝 إبلاغ عن الكابتن', '🔄 استمر في الرحلة', '🎧 تواصل مع الدعم']
        },
        en: {
            message: '⚠️ We\'re sorry to hear that!\n\nSmartLine prohibits captains from:\n❌ Asking you to cancel\n❌ Asking for payment outside the app\n\n🛡️ Your fare is protected.\n\nWould you like to report?',
            quick_replies: ['📝 Report Captain', '🔄 Continue Trip', '🎧 Contact Support']
        }
    },
    
    EVIDENCE_OPTIONS: {
        ar: {
            message: '📎 هل لديك دليل (صورة أو تسجيل)؟',
            quick_replies: ['📸 لدي صورة', '🎙️ لدي تسجيل', '📝 وصف فقط', '❌ إلغاء البلاغ']
        },
        en: {
            message: '📎 Do you have evidence (image or recording)?',
            quick_replies: ['📸 I Have Image', '🎙️ I Have Recording', '📝 Describe Only', '❌ Cancel Report']
        }
    },
    
    // ============================================
    // ⭐ RATING
    // ============================================
    ASK_RATING: {
        ar: {
            message: '⭐ كيف كانت رحلتك؟\n\nقيم تجربتك:',
            quick_replies: ['⭐⭐⭐⭐⭐ ممتاز', '⭐⭐⭐⭐ جيد جداً', '⭐⭐⭐ جيد', '⭐⭐ مقبول', '⭐ سيء']
        },
        en: {
            message: '⭐ How was your trip?\n\nRate your experience:',
            quick_replies: ['⭐⭐⭐⭐⭐ Excellent', '⭐⭐⭐⭐ Very Good', '⭐⭐⭐ Good', '⭐⭐ Fair', '⭐ Poor']
        }
    },
    
    RATING_THANKS: {
        ar: {
            message: '🙏 شكراً على تقييمك!\n\nنسعى دائماً لتحسين خدمتنا.',
            quick_replies: ['🚗 احجز رحلة جديدة', '🏠 القائمة الرئيسية']
        },
        en: {
            message: '🙏 Thanks for your rating!\n\nWe always strive to improve.',
            quick_replies: ['🚗 Book New Ride', '🏠 Main Menu']
        }
    },
    
    LOW_RATING_FOLLOWUP: {
        ar: {
            message: '😔 نأسف أن تجربتك لم تكن جيدة.\n\nهل تريد إخبارنا بالمشكلة؟',
            quick_replies: ['👨‍✈️ مشكلة مع الكابتن', '🚗 مشكلة في السيارة', '💰 مشكلة في السعر', '✅ لا شكراً']
        },
        en: {
            message: '😔 Sorry your experience wasn\'t great.\n\nWould you like to tell us what went wrong?',
            quick_replies: ['👨‍✈️ Captain Issue', '🚗 Vehicle Issue', '💰 Pricing Issue', '✅ No Thanks']
        }
    },
    
    // ============================================
    // 🎧 HELP & SUPPORT
    // ============================================
    HELP_MENU: {
        ar: {
            message: '🎧 كيف يمكنني مساعدتك؟',
            quick_replies: ['📋 مشكلة في رحلة', '💰 مشكلة في الدفع', '📱 مشكلة تقنية', '🎧 تواصل مع موظف']
        },
        en: {
            message: '🎧 How can I help you?',
            quick_replies: ['📋 Trip Issue', '💰 Payment Issue', '📱 Technical Issue', '🎧 Talk to Agent']
        }
    },
    
    ESCALATE_TO_HUMAN: {
        ar: {
            message: '🎧 سيتواصل معك أحد موظفينا قريباً.\n\nمتوسط وقت الانتظار: 5 دقائق',
            quick_replies: ['🏠 القائمة الرئيسية']
        },
        en: {
            message: '🎧 An agent will contact you soon.\n\nAverage wait time: 5 minutes',
            quick_replies: ['🏠 Main Menu']
        }
    },
    
    // ============================================
    // 💰 WALLET & PAYMENTS
    // ============================================
    WALLET_BALANCE: {
        ar: {
            message: '💰 رصيدك الحالي:',
            quick_replies: ['💳 شحن الرصيد', '📋 سجل المعاملات', '🏠 القائمة الرئيسية']
        },
        en: {
            message: '💰 Your current balance:',
            quick_replies: ['💳 Add Balance', '📋 Transactions', '🏠 Main Menu']
        }
    },
    
    // ============================================
    // 🎟️ PROMO CODES
    // ============================================
    ASK_PROMO: {
        ar: {
            message: '🎟️ هل لديك كود خصم؟',
            quick_replies: ['🎟️ نعم، لدي كود', '❌ لا، استمر']
        },
        en: {
            message: '🎟️ Do you have a promo code?',
            quick_replies: ['🎟️ Yes, I have a code', '❌ No, continue']
        }
    },
    
    PROMO_APPLIED: {
        ar: {
            message: '✅ تم تطبيق الخصم!',
            quick_replies: ['✅ تأكيد الحجز', '❌ إلغاء']
        },
        en: {
            message: '✅ Discount applied!',
            quick_replies: ['✅ Confirm Booking', '❌ Cancel']
        }
    },
    
    PROMO_INVALID: {
        ar: {
            message: '❌ كود الخصم غير صالح أو منتهي.',
            quick_replies: ['🎟️ جرب كود آخر', '❌ استمر بدون كود']
        },
        en: {
            message: '❌ Invalid or expired promo code.',
            quick_replies: ['🎟️ Try Another Code', '❌ Continue Without Code']
        }
    },
    
    // ============================================
    // ❌ ERRORS & FALLBACKS
    // ============================================
    ERROR_GENERAL: {
        ar: {
            message: '❌ حدث خطأ. حاول مرة أخرى.',
            quick_replies: ['🔄 حاول مرة أخرى', '🏠 القائمة الرئيسية']
        },
        en: {
            message: '❌ An error occurred. Please try again.',
            quick_replies: ['🔄 Try Again', '🏠 Main Menu']
        }
    },
    
    NOT_UNDERSTOOD: {
        ar: {
            message: '🤔 معلش مش فاهم.\n\nاختر من القائمة:',
            quick_replies: ['🚗 احجز رحلة', '📍 تتبع رحلتي', '🎧 مساعدة']
        },
        en: {
            message: '🤔 Sorry, I didn\'t understand.\n\nPlease choose from the menu:',
            quick_replies: ['🚗 Book Ride', '📍 Track My Ride', '🎧 Help']
        }
    },
    
    OUT_OF_CONTEXT: {
        ar: {
            message: '😊 أنا هنا لمساعدتك في حجز رحلات سمارت لاين فقط.\n\nكيف أقدر أساعدك؟',
            quick_replies: ['🚗 احجز رحلة', '📍 تتبع رحلتي', '🎧 مساعدة']
        },
        en: {
            message: '😊 I\'m here to help you with SmartLine rides only.\n\nHow can I help you?',
            quick_replies: ['🚗 Book Ride', '📍 Track My Ride', '🎧 Help']
        }
    }
};

/**
 * Get a pre-built response by key
 * @param {string} key - Response key
 * @param {string} language - Language code ('ar' or 'en')
 * @returns {Object} - Response object
 */
function getResponse(key, language = 'ar') {
    const response = RESPONSES[key];
    if (!response) {
        return RESPONSES.NOT_UNDERSTOOD[language] || RESPONSES.NOT_UNDERSTOOD.ar;
    }
    return response[language] || response.ar;
}

/**
 * Get a response with custom data merged in
 * @param {string} key - Response key
 * @param {string} language - Language code
 * @param {Object} customData - Custom data to merge
 * @returns {Object} - Response object with custom data
 */
function getResponseWithData(key, language = 'ar', customData = {}) {
    const response = getResponse(key, language);
    return {
        ...response,
        ...customData,
        message: customData.message || response.message
    };
}

/**
 * Build a dynamic response with template variables
 * @param {string} key - Response key
 * @param {string} language - Language code
 * @param {Object} variables - Template variables
 * @returns {Object} - Response with variables replaced
 */
function buildDynamicResponse(key, language = 'ar', variables = {}) {
    const response = getResponse(key, language);
    let message = response.message;
    
    // Replace template variables
    Object.entries(variables).forEach(([varKey, value]) => {
        message = message.replace(new RegExp(`{{${varKey}}}`, 'g'), value);
    });
    
    return {
        ...response,
        message
    };
}

/**
 * Get booking confirmation response with trip summary
 */
function getBookingConfirmation(tripData, language = 'ar') {
    const labels = language === 'ar' ? {
        from: 'من',
        to: 'إلى',
        vehicle: 'نوع السيارة',
        price: 'السعر المتوقع'
    } : {
        from: 'From',
        to: 'To',
        vehicle: 'Vehicle',
        price: 'Est. Price'
    };
    
    const summary = `📋 ملخص الحجز:\n\n📍 ${labels.from}: ${tripData.pickup}\n📍 ${labels.to}: ${tripData.destination}\n🚗 ${labels.vehicle}: ${tripData.vehicle_type}\n💰 ${labels.price}: ${tripData.estimated_price} EGP`;
    
    const confirmMsg = language === 'ar' ? 'تأكيد الحجز؟' : 'Confirm booking?';
    
    return {
        message: `${summary}\n\n${confirmMsg}`,
        quick_replies: language === 'ar' 
            ? ['✅ تأكيد الحجز', '🔄 تغيير السيارة', '❌ إلغاء']
            : ['✅ Confirm', '🔄 Change Vehicle', '❌ Cancel']
    };
}

/**
 * Get captain info response
 */
function getCaptainInfo(captainData, language = 'ar') {
    const labels = language === 'ar' ? {
        name: 'الكابتن',
        vehicle: 'السيارة',
        plate: 'رقم اللوحة',
        rating: 'التقييم',
        eta: 'الوصول خلال'
    } : {
        name: 'Captain',
        vehicle: 'Vehicle',
        plate: 'Plate',
        rating: 'Rating',
        eta: 'ETA'
    };
    
    return {
        message: `👨‍✈️ ${labels.name}: ${captainData.name}\n🚗 ${labels.vehicle}: ${captainData.vehicle_model}\n🔢 ${labels.plate}: ${captainData.plate}\n⭐ ${labels.rating}: ${captainData.rating}\n⏱️ ${labels.eta}: ${captainData.eta}`,
        quick_replies: language === 'ar'
            ? ['📞 اتصل بالكابتن', '📍 فين الكابتن؟', '❌ إلغاء']
            : ['📞 Call Captain', '📍 Where\'s Captain?', '❌ Cancel']
    };
}

module.exports = {
    RESPONSES,
    getResponse,
    getResponseWithData,
    buildDynamicResponse,
    getBookingConfirmation,
    getCaptainInfo
};

