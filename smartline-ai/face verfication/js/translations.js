/**
 * Translations Manager for Face Liveness Verification
 * Supports English and Arabic with RTL layout
 */
const Translations = {
    currentLang: 'en',

    translations: {
        en: {
            // Header
            appTitle: 'LivenessGuard',
            statusInitializing: 'Initializing...',
            statusReady: 'Ready',
            statusVerifying: 'Verifying...',
            statusStartingCamera: 'Starting camera...',
            statusVerified: 'Verified',
            statusFailed: 'Failed',
            statusError: 'Error',

            // Welcome Screen
            welcomeTitle: 'Face Liveness Verification',
            welcomeSubtitle: 'Secure identity verification with advanced anti-spoofing technology',
            featureRealtime: 'Real-time Detection',
            featureRealtimeDesc: 'Advanced AI-powered face detection',
            featureAntiSpoof: 'Anti-Spoofing',
            featureAntiSpoofDesc: 'Liveness challenges prevent fraud',
            featureVideo: 'Video Recording',
            featureVideoDesc: 'Secure verification evidence',
            featureInstant: 'Instant Results',
            featureInstantDesc: 'Fast and accurate verification',
            startBtn: 'Start Verification',

            // Camera Screen
            challengePosition: 'Position your face in the frame',
            recording: 'Recording',
            statusLabel: 'Status:',
            confidenceLabel: 'Confidence:',
            challengeLabel: 'Challenge:',
            detecting: 'Detecting...',
            faceDetected: 'Face detected',
            noFaceDetected: 'No face detected',
            multipleFaces: 'Multiple faces detected!',
            cancelBtn: 'Cancel',
            retryBtn: 'Retry',

            // Challenges
            challengeBlink: 'Please blink your eyes',
            challengeSmile: 'Please smile naturally',
            challengeHeadTurn: 'Turn your head slowly left, then right',
            challengeMouthOpen: 'Please open your mouth',
            challengeEyebrowRaise: 'Please raise your eyebrows',
            challengeEyeMovement: 'Look left, then right with your eyes',

            // Results Screen
            resultSuccess: 'Verification Successful!',
            resultFailed: 'Verification Failed',
            resultSuccessMsg: 'Your identity has been verified successfully.',
            resultFailedMsg: 'Liveness check failed. Please try again.',
            verificationTime: 'Verification Time:',
            challengesCompleted: 'Challenges Completed:',
            avgConfidence: 'Average Confidence:',
            timestamp: 'Timestamp:',
            securityAnalysis: '🛡️ Security Analysis',
            activeLiveness: 'Active Liveness',
            passiveLiveness: 'Passive Liveness',
            antiSpoofing: 'Anti-Spoofing',
            depthAnalysis: 'Depth Analysis',
            eyeReflection: 'Eye Reflection',
            microExpressions: 'Micro-Expressions',
            combinedScore: 'Combined Security Score:',
            downloadBtn: 'Download Video',
            newVerificationBtn: 'New Verification',

            // Footer
            footer: 'Powered by TensorFlow.js • Secure & Privacy-First',

            // Loading
            loadingText: 'Loading AI models...',
            loadingDepth: 'Loading depth estimation model...',
            loadingPassive: 'Loading passive liveness analyzer...',
            loadingComplete: 'All models loaded successfully!',
            loadingError: 'Error loading models. Please refresh the page.',

            // Language
            langToggle: 'العربية'
        },

        ar: {
            // Header
            appTitle: 'حارس الهوية',
            statusInitializing: 'جاري التهيئة...',
            statusReady: 'جاهز',
            statusVerifying: 'جاري التحقق...',
            statusStartingCamera: 'تشغيل الكاميرا...',
            statusVerified: 'تم التحقق',
            statusFailed: 'فشل',
            statusError: 'خطأ',

            // Welcome Screen
            welcomeTitle: 'التحقق من الهوية الحية',
            welcomeSubtitle: 'التحقق الآمن من الهوية باستخدام تقنية مكافحة الانتحال المتقدمة',
            featureRealtime: 'الكشف الفوري',
            featureRealtimeDesc: 'كشف الوجه بالذكاء الاصطناعي',
            featureAntiSpoof: 'مكافحة الانتحال',
            featureAntiSpoofDesc: 'تحديات حية لمنع الاحتيال',
            featureVideo: 'تسجيل الفيديو',
            featureVideoDesc: 'دليل التحقق الآمن',
            featureInstant: 'نتائج فورية',
            featureInstantDesc: 'تحقق سريع ودقيق',
            startBtn: 'بدء التحقق',

            // Camera Screen
            challengePosition: 'ضع وجهك في الإطار',
            recording: 'جاري التسجيل',
            statusLabel: 'الحالة:',
            confidenceLabel: 'الثقة:',
            challengeLabel: 'التحدي:',
            detecting: 'جاري الكشف...',
            faceDetected: 'تم اكتشاف الوجه',
            noFaceDetected: 'لم يتم اكتشاف وجه',
            multipleFaces: 'تم اكتشاف عدة وجوه!',
            cancelBtn: 'إلغاء',
            retryBtn: 'إعادة المحاولة',

            // Challenges
            challengeBlink: 'يرجى رمش عينيك',
            challengeSmile: 'يرجى الابتسام بشكل طبيعي',
            challengeHeadTurn: 'أدر رأسك ببطء لليسار ثم اليمين',
            challengeMouthOpen: 'يرجى فتح فمك',
            challengeEyebrowRaise: 'يرجى رفع حاجبيك',
            challengeEyeMovement: 'انظر لليسار ثم اليمين بعينيك',

            // Results Screen
            resultSuccess: 'تم التحقق بنجاح!',
            resultFailed: 'فشل التحقق',
            resultSuccessMsg: 'تم التحقق من هويتك بنجاح.',
            resultFailedMsg: 'فشل فحص الحيوية. يرجى المحاولة مرة أخرى.',
            verificationTime: 'وقت التحقق:',
            challengesCompleted: 'التحديات المكتملة:',
            avgConfidence: 'متوسط الثقة:',
            timestamp: 'الطابع الزمني:',
            securityAnalysis: '🛡️ تحليل الأمان',
            activeLiveness: 'الحيوية النشطة',
            passiveLiveness: 'الحيوية السلبية',
            antiSpoofing: 'مكافحة الانتحال',
            depthAnalysis: 'تحليل العمق',
            eyeReflection: 'انعكاس العين',
            microExpressions: 'التعبيرات الدقيقة',
            combinedScore: 'النتيجة الأمنية الإجمالية:',
            downloadBtn: 'تحميل الفيديو',
            newVerificationBtn: 'تحقق جديد',

            // Footer
            footer: 'مدعوم بـ TensorFlow.js • آمن وخاص',

            // Loading
            loadingText: 'جاري تحميل نماذج الذكاء الاصطناعي...',
            loadingDepth: 'جاري تحميل نموذج تقدير العمق...',
            loadingPassive: 'جاري تحميل محلل الحيوية السلبية...',
            loadingComplete: 'تم تحميل جميع النماذج بنجاح!',
            loadingError: 'خطأ في تحميل النماذج. يرجى تحديث الصفحة.',

            // Language
            langToggle: 'English'
        }
    },

    /**
     * Get translation for a key
     */
    t(key) {
        return this.translations[this.currentLang][key] || this.translations['en'][key] || key;
    },

    /**
     * Set current language
     */
    setLanguage(lang) {
        if (this.translations[lang]) {
            this.currentLang = lang;
            localStorage.setItem('livenessLang', lang);
            this.updateUI();
            this.updateDirection();
        }
    },

    /**
     * Toggle between languages
     */
    toggle() {
        const newLang = this.currentLang === 'en' ? 'ar' : 'en';
        this.setLanguage(newLang);
    },

    /**
     * Update text direction for RTL/LTR
     */
    updateDirection() {
        const html = document.documentElement;
        if (this.currentLang === 'ar') {
            html.setAttribute('dir', 'rtl');
            html.setAttribute('lang', 'ar');
            document.body.classList.add('rtl');
        } else {
            html.setAttribute('dir', 'ltr');
            html.setAttribute('lang', 'en');
            document.body.classList.remove('rtl');
        }
    },

    /**
     * Update all UI elements with translations
     */
    updateUI() {
        // Update all elements with data-i18n attribute
        document.querySelectorAll('[data-i18n]').forEach(el => {
            const key = el.getAttribute('data-i18n');
            if (key) {
                el.textContent = this.t(key);
            }
        });

        // Update placeholders
        document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
            const key = el.getAttribute('data-i18n-placeholder');
            if (key) {
                el.placeholder = this.t(key);
            }
        });

        // Update page title
        document.title = this.t('welcomeTitle');
    },

    /**
     * Initialize translations
     */
    init() {
        // Check for saved language preference
        const savedLang = localStorage.getItem('livenessLang');
        if (savedLang && this.translations[savedLang]) {
            this.currentLang = savedLang;
        }
        this.updateDirection();
        this.updateUI();
    }
};

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = Translations;
}
