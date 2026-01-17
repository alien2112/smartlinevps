// ============================================
// 📋 ISSUE REPORTING SYSTEM V3.4.1
// Saves to DB + Notifies back-office
// Easy quick replies for customers
// ============================================

const { v4: uuidv4 } = require('uuid');

// Issue Categories with icons and quick replies
const ISSUE_CATEGORIES = {
    VEHICLE: {
        id: 'vehicle',
        name_ar: 'مشكلة في السيارة',
        name_en: 'Vehicle Issue',
        icon: '🚗',
        priority: 'MEDIUM',
        subcategories: [
            { id: 'cleanliness', name_ar: 'نظافة السيارة', name_en: 'Cleanliness', icon: '🧹' },
            { id: 'ac_not_working', name_ar: 'التكييف لا يعمل', name_en: 'AC not working', icon: '❄️' },
            { id: 'safety_concern', name_ar: 'مخاوف أمان', name_en: 'Safety concern', icon: '⚠️' },
            { id: 'different_vehicle', name_ar: 'سيارة مختلفة', name_en: 'Different vehicle', icon: '🚗' },
            { id: 'bad_condition', name_ar: 'حالة سيئة', name_en: 'Poor condition', icon: '🔧' }
        ]
    },
    CAPTAIN: {
        id: 'captain',
        name_ar: 'مشكلة مع الكابتن',
        name_en: 'Captain Issue',
        icon: '👨‍✈️',
        priority: 'HIGH',
        subcategories: [
            { id: 'rude_behavior', name_ar: 'سلوك غير لائق', name_en: 'Rude behavior', icon: '😤' },
            { id: 'unsafe_driving', name_ar: 'قيادة غير آمنة', name_en: 'Unsafe driving', icon: '🚗' },
            { id: 'not_responding', name_ar: 'لا يرد', name_en: 'Not responding', icon: '📵' },
            { id: 'wrong_route', name_ar: 'مسار خاطئ', name_en: 'Wrong route', icon: '🗺️' },
            { id: 'asked_to_cancel', name_ar: 'طلب الإلغاء', name_en: 'Asked to cancel', icon: '❌' },
            { id: 'asked_for_cash', name_ar: 'طلب دفع نقدي', name_en: 'Asked for cash', icon: '💵' },
            { id: 'no_show', name_ar: 'لم يحضر', name_en: 'No show', icon: '👻' }
        ]
    },
    PRICING: {
        id: 'pricing',
        name_ar: 'مشكلة في السعر',
        name_en: 'Pricing Issue',
        icon: '💰',
        priority: 'MEDIUM',
        subcategories: [
            { id: 'overcharged', name_ar: 'مبلغ زائد', name_en: 'Overcharged', icon: '💸' },
            { id: 'wrong_fare', name_ar: 'سعر خاطئ', name_en: 'Wrong fare', icon: '❌' },
            { id: 'promo_not_applied', name_ar: 'الخصم لم يُطبق', name_en: 'Promo not applied', icon: '🎟️' },
            { id: 'double_charged', name_ar: 'خصم مرتين', name_en: 'Double charged', icon: '💳' }
        ]
    },
    TECHNICAL: {
        id: 'technical',
        name_ar: 'مشكلة تقنية',
        name_en: 'Technical Issue',
        icon: '⚙️',
        priority: 'LOW',
        subcategories: [
            { id: 'app_crash', name_ar: 'التطبيق يتوقف', name_en: 'App crashes', icon: '📱' },
            { id: 'payment_failed', name_ar: 'فشل الدفع', name_en: 'Payment failed', icon: '💳' },
            { id: 'gps_issue', name_ar: 'مشكلة الموقع', name_en: 'GPS issue', icon: '📍' },
            { id: 'cant_book', name_ar: 'لا أستطيع الحجز', name_en: 'Cannot book', icon: '🚫' },
            { id: 'notification_issue', name_ar: 'مشكلة الإشعارات', name_en: 'Notification issue', icon: '🔔' }
        ]
    },
    EMERGENCY: {
        id: 'emergency',
        name_ar: 'طوارئ',
        name_en: 'Emergency',
        icon: '🚨',
        priority: 'CRITICAL',
        subcategories: [
            { id: 'accident', name_ar: 'حادث', name_en: 'Accident', icon: '🚨' },
            { id: 'threat', name_ar: 'تهديد/خطر', name_en: 'Threat/Danger', icon: '⚠️' },
            { id: 'medical', name_ar: 'حالة طبية', name_en: 'Medical emergency', icon: '🏥' },
            { id: 'harassment', name_ar: 'تحرش', name_en: 'Harassment', icon: '🛑' }
        ]
    }
};

// Issue Report States
const ISSUE_STATES = {
    AWAITING_CATEGORY: 'AWAITING_ISSUE_CATEGORY',
    AWAITING_SUBCATEGORY: 'AWAITING_ISSUE_SUBCATEGORY',
    AWAITING_DESCRIPTION: 'AWAITING_ISSUE_DESCRIPTION',
    AWAITING_EVIDENCE: 'AWAITING_ISSUE_EVIDENCE',
    SUBMITTED: 'ISSUE_SUBMITTED'
};

class IssueReportingService {
    constructor(dbQuery, dbExecute, backofficeNotifier) {
        this.dbQuery = dbQuery;
        this.dbExecute = dbExecute;
        this.notifier = backofficeNotifier;
    }
    
    /**
     * Start issue reporting flow with quick replies
     */
    startReportFlow(language = 'ar') {
        const categories = Object.values(ISSUE_CATEGORIES);
        
        const message = language === 'ar'
            ? '😔 آسفين على أي مشكلة.\n\nاختر نوع المشكلة:'
            : '😔 Sorry for any inconvenience.\n\nSelect issue type:';
        
        const quickReplies = categories.map(cat => 
            `${cat.icon} ${language === 'ar' ? cat.name_ar : cat.name_en}`
        );
        
        return {
            message,
            quick_replies: quickReplies,
            new_state: ISSUE_STATES.AWAITING_CATEGORY,
            action: 'show_issue_categories'
        };
    }
    
    /**
     * Handle category selection
     */
    handleCategorySelection(input, language = 'ar') {
        const normalizedInput = input.toLowerCase().replace(/[🚗👨‍✈️💰⚙️🚨]/g, '').trim();
        
        const category = Object.values(ISSUE_CATEGORIES).find(cat => 
            cat.name_ar.includes(normalizedInput) || 
            cat.name_en.toLowerCase().includes(normalizedInput) ||
            cat.id === normalizedInput ||
            normalizedInput.includes(cat.name_ar) ||
            normalizedInput.includes(cat.name_en.toLowerCase())
        );
        
        if (!category) {
            return {
                message: language === 'ar' 
                    ? '❌ اختيار غير صحيح. اختر من القائمة:'
                    : '❌ Invalid selection. Choose from the list:',
                quick_replies: Object.values(ISSUE_CATEGORIES).map(c => 
                    `${c.icon} ${language === 'ar' ? c.name_ar : c.name_en}`
                )
            };
        }
        
        // Emergency gets special handling
        if (category.priority === 'CRITICAL') {
            return {
                isEmergency: true,
                category,
                message: language === 'ar'
                    ? '🚨 حالة طوارئ!\n\nاختر نوع الطوارئ:'
                    : '🚨 Emergency!\n\nSelect emergency type:',
                quick_replies: category.subcategories.map(sub => 
                    `${sub.icon} ${language === 'ar' ? sub.name_ar : sub.name_en}`
                ),
                new_state: ISSUE_STATES.AWAITING_SUBCATEGORY,
                data: { selected_category: category.id, is_emergency: true }
            };
        }
        
        return {
            message: language === 'ar'
                ? `${category.icon} ${category.name_ar}\n\nاختر التفاصيل:`
                : `${category.icon} ${category.name_en}\n\nSelect details:`,
            quick_replies: category.subcategories.map(sub => 
                `${sub.icon} ${language === 'ar' ? sub.name_ar : sub.name_en}`
            ),
            new_state: ISSUE_STATES.AWAITING_SUBCATEGORY,
            data: { selected_category: category.id }
        };
    }
    
    /**
     * Handle subcategory selection
     */
    handleSubcategorySelection(input, categoryId, language = 'ar') {
        const category = Object.values(ISSUE_CATEGORIES).find(c => c.id === categoryId);
        if (!category) {
            return this.startReportFlow(language);
        }
        
        const normalizedInput = input.toLowerCase().replace(/[🧹❄️⚠️🚗🔧😤📵🗺️❌💵👻💸🎟️💳📱📍🚫🔔🚨🏥🛑]/g, '').trim();
        
        const subcategory = category.subcategories.find(sub =>
            sub.name_ar.includes(normalizedInput) ||
            sub.name_en.toLowerCase().includes(normalizedInput) ||
            sub.id === normalizedInput ||
            normalizedInput.includes(sub.name_ar) ||
            normalizedInput.includes(sub.name_en.toLowerCase())
        );
        
        if (!subcategory) {
            return {
                message: language === 'ar'
                    ? '❌ اختيار غير صحيح. اختر من القائمة:'
                    : '❌ Invalid selection. Choose from the list:',
                quick_replies: category.subcategories.map(sub => 
                    `${sub.icon} ${language === 'ar' ? sub.name_ar : sub.name_en}`
                )
            };
        }
        
        // Check for manipulation (captain asking to cancel/pay cash)
        const manipulationTypes = ['asked_to_cancel', 'asked_for_cash'];
        if (manipulationTypes.includes(subcategory.id)) {
            return {
                isManipulation: true,
                message: language === 'ar'
                    ? '⚠️ نأسف لسماع ذلك!\n\nسمارت لاين تمنع الكباتن من:\n❌ طلب الإلغاء\n❌ طلب الدفع نقداً\n\n🛡️ أجرتك محمية.\n\nهل تريد الإبلاغ؟'
                    : '⚠️ We\'re sorry!\n\nSmartLine prohibits captains from:\n❌ Asking to cancel\n❌ Asking for cash\n\n🛡️ Your fare is protected.\n\nWould you like to report?',
                quick_replies: language === 'ar'
                    ? ['📝 إبلاغ', '📸 لدي صورة', '🎙️ لدي تسجيل', '❌ إلغاء']
                    : ['📝 Report', '📸 I Have Image', '🎙️ I Have Recording', '❌ Cancel'],
                new_state: 'AWAITING_MANIPULATION_EVIDENCE',
                data: { 
                    selected_category: categoryId, 
                    selected_subcategory: subcategory.id,
                    is_manipulation: true
                }
            };
        }
        
        // Ask if they want to add description or submit directly
        return {
            message: language === 'ar'
                ? `📋 ${subcategory.icon} ${subcategory.name_ar}\n\nهل تريد إضافة تفاصيل؟`
                : `📋 ${subcategory.icon} ${subcategory.name_en}\n\nWould you like to add details?`,
            quick_replies: language === 'ar'
                ? ['✅ إرسال البلاغ', '📝 إضافة تفاصيل', '📸 إضافة صورة', '❌ إلغاء']
                : ['✅ Submit Report', '📝 Add Details', '📸 Add Image', '❌ Cancel'],
            new_state: ISSUE_STATES.AWAITING_DESCRIPTION,
            data: { 
                selected_category: categoryId, 
                selected_subcategory: subcategory.id 
            }
        };
    }
    
    /**
     * Submit report - SAVES TO DB + NOTIFIES BACK-OFFICE
     */
    async submitReport(userId, tripId, reportData, language = 'ar') {
        const reportId = `RPT-${Date.now()}-${uuidv4().slice(0, 8).toUpperCase()}`;
        
        // Get category priority
        const category = Object.values(ISSUE_CATEGORIES).find(c => c.id === reportData.category);
        const priority = category?.priority || 'MEDIUM';
        
        try {
            // 1. SAVE TO DATABASE
            await this.dbExecute(`
                INSERT INTO issue_reports 
                (id, user_id, trip_id, captain_id, category, subcategory, description, 
                 status, priority, has_evidence, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'PENDING', ?, ?, NOW())
            `, [
                reportId,
                userId,
                tripId,
                reportData.captain_id || null,
                reportData.category,
                reportData.subcategory,
                reportData.description || null,
                priority,
                reportData.has_evidence ? 1 : 0
            ]);
            
            // 2. NOTIFY BACK-OFFICE
            if (this.notifier) {
                await this.notifier.notify({
                    type: 'ISSUE_REPORT',
                    priority: priority === 'CRITICAL' ? 1 : priority === 'HIGH' ? 2 : 3,
                    title: `📋 New Issue Report: ${reportData.category}`,
                    data: {
                        report_id: reportId,
                        user_id: userId,
                        trip_id: tripId,
                        captain_id: reportData.captain_id,
                        category: reportData.category,
                        subcategory: reportData.subcategory,
                        description: reportData.description,
                        has_evidence: reportData.has_evidence,
                        is_manipulation: reportData.is_manipulation
                    },
                    action_required: priority === 'CRITICAL' ? 'IMMEDIATE' : 'REVIEW',
                    suggested_action: this.getSuggestedAction(reportData.category, reportData.subcategory)
                });
            }
            
            console.log('[IssueReporting] Report submitted:', reportId);
            
            return {
                success: true,
                report_id: reportId,
                message: language === 'ar'
                    ? `✅ تم استلام بلاغك!\n\n📋 رقم البلاغ: ${reportId}\n\nفريقنا سيراجع البلاغ ويتواصل معك.`
                    : `✅ Report received!\n\n📋 Report ID: ${reportId}\n\nOur team will review and contact you.`,
                quick_replies: language === 'ar'
                    ? ['🏠 القائمة الرئيسية', '🚗 احجز رحلة جديدة']
                    : ['🏠 Main Menu', '🚗 Book New Ride']
            };
            
        } catch (error) {
            console.error('[IssueReporting] Submit error:', error);
            return {
                success: false,
                message: language === 'ar'
                    ? '❌ حدث خطأ. حاول مرة أخرى أو اتصل بالدعم.'
                    : '❌ An error occurred. Please try again or contact support.',
                quick_replies: language === 'ar'
                    ? ['🔄 حاول مرة أخرى', '📞 اتصل بالدعم']
                    : ['🔄 Try Again', '📞 Call Support']
            };
        }
    }
    
    /**
     * Handle emergency - IMMEDIATE SAVE + NOTIFY
     */
    async handleEmergency(userId, tripId, subcategoryId, language = 'ar') {
        const alertId = `EMR-${Date.now()}-${uuidv4().slice(0, 8).toUpperCase()}`;
        
        try {
            // 1. SAVE IMMEDIATELY
            await this.dbExecute(`
                INSERT INTO emergency_alerts 
                (id, user_id, trip_id, category, subcategory, status, priority, created_at)
                VALUES (?, ?, ?, 'emergency', ?, 'PENDING', 'CRITICAL', NOW())
            `, [alertId, userId, tripId, subcategoryId]);
            
            // 2. NOTIFY BACK-OFFICE IMMEDIATELY
            if (this.notifier) {
                await this.notifier.notify({
                    type: 'EMERGENCY',
                    priority: 1, // CRITICAL
                    title: '🚨 EMERGENCY ALERT',
                    data: {
                        alert_id: alertId,
                        user_id: userId,
                        trip_id: tripId,
                        emergency_type: subcategoryId
                    },
                    action_required: 'IMMEDIATE_CONTACT',
                    auto_escalate: true
                });
            }
            
            console.log('[IssueReporting] EMERGENCY ALERT:', alertId);
            
            return {
                success: true,
                alert_id: alertId,
                message: language === 'ar'
                    ? `🚨 تم استلام بلاغ الطوارئ!\n\n📋 رقم البلاغ: ${alertId}\n\nفريق الدعم سيتواصل معك فوراً.\n\n📞 للطوارئ الفورية: 123`
                    : `🚨 Emergency received!\n\n📋 Alert ID: ${alertId}\n\nSupport will contact you immediately.\n\n📞 Emergency: 123`,
                quick_replies: language === 'ar'
                    ? ['📞 اتصل بالدعم', '🏠 القائمة الرئيسية']
                    : ['📞 Call Support', '🏠 Main Menu'],
                action: 'emergency_alert'
            };
            
        } catch (error) {
            console.error('[IssueReporting] Emergency error:', error);
            return {
                success: false,
                message: language === 'ar'
                    ? '❌ حدث خطأ. اتصل بالطوارئ: 123'
                    : '❌ Error. Call emergency: 123'
            };
        }
    }
    
    /**
     * Get suggested action for back-office
     */
    getSuggestedAction(category, subcategory) {
        const actions = {
            vehicle: {
                cleanliness: 'Log for captain review, offer apology',
                ac_not_working: 'Log for captain review, consider partial refund',
                safety_concern: 'URGENT: Contact customer, review captain',
                different_vehicle: 'Verify vehicle records, contact captain'
            },
            captain: {
                rude_behavior: 'Review trip, contact both parties',
                unsafe_driving: 'URGENT: Review, consider warning',
                not_responding: 'Check captain status, assist customer',
                wrong_route: 'Review route, check fare adjustment',
                asked_to_cancel: 'MANIPULATION: Review for strike',
                asked_for_cash: 'MANIPULATION: Review for strike'
            },
            pricing: {
                overcharged: 'Review fare, process refund if needed',
                wrong_fare: 'Verify fare, adjust if needed',
                promo_not_applied: 'Check promo, apply manually if valid'
            },
            technical: {
                app_crash: 'Log for dev team',
                payment_failed: 'Check payment gateway',
                gps_issue: 'Log for dev team',
                cant_book: 'Troubleshoot, offer phone booking'
            }
        };
        
        return actions[category]?.[subcategory] || 'Review and take appropriate action';
    }
    
    /**
     * Get report status
     */
    async getReportStatus(reportId, language = 'ar') {
        try {
            const reports = await this.dbQuery(`
                SELECT * FROM issue_reports WHERE id = ?
            `, [reportId]);
            
            if (reports.length === 0) {
                return {
                    found: false,
                    message: language === 'ar'
                        ? '❌ البلاغ غير موجود'
                        : '❌ Report not found'
                };
            }
            
            const report = reports[0];
            const statusText = {
                PENDING: { ar: '⏳ قيد المراجعة', en: '⏳ Under review' },
                IN_PROGRESS: { ar: '🔄 جاري المعالجة', en: '🔄 In progress' },
                RESOLVED: { ar: '✅ تم الحل', en: '✅ Resolved' },
                CLOSED: { ar: '📁 مغلق', en: '📁 Closed' }
            };
            
            const status = statusText[report.status] || statusText.PENDING;
            
            return {
                found: true,
                report_id: reportId,
                status: report.status,
                message: language === 'ar'
                    ? `📋 حالة البلاغ ${reportId}:\n\n${status.ar}`
                    : `📋 Report ${reportId} status:\n\n${status.en}`,
                quick_replies: language === 'ar'
                    ? ['🏠 القائمة الرئيسية']
                    : ['🏠 Main Menu']
            };
        } catch (error) {
            return {
                found: false,
                error: error.message
            };
        }
    }
}

module.exports = { 
    IssueReportingService, 
    ISSUE_CATEGORIES, 
    ISSUE_STATES 
};
