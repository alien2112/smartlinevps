// ============================================
// 🛡️ ANTI-MANIPULATION DETECTION SYSTEM V3.4
// Detects captain manipulation attempts
// ============================================

// Manipulation keywords in multiple languages
const MANIPULATION_KEYWORDS = {
    ar: [
        'كنسل', 'الغي', 'الغى', 'فلوس', 'كاش', 'نقدي', 'خارج التطبيق',
        'ادفع كاش', 'ادفعلي', 'الغي الطلب', 'كنسل الرحلة', 'احجز تاني',
        'اعمل طلب جديد', 'الغيها', 'مش هينفع', 'انا مش جاي', 'مش قادر اجي',
        'الغي واحجز', 'ادفع نقدا', 'بره التطبيق', 'كاش بس', 'نقدي بس',
        'مش هوصل', 'الغي الاوردر', 'طلب الكابتن الالغاء', 'قالي الغي'
    ],
    en: [
        'cancel', 'cash', 'outside app', 'pay me', 'cancel ride', 
        'rebook', 'new booking', 'pay direct', 'cancel order',
        'not coming', 'cancel it', 'pay cash', 'cash only',
        'outside the app', 'cancel and rebook', 'driver asked cancel',
        'captain asked', 'told me to cancel'
    ],
    arabizi: [
        'cancel', 'cash', 'feloos', 'flos', 'cancel el ride',
        'edfa3', 'edfa3li', 'barra el app', 'kan-sel', 'kansel',
        'elghy', 'elghyha', 'msh hayenfa3', 'msh gay', 'msh 2adr',
        'a7gez tany', 'e3mel order gded', 'cash bs', 'na2dy'
    ]
};

// Cancel reasons that trigger manipulation flow
const CANCEL_REASONS_TRIGGERING_FLOW = [
    'driver_asked_cancel',
    'driver_asked_cash', 
    'driver_asked_rebook',
    'captain_not_coming',
    'captain_asked_outside_payment',
    'captain_requested_cancellation'
];

// Manipulation report states
const MANIPULATION_STATES = {
    AWAITING_EVIDENCE: 'AWAITING_MANIPULATION_EVIDENCE',
    AWAITING_EVIDENCE_FILE: 'AWAITING_EVIDENCE_FILE',
    AWAITING_DESCRIPTION: 'AWAITING_MANIPULATION_DESCRIPTION',
    SUBMITTED: 'MANIPULATION_REPORT_SUBMITTED'
};

class ManipulationDetectionService {
    constructor(dbQuery, dbExecute) {
        this.dbQuery = dbQuery;
        this.dbExecute = dbExecute;
    }
    
    /**
     * Check if message contains manipulation keywords
     * @param {string} message - User's message
     * @returns {Object} - Detection result
     */
    detectManipulationKeywords(message) {
        if (!message || typeof message !== 'string') {
            return { hasKeywords: false, keywords: [], confidence: 0 };
        }
        
        const lowerMessage = message.toLowerCase();
        const allKeywords = [
            ...MANIPULATION_KEYWORDS.ar,
            ...MANIPULATION_KEYWORDS.en,
            ...MANIPULATION_KEYWORDS.arabizi
        ];
        
        const detected = allKeywords.filter(kw => 
            lowerMessage.includes(kw.toLowerCase())
        );
        
        // Calculate confidence based on number of keywords found
        const confidence = Math.min(detected.length * 25, 100);
        
        return {
            hasKeywords: detected.length > 0,
            keywords: detected,
            confidence,
            severity: confidence >= 75 ? 'HIGH' : confidence >= 50 ? 'MEDIUM' : 'LOW'
        };
    }
    
    /**
     * Check if cancel reason triggers manipulation flow
     * @param {string} cancelReason - Cancel reason code
     * @returns {boolean}
     */
    shouldTriggerManipulationFlow(cancelReason) {
        return CANCEL_REASONS_TRIGGERING_FLOW.includes(cancelReason);
    }
    
    /**
     * Start manipulation report flow
     * @param {string} language - Language code
     * @returns {Object} - Response to start flow
     */
    startManipulationReportFlow(language = 'ar') {
        const messages = {
            ar: `⚠️ نأسف لسماع ذلك!

سمارت لاين تمنع الكباتن من:
❌ طلب إلغاء الرحلة
❌ طلب الدفع خارج التطبيق
❌ طلب إعادة الحجز

🛡️ أجرتك محمية ولن تدفع أي مبلغ إضافي.

هل تريد الإبلاغ عن هذا الكابتن؟ سنحتاج دليل (صورة أو تسجيل صوتي).`,
            
            en: `⚠️ We're sorry to hear that!

SmartLine prohibits captains from:
❌ Asking you to cancel
❌ Asking for payment outside the app
❌ Asking you to rebook

🛡️ Your fare is protected - no extra charges.

Would you like to report this captain? We'll need evidence (image or audio).`
        };
        
        return {
            message: messages[language] || messages.ar,
            quick_replies: language === 'ar' 
                ? ['📸 رفع صورة', '🎙️ رفع تسجيل صوتي', '📝 وصف فقط', '❌ إلغاء البلاغ']
                : ['📸 Upload Image', '🎙️ Upload Audio', '📝 Describe Only', '❌ Cancel Report'],
            new_state: MANIPULATION_STATES.AWAITING_EVIDENCE,
            action: 'request_evidence',
            data: { report_type: 'manipulation' }
        };
    }
    
    /**
     * Handle evidence upload request
     * @param {string} evidenceType - Type of evidence ('image' or 'audio')
     * @param {string} language - Language code
     * @returns {Object} - Instructions for upload
     */
    handleEvidenceUploadRequest(evidenceType, language = 'ar') {
        const instructions = {
            image: {
                ar: `📸 من فضلك ارفع صورة توضح المشكلة.

يجب أن تحتوي الصورة على:
✅ رسالة من الكابتن تطلب الإلغاء أو الدفع نقداً
✅ صورة واضحة للمحادثة

⚠️ الصور المعدلة أو القديمة سيتم رفضها.`,
                en: `📸 Please upload an image showing the issue.

The image should contain:
✅ A message from the captain asking to cancel or pay cash
✅ Clear screenshot of the conversation

⚠️ Edited or old images will be rejected.`
            },
            audio: {
                ar: `🎙️ من فضلك ارفع تسجيل صوتي.

متطلبات التسجيل:
✅ مدة لا تقل عن 5 ثواني
✅ صوت واضح
✅ يحتوي على طلب الكابتن

⚠️ التسجيلات غير الواضحة سيتم رفضها.`,
                en: `🎙️ Please upload an audio recording.

Recording requirements:
✅ At least 5 seconds long
✅ Clear audio
✅ Contains the captain's request

⚠️ Unclear recordings will be rejected.`
            },
            description: {
                ar: `📝 من فضلك اكتب وصف تفصيلي للمشكلة:

اذكر:
• ماذا قال الكابتن؟
• متى حدث ذلك؟
• أي تفاصيل أخرى مهمة`,
                en: `📝 Please write a detailed description:

Include:
• What did the captain say?
• When did this happen?
• Any other important details`
            }
        };
        
        const type = evidenceType === 'audio' ? 'audio' : 
                     evidenceType === 'description' ? 'description' : 'image';
        
        return {
            message: instructions[type]?.[language] || instructions.image.ar,
            new_state: type === 'description' 
                ? MANIPULATION_STATES.AWAITING_DESCRIPTION 
                : MANIPULATION_STATES.AWAITING_EVIDENCE_FILE,
            action: type === 'description' ? 'request_description' : 'open_file_picker',
            data: { 
                evidence_type: type,
                accepted_types: type === 'image' 
                    ? ['image/jpeg', 'image/png'] 
                    : type === 'audio'
                        ? ['audio/m4a', 'audio/mp3', 'audio/mpeg', 'audio/wav']
                        : []
            }
        };
    }
    
    /**
     * Create manipulation report
     * @param {string} userId - User ID
     * @param {string} tripId - Trip ID
     * @param {string} captainId - Captain ID
     * @param {Object} reportData - Report data
     * @returns {Object} - Result with report ID
     */
    async createManipulationReport(userId, tripId, captainId, reportData) {
        const reportId = `MNP-${Date.now()}-${Math.random().toString(36).substr(2, 6).toUpperCase()}`;
        
        try {
            await this.dbExecute(`
                INSERT INTO manipulation_reports 
                (id, trip_id, captain_id, rider_id, report_type, description, 
                 keywords_detected, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'PENDING', NOW())
            `, [
                reportId,
                tripId,
                captainId,
                userId,
                reportData.type || 'manipulation',
                reportData.description,
                JSON.stringify(reportData.keywords || [])
            ]);
            
            console.log('[ManipulationReport] Created:', reportId);
            
            return {
                success: true,
                report_id: reportId
            };
        } catch (error) {
            console.error('[ManipulationReport] Create failed:', error);
            return {
                success: false,
                error: error.message
            };
        }
    }
    
    /**
     * Update report with evidence
     * @param {string} reportId - Report ID
     * @param {string} evidenceId - Evidence file ID
     * @param {string} evidenceType - Type of evidence
     * @returns {Object} - Update result
     */
    async updateReportWithEvidence(reportId, evidenceId, evidenceType) {
        try {
            await this.dbExecute(`
                UPDATE manipulation_reports 
                SET evidence_id = ?, evidence_type = ?, updated_at = NOW()
                WHERE id = ?
            `, [evidenceId, evidenceType, reportId]);
            
            return { success: true };
        } catch (error) {
            console.error('[ManipulationReport] Update evidence failed:', error);
            return { success: false, error: error.message };
        }
    }
    
    /**
     * Get captain's manipulation report history
     * @param {string} captainId - Captain ID
     * @returns {Object} - Report history
     */
    async getCaptainReportHistory(captainId) {
        try {
            const reports = await this.dbQuery(`
                SELECT * FROM manipulation_reports 
                WHERE captain_id = ?
                ORDER BY created_at DESC
                LIMIT 10
            `, [captainId]);
            
            const validatedReports = reports.filter(r => 
                r.status === 'VALIDATED' || r.status === 'STRIKE_APPLIED'
            );
            
            return {
                total: reports.length,
                validated: validatedReports.length,
                reports
            };
        } catch (error) {
            console.error('[ManipulationReport] Get history failed:', error);
            return { total: 0, validated: 0, reports: [] };
        }
    }
}

module.exports = { 
    ManipulationDetectionService, 
    MANIPULATION_KEYWORDS, 
    CANCEL_REASONS_TRIGGERING_FLOW,
    MANIPULATION_STATES
};

