// ============================================
// 🎨 UI COMPONENTS V3.4.1
// Smart UI builders for easy customer interaction
// ============================================

/**
 * Build a card-style message with title, body, and optional footer
 */
function buildCard(options) {
    const { title, body, footer, icon } = options;
    let card = '';
    
    if (icon && title) {
        card += `${icon} **${title}**\n\n`;
    } else if (title) {
        card += `**${title}**\n\n`;
    }
    
    if (body) {
        card += `${body}\n`;
    }
    
    if (footer) {
        card += `\n─────────────────\n${footer}`;
    }
    
    return card;
}

/**
 * Build a summary card for trip/booking
 */
function buildTripSummary(tripData, language = 'ar') {
    const labels = {
        ar: {
            from: '📍 من',
            to: '📍 إلى',
            vehicle: '🚗 نوع السيارة',
            price: '💰 السعر المتوقع',
            eta: '⏱️ الوقت المتوقع',
            captain: '👨‍✈️ الكابتن',
            plate: '🚘 رقم السيارة'
        },
        en: {
            from: '📍 From',
            to: '📍 To',
            vehicle: '🚗 Vehicle',
            price: '💰 Est. Price',
            eta: '⏱️ ETA',
            captain: '👨‍✈️ Captain',
            plate: '🚘 Plate'
        }
    };
    
    const l = labels[language] || labels.ar;
    let summary = '';
    
    if (tripData.pickup) summary += `${l.from}: ${tripData.pickup}\n`;
    if (tripData.destination) summary += `${l.to}: ${tripData.destination}\n`;
    if (tripData.vehicle_type) summary += `${l.vehicle}: ${tripData.vehicle_type}\n`;
    if (tripData.estimated_price) summary += `${l.price}: ${tripData.estimated_price} EGP\n`;
    if (tripData.eta) summary += `${l.eta}: ${tripData.eta}\n`;
    if (tripData.captain_name) summary += `${l.captain}: ${tripData.captain_name}\n`;
    if (tripData.vehicle_plate) summary += `${l.plate}: ${tripData.vehicle_plate}\n`;
    
    return summary.trim();
}

/**
 * Build a numbered list for selection
 */
function buildNumberedList(items, language = 'ar') {
    const header = language === 'ar' ? '👆 اختر رقم:' : '👆 Choose a number:';
    const list = items.map((item, i) => `${i + 1}. ${item}`).join('\n');
    return `${header}\n\n${list}`;
}

/**
 * Build a bullet list
 */
function buildBulletList(items, bullet = '•') {
    return items.map(item => `${bullet} ${item}`).join('\n');
}

/**
 * Build a status indicator
 */
function buildStatus(status, language = 'ar') {
    const statuses = {
        pending: { ar: '⏳ قيد الانتظار', en: '⏳ Pending' },
        searching: { ar: '🔍 جاري البحث عن كابتن', en: '🔍 Searching for driver' },
        accepted: { ar: '✅ تم قبول الرحلة', en: '✅ Ride accepted' },
        arriving: { ar: '🚗 الكابتن في الطريق', en: '🚗 Driver on the way' },
        arrived: { ar: '📍 الكابتن وصل', en: '📍 Driver arrived' },
        in_progress: { ar: '🚗 الرحلة جارية', en: '🚗 Trip in progress' },
        completed: { ar: '✅ تمت الرحلة', en: '✅ Trip completed' },
        cancelled: { ar: '❌ تم الإلغاء', en: '❌ Cancelled' }
    };
    
    const s = statuses[status.toLowerCase()];
    return s ? (s[language] || s.ar) : status;
}

/**
 * Build a progress indicator
 */
function buildProgress(current, total, language = 'ar') {
    const filled = '●';
    const empty = '○';
    const progress = filled.repeat(current) + empty.repeat(total - current);
    
    const label = language === 'ar' 
        ? `الخطوة ${current} من ${total}` 
        : `Step ${current} of ${total}`;
    
    return `${progress} ${label}`;
}

/**
 * Build a confirmation prompt
 */
function buildConfirmation(message, language = 'ar') {
    const confirm = language === 'ar' ? '✅ تأكيد' : '✅ Confirm';
    const cancel = language === 'ar' ? '❌ إلغاء' : '❌ Cancel';
    
    return {
        message: `${message}\n\n${language === 'ar' ? 'هل أنت متأكد؟' : 'Are you sure?'}`,
        quick_replies: [confirm, cancel]
    };
}

/**
 * Build an error message with retry option
 */
function buildError(errorType, language = 'ar') {
    const errors = {
        network: {
            ar: '❌ حدث خطأ في الاتصال. حاول مرة أخرى.',
            en: '❌ Connection error. Please try again.'
        },
        invalid_input: {
            ar: '❌ المدخلات غير صحيحة. حاول مرة أخرى.',
            en: '❌ Invalid input. Please try again.'
        },
        not_found: {
            ar: '❌ غير موجود. حاول مرة أخرى.',
            en: '❌ Not found. Please try again.'
        },
        timeout: {
            ar: '⏱️ انتهت المهلة. حاول مرة أخرى.',
            en: '⏱️ Request timed out. Please try again.'
        },
        general: {
            ar: '❌ حدث خطأ. حاول مرة أخرى.',
            en: '❌ An error occurred. Please try again.'
        }
    };
    
    const error = errors[errorType] || errors.general;
    const retry = language === 'ar' ? '🔄 حاول مرة أخرى' : '🔄 Try Again';
    const menu = language === 'ar' ? '🏠 القائمة الرئيسية' : '🏠 Main Menu';
    
    return {
        message: error[language] || error.ar,
        quick_replies: [retry, menu]
    };
}

/**
 * Build a rating UI
 */
function buildRatingUI(language = 'ar') {
    const message = language === 'ar'
        ? '⭐ كيف كانت رحلتك؟\n\nاختر تقييمك:'
        : '⭐ How was your trip?\n\nSelect your rating:';
    
    return {
        message,
        quick_replies: ['⭐', '⭐⭐', '⭐⭐⭐', '⭐⭐⭐⭐', '⭐⭐⭐⭐⭐'],
        action: 'show_rating'
    };
}

/**
 * Build a vehicle selection UI
 */
function buildVehicleSelection(vehicles, language = 'ar') {
    const header = language === 'ar'
        ? '🚗 اختر نوع السيارة:'
        : '🚗 Select vehicle type:';
    
    const defaultVehicles = [
        { id: 'economy', name_ar: 'اقتصادي', name_en: 'Economy', icon: '🚗', price: '~25 EGP' },
        { id: 'comfort', name_ar: 'كومفورت', name_en: 'Comfort', icon: '🚙', price: '~35 EGP' },
        { id: 'premium', name_ar: 'بريميوم', name_en: 'Premium', icon: '🚘', price: '~50 EGP' }
    ];
    
    const vehicleList = vehicles || defaultVehicles;
    
    const options = vehicleList.map(v => {
        const name = language === 'ar' ? v.name_ar : v.name_en;
        return `${v.icon} ${name} (${v.price})`;
    });
    
    const quickReplies = vehicleList.map(v => {
        const name = language === 'ar' ? v.name_ar : v.name_en;
        return `${v.icon} ${name}`;
    });
    
    return {
        message: `${header}\n\n${options.join('\n')}`,
        quick_replies: quickReplies,
        action: 'select_vehicle',
        data: { vehicles: vehicleList }
    };
}

/**
 * Build an issue category selection UI
 */
function buildIssueCategoryUI(categories, language = 'ar') {
    const header = language === 'ar'
        ? '😔 آسفين على أي مشكلة.\n\nاختر نوع المشكلة:'
        : '😔 Sorry for any inconvenience.\n\nSelect issue type:';
    
    const quickReplies = categories.map(cat => {
        const name = language === 'ar' ? cat.name_ar : cat.name_en;
        return `${cat.icon} ${name}`;
    });
    
    return {
        message: header,
        quick_replies: quickReplies,
        action: 'select_issue_category'
    };
}

/**
 * Build a help menu UI
 */
function buildHelpMenu(language = 'ar') {
    const message = language === 'ar'
        ? '🎧 كيف يمكنني مساعدتك؟'
        : '🎧 How can I help you?';
    
    const options = language === 'ar'
        ? ['📋 مشكلة في رحلة', '💰 مشكلة في الدفع', '📱 مشكلة تقنية', '🎧 تواصل مع موظف']
        : ['📋 Trip Issue', '💰 Payment Issue', '📱 Technical Issue', '🎧 Talk to Agent'];
    
    return {
        message,
        quick_replies: options,
        action: 'show_help_menu'
    };
}

/**
 * Build a location input UI
 */
function buildLocationInput(type, language = 'ar', favorites = []) {
    const isPickup = type === 'pickup';
    
    const message = language === 'ar'
        ? (isPickup ? '📍 من فين عايز تتحرك؟' : '📍 عايز تروح فين؟')
        : (isPickup ? '📍 Where do you want to be picked up?' : '📍 Where do you want to go?');
    
    let quickReplies = language === 'ar'
        ? ['📍 موقعي الحالي', '🏠 البيت', '🏢 الشغل']
        : ['📍 Current Location', '🏠 Home', '🏢 Work'];
    
    // Add favorites if available
    if (favorites && favorites.length > 0) {
        const favOptions = favorites.slice(0, 2).map(f => `⭐ ${f.name}`);
        quickReplies = [...quickReplies.slice(0, 1), ...favOptions, ...quickReplies.slice(1)];
    }
    
    // Add "Other" option
    quickReplies.push(language === 'ar' ? '✏️ مكان آخر' : '✏️ Other Place');
    
    return {
        message,
        quick_replies: quickReplies.slice(0, 6), // Max 6 options
        action: isPickup ? 'get_pickup' : 'get_destination'
    };
}

/**
 * Build a wallet/balance UI
 */
function buildWalletUI(balance, language = 'ar') {
    const message = language === 'ar'
        ? `💰 رصيدك الحالي: ${balance} EGP`
        : `💰 Your balance: ${balance} EGP`;
    
    const options = language === 'ar'
        ? ['💳 شحن الرصيد', '📋 سجل المعاملات', '🏠 القائمة الرئيسية']
        : ['💳 Add Balance', '📋 Transactions', '🏠 Main Menu'];
    
    return {
        message,
        quick_replies: options,
        action: 'show_wallet'
    };
}

/**
 * Build a promo code input UI
 */
function buildPromoCodeUI(language = 'ar') {
    const message = language === 'ar'
        ? '🎟️ هل لديك كود خصم؟'
        : '🎟️ Do you have a promo code?';
    
    const options = language === 'ar'
        ? ['🎟️ نعم، لدي كود', '❌ لا، استمر بدون كود']
        : ['🎟️ Yes, I have a code', '❌ No, continue without code'];
    
    return {
        message,
        quick_replies: options,
        action: 'ask_promo'
    };
}

module.exports = {
    buildCard,
    buildTripSummary,
    buildNumberedList,
    buildBulletList,
    buildStatus,
    buildProgress,
    buildConfirmation,
    buildError,
    buildRatingUI,
    buildVehicleSelection,
    buildIssueCategoryUI,
    buildHelpMenu,
    buildLocationInput,
    buildWalletUI,
    buildPromoCodeUI
};

