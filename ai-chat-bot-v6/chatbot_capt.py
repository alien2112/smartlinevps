"""
🚗 RIDE-HAILING CAPTAIN SUPPORT CHATBOT
=======================================
Production-ready chatbot for captain registration support.
Supports: Arabic, English, Arabizi
"""

import re
from typing import Optional, Dict, List
from dataclasses import dataclass
from datetime import datetime


# ============================================
# BAD WORDS FILTER
# ============================================

class BadWordsFilter:
    """Filter inappropriate language in all 3 supported languages."""
    
    def __init__(self):
        self.bad_words = {
            'english': [
                'damn', 'shit', 'fuck', 'ass', 'bitch', 'hell', 'crap',
                'bastard', 'idiot', 'stupid', 'moron', 'dumb', 'jerk',
                'screw', 'suck', 'piss', 'bloody', 'bugger', 'dick',
                'asshole', 'bullshit', 'retard', 'slut', 'whore'
            ],
            'arabic': [
                'كلب', 'حمار', 'غبي', 'خرا', 'تفو', 'لعنة', 'احمق',
                'منيك', 'شرموط', 'عرص', 'زفت', 'قذر', 'واطي',
                'حقير', 'نجس', 'كس', 'طيز', 'زق', 'متخلف',
                'ابن الكلب', 'يلعن', 'خول', 'عاهرة', 'شرموطة'
            ],
            'arabizi': [
                'kelb', '7mar', '5ara', 'kos', 'a7a', 'sharmo6', 'sharmou6',
                '3ars', 'zeft', 'manyak', 'manyik', 'wes5', 'a5a', 'teez',
                '6eez', 'zo2', 'kosomak', 'ya7mar', 'ya kelb', 'ibn el kalb',
                'kharا', '5awal', 'mot5alef', 'ghabi', '8abi'
            ]
        }
        self._compile_patterns()
    
    def _compile_patterns(self):
        """Compile regex patterns for efficient matching."""
        self.patterns = []
        for lang_words in self.bad_words.values():
            for word in lang_words:
                self.patterns.append(
                    re.compile(rf'\b{re.escape(word)}\b', re.IGNORECASE | re.UNICODE)
                )
    
    def filter_text(self, text: str) -> str:
        """Replace bad words with asterisks."""
        filtered = text
        for pattern in self.patterns:
            filtered = pattern.sub('***', filtered)
        return filtered
    
    def contains_bad_words(self, text: str) -> bool:
        """Check if text contains any bad words."""
        for pattern in self.patterns:
            if pattern.search(text):
                return True
        return False
    
    def clean_name(self, name: str) -> str:
        """Clean captain name from bad words and normalize."""
        cleaned = self.filter_text(name)
        cleaned = ' '.join(cleaned.split())  # Normalize whitespace
        return cleaned[:50] if len(cleaned) > 50 else cleaned  # Limit length


# ============================================
# RESPONSE TEMPLATES
# ============================================

RESPONSES = {
    # ==========================================
    # UNDER REVIEW
    # ==========================================
    'under_review': {
        'arabic': """مرحباً كابتن {captain_name} 👋

شكراً لتواصلك معنا!

طلب التسجيل الخاص بك قيد المراجعة حالياً من قبل فريقنا المختص. نحن نعمل على مراجعة جميع المستندات بعناية لضمان أفضل تجربة لك.

سنقوم بإشعارك فور الانتهاء من المراجعة.

نقدر صبرك وتفهمك 🙏""",

        'english': """Hello Captain {captain_name} 👋

Thank you for reaching out!

Your registration request is currently under review by our team. We are carefully reviewing all your documents to ensure the best experience for you.

You will be notified as soon as the review is complete.

We appreciate your patience 🙏""",

        'arabizi': """Ahlan Captain {captain_name} 👋

Shokran 3ala el tawasol!

Talab el tasjeel beta3ak 7alياً under review men el team beta3na. E7na bنراجع kol el documents beta3tak b3enaya.

Ha neb3atlak notification awel ma nkhalas.

Neshkor sabrak 🙏"""
    },

    # ==========================================
    # DOCUMENTS MISSING
    # ==========================================
    'documents_missing': {
        'arabic': """مرحباً كابتن {captain_name} 👋

شكراً لتواصلك معنا.

لاحظنا أن بعض المستندات المطلوبة ناقصة أو تحتاج إلى تحديث.

📄 الخطوات المطلوبة:
• افتح التطبيق وادخل على حسابك
• اذهب إلى قسم "المستندات"
• ارفع المستندات الناقصة بصورة واضحة
• تأكد أن جميع الوثائق سارية المفعول

بمجرد استلام المستندات الكاملة، سنراجع طلبك فوراً ✅

نحن هنا لمساعدتك!""",

        'english': """Hello Captain {captain_name} 👋

Thank you for contacting us.

We noticed that some required documents are missing or need to be updated.

📄 Required Steps:
• Open the app and log into your account
• Go to the "Documents" section
• Upload the missing documents in clear quality
• Make sure all documents are valid and not expired

Once we receive the complete documents, we'll review your request right away ✅

We're here to help!""",

        'arabizi': """Ahlan Captain {captain_name} 👋

Shokran 3ala el tawasol.

La7azna en fi documents na2sa aw me7taga update.

📄 El Khatawat el Matloba:
• Efta7 el app w login 3ala account-ak
• Ro7 3ala section el "Documents"
• Upload el documents el na2sa b sora wade7a
• Eta2kad en kol el documents sari7a

Awel ma nestalem el documents kamla, ha nراجع talab-ak 3ala tool ✅

E7na hena 3ashan nesa3dak!"""
    },

    # ==========================================
    # APPROVED
    # ==========================================
    'approved': {
        'arabic': """مبروك كابتن {captain_name}! 🎉

يسعدنا إخبارك بأن طلب التسجيل الخاص بك قد تمت الموافقة عليه!

✅ يمكنك الآن:
• تسجيل الدخول إلى حسابك
• تفعيل وضع "متصل"
• البدء في قبول الرحلات
• تحقيق الأرباح!

مرحباً بك في العائلة! نتمنى لك رحلة موفقة 🚗

بالتوفيق!""",

        'english': """Congratulations Captain {captain_name}! 🎉

We're happy to inform you that your registration has been approved!

✅ You can now:
• Log into your account
• Turn on "Online" mode
• Start accepting rides
• Start earning!

Welcome to the family! We wish you a great journey 🚗

Good luck!""",

        'arabizi': """Mabrook Captain {captain_name}! 🎉

Mabsooteen n2ollak en talab el tasjeel beta3ak etm el mowaf2a 3aleh!

✅ Delwa2ty te2dar:
• Login 3ala account-ak
• Sha8al "Online" mode
• Tebda2 te2bal re7lat
• Tebda2 tekسب floos!

Ahlan bik fi el 3aila! Netmannalek re7la mowafa2a 🚗

Bel tawfi2!"""
    },

    # ==========================================
    # REJECTED
    # ==========================================
    'rejected': {
        'arabic': """مرحباً كابتن {captain_name} 👋

شكراً لاهتمامك بالانضمام إلينا.

نأسف لإبلاغك بأن طلب التسجيل الخاص بك لم يتم قبوله في الوقت الحالي.

🔄 خياراتك:
• التواصل مع فريق الدعم لمعرفة التفاصيل
• إعادة التقديم بعد معالجة أسباب الرفض
• تقديم استئناف إذا كنت تعتقد أن هناك خطأ

للاستفسار، نحن هنا لمساعدتك.

نتمنى لك كل التوفيق 🙏""",

        'english': """Hello Captain {captain_name} 👋

Thank you for your interest in joining us.

We regret to inform you that your registration request has not been accepted at this time.

🔄 Your options:
• Contact our support team for more details
• Reapply after addressing the rejection reasons
• Submit an appeal if you believe there was an error

For inquiries, we're here to help.

We wish you all the best 🙏""",

        'arabizi': """Ahlan Captain {captain_name} 👋

Shokran 3ala ehtimamak bel join ma3ana.

Mota2asfeen n2ollak en talab el tasjeel beta3ak ma etmش 2aboloh delwa2ty.

🔄 El e5tiyarat beta3tak:
• Etواصل ma3 el support team 3ashan ta3raf el tafaseel
• 2addem tany ba3d ma t3aleg asbab el rafd
• 2addem appeal law fakker en fi 8alat

Lel este5sarat, e7na hena 3ashan nesa3dak.

Netmannalek kol el tawfi2 🙏"""
    },

    # ==========================================
    # BACKGROUND CHECK
    # ==========================================
    'background_check': {
        'arabic': """مرحباً كابتن {captain_name} 👋

شكراً لتواصلك معنا.

طلب التسجيل الخاص بك يخضع حالياً للفحص الأمني. هذه خطوة ضرورية لضمان سلامة جميع المستخدمين على المنصة.

🔒 معلومات مهمة:
• هذه العملية قد تستغرق بضعة أيام
• لا تحتاج لاتخاذ أي إجراء
• سنشعرك فور اكتمال الفحص

نشكر صبرك وتعاونك! 🙏""",

        'english': """Hello Captain {captain_name} 👋

Thank you for reaching out.

Your registration is currently undergoing a background check. This is a necessary step to ensure the safety of all users on our platform.

🔒 Important information:
• This process may take a few days
• No action is required from you
• You'll be notified once the check is complete

Thank you for your patience and cooperation! 🙏""",

        'arabizi': """Ahlan Captain {captain_name} 👋

Shokran 3ala el tawasol.

Talab el tasjeel beta3ak 7alياً fi marhalet el fa7s el amny. Dي step darورiya 3ashan ned-man safety kol el users 3al platform.

🔒 Ma3lomat mohemma:
• El process da momken yakhod kam yom
• Mesh me7tag te3mel ay 7aga
• Ha neb3atlak notification awel ma nkhalas

Neshkor sabrak w ta3awonak! 🙏"""
    },

    # ==========================================
    # SYSTEM DELAY
    # ==========================================
    'system_delay': {
        'arabic': """مرحباً كابتن {captain_name} 👋

شكراً لتواصلك معنا.

نعتذر عن التأخير في معالجة طلبك. نواجه حالياً ضغطاً كبيراً على النظام بسبب كثرة الطلبات.

⏳ ما يجب أن تعرفه:
• طلبك في قائمة الانتظار ولن يُفقد
• نعمل بأقصى سرعة لمراجعة جميع الطلبات
• سنتواصل معك فور تحديث حالة طلبك

نقدر صبرك الكبير ونعتذر مجدداً عن الإزعاج 🙏""",

        'english': """Hello Captain {captain_name} 👋

Thank you for reaching out.

We apologize for the delay in processing your request. We're currently experiencing high volume due to many applications.

⏳ What you should know:
• Your request is in queue and won't be lost
• We're working as fast as possible to review all requests
• We'll contact you once your status is updated

We appreciate your patience and apologize for any inconvenience 🙏""",

        'arabizi': """Ahlan Captain {captain_name} 👋

Shokran 3ala el tawasol.

Beta3tezر 3an el ta2kheer fi mo3alget talab-ak. 3andena daght kebeer 3al system delwa2ty bisabab ketret el talabat.

⏳ El lazem ta3rafo:
• Talab-ak fi el queue w mesh ha yed-ya3
• E7na shaghaleen bأقصى sor3a 3ashan nراجع kol el talabat
• Ha netواصل ma3ak awel ma 7alet talab-ak tet-update

Neshkor sabrak gedan w beta3tezر tany 3an el ez3ag 🙏"""
    }
}

# ==========================================
# GENERAL INQUIRY RESPONSES
# ==========================================

GENERAL_RESPONSES = {
    'greeting': {
        'arabic': """مرحباً كابتن {captain_name} 👋

أهلاً بك! كيف يمكنني مساعدتك اليوم؟""",

        'english': """Hello Captain {captain_name} 👋

Welcome! How can I help you today?""",

        'arabizi': """Ahlan Captain {captain_name} 👋

Welcome! Ezay a2dar asa3dak el naharda?"""
    },

    'thank_you': {
        'arabic': """شكراً لك كابتن {captain_name}! 

نحن سعداء بخدمتك. إذا كان لديك أي استفسار آخر، نحن هنا دائماً 🙏""",

        'english': """Thank you Captain {captain_name}!

We're happy to help. If you have any other questions, we're always here 🙏""",

        'arabizi': """Shokran Captain {captain_name}!

E7na mabsooteen nesa3dak. Law 3andak ay so2al tany, e7na hena dayman 🙏"""
    },

    'unknown': {
        'arabic': """مرحباً كابتن {captain_name} 👋

شكراً لتواصلك. لم أتمكن من فهم طلبك بشكل واضح.

هل يمكنك إعادة صياغة سؤالك أو اختيار أحد الخيارات التالية؟
• الاستفسار عن حالة التسجيل
• المستندات المطلوبة
• التواصل مع الدعم الفني

نحن هنا لمساعدتك!""",

        'english': """Hello Captain {captain_name} 👋

Thank you for reaching out. I couldn't clearly understand your request.

Could you please rephrase your question or choose one of the following options?
• Check registration status
• Required documents
• Contact technical support

We're here to help!""",

        'arabizi': """Ahlan Captain {captain_name} 👋

Shokran 3ala el tawasol. Ma2dertش afham talab-ak kwayes.

Momken te3eed tekteb so2alak aw tekhtar wa7ed men el options dي?
• El este5sar 3an 7alet el tasjeel
• El documents el matloba
• El tawasol ma3 el support

E7na hena 3ashan nesa3dak!"""
    }
}


# ============================================
# CHATBOT CLASS
# ============================================

@dataclass
class ChatbotResponse:
    """Structure for chatbot response."""
    message: str
    captain_name: str
    language: str
    status: str
    timestamp: str
    success: bool
    error: Optional[str] = None


class CaptainSupportChatbot:
    """
    Production-ready chatbot for captain registration support.
    Supports Arabic, English, and Arabizi.
    """
    
    VALID_LANGUAGES = ['arabic', 'english', 'arabizi']
    VALID_STATUSES = [
        'under_review', 'documents_missing', 'approved',
        'rejected', 'background_check', 'system_delay'
    ]
    
    def __init__(self):
        self.filter = BadWordsFilter()
        self.responses = RESPONSES
        self.general_responses = GENERAL_RESPONSES
    
    def get_status_response(
        self,
        captain_name: str,
        language: str,
        registration_status: str
    ) -> ChatbotResponse:
        """
        Generate response based on captain's registration status.
        
        Args:
            captain_name: Name of the captain
            language: Language preference (arabic/english/arabizi)
            registration_status: Current registration status
            
        Returns:
            ChatbotResponse object with the message
        """
        # Clean and validate captain name
        clean_name = self.filter.clean_name(captain_name)
        if not clean_name or clean_name == '***':
            clean_name = 'Captain'
        
        # Validate and normalize language
        language = language.lower().strip()
        if language not in self.VALID_LANGUAGES:
            language = 'english'
        
        # Validate status
        registration_status = registration_status.lower().strip()
        if registration_status not in self.VALID_STATUSES:
            return ChatbotResponse(
                message=self.general_responses['unknown'][language].format(captain_name=clean_name),
                captain_name=clean_name,
                language=language,
                status='unknown',
                timestamp=datetime.now().isoformat(),
                success=False,
                error=f"Invalid status: {registration_status}"
            )
        
        # Get response template
        try:
            template = self.responses[registration_status][language]
            message = template.format(captain_name=clean_name)
            
            return ChatbotResponse(
                message=message,
                captain_name=clean_name,
                language=language,
                status=registration_status,
                timestamp=datetime.now().isoformat(),
                success=True
            )
        except KeyError as e:
            return ChatbotResponse(
                message=self.general_responses['unknown'][language].format(captain_name=clean_name),
                captain_name=clean_name,
                language=language,
                status=registration_status,
                timestamp=datetime.now().isoformat(),
                success=False,
                error=f"Template error: {str(e)}"
            )
    
    def get_greeting(self, captain_name: str, language: str) -> str:
        """Get greeting message."""
        clean_name = self.filter.clean_name(captain_name)
        language = language.lower() if language.lower() in self.VALID_LANGUAGES else 'english'
        return self.general_responses['greeting'][language].format(captain_name=clean_name)
    
    def get_thank_you(self, captain_name: str, language: str) -> str:
        """Get thank you message."""
        clean_name = self.filter.clean_name(captain_name)
        language = language.lower() if language.lower() in self.VALID_LANGUAGES else 'english'
        return self.general_responses['thank_you'][language].format(captain_name=clean_name)
    
    def get_unknown_response(self, captain_name: str, language: str) -> str:
        """Get response for unknown queries."""
        clean_name = self.filter.clean_name(captain_name)
        language = language.lower() if language.lower() in self.VALID_LANGUAGES else 'english'
        return self.general_responses['unknown'][language].format(captain_name=clean_name)
    
    def process_message(
        self,
        captain_name: str,
        language: str,
        registration_status: str,
        user_message: Optional[str] = None
    ) -> str:
        """
        Main method to process captain message and return response.
        
        This is the primary method to call from your backend.
        
        Args:
            captain_name: Captain's name
            language: Language preference
            registration_status: Current status
            user_message: Optional message from captain (for future NLP)
            
        Returns:
            Response message string
        """
        # Filter any bad words from user message if provided
        if user_message and self.filter.contains_bad_words(user_message):
            # Log this incident (in production, you'd log to a proper system)
            pass
        
        response = self.get_status_response(captain_name, language, registration_status)
        return response.message


# ============================================
# SIMPLE API FUNCTIONS
# ============================================

# Initialize global chatbot instance
_chatbot = CaptainSupportChatbot()


def get_captain_response(
    captain_name: str,
    language: str,
    registration_status: str
) -> str:
    """
    Simple function to get chatbot response.
    
    Usage:
        message = get_captain_response("Ahmed", "arabic", "under_review")
        print(message)
    """
    return _chatbot.process_message(captain_name, language, registration_status)


def get_response_dict(
    captain_name: str,
    language: str,
    registration_status: str
) -> Dict:
    """
    Get response as dictionary (useful for APIs).
    
    Usage:
        result = get_response_dict("Ahmed", "arabic", "under_review")
        # Returns: {"message": "...", "success": True, ...}
    """
    response = _chatbot.get_status_response(captain_name, language, registration_status)
    return {
        'message': response.message,
        'captain_name': response.captain_name,
        'language': response.language,
        'status': response.status,
        'timestamp': response.timestamp,
        'success': response.success,
        'error': response.error
    }


# ============================================
# FLASK API EXAMPLE
# ============================================

"""
from flask import Flask, request, jsonify

app = Flask(__name__)

@app.route('/api/captain/message', methods=['POST'])
def captain_message():
    data = request.get_json()
    
    result = get_response_dict(
        captain_name=data.get('captain_name', 'Captain'),
        language=data.get('language', 'english'),
        registration_status=data.get('registration_status', 'under_review')
    )
    
    return jsonify(result)

if __name__ == '__main__':
    app.run(debug=True)
"""


# ============================================
# FASTAPI EXAMPLE
# ============================================

"""
from fastapi import FastAPI
from pydantic import BaseModel

app = FastAPI()

class CaptainRequest(BaseModel):
    captain_name: str
    language: str = 'english'
    registration_status: str

@app.post("/api/captain/message")
async def captain_message(request: CaptainRequest):
    return get_response_dict(
        captain_name=request.captain_name,
        language=request.language,
        registration_status=request.registration_status
    )
"""


# ============================================
# TESTING
# ============================================

if __name__ == "__main__":
    print("=" * 60)
    print("🚗 CAPTAIN SUPPORT CHATBOT - TESTING")
    print("=" * 60)
    
    # Test all statuses in all languages
    test_cases = [
        ("أحمد حسن", "arabic", "under_review"),
        ("John Smith", "english", "approved"),
        ("Mohamed", "arabizi", "documents_missing"),
        ("Sara Ahmed", "arabic", "rejected"),
        ("Ali Hassan", "english", "background_check"),
        ("Omar", "arabizi", "system_delay"),
    ]
    
    for name, lang, status in test_cases:
        print(f"\n{'='*60}")
        print(f"📋 Captain: {name} | Language: {lang} | Status: {status}")
        print("-" * 60)
        message = get_captain_response(name, lang, status)
        print(message)
    
    # Test bad word filtering
    print(f"\n{'='*60}")
    print("🚫 BAD WORD FILTER TEST")
    print("-" * 60)
    bad_name = "Ahmed damn shit"
    message = get_captain_response(bad_name, "english", "approved")
    print(f"Input name: '{bad_name}'")
    print(f"Filtered response:\n{message}")
    
    print(f"\n{'='*60}")
    print("✅ ALL TESTS COMPLETED")
    print("=" * 60)
