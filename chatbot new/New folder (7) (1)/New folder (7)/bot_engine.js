// ============================================
// 🤖 BOT ENGINE & STATE MACHINE
// ============================================

const Templates = require('./templates_sa');

// 🛡️ SAFETY KW DETECTION
const SAFETY_KEYWORDS = [
    'خطر', 'تحرش', 'حادث', 'شرطة', 'طوارئ', 'اسعاف', 'إسعاف',
    'دم', 'سلاح', 'سكران', 'مخدرات', 'يضرب', 'يسب', 'شتيمة',
    'خطف', 'يلاحقني', 'يمشي بسرعة', 'سرعة جنونية', 'صدم',
    'danger', 'harassment', 'accident', 'police', 'emergency', 'drunk', 'weapon'
];

const HANDOFF_KEYWORDS = [
    'أبغى موظف', 'ابغى موظف', 'اريد موظف', 'كلم موظف', 'دعم بشري',
    'انسان', 'إنسان', 'كلمني', 'اتصل بي',
    'human', 'support', 'agent'
];

class BotEngine {
    constructor() {
        // Simple in-memory state store for demo (In prod: Redis/DB)
        // Map<UserId, { state: string, data: object, history: string[] }>
        this.sessions = new Map();
    }

    // 🔍 1. DETECT SAFETY
    detectSafety(message) {
        if (!message) return false;
        return SAFETY_KEYWORDS.some(kw => message.includes(kw));
    }

    // 🔄 2. DETECT HANDOFF
    detectHandoff(message) {
        if (!message) return false;
        return HANDOFF_KEYWORDS.some(kw => message.includes(kw));
    }

    // 🆔 3. GET/INIT SESSION
    getSession(userId) {
        if (!this.sessions.has(userId)) {
            this.sessions.set(userId, {
                state: 'START',
                data: {},
                history: [],
                handoffCount: 0
            });
        }
        return this.sessions.get(userId);
    }

    resetSession(userId) {
        this.sessions.delete(userId);
    }

    // 🎮 4. MAIN PROCESS FUNCTION (Returns Rule Signals)
    processMessage(userId, userMessage, activeRide) {
        const session = this.getSession(userId);
        let nextState = session.state;
        let ruleSignals = {
            isSafety: false,
            forceHandoff: false,
            handoffAlreadyDone: session.handoffCount > 0,
            intent: null,
            parsedChoice: null,
            mustAsk: null,
            forbiddenPhrases: []
        };

        // --- GLOBAL GUARDRAILS ---

        // A. Safety Override
        const safetyCheck = this.detectSafety(userMessage);

        if (safetyCheck.isSafety) {
            session.state = 'SAFETY_ALERT';
            session.handoffCount++;
            return {
                ...ruleSignals,
                isSafety: true,
                forceHandoff: true,
                intent: 'safety_emergency'
            };
        }

        if (safetyCheck.suspectedSafety) {
            // Do NOT force handoff yet. Let LLM ask the safety question first.
            // We signal suspectedSafety -> LLM sees this and Must Ask "Are you safe?"
            session.state = 'SAFETY_ALERT'; // Move to alert state so next msg flows there
            return {
                ...ruleSignals,
                isSafety: false,
                suspectedSafety: true,
                forceHandoff: false,
                intent: 'safety_suspected',
                mustAsk: "إنت/إنتي بأمان دلوقتي؟ (نعم / لا)" // Advisory for LLM
            };
        }

        // B. Explicit Handoff Request
        if (this.detectHandoff(userMessage) && session.state !== 'ESCALATE') {
            if (session.handoffCount > 0) {
                // Prevent loop, just say "Already connecting..."
                return { ...ruleSignals, forceHandoff: true, intent: 'human_handoff', mustAsk: "(جاري التوصيل بأحد الموظفين...)" };
            }
            session.state = 'ESCALATE';
            session.handoffCount++;
            return {
                ...ruleSignals,
                forceHandoff: true,
                intent: 'human_handoff',
                mustAsk: Templates.HANDOFF_REQUEST()
            };
        }

        // --- STATE MACHINE LOGIC ---
        // (Here we determine WHAT needs to be asked, but let LLM phrase it)

        const rideHeader = Templates.RIDE_HEADER(activeRide);

        switch (session.state) {
            case 'START':
                if (activeRide && activeRide.exists) {
                    ruleSignals.mustAsk = Templates.START_WITH_RIDE(rideHeader);
                    nextState = 'RIDE_MENU';
                    ruleSignals.intent = 'menu_active_ride';
                } else {
                    ruleSignals.mustAsk = Templates.START_NO_RIDE();
                    nextState = 'GENERAL_MENU';
                    ruleSignals.intent = 'menu_general';
                }
                break;

            case 'RIDE_MENU':
                if (userMessage.includes('1') || userMessage.includes('تأخر') || userMessage.includes('يتحرك')) {
                    const eta = "3";
                    ruleSignals.mustAsk = Templates.DRIVER_LATE(activeRide?.driverName || "الكابتن", eta);
                    nextState = 'DRIVER_LATE_FLOW';
                    ruleSignals.parsedChoice = "1";
                    ruleSignals.intent = 'driver_late';
                } else if (userMessage.includes('2') || userMessage.includes('سيارة')) {
                    ruleSignals.mustAsk = "وش المشكلة في السيارة؟\n1. ريحة كريهة\n2. وسخة\n3. مكيف خربان";
                    nextState = 'CAR_ISSUE_FLOW';
                    ruleSignals.parsedChoice = "2";
                    ruleSignals.intent = 'car_issue';
                } else if (userMessage.includes('3') || userMessage.includes('سعر')) {
                    ruleSignals.mustAsk = Templates.FARE_DISPUTE("45");
                    nextState = 'FARE_FLOW';
                    ruleSignals.parsedChoice = "3";
                    ruleSignals.intent = 'fare_dispute';
                } else {
                    // Start/Menu again
                    ruleSignals.mustAsk = Templates.FALLBACK();
                }
                break;

            case 'GENERAL_MENU':
                if (userMessage.includes('1') || userMessage.includes('سابق')) {
                    ruleSignals.mustAsk = "أخر رحلة كانت للرياض مول. هل فيها مشكلة؟ (نعم/لا)";
                    nextState = 'HISTORY_FLOW';
                    ruleSignals.parsedChoice = "1";
                    ruleSignals.intent = 'previous_ride_inquiry';
                } else if (userMessage.includes('2') || userMessage.includes('جديد')) {
                    ruleSignals.mustAsk = "تقدر تطلب رحلة من الشاشة الرئيسية. أساعدك بشي ثاني؟";
                    nextState = 'RESOLVED';
                    ruleSignals.parsedChoice = "2";
                    ruleSignals.intent = 'new_ride_info';
                } else {
                    ruleSignals.mustAsk = Templates.FALLBACK();
                }
                break;

            case 'DRIVER_LATE_FLOW':
                if (userMessage.includes('1') || userMessage.includes('تتحرك') || userMessage.includes('ببطء')) {
                    ruleSignals.mustAsk = Templates.DRIVER_NOT_MOVING();
                    nextState = 'WAIT_OR_CANCEL';
                    ruleSignals.parsedChoice = "1";
                    ruleSignals.intent = 'driver_not_moving';
                } else {
                    ruleSignals.mustAsk = Templates.REFUND_OFFER();
                    nextState = 'RESOLVED';
                    ruleSignals.intent = 'driver_late_cancel';
                }
                break;

            case 'CAR_ISSUE_FLOW':
                if (userMessage.includes('1') || userMessage.includes('ريحة')) {
                    ruleSignals.mustAsk = "تم تسجيل ملاحظة بخصوص الرائحة الكريهة. هل هناك شيء آخر؟";
                    nextState = 'RESOLVED';
                    ruleSignals.parsedChoice = "1";
                    ruleSignals.intent = 'car_smell_issue';
                } else if (userMessage.includes('2') || userMessage.includes('وسخة')) {
                    ruleSignals.mustAsk = "تم تسجيل ملاحظة بخصوص نظافة السيارة. هل هناك شيء آخر؟";
                    nextState = 'RESOLVED';
                    ruleSignals.parsedChoice = "2";
                    ruleSignals.intent = 'car_dirty_issue';
                } else if (userMessage.includes('3') || userMessage.includes('مكيف')) {
                    ruleSignals.mustAsk = "تم تسجيل ملاحظة بخصوص المكيف. هل هناك شيء آخر؟";
                    nextState = 'RESOLVED';
                    ruleSignals.parsedChoice = "3";
                    ruleSignals.intent = 'car_ac_issue';
                } else {
                    ruleSignals.mustAsk = Templates.FALLBACK();
                }
                break;

            case 'FARE_FLOW':
                ruleSignals.mustAsk = "تم تسجيل اعتراضك على السعر. سيتم مراجعته والتواصل معك. هل هناك شيء آخر؟";
                nextState = 'RESOLVED';
                ruleSignals.intent = 'fare_dispute_recorded';
                break;

            case 'HISTORY_FLOW':
                if (userMessage.includes('نعم')) {
                    ruleSignals.mustAsk = "ما هي المشكلة التي واجهتها في رحلة الرياض مول؟";
                    nextState = 'RIDE_ISSUE_DETAIL';
                    ruleSignals.parsedChoice = "yes";
                    ruleSignals.intent = 'history_ride_problem';
                } else {
                    ruleSignals.mustAsk = "تمام. هل أستطيع مساعدتك بشيء آخر؟";
                    nextState = 'RESOLVED';
                    ruleSignals.parsedChoice = "no";
                    ruleSignals.intent = 'history_ride_no_problem';
                }
                break;

            case 'RIDE_ISSUE_DETAIL':
                ruleSignals.mustAsk = "شكراً لتوضيحك. تم تسجيل ملاحظتك وسيتم التواصل معك. هل هناك شيء آخر؟";
                nextState = 'RESOLVED';
                ruleSignals.intent = 'ride_issue_detailed';
                break;

            case 'WAIT_OR_CANCEL':
                if (userMessage.includes('انتظر') || userMessage.includes('انتظار')) {
                    ruleSignals.mustAsk = "تمام، سنقوم بتنبيه الكابتن. شكراً لصبرك.";
                    nextState = 'RESOLVED';
                    ruleSignals.intent = 'wait_for_driver';
                } else if (userMessage.includes('إلغاء') || userMessage.includes('الغاء')) {
                    ruleSignals.mustAsk = Templates.REFUND_OFFER();
                    nextState = 'RESOLVED';
                    ruleSignals.intent = 'cancel_ride_offer_refund';
                } else {
                    ruleSignals.mustAsk = Templates.FALLBACK();
                }
                break;

            case 'SAFETY_ALERT':
                // User answered "Yes" or "No" to "Are you safe?"
                if (userMessage.includes('لا') || userMessage.includes('no')) {
                    ruleSignals.mustAsk = Templates.SAFETY_ADVICE_UNSAFE();
                    ruleSignals.parsedChoice = "no";
                    ruleSignals.intent = 'safety_unsafe_response';
                } else {
                    ruleSignals.mustAsk = Templates.SAFETY_ADVICE_SAFE();
                    ruleSignals.parsedChoice = "yes";
                    ruleSignals.intent = 'safety_safe_response';
                }
                nextState = 'ESCALATE'; // End of bot flow
                ruleSignals.forceHandoff = true; // Ensure handoff after safety check
                break;

            case 'RESOLVED':
                ruleSignals.mustAsk = "هل أستطيع مساعدتك بشيء آخر؟";
                nextState = 'START'; // Or keep it resolved until new message
                ruleSignals.intent = 'resolved_prompt';
                break;

            default:
                // Fallback
                ruleSignals.mustAsk = Templates.FALLBACK();
                nextState = 'RIDE_MENU';
                break;
        }

        // UPDATE STATE
        session.state = nextState;
        session.history.push(nextState);

        return ruleSignals;
    }

    // ✂️ 5. ANTI-VERBOSITY
    trimOutput(text) {
        if (!text) return "";
        const lines = text.split('\n');
        if (lines.length > 6) {
            // Keep first 5 lines (usually contains the core q + options)
            return lines.slice(0, 6).join('\n');
        }
        // Char limit (approx 350 chars)
        if (text.length > 350) {
            return text.substring(0, 347) + "...";
        }
        return text;
    }
}

module.exports = new BotEngine();
